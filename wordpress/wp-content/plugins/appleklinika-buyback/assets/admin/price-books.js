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
});
