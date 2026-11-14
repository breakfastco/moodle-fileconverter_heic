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
 * Puts a Convert to .jpg button beside each .heic image and file link on the page that the viewer may convert.
 *
 * The server sends the files this person may convert. This finds where the
 * page shows them, by their pluginfile.php address, including parts of the
 * page that load later, such as the assignment grader.
 *
 * @module     fileconverter_heic/inline
 * @copyright  2026 Breakfast Company <https://breakfastco.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const SEEN = 'data-fileconverter-heic';
const HIDDEN = 'data-fileconverter-heic-hidden';
const FILE = 'data-fileconverter-heic-file';
const SELECTOR = 'a[href*="pluginfile.php"], img[src*="pluginfile.php"]';

let files = [];
let strings = {};

/**
 * Splits a pluginfile.php address into its parts.
 *
 * @param {string} address Link or image address.
 * @returns {object|null} contextid, component, filearea, folders, filename.
 */
const parse = (address) => {
    let url;
    try {
        url = new URL(address, window.location.href);
    } catch (e) {
        return null;
    }
    const marker = '/pluginfile.php';
    const at = url.pathname.indexOf(marker);
    if (at === -1) {
        return null;
    }
    let path = url.pathname.slice(at + marker.length);
    if (path === '' && url.searchParams.has('file')) {
        // Sites with slash arguments turned off.
        path = url.searchParams.get('file');
    }
    const parts = path.split('/').filter((part) => part !== '').map((part) => {
        try {
            return decodeURIComponent(part);
        } catch (e) {
            return part;
        }
    });
    if (parts.length < 4) {
        return null;
    }
    return {
        contextid: parseInt(parts[0], 10),
        component: parts[1],
        filearea: parts[2],
        // The item id, when the address has one, then any folders.
        folders: parts.slice(3, -1),
        filename: parts[parts.length - 1],
    };
};

/**
 * The convertible file an address points to.
 *
 * @param {object|null} parts From parse().
 * @returns {object|undefined}
 */
const match = (parts) => {
    if (!parts) {
        return undefined;
    }
    return files.find((file) => {
        if (file.converted || file.contextid !== parts.contextid || file.component !== parts.component ||
                file.filearea !== parts.filearea || file.filename !== parts.filename) {
            return false;
        }
        const folders = file.filepath.split('/').filter((part) => part !== '');
        return folders.every((folder, index) => parts.folders[parts.folders.length - folders.length + index] === folder);
    });
};

/**
 * The same address with a new file name.
 *
 * @param {string} address Link or image address.
 * @param {string} filename New file name.
 * @param {boolean} fresh Add a query string so the browser loads it again.
 * @returns {string}
 */
const rename = (address, filename, fresh) => {
    const url = new URL(address, window.location.href);
    if (url.searchParams.has('file')) {
        const file = url.searchParams.get('file');
        url.searchParams.set('file', file.slice(0, file.lastIndexOf('/') + 1) + filename);
    } else {
        url.pathname = url.pathname.slice(0, url.pathname.lastIndexOf('/') + 1) + encodeURIComponent(filename);
    }
    if (fresh) {
        url.searchParams.set('converted', Date.now());
    }
    return url.toString();
};

/**
 * The links and images on the page that show a file.
 *
 * Looked up each time, because some parts of Moodle, such as the grader's file
 * tree, rebuild their markup after the page loads.
 *
 * @param {object} file The file.
 * @returns {HTMLElement[]}
 */
const elementsFor = (file) => [...document.querySelectorAll(SELECTOR)].filter((element) =>
    match(parse(element.getAttribute(element.tagName === 'IMG' ? 'src' : 'href'))) === file);

/**
 * The selector for a file's button holders.
 *
 * @param {object} file The file.
 * @returns {string}
 */
const holdersOf = (file) => `.fileconverter-heic[${FILE}="${file.id}"]`;

/**
 * Points every link and image for a file at the converted .jpg.
 *
 * @param {object} file The converted file.
 * @param {string} filename Its new name.
 */
const refresh = (file, filename) => {
    elementsFor(file).forEach((element) => {
        if (element.tagName === 'IMG') {
            element.src = rename(element.getAttribute('src'), filename, true);
            if (element.hasAttribute(HIDDEN)) {
                element.removeAttribute(HIDDEN);
                element.classList.remove('d-none');
            }
            return;
        }
        element.href = rename(element.getAttribute('href'), filename, false);
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            if (walker.currentNode.nodeValue.includes(file.filename)) {
                walker.currentNode.nodeValue = walker.currentNode.nodeValue.replace(file.filename, filename);
            }
        }
    });
    file.filename = filename;
    file.converted = true;
};

/**
 * Converts a file when its button is pressed.
 *
 * @param {object} file The file.
 * @param {HTMLElement} holder The pressed button's holder.
 */
