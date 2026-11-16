# HEIC Support

Moodle plugin. Converts raw iPhone .heic and .heif images to .jpg during uploads.

## Features

- **Teaches Moodle the .heic and .heif file types**, so they count as "Accepted file types".
- **Converts uploads as they arrive.** A .heic or .heif file becomes a .jpg the moment it is uploaded, before Moodle stores it. 
- **Lets teachers convert HEIC images already uploaded.** A "Convert to .jpg" button appears beside each .heic image and file link that the viewer may convert: a forum attachment, an image on a page, a file in the assignment grader.
- **Shows the server's ImageMagick version**, or the cloud settings and remaining credits when the server cannot convert.

## Free forever on most servers

- **Most web hosts support free conversion.** When PHP has the Imagick extension and ImageMagick was built with libheif, images are converted by this plugin on the Moodle server for free.
- **In the cloud, with credits.** When the server cannot read HEIC, images can be sent to the Cloud Conversion server. One credit converts one image.

## Where to see it

- **Uploads:** anywhere people upload with Moodle's file picker, such as assignment submissions, quiz essay attachments, forum posts and course files. The .jpg is stored instead of the .heic. An image that cannot be converted is stored unchanged.
- **Image-only uploads:** the text editor's Image button, profile pictures and course images refuse .heic in plain Moodle. While the site can convert, they accept it and store a .jpg.
- **Convert to .jpg button:** beside .heic images and file links already in Moodle, such as images on a page, forum attachments and files in the assignment grader. Only people allowed to edit the file see it. Above an image the browser cannot show, a red box reads "Cannot display .heic image". Each conversion is recorded in Moodle's logs (Reports → Logs) as "HEIC image converted".
- **Assignment grader:** with Annotate PDF feedback, a .heic submission shows as a photo teachers can mark up. The student's file is not changed.
- **Settings:** Site administration → Plugins → Document converters → HEIC Support.

## Requirements

- Moodle 4.5 or later. Tested on Moodle 4.5.16 and 5.3.1, with PostgreSQL 17 and MySQL 8.4.
- Either PHP Imagick with HEIC support on the Moodle server, or a license key with conversion credits.
- For cloud conversion only: outbound HTTPS from the Moodle server to `breakfastco.xyz` and `heic.breakfastco.xyz`.

## Install

1. Copy this folder to `files/converter/heic` (Moodle 5.1 and later: `public/files/converter/heic`).
2. Visit Site administration, or run `php admin/cli/upgrade.php`.
3. Go to Site administration → Plugins → Document converters → HEIC Support. A server that can convert shows its ImageMagick version and needs nothing more. Otherwise save a license key and switch on cloud conversion.

The converter is enabled at install. It stays idle only when the server cannot convert and cloud conversion is not switched on.

## Privacy

When cloud conversion is used, image contents, the file name, and the site address are sent to the conversion service. Nothing leaves the server when it converts images itself. The plugin stores no personal data in Moodle. This is declared through Moodle's privacy API.
