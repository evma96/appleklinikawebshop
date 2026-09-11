/* Homepage carousel: one timer, native links/controls, no cloned slides. */
(() => {
  'use strict';
  document.querySelectorAll('[data-home-hero]').forEach(hero => {
    const slides = Array.from(hero.querySelectorAll('[data-home-slide]'));
    const navigation = hero.querySelector('[data-home-navigation]');
    if (!navigation || slides.length < 2) return;
    const dots = Array.from(navigation.querySelectorAll('[data-home-dot]'));
    const toggle = navigation.querySelector('[data-home-toggle]');
    const status = navigation.querySelector('[data-home-status]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Number(hero.dataset.homeInterval) || 5500;
    let current = 0;
    let timer;
    let paused = motion.matches;
    let manuallyPaused = false;
    let focused = false;
    let pointer = null;
    let suppressClickUntil = 0;
    const schedule = () => {
      window.clearTimeout(timer);
      if (!paused && !focused && !pointer && !document.hidden) {
        timer = window.setTimeout(() => show(current + 1, false), interval);
      }
    };
    const updateToggle = () => {
      toggle.setAttribute('aria-label', paused ? 'Automatikus lapozás indítása' : 'Automatikus lapozás szüneteltetése');
      toggle.querySelector('span').textContent = paused ? '▶\uFE0E' : 'Ⅱ';
    };
    const show = (index, manual = true) => {
      slides[current].hidden = true;
      current = (index + slides.length) % slides.length;
      slides[current].hidden = false;
      if (!motion.matches && slides[current].animate) {
        slides[current].animate([{ opacity: 0.45 }, { opacity: 1 }], { duration: 300 });
      }
      dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === current)));
      status.setAttribute('aria-live', manual ? 'polite' : 'off');
      status.textContent = `${current + 1} / ${slides.length}`;
      schedule();
    };
    navigation.hidden = false;
    updateToggle();
    navigation.addEventListener('click', event => {
      const button = event.target.closest('button');
      if (!button) return;
      if (button.hasAttribute('data-home-dot')) show(Number(button.dataset.homeDot));
      else if (button === toggle) { paused = !paused; manuallyPaused = paused; updateToggle(); schedule(); }
    });
    hero.addEventListener('pointerleave', () => {
      if (pointer) { pointer = null; schedule(); }
    });
    // Never hide a link while someone is using it with a keyboard. Dot focus,
    // unlike slide-link focus, must not leave rotation paused after a click.
    hero.addEventListener('focusin', event => { focused = slides.some(slide => slide.contains(event.target)); schedule(); });
    hero.addEventListener('focusout', event => { focused = slides.some(slide => slide.contains(event.relatedTarget)); schedule(); });
    hero.addEventListener('keydown', event => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      const next = (current + (event.key === 'ArrowRight' ? 1 : -1) + slides.length) % slides.length;
      dots[next].focus();
      show(next);
    });
    hero.addEventListener('pointerdown', event => {
      if (!event.isPrimary || event.button !== 0 || event.target.closest('[data-home-navigation]')) return;
      pointer = { id: event.pointerId, x: event.clientX, y: event.clientY };
      suppressClickUntil = 0;
      window.clearTimeout(timer);
    });
    hero.addEventListener('pointerup', event => {
      if (!pointer || pointer.id !== event.pointerId) return;
      const dx = event.clientX - pointer.x;
      const dy = event.clientY - pointer.y;
      pointer = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.4) {
        suppressClickUntil = Date.now() + 700;
        show(current + (dx < 0 ? 1 : -1));
      } else schedule();
    });
    hero.addEventListener('pointercancel', () => { pointer = null; schedule(); });
    hero.addEventListener('click', event => {
      if (Date.now() < suppressClickUntil && event.target.closest('.ak-home-hero__artwork')) { event.preventDefault(); event.stopPropagation(); }
    }, true);
    document.addEventListener('visibilitychange', schedule);
    motion.addEventListener('change', () => {
      paused = manuallyPaused || motion.matches; updateToggle(); schedule();
    });
    schedule();
  });
})();
