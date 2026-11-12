document.addEventListener('DOMContentLoaded', function () {

	/* ── Mobile nav ─────────────────────────────────────────── */
	const navToggle = document.getElementById('nav-toggle');
	const siteNav   = document.getElementById('site-nav');

	if (navToggle && siteNav) {
		const setNav = function (open) {
			siteNav.classList.toggle('is-open', open);
			navToggle.classList.toggle('is-open', open);
			document.body.classList.toggle('nav-locked', open);
			navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			navToggle.setAttribute('aria-label', open ? 'Zatvori izbornik' : 'Otvori izbornik');
		};
		navToggle.addEventListener('click', function () { setNav(!siteNav.classList.contains('is-open')); });
		document.addEventListener('click', function (e) {
			if (siteNav.classList.contains('is-open') && !siteNav.contains(e.target) && !navToggle.contains(e.target)) setNav(false);
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && siteNav.classList.contains('is-open')) setNav(false); });
	}

	/* ── Prijedlozi pretrage dok se tipka (REST kurskc/v1/search) ── */
	document.querySelectorAll('form[data-suggest]').forEach(function (form) {
		var input = form.querySelector('input[name="s"]');
		if (!input) return;
		var box = document.createElement('div');
		box.className = 'srch-suggest';
		box.hidden = true;
		form.appendChild(box);
		var timer = null, last = '', ctrl = null;
		function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
		function hide() { box.hidden = true; }
		function run() {
			var q = input.value.trim();
			if (q.length < 2) { hide(); last = ''; return; }
			if (q === last) return;
			last = q;
			if (ctrl && ctrl.abort) ctrl.abort();
			ctrl = window.AbortController ? new AbortController() : null;
			fetch(form.getAttribute('data-suggest') + (form.getAttribute('data-suggest').indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), ctrl ? { signal: ctrl.signal } : {})
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (!d || !d.groups) return;
					if (!d.groups.length) { box.innerHTML = '<div class="srch-suggest__none">Nema rezultata za „' + esc(q) + '”</div>'; box.hidden = false; return; }
					box.innerHTML = d.groups.map(function (g) {
						return '<div class="srch-suggest__grp">' + esc(g.label) + '</div>' + g.items.map(function (it) {
							return '<a class="srch-suggest__it" href="' + esc(it.url) + '">' + (it.img || '<span class="srch-suggest__dot"></span>') +
								'<span class="srch-suggest__t"><b>' + it.title + '</b>' + (it.sub ? '<small>' + it.sub + '</small>' : '') + '</span></a>';
						}).join('');
					}).join('') + '<a class="srch-suggest__all" href="' + esc(d.all) + '">Svi rezultati za „' + esc(q) + '” &rarr;</a>';
					box.hidden = false;
				}).catch(function () {});
		}
		input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 180); });
		input.addEventListener('focus', function () { if (box.innerHTML && input.value.trim().length >= 2) box.hidden = false; });
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { hide(); return; }
			if (e.key === 'ArrowDown' && !box.hidden) { var f = box.querySelector('a'); if (f) { e.preventDefault(); f.focus(); } }
		});
		box.addEventListener('keydown', function (e) {
			var links = [].slice.call(box.querySelectorAll('a')), i = links.indexOf(document.activeElement);
			if (e.key === 'ArrowDown' && i < links.length - 1) { e.preventDefault(); links[i + 1].focus(); }
			if (e.key === 'ArrowUp') { e.preventDefault(); (i > 0 ? links[i - 1] : input).focus(); }
			if (e.key === 'Escape') { hide(); input.focus(); }
		});
		document.addEventListener('click', function (e) { if (!form.contains(e.target)) hide(); });
	});

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
	/* ── Selecti koji odmah šalju formu (npr. Za medije: sezona/kolo) ── */
	document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
		sel.addEventListener('change', function () { if (sel.form) sel.form.submit(); });
	});

	/* ── Za medije: kartice, kopiranje HTML-a/teksta, PNG ─────────── */
	var mediaKit = document.querySelector('.media-kit');
	if (mediaKit) {
		var mediaFlash = function (btn, cls) {
			btn.classList.remove('is-busy');
			btn.classList.add(cls || 'is-done');
			clearTimeout(btn._mkT);
			btn._mkT = setTimeout(function () { btn.classList.remove('is-done', 'is-failed'); }, 2200);
		};
		mediaKit.querySelectorAll('[data-media-tab]').forEach(function (tab) {
			tab.addEventListener('click', function () {
				var key = tab.getAttribute('data-media-tab');
				mediaKit.querySelectorAll('[data-media-tab]').forEach(function (t) {
					var on = t === tab; t.classList.toggle('is-active', on); t.setAttribute('aria-selected', on ? 'true' : 'false');
				});
				mediaKit.querySelectorAll('[data-media-panel]').forEach(function (p) {
					var on = p.getAttribute('data-media-panel') === key; p.classList.toggle('is-active', on); p.hidden = !on;
				});
			});
		});
		var code = mediaKit.querySelector('.media-code');
		if (code) code.addEventListener('focus', function () { code.select(); });

		mediaKit.querySelectorAll('[data-media-copy]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var kind = btn.getAttribute('data-media-copy');
				var src  = document.getElementById('media-src-' + kind);
				var text = src ? src.value : '';
				var done = function () { mediaFlash(btn); };
				var fallback = function () { kurskcFallbackCopy(text); done(); };
				// HTML: text/plain = kod (za blok „Prilagođeni HTML"), text/html = oblikovani sadržaj (vizualni uređivač)
				if (kind === 'html' && window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
					navigator.clipboard.write([new ClipboardItem({
						'text/plain': new Blob([text], { type: 'text/plain' }),
						'text/html':  new Blob([text], { type: 'text/html' })
					})]).then(done, fallback);
				} else if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done, fallback);
				} else {
					fallback();
				}
			});
		});

		/* PNG: html-to-image (crta preglednik kroz SVG foreignObject — točno kao na ekranu;
		   html2canvas je Barlow tekst crtao pomaknut). Web fontovi s Google Fontsa se
		   moraju ugraditi kao data: URL, inače slika padne na Arial → dohvati jednom
		   samo latin + latin-ext varijante (hrvatski znakovi). */
		var mediaFontCSS = null;
		var getMediaFontCSS = function () {
			if (mediaFontCSS) return mediaFontCSS;
			var link = document.getElementById('kurskc-fonts-css');
			if (!link || !window.fetch) return Promise.resolve('');
			mediaFontCSS = fetch(link.href).then(function (r) { return r.text(); }).then(function (css) {
				var blocks = css.split(/(?=\/\*\s*[\w-]+\s*\*\/)/).filter(function (b) { return /\/\*\s*latin(-ext)?\s*\*\//.test(b); });
				return Promise.all(blocks.map(function (b) {
					var m = b.match(/url\((https:[^)]+)\)/);
					if (!m) return '';
					return fetch(m[1]).then(function (r) { return r.blob(); }).then(function (blob) {
						return new Promise(function (ok) {
							var fr = new FileReader();
							fr.onload = function () { ok(b.replace(m[1], fr.result)); };
							fr.onerror = function () { ok(''); };
							fr.readAsDataURL(blob);
						});
					});
				})).then(function (parts) { return parts.join('\n'); });
			}).catch(function () { return ''; });
			return mediaFontCSS;
		};
		mediaKit.querySelectorAll('[data-media-png]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var el = document.getElementById(btn.getAttribute('data-media-png'));
				if (!el || !window.htmlToImage) { mediaFlash(btn, 'is-failed'); return; }
				btn.classList.add('is-busy');
				var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
				Promise.all([getMediaFontCSS(), ready]).then(function (res) {
					return window.htmlToImage.toPng(el, { pixelRatio: 2, backgroundColor: '#ffffff', fontEmbedCSS: res[0] });
				}).then(function (url) {
					var a = document.createElement('a');
					a.download = (btn.getAttribute('data-media-name') || 'kurs-rezultati') + '.png';
					a.href = url;
					document.body.appendChild(a); a.click(); a.remove();
					mediaFlash(btn);
				}).catch(function () { mediaFlash(btn, 'is-failed'); });
			});
		});
	}

	/* Podijeli (Web Share API — mobitel nudi sve aplikacije) */
	if (navigator.share) {
		document.querySelectorAll('[data-share-native]').forEach(function (btn) {
			btn.hidden = false;
			btn.addEventListener('click', function () {
				navigator.share({ title: btn.getAttribute('data-title') || document.title, url: btn.getAttribute('data-url') || location.href }).catch(function () {});
			});
		});
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

	/* ── Vodoravno pomicanje (1.0.54): rubovi se zatamne gdje ima još sadržaja,
	   a iznad tablica piše „Povuci lijevo-desno" dok se prvi put ne pomakne ── */
	var xsTables = '.career-table-wrap, .pf-table-wrap, .kurs-bracket, .media-paper';
	var xsAll    = xsTables + ', .view-tabs, .round-nav__pills';
	document.querySelectorAll(xsAll).forEach(function (el) {
		var hint = null;
		function update() {
			var more = el.scrollWidth - el.clientWidth > 4;
			el.classList.toggle('xs-left', more && el.scrollLeft > 4);
			el.classList.toggle('xs-right', more && el.scrollLeft < el.scrollWidth - el.clientWidth - 4);
			if (more && !hint && el.matches(xsTables) && !el.dataset.xsSeen) {
				hint = document.createElement('div');
				hint.className = 'xs-hint';
				hint.innerHTML = '<span aria-hidden="true">&#8596;</span> Povuci lijevo-desno za cijelu tablicu';
				el.parentNode.insertBefore(hint, el);
			}
			if (hint) hint.hidden = !more;
		}
		el.addEventListener('scroll', function () {
			if (hint && el.scrollLeft > 10) { el.dataset.xsSeen = '1'; hint.remove(); hint = null; }
			update();
		}, { passive: true });
		window.addEventListener('resize', update);
		update();
	});

});
