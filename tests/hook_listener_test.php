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

#[\PHPUnit\Framework\Attributes\CoversClass(hook_listener::class)]
/**
 * Tests for converting uploads as they arrive.
 *
 * Skipped on servers whose ImageMagick cannot read HEIC, so no test sends an image to the cloud.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\hook_listener
 */
final class hook_listener_test extends \advanced_testcase {
    /** @var string The sample image shipped with the plugin. */
    const SAMPLE = __DIR__ . '/../samples/sample.heic';

    /** @var \stdClass The person uploading. */
    protected \stdClass $user;

    /** @var int Their draft area. */
    protected int $draftid;

    /**
     * Logs in a person with an empty draft area. Skips the test when this server cannot convert.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!local_converter::is_available(true)) {
            $this->markTestSkipped('This server\'s ImageMagick cannot read HEIC.');
        }
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
        $this->draftid = file_get_unused_draft_itemid();
    }

    /**
     * Stores a file in the uploader's draft area, as the file picker does.
     *
     * @param  string      $filename File name.
     * @param  string|null $content  Content, or null for the sample HEIC.
     * @return \stored_file
     */
    protected function upload(string $filename, ?string $content = null): \stored_file {
        $record = [
            'contextid' => \context_user::instance($this->user->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $this->draftid,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $fs = get_file_storage();
        if ($content === null) {
            return $fs->create_file_from_pathname($record, self::SAMPLE);
        }
        return $fs->create_file_from_string($record, $content);
    }

    /**
     * An uploaded HEIC is stored as a JPEG with a .jpg name.
     */
    public function test_upload_is_stored_as_jpeg(): void {
        $file = $this->upload('IMG_0001.HEIC');

        $this->assertSame('IMG_0001.jpg', $file->get_filename());
        $this->assertSame('image/jpeg', $file->get_mimetype());
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($file->get_content())[2]);
    }

    /**
     * An upload sent as a string, rather than a file on disk, is converted too.
     */
    public function test_upload_from_string_is_stored_as_jpeg(): void {
        $file = $this->upload('photo.heif', file_get_contents(self::SAMPLE));

        $this->assertSame('photo.jpg', $file->get_filename());
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($file->get_content())[2]);
    }

    /**
     * A .jpg already in the folder keeps its name; the converted upload takes the next free one.
     */
    public function test_upload_does_not_replace_a_jpeg_of_the_same_name(): void {
        $this->upload('IMG_0001.jpg', 'an existing photo');

        $file = $this->upload('IMG_0001.HEIC');

        $this->assertSame('IMG_0001 (1).jpg', $file->get_filename());
    }

    /**
     * An image that cannot be converted is stored as it was uploaded.
     */
    public function test_upload_that_cannot_be_converted_is_kept(): void {
        $file = $this->upload('broken.heic', 'This is not a photo.');
        $this->assertDebuggingCalled();

        $this->assertSame('broken.heic', $file->get_filename());
        $this->assertSame('This is not a photo.', $file->get_content());
    }

    /**
     * A picker that takes web images only refuses an image that cannot be converted, as Moodle does without this plugin.
     */
    public function test_web_image_picker_refuses_an_image_that_cannot_be_converted(): void {
        $_GET['accepted_types'] = ['.jpg', '.png', '.gif'];
        try {
            $this->upload('broken.heic', 'This is not a photo.');
            $this->fail('The upload should be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidfiletype', $e->errorcode);
        } finally {
            unset($_GET['accepted_types']);
        }
        $this->assertDebuggingCalled();
    }

    /**
     * In the text editor the name is kept, because the editor links to the image by the name it sent.
     */
    public function test_editor_upload_keeps_its_name(): void {
        $_GET['env'] = 'editor';
        try {
            $file = $this->upload('IMG_0001.HEIC');
        } finally {
            unset($_GET['env']);
        }

        $this->assertSame('IMG_0001.HEIC', $file->get_filename());
        $this->assertSame('image/jpeg', $file->get_mimetype());
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($file->get_content())[2]);
    }

    /**
     * Files stored anywhere but a draft area, such as by a restore or by code, are left alone.
     */
    public function test_files_outside_draft_areas_are_left_alone(): void {
        $course = $this->getDataGenerator()->create_course();
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'IMG_0001.HEIC',
        ], self::SAMPLE);

        $this->assertSame('IMG_0001.HEIC', $file->get_filename());
        $this->assertSame(file_get_contents(self::SAMPLE), $file->get_content());
    }

    /**
     * Other uploads are not touched.
     */
    public function test_other_uploads_are_left_alone(): void {
        $file = $this->upload('notes.txt', 'Some notes.');

        $this->assertSame('notes.txt', $file->get_filename());
        $this->assertSame('Some notes.', $file->get_content());
    }
}
