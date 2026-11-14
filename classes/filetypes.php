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
 * Tells Moodle what kind of file .heic and .heif are.
 *
 * Moodle core has no entry for these extensions. Without one, uploads are
 * stored as "document/unknown" and refused by anything that accepts image
 * files only.
 *
 * Places that accept web images only, such as the image button in the text
 * editor, profile pictures, and course images, are a second step. They take
 * HEIC only while this site can convert it, because an unconverted HEIC there
 * is a broken image.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filetypes {

    /** @var array<string, string> Extension => MIME type. */
    const TYPES = ['heic' => 'image/heic', 'heif' => 'image/heif'];

    /**
     * Adds the file types at install. Remembers which ones were added, so
     * types an administrator defined are never changed or removed.
     */
    public static function install(): void {
        $known = \core_filetypes::get_types();
        $added = [];
        foreach (self::TYPES as $extension => $mimetype) {
            if (array_key_exists($extension, $known)) {
                continue;
            }
            \core_filetypes::add_type($extension, $mimetype, 'image', self::groups(), '', 'HEIC image');
            $added[] = $extension;
        }
        set_config('addedfiletypes', implode(',', $added), 'fileconverter_heic');
    }

    /**
     * Makes the file types match what this site can do right now.
     *
     * Runs when the plugin is upgraded and whenever its settings change.
     */
    public static function sync(): void {
        $ours = array_filter(explode(',', (string) get_config('fileconverter_heic', 'addedfiletypes')));
        $known = \core_filetypes::get_types();
        $groups = self::groups();
        foreach ($ours as $extension) {
            if (!isset($known[$extension], self::TYPES[$extension])) {
                continue;
            }
            $current = $known[$extension]['groups'] ?? [];
            if (!array_diff($groups, $current) && !array_diff($current, $groups)) {
                continue;
            }
            \core_filetypes::update_type($extension, $extension, self::TYPES[$extension], 'image', $groups, '', 'HEIC image');
        }
    }

    /**
     * Removes the file types this plugin added.
     */
    public static function uninstall(): void {
        $ours = array_filter(explode(',', (string) get_config('fileconverter_heic', 'addedfiletypes')));
        $known = \core_filetypes::get_types();
        foreach ($ours as $extension) {
            if (array_key_exists($extension, $known)) {
                \core_filetypes::delete_type($extension);
            }
        }
    }

    /**
     * The file type groups HEIC belongs to on this site.
     *
     * @return string[]
     */
    protected static function groups(): array {
        // Moodle uses optimised_image for pictures it resizes, such as profile pictures.
        return image_converter::is_ready() ? ['image', 'web_image', 'optimised_image'] : ['image'];
    }
}
