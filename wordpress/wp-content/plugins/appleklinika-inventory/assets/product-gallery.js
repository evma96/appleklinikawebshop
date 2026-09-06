/* One gallery owner. No Woo-owned checkout nodes, observers or image pixel sampling. */
(function () {
    'use strict';
    const gallery = document.querySelector('[data-gallery-images]');
    if (!gallery) return;
    let images = JSON.parse(gallery.dataset.galleryImages);
    let index = 0, dialog, photo, canvas, count, zoomButton, fitButton, message;
    let zoom = 1, panX = 0, panY = 0, fitWidth = 0, fitHeight = 0, gesture, returnFocus, savedScroll;
    let savedBodyStyle = null, savedScrollBehavior, hadLock;
    const opener = gallery.querySelector('[data-gallery-open]');
    const stageImage = gallery.querySelector('[data-appleklinika-stage-image]');
    const thumbs = gallery.querySelector('.appleklinika-product-gallery__thumbs');
    const imageSizes = '(max-width: 600px) 92vw, 500px';

    function updateStage() {
        const image = images[index];
        stageImage.src = image.url;
        stageImage.srcset = image.srcset || '';
        stageImage.sizes = imageSizes;
        stageImage.alt = image.alt || '';
        opener.href = image.full || image.url;
        gallery.dataset.currentIndex = String(index);
        thumbs.hidden = images.length < 2;
        thumbs.querySelectorAll('button').forEach((button, i) => button.setAttribute('aria-pressed', String(i === index)));
    }

    function layout() {
        if (!dialog?.open) return;
        const image = images[index];
        // Decoded dimensions win over stale WP metadata (e.g. a replaced/oriented upload).
        const width = (!photo.hidden && photo.naturalWidth) || image.width || 600;
        const height = (!photo.hidden && photo.naturalHeight) || image.height || 600;
        const fit = Math.min((canvas.clientWidth - 16) / width, (canvas.clientHeight - 16) / height, 1);
        fitWidth = Math.max(1, width * fit); fitHeight = Math.max(1, height * fit);
        const maxX = Math.max(0, (fitWidth * zoom - canvas.clientWidth) / 2);
        const maxY = Math.max(0, (fitHeight * zoom - canvas.clientHeight) / 2);
        panX = Math.max(-maxX, Math.min(maxX, panX)); panY = Math.max(-maxY, Math.min(maxY, panY));
        photo.style.width = fitWidth + 'px'; photo.style.height = fitHeight + 'px';
        photo.style.transform = `translate(calc(-50% + ${panX}px), calc(-50% + ${panY}px)) scale(${zoom})`;
        canvas.dataset.zoomed = String(zoom > 1);
        zoomButton.textContent = zoom === 1 ? 'Nagyítás +' : `${zoom}× +`;
        zoomButton.setAttribute('aria-label', zoom === 4 ? 'Vissza a teljes képhez' : 'Kép nagyítása');
        fitButton.hidden = zoom === 1;
    }

    function setZoom(value) {
        if (value === 1 && document.activeElement === fitButton) zoomButton.focus({preventScroll: true});
        zoom = value; panX = panY = 0; layout();
    }
    function cycleZoom() { setZoom(zoom === 1 ? 2 : zoom === 2 ? 4 : 1); }
    function select(next) {
        index = (next + images.length) % images.length;
        updateStage();
        if (!dialog?.open) return;
        setZoom(1);
        photo.hidden = true;
        message.hidden = false; message.textContent = 'Kép betöltése…';
        photo.alt = images[index].alt || 'Termékkép';
        // Only the selected full-size image is fetched, and only once the viewer opens.
        photo.src = images[index].full || images[index].url;
        count.textContent = `${index + 1} / ${images.length}`;
        dialog.querySelectorAll('[data-direction]').forEach(button => { button.hidden = images.length < 2; });
        count.hidden = images.length < 2;
        layout();
    }

    function createViewer() {
        dialog = document.createElement('dialog');
        dialog.className = 'ak-image-viewer';
        dialog.setAttribute('aria-label', 'Termékképek nagy nézetben');
        dialog.innerHTML = `<div class="ak-image-viewer__bar"><p class="ak-image-viewer__title">Közelebbről</p><div class="ak-image-viewer__tools"><button type="button" data-zoom>Nagyítás +</button><button type="button" data-fit hidden aria-label="Vissza a teljes képhez">Teljes kép</button><button type="button" data-close aria-label="Képnézegető bezárása">✕</button></div></div><div class="ak-image-viewer__canvas" tabindex="0" aria-label="Termékkép; nagyítás után húzással vagy nyílbillentyűkkel mozgatható"><img class="ak-image-viewer__image" alt="" draggable="false"><span class="ak-image-viewer__message" role="status"></span></div><div class="ak-image-viewer__bottom"><button type="button" data-direction="-1" aria-label="Előző termékkép">←</button><span class="ak-image-viewer__count" aria-live="polite"></span><button type="button" data-direction="1" aria-label="Következő termékkép">→</button><p class="ak-image-viewer__help">Nagyítás: + / − · Mozgatás: húzás · Bezárás: Esc</p></div>`;
        document.body.append(dialog);
        canvas = dialog.querySelector('.ak-image-viewer__canvas');
        photo = dialog.querySelector('img'); count = dialog.querySelector('.ak-image-viewer__count');
        message = dialog.querySelector('.ak-image-viewer__message');
        zoomButton = dialog.querySelector('[data-zoom]'); fitButton = dialog.querySelector('[data-fit]');
        photo.addEventListener('load', () => { photo.hidden = false; message.hidden = true; layout(); });
        photo.addEventListener('error', () => { photo.hidden = true; message.hidden = false; message.textContent = 'A kép nem tölthető be. Válassz másik képet, vagy zárd be a nézetet.'; });
        dialog.querySelector('[data-close]').addEventListener('click', closeViewer);
        zoomButton.addEventListener('click', cycleZoom);
        fitButton.addEventListener('click', () => setZoom(1));
        dialog.querySelectorAll('[data-direction]').forEach(button => button.addEventListener('click', () => select(index + Number(button.dataset.direction))));
        dialog.addEventListener('close', () => { if (!dialog.open && savedBodyStyle !== null) restorePage(); });
        dialog.addEventListener('cancel', event => { event.preventDefault(); closeViewer(); });
        // Native modal top layer supplies inert background and ESC behavior.
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Tab') {
                const controls = [...dialog.querySelectorAll('button, [tabindex="0"]')].filter(el => !el.hidden);
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                event.preventDefault();
                if (zoom > 1) { panX += event.key === 'ArrowLeft' ? 60 : event.key === 'ArrowRight' ? -60 : 0; panY += event.key === 'ArrowUp' ? 60 : event.key === 'ArrowDown' ? -60 : 0; layout(); }
                else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') select(index + (event.key === 'ArrowLeft' ? -1 : 1));
            }
            if (event.key === '+' || event.key === '=') { event.preventDefault(); cycleZoom(); }
            if (event.key === '-' || event.key === '0') { event.preventDefault(); setZoom(1); }
        });
        canvas.addEventListener('pointerdown', event => {
            if (!event.isPrimary || event.button > 0) return;
            gesture = {id: event.pointerId, x: event.clientX, y: event.clientY, panX, panY, moved: false, onImage: event.target === photo};
            canvas.setPointerCapture(event.pointerId);
        });
        canvas.addEventListener('pointermove', event => {
            if (!gesture || event.pointerId !== gesture.id) return;
            const dx = event.clientX - gesture.x, dy = event.clientY - gesture.y;
            gesture.moved ||= Math.hypot(dx, dy) > 8;
            if (zoom > 1 && gesture.moved) { canvas.classList.add('is-dragging'); panX = gesture.panX + dx; panY = gesture.panY + dy; layout(); }
        });
        canvas.addEventListener('pointerup', event => {
            if (!gesture || event.pointerId !== gesture.id) return;
            const dx = event.clientX - gesture.x, dy = event.clientY - gesture.y, current = gesture;
            gesture = null; canvas.classList.remove('is-dragging');
            if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
            if (zoom === 1 && Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) select(index + (dx < 0 ? 1 : -1));
            else if (!current.moved) { if (current.onImage) cycleZoom(); else if (zoom === 1) closeViewer(); }
        });
        canvas.addEventListener('pointercancel', () => { gesture = null; canvas.classList.remove('is-dragging'); });
        dialog.addEventListener('click', event => { if (event.target === dialog) closeViewer(); });
        window.addEventListener('resize', layout);
    }

    function closeViewer() {
        dialog.close();
        restorePage(); // Restore before a rapid reopen, not in the queued native close event.
    }

    function restorePage() {
        if (savedBodyStyle === null) return;
        document.body.style.cssText = savedBodyStyle;
        savedBodyStyle = null;
        if (!hadLock) document.documentElement.classList.remove('ak-image-viewer-lock');
        window.scrollTo(savedScroll.x, savedScroll.y);
        document.documentElement.style.scrollBehavior = savedScrollBehavior;
        const target = returnFocus?.isConnected ? returnFocus : opener;
        target.focus({preventScroll: true});
        gesture = null;
    }

    opener.addEventListener('click', event => {
        if (!window.HTMLDialogElement || !HTMLDialogElement.prototype.showModal) return; // Native image-link fallback.
        event.preventDefault();
        if (!dialog) createViewer();
        if (dialog.open) return;
        returnFocus = document.activeElement === document.body ? opener : document.activeElement;
        savedScroll = {x: window.scrollX, y: window.scrollY};
        savedBodyStyle = document.body.style.cssText;
        savedScrollBehavior = document.documentElement.style.scrollBehavior;
        hadLock = document.documentElement.classList.contains('ak-image-viewer-lock');
        const gutter = window.innerWidth - document.documentElement.clientWidth;
        document.documentElement.style.scrollBehavior = 'auto';
        document.documentElement.classList.add('ak-image-viewer-lock');
        document.body.style.position = 'fixed'; document.body.style.top = -savedScroll.y + 'px';
        document.body.style.left = '0'; document.body.style.width = '100%';
        document.body.style.paddingRight = gutter + 'px';
        dialog.showModal(); select(index);
        dialog.querySelector('[data-close]').focus({preventScroll: true});
    });
    thumbs.addEventListener('click', event => {
        const button = event.target.closest('[data-gallery-index]');
        if (button && thumbs.contains(button)) select(Number(button.dataset.galleryIndex));
    });
    window.AppleklinikaProductGallery = {replace(nextImages) {
        if (!nextImages?.length) return;
        images = nextImages; index = 0;
        thumbs.replaceChildren(...images.map((image, i) => {
            const button = document.createElement('button'); button.type = 'button';
            button.className = 'appleklinika-product-gallery__thumb'; button.dataset.galleryIndex = String(i);
            button.setAttribute('aria-label', `Termékkép ${i + 1}`);
            const thumb = document.createElement('img'); thumb.src = image.thumb || image.url;
            thumb.alt = ''; thumb.loading = 'lazy'; thumb.width = thumb.height = 72;
            button.append(thumb); return button;
        }));
        select(0);
    }};
    updateStage();
}());
