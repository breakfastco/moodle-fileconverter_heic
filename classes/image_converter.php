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
 * Turns a HEIC file into an image Moodle can display, by whichever route this site has.
 *
 * Images are converted on this server when its ImageMagick can read HEIC,
 * which is free. The Breakfast cloud conversion service is used only when the
 * server cannot and cloud conversion is switched on. It uses one credit per
 * image. A server that can convert never uses the cloud, even for a file it
 * fails on.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_converter {

    /** @var string Converted on this server. Free. */
    const ROUTE_LOCAL = 'local';

    /** @var string Converted by the cloud service. One credit. */
    const ROUTE_CLOUD = 'cloud';

    /** @var string[] Source extensions, lower case. */
    const EXTENSIONS = ['heic', 'heif'];

    /**
     * Whether this site can convert HEIC images at all. Makes no network request.
     *
     * @return bool
     */
    public static function is_ready(): bool {
        return local_converter::is_available() || api::is_active();
    }

    /**
     * Whether a file name ends in .heic or .heif. iPhones use upper case.
     *
     * @param  string $filename File name.
     * @return bool
     */
    public static function is_heic(string $filename): bool {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * Converts one HEIC file.
     *
     * @param  string $path     Path to the HEIC file.
     * @param  string $filename Original file name.
     * @param  string $format   jpeg or webp.
     * @return array Image contents, then the route used: self::ROUTE_LOCAL or self::ROUTE_CLOUD.
     * @throws conversion_exception
     */
    public static function convert(string $path, string $filename, string $format): array {
        if (local_converter::is_available()) {
            return [local_converter::convert($path, $format), self::ROUTE_LOCAL];
        }
        return [(new api())->convert($path, $filename, $format), self::ROUTE_CLOUD];
    }
}
