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

#[\PHPUnit\Framework\Attributes\CoversClass(stored_images::class)]
/**
 * Tests for finding and converting HEIC images already stored in a course.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\stored_images
 */
final class stored_images_test extends \advanced_testcase {
    /** @var string The sample image shipped with the plugin. */
    const SAMPLE = __DIR__ . '/../samples/sample.heic';

    /** @var \stdClass A course. */
    protected \stdClass $course;

    /** @var \stdClass An editing teacher in it. */
    protected \stdClass $teacher;

    /** @var \stdClass A student in it. */
    protected \stdClass $student;

    /** @var \stdClass A forum in it. */
    protected \stdClass $forum;

    /** @var \stdClass A post the student wrote in the forum. */
    protected \stdClass $post;

    /**
     * Sets up a course with a forum post by a student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->forum = $generator->create_module('forum', ['course' => $this->course->id]);
        $forumgenerator = $generator->get_plugin_generator('mod_forum');
        $discussion = $forumgenerator->create_discussion([
            'course' => $this->course->id,
            'forum' => $this->forum->id,
            'userid' => $this->student->id,
        ]);
        $this->post = (object) ['id' => $discussion->firstpost];
    }

    /**
     * Stores the sample HEIC as if it had been uploaded before this plugin was installed.
     *
     * Files stored by code skip the upload conversion, so it stays a HEIC.
     *
     * @param  string $component Component.
     * @param  string $filearea  File area.
     * @param  int    $itemid    Item id.
     * @param  string $filename  File name.
     * @return \stored_file
     */
    protected function store_heic(string $component, string $filearea, int $itemid, string $filename): \stored_file {
        return get_file_storage()->create_file_from_pathname([
            'contextid' => \context_module::instance($this->forum->cmid)->id,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $this->student->id,
        ], self::SAMPLE);
    }

    /**
     * Teachers who may edit any post find the student's HEIC attachment; the student does not.
     */
    public function test_find_and_convertible_follow_permissions(): void {
        $file = $this->store_heic('mod_forum', 'attachment', $this->post->id, 'IMG_0001.HEIC');
        $coursecontext = \context_course::instance($this->course->id);

        $this->setUser($this->teacher);
        $this->assertSame([(int) $file->get_id()], array_keys(stored_images::find($coursecontext)));
        $this->assertEquals($file->get_id(), stored_images::convertible($file->get_id())?->get_id());

        $this->setUser($this->student);
        $this->assertSame([], stored_images::find($coursecontext));
        $this->assertNull(stored_images::convertible($file->get_id()));
    }

    /**
     * Files that are not HEIC, and areas the plugin never lists, are not found.
     */
    public function test_find_skips_other_files(): void {
        $this->setUser($this->teacher);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($this->forum->cmid)->id,
            'component' => 'mod_forum',
            'filearea' => 'attachment',
            'itemid' => $this->post->id,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], 'Some notes.');
        $this->store_heic('assignfeedback_editpdf', 'pages', 1, 'page.heic');

        $this->assertSame([], stored_images::find(\context_course::instance($this->course->id)));
    }

    /**
     * Each kind of file takes the permission that already lets someone change it.
     */
    public function test_capability_for(): void {
        $contextid = \context_module::instance($this->forum->cmid)->id;
        $record = fn(string $component, string $area) => (object) [
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $area,
        ];

        $expected = [
            'mod/assign:editothersubmission' => ['assignsubmission_file', 'submission_files'],
            'mod/forum:editanypost' => ['mod_forum', 'attachment'],
            'mod/glossary:manageentries' => ['mod_glossary', 'entry'],
            'mod/data:manageentries' => ['mod_data', 'content'],
            'moodle/question:editall' => ['question', 'questiontext'],
            'moodle/course:manageactivities' => ['mod_page', 'content'],
        ];
        foreach ($expected as $capability => [$component, $area]) {
            $this->assertSame($capability, stored_images::capability_for($record($component, $area)), "{$component}/{$area}");
        }
        // Moodle lets nobody edit a quiz attempt or someone else's workshop submission.
        $this->assertNull(stored_images::capability_for($record('question', 'response_attachments')));
        $this->assertNull(stored_images::capability_for($record('mod_workshop', 'submission_attachment')));

        $coursecontextid = \context_course::instance($this->course->id)->id;
        $this->assertSame('moodle/course:update', stored_images::capability_for((object) [
            'contextid' => $coursecontextid,
            'component' => 'course',
            'filearea' => 'summary',
        ]));
    }

    /**
     * Converting an attachment replaces it with a JPEG, renames it to .jpg, and logs who did it.
     */
    public function test_convert_attachment(): void {
        if (!local_converter::is_available(true)) {
            $this->markTestSkipped('This server\'s ImageMagick cannot read HEIC.');
        }
        $file = $this->store_heic('mod_forum', 'attachment', $this->post->id, 'IMG_0001.HEIC');
        $this->setUser($this->teacher);

        $sink = $this->redirectEvents();
        $converted = stored_images::convert($file);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame($file->get_id(), $converted->get_id(), 'The file keeps its record.');
        $this->assertSame('IMG_0001.jpg', $converted->get_filename());
        $this->assertSame('image/jpeg', $converted->get_mimetype());
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($converted->get_content())[2]);
        $this->assertSame((int) $this->student->id, (int) $converted->get_userid());

        $logged = array_values(array_filter($events, fn($event) => $event instanceof event\image_converted));
        $this->assertCount(1, $logged);
        $this->assertSame((int) $this->teacher->id, (int) $logged[0]->userid);
        $this->assertSame((int) $this->student->id, (int) $logged[0]->relateduserid);
        $this->assertSame(['filename' => 'IMG_0001.HEIC', 'newfilename' => 'IMG_0001.jpg'], $logged[0]->other);

        // Once converted, it is no longer offered.
        $this->assertSame([], stored_images::find(\context_course::instance($this->course->id)));
    }

    /**
     * An image in written text keeps its name, because the text links to it by name.
     */
    public function test_convert_image_in_text_keeps_its_name(): void {
        if (!local_converter::is_available(true)) {
            $this->markTestSkipped('This server\'s ImageMagick cannot read HEIC.');
        }
        $file = $this->store_heic('mod_forum', 'post', $this->post->id, 'IMG_0002.HEIC');
        $this->setUser($this->teacher);

        $converted = stored_images::convert($file);

        $this->assertSame('IMG_0002.HEIC', $converted->get_filename());
        $this->assertSame('image/jpeg', $converted->get_mimetype());
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($converted->get_content())[2]);
    }
}
