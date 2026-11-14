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
 * A cloud conversion that did not produce a file.
 *
 * The error code is a string identifier in this plugin's language file.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversion_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string      $errorcode String identifier in fileconverter_heic.
     * @param string|null $debuginfo Technical detail for logs.
     */
    public function __construct(string $errorcode, ?string $debuginfo = null) {
        parent::__construct($errorcode, 'fileconverter_heic', '', null, $debuginfo);
    }
}
