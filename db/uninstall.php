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
 * Uninstall steps for the HEIC document converter.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes the file types this plugin added at install.
 *
 * @return bool
 */
function xmldb_fileconverter_heic_uninstall() {
    $added = array_filter(explode(',', (string) get_config('fileconverter_heic', 'addedfiletypes')));
    $known = core_filetypes::get_types();
    foreach ($added as $extension) {
        if (array_key_exists($extension, $known)) {
            core_filetypes::delete_type($extension);
        }
    }
    return true;
}
