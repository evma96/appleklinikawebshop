/* Presentation-only carousel. No network, cookies, storage, autoplay or review rewriting. */
(() => {
    'use strict';
    document.querySelectorAll('.ak-reviews').forEach(section => {
        const track = section.querySelector('.ak-reviews__cards');
        const nav = section.querySelector('.ak-reviews__navigation');
        if (!track || !nav) return;
        const cards = Array.from(track.children);
        const previous = nav.querySelector('[data-review-prev]');
        const next = nav.querySelector('[data-review-next]');
        const range = nav.querySelector('.ak-reviews__range');
        const pageSize = () => Math.max(1, Math.round(track.clientWidth / (cards[0].offsetWidth + parseFloat(getComputedStyle(track).columnGap))));
        const update = () => {
            nav.hidden = track.scrollWidth <= track.clientWidth + 2;
            previous.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
            const start = Math.round(track.scrollLeft / (cards[0].offsetWidth + parseFloat(getComputedStyle(track).columnGap)));
            const end = Math.min(cards.length, start + pageSize());
            range.textContent = `${start + 1}–${end} / ${cards.length}`;
        };
        const move = direction => track.scrollBy({
            left: direction * pageSize() * (cards[0].offsetWidth + parseFloat(getComputedStyle(track).columnGap)),
            behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
        });
        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        track.addEventListener('scroll', update, {passive: true});
        new ResizeObserver(update).observe(track);
        update();
    });
})();
