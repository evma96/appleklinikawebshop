/* Print existing protected documents only; never generate or upload a PDF. */
(function () {
    'use strict';
    var root = document.querySelector('[data-akbo-print]');
    if (!root) { return; }
    if (root.dataset.akboPrint === 'sheet') {
        window.addEventListener('load', function () { window.print(); }, { once: true });
        return;
    }

    var button = root.querySelector('[data-document-print-button]');
    var status = root.querySelector('[data-document-print-status]');
    var frame = root.querySelector('[data-document-frame]');
    var ready = false;
    var objectUrl = null;
    function printDocument() {
        if (!ready) { return; }
        // A returned print() call does not prove that anything was printed.
        status.textContent = 'Nyomtatás indítása. Ha nem jelenik meg a nyomtatási ablak, használd a PDF megnyitása hivatkozást.';
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch (error) {
            status.textContent = 'A böngésző nem engedi a beágyazott PDF nyomtatását. Nyisd meg a PDF-et, és használd a megjelenítő nyomtatás műveletét.';
        }
    }
    button.addEventListener('click', printDocument);

    async function loadDocument() {
        try {
            var url = new URL(root.dataset.documentUrl, window.location.href);
            if (url.origin !== window.location.origin) { throw new Error('Different origin'); }
            // Reject login redirects/error HTML before enabling printing.
            var response = await fetch(url.href, { credentials: 'same-origin', redirect: 'error', cache: 'no-store' });
            if (!response.ok || !(response.headers.get('Content-Type') || '').toLowerCase().startsWith('application/pdf')) {
                throw new Error('PDF unavailable');
            }
            var blob = await response.blob();
            if ((await blob.slice(0, 5).text()) !== '%PDF-') { throw new Error('Invalid PDF header'); }
            objectUrl = URL.createObjectURL(blob);
            frame.addEventListener('load', function () {
                ready = true;
                button.disabled = false;
                status.textContent = 'A PDF betöltve. Ellenőrizd az előnézetet; a nyomtatás a böngésző párbeszédablakában történik.';
                requestAnimationFrame(function () { requestAnimationFrame(printDocument); });
            }, { once: true });
            frame.src = objectUrl;
            frame.hidden = false;
        } catch (error) {
            status.textContent = 'A PDF nem tölthető be biztonságosan. Nyisd meg újra a rendelést, vagy próbáld a PDF megnyitása hivatkozást. Nyomtatás nem indult.';
        }
    }
    window.addEventListener('pagehide', function () {
        if (objectUrl) { URL.revokeObjectURL(objectUrl); }
    }, { once: true });
    loadDocument();
}());
