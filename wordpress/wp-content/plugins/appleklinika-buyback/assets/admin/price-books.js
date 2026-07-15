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
});
