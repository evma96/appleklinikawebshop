/* Native Settings API form controls; wp.media supplies the existing media library. */
(() => {
  'use strict';

  const form = document.querySelector('[data-home-editor]');
  if (!form) return;
  const status = form.querySelector('[data-home-status]');
  const announce = message => { status.textContent = message; };

  function reindex(list) {
    const rows = [...list.querySelector('[data-home-rows]').children];
    rows.forEach((row, index) => {
      row.querySelectorAll('[data-home-field]').forEach(field => {
        const key = field.dataset.homeField;
        const id = `ak-home-${list.dataset.homeList}-${index}-${key}`;
        const label = field.closest('.ak-home-editor__field').querySelector('label');
        field.name = `appleklinika_home_content[${list.dataset.homeList}][${index}][${key}]`;
        field.id = id;
        label.htmlFor = id;
      });
      const title = row.querySelector('[data-home-field="title"]').value.trim().replace(/\s+/g, ' ');
      const label = `${index + 1}. ${title || 'Új elem'}`;
      row.querySelector('[data-home-row-title]').textContent = label;
      ['up', 'down', 'remove'].forEach(action => {
        const button = row.querySelector(`[data-home-action="${action}"]`);
        button.disabled = action === 'up' ? index === 0 : action === 'down' ? index === rows.length - 1 : rows.length <= Number(list.dataset.min);
        button.setAttribute('aria-label', `${button.textContent}: ${label}`);
      });
    });
    list.querySelector('[data-home-action="add"]').disabled = rows.length >= Number(list.dataset.max);
  }

  form.querySelectorAll('[data-home-list]').forEach(reindex);
  form.addEventListener('submit', () => form.querySelectorAll('[data-home-list]').forEach(reindex));
  form.addEventListener('input', event => {
    if (event.target.matches('[data-home-field="title"]')) reindex(event.target.closest('[data-home-list]'));
  });

  function setImage(media, attachment) {
    const preview = media.querySelector('[data-home-preview]');
    const field = media.querySelector('input');
    const url = attachment ? (attachment.sizes?.thumbnail?.url || attachment.url) : '';
    field.value = attachment ? String(attachment.id) : '0';
    preview.hidden = !url;
    if (url) preview.src = url;
    else preview.removeAttribute('src');
    media.querySelector('[data-home-image-empty]').hidden = !!url;
    media.querySelector('[data-home-action="image-select"]').textContent = url ? 'Kép cseréje' : 'Kép választása';
    media.querySelector('[data-home-action="image-remove"]').disabled = !url;
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }

  form.addEventListener('click', event => {
    const button = event.target.closest('[data-home-action]');
    if (!button || button.disabled) return;
    const action = button.dataset.homeAction;

    if (action === 'image-select' || action === 'image-remove') {
      const media = button.closest('[data-home-media]');
      if (action === 'image-remove') {
        setImage(media, null);
        media.querySelector('[data-home-action="image-select"]').focus();
        announce('A kép eltávolítva. A változás a főoldal mentése után jelenik meg.');
        return;
      }
      if (!window.wp?.media) {
        announce('A médiatár nem érhető el. Töltsd újra az oldalt.');
        return;
      }
      const frame = window.wp.media({ title: 'Főoldali kép választása', button: { text: 'Kép használata' }, library: { type: 'image' }, multiple: false });
      frame.on('open', () => {
        const id = Number(media.querySelector('input').value);
        if (id > 0) frame.state().get('selection').add(window.wp.media.attachment(id));
      });
      frame.on('select', () => {
        const selected = frame.state().get('selection').first();
        if (!selected) return;
        setImage(media, selected.toJSON());
        announce('A kép kiválasztva. A változás a főoldal mentése után jelenik meg.');
      });
      frame.on('close', () => button.focus());
      frame.open();
      return;
    }

    const list = button.closest('[data-home-list]');
    const container = list.querySelector('[data-home-rows]');
    const row = button.closest('[data-home-row]');
    let focusTarget;
    if (action === 'add') {
      if (container.children.length >= Number(list.dataset.max)) return;
      const added = list.querySelector('[data-home-template]').content.firstElementChild.cloneNode(true);
      added.open = true;
      container.append(added);
      focusTarget = added.querySelector('[data-home-field="title"]');
      announce('Új elem hozzáadva. Add meg a címét és a tartalmát.');
    } else if (action === 'remove') {
      if (container.children.length <= Number(list.dataset.min)) return;
      focusTarget = (row.nextElementSibling || row.previousElementSibling)?.querySelector('summary') || list.querySelector('[data-home-action="add"]');
      row.remove();
      announce('Az elem eltávolítva. A változás a főoldal mentése után jelenik meg.');
    } else if (action === 'up' && row.previousElementSibling) {
      container.insertBefore(row, row.previousElementSibling);
      focusTarget = button;
      announce('Az elem egy hellyel feljebb került.');
    } else if (action === 'down' && row.nextElementSibling) {
      container.insertBefore(row.nextElementSibling, row);
      focusTarget = button;
      announce('Az elem egy hellyel lejjebb került.');
    }
    reindex(list);
    if (focusTarget?.disabled) focusTarget = row.querySelector('summary');
    focusTarget?.focus();
  });
})();
