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
 * Retries assignment submissions whose HEIC images could not be converted.
 *
 * Moodle keeps the blank page it built when a conversion failed, so
 * submissions made before this converter was set up stay blank in the grader.
 * This script discards those kept pages and queues each submission to be
 * converted again by cron.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use fileconverter_heic\grader_pages;

[$options, $unrecognised] = cli_get_params(['run' => false, 'help' => false], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Retries assignment submissions whose HEIC images could not be converted.

Lists the affected submissions. With --run, discards the blank pages Moodle
kept for them and queues each one for conversion by cron. Each image uses one
conversion credit when it is converted.

Options:
    --run         Reset and queue the submissions. Without it, only lists them.
    -h, --help    Print this help.

Example:
    php files/converter/heic/cli/retry_failed.php --run");
    exit(0);
}

if (!grader_pages::available()) {
    cli_error('The assignment "Annotate PDF" feedback plugin is not installed.');
}

$sql = "SELECT DISTINCT f.itemid
          FROM {files} f
         WHERE f.component = :component
           AND f.filearea = :filearea
           AND (" . $DB->sql_like('f.filename', ':heic', false) . "
                OR " . $DB->sql_like('f.filename', ':heif', false) . ")";
$submissionids = $DB->get_fieldset_sql($sql, [
    'component' => 'assignsubmission_file',
    'filearea' => 'submission_files',
    'heic' => '%.heic',
    'heif' => '%.heif',
]);

$found = 0;

foreach ($submissionids as $submissionid) {
    $submission = $DB->get_record('assign_submission', ['id' => $submissionid]);
    if (!$submission) {
        continue;
    }
    $cm = get_coursemodule_from_instance('assign', $submission->assignment);
    if (!$cm) {
        continue;
    }
    $context = context_module::instance($cm->id);
    $assign = new assign($context, null, null);

    if (!grader_pages::rebuild_if_failed($assign, $submission, (bool) $options['run'])) {
        continue;
    }

    $found++;
    cli_writeln("Submission {$submission->id}: " . format_string($assign->get_instance()->name));
}

if ($options['run']) {
    cli_writeln("{$found} submission(s) queued. Cron converts them.");
} else {
    cli_writeln("{$found} submission(s) to retry. Run again with --run to queue them.");
}
