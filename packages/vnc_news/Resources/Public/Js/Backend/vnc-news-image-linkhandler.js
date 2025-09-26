/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

import LinkBrowser
    from "@typo3/backend/link-browser.js";

/**
 * Module: @t3docs/examples/github_link_handler.js
 * Github issue link interaction
 */

class NewsImageLinkHandler {
    constructor() {
        const form = document.getElementById('limageform');
        alert('huhu');
        if (!form) return;
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const uidEl = document.getElementById('limage-uid');
            const uid = uidEl ? uidEl.value.trim() : '';
            if (!uid || Number(uid) <= 0) {
                // optionally show an inline error
                return;
            }
            // Here we hand over raw HTML — LinkBrowser will insert this string into the editor.
            // NOTE: RTE/CKEditor sanitize rules must allow <img> tags.
            const html = '<img src="t3://file?uid=' + uid + '" />';
            LinkBrowser.finalizeFunction(html);
        });
    }
}