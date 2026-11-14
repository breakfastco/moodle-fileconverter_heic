# HEIC Support for Moodle

`fileconverter_heic` lets teachers view and annotate iPhone photos (.heic and .heif) in the assignment grader.

Moodle has no converter for HEIC. A student who submits a photo straight from an iPhone gives the teacher a blank page in the grader and a file most computers cannot open. This plugin hands Moodle a PDF of the photo, the same thing Moodle builds for a JPEG.

## Free on servers that can convert, cloud for the rest

- **On this server, free.** When PHP has the Imagick extension and ImageMagick was built with libheif, images are converted on the Moodle server. No license key, no credits.
- **In the cloud, with credits.** When the server cannot read HEIC, images can be sent to the Breakfast cloud conversion service. One credit converts one image. A server that can convert never uses the cloud.

The plugin decides by decoding a sample HEIC image, because many servers list the format and still cannot read it.

## What it does

- Converts .heic and .heif to PDF for the assignment grader ("Annotate PDF"). Also offers JPG and WebP to any other code that asks Moodle's converter API.
- Teaches Moodle the .heic and .heif file types, so they count as image files in "Accepted file types".
- Shows the server's ImageMagick version, or the cloud settings and remaining credits when the server cannot convert.

Moodle stores each result against the file's content, so the same image is never converted twice.

## Requirements

- Moodle 4.5 or later. Developed and tested on Moodle 5.3.1 only.
- Either PHP Imagick with HEIC support on the Moodle server, or a license key with conversion credits.
- Ghostscript configured in Moodle, as the assignment grader already requires.
- For cloud conversion only: outbound HTTPS from the Moodle server to `breakfastco.xyz` and `heic.breakfastco.xyz`.

## Install

1. Copy this folder to `files/converter/heic` (Moodle 5.1 and later: `public/files/converter/heic`).
2. Visit Site administration, or run `php admin/cli/upgrade.php`.
3. Go to Site administration → Plugins → Document converters → HEIC Support. A server that can convert shows its ImageMagick version and needs nothing more. Otherwise save a license key and switch on cloud conversion.

The converter is enabled at install. It stays idle only when the server cannot convert and cloud conversion is not switched on.

## Submissions made before the plugin was set up

Moodle keeps the blank page it built when a conversion failed. To retry those submissions:

```bash
php files/converter/heic/cli/retry_failed.php
```

That lists them. Add `--run` to discard the blank pages and queue each submission for cron. Images converted in the cloud use one credit each.

## Privacy

When cloud conversion is used, image contents, the file name, and the site address are sent to the conversion service. Nothing leaves the server when it converts images itself. The plugin stores no personal data in Moodle. This is declared through Moodle's privacy API.

## Development

The store and conversion server addresses can be overridden in `config.php`:

```php
$CFG->forced_plugin_settings['fileconverter_heic'] = [
    'storeurl' => 'http://localhost:8766',
    'converturl' => 'http://localhost:8766/v1',
    'cloudonly' => 1, // Skip server conversion, to test the cloud route.
];
```

## License

GNU GPL v3 or later.
