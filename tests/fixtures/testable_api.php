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

require_once(__DIR__ . '/fake_curl.php');

/**
 * The cloud API client, talking to a fake_curl instead of the network.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_api extends api {
    /** @var fake_curl The client every request goes through. */
    public fake_curl $curl;

    /**
     * Constructor.
     *
     * @param array[] $responses Responses to give, each [HTTP status, body].
     */
    public function __construct(array $responses) {
        $this->curl = new fake_curl();
        $this->curl->responses = $responses;
    }

    /**
     * The fake client.
     *
     * @return \curl
     */
    protected function http(): \curl {
        return $this->curl;
    }
}
