/* Contact-page mobile directions chooser. */
(() => {
  'use strict';
  // Native details/links work without JavaScript; add familiar dismiss behaviour only.
  document.querySelectorAll('[data-directions]').forEach(chooser => {
    const summary = chooser.querySelector('summary');
    document.addEventListener('click', event => {
      if (!chooser.contains(event.target)) chooser.open = false;
    });
    chooser.addEventListener('keydown', event => {
      if (event.key === 'Escape' && chooser.open) {
        chooser.open = false;
        summary.focus();
      }
    });
    chooser.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { chooser.open = false; }));
    window.matchMedia('(max-width: 800px)').addEventListener('change', event => {
      if (!event.matches) chooser.open = false;
    });
  });
})();
