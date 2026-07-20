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
});
