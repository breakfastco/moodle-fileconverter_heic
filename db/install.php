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
 */
function xmldb_fileconverter_heic_install() {
    \fileconverter_heic\filetypes::install();

    // Safe to enable now. The converter stays idle until the site can convert.
    // The list of installed plugins is cached and does not include this one yet.
    \core_plugin_manager::reset_caches();
    \core\plugininfo\fileconverter::enable_plugin('heic', 1);
}