const convert = async(file, holder) => {
    const button = holder.querySelector('button');
    const status = holder.querySelector('[role="status"]');
    button.disabled = true;
    button.textContent = strings.converting;
    status.textContent = '';
    status.classList.remove('text-danger');
    try {
        const result = await Ajax.call([{
            methodname: 'fileconverter_heic_convert_image',
            args: {fileid: file.id},
        }])[0];
        if (!result.success) {
            status.textContent = result.error;
            status.classList.add('text-danger');
            button.disabled = false;
            button.textContent = strings.convert;
            return;
        }
        refresh(file, result.filename);
        // The image or link now shows the .jpg, which says enough.
        document.querySelectorAll(holdersOf(file)).forEach((element) => element.remove());
    } catch (error) {
        button.disabled = false;
        button.textContent = strings.convert;
        Notification.exception(error);
    }
};

/**
 * Puts Moodle's error box in place of a HEIC image this browser could not show.
 *
 * Browsers show only the alt text for an image they cannot read, which is easy
 * to miss. Safari can show HEIC, so nothing changes there.
 *
 * @param {HTMLImageElement} image The image.
 * @param {object} file Its file.
 * @param {HTMLElement} holder The button's holder. The box goes above the button.
 */
const markIfMissing = (image, file, holder) => {
    const mark = () => {
        if (image.naturalWidth !== 0 || image.hasAttribute(HIDDEN)) {
            return;
        }
        image.setAttribute(HIDDEN, '');
        image.classList.add('d-none');
        // Sized to its text, on its own line above the button.
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger d-inline-block mb-2';
        alert.textContent = strings.missing.replace('{$a}', image.getAttribute('alt') || file.filename);
        const box = document.createElement('div');
        box.append(alert);
        holder.prepend(box);
    };
    if (image.complete) {
        mark();
    } else {
        image.addEventListener('error', mark, {once: true});
    }
};

/**
 * Adds the button for a file after the element that shows it.
 *
 * @param {object} file The file.
 * @param {HTMLElement} element The link or image.
 */
const addButton = (file, element) => {
    const isImage = element.tagName === 'IMG';
    // Submitted files sit in Moodle's file tree, which is often wider than its column in the grader.
    // Below the name, the button stays in view.
    const ownLine = isImage || element.closest('.fileuploadsubmission, .ygtvhtml') !== null;
    const holder = document.createElement(ownLine ? 'div' : 'span');
    holder.setAttribute(FILE, file.id);
    if (isImage) {
        holder.className = 'fileconverter-heic my-2';
    } else {
        holder.className = ownLine ? 'fileconverter-heic my-1' : 'fileconverter-heic ms-2';
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-secondary';
    button.textContent = strings.convert;

    const status = document.createElement('span');
    status.className = 'small ms-2';
    status.setAttribute('role', 'status');

    holder.append(button, status);
    if (isImage) {
        markIfMissing(element, file, holder);
    }

    // An image inside a link goes after the link, so the button is not part of it.
    const anchor = isImage && element.parentElement.closest('a') ? element.parentElement.closest('a') : element;
    // The space lets the button wrap below a file name in a narrow column, such as the grader's.
    anchor.after(' ', holder);
};

/**
 * Finds links and images for convertible files that have no button yet.
 */
const scan = () => {
    document.querySelectorAll(SELECTOR).forEach((element) => {
        if (element.hasAttribute(SEEN)) {
            return;
        }
        element.setAttribute(SEEN, '');
        if (element.closest('[contenteditable="true"]')) {
            return;
        }
        const file = match(parse(element.getAttribute(element.tagName === 'IMG' ? 'src' : 'href')));
        if (!file) {
            return;
        }
        if (!document.querySelector(holdersOf(file))) {
            addButton(file, element);
        }
    });
};

/**
 * Starts looking for the files.
 *
 * @param {object[]} convertible Files the viewer may convert: id, contextid, component, filearea, filepath, filename.
 * @param {object} words The button and alt text wording: convert, converting, missing.
 */
export const init = (convertible, words) => {
    files = convertible.map((file) => ({...file, converted: false}));
    strings = words;
    scan();

    // One listener for every button. Moodle's file tree copies its markup, which drops listeners on the button itself.
    document.addEventListener('click', (event) => {
        const holder = event.target.closest('.fileconverter-heic');
        if (!holder || !event.target.closest('button')) {
            return;
        }
        const file = files.find((candidate) => String(candidate.id) === holder.getAttribute(FILE));
        if (file && !file.converted) {
            convert(file, holder);
        }
    });

    let queued = false;
    new MutationObserver(() => {
        if (queued) {
            return;
        }
        queued = true;
        // A timer rather than an animation frame, which browsers pause in background tabs.
        window.setTimeout(() => {
            queued = false;
            scan();
        }, 50);
    }).observe(document.body, {childList: true, subtree: true});
};
