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

namespace fileconverter_heic\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\external_location;

#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
/**
 * Tests for the privacy declaration.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * What cloud conversion sends is declared.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('fileconverter_heic'))->get_collection();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(external_location::class, $items[0]);
        $this->assertSame('breakfastcloud', $items[0]->get_name());
        $this->assertSame(['filecontent', 'filename', 'siteurl'], array_keys($items[0]->get_privacy_fields()));
    }

    /**
     * Nothing about anyone is stored in Moodle.
     */
    public function test_no_personal_data_is_stored(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame([], provider::get_contexts_for_userid($user->id)->get_contextids());
    }
}
