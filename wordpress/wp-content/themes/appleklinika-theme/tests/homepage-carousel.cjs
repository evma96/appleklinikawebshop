// Deterministic, dependency-free regression of the real homepage carousel asset.
// No browser, WordPress bootstrap, network, media, or database access.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const asset = path.resolve(__dirname, '../assets/js/homepage.js');
const source = fs.readFileSync(asset, 'utf8');
let checks = 0;
const equal = (actual, expected, label) => { assert.deepEqual(actual, expected, label); checks++; };

class Element {
  constructor(tag, attributes = {}) {
    this.tag = tag;
    this.attributes = { ...attributes };
    this.dataset = {};
    for (const [name, value] of Object.entries(attributes)) {
      if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = String(value);
    }
    this.children = [];
    this.listeners = {};
    this.parent = null;
    this.hidden = false;
    this.textContent = '';
    this.animations = [];
  }
  append(child) { this.children.push(child); child.parent = this; return child; }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  getAttribute(name) { return this.attributes[name] ?? null; }
  hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
  matches(selector) {
    if (selector.startsWith('.')) return String(this.attributes.class || '').split(/\s+/).includes(selector.slice(1));
    if (!selector.startsWith('[')) return this.tag === selector;
    const match = selector.match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);
    assert.ok(match, `Supported fixture selector: ${selector}`);
    return this.hasAttribute(match[1]) && (match[2] === undefined || String(this.attributes[match[1]]) === match[2]);
  }
  querySelectorAll(selector) {
    return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]);
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  closest(selector) { return this.matches(selector) ? this : this.parent?.closest(selector) || null; }
  contains(child) { return !!child && (child === this || this.children.some(node => node.contains(child))); }
  addEventListener(type, handler, capture = false) {
    (this.listeners[type] ||= []).push({ handler, capture: capture === true || capture?.capture === true });
  }
  emit(type, properties = {}) {
    const event = {
      type, target: this, defaultPrevented: false, propagationStopped: false,
      preventDefault() { this.defaultPrevented = true; },
      stopPropagation() { this.propagationStopped = true; },
      ...properties,
    };
    const ancestors = [];
    for (let node = this; node; node = node.parent) ancestors.push(node);
    const invoke = (node, capture) => {
      for (const listener of node.listeners[type] || []) {
        if (listener.capture === capture) listener.handler(event);
      }
    };
    for (const node of [...ancestors].reverse()) {
      invoke(node, true);
      if (event.propagationStopped) return event;
    }
    for (const node of ancestors) {
      invoke(node, false);
      if (event.propagationStopped) break;
    }
    return event;
  }
  focus() {
    let document = this;
    while (document.parent) document = document.parent;
    const previous = document.activeElement;
    if (previous === this) return;
    document.activeElement = this;
    previous?.emit('focusout', { relatedTarget: this });
    this.emit('focusin', { relatedTarget: previous });
  }
  animate(frames, options) { this.animations.push({ frames, options }); }
}

function setup(count = 4, { reduced = false, navigation = true, interval } = {}) {
  const document = new Element('document');
  document.hidden = false;
  document.activeElement = null;
  const hero = document.append(new Element('section', { 'data-home-hero': '', ...(interval ? { 'data-home-interval': interval } : {}) }));
  const slides = Array.from({ length: count }, (_, index) => {
    const slide = hero.append(new Element('div', { 'data-home-slide': '' }));
    slide.hidden = index > 0;
    slide.append(new Element('a', { class: 'ak-home-hero__artwork', href: `/fixture-${index}` })).append(new Element('img'));
    return slide;
  });
  const nav = new Element('nav', { 'data-home-navigation': '' });
  nav.hidden = true;
  if (navigation) hero.append(nav);
  const dots = Array.from({ length: count }, (_, index) => nav.append(new Element('button', { 'data-home-dot': String(index), 'aria-current': String(index === 0) })));
  const toggle = nav.append(new Element('button', { 'data-home-toggle': '' }));
  toggle.append(new Element('span'));
  const status = nav.append(new Element('span', { 'data-home-status': '', 'aria-live': 'off' }));
  status.textContent = `1 / ${count}`;
  const outside = document.append(new Element('button'));
  const motionListeners = [];
  const motion = { matches: reduced, addEventListener: (type, handler) => { assert.equal(type, 'change'); motionListeners.push(handler); } };
  let now = 0;
  let sequence = 0;
  const timers = new Map();
  const window = {
    matchMedia: query => { assert.equal(query, '(prefers-reduced-motion: reduce)'); return motion; },
    clearTimeout: id => timers.delete(id),
    setTimeout: (callback, delay) => { const id = ++sequence; timers.set(id, { callback, at: now + delay }); return id; },
  };
  vm.runInNewContext(source, { document, window, Date: { now: () => now } }, { filename: asset });
  return {
    document, hero, slides, nav, dots, toggle, status, outside,
    pending: () => timers.size,
    current: () => slides.findIndex(slide => !slide.hidden),
    visible: () => slides.filter(slide => !slide.hidden).length,
    advance(ms) {
      const target = now + ms;
      let iterations = 0;
      while (timers.size) {
        const [id, timer] = [...timers.entries()].sort((a, b) => a[1].at - b[1].at)[0];
        if (timer.at > target) break;
        if (++iterations > 100) throw new Error('Unexpected timer loop');
        now = timer.at;
        timers.delete(id);
        timer.callback();
      }
      now = target;
    },
    reduce(value) { motion.matches = value; motionListeners.forEach(handler => handler({ matches: value })); },
    visibility(hidden) { document.hidden = hidden; document.emit('visibilitychange'); },
    down(x, y, extra = {}) { return slides[this.current()].children[0].emit('pointerdown', { pointerType: 'touch', pointerId: 7, isPrimary: true, button: 0, clientX: x, clientY: y, ...extra }); },
    up(x, y, extra = {}) { return hero.emit('pointerup', { pointerType: 'touch', pointerId: 7, isPrimary: true, button: 0, clientX: x, clientY: y, ...extra }); },
  };
}

