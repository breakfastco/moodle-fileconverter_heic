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

namespace fileconverter_heic\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use fileconverter_heic\grader_pages;
use fileconverter_heic\image_converter;
use fileconverter_heic\stored_images;

/**
 * Converts one stored .heic image to a .jpg, from the Convert button beside it.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convert_image extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fileid' => new external_value(PARAM_INT, 'Id of the stored .heic file'),
        ]);
    }

    /**
     * Converts the file, if the current user may change it.
     *
     * @param  int $fileid Stored file id.
     * @return array
     */
    public static function execute(int $fileid): array {
        global $DB;

        ['fileid' => $fileid] = self::validate_parameters(self::execute_parameters(), ['fileid' => $fileid]);

        $contextid = $DB->get_field('files', 'contextid', ['id' => $fileid]);
        if (!$contextid) {
            throw new \moodle_exception('filenotfound', 'error');
        }
        self::validate_context(\context::instance_by_id($contextid));

        // The same permission that already lets this person edit what the image belongs to.
        $file = stored_images::convertible($fileid);
        if (!$file) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('convert', 'fileconverter_heic'));
        }
        if (!image_converter::is_ready()) {
            return ['success' => false, 'filename' => '', 'error' => get_string('convertunavailable', 'fileconverter_heic')];
        }

        try {
            $file = stored_images::convert($file);
        } catch (\moodle_exception $e) {
            return ['success' => false, 'filename' => '', 'error' => $e->getMessage()];
        }
        grader_pages::after_converted([$file]);

        return ['success' => true, 'filename' => $file->get_filename(), 'error' => ''];
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the image was converted'),
            'filename' => new external_value(PARAM_FILE, 'The file name after converting'),
            'error' => new external_value(PARAM_TEXT, 'Why it was not converted'),
        ]);
    }
}
