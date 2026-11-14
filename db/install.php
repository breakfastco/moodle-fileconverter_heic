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

/**
 * Install steps for the HEIC document converter.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Teaches Moodle the .heic and .heif file types and enables the converter.
 *
 * Moodle core has no entry for these extensions, so uploads are stored as
 * "document/unknown" and are rejected by activities that accept image files only.
 */
function xmldb_fileconverter_heic_install() {
    $known = core_filetypes::get_types();
    $added = [];
    foreach (['heic' => 'image/heic', 'heif' => 'image/heif'] as $extension => $mimetype) {
        if (array_key_exists($extension, $known)) {
            continue;
        }
        core_filetypes::add_type($extension, $mimetype, 'image', ['image'], '', 'HEIC image');
        $added[] = $extension;
    }
    // Remembered so uninstalling removes only the types this plugin added.
    set_config('addedfiletypes', implode(',', $added), 'fileconverter_heic');

    // Safe to enable now. The converter stays idle until a license key is saved.
    // The list of installed plugins is cached and does not include this one yet.
    \core_plugin_manager::reset_caches();
    \core\plugininfo\fileconverter::enable_plugin('heic', 1);
}
