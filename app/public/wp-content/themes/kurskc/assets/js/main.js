document.addEventListener('DOMContentLoaded', function () {
	const toggle = document.querySelector('.nav-toggle');
	const nav    = document.querySelector('.site-nav');

	if (!toggle || !nav) return;

	toggle.addEventListener('click', function () {
		const isOpen = nav.classList.toggle('is-open');
		toggle.setAttribute('aria-expanded', isOpen);
	});

	// Close nav when clicking outside
	document.addEventListener('click', function (e) {
		if (!nav.contains(e.target) && !toggle.contains(e.target)) {
			nav.classList.remove('is-open');
			toggle.setAttribute('aria-expanded', 'false');
		}
	});
});
