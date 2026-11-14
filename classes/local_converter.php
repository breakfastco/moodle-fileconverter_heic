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

/**
 * Converts HEIC images on this server with ImageMagick. Free, no credits.
 *
 * Works when PHP has the Imagick extension and ImageMagick was built with
 * libheif. Many servers list HEIC as a format and still cannot decode it, so
 * availability is decided by decoding a sample image.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_converter {

    /** @var string[] Formats this converter writes, named as the cloud service names them. */
    const FORMATS = ['jpeg', 'webp'];

    /**
     * Whether this server can convert HEIC images itself. Cached for a day.
     *
     * @param  bool $fresh Test again and replace the cached answer.
     * @return bool
     */
    public static function is_available(bool $fresh = false): bool {
        // Not shown in the settings page. Set in config.php to send everything to the cloud:
        // $CFG->forced_plugin_settings['fileconverter_heic']['cloudonly'] = 1.
        if (get_config('fileconverter_heic', 'cloudonly')) {
            return false;
        }

        $cache = \cache::make('fileconverter_heic', 'server');
        if (!$fresh) {
            $cached = $cache->get('local');
            if ($cached === 'yes' || $cached === 'no') {
                return $cached === 'yes';
            }
        }

        $works = self::can_decode_sample();
        $cache->set('local', $works ? 'yes' : 'no');
        return $works;
    }

    /**
     * The ImageMagick version string, or an empty string without the Imagick extension.
     *
     * @return string
     */
    public static function imagemagick_version(): string {
        if (!class_exists('\Imagick')) {
            return '';
        }
        return (string) (\Imagick::getVersion()['versionString'] ?? '');
    }

    /**
     * Tries to decode the sample HEIC image shipped with the plugin.
     *
     * @return bool
     */
    protected static function can_decode_sample(): bool {
        if (!class_exists('\Imagick')) {
            return false;
        }
        try {
            $imagick = new \Imagick();
            $works = (bool) $imagick->readImage(dirname(__DIR__) . '/samples/sample.heic');
            $imagick->clear();
            return $works;
        } catch (\Throwable $e) {
            // Typically "no decode delegate for this image format `HEIC'".
            return false;
        }
    }

    /**
     * Converts one HEIC file and returns the converted image bytes.
     *
     * @param  string $path   Path to the HEIC file.
     * @param  string $format One of self::FORMATS.
     * @return string Converted image contents.
     * @throws conversion_exception
     */
    public static function convert(string $path, string $format): string {
        if (!in_array($format, self::FORMATS, true)) {
            throw new conversion_exception('error_localfailed', 'unsupported format ' . $format);
        }
        try {
            $imagick = new \Imagick();
            $imagick->readImage($path);
            // A HEIC file can hold several images. The first is the photo.
            $imagick->setFirstIterator();
            $imagick->setImageFormat($format);
            $blob = $imagick->getImageBlob();
            $imagick->clear();
        } catch (\Throwable $e) {
            throw new conversion_exception('error_localfailed', $e->getMessage());
        }
        if (!is_string($blob) || $blob === '') {
            throw new conversion_exception('error_convertempty');
        }
        return $blob;
    }
}
