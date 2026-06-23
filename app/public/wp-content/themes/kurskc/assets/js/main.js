document.addEventListener('DOMContentLoaded', function () {

	/* ── Mobile nav ─────────────────────────────────────────── */
	const navToggle = document.getElementById('nav-toggle');
	const siteNav   = document.getElementById('site-nav');

	if (navToggle && siteNav) {
		navToggle.addEventListener('click', function () {
			const isOpen = siteNav.classList.toggle('is-open');
			navToggle.setAttribute('aria-expanded', isOpen);
		});
		document.addEventListener('click', function (e) {
			if (!siteNav.contains(e.target) && !navToggle.contains(e.target)) {
				siteNav.classList.remove('is-open');
				navToggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	/* ── Tražilica je uvijek otvorena (inline form u headeru) — bez JS-a ── */

	/* ── Live ticker: pauza na dodir (hover je CSS) ──────────────── */
	const ticker = document.querySelector('.site-ticker');
	if (ticker && !ticker.classList.contains('is-static')) {
		ticker.addEventListener('touchstart', function () { ticker.classList.add('is-paused'); }, { passive: true });
		const resume = function () { ticker.classList.remove('is-paused'); };
		ticker.addEventListener('touchend', resume);
		ticker.addEventListener('touchcancel', resume);
	}

	/* ── Lightbox za galeriju objave ─────────────────────────────── */
	const galItems = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));
	if (galItems.length) {
		let lb, lbImg, lbCap, idx = 0;
		function buildLb() {
			lb = document.createElement('div');
			lb.className = 'kc-lightbox';
			lb.innerHTML = '<button class="kc-lightbox__close" aria-label="Zatvori">&times;</button>' +
				'<button class="kc-lightbox__nav kc-lightbox__nav--prev" aria-label="Prethodna">&lsaquo;</button>' +
				'<img alt=""><div class="kc-lightbox__cap"></div>' +
				'<button class="kc-lightbox__nav kc-lightbox__nav--next" aria-label="Sljedeća">&rsaquo;</button>';
			document.body.appendChild(lb);
			lbImg = lb.querySelector('img');
			lbCap = lb.querySelector('.kc-lightbox__cap');
			lb.querySelector('.kc-lightbox__close').addEventListener('click', closeLb);
			lb.querySelector('.kc-lightbox__nav--prev').addEventListener('click', function (e) { e.stopPropagation(); showLb(idx - 1); });
			lb.querySelector('.kc-lightbox__nav--next').addEventListener('click', function (e) { e.stopPropagation(); showLb(idx + 1); });
			lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
		}
		function showLb(i) {
			idx = (i + galItems.length) % galItems.length;
			var a = galItems[idx];
			lbImg.src = a.getAttribute('href');
			var cap = a.getAttribute('data-caption') || '';
			lbCap.textContent = cap;
			lbCap.style.display = cap ? 'block' : 'none';
		}
		function openLb(i) { if (!lb) buildLb(); showLb(i); lb.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
		function closeLb() { if (lb) lb.classList.remove('is-open'); document.body.style.overflow = ''; }
		galItems.forEach(function (a, i) { a.addEventListener('click', function (e) { e.preventDefault(); openLb(i); }); });
		document.addEventListener('keydown', function (e) {
			if (!lb || !lb.classList.contains('is-open')) return;
			if (e.key === 'Escape') closeLb();
			else if (e.key === 'ArrowLeft') showLb(idx - 1);
			else if (e.key === 'ArrowRight') showLb(idx + 1);
		});
	}

	/* ── Kopiraj poveznicu (dijeljenje) ──────────────────────────── */
	function kurskcFallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text; ta.setAttribute('readonly', '');
		ta.style.position = 'fixed'; ta.style.top = '-9999px';
		document.body.appendChild(ta); ta.select();
		try { document.execCommand('copy'); } catch (e) {}
		document.body.removeChild(ta);
	}
	document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var url = btn.getAttribute('data-copy-link');
			function showCopied() {
				btn.classList.add('is-copied');
				clearTimeout(btn._copiedT);
				btn._copiedT = setTimeout(function () { btn.classList.remove('is-copied'); }, 1800);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(showCopied).catch(function () { kurskcFallbackCopy(url); showCopied(); });
			} else {
				kurskcFallbackCopy(url); showCopied();
			}
		});
	});

	/* ── Custom dropdownovi (.hub-dd: profil ekipe, sezona lige…) ── */
	document.querySelectorAll('.hub-dd').forEach(function (dd) {
		var ddToggle = dd.querySelector('.hub-dd__toggle');
		if (!ddToggle) return;
		var ddClose = function () { dd.classList.remove('is-open'); ddToggle.setAttribute('aria-expanded', 'false'); };
		ddToggle.addEventListener('click', function (e) {
			e.stopPropagation();
			var open = dd.classList.toggle('is-open');
			ddToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		document.addEventListener('click', function (e) { if (!dd.contains(e.target)) ddClose(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') ddClose(); });
	});

	/* ── Traka sponzora: pauza na dodir (hover je CSS) ───────────── */
	var sponsors = document.querySelector('.sponsors');
	if (sponsors) {
		sponsors.addEventListener('touchstart', function () { sponsors.classList.add('is-paused'); }, { passive: true });
		var resumeSp = function () { sponsors.classList.remove('is-paused'); };
		sponsors.addEventListener('touchend', resumeSp);
		sponsors.addEventListener('touchcancel', resumeSp);
	}

	/* ── Mobilna tražilica: ikona otvara/zatvara search panel ────── */
	var searchToggle = document.getElementById('search-toggle');
	var siteHeader = document.querySelector('.site-header');
	if (searchToggle && siteHeader) {
		searchToggle.addEventListener('click', function (e) {
			e.stopPropagation();
			var open = siteHeader.classList.toggle('search-open');
			searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				var inp = siteHeader.querySelector('.header-search__input');
				if (inp) inp.focus();
			}
		});
		document.addEventListener('click', function (e) {
			if (!siteHeader.contains(e.target)) siteHeader.classList.remove('search-open');
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') siteHeader.classList.remove('search-open');
		});
	}

});
