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
 * Callbacks for HEIC Support.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds "Convert HEIC images" to the course's More menu for people who may edit something in it.
 *
 * @param navigation_node $navigation The course node.
 * @param stdClass        $course     The course.
 * @param context         $context    The course context.
 */
function fileconverter_heic_extend_navigation_course($navigation, $course, $context) {
    if (!\fileconverter_heic\stored_images::may_convert_any($context)) {
        return;
    }
    if (!\fileconverter_heic\image_converter::is_ready()) {
        return;
    }
    $navigation->add(
        get_string('convertpage', 'fileconverter_heic'),
        new moodle_url('/files/converter/heic/convert.php', ['contextid' => $context->id]),
        navigation_node::TYPE_SETTING,
        null,
        'fileconverter_heic_convert',
        new pix_icon('i/files', '')
    );
}
