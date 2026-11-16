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

/**
 * Settings for HEIC Support.
 *
 * Follows the WordPress plugin. A server that can convert .heic images itself
 * never sees the cloud settings, unless a license key is already saved.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use fileconverter_heic\api;
use fileconverter_heic\local_converter;

if ($hassiteconfig) {
    $onscreen = $ADMIN->fulltree && optional_param('section', '', PARAM_ALPHANUMEXT) === $section;

    // Test the server again whenever this page is on screen, so the answer follows server changes.
    $local = local_converter::is_available($onscreen);
    if ($onscreen) {
        \fileconverter_heic\filetypes::sync();
    }

    $settings->add(new admin_setting_heading(
        'fileconverter_heic/intro',
        '',
        get_string('intro', 'fileconverter_heic')
    ));

    $version = local_converter::imagemagick_version();
    if ($version !== '') {
        $settings->add(new admin_setting_description(
            'fileconverter_heic/imagemagick',
            get_string('imagemagick', 'fileconverter_heic'),
            s($version)
        ));
    }

    if (!$local) {
        $settings->add(new admin_setting_description(
            'fileconverter_heic/cantconvert',
            get_string('cantconvert', 'fileconverter_heic'),
            get_string('cantconvert_desc', 'fileconverter_heic')
        ));
    }

    if (!$local || api::license_key() !== '') {
        $cloudenabled = new admin_setting_configcheckbox(
            'fileconverter_heic/cloudenabled',
            get_string('cloudenabled', 'fileconverter_heic'),
            get_string('cloudenabled_label', 'fileconverter_heic') . ' ' . get_string('cloudenabled_desc', 'fileconverter_heic'),
            0
        );
        $cloudenabled->set_updatedcallback('\fileconverter_heic\api::settings_updated');
        $settings->add($cloudenabled);

        // The key sits just above its status.
        $licensekey = new admin_setting_configpasswordunmask(
            'fileconverter_heic/licensekey',
            get_string('licensekey', 'fileconverter_heic'),
            get_string('licensekey_desc', 'fileconverter_heic'),
            ''
        );
        $licensekey->set_updatedcallback('\fileconverter_heic\api::settings_updated');
        $settings->add($licensekey);

        // Ask the license server only when this page itself is on screen, not for every admin page or search.
        if ($onscreen && api::license_key() !== '') {
            $info = api::status();
            $known = ['active', 'empty', 'invalid', 'unreachable'];
            $status = in_array($info['status'], $known, true) ? $info['status'] : 'invalid';
            $settings->add(new admin_setting_description(
                'fileconverter_heic/status',
                get_string('status', 'fileconverter_heic'),
                get_string('status_' . $status, 'fileconverter_heic', $info['remaining'])
            ));
        }
    }
}
