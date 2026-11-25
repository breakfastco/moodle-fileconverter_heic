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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_api.php');

/**
 * Tests for the cloud conversion client. No request leaves the test.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\api
 */
#[\PHPUnit\Framework\Attributes\CoversClass(api::class)]
final class api_test extends \advanced_testcase {
    /**
     * Saves a license key and switches cloud conversion on.
     */
    protected function switch_on_cloud(): void {
        set_config('licensekey', 'TEST-KEY', 'fileconverter_heic');
        set_config('cloudenabled', 1, 'fileconverter_heic');
    }

    /**
     * The conversion_exception a call throws, or null.
     *
     * @param  callable $call The call to make.
     * @return conversion_exception|null
     */
    protected function thrown(callable $call): ?conversion_exception {
        try {
            $call();
        } catch (conversion_exception $e) {
            return $e;
        }
        return null;
    }

    /**
     * Cloud conversion needs both a license key and the switch.
     */
    public function test_is_active(): void {
        $this->resetAfterTest();

        $this->assertFalse(api::is_active());
        set_config('licensekey', '  ', 'fileconverter_heic');
        set_config('cloudenabled', 1, 'fileconverter_heic');
        $this->assertFalse(api::is_active(), 'A blank key does not count.');
        set_config('licensekey', 'TEST-KEY', 'fileconverter_heic');
        $this->assertTrue(api::is_active());
        set_config('cloudenabled', 0, 'fileconverter_heic');
        $this->assertFalse(api::is_active());
    }

    /**
     * A conversion takes a credit from the store, then sends the image with the ticket.
     */
    public function test_convert_takes_a_credit_then_converts(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->switch_on_cloud();

        $api = new testable_api([
            [200, json_encode(['ticket' => 'TICKET-1', 'remaining' => 4])],
            [200, 'JPEG BYTES'],
        ]);
        $image = $api->convert(__DIR__ . '/../samples/sample.heic', 'IMG_0001.HEIC', 'jpeg');

        $this->assertSame('JPEG BYTES', $image);
        $this->assertCount(2, $api->curl->requests);

        [$method, $url, $params] = $api->curl->requests[0];
        $this->assertSame('POST', $method);
        $this->assertStringEndsWith('/wp-json/heic-support/v1/credits/consume', $url);
        parse_str($params, $sent);
        $this->assertSame(['license' => 'TEST-KEY', 'site' => $CFG->wwwroot, 'amount' => '1'], $sent);

        [, $url, $params] = $api->curl->requests[1];
        $this->assertStringEndsWith('/convert', $url);
        $this->assertSame('TICKET-1', $params['ticket']);
        $this->assertSame('jpeg', $params['format']);
        $this->assertSame('IMG_0001.HEIC', $params['file']->getPostFilename());

        // The store's count is remembered, so the settings page needs no request.
        $this->assertSame(['status' => 'active', 'remaining' => 4], api::status());
    }

    /**
     * With cloud conversion off, nothing is sent.
     */
    public function test_convert_when_cloud_is_off(): void {
        $this->resetAfterTest();

        $api = new testable_api([]);
        $e = $this->thrown(fn() => $api->convert(__DIR__ . '/../samples/sample.heic', 'a.heic', 'jpeg'));

        $this->assertSame('error_cloudoff', $e?->errorcode);
        $this->assertSame([], $api->curl->requests);
    }

    /**
     * An unsupported format is refused before any credit is taken.
     */
    public function test_convert_unsupported_format(): void {
        $this->resetAfterTest();
        $this->switch_on_cloud();

        $api = new testable_api([]);
        $e = $this->thrown(fn() => $api->convert(__DIR__ . '/../samples/sample.heic', 'a.heic', 'gif'));

        $this->assertSame('error_convertfailed', $e?->errorcode);
        $this->assertSame([], $api->curl->requests);
    }

    /**
     * How each answer from the store and the conversion server is reported.
     *
     * @return array[] Responses, the expected error, and the status remembered afterwards.
     */
    public static function failure_provider(): array {
        $ticket = [200, json_encode(['ticket' => 'TICKET-1', 'remaining' => 3])];
        return [
            'no credits left' => [[[402, '{}']], 'error_nocredits', ['status' => 'empty', 'remaining' => 0]],
            'unknown license key' => [[[401, '{}']], 'error_invalidlicense', ['status' => 'invalid', 'remaining' => 0]],
            'store unreachable' => [[[0, '']], 'error_storeunreachable', null],
            'store error' => [[[500, 'oops']], 'error_consumefailed', null],
            'no ticket' => [[[200, '{}']], 'error_consumefailed', null],
            'server busy' => [[$ticket, [503, '']], 'error_busy', ['status' => 'active', 'remaining' => 3]],
            'server unreachable' => [[$ticket, [0, '']], 'error_serverunreachable', ['status' => 'active', 'remaining' => 3]],
            'conversion failed' => [[$ticket, [422, '']], 'error_convertfailed', ['status' => 'active', 'remaining' => 3]],
            'empty image' => [[$ticket, [200, '']], 'error_convertempty', ['status' => 'active', 'remaining' => 3]],
        ];
    }

    /**
     * Failures are reported with their own message, and the license status is remembered when the store gave one.
     *
     * @param array[]    $responses Responses, each [HTTP status, body].
     * @param string     $error     Expected error code.
     * @param array|null $status    Expected remembered status, or null for none.
     * @dataProvider failure_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('failure_provider')]
    public function test_convert_failures(array $responses, string $error, ?array $status): void {
        $this->resetAfterTest();
        $this->switch_on_cloud();

        $api = new testable_api($responses);
        $e = $this->thrown(fn() => $api->convert(__DIR__ . '/../samples/sample.heic', 'a.heic', 'jpeg'));

        $this->assertSame($error, $e?->errorcode);
        $this->assertSame($status, \cache::make('fileconverter_heic', 'credits')->get('status') ?: null);
    }

    /**
     * Without a license key there is nothing to ask the store.
     */
    public function test_status_without_a_key(): void {
        $this->resetAfterTest();

        $this->assertSame(['status' => 'invalid', 'remaining' => 0], api::status());
    }

    /**
     * Saving the settings forgets the remembered status, so a new key is checked.
     */
    public function test_settings_updated_forgets_the_status(): void {
        $this->resetAfterTest();
        $cache = \cache::make('fileconverter_heic', 'credits');
        $cache->set('status', ['status' => 'empty', 'remaining' => 0]);

        api::settings_updated();

        $this->assertFalse($cache->get('status'));
    }
}
