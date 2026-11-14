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
 * Lists the .heic images in a course or activity and converts them to JPEG.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use fileconverter_heic\grader_pages;
use fileconverter_heic\image_converter;
use fileconverter_heic\local_converter;
use fileconverter_heic\stored_images;

$contextid = required_param('contextid', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$all = optional_param('all', 0, PARAM_BOOL);

$context = context::instance_by_id($contextid);
if ($context instanceof context_module) {
    [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid);
    require_login($course, false, $cm);
    $title = $cm->get_formatted_name();
} else if ($context instanceof context_course) {
    $course = get_course($context->instanceid);
    require_login($course);
    $title = format_string($course->fullname, true, ['context' => $context]);
} else {
    throw new moodle_exception('invalidcontext');
}
// Each file is checked against the permission that already lets someone edit it. See stored_images::find().

$url = new moodle_url('/files/converter/heic/convert.php', ['contextid' => $context->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('convertpage', 'fileconverter_heic') . moodle_page::TITLE_SEPARATOR . $title);
$PAGE->set_heading($PAGE->course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add(get_string('convertpage', 'fileconverter_heic'), $url);

$ready = image_converter::is_ready();
$files = stored_images::find($context);

// Convert, then report on the same page so a long run shows its progress.
if (($fileid || $all) && confirm_sesskey()) {
    if (!$ready) {
        redirect($url);
    }
    $chosen = $all ? $files : array_intersect_key($files, [$fileid => true]);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('convertpage', 'fileconverter_heic'));
    $progress = new \core\output\progress_bar('fileconverter_heic_progress', 500, true);
    $done = 0;
    $failed = [];
    $converted = [];
    $count = 0;
    foreach ($chosen as $file) {
        $progress->update($count, count($chosen), s($file->get_filename()));
        $count++;
        try {
            $converted[] = stored_images::convert($file);
            $done++;
        } catch (moodle_exception $e) {
            $failed[] = get_string('convertfailed', 'fileconverter_heic', (object) [
                'filename' => $file->get_filename(),
                'error' => $e->getMessage(),
            ]);
        }
    }
    $progress->update_full(100, get_string('converted', 'fileconverter_heic', $done));

    // Give the assignment grader a fresh start on submissions it showed blank.
    grader_pages::after_converted($converted);

    foreach ($failed as $message) {
        echo $OUTPUT->notification(s($message), \core\output\notification::NOTIFY_ERROR);
    }
    echo $OUTPUT->continue_button($url);
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('convertpage', 'fileconverter_heic'));

if (!$ready) {
    echo $OUTPUT->notification(get_string('convertunavailable', 'fileconverter_heic'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

if (!$files) {
    echo $OUTPUT->notification(get_string('convertnone', 'fileconverter_heic'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$intro = get_string('convertintro', 'fileconverter_heic');
if (!local_converter::is_available()) {
    $intro .= ' ' . get_string('convertcredits', 'fileconverter_heic');
}
echo html_writer::tag('p', $intro);

$modinfo = get_fast_modinfo($course);
$ownerids = array_filter(array_unique(array_map(fn($file) => (int) $file->get_userid(), $files)));
$owners = $ownerids ? user_get_users_by_id($ownerids) : [];

$table = new html_table();
$table->head = [
    get_string('colfile', 'fileconverter_heic'),
    get_string('colwhere', 'fileconverter_heic'),
    get_string('coluploadedby', 'fileconverter_heic'),
    get_string('coluploaded', 'fileconverter_heic'),
    '',
];
$table->attributes['class'] = 'generaltable';
foreach ($files as $file) {
    $filecontext = context::instance_by_id($file->get_contextid());
    if ($filecontext instanceof context_module && isset($modinfo->cms[$filecontext->instanceid])) {
        $filecm = $modinfo->cms[$filecontext->instanceid];
        $where = $filecm->url ? html_writer::link($filecm->url, $filecm->get_formatted_name()) : $filecm->get_formatted_name();
    } else {
        $where = s($filecontext->get_context_name(false));
    }
    $owner = $owners[$file->get_userid()] ?? null;
    $button = new single_button(
        new moodle_url($url, ['fileid' => $file->get_id(), 'sesskey' => sesskey()]),
        get_string('convert', 'fileconverter_heic'),
        'post',
        single_button::BUTTON_SECONDARY
    );
    $table->data[] = [
        s($file->get_filename()),
        $where,
        $owner ? fullname($owner) : '',
        userdate($file->get_timecreated(), get_string('strftimedatetimeshort', 'langconfig')),
        $OUTPUT->render($button),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->render(new single_button(
    new moodle_url($url, ['all' => 1, 'sesskey' => sesskey()]),
    get_string('convertall', 'fileconverter_heic', count($files)),
    'post',
    single_button::BUTTON_PRIMARY
));

echo $OUTPUT->footer();
