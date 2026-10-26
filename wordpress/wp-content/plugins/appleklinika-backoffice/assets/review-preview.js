/* Only reveals server-calculated consequences; no order changes or provider calls. */
document.querySelectorAll('.akbo-review-form').forEach(function (form) {
    var select = form.querySelector('[name="shipping_choice"]');
    if (!select) return;
    var panels = form.querySelectorAll('[data-review-choice]');
    var confirmation = form.querySelector('[name="confirm_review"]');
    var hint = form.querySelector('[data-review-hint]');
    function show() {
        panels.forEach(function (panel) { panel.hidden = panel.dataset.reviewChoice !== select.value; });
        if (hint) hint.hidden = select.value !== '';
    }
    select.addEventListener('change', function () {
        if (confirmation) confirmation.checked = false;
        show();
    });
    show();
});
