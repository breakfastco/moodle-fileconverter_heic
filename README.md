# HEIC Support for Moodle

`fileconverter_heic` makes iPhone photos (.heic and .heif) work in Moodle.

Moodle has no support for HEIC. A student who submits a photo straight from an iPhone gives the teacher a blank page in the assignment grader and a file most computers cannot open. Forum posts, quiz answers, and course files show a download link where a photo should be.

## Free on servers that can convert, cloud for the rest

- **On this server, free.** When PHP has the Imagick extension and ImageMagick was built with libheif, images are converted on the Moodle server. No license key, no credits.
- **In the cloud, with credits.** When the server cannot read HEIC, images can be sent to the Breakfast cloud conversion service. One credit converts one image. A server that can convert never uses the cloud.

The plugin decides by decoding a sample HEIC image, because many servers list the format and still cannot read it.

## What it does

- **Converts uploads as they arrive.** A .heic or .heif file becomes a .jpg the moment it is uploaded, before Moodle stores it. This covers everything that uses Moodle's file picker: assignment submissions, quiz essay attachments, forum, glossary and workshop attachments, and files added to a course. The original HEIC is not kept. If the image cannot be converted, the original is stored unchanged.
- **Lets teachers convert HEIC images already in a course, where they see them.** A "Convert to .jpg" button appears beside each .heic image and file link that the viewer may convert: a forum attachment, an image on a page, a file in the assignment grader. An image the browser can't show is replaced by Moodle's red error box, "Cannot display .heic image", with the button below it. Pressing it swaps in the JPEG without reloading the page. People who can't convert the file see no button and no box. The button finds files by their address on the page, so it works in any activity, including ones Moodle adds later. Converting replaces the file with a JPEG and keeps its owner and dates. The original HEIC is not kept. Attachments are renamed to .jpg; images placed in text keep their name, because the text links to them by it. Submissions the assignment grader showed blank are queued to be rebuilt.
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

HEIC Support adds no permissions of its own. Converting a stored image replaces it, so it takes the permission Moodle already uses to edit the thing the image belongs to:

| Where the image is | Permission |
| --- | --- |
| Assignment submission | Edit another user's submission (`mod/assign:editothersubmission`). Moodle gives this to managers only by default. |
| Forum post | Edit any post (`mod/forum:editanypost`) |
| Glossary entry | Manage entries (`mod/glossary:manageentries`) |
| Database entry | Manage entries (`mod/data:manageentries`) |
| Quiz answer, workshop submission | Nobody. Moodle lets nobody edit these after they are submitted. |
| An activity's own content, such as a page or file | Manage activities (`moodle/course:manageactivities`) |
| Course summary or section | Update course settings (`moodle/course:update`) |

Teachers who can't convert an assignment submission still see its photo in the assignment grader, which shows a converted copy and never changes the student's file.

Converting uploads as they arrive needs no extra permission: it applies to whatever the person was already allowed to upload. Every conversion of a stored image is logged with who converted which file.

The person who converts sees the JPEG at once. Images placed in text keep their address when converted, so other people's browsers that recently tried to show the old HEIC may keep showing it broken for up to six hours, Moodle's cache time for files. Renamed attachments get a new address and are not affected.

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

The browser script's source is `amd/src/inline.js`. `amd/build/inline.min.js` was written by hand from it, without Moodle's build tools. Run `grunt amd` from the Moodle root to rebuild it before a release.

## License

GNU GPL v3 or later.
