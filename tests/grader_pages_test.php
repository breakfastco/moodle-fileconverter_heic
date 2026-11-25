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

use assignfeedback_editpdf\document_services;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Tests for rebuilding the assignment grader's pages for submissions it could not show.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\grader_pages
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grader_pages::class)]
final class grader_pages_test extends \advanced_testcase {
    /** @var \assign The assignment. */
    protected \assign $assign;

    /** @var \stdClass The student's submission. */
    protected \stdClass $submission;

    /** @var \stdClass The student's grade. */
    protected \stdClass $grade;

    /**
     * Sets up an assignment with Annotate PDF feedback and a student's submission, opened by a teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 1024 * 1024,
            'assignfeedback_editpdf_enabled' => 1,
        ]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $this->assign = new \assign(\context_module::instance($cm->id), $cm, $course);
        $this->submission = $this->assign->get_user_submission($student->id, true);
        $this->setUser($teacher);
        $this->grade = $this->assign->get_user_grade($student->id, true, $this->submission->attemptnumber);
    }

    /**
     * Stores a page file the grader keeps for the grade.
     *
     * @param string $filearea File area.
     * @param string $filename File name.
     */
    protected function store_page(string $filearea, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $this->assign->get_context()->id,
            'component' => 'assignfeedback_editpdf',
            'filearea' => $filearea,
            'itemid' => $this->grade->id,
            'filepath' => '/',
            'filename' => $filename,
        ], 'page');
    }

    /**
     * Grader page builds queued for this submission.
     *
     * @return int
     */
    protected function queued(): int {
        $tasks = \core\task\manager::get_adhoc_tasks(\assignfeedback_editpdf\task\convert_submission::class);
        $mine = fn($task) => (int) $task->get_custom_data()->submissionid === (int) $this->submission->id;
        return count(array_filter($tasks, $mine));
    }

    /**
     * Annotate PDF is part of Moodle.
     */
    public function test_available(): void {
        $this->assertTrue(grader_pages::available());
    }

    /**
     * Pages built from a submission the grader could not fully convert are discarded, and the submission is queued.
     */
    public function test_rebuilds_pages_after_a_failed_conversion(): void {
        $this->store_page(document_services::PARTIAL_PDF_FILEAREA, document_services::COMBINED_PDF_FILENAME);
        $this->store_page(document_services::PAGE_IMAGE_FILEAREA, 'image_page0.png');
        $this->store_page(document_services::PAGE_IMAGE_READONLY_FILEAREA, 'image_page0.png');
        $before = $this->queued();

        $this->assertTrue(grader_pages::rebuild_if_failed($this->assign, $this->submission));

        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;
        $areas = [
            document_services::PARTIAL_PDF_FILEAREA,
            document_services::PAGE_IMAGE_FILEAREA,
            document_services::PAGE_IMAGE_READONLY_FILEAREA,
        ];
        foreach ($areas as $area) {
            $this->assertTrue($fs->is_area_empty($contextid, 'assignfeedback_editpdf', $area, $this->grade->id), $area);
        }
        $this->assertSame($before + 1, $this->queued());
    }

    /**
     * Pages the grader built without trouble are kept, and nothing is queued.
     */
    public function test_keeps_pages_after_a_good_conversion(): void {
        $this->store_page(document_services::COMBINED_PDF_FILEAREA, document_services::COMBINED_PDF_FILENAME);
        $this->store_page(document_services::PAGE_IMAGE_FILEAREA, 'image_page0.png');
        $before = $this->queued();

        $this->assertFalse(grader_pages::rebuild_if_failed($this->assign, $this->submission));

        $this->assertFalse(get_file_storage()->is_area_empty(
            $this->assign->get_context()->id,
            'assignfeedback_editpdf',
            document_services::PAGE_IMAGE_FILEAREA,
            $this->grade->id
        ));
        $this->assertSame($before, $this->queued());
    }
}
