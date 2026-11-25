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

/**
 * Tests for converting on this server and choosing between this server and the cloud.
 *
 * Tests that convert are skipped on servers whose ImageMagick cannot read HEIC.
 *
 * @package   fileconverter_heic
 * @copyright 2026 Breakfast Company <https://breakfastco.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \fileconverter_heic\local_converter
 * @covers    \fileconverter_heic\image_converter
 */
#[\PHPUnit\Framework\Attributes\CoversClass(local_converter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(image_converter::class)]
final class local_converter_test extends \advanced_testcase {
    /** @var string The sample image shipped with the plugin. */
    const SAMPLE = __DIR__ . '/../samples/sample.heic';

    /**
     * Skips the test when this server cannot convert HEIC.
     */
    protected function require_local(): void {
        if (!local_converter::is_available(true)) {
            $this->markTestSkipped('This server\'s ImageMagick cannot read HEIC.');
        }
    }

    /**
     * iPhones name photos in upper case.
     */
    public function test_is_heic(): void {
        $this->assertTrue(image_converter::is_heic('IMG_0001.HEIC'));
        $this->assertTrue(image_converter::is_heic('photo.heif'));
        $this->assertTrue(image_converter::is_heic('my.photo.Heic'));
        $this->assertFalse(image_converter::is_heic('photo.jpg'));
        $this->assertFalse(image_converter::is_heic('heic'));
        $this->assertFalse(image_converter::is_heic('photo.heic.pdf'));
    }

    /**
     * The sample converts to a JPEG and a WebP of the same size.
     */
    public function test_convert_sample(): void {
        $this->resetAfterTest();
        $this->require_local();

        $jpeg = getimagesizefromstring(local_converter::convert(self::SAMPLE, 'jpeg'));
        $this->assertSame(IMAGETYPE_JPEG, $jpeg[2]);
        $this->assertGreaterThan(0, $jpeg[0]);

        $webp = getimagesizefromstring(local_converter::convert(self::SAMPLE, 'webp'));
        $this->assertSame(IMAGETYPE_WEBP, $webp[2]);
        $this->assertSame([$jpeg[0], $jpeg[1]], [$webp[0], $webp[1]]);
    }

    /**
     * Transparent areas become white in a JPEG, which cannot be transparent, instead of black.
     */
    public function test_transparent_areas_become_white(): void {
        $this->resetAfterTest();
        $this->require_local();

        // A transparent square. The converter reads any format ImageMagick knows.
        $source = make_request_directory() . '/clear.png';
        $image = new \Imagick();
        $image->newImage(20, 20, new \ImagickPixel('transparent'));
        $image->setImageFormat('png');
        $image->writeImage($source);
        $image->clear();

        $jpeg = new \Imagick();
        $jpeg->readImageBlob(local_converter::convert($source, 'jpeg'));
        $colour = $jpeg->getImagePixelColor(10, 10)->getColor();
        $this->assertSame([255, 255, 255], [$colour['r'], $colour['g'], $colour['b']]);
    }

    /**
     * Formats the server does not write, and files that are not images, are reported as failures.
     */
    public function test_convert_failures(): void {
        $this->resetAfterTest();
        $this->require_local();

        try {
            local_converter::convert(self::SAMPLE, 'avif');
            $this->fail('avif is not written on this server.');
        } catch (conversion_exception $e) {
            $this->assertSame('error_localfailed', $e->errorcode);
        }

        $text = make_request_directory() . '/not-an-image.heic';
        file_put_contents($text, 'This is not a photo.');
        try {
            local_converter::convert($text, 'jpeg');
            $this->fail('A text file is not an image.');
        } catch (conversion_exception $e) {
            $this->assertSame('error_localfailed', $e->errorcode);
        }
    }

    /**
     * Images are converted on this server whenever it can, and the route says so.
     */
    public function test_convert_uses_this_server_when_it_can(): void {
        $this->resetAfterTest();
        $this->require_local();
        // Even with cloud conversion set up, a server that can convert never uses a credit.
        set_config('licensekey', 'TEST-KEY', 'fileconverter_heic');
        set_config('cloudenabled', 1, 'fileconverter_heic');

        [$image, $route] = image_converter::convert(self::SAMPLE, 'IMG_0001.HEIC', 'jpeg');

        $this->assertSame(image_converter::ROUTE_LOCAL, $route);
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($image)[2]);
    }

    /**
     * The cloudonly setting turns this server's conversion off, leaving the cloud.
     */
    public function test_is_ready(): void {
        $this->resetAfterTest();
        set_config('cloudonly', 1, 'fileconverter_heic');

        $this->assertFalse(local_converter::is_available());
        $this->assertFalse(image_converter::is_ready());

        set_config('licensekey', 'TEST-KEY', 'fileconverter_heic');
        set_config('cloudenabled', 1, 'fileconverter_heic');
        $this->assertTrue(image_converter::is_ready());
    }
}