for (const count of [0, 1]) {
  const state = setup(count);
  equal(state.pending(), 0, `${count} slides: no timer`);
  equal(state.nav.hidden, true, `${count} slides: no controls exposed`);
  state.advance(22000);
  equal(state.visible(), count, `${count} slides: static content unchanged`);
}
equal(setup(4, { navigation: false }).pending(), 0, 'Missing navigation: safe early exit');
equal(source.includes('data-home-direction'), false, 'Runtime no longer depends on removed previous/next buttons');

{
  const state = setup();
  equal(state.nav.hidden, false, 'Four slides expose controls');
  equal(state.nav.querySelectorAll('button').length, 5, 'Navigation contains four dots and the accessible pause control only');
  equal(state.pending(), 1, 'Exactly one initial timer');
  state.advance(5499);
  equal(state.current(), 0, 'Default interval does not advance before 5500ms');
  state.advance(1);
  equal(state.current(), 1, 'Default interval advances at 5500ms');
  equal(state.visible(), 1, 'Only one slide remains exposed');
  equal(state.status.getAttribute('aria-live'), 'off', 'Automatic changes do not announce');
  equal(state.dots.map(dot => dot.getAttribute('aria-current')), ['false', 'true', 'false', 'false'], 'Current dot follows automatic change');
  state.advance(16500);
  equal(state.current(), 0, 'Four automatic ticks wrap to first slide');
  equal(state.pending(), 1, 'Wrapping still owns exactly one timer');
}

{
  const state = setup();
  state.advance(2000);
  state.dots[1].focus();
  state.dots[1].emit('click');
  equal(state.current(), 1, 'Focused dot selects its exact slide');
  equal(state.pending(), 1, 'Focus on the clicked dot does not pause autoplay');
  equal(state.status.getAttribute('aria-live'), 'polite', 'Manual navigation announces status');
  equal(state.status.textContent, '2 / 4', 'Manual status gives current position');
  state.advance(5499);
  equal(state.current(), 1, 'Focused dot click resets the complete interval');
  state.advance(1);
  equal(state.current(), 2, 'Timer resumes from manual slide');
  state.dots[3].focus();
  state.dots[3].emit('click');
  equal(state.current(), 3, 'Last dot selects the fourth slide');
  state.advance(5500);
  equal(state.current(), 0, 'Focused last dot advances from slide four back to slide one');
  equal(state.document.activeElement === state.dots[3], true, 'Automatic wrapping keeps focus on the stable dot');
  state.dots[2].emit('click');
  equal(state.current(), 2, 'Dot selects its exact slide');
  state.advance(5500);
  equal(state.current(), 3, 'Dot resets the interval before autoplay');
  equal(state.pending(), 1, 'Rapid manual navigation never duplicates timers');
}

