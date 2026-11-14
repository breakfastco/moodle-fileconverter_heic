# HEIC Support for Moodle

`fileconverter_heic` makes iPhone photos (.heic and .heif) work in Moodle.

Moodle has no support for HEIC. A student who submits a photo straight from an iPhone gives the teacher a blank page in the assignment grader and a file most computers cannot open. Forum posts, quiz answers, and course files show a download link where a photo should be.

## Free on servers that can convert, cloud for the rest

- **On this server, free.** When PHP has the Imagick extension and ImageMagick was built with libheif, images are converted on the Moodle server. No license key, no credits.
- **In the cloud, with credits.** When the server cannot read HEIC, images can be sent to the Breakfast cloud conversion service. One credit converts one image. A server that can convert never uses the cloud.

The plugin decides by decoding a sample HEIC image, because many servers list the format and still cannot read it.

## What it does

- **Converts uploads as they arrive.** A .heic or .heif file becomes a .jpg the moment it is uploaded, before Moodle stores it. This covers everything that uses Moodle's file picker: assignment submissions, quiz essay attachments, forum, glossary and workshop attachments, and files added to a course. The original HEIC is not kept. If the image cannot be converted, the original is stored unchanged.
- **Lets teachers convert HEIC images already in a course.** "Convert HEIC images" in the course's More menu lists every .heic and .heif file in the course's activities, with a Convert button for each and a Convert all. Converting replaces the file with a JPEG and keeps its owner and dates. The original HEIC is not kept. Attachments are renamed to .jpg; images placed in text keep their name, because the text links to them by it. Submissions the assignment grader showed blank are queued to be rebuilt.
- **Shows HEIC files already in Moodle in the assignment grader** ("Annotate PDF"). As a document converter, it hands Moodle a PDF of the photo, the same thing Moodle builds for a JPEG, and leaves the stored file untouched. It also offers JPG and WebP to any other code that asks Moodle's converter API.
- **Opens the image-only upload points to HEIC.** The image button in the text editor, profile pictures, course images, and database picture fields refuse HEIC in plain Moodle. While this site can convert, they accept it and store a JPEG. If a conversion fails there, the upload is refused, as it would be without the plugin. An image added through the text editor keeps its .heic name, because the editor links to it by that name; the file itself is a JPEG and is served as one.
- **Teaches Moodle the .heic and .heif file types**, so they count as image files in "Accepted file types".
- Shows the server's ImageMagick version, or the cloud settings and remaining credits when the server cannot convert.

Moodle stores each document-converter result against the file's content, so the same stored image is never converted twice.

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

## Permissions

Converting stored images needs the capability `fileconverter/heic:convert` (Convert HEIC images) in the course or activity. By default editing teachers and managers have it; non-editing teachers and students do not. Because converting replaces the original file, the capability is marked as a data loss risk. Every conversion is logged with who converted which file.

Converting uploads as they arrive needs no extra permission: it applies to whatever the person was already allowed to upload.

After converting, browsers that recently tried to show the old HEIC may keep showing it broken for up to six hours, Moodle's cache time for files.

## Submissions made before the plugin was set up (command line)

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
