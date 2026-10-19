// Exercise navigation with scroll geometry, without a browser or third-party DOM library.
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function fixture({ rtl = false, reduced = false, length = 3 } = {}) {
	let position = 0, width = 320;
	const listeners = {}, buttons = {};
	const slides = Array.from({ length }, (_, i) => ({
		classList: { contains: name => name === 'wp-block-image' },
		get offsetLeft() { return i * width * (rtl ? -1 : 1); },
		getBoundingClientRect: () => ({ left: (rtl ? (length - 1 - i) : i) * width - position, width })
	}));
	const track = {
		children: slides, get clientWidth() { return width; },
		getBoundingClientRect: () => ({ left: rtl ? (length - 1) * width : 0 }),
		scrollTo({ left, behavior }) { position = left; track.behavior = behavior; listeners.scroll(); },
		addEventListener(name, fn) { listeners[name] = fn; }
	};
	for (const name of ['prev', 'next', 'count']) buttons[name] = { addEventListener(name, fn) { this[name] = fn; } };
	const controls = { hidden: true, querySelector: s => buttons[s.split('__')[1]] };
	const gallery = { querySelector: s => s === '.is-style-pct-swipe' ? track : controls };
	const resize = [];
	vm.runInNewContext(fs.readFileSync(require.resolve('../assets/photo-gallery.js'), 'utf8'), {
		document: { querySelectorAll: () => [gallery] },
		window: { matchMedia: () => ({ matches: reduced }), getComputedStyle: () => ({ direction: rtl ? 'rtl' : 'ltr' }), requestAnimationFrame: fn => fn(), ResizeObserver: function () {} },
		ResizeObserver: function (fn) { this.observe = () => resize.push(fn); }
	});
	const key = value => { let prevented = false; listeners.keydown({ target: track, key: value, preventDefault() { prevented = true; } }); return prevented; };
	return { track, controls, buttons, key, resize: () => { width = 740; resize.forEach(fn => fn()); } };
}
for (const rtl of [false, true]) {
	const f = fixture({ rtl });
	assert.equal(f.controls.hidden, false);
	assert.equal(f.buttons.count.textContent, '1 / 3');
	assert.equal(f.buttons.prev.disabled, true);
	f.buttons.next.click();
	assert.equal(f.buttons.count.textContent, '2 / 3');
	f.key('End');
	assert.equal(f.buttons.count.textContent, '3 / 3');
	assert.equal(f.buttons.next.disabled, true);
	f.key('Home');
	f.key(rtl ? 'ArrowLeft' : 'ArrowRight');
	assert.equal(f.buttons.count.textContent, '2 / 3');
	assert.equal(f.key('PageDown'), false);
}
const reduced = fixture({ reduced: true });
reduced.buttons.next.click();
assert.equal(reduced.track.behavior, 'instant');
assert.equal(fixture({ length: 1 }).controls.hidden, true);
console.log('Gallery arrows, keyboard, RTL, boundaries, reduced motion and single-image fallback passed.');
