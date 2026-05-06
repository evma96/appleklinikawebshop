(function () {
  var lastScrollY = window.scrollY || 0;
  var ticking = false;

  function formatPrice(value) {
    return String(Math.round(Number(value) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Ft';
  }

  function updatePriceFilter(filter) {
    var minInput = filter.querySelector('[data-price-min]');
    var maxInput = filter.querySelector('[data-price-max]');
    var minLabel = filter.querySelector('[data-price-min-label]');
    var maxLabel = filter.querySelector('[data-price-max-label]');

    if (!minInput || !maxInput || !minLabel || !maxLabel) {
      return;
    }

    var min = Number(minInput.value);
    var max = Number(maxInput.value);

    if (min > max) {
      if (document.activeElement === minInput) {
        maxInput.value = String(min);
        max = min;
      } else {
        minInput.value = String(max);
        min = max;
      }
    }

    minLabel.textContent = formatPrice(min);
    maxLabel.textContent = formatPrice(max);
  }

  document.querySelectorAll('[data-price-filter]').forEach(function (filter) {
    updatePriceFilter(filter);
    filter.addEventListener('input', function () {
      updatePriceFilter(filter);
    });
  });

  document.querySelectorAll('[data-cart-qty-control]').forEach(function (control) {
    var input = control.querySelector('input[type="number"]');

    if (!input) {
      return;
    }

    control.addEventListener('click', function (event) {
      var button = event.target.closest('button');

      if (!button) {
        return;
      }

      var step = Number(input.getAttribute('step')) || 1;
      var min = Number(input.getAttribute('min')) || 0;
      var current = Number(input.value) || 0;

      if (button.hasAttribute('data-cart-qty-decrease')) {
        input.value = String(Math.max(min, current - step));
      }

      if (button.hasAttribute('data-cart-qty-increase')) {
        input.value = String(current + step);
      }

      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  window.addEventListener('scroll', function () {
    if (ticking) {
      return;
    }

    window.requestAnimationFrame(function () {
      var currentScrollY = window.scrollY || 0;
      var shouldHide = currentScrollY > lastScrollY && currentScrollY > 140;

      document.body.classList.toggle('ak-header-hidden', shouldHide);
      lastScrollY = currentScrollY;
      ticking = false;
    });

    ticking = true;
  }, { passive: true });
}());
