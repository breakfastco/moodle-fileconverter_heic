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
 * Finds and converts .heic and .heif images already stored in a course or activity.
 *
 * Converting replaces the stored file's content with a JPEG and keeps its
 * record, so its id, owner, and dates do not change. The original HEIC is not
 * kept.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stored_images {

    /**
     * Areas where a file is listed by name and nothing else refers to it, so
     * the converted file can be renamed to .jpg. Everywhere else, such as text
     * written in the editor, the page links to the file by name, so the name is
     * kept. The file is stored and served as a JPEG either way.
     *
     * @var string[] component/filearea
     */
    const RENAMEABLE = [
        'assignsubmission_file/submission_files',
        'mod_forum/attachment',
        'mod_glossary/attachment',
        'mod_workshop/submission_attachment',
        'question/response_attachments',
        'mod_folder/content',
        'mod_resource/content',
    ];

    /** @var string[] Components whose files are never listed: generated, internal, or backups. */
    const SKIPPED = ['assignfeedback_editpdf', 'backup', 'fileconverter_heic', 'user'];

    /**
     * The HEIC images in a context and the contexts below it that the current user may convert.
     *
     * @param  \context $context A course or activity context.
     * @return \stored_file[] Keyed by file id, oldest first.
     */
    public static function find(\context $context): array {
        global $DB;

        [$where, $params] = self::where();
        $like = $DB->sql_like('ctx.path', ':path', false, false);
        $sql = "SELECT f.*
                  FROM {files} f
                  JOIN {context} ctx ON ctx.id = f.contextid
                 WHERE (ctx.id = :contextid OR {$like})
                   AND {$where}
              ORDER BY f.timecreated, f.id";
        $records = $DB->get_records_sql($sql, ['contextid' => $context->id, 'path' => $context->path . '/%'] + $params);

        $fs = get_file_storage();
        $allowed = [];
        $files = [];
        foreach ($records as $record) {
            $capability = self::capability_for($record);
            if ($capability === null) {
                continue;
            }
            $key = $record->contextid . ':' . $capability;
            if (!isset($allowed[$key])) {
                $allowed[$key] = has_capability($capability, \context::instance_by_id($record->contextid));
            }
            if ($allowed[$key]) {
                $files[$record->id] = $fs->get_file_instance($record);
            }
        }
        return $files;
    }

    /**
     * One HEIC image, if it is unconverted and the current user may convert it.
     *
     * @param  int $fileid Stored file id.
     * @return \stored_file|null
     */
    public static function convertible(int $fileid): ?\stored_file {
        global $DB;

        [$where, $params] = self::where();
        $record = $DB->get_record_sql("SELECT f.* FROM {files} f WHERE f.id = :fileid AND {$where}",
            ['fileid' => $fileid] + $params);
        if (!$record) {
            return null;
        }
        $capability = self::capability_for($record);
        if ($capability === null || !has_capability($capability, \context::instance_by_id($record->contextid))) {
            return null;
        }
        return get_file_storage()->get_file_instance($record);
    }

    /**
     * The Moodle permission that already lets someone change this file. Null when nobody but its owner may.
     *
     * Converting replaces the file, so it takes the same permission as editing
     * the thing the file belongs to.
     *
     * @param  \stdClass $record A files table record.
     * @return string|null Capability name.
     */
    public static function capability_for(\stdClass $record): ?string {
        $context = \context::instance_by_id($record->contextid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }
        $component = $record->component;
        $area = $record->filearea;

        // Files people submitted or posted: the permission to edit someone else's.
        if (str_starts_with($component, 'assignsubmission_')) {
            return 'mod/assign:editothersubmission';
        }
        if ($component === 'mod_forum' && in_array($area, ['attachment', 'post'], true)) {
            return 'mod/forum:editanypost';
        }
        if ($component === 'mod_glossary' && in_array($area, ['attachment', 'entry'], true)) {
            return 'mod/glossary:manageentries';
        }
        if ($component === 'mod_data' && $area === 'content') {
            return 'mod/data:manageentries';
        }
        // Moodle lets nobody edit a quiz attempt or someone else's workshop submission.
        if ($component === 'question' && str_starts_with($area, 'response_')) {
            return null;
        }
        if ($component === 'mod_workshop' && !in_array($area, ['intro', 'instructauthors', 'instructreviewers', 'conclusion'], true)) {
            return null;
        }
        if ($component === 'question') {
            return 'moodle/question:editall';
        }

        // Everything else is the course's or activity's own content: the permission to edit it.
        if ($context instanceof \context_module) {
            return 'moodle/course:manageactivities';
        }
        if ($context instanceof \context_block) {
            return 'moodle/block:edit';
        }
        return 'moodle/course:update';
    }

    /**
     * Whether the current user holds any permission that lets them convert something in a course.
     *
     * A quick check for showing the way in. The page itself checks each file.
     *
     * @param  \context $context The course context.
     * @return bool
     */
    public static function may_convert_any(\context $context): bool {
        return has_any_capability([
            'moodle/course:update',
            'moodle/course:manageactivities',
            'mod/assign:editothersubmission',
            'mod/forum:editanypost',
            'mod/glossary:manageentries',
            'mod/data:manageentries',
        ], $context);
    }

    /**
     * SQL that picks out unconverted HEIC images in the files table, aliased f.
     *
     * @return array SQL, then its parameters.
     */
    protected static function where(): array {
        global $DB;

        [$skipsql, $params] = $DB->get_in_or_equal(self::SKIPPED, SQL_PARAMS_NAMED, 'skip', false);
        $sql = "f.filename <> '.'
                AND f.referencefileid IS NULL
                AND f.mimetype <> :jpeg
                AND f.component {$skipsql}
                AND (" . $DB->sql_like('f.filename', ':heic', false) . "
                     OR " . $DB->sql_like('f.filename', ':heif', false) . ")";
        return [$sql, $params + ['jpeg' => 'image/jpeg', 'heic' => '%.heic', 'heif' => '%.heif']];
    }

    /**
     * Converts one stored HEIC image to a JPEG, in place.
     *
     * @param  \stored_file $file The HEIC image.
     * @return \stored_file The same file, now a JPEG.
     * @throws conversion_exception
     */
    public static function convert(\stored_file $file): \stored_file {
        global $DB;

        $dir = make_request_directory();
        $source = $dir . '/source.heic';
        if ($file->copy_content_to($source) === false) {
            throw new conversion_exception('error_copyfailed');
        }
        \core_php_time_limit::raise(180);
        [$image] = image_converter::convert($source, $file->get_filename(), 'jpeg');
        $converted = $dir . '/converted.jpg';
        file_put_contents($converted, $image);

        // Put the JPEG in the file pool through a temporary record, then point the original record at it.
        $fs = get_file_storage();
        $temp = $fs->create_file_from_pathname([
            'contextid' => \context_system::instance()->id,
            'component' => 'fileconverter_heic',
            'filearea' => 'temp',
            'itemid' => $file->get_id(),
            'filepath' => '/',
            'filename' => 'converted-' . random_string(12) . '.jpg',
            'userid' => $file->get_userid(),
        ], $converted);

        $oldname = $file->get_filename();
        try {
            $file->replace_file_with($temp);
        } finally {
            $temp->delete();
        }

        $area = $file->get_component() . '/' . $file->get_filearea();
        if (in_array($area, self::RENAMEABLE, true)) {
            $file->rename($file->get_filepath(), self::unused_name($file, pathinfo($oldname, PATHINFO_FILENAME)));
        }
        if ($file->get_mimetype() !== 'image/jpeg') {
            // Moodle works the type out from the name, which still ends in .heic.
            $DB->set_field('files', 'mimetype', 'image/jpeg', ['id' => $file->get_id()]);
            $file = $fs->get_file_by_id($file->get_id());
        }

        event\image_converted::create([
            'context' => \context::instance_by_id($file->get_contextid()),
            'objectid' => $file->get_id(),
            'relateduserid' => $file->get_userid() ?: null,
            'other' => ['filename' => $oldname, 'newfilename' => $file->get_filename()],
        ])->trigger();

        return $file;
    }

    /**
     * A .jpg name that is free beside the file.
     *
     * @param  \stored_file $file The file being renamed.
     * @param  string       $base File name without its extension.
     * @return string
     */
    protected static function unused_name(\stored_file $file, string $base): string {
        $fs = get_file_storage();
        $name = $base . '.jpg';
        $count = 1;
        while ($fs->file_exists($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $name)) {
            $name = "{$base} ({$count}).jpg";
            $count++;
        }
        return $name;
    }
}
