<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace fileconverter_heic;

use core_files\conversion;

/**
 * Converts HEIC and HEIF images to formats Moodle can display.
 *
 * Moodle asks document converters for a PDF when a teacher opens a submission
 * in the assignment grader. With no converter for .heic, the grader shows a
 * blank page. This converter returns a one-page PDF of the photo, the same
 * shape Moodle builds itself for a JPEG submission.
 *
 * Moodle stores each result against the source file's content, so one image
 * is converted once per target format.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter implements \core_files\converter_interface {

    /** @var string[] Source extensions, lower case. */
    const FROM = image_converter::EXTENSIONS;

    /** @var array<string, string> Target extension => format requested from the conversion server. */
    const TO = [
        'pdf' => 'jpeg',
        'jpg' => 'jpeg',
        'jpeg' => 'jpeg',
        'webp' => 'webp',
    ];

    /**
     * Whether the converter is ready to use.
     *
     * True when this server can convert HEIC itself, or cloud conversion is
     * set up. Makes no network request. Moodle calls this every time it looks
     * for a converter.
     *
     * @return bool
     */
    public static function are_requirements_met() {
        return image_converter::is_ready();
    }

    /**
     * Whether a conversion between two formats is supported.
     *
     * iPhones name photos IMG_0001.HEIC, so the comparison ignores case.
     *
     * @param  string $from Source file extension.
     * @param  string $to   Target file extension.
     * @return bool
     */
    public static function supports($from, $to) {
        return in_array(strtolower((string) $from), self::FROM, true)
            && array_key_exists(strtolower((string) $to), self::TO);
    }

    /**
     * A list of the supported conversions for the Manage document converters page.
     *
     * @return string
     */
    public function get_supported_conversions() {
        return get_string('supportedconversions', 'fileconverter_heic');
    }

    /**
     * Converts the file. The conversion is finished or failed when this returns.
     *
     * @param  conversion $conversion The file to be converted.
     * @return $this
     */
    public function start_document_conversion(conversion $conversion) {
        $file = $conversion->get_sourcefile();
        $from = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
        $to = strtolower((string) $conversion->get('targetformat'));

        if (!self::supports($from, $to)) {
            return $this->fail($conversion, get_string('error_unsupported', 'fileconverter_heic'));
        }

        $dir = make_request_directory();
        $source = $dir . '/source.heic';
        if ($file->copy_content_to($source) === false) {
            return $this->fail($conversion, get_string('error_copyfailed', 'fileconverter_heic'));
        }

        \core_php_time_limit::raise(180);

        try {
            [$image, $route] = image_converter::convert($source, $file->get_filename(), self::TO[$to]);
        } catch (conversion_exception $e) {
            $detail = $e->debuginfo ? " ({$e->debuginfo})" : '';
            return $this->fail($conversion, $e->getMessage() . $detail);
        }

        $dest = $dir . '/converted.' . $to;
        try {
            if ($to === 'pdf') {
                $this->image_to_pdf($image, $dest);
            } else {
                file_put_contents($dest, $image);
            }
        } catch (\Throwable $e) {
            // TCPDF puts the whole image in the message when the data is inline.
            $message = strstr($e->getMessage(), '@', true) ?: $e->getMessage();
            return $this->fail($conversion, get_string('error_pdffailed', 'fileconverter_heic') . " ({$message})");
        }
        if (!file_exists($dest) || filesize($dest) === 0) {
            return $this->fail($conversion, get_string('error_convertempty', 'fileconverter_heic'));
        }

        $conversion
            ->store_destfile_from_path($dest)
            ->set('status', conversion::STATUS_COMPLETE)
            ->set('statusmessage', null)
            ->set('data', $route)
            ->update();

        return $this;
    }

    /**
     * Nothing to poll. Conversions finish inside start_document_conversion().
     *
     * @param  conversion $conversion The file being converted.
     * @return $this
     */
    public function poll_conversion_status(conversion $conversion) {
        return $this;
    }

    /**
     * Marks the conversion failed and records why.
     *
     * @param  conversion $conversion The conversion.
     * @param  string     $reason     Why it failed.
     * @return $this
     */
    protected function fail(conversion $conversion, string $reason) {
        $conversion
            ->set('status', conversion::STATUS_FAILED)
            ->set('statusmessage', $reason)
            ->update();
        debugging('fileconverter_heic: ' . $reason, DEBUG_NORMAL);
        return $this;
    }

    /**
     * Writes a one-page PDF holding the image, scaled to fit the page.
     *
     * @param string $image Image contents (JPEG).
     * @param string $dest  Path of the PDF to write.
     */
    protected function image_to_pdf(string $image, string $dest): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $size = getimagesizefromstring($image);
        if (!$size || empty($size[0]) || empty($size[1])) {
            throw new \RuntimeException('not an image');
        }
        [$width, $height] = $size;
        $orientation = $width > $height ? 'L' : 'P';

        $pdf = new \pdf($orientation);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->setPrintFooter(false);
        $pdf->setPrintHeader(false);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->AddPage($orientation);
        $pdf->SetAutoPageBreak(false);

        // Fit inside the page on both axes and centre across it.
        $scale = min($pdf->getPageWidth() / $width, $pdf->getPageHeight() / $height);
        $w = $width * $scale;
        $h = $height * $scale;
        $pdf->Image('@' . $image, ($pdf->getPageWidth() - $w) / 2, 0, $w, $h);
        $pdf->setPageMark();
        $pdf->Output($dest, 'F');
    }
}
