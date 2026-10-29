/* One page, one physical unit. No identity or image data is persisted in browser storage. */
(() => {
    'use strict';
    const configNode = document.getElementById('akbo-intake-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent);
    const form = document.getElementById('akbo-intake-form');
    const context = document.getElementById('akbo-intake-context');
    const feedback = document.getElementById('akbo-intake-feedback');
    const success = document.getElementById('akbo-intake-success');
    const photoList = document.getElementById('akbo-intake-photo-list');
    const picker = document.getElementById('akbo-intake-files');
    const identityError = document.getElementById('akbo-intake-identity-error');
    let photos = [], busy = false, saved = config.saved, operation = config.operation, dragged = null;
    let contextMemory = {}, identityVersion = 0, dirty = false;
    const field = name => form.elements.namedItem(name);
    const node = (tag, text, className) => {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    const message = (text, error = false) => {
        feedback.replaceChildren(node('p', text, error ? 'akbo-notice akbo-notice--error' : 'akbo-notice'));
        feedback.setAttribute('role', error ? 'alert' : 'status');
    };
    const options = (select, values, preferred = '') => {
        select.replaceChildren(new Option('Válassz…', ''));
        Object.entries(values).forEach(([key, label]) => select.add(new Option(label, key)));
        if (Object.hasOwn(values, preferred)) select.value = preferred;
        else if (Object.keys(values).length === 1) select.value = Object.keys(values)[0];
    };
    function rememberContext() {
        context.querySelectorAll('select').forEach(select => { contextMemory[select.name] = select.value; });
    }
    function refreshName() {
        const model = config.schema.models[field('device_model').value];
        const generated = model?.names[field('storage_capacity')?.value || ''] || model?.names[''];
        document.getElementById('akbo-intake-name').textContent = field('custom_name').checked ? (field('title').value || 'Add meg az egyedi nevet.') : (generated || 'A modell kiválasztása után automatikusan kitöltjük.');
    }
    function refreshColors() {
        const model = config.schema.models[field('device_model').value];
        if (!model || !field('case_material') || !field('color')) return;
        const allowed = model.colors_by_material[field('case_material').value];
        const colors = model.fields.color.options;
        options(field('color'), allowed ? Object.fromEntries(Object.entries(colors).filter(([key]) => allowed.includes(key))) : colors, field('color').value || contextMemory.color);
    }
    function refreshContext() {
        rememberContext();
        context.replaceChildren();
        const model = config.schema.models[field('device_model').value];
        Object.entries(model?.fields || {}).forEach(([key, definition]) => {
            const label = node('label', definition.label), select = node('select');
            select.name = key; select.required = definition.required;
            options(select, definition.options, contextMemory[key]);
            label.append(select); context.append(label);
        });
        refreshColors(); refreshName();
    }
    function refreshModels(preferred = '') {
        rememberContext();
        const models = Object.fromEntries(Object.entries(config.schema.models).filter(([, model]) => model.type === field('device_type').value).map(([key, model]) => [key, model.name]));
        options(field('device_model'), models, preferred);
        field('device_model').disabled = !field('device_type').value;
        refreshContext();
    }
    function customName() {
        const enabled = field('custom_name').checked;
        document.getElementById('akbo-intake-custom').hidden = !enabled;
        field('title').disabled = !enabled; field('title').required = enabled;
        refreshName();
    }
    field('device_type').addEventListener('change', () => refreshModels(field('device_model').value));
    field('device_model').addEventListener('change', refreshContext);
    field('custom_name').addEventListener('change', customName);
    field('title').addEventListener('input', refreshName);
    context.addEventListener('change', event => { if (event.target.name === 'case_material') refreshColors(); rememberContext(); refreshName(); });
    form.addEventListener('input', () => { dirty = true; });

    function validatePricing() {
        const regular = field('regular_price'), sale = field('sale_price');
        sale.setCustomValidity('');
        if (sale.validity.badInput || (sale.value !== '' && (!Number.isFinite(sale.valueAsNumber) || sale.valueAsNumber < 0))) {
            sale.setCustomValidity('Az akciós ár érvényes, nullánál nem kisebb összeg lehet.');
        } else if (sale.value !== '' && regular.value !== '' && sale.valueAsNumber >= regular.valueAsNumber) {
            sale.setCustomValidity('Az akciós árnak kisebbnek kell lennie a normál árnál.');
        }
    }
    ['regular_price','sale_price'].forEach(key => field(key).addEventListener('input', validatePricing));

    function renderPhotos() {
        photoList.replaceChildren();
        photos.forEach((photo, index) => {
            const item = node('li'); item.draggable = !busy; item.dataset.index = index;
            const preview = node('img'); preview.src = photo.url; preview.alt = `${index + 1}. termékfotó`;
            preview.addEventListener('error', () => { preview.replaceWith(node('div', 'HEIC / HEIF · előnézet mentés után', 'akbo-intake-photo-placeholder')); }, {once:true});
            item.append(preview, node('strong', index === 0 ? '1. Főfotó' : `${index + 1}. fotó`), node('span', photo.file.name));
            const controls = node('div');
            [['←', 'Előrébb', -1], ['→', 'Hátrébb', 1], ['×', 'Eltávolítás', 0]].forEach(([label, title, delta]) => {
                const button = node('button', label); button.type = 'button'; button.setAttribute('aria-label', `${title}: ${index + 1}. fotó`);
                button.disabled = busy || (delta === -1 && index === 0) || (delta === 1 && index === photos.length - 1);
                button.addEventListener('click', () => {
                    if (busy) return;
                    if (delta === 0) { URL.revokeObjectURL(photo.url); photos.splice(index, 1); }
                    else [photos[index], photos[index + delta]] = [photos[index + delta], photos[index]];
                    dirty = true; renderPhotos();
                });
                controls.append(button);
            });
            item.append(controls); photoList.append(item);
        });
    }
    function addFiles(files) {
        if (busy) return;
        if ([...photos.map(photo => photo.file), ...files].reduce((total, file) => total + file.size, 0) > 100 * 1024 * 1024) { message('A fotók összes mérete legfeljebb 100 MB lehet.', true); return; }
        if (photos.length + files.length > 12) { message('Legfeljebb 12 fotó választható.', true); return; }
        for (const file of files) {
            if (!file.size || file.size > 20 * 1024 * 1024) { message('Egy fotó legfeljebb 20 MB lehet.', true); return; }
            if (!/\.(jpe?g|png|webp|heic|heif)$/i.test(file.name)) { message('JPEG, PNG, WebP, HEIC vagy HEIF fotót válassz.', true); return; }
        }
        files.forEach(file => photos.push({file, url:URL.createObjectURL(file)}));
        dirty = true; renderPhotos();
    }
    picker.addEventListener('change', () => { addFiles(Array.from(picker.files)); picker.value = ''; });
    const drop = document.getElementById('akbo-intake-drop');
    drop.addEventListener('dragover', event => { event.preventDefault(); if (!busy) drop.classList.add('is-dragging'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-dragging'));
    drop.addEventListener('drop', event => { event.preventDefault(); drop.classList.remove('is-dragging'); addFiles(Array.from(event.dataTransfer.files)); });
    photoList.addEventListener('dragstart', event => { dragged = Number(event.target.closest('li')?.dataset.index); event.dataTransfer.setData('text/plain', String(dragged)); });
    photoList.addEventListener('dragover', event => event.preventDefault());
    photoList.addEventListener('drop', event => {
        event.preventDefault(); const target = event.target.closest('li');
        if (busy || !target || dragged === null || !photos[dragged]) return;
        photos.splice(Number(target.dataset.index), 0, photos.splice(dragged, 1)[0]); dragged = null; dirty = true; renderPhotos();
    });
    photoList.addEventListener('dragend', () => { dragged = null; });

    async function checkIdentity() {
        if (busy) return;
        const version = ++identityVersion, data = new FormData();
        data.set('action', 'appleklinika_intake_identity'); data.set('nonce', config.nonce);
        ['sku','imei','serial_number'].forEach(key => data.set(key, field(key).value));
        try {
            const response = await fetch(config.endpoint, {method:'POST', body:data, credentials:'same-origin'});
            const result = await response.json();
            if (version === identityVersion) identityError.textContent = result.success ? '' : result.data.message;
        } catch (_) { /* The authoritative check still runs on Save. */ }
    }
    ['sku','imei','serial_number'].forEach(key => field(key).addEventListener('blur', checkIdentity));

    function clearPhotos() { photos.forEach(photo => URL.revokeObjectURL(photo.url)); photos = []; dragged = null; picker.value = ''; renderPhotos(); }
    function fresh(similar = {}) {
        ++identityVersion; saved = null; form.reset(); context.replaceChildren(); contextMemory = {};
        validatePricing(); // Reset custom validity along with both device-specific prices.
        // Positive allowlist from Inventory. Never copy either price, condition, title, identifiers or photos.
        config.schema.reusable.forEach(key => { if (Object.hasOwn(similar, key)) contextMemory[key] = similar[key]; });
        field('device_type').value = contextMemory.device_type || '';
        refreshModels(contextMemory.device_model || '');
        customName(); clearPhotos(); identityError.textContent = ''; dirty = false;
        form.hidden = false; success.hidden = true;
        history.replaceState(null, '', config.url);
        (Object.keys(similar).length ? field('sku') : field('device_type')).focus();
    }
    function showSuccess(device, next = false) {
        saved = next ? null : device; success.replaceChildren(); success.hidden = false;
        success.append(node('h2', 'Készülék elmentve'), node('p', device.name));
        const facts = node('dl', undefined, 'akbo-intake-summary');
        const statuses = {draft:'Piszkozat',publish:'Közzétéve',pending:'Ellenőrzésre vár',private:'Privát'};
        const money = value => `${Number(value).toLocaleString('hu-HU')} ${device.currency}`;
        const pricing = {'Normál ár':money(device.regular_price)};
        if (device.sale_price !== '' && device.sale_price != null) pricing['Akciós ár'] = money(device.sale_price);
        pricing['Tényleges eladási ár'] = money(device.price);
        Object.entries({'Termékazonosító':`#${device.id}`, 'Cikkszám':device.sku, 'Modell':device.model, ...pricing, 'Fotók':`${device.image_count} db`, 'Készlet':`${device.stock} db`, 'Állapot':statuses[device.status] || device.status}).forEach(([key, value]) => facts.append(node('dt', key),node('dd', value)));
        success.append(facts);
        if (!next) {
            const details = node('details'), label = node('summary', 'Fotók és privát készülékazonosítók');
            details.append(label);
            if (device.imei) details.append(node('p', `IMEI: ${device.imei}`));
            if (device.serial_number) details.append(node('p', `Sorozatszám: ${device.serial_number}`));
            const images = node('div', undefined, 'akbo-intake-saved-photos');
            (device.images || []).forEach((url, index) => { const photo = node('img'); photo.src = url; photo.alt = index === 0 ? 'Mentett főfotó' : `Mentett ${index + 1}. fotó`; images.append(photo); });
            details.append(images); success.append(details);
        }

        const actions = node('div', undefined, 'akbo-intake-success-actions');
        const view = node('a','Készülék megtekintése','akbo-button akbo-button--secondary'); view.href = `${config.url}&created=${device.id}`; actions.append(view);
        [['Következő készülék',{}], ['Új hasonló készülék',device.similar]].forEach(([label, values]) => {
            const button = node('button',label,'akbo-button akbo-button--secondary'); button.type = 'button';
            button.addEventListener('click', () => { if (dirty && !window.confirm('Az elkezdett készülék adatai törlődnek. Folytatod?')) return; fresh(values); }); actions.append(button);
        });
        success.append(actions);
        if (device.warning) success.append(node('p',device.warning,'akbo-help'));
        form.hidden = !next;
        if (!next) success.focus();
    }
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        validatePricing(); if (!form.reportValidity()) return;
        if (!field('imei').value.trim() && !field('serial_number').value.trim()) { message('Adj meg IMEI-t vagy sorozatszámot.', true); field('imei').focus(); return; }
        if (!photos.length) { message('Válassz legalább egy termékfotót.', true); picker.focus(); return; }
        const mode = event.submitter?.value || 'draft';
        const input = Object.fromEntries(new FormData(form)); input.custom_name = field('custom_name').checked;
        const request = new FormData(); request.set('action','appleklinika_intake_device'); request.set('nonce',config.nonce); request.set('operation',operation); request.set('save_mode',mode); request.set('device',JSON.stringify(input));
        photos.forEach(photo => request.append('photos[]', photo.file));
        busy = true; ++identityVersion;
        form.querySelectorAll('fieldset,button[type=submit]').forEach(element => { element.disabled = true; });
        form.setAttribute('aria-busy','true'); renderPhotos(); message('Fotók feldolgozása és készülék mentése…');
        let focusNext = false;
        try {
            const response = await fetch(config.endpoint, {method:'POST', body:request, credentials:'same-origin'});
            const result = await response.json();
            if (!result.success) throw new Error(result.data?.message || 'A mentés sikertelen.');
            operation = result.data.next_operation; dirty = false; busy = false;
            clearPhotos(); feedback.replaceChildren();
            if (mode === 'next') { fresh(); showSuccess(result.data.device, true); focusNext = true; }
            else {
                // Clear physical identity before the completed page can enter browser back/forward cache.
                fresh(); showSuccess(result.data.device); history.replaceState(null,'',`${config.url}&created=${result.data.device.id}`);
            }
        } catch (error) {
            message(error instanceof SyntaxError || error instanceof TypeError ? 'Nem érkezett értelmezhető válasz. Próbáld újra ugyanazzal az űrlappal; korábbi siker esetén nem készül másolat.' : error.message, true);
        } finally {
            busy = false; form.querySelectorAll('fieldset,button[type=submit]').forEach(element => { element.disabled = false; });
            form.removeAttribute('aria-busy'); renderPhotos();
            if (focusNext) field('device_type').focus();
        }
    });
    window.addEventListener('beforeunload', event => { if (dirty || busy) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pageshow', event => { if (event.persisted && !dirty && saved) showSuccess(saved); });
    refreshModels(); customName();
    if (saved) showSuccess(saved);
})();
