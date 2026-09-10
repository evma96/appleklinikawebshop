(() => {
  'use strict';
  document.querySelectorAll('[data-home-hero]').forEach(hero => {
    const slides = Array.from(hero.querySelectorAll('[data-home-slide]'));
    const navigation = hero.querySelector('[data-home-navigation]');
    if (!navigation || slides.length < 2) return;
    let current = 0;
    navigation.hidden = false;
    navigation.addEventListener('click', event => {
      const button = event.target.closest('[data-home-direction]');
      if (!button) return;
      slides[current].hidden = true;
      current = (current + Number(button.dataset.homeDirection) + slides.length) % slides.length;
      slides[current].hidden = false;
      navigation.querySelector('[data-home-status]').textContent = `${current + 1} / ${slides.length}`;
    });
  });
})();
