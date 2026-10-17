// Offline browser-contract tests. No real PDF, order, credential or network.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/document-print.js'), 'utf8');
let assertions = 0;
function check(value, message) { ++assertions; assert.ok(value, message); }
function target(extra = {}) {
    return Object.assign({ listeners: {}, addEventListener(name, fn) { this.listeners[name] = fn; } }, extra);
}
async function browser(options = {}) {
    const result = { fetches: [], framePrints: 0, pagePrints: 0, created: 0, revoked: 0, frames: [] };
    const button = target({ disabled: true });
    const status = { textContent: '' };
    const frame = target({ hidden: true, contentWindow: {
        focus() {}, print() { ++result.framePrints; if (options.printDenied) { throw new Error('Browser restriction'); } },
    } });
    const root = {
        dataset: { akboPrint: options.sheet ? 'sheet' : 'pdf', documentUrl: options.url || 'https://backoffice.test/wp-admin/admin-post.php?action=invoice&order_id=123&_wpnonce=unit' },
        querySelector(selector) { return selector.includes('button') ? button : selector.includes('status') ? status : frame; },
    };
    const window = target({ location: { href: 'https://backoffice.test/print', origin: 'https://backoffice.test' }, print() { ++result.pagePrints; } });
    class DocumentURL extends URL {
        static createObjectURL() { ++result.created; return 'blob:https://backoffice.test/isolated'; }
        static revokeObjectURL() { ++result.revoked; }
    }
    vm.runInNewContext(source, {
        document: { querySelector() { return options.noRoot ? null : root; } }, window, URL: DocumentURL,
        requestAnimationFrame(fn) { result.frames.push(fn); },
        async fetch(url, settings) {
            result.fetches.push({ url, settings });
            if (options.fetchError) { throw new Error('Failed or redirected request'); }
            return { ok: !options.httpError, headers: { get() { return options.type || 'application/pdf'; } },
                async blob() { return new Blob([options.invalidPdf ? '<html>Login</html>' : '%PDF-1.4 isolated test bytes']); } };
        },
    });
    // Resolve native Blob/text promises without simulating successful rendering.
    for (let i = 0; i < 5; ++i) { await new Promise(resolve => setImmediate(resolve)); }
    return Object.assign(result, { button, status, frame, window,
        render() { if (frame.listeners.load) { frame.listeners.load(); } },
        animationFrames() { while (result.frames.length) { result.frames.shift()(); } },
    });
}
(async () => {
    const pdf = await browser();
    check(pdf.fetches.length === 1 && pdf.fetches[0].url.includes('_wpnonce=unit'), 'Retrieve the existing nonce-protected document exactly once.');
    check(pdf.fetches[0].settings.credentials === 'same-origin' && pdf.fetches[0].settings.redirect === 'error' && pdf.fetches[0].settings.cache === 'no-store', 'Keep auth, reject redirects and avoid persistent PDF caching.');
    check(pdf.created === 1 && pdf.frame.src.startsWith('blob:') && !pdf.frame.hidden, 'Render only the validated response using a transient object URL.');
    check(pdf.button.disabled && pdf.framePrints === 0, 'Do not print before the PDF frame has loaded.');
    pdf.render();
    check(!pdf.button.disabled && pdf.framePrints === 0, 'A loaded PDF enables manual printing before the automatic request.');
    pdf.animationFrames();
    check(pdf.framePrints === 1 && pdf.pagePrints === 0, 'Print the PDF frame, never the surrounding HTML wrapper.');
    pdf.button.listeners.click();
    check(pdf.framePrints === 2, 'A separate employee click can retry browser printing with user activation.');
    check(pdf.status.textContent.includes('Ha nem jelenik meg') && !pdf.status.textContent.includes('sikeres'), 'Never claim a completed print from a returned print call.');
    pdf.window.listeners.pagehide();
    check(pdf.revoked === 1, 'Release the temporary PDF URL when leaving.');

    const restricted = await browser({ printDenied: true });
    restricted.render(); restricted.animationFrames();
    check(restricted.status.textContent.includes('Nyisd meg a PDF-et') && !restricted.button.disabled, 'Browser restrictions retain a clear native-PDF fallback and retry.');

    for (const options of [{ httpError: true }, { type: 'text/html' }, { invalidPdf: true }, { fetchError: true }]) {
        const failed = await browser(options);
        check(failed.button.disabled && failed.created === 0 && failed.framePrints === 0, 'Errors/login HTML/invalid PDF cannot be printed.');
        check(failed.status.textContent.includes('Nyomtatás nem indult'), 'Failed loading has an honest recoverable state.');
    }
    const external = await browser({ url: 'https://external.test/file.pdf' });
    check(external.fetches.length === 0 && external.created === 0, 'Do not retrieve external document URLs.');
    const sheet = await browser({ sheet: true });
    check(sheet.fetches.length === 0 && sheet.pagePrints === 0, 'Order-sheet printing waits for the actual HTML page load without fetching providers.');
    sheet.window.listeners.load();
    check(sheet.pagePrints === 1 && sheet.framePrints === 0, 'Order sheet invokes its own standard browser print flow.');
    check((await browser({ noRoot: true })).fetches.length === 0, 'Normal Back Office pages do not load or print documents.');
    console.log(`Back Office document printing: ${assertions} assertions passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
