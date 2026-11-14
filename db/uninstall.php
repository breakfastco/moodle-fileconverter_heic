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
 * Removes the file types this plugin added at install, and takes it off the list of enabled converters.
 *
 * @return bool
 */
function xmldb_fileconverter_heic_uninstall() {
    \fileconverter_heic\filetypes::uninstall();
    // Moodle leaves an uninstalled converter on its list of enabled converters.
    \core\plugininfo\fileconverter::enable_plugin('heic', 0);
    return true;
}
