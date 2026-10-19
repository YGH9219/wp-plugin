(function () {
	'use strict';
	document.querySelectorAll('.pct-gallery').forEach(function (gallery) {
		var track = gallery.querySelector('.is-style-pct-swipe');
		var slides = Array.from(track.children).filter(function (child) { return child.classList.contains('wp-block-image'); });
		if (slides.length < 2) return;
		var controls = gallery.querySelector('.pct-gallery__controls');
		var prev = controls.querySelector('.pct-gallery__prev');
		var next = controls.querySelector('.pct-gallery__next');
		var count = controls.querySelector('.pct-gallery__count');
		var index = 0;
		var pending = false;
		function update() {
			var center = track.getBoundingClientRect().left + track.clientWidth / 2;
			var closest = Infinity;
			slides.forEach(function (slide, i) {
				var rect = slide.getBoundingClientRect();
				var distance = Math.abs(rect.left + rect.width / 2 - center);
				if (distance < closest) { closest = distance; index = i; }
			});
			prev.disabled = index === 0;
			next.disabled = index === slides.length - 1;
			count.textContent = (index + 1) + ' / ' + slides.length;
			pending = false;
		}
		function move(to) {
			to = Math.max(0, Math.min(slides.length - 1, to));
			track.scrollTo({ left: slides[to].offsetLeft - slides[0].offsetLeft, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
		}
		prev.addEventListener('click', function () { move(index - 1); });
		next.addEventListener('click', function () { move(index + 1); });
		track.addEventListener('keydown', function (event) {
			if (event.target !== track || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
			event.preventDefault();
			var rtl = window.getComputedStyle(track).direction === 'rtl';
			move(event.key === 'Home' ? 0 : event.key === 'End' ? slides.length - 1 : index + ((event.key === 'ArrowRight') !== rtl ? 1 : -1));
		});
		track.addEventListener('scroll', function () { if (!pending) { pending = true; window.requestAnimationFrame(update); } }, { passive: true });
		if (window.ResizeObserver) new ResizeObserver(update).observe(track);
		else window.addEventListener('resize', update);
		controls.hidden = false;
		update();
	});
}());
