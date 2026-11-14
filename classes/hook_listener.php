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

use core_files\hook\before_file_created;

/**
 * Converts .heic and .heif images to .jpg as they are uploaded.
 *
 * Everything a person uploads in Moodle lands in their draft file area first,
 * whichever activity it is for. Converting there means the assignment, forum
 * post, quiz answer, or course page receives an ordinary JPEG that Moodle and
 * every browser can display.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {

    /**
     * Swaps a HEIC upload for a JPEG before Moodle stores it.
     *
     * If the image cannot be converted, the original file is stored unchanged.
     * The one exception is a picker that takes web images only, which refuses it.
     *
     * @param before_file_created $hook The file about to be stored.
     */
    public static function convert_upload(before_file_created $hook): void {
        $record = $hook->get_filerecord();
        if (!$record || !isset($record->filename, $record->component, $record->filearea)) {
            return;
        }
        // Only new uploads. Restores, copies, and files created by code are left alone.
        if ($record->component !== 'user' || $record->filearea !== 'draft') {
            return;
        }
        if (!image_converter::is_heic($record->filename) || !image_converter::is_ready()) {
            return;
        }

        if ($hook->has_filepath()) {
            $source = $hook->get_filepath();
        } else {
            $source = make_request_directory() . '/source.heic';
            file_put_contents($source, $hook->get_filecontent());
        }

        \core_php_time_limit::raise(180);

        try {
            [$image] = image_converter::convert($source, $record->filename, 'jpeg');
        } catch (conversion_exception $e) {
            $detail = $e->debuginfo ? " ({$e->debuginfo})" : '';
            debugging('fileconverter_heic: ' . $e->getMessage() . $detail, DEBUG_NORMAL);
            if (self::picker_wants_web_images_only()) {
                // An unconverted HEIC is a broken image here. Refuse it, as Moodle does without this plugin.
                throw new \moodle_exception('invalidfiletype', 'repository', '', get_mimetype_description(['filename' => $record->filename]));
            }
            return;
        }

        if ($hook->has_filepath()) {
            $converted = make_request_directory() . '/converted.jpg';
            file_put_contents($converted, $image);
            $hook->update_filepath($converted);
        } else {
            $hook->update_filecontent($image);
        }

        $record->mimetype = 'image/jpeg';

        // The text editor links to an uploaded image by the name it was sent with,
        // and nothing after this point can tell it about a new name. Keep the name
        // there. The file is a JPEG, is stored as a JPEG, and is served as one.
        if (optional_param('env', '', PARAM_ALPHA) === 'editor') {
            return;
        }
        $record->filename = self::unused_filename($record, pathinfo($record->filename, PATHINFO_FILENAME));
    }

    /**
     * Whether the file picker behind this upload accepts web images and nothing else.
     *
     * True for the image button in the text editor, profile pictures, and course images.
     *
     * @return bool
     */
    protected static function picker_wants_web_images_only(): bool {
        $accepted = optional_param_array('accepted_types', [], PARAM_RAW);
        if (!$accepted || in_array('*', $accepted, true)) {
            return false;
        }
        $accepted = array_map('strtolower', $accepted);
        return !array_diff($accepted, file_get_typegroup('extension', 'web_image'));
    }

    /**
     * Puts a Convert to .jpg button beside each HEIC image and file link that the viewer may convert.
     *
     * Runs on every page in a course or activity. The script finds where the page
     * shows each file, so it works in any activity without knowing its markup.
     *
     * @param \core\hook\output\before_http_headers $hook The page about to be sent.
     */
    public static function inline_convert(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;

        if (during_initial_install() || !isloggedin() || isguestuser() || CLI_SCRIPT || AJAX_SCRIPT) {
            return;
        }
        $context = $PAGE->context;
        if (!$context instanceof \context_module && !$context instanceof \context_course) {
            return;
        }
        if (!image_converter::is_ready()) {
            return;
        }
        // Only images this person is already allowed to change.
        $files = stored_images::find($context);
        if (!$files) {
            return;
        }
        $PAGE->requires->js_call_amd('fileconverter_heic/inline', 'init', [array_values(array_map(fn(\stored_file $file) => [
            'id' => (int) $file->get_id(),
            'contextid' => (int) $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
        ], $files))]);
    }

    /**
     * A .jpg file name that is free in the folder the upload is going to.
     *
     * @param  \stdClass $record The file record being stored.
     * @param  string    $base   File name without its extension.
     * @return string
     */
    protected static function unused_filename(\stdClass $record, string $base): string {
        $fs = get_file_storage();
        $filepath = $record->filepath ?? '/';
        $name = $base . '.jpg';
        $count = 1;
        while ($fs->file_exists($record->contextid, $record->component, $record->filearea, $record->itemid, $filepath, $name)) {
            $name = "{$base} ({$count}).jpg";
            $count++;
        }
        return $name;
    }
}
