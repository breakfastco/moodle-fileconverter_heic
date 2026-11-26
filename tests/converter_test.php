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

use core_files\conversion;

#[\PHPUnit\Framework\Attributes\CoversClass(converter::class)]
/**
 * Tests for the document converter Moodle calls, such as for the assignment grader's pages.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\converter
 */
final class converter_test extends \advanced_testcase {
    /**
     * Stores the sample HEIC and starts a conversion of it.
     *
     * @param  string $targetformat Extension to convert to.
     * @return conversion
     */
    protected function conversion(string $targetformat): conversion {
        $user = $this->getDataGenerator()->create_user();
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => \context_user::instance($user->id)->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'IMG_0001.HEIC',
        ], __DIR__ . '/../samples/sample.heic');
        $conversion = new conversion(0, (object) [
            'sourcefileid' => $file->get_id(),
            'targetformat' => $targetformat,
        ]);
        $conversion->create();
        return $conversion;
    }

    /**
     * HEIC and HEIF convert to PDF and images, whatever the case of the extension.
     */
    public function test_supports(): void {
        $this->assertTrue(converter::supports('HEIC', 'pdf'));
        $this->assertTrue(converter::supports('heif', 'JPG'));
        $this->assertTrue(converter::supports('heic', 'webp'));
        $this->assertFalse(converter::supports('heic', 'docx'));
        $this->assertFalse(converter::supports('png', 'pdf'));
    }

    /**
     * The converter is offered only while this site can convert.
     */
    public function test_are_requirements_met(): void {
        $this->resetAfterTest();
        set_config('cloudonly', 1, 'fileconverter_heic');

        $this->assertFalse(converter::are_requirements_met());

        set_config('licensekey', 'TEST-KEY', 'fileconverter_heic');
        set_config('cloudenabled', 1, 'fileconverter_heic');
        $this->assertTrue(converter::are_requirements_met());
    }

    /**
     * A HEIC becomes a one-page PDF, which is what the assignment grader shows.
     */
    public function test_convert_to_pdf(): void {
        $this->resetAfterTest();
        if (!local_converter::is_available(true)) {
            $this->markTestSkipped('This server\'s ImageMagick cannot read HEIC.');
        }
        $conversion = $this->conversion('pdf');

        (new converter())->start_document_conversion($conversion);

        $this->assertEquals(conversion::STATUS_COMPLETE, $conversion->get('status'));
        $this->assertSame(image_converter::ROUTE_LOCAL, $conversion->get('data'));
        $pdf = $conversion->get_destfile();
        $this->assertSame('application/pdf', $pdf->get_mimetype());
        $this->assertStringStartsWith('%PDF', $pdf->get_content());
    }

    /**
     * A HEIC becomes a JPEG when that is what is asked for.
     */
    public function test_convert_to_jpeg(): void {
        $this->resetAfterTest();
        if (!local_converter::is_available(true)) {
            $this->markTestSkipped('This server\'s ImageMagick cannot read HEIC.');
        }
        $conversion = $this->conversion('jpg');

        (new converter())->start_document_conversion($conversion);

        $this->assertEquals(conversion::STATUS_COMPLETE, $conversion->get('status'));
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($conversion->get_destfile()->get_content())[2]);
    }

    /**
     * A format the converter does not write fails with a reason, and nothing is converted.
     */
    public function test_unsupported_target_fails(): void {
        $this->resetAfterTest();
        $conversion = $this->conversion('docx');

        (new converter())->start_document_conversion($conversion);
        $this->assertDebuggingCalled();

        $this->assertEquals(conversion::STATUS_FAILED, $conversion->get('status'));
        $this->assertSame(get_string('error_unsupported', 'fileconverter_heic'), $conversion->get('statusmessage'));
        $this->assertFalse($conversion->get_destfile());
    }
}
