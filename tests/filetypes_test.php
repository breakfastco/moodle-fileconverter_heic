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

#[\PHPUnit\Framework\Attributes\CoversClass(filetypes::class)]
/**
 * Tests for the .heic and .heif file types the plugin adds to Moodle.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\filetypes
 */
final class filetypes_test extends \advanced_testcase {
    /**
     * The groups a file type is in.
     *
     * @param  string $extension Extension.
     * @return string[]
     */
    protected function groups_of(string $extension): array {
        $groups = \core_filetypes::get_types()[$extension]['groups'] ?? [];
        sort($groups);
        return $groups;
    }

    /**
     * Installing the plugin teaches Moodle both file types.
     */
    public function test_installed_types(): void {
        $types = \core_filetypes::get_types();

        $this->assertSame('image/heic', $types['heic']['type']);
        $this->assertSame('image/heif', $types['heif']['type']);
        $this->assertSame('image/heic', mimeinfo('type', 'IMG_0001.HEIC'));
        $this->assertSame('heic,heif', get_config('fileconverter_heic', 'addedfiletypes'));
    }

    /**
     * Places that take web images only accept HEIC while the site can convert it, and not otherwise.
     */
    public function test_sync_follows_whether_the_site_can_convert(): void {
        $this->resetAfterTest();

        set_config('cloudonly', 1, 'fileconverter_heic');
        filetypes::sync();
        $this->assertSame(['image'], $this->groups_of('heic'));
        $this->assertSame(['image'], $this->groups_of('heif'));

        set_config('licensekey', 'TEST-KEY', 'fileconverter_heic');
        set_config('cloudenabled', 1, 'fileconverter_heic');
        filetypes::sync();
        $this->assertSame(['image', 'optimised_image', 'web_image'], $this->groups_of('heic'));
        $this->assertContains('.heic', file_get_typegroup('extension', 'web_image'));
    }

    /**
     * File types an administrator defined are never changed or removed.
     */
    public function test_types_defined_by_an_administrator_are_left_alone(): void {
        $this->resetAfterTest();
        filetypes::uninstall();
        \core_filetypes::add_type('heic', 'image/x-custom-heic', 'image', ['document'], '', 'My HEIC');

        filetypes::install();
        $this->assertSame('heif', get_config('fileconverter_heic', 'addedfiletypes'));
        $this->assertSame('image/x-custom-heic', \core_filetypes::get_types()['heic']['type']);

        filetypes::sync();
        $this->assertSame(['document'], $this->groups_of('heic'));

        filetypes::uninstall();
        $types = \core_filetypes::get_types();
        $this->assertArrayHasKey('heic', $types);
        $this->assertArrayNotHasKey('heif', $types);
    }
}