{
  const state = setup();
  state.advance(1000);
  state.hero.emit('pointerenter', { pointerType: 'touch' });
  equal(state.pending(), 1, 'Touch pointer entry does not create sticky hover pause');
  state.hero.emit('pointerenter', { pointerType: 'mouse' });
  equal(state.pending(), 1, 'Mouse hover does not pause autoplay');
  state.advance(1000);
  state.hero.emit('pointerleave');
  equal(state.pending(), 1, 'Pointer exit without an active gesture retains the timer');
  state.advance(3499);
  equal(state.current(), 0, 'Hover entry/exit do not advance the timer early');
  state.advance(1);
  equal(state.current(), 1, 'Hover entry/exit do not reset the original timer deadline');
  state.slides[1].children[0].focus();
  equal(state.pending(), 0, 'Focus inside a slide pauses to protect its image link');
  state.advance(12000);
  equal(state.current(), 1, 'The focused slide cannot be hidden by autoplay');
  state.dots[2].focus();
  equal(state.pending(), 1, 'Moving focus from the slide to a dot resumes autoplay');
  state.dots[2].emit('click');
  state.advance(5500);
  equal(state.current(), 3, 'Focused dot click restarts autoplay for a full interval');
  state.slides[3].children[0].focus();
  equal(state.pending(), 0, 'Returning focus to a slide pauses again');
  state.outside.focus();
  equal(state.pending(), 1, 'Focus leaving a slide for outside the hero resumes');
  state.advance(5500);
  equal(state.current(), 0, 'Slide focus exit schedules a full interval');
}

{
  const state = setup();
  state.slides[0].children[0].focus();
  const arrow = state.slides[0].children[0].emit('keydown', { key: 'ArrowRight' });
  equal(arrow.defaultPrevented, true, 'Arrow navigation prevents page scroll');
  equal(state.current(), 1, 'Right arrow advances');
  equal(state.document.activeElement === state.dots[1], true, 'Right arrow moves focus to the destination dot before hiding the slide');
  equal(state.pending(), 1, 'Stable dot focus permits autoplay');
  state.dots[1].emit('keydown', { key: 'ArrowLeft' });
  equal(state.current(), 0, 'Left arrow moves backwards');
  equal(state.document.activeElement === state.dots[0], true, 'Left arrow focuses its destination dot');
  state.dots[0].emit('keydown', { key: 'ArrowLeft' });
  equal(state.current(), 3, 'Left arrow wraps from first to last slide without arrow buttons');
  equal(state.document.activeElement === state.dots[3], true, 'Keyboard wrap focuses the last dot');
  const other = state.dots[3].emit('keydown', { key: 'Tab' });
  equal(other.defaultPrevented, false, 'Tab keeps native keyboard behavior');
}

{
  const state = setup();
  state.toggle.emit('click');
  equal(state.pending(), 0, 'Pause toggle clears timer');
  equal(state.toggle.getAttribute('aria-label'), 'Automatikus lapozás indítása', 'Pause toggle exposes the start action');
  equal(state.toggle.querySelector('span').textContent, '▶\uFE0E', 'Pause toggle shows a text-only play symbol');
  state.hero.emit('pointerenter', { pointerType: 'mouse' });
  state.hero.emit('pointerleave');
  state.outside.focus();
  equal(state.pending(), 0, 'Transient state changes do not clear manual pause');
  state.advance(20000);
  equal(state.current(), 0, 'Manual pause stays paused');
  state.toggle.emit('click');
  equal(state.toggle.getAttribute('aria-label'), 'Automatikus lapozás szüneteltetése', 'Resume toggle exposes the pause action');
  equal(state.pending(), 1, 'Explicit resume schedules one timer');
}

{
  const state = setup(4, { reduced: true });
  equal(state.pending(), 0, 'Reduced motion begins paused');
  state.dots[1].emit('click');
  equal(state.current(), 1, 'Reduced motion retains manual navigation');
  equal(state.slides[1].animations.length, 0, 'Reduced motion does not animate manual changes');
  state.reduce(false);
  equal(state.pending(), 1, 'Motion preference change resumes normal mode');
  state.advance(5500);
  equal(state.slides[2].animations.length, 1, 'Normal mode animates the incoming slide');
  state.reduce(true);
  equal(state.pending(), 0, 'Enabling reduced motion cancels autoplay');
  state.advance(10000);
  equal(state.current(), 2, 'Reduced motion keeps the current slide');
}

{
  const state = setup();
  state.toggle.emit('click');
  state.reduce(true);
  state.reduce(false);
  equal(state.pending(), 0, 'Explicit manual pause survives motion preference changes');
  equal(state.toggle.getAttribute('aria-label'), 'Automatikus lapozás indítása', 'Manual pause retains its accessible start action');
  state.advance(12000);
  equal(state.current(), 0, 'Motion preference changes cannot resume a manually paused carousel');
  state.toggle.emit('click');
  equal(state.pending(), 1, 'Explicit resume clears the retained manual pause');
  state.reduce(true);
  state.reduce(false);
  equal(state.pending(), 1, 'After explicit resume, motion preferences control autoplay again');
}

