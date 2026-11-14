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

/**
 * Rebuilds the assignment grader's pages for submissions it could not show.
 *
 * Moodle keeps the blank page it built when a file in a submission could not
 * be converted, even after a converter is set up. Discarding those pages and
 * queueing the submission lets cron build them again.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grader_pages {
    /**
     * Whether the assignment "Annotate PDF" feedback plugin is installed.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists(document_services::class);
    }

    /**
     * Discards the kept pages when the grader could not show every file. Then queues the submission.
     *
     * @param  \assign    $assign     The assignment.
     * @param  \stdClass  $submission The submission record.
     * @param  bool       $apply      False to only report whether the submission needs it.
     * @return bool Whether the submission had pages to rebuild.
     */
    public static function rebuild_if_failed(\assign $assign, \stdClass $submission, bool $apply = true): bool {
        if (!self::available()) {
            return false;
        }

        if ($submission->userid) {
            $userids = [$submission->userid];
        } else {
            $userids = array_column($assign->get_submission_group_members($submission->groupid, true), 'id');
        }

        $fs = get_file_storage();
        $contextid = $assign->get_context()->id;
        $affected = false;
        foreach ($userids as $userid) {
            $grade = $assign->get_user_grade($userid, false, $submission->attemptnumber);
            if (!$grade) {
                continue;
            }
            // The grader keeps one set of pages for the grade, and on Moodle 5.0 and later
            // one more for each marker when an assignment has several.
            $sets = [[self::areas(), $grade->id]];
            foreach (self::mark_ids($grade->id) as $markid) {
                $sets[] = [self::marker_areas(), $markid];
            }
            // A partial PDF means Moodle could not convert every file in the submission.
            $failed = false;
            foreach ($sets as [$areas, $itemid]) {
                $filename = document_services::COMBINED_PDF_FILENAME;
                if ($fs->file_exists($contextid, 'assignfeedback_editpdf', $areas[0], $itemid, '/', $filename)) {
                    $failed = true;
                }
            }
            if (!$failed) {
                continue;
            }
            $affected = true;
            if ($apply) {
                foreach ($sets as [$areas, $itemid]) {
                    foreach ($areas as $area) {
                        $fs->delete_area_files($contextid, 'assignfeedback_editpdf', $area, $itemid);
                    }
                }
            }
        }

        if ($affected && $apply) {
            self::queue($submission);
        }
        return $affected;
    }

    /**
     * Gives the grader a fresh start on the submissions these converted files belong to.
     *
     * @param \stored_file[] $files Files just converted.
     */
    public static function after_converted(array $files): void {
        global $CFG, $DB;

        if (!self::available()) {
            return;
        }
        $submissions = [];
        foreach ($files as $file) {
            if ($file->get_component() === 'assignsubmission_file') {
                $submissions[$file->get_itemid()] = $file->get_contextid();
            }
        }
        if (!$submissions) {
            return;
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        foreach ($submissions as $submissionid => $contextid) {
            $submission = $DB->get_record('assign_submission', ['id' => $submissionid]);
            if ($submission) {
                self::rebuild_if_failed(new \assign(\context::instance_by_id($contextid), null, null), $submission);
            }
        }
    }

    /**
     * Queues a submission for cron to build its grader pages.
     *
     * @param \stdClass $submission The submission record.
     */
    public static function queue(\stdClass $submission): void {
        $task = new \assignfeedback_editpdf\task\convert_submission();
        $task->set_custom_data([
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * The grader's file areas for a grade that are rebuilt. The partial PDF comes first.
     *
     * @return string[]
     */
    protected static function areas(): array {
        return [
            document_services::PARTIAL_PDF_FILEAREA,
            document_services::COMBINED_PDF_FILEAREA,
            document_services::PAGE_IMAGE_FILEAREA,
            document_services::PAGE_IMAGE_READONLY_FILEAREA,
        ];
    }

    /**
     * The grader's file areas for one marker's copy. The partial PDF comes first. Moodle 5.0 and later.
     *
     * @return string[]
     */
    protected static function marker_areas(): array {
        return ['partial_marker', 'combined_marker', 'pages_marker'];
    }

    /**
     * Ids of the marks several markers gave a grade. Empty before Moodle 5.0.
     *
     * @param  int $gradeid The grade.
     * @return int[]
     */
    protected static function mark_ids(int $gradeid): array {
        global $DB;

        if (!defined(document_services::class . '::PARTIAL_PDF_FILEAREA_MARKER')) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_select('assign_mark', 'id', 'gradeid = ?', [$gradeid]));
    }
}
