document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ak-rule-form').forEach(function (form) {
    var kind = form.querySelector('[data-ak-rule-kind]');
    var adjustmentType = form.querySelector('[name="adjustment_type"]');
    if (!kind) return;
    var refresh = function () {
      form.querySelectorAll('.ak-rule-field[data-kinds]').forEach(function (field) {
        var matchesKind = field.dataset.kinds.split(' ').includes(kind.value);
        var matchesAdjustment = !field.dataset.adjustmentType ||
          kind.value !== 'mode_adjustment' ||
          field.dataset.adjustmentType === adjustmentType.value;
        var visible = matchesKind && matchesAdjustment;
        field.hidden = !visible;
        field.querySelectorAll('input, select, textarea').forEach(function (control) {
          control.disabled = !visible;
        });
      });
    };
    kind.addEventListener('change', refresh);
    if (adjustmentType) adjustmentType.addEventListener('change', refresh);
    refresh();
  });

  document.querySelectorAll('.ak-base-price-matrix').forEach(function (matrix) {
    var form = matrix.querySelector('[data-ak-base-price-form]');
    var search = matrix.querySelector('[data-ak-matrix-search]');
    var missingOnly = matrix.querySelector('[data-ak-missing-only]');
    var configuredCount = matrix.querySelector('[data-ak-configured-count]');
    var missingCount = matrix.querySelector('[data-ak-missing-count]');
    var emptyState = matrix.querySelector('[data-ak-matrix-empty]');
    var table = matrix.querySelector('[data-ak-matrix-table]');
    var inputs = form ? Array.prototype.slice.call(form.querySelectorAll('[data-ak-base-price]')) : [];
    var refreshCounts = function () {
      var configured = inputs.filter(function (input) { return input.value.trim() !== ''; }).length;
      if (configuredCount) configuredCount.textContent = configured;
      if (missingCount) missingCount.textContent = inputs.length - configured;
      matrix.querySelectorAll('[data-ak-matrix-row]').forEach(function (row) {
        var rowInputs = Array.prototype.slice.call(row.querySelectorAll('[data-ak-base-price]'));
        row.dataset.akRowMissing = rowInputs.some(function (input) { return input.value.trim() === ''; }) ? '1' : '0';
      });
      refreshVisibility();
    };
    var refreshVisibility = function () {
      var query = search ? search.value.trim().toLocaleLowerCase('hu') : '';
      var visibleRows = 0;
      matrix.querySelectorAll('[data-ak-matrix-row]').forEach(function (row) {
        var label = (row.dataset.akModelLabel || '').toLocaleLowerCase('hu');
        var matchesSearch = !query || label.includes(query);
        var matchesMissing = !missingOnly || !missingOnly.checked || row.dataset.akRowMissing === '1';
        var visible = matchesSearch && matchesMissing;
        row.hidden = !visible;
        if (visible) visibleRows += 1;
      });
      if (emptyState) emptyState.hidden = visibleRows !== 0;
      if (table) table.hidden = visibleRows === 0;
    };
    inputs.forEach(function (input) { input.addEventListener('input', refreshCounts); });
    if (search) search.addEventListener('input', refreshVisibility);
    if (missingOnly) missingOnly.addEventListener('change', refreshVisibility);
    if (form) refreshCounts();
    else refreshVisibility();
  });

  document.querySelectorAll('[data-ak-condition-form]').forEach(function (form) {
    var dirty = false;
    var editor = form.closest('.ak-conditions-editor') || form;
    var refresh = function () {
      var configured = 0;
      var manual = 0;
      var reject = 0;
      var changed = false;
      form.querySelectorAll('[data-ak-condition-row]').forEach(function (row) {
        var action = row.querySelector('[data-ak-condition-action]');
        var valueWrap = row.querySelector('[data-ak-condition-value]');
        var value = row.querySelector('[data-ak-condition-value-input]');
        var unit = row.querySelector('[data-ak-condition-unit]');
        if (!action || !valueWrap || !value) return;
        var requiresValue = action.value === 'fixed' || action.value === 'percentage';
        valueWrap.hidden = !requiresValue;
        value.disabled = !requiresValue;
        value.max = action.value === 'percentage' ? '100' : String(Number.MAX_SAFE_INTEGER);
        if (unit) unit.textContent = action.value === 'percentage' ? '%' : 'Ft';
        if (action.value !== 'none') configured += 1;
        if (action.value === 'manual_review') manual += 1;
        if (action.value === 'hard_reject') reject += 1;
        var currentValue = requiresValue ? value.value.trim() : '';
        if (action.value !== row.dataset.akConditionOriginalAction || currentValue !== row.dataset.akConditionOriginalValue) {
          changed = true;
          row.classList.add('is-changed');
        } else {
          row.classList.remove('is-changed');
        }
      });
      var total = editor.querySelector('[data-ak-condition-total]');
      var configuredTarget = editor.querySelector('[data-ak-condition-configured]');
      var unconfiguredTarget = editor.querySelector('[data-ak-condition-unconfigured]');
      var manualTarget = editor.querySelector('[data-ak-condition-manual]');
      var rejectTarget = editor.querySelector('[data-ak-condition-reject]');
      if (configuredTarget) configuredTarget.textContent = configured;
      if (unconfiguredTarget && total) unconfiguredTarget.textContent = Number(total.textContent) - configured;
      if (manualTarget) manualTarget.textContent = manual;
      if (rejectTarget) rejectTarget.textContent = reject;
      form.querySelectorAll('[data-ak-condition-changes]').forEach(function (target) {
        target.textContent = changed ? 'Mentetlen változások vannak.' : 'Nincs mentetlen változás.';
      });
      dirty = changed;
    };
    form.querySelectorAll('[data-ak-condition-action], [data-ak-condition-value-input]').forEach(function (control) {
      control.addEventListener('change', refresh);
      control.addEventListener('input', refresh);
    });
    form.addEventListener('submit', function () { dirty = false; });
    var modelSelect = editor.querySelector('[data-ak-condition-model-select]');
    if (modelSelect) {
      modelSelect.addEventListener('change', function (event) {
        if (dirty && !window.confirm('Mentetlen állapotlevonás-módosítások vannak. Biztosan másik modellt töltesz be?')) {
          event.preventDefault();
          modelSelect.value = modelSelect.dataset.akCurrentValue || '';
          return;
        }
        modelSelect.closest('form').submit();
      });
    }
    window.addEventListener('beforeunload', function (event) {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
    refresh();
  });

  document.querySelectorAll('[data-ak-battery-form]').forEach(function (form) {
    var dirty = false;
    var editor = form.closest('.ak-battery-editor') || form;
    var bands = form.querySelector('[data-ak-battery-bands]');
    var template = form.querySelector('[data-ak-battery-row-template]');
    var nextIndex = form.querySelectorAll('[data-ak-battery-row]').length;
    var rowValue = function (row) {
      var minimum = row.querySelector('[data-ak-battery-minimum]');
      var maximum = row.querySelector('[data-ak-battery-maximum]');
      var action = row.querySelector('[data-ak-battery-action]');
      var value = row.querySelector('[data-ak-battery-value-input]');
      return [minimum ? minimum.value.trim() : '', maximum ? maximum.value.trim() : '', action ? action.value : '', value && !value.disabled ? value.value.trim() : ''].join('|');
    };
    var needsValue = function (action) { return action === 'fixed' || action === 'percentage'; };
    var refreshRow = function (row) {
      var action = row.querySelector('[data-ak-battery-action]');
      var valueWrap = row.querySelector('[data-ak-battery-value]');
      var value = row.querySelector('[data-ak-battery-value-input]');
      var unit = row.querySelector('[data-ak-battery-unit]');
      if (!action || !valueWrap || !value) return;
      var required = needsValue(action.value);
      valueWrap.hidden = !required;
      value.disabled = !required;
      if (!required) value.value = '';
      value.max = action.value === 'percentage' ? '100' : String(Number.MAX_SAFE_INTEGER);
      if (unit) unit.textContent = action.value === 'percentage' ? '%' : 'Ft';
    };
    var refresh = function () {
      var configured = 0;
      var manual = 0;
      var reject = 0;
      var changes = 0;
      form.querySelectorAll('[data-ak-battery-row]').forEach(function (row) {
        if (row.dataset.akBatteryDeleted === '1') {
          changes += 1;
          return;
        }
        refreshRow(row);
        var action = row.querySelector('[data-ak-battery-action]');
        if (action && action.value !== 'none') configured += 1;
        if (action && action.value === 'manual_review') manual += 1;
        if (action && action.value === 'hard_reject') reject += 1;
        var changed = row.dataset.akBatteryOriginal !== rowValue(row);
        row.classList.toggle('is-changed', changed);
        if (changed) changes += 1;
      });
      [['[data-ak-battery-configured]', configured], ['[data-ak-battery-manual]', manual], ['[data-ak-battery-reject]', reject], ['[data-ak-battery-changes]', changes]].forEach(function (item) {
        editor.querySelectorAll(item[0]).forEach(function (target) { target.textContent = item[1]; });
      });
      form.querySelectorAll('[data-ak-battery-change-message]').forEach(function (target) {
        target.textContent = changes > 0 ? changes + ' mentetlen módosítás van.' : 'Nincs mentetlen változás.';
      });
      dirty = changes > 0;
    };
    var bindRow = function (row) {
      row.querySelectorAll('[data-ak-battery-minimum], [data-ak-battery-maximum], [data-ak-battery-action], [data-ak-battery-value-input]').forEach(function (control) {
        control.addEventListener('change', refresh);
        control.addEventListener('input', refresh);
      });
      var remove = row.querySelector('[data-ak-battery-remove]');
      if (remove) {
        remove.addEventListener('click', function () {
          if (!window.confirm('Biztosan törlöd ezt az akkumulátorsávot? A mentésig a törlés még nem végleges.')) return;
          var deleteInput = row.querySelector('[data-ak-battery-delete]');
          if (row.dataset.akBatteryExisting === '1' && deleteInput) {
            deleteInput.value = '1';
            row.dataset.akBatteryDeleted = '1';
            row.classList.add('is-deleting');
            row.hidden = true;
          } else {
            row.remove();
          }
          refresh();
        });
      }
      refreshRow(row);
    };
    form.querySelectorAll('[data-ak-battery-row]').forEach(bindRow);
    var add = form.querySelector('[data-ak-battery-add]');
    if (add && bands && template) {
      add.addEventListener('click', function () {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        var row = wrapper.firstElementChild;
        if (!row) return;
        bands.appendChild(row);
        bindRow(row);
        refresh();
      });
    }
    var modelSelect = editor.querySelector('[data-ak-battery-model-select]');
    if (modelSelect) {
      modelSelect.addEventListener('change', function (event) {
        if (dirty && !window.confirm('Mentetlen akkumulátorszabály-módosítások vannak. Biztosan másik modellt töltesz be?')) {
          event.preventDefault();
          modelSelect.value = modelSelect.dataset.akCurrentValue || '';
          return;
        }
        modelSelect.closest('form').submit();
      });
    }
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (event) {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
    refresh();
  });

  document.querySelectorAll('[data-ak-offer-mode-form]').forEach(function (form) {
    var dirty = false;
    var editor = form.closest('.ak-offer-modes-editor') || form;
    form.querySelectorAll('[data-ak-offer-mode-row]').forEach(function (row) {
      if (row.dataset.akOfferOriginal === 'missing') row.dataset.akOfferOriginal = 'missing|multiplier';
    });
    var refresh = function () {
      var configured = 0;
      var changes = 0;
      form.querySelectorAll('[data-ak-offer-mode-row]').forEach(function (row) {
        var type = row.querySelector('[data-ak-offer-type]');
        var value = row.querySelector('[data-ak-offer-value]');
        var unit = row.querySelector('[data-ak-offer-unit]');
        var help = row.querySelector('[data-ak-offer-help]');
        var remove = row.querySelector('[data-ak-offer-remove]');
        if (!type || !value || !remove) return;
        if (remove.checked) value.value = '';
        var isAmount = type.value === 'amount';
        unit.textContent = isAmount ? 'Ft' : '%';
        help.textContent = isAmount ? 'Előjeles egész Ft: mínusz csökkent, plusz növel.' : 'Előjeles százalék: -100%–+400%, legfeljebb két tizedessel.';
        value.step = isAmount ? '1' : '0.01';
        value.min = isAmount ? String(-Number.MAX_SAFE_INTEGER) : '-100';
        value.max = isAmount ? String(Number.MAX_SAFE_INTEGER) : '400';
        value.disabled = remove.checked;
        type.disabled = remove.checked;
        row.classList.toggle('is-disabled', remove.checked);
        if (!remove.checked && value.value.trim() !== '') configured += 1;
        var now = remove.checked || value.value.trim() === '' ? 'missing|' + type.value : 'configured|' + type.value + '|' + value.value.trim();
        var changed = now !== row.dataset.akOfferOriginal;
        row.classList.toggle('is-changed', changed);
        if (changed) changes += 1;
      });
      editor.querySelectorAll('[data-ak-offer-configured]').forEach(function (target) { target.textContent = configured; });
      editor.querySelectorAll('[data-ak-offer-missing]').forEach(function (target) { target.textContent = 4 - configured; });
      editor.querySelectorAll('[data-ak-offer-changes]').forEach(function (target) { target.textContent = changes; });
      form.querySelectorAll('[data-ak-offer-change-message]').forEach(function (target) {
        target.textContent = changes ? changes + ' mentetlen módosítás van.' : 'Nincs mentetlen változás.';
      });
      dirty = changes > 0;
    };
    form.querySelectorAll('[data-ak-offer-type]').forEach(function (type) {
      type.addEventListener('change', function () {
        var value = type.closest('[data-ak-offer-mode-row]').querySelector('[data-ak-offer-value]');
        if (value) value.value = '';
      });
    });
    form.querySelectorAll('[data-ak-offer-type], [data-ak-offer-value], [data-ak-offer-remove]').forEach(function (control) {
      control.addEventListener('change', refresh);
      control.addEventListener('input', refresh);
    });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (event) {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
    refresh();
  });

  document.querySelectorAll('[data-ak-preview-form]').forEach(function (form) {
    var payload;
    try { payload = JSON.parse(form.dataset.akPreviewCatalog || '{}'); } catch (error) { payload = {}; }
    var model = form.querySelector('[data-ak-preview-model]');
    var storage = form.querySelector('[data-ak-preview-storage]');
    var color = form.querySelector('[data-ak-preview-color]');
    var colorWrap = form.querySelector('[data-ak-preview-color-wrap]');
    var refreshConfiguration = function () {
      if (!model || !storage) return;
      var key = model.value;
      var storages = (payload.configurations || {})[key] || [];
      var colors = ((payload.catalog || {})[key] || {}).colors || {};
      storage.innerHTML = '<option value="">Válassz tárhelyet</option>';
      storages.forEach(function (value) {
        var option = document.createElement('option');
        option.value = String(value);
        option.textContent = value % 1024 === 0 ? String(value / 1024) + ' TB' : String(value) + ' GB';
        storage.appendChild(option);
      });
      if (color) {
        color.innerHTML = '<option value="">Nincs kiválasztva</option>';
        Object.keys(colors).forEach(function (key) {
          var option = document.createElement('option');
          option.value = key;
          option.textContent = colors[key];
          color.appendChild(option);
        });
      }
      if (colorWrap) colorWrap.hidden = Object.keys(colors).length === 0;
    };
    if (model) model.addEventListener('change', refreshConfiguration);
    form.querySelectorAll('[data-ak-preview-conditional]').forEach(function (field) {
      var source = form.querySelector('[data-ak-preview-question="' + field.dataset.akPreviewConditional + '"]');
      var refreshConditional = function () {
        var visible = !source || source.value !== field.dataset.akPreviewExcept;
        field.hidden = !visible;
        field.querySelectorAll('input, select').forEach(function (control) {
          control.disabled = !visible;
          if (!visible && control.type === 'checkbox') control.checked = false;
        });
      };
      if (source) source.addEventListener('change', refreshConditional);
      refreshConditional();
    });
    var reset = form.querySelector('[data-ak-preview-reset]');
    if (reset) reset.addEventListener('click', function () { window.setTimeout(refreshConfiguration, 0); });
  });
});
