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
 * Strings for HEIC Support.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_credits'] = 'License status and remaining conversion credits';
$string['cachedef_server'] = 'Whether this server can convert HEIC images itself';
$string['cantconvert'] = 'Conversion';
$string['cantconvert_desc'] = 'This server can\'t convert .heic images on its own. They will upload, but won\'t display in most browsers. HEIC Support can convert them for you automatically in the cloud, without installing more software. One credit converts one image. Add your license key below to switch it on.';
$string['cloudenabled'] = 'Cloud conversion';
$string['cloudenabled_desc'] = 'Turn this on after you have saved a license key with available credits.';
$string['cloudenabled_label'] = 'Automatically convert every new .heic upload in the cloud (uses 1 credit per image).';
$string['convert'] = 'Convert';
$string['converting'] = 'Converting…';
$string['convertinline'] = 'Convert to .jpg';
$string['convertinlinedone'] = 'Converted to .jpg.';
$string['convertunavailable'] = 'This site can\'t convert .heic images. Ask your site administrator about HEIC Support.';
$string['error_busy'] = 'The conversion server is busy. Try again in a few minutes.';
$string['error_cloudoff'] = 'Cloud conversion is not available.';
$string['error_consumefailed'] = 'Could not start the cloud conversion.';
$string['error_convertempty'] = 'The conversion produced an empty file.';
$string['error_convertfailed'] = 'The cloud conversion failed.';
$string['error_copyfailed'] = 'Could not read the submitted file.';
$string['error_invalidlicense'] = 'The license key is invalid.';
$string['error_localfailed'] = 'This server could not convert the image.';
$string['error_nocredits'] = 'Out of conversion credits.';
$string['error_pdffailed'] = 'Could not build a PDF from the converted image.';
$string['error_serverunreachable'] = 'Could not reach the conversion server.';
$string['error_storeunreachable'] = 'Could not reach the license server.';
$string['error_unsupported'] = 'This converter only converts .heic and .heif images.';
$string['eventimageconverted'] = 'HEIC image converted';
$string['imagemagick'] = 'ImageMagick';
$string['intro'] = 'Control how .heic images are handled during uploads.';
$string['licensekey'] = 'License key';
$string['licensekey_desc'] = 'Enter the license key from your conversion credits purchase and save changes.';
$string['pluginname'] = 'HEIC Support';
$string['privacy:metadata:breakfastcloud'] = 'When cloud conversion is switched on, HEIC and HEIF images are sent to the Breakfast cloud conversion service to be converted to a format Moodle can display.';
$string['privacy:metadata:breakfastcloud:filecontent'] = 'The contents of the image file.';
$string['privacy:metadata:breakfastcloud:filename'] = 'The name of the image file.';
$string['privacy:metadata:breakfastcloud:siteurl'] = 'The address of this Moodle site, used to account for conversion credits.';
$string['status'] = 'License status';
$string['status_active'] = 'Active. Credits remaining: {$a}.';
$string['status_empty'] = 'Empty. Credits remaining: 0.';
$string['status_invalid'] = 'Invalid.';
$string['status_unreachable'] = 'Could not reach the license server to check this key.';
$string['supportedconversions'] = 'heic, heif => pdf, jpg, webp';
