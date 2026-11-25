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
 * An HTTP client that answers from a list of canned responses and records each request.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_curl extends \curl {
    /** @var array[] Responses still to give, each [HTTP status, body]. Status 0 is a failed connection. */
    public array $responses = [];

    /** @var array[] Requests made, each [method, URL, parameters]. */
    public array $requests = [];

    /** @var int Status of the last response. */
    protected int $lastcode = 0;

    /**
     * Records a POST and returns the next canned body.
     *
     * @param  string       $url     URL.
     * @param  array|string $params  Parameters.
     * @param  array        $options cURL options.
     * @return string
     */
    public function post($url, $params = '', $options = []) {
        return $this->answer('POST', $url, $params);
    }

    /**
     * Records a GET and returns the next canned body.
     *
     * @param  string $url     URL.
     * @param  array  $params  Parameters.
     * @param  array  $options cURL options.
     * @return string
     */
    public function get($url, $params = [], $options = []) {
        return $this->answer('GET', $url, $params);
    }

    /**
     * The status of the last response, as \curl reports it.
     *
     * @return array
     */
    public function get_info() {
        return ['http_code' => $this->lastcode];
    }

    /**
     * Never a cURL error; a failed connection is status 0.
     *
     * @return int
     */
    public function get_errno() {
        return 0;
    }

    /**
     * Records a request and gives the next response.
     *
     * @param  string       $method GET or POST.
     * @param  string       $url    URL.
     * @param  array|string $params Parameters.
     * @return string
     */
    protected function answer(string $method, string $url, $params): string {
        $this->requests[] = [$method, $url, $params];
        [$this->lastcode, $body] = array_shift($this->responses) ?? [0, ''];
        return $body;
    }
}