{
  const state = setup();
  state.advance(3000);
  state.visibility(true);
  equal(state.pending(), 0, 'Hidden page clears timer');
  state.advance(10000);
  equal(state.current(), 0, 'Hidden page does not advance');
  state.visibility(false);
  state.advance(5499);
  equal(state.current(), 0, 'Visible page receives a fresh full interval');
  state.advance(1);
  equal(state.current(), 1, 'Visible page resumes after full interval');
  equal(state.pending(), 1, 'Visibility changes retain single timer ownership');
}

{
  const state = setup();
  state.down(200, 100);
  equal(state.pending(), 0, 'Active touch pauses autoplay');
  state.up(70, 108);
  equal(state.current(), 1, 'Horizontal left swipe advances');
  equal(state.pending(), 1, 'Completed swipe resumes timer');
  const accidental = state.slides[1].children[0].emit('click');
  equal(accidental.defaultPrevented, true, 'Swipe suppresses accidental banner navigation');
  equal(accidental.propagationStopped, true, 'Swipe suppression also stops propagation');
  state.advance(700);
  equal(state.slides[1].children[0].emit('click').defaultPrevented, false, 'Ordinary banner navigation returns after suppression window');
  state.down(50, 100);
  state.up(150, 90);
  equal(state.current(), 0, 'Horizontal right swipe moves backwards');
  state.down(150, 100);
  state.up(158, 104);
  equal(state.current(), 0, 'Tap-sized gesture does not change slides');
  equal(state.slides[0].children[0].emit('click').defaultPrevented, false, 'A new intentional tap is not blocked by a prior swipe');
}

{
  const state = setup();
  state.down(200, 100);
  state.up(70, 100);
  const imageClick = state.slides[1].children[0].children[0].emit('click');
  equal(imageClick.defaultPrevented, true, 'A click on the artwork image is suppressed through its containing link');
  const dotClick = state.dots[2].emit('click');
  equal(dotClick.defaultPrevented, false, 'Post-swipe suppression does not block dot controls');
  equal(dotClick.propagationStopped, false, 'Post-swipe navigation clicks still bubble');
  equal(state.current(), 2, 'Dot control works immediately after a swipe');
  state.dots[0].emit('click');
  equal(state.current(), 0, 'Dot control works within the same suppression window');
  state.toggle.querySelector('span').emit('click');
  equal(state.pending(), 0, 'Pause control works immediately after a swipe, including its icon');
  equal(state.toggle.getAttribute('aria-label'), 'Automatikus lapozás indítása', 'Post-swipe pause updates its accessible action');
}

{
  const state = setup();
  state.down(100, 100);
  state.up(145, 240);
  equal(state.current(), 0, 'Vertical scrolling does not swipe the carousel');
  equal(state.slides[0].children[0].emit('click').defaultPrevented, false, 'Vertical motion does not suppress normal links');
  equal(state.pending(), 1, 'Vertical gesture resumes the timer');
  state.down(100, 100);
  state.up(50, 140);
  equal(state.current(), 0, 'Diagonal gesture must exceed horizontal dominance ratio');
  state.down(100, 100);
  state.up(60, 100);
  equal(state.current(), 0, 'Exactly 40px stays below the swipe threshold');
  state.down(100, 100);
  state.up(0, 100, { pointerId: 99 });
  equal(state.current(), 0, 'Unrelated pointer cannot finish another pointer gesture');
  equal(state.pending(), 0, 'Original pointer remains active');
  state.hero.emit('pointercancel');
  equal(state.pending(), 1, 'Cancelled pointer resumes timer without navigation');
  state.down(100, 100, { isPrimary: false });
  equal(state.pending(), 1, 'Secondary touch does not start a swipe');
  state.dots[1].emit('pointerdown', { pointerType: 'touch', isPrimary: true, button: 0, pointerId: 7, clientX: 100, clientY: 100 });
  state.up(0, 100);
  equal(state.current(), 0, 'Navigation controls are excluded from swipe start');
}

{
  const state = setup();
  state.down(200, 100);
  equal(state.pending(), 0, 'Gesture pauses timer before leaving the hero');
  state.hero.emit('pointerleave');
  equal(state.pending(), 1, 'Leaving the hero clears the active pointer and resumes scheduling');
  state.outside.emit('pointerup', { pointerType: 'touch', pointerId: 7, isPrimary: true, button: 0, clientX: 0, clientY: 100 });
  equal(state.current(), 0, 'Pointer released outside does not produce an unintended swipe');
  state.up(0, 100);
  equal(state.current(), 0, 'Stale pointerup after leaving is also ignored');
  state.advance(5500);
  equal(state.current(), 1, 'Outside release cannot leave autoplay permanently suspended');
  equal(state.pending(), 1, 'Outside release recovery still has one timer');
}

console.log(`Homepage carousel passed: ${checks} assertions; real asset, simulated DOM/timers only.`);
