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

namespace fileconverter_heic\event;

/**
 * A stored .heic or .heif image was converted to a JPEG.
 *
 * The original file is replaced, so the log is the record of who did it.
 *
 * @property-read array $other {
 *      - string filename: The name before conversion.
 *      - string newfilename: The name after conversion.
 * }
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_converted extends \core\event\base {

    /**
     * Sets the basic properties.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'files';
    }

    /**
     * The event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventimageconverted', 'fileconverter_heic');
    }

    /**
     * What happened, in English, for the logs.
     *
     * @return string
     */
    public function get_description() {
        $owner = $this->relateduserid ? " uploaded by the user with id '{$this->relateduserid}'" : '';
        return "The user with id '{$this->userid}' converted the image '{$this->other['filename']}'{$owner} "
            . "to '{$this->other['newfilename']}'.";
    }

    /**
     * Checks the data.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['filename'], $this->other['newfilename'])) {
            throw new \coding_exception('The filename and newfilename values must be set in other.');
        }
    }

    /**
     * How the object id maps when logs are restored.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'files', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * How the other values map when logs are restored.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
