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

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Client for the Breakfast cloud conversion service.
 *
 * A conversion is two requests. The store takes one credit from the license
 * and returns a short-lived signed ticket. The conversion server accepts the
 * ticket with the image and returns the converted file.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string Where credits are counted. */
    const DEFAULT_STORE_URL = 'https://breakfastco.xyz';

    /** @var string Where images are converted. */
    const DEFAULT_CONVERT_URL = 'https://heic.breakfastco.xyz/v1';

    /** @var string[] Image formats the conversion server can return. */
    const FORMATS = ['jpeg', 'webp', 'avif'];

    /**
     * The saved license key.
     *
     * @return string
     */
    public static function license_key(): string {
        return trim((string) get_config('fileconverter_heic', 'licensekey'));
    }

    /**
     * Whether cloud conversion is set up: a license key is saved and cloud conversion is switched on.
     *
     * @return bool
     */
    public static function is_active(): bool {
        return self::license_key() !== '' && (bool) get_config('fileconverter_heic', 'cloudenabled');
    }

    /**
     * Store base URL without a trailing slash.
     *
     * Not shown in the settings page. Override it for development with
     * $CFG->forced_plugin_settings['fileconverter_heic']['storeurl'] in config.php.
     *
     * @return string
     */
    public static function store_url(): string {
        $url = trim((string) get_config('fileconverter_heic', 'storeurl'));
        return rtrim($url !== '' ? $url : self::DEFAULT_STORE_URL, '/');
    }

    /**
     * Conversion server base URL without a trailing slash.
     *
     * Override with $CFG->forced_plugin_settings['fileconverter_heic']['converturl'].
     *
     * @return string
     */
    public static function convert_url(): string {
        $url = trim((string) get_config('fileconverter_heic', 'converturl'));
        return rtrim($url !== '' ? $url : self::DEFAULT_CONVERT_URL, '/');
    }

    /**
     * Converts one HEIC file and returns the converted image bytes.
     *
     * Uses one credit.
     *
     * @param  string $path     Path to the HEIC file.
     * @param  string $filename Original file name.
     * @param  string $format   One of self::FORMATS.
     * @return string Converted image contents.
     * @throws conversion_exception
     */
    public function convert(string $path, string $filename, string $format): string {
        if (!in_array($format, self::FORMATS, true)) {
            throw new conversion_exception('error_convertfailed', 'unsupported format ' . $format);
        }
        $ticket = $this->consume();
        return $this->send($path, $filename, $format, $ticket);
    }

    /**
     * Takes one credit from the license and returns a conversion ticket.
     *
     * @return string Signed ticket.
     * @throws conversion_exception
     */
    protected function consume(): string {
        global $CFG;

        if (!self::is_active()) {
            throw new conversion_exception('error_cloudoff');
        }

        $curl = $this->http();
        $body = $curl->post(
            self::store_url() . '/wp-json/heic-support/v1/credits/consume',
            http_build_query([
                'license' => self::license_key(),
                'site' => $CFG->wwwroot,
                'amount' => 1,
            ], '', '&'),
            ['CURLOPT_TIMEOUT' => 20, 'CURLOPT_CONNECTTIMEOUT' => 10]
        );
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $code === 0) {
            throw new conversion_exception('error_storeunreachable', self::transport_error($curl, $body));
        }
        $data = json_decode((string) $body, true);

        if ($code === 402) {
            self::remember_status('empty', 0);
            throw new conversion_exception('error_nocredits');
        }
        if ($code === 401) {
            self::remember_status('invalid', 0);
            throw new conversion_exception('error_invalidlicense');
        }
        if ($code !== 200 || empty($data['ticket'])) {
            throw new conversion_exception('error_consumefailed', 'HTTP ' . $code);
        }
        if (isset($data['remaining'])) {
            $remaining = (int) $data['remaining'];
            self::remember_status($remaining > 0 ? 'active' : 'empty', $remaining);
        }
        return (string) $data['ticket'];
    }

    /**
     * Sends the image and ticket to the conversion server.
     *
     * @param  string $path     Path to the HEIC file.
     * @param  string $filename Original file name.
     * @param  string $format   Output format.
     * @param  string $ticket   Signed ticket.
     * @return string Converted image contents.
     * @throws conversion_exception
     */
    protected function send(string $path, string $filename, string $format, string $ticket): string {
        $curl = $this->http();
        $body = $curl->post(
            self::convert_url() . '/convert',
            [
                'ticket' => $ticket,
                'format' => $format,
                'file' => new \CURLFile($path, 'image/heic', $filename),
            ],
            ['CURLOPT_TIMEOUT' => 120, 'CURLOPT_CONNECTTIMEOUT' => 10]
        );
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $code === 0) {
            throw new conversion_exception('error_serverunreachable', self::transport_error($curl, $body));
        }
        if ($code === 503) {
            throw new conversion_exception('error_busy');
        }
        if ($code !== 200) {
            throw new conversion_exception('error_convertfailed', 'HTTP ' . $code);
        }
        if (!is_string($body) || $body === '') {
            throw new conversion_exception('error_convertempty');
        }
        return $body;
    }

    /**
     * Why a request never got an HTTP response.
     *
     * Moodle returns a short message in place of the body when its own
     * security settings block the URL, and sets no cURL error.
     *
     * @param  \curl $curl The client after the request.
     * @param  mixed $body What the request returned.
     * @return string
     */
    protected static function transport_error(\curl $curl, $body): string {
        if (!empty($curl->error)) {
            return (string) $curl->error;
        }
        return is_string($body) ? \core_text::substr($body, 0, 200) : '';
    }

    /**
     * License status and remaining credits, cached for ten minutes.
     *
     * @return array{status: string, remaining: int} Status is active, empty, invalid, or unreachable.
     */
    public static function status(): array {
        global $CFG;

        $cache = \cache::make('fileconverter_heic', 'credits');
        $cached = $cache->get('status');
        if (is_array($cached)) {
            return $cached;
        }

        $key = self::license_key();
        if ($key === '') {
            return ['status' => 'invalid', 'remaining' => 0];
        }

        $curl = (new self())->http();
        $body = $curl->get(
            self::store_url() . '/wp-json/heic-support/v1/credits',
            ['license' => $key, 'site' => $CFG->wwwroot],
            ['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]
        );
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        $data = json_decode((string) $body, true);
        if ($curl->get_errno() || $code !== 200 || !is_array($data)) {
            // Not cached, so the next page view asks again.
            return ['status' => 'unreachable', 'remaining' => 0];
        }

        $status = isset($data['status']) ? (string) $data['status'] : 'invalid';
        $remaining = isset($data['remaining']) ? (int) $data['remaining'] : 0;
        self::remember_status($status, $remaining);
        return ['status' => $status, 'remaining' => $remaining];
    }

    /**
     * Caches the license status.
     *
     * @param string $status    Status.
     * @param int    $remaining Remaining credits.
     */
    protected static function remember_status(string $status, int $remaining): void {
        \cache::make('fileconverter_heic', 'credits')->set('status', [
            'status' => $status,
            'remaining' => $remaining,
        ]);
    }

    /**
     * Forgets the cached license status. Runs when the license key changes.
     */
    public static function purge_status(): void {
        \cache::make('fileconverter_heic', 'credits')->delete('status');
    }

    /**
     * Runs when the license key or the cloud conversion switch is saved.
     */
    public static function settings_updated(): void {
        self::purge_status();
        filetypes::sync();
    }

    /**
     * HTTP client. Moodle's wrapper honours the site's proxy and blocked-host settings.
     *
     * @return \curl
     */
    protected function http(): \curl {
        return new \curl();
    }
}
