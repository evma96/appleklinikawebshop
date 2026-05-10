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

  document.querySelectorAll('.woocommerce-ordering').forEach(function (form) {
    var select = form.querySelector('select.orderby');

    if (!select || form.querySelector('.ak-sort-dropdown')) {
      return;
    }

    var wrapper = document.createElement('div');
    var button = document.createElement('button');
    var list = document.createElement('div');
    var selectedOption = select.options[select.selectedIndex];

    wrapper.className = 'ak-sort-dropdown';
    button.className = 'ak-sort-dropdown__button';
    button.type = 'button';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    button.textContent = selectedOption ? selectedOption.textContent : '';

    list.className = 'ak-sort-dropdown__list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    Array.prototype.forEach.call(select.options, function (option) {
      var item = document.createElement('button');

      item.className = 'ak-sort-dropdown__option';
      item.type = 'button';
      item.setAttribute('role', 'option');
      item.setAttribute('data-value', option.value);
      item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
      item.textContent = option.textContent;

      item.addEventListener('click', function () {
        select.value = option.value;
        button.textContent = option.textContent;
        closeSortDropdown();
        select.dispatchEvent(new Event('change', { bubbles: true }));
        form.submit();
      });

      list.appendChild(item);
    });

    function closeSortDropdown() {
      wrapper.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
      list.hidden = true;
    }

    function openSortDropdown() {
      wrapper.classList.add('is-open');
      button.setAttribute('aria-expanded', 'true');
      list.hidden = false;
    }

    button.addEventListener('click', function () {
      if (wrapper.classList.contains('is-open')) {
        closeSortDropdown();
      } else {
        openSortDropdown();
      }
    });

    wrapper.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeSortDropdown();
        button.focus();
      }
    });

    document.addEventListener('click', function (event) {
      if (!wrapper.contains(event.target)) {
        closeSortDropdown();
      }
    });

    wrapper.appendChild(button);
    wrapper.appendChild(list);
    form.classList.add('ak-sort-enhanced');
    form.appendChild(wrapper);
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
