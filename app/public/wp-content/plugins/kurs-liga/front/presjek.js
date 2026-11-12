/* KURS — /presjek/: odgovori na pitanja i napomene po ekipi (Kurs_Presjek::ajax_answer). */
(function () {
	'use strict';
	var KP = window.KP || {};
	var answers = KP.answers || {};
	var who = document.getElementById('kp-who');

	function store(get, val) {
		try {
			if (get) return window.localStorage.getItem('kp-who') || '';
			window.localStorage.setItem('kp-who', val);
		} catch (e) { return ''; }
	}
	if (who) {
		who.value = store(true);
		who.addEventListener('change', function () { store(false, who.value.trim()); });
	}

	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function renderHistory(box, list) {
		var ol = box.querySelector('.kp-history');
		if (!ol) return;
		ol.innerHTML = (list || []).slice(0, 5).map(function (e, i) {
			return '<li class="' + (i ? 'is-old' : 'is-last') + '">' +
				(e.v ? '<b>' + esc(e.v) + '</b>' : '') +
				(e.t ? '<span class="kp-history__t">' + esc(e.t).replace(/\n/g, '<br>') + '</span>' : '') +
				'<small>' + (e.who ? esc(e.who) + ' · ' : '') + esc(e.at) + '</small></li>';
		}).join('');
		var isQ = box.classList.contains('kp-q');
		if (isQ) {
			var has = list && list.length;
			box.classList.toggle('is-answered', !!has);
			var st = box.querySelector('.kp-q__state');
			if (st) st.textContent = has ? 'Odgovoreno' : 'Čeka odgovor';
			if (has) {
				box.querySelectorAll('.kp-opt').forEach(function (b) {
					var on = b.getAttribute('data-v') === list[0].v;
					b.classList.toggle('is-on', on);
					b.setAttribute('aria-checked', on ? 'true' : 'false');
				});
			}
			var link = document.querySelectorAll('[data-qlink="' + box.getAttribute('data-q') + '"]');
			link.forEach(function (a) { a.classList.toggle('is-answered', !!has); });
		}
	}

	function updateDone() {
		var n = (KP.qids || []).filter(function (id) { return answers[id] && answers[id].length; }).length;
		var el = document.getElementById('kp-done');
		if (el) el.textContent = n;
	}

	var boxes = document.querySelectorAll('.kp-q, .kp-note');
	boxes.forEach(function (box) {
		var id = box.getAttribute('data-q');
		renderHistory(box, answers[id]);

		box.querySelectorAll('.kp-opt').forEach(function (b) {
			b.addEventListener('click', function () {
				box.querySelectorAll('.kp-opt').forEach(function (o) {
					o.classList.toggle('is-on', o === b);
					o.setAttribute('aria-checked', o === b ? 'true' : 'false');
				});
				box.querySelector('.kp-status').textContent = '';
			});
		});

		var btn = box.querySelector('.kp-save');
		btn.addEventListener('click', function () {
			var sel = box.querySelector('.kp-opt.is-on');
			var txt = box.querySelector('.kp-text');
			var status = box.querySelector('.kp-status');
			var v = sel ? sel.getAttribute('data-v') : '';
			var t = txt ? txt.value.trim() : '';
			var last = (answers[id] || [])[0];
			if (!v && !t) { status.className = 'kp-status is-err'; status.textContent = box.querySelector('.kp-opt') ? 'Odaberite odgovor.' : 'Upišite tekst.'; return; }
			if (last && last.v === v && !t) { status.className = 'kp-status is-ok'; status.textContent = 'Već spremljeno ✓'; return; }

			var body = new URLSearchParams();
			body.append('action', 'kurs_presjek_odgovor');
			body.append('k', KP.k);
			body.append('q', id);
			body.append('v', v);
			body.append('t', t);
			body.append('who', who ? who.value.trim() : '');
			var hp = document.getElementById('kp-web');
			body.append('web', hp ? hp.value : '');

			btn.disabled = true;
			status.className = 'kp-status';
			status.textContent = 'Šaljem…';
			fetch(KP.ajax, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) throw new Error((res && res.data && res.data.msg) || 'Greška pri spremanju.');
					answers[id] = res.data.entries;
					renderHistory(box, answers[id]);
					updateDone();
					if (txt) txt.value = '';
					status.className = 'kp-status is-ok';
					status.textContent = 'Spremljeno ✓ Hvala!';
				})
				.catch(function (e) {
					status.className = 'kp-status is-err';
					status.textContent = e.message || 'Greška — pokušajte ponovno.';
				})
				.then(function () { btn.disabled = false; });
		});
	});
	updateDone();

	// ── Sponzori ekipa (1.29.0) ──────────────────────────────────────────
	var spons = KP.spons || {};
	function renderSpons(box, list) {
		var ul = box.querySelector('.kp-spons__list');
		var team = box.getAttribute('data-team');
		ul.innerHTML = (list || []).map(function (s) {
			return '<li class="kp-sp kp-sp--' + esc(s.tier) + '">' +
				(s.logo ? '<img class="kp-sp__logo" src="' + esc(s.logo) + '" alt="">' : '<span class="kp-sp__logo kp-sp__logo--none">bez loga</span>') +
				'<span class="kp-sp__body"><b>' + esc(s.name) + '</b><span class="kp-sp__tier">' + esc(s.tierL) + ' sponzor' + (s.compL ? ' · ' + esc(s.compL) : '') + '</span>' +
				(s.link ? '<a href="' + esc(s.link) + '" target="_blank" rel="noopener nofollow">' + esc(s.link.replace(/^https?:\/\/(www\.)?/, '')) + '</a>' : '<span class="kp-sp__nolink">bez linka</span>') +
				'</span><button type="button" class="kp-sp__x" data-i="' + s.i + '" title="Ukloni" aria-label="Ukloni ' + esc(s.name) + '">&times;</button></li>';
		}).join('');
		var n = (list || []).length;
		var badge = box.querySelector('.kp-spons__badge');
		if (badge) badge.textContent = n ? n + ' poslano' : 'još nije poslano';
		box.classList.toggle('is-done', n > 0);
		var link = document.querySelector('[data-spons-link="' + team + '"]');
		if (link) { link.classList.toggle('is-done', n > 0); link.querySelector('span').textContent = n || '—'; }
		var cnt = document.getElementById('kp-spons-n');
		if (cnt && team === '0') cnt.textContent = n ? n + ' poslano' : '';
	}
	function postSpons(fd, box, status, done) {
		fd.append('action', 'kurs_presjek_sponzor');
		fd.append('k', KP.k);
		fd.append('team', box.getAttribute('data-team'));
		var hp = document.getElementById('kp-web');
		fd.append('web', hp ? hp.value : '');
		fetch(KP.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) throw new Error((res && res.data && res.data.msg) || 'Greška pri spremanju.');
				spons[box.getAttribute('data-team')] = res.data.list;
				renderSpons(box, res.data.list);
				done(null);
			})
			.catch(function (e) { done(e); });
	}
	document.querySelectorAll('.kp-spons').forEach(function (box) {
		renderSpons(box, spons[box.getAttribute('data-team')]);
		var form = box.querySelector('.kp-spons__form');
		var status = form.querySelector('.kp-status');
		var btn = form.querySelector('button[type=submit]');
		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var fd = new FormData(form);
			if (!String(fd.get('name') || '').trim()) { status.className = 'kp-status is-err'; status.textContent = 'Upišite naziv sponzora.'; return; }
			if (!fd.get('tier')) { status.className = 'kp-status is-err'; status.textContent = 'Odaberite razinu: glavni, srednji ili mali.'; return; }
			if (box.getAttribute('data-team') === '0' && !fd.get('comp')) { status.className = 'kp-status is-err'; status.textContent = 'Odaberite natjecanje.'; return; }
			var file = form.querySelector('input[type=file]').files[0];
			if (file && file.size > 5 * 1024 * 1024) { status.className = 'kp-status is-err'; status.textContent = 'Logo je veći od 5 MB.'; return; }
			fd.append('who', who ? who.value.trim() : '');
			btn.disabled = true; status.className = 'kp-status'; status.textContent = file ? 'Šaljem logo…' : 'Šaljem…';
			postSpons(fd, box, status, function (err) {
				btn.disabled = false;
				if (err) { status.className = 'kp-status is-err'; status.textContent = err.message; return; }
				var keep = form.querySelector('input[name=comp]:checked');
				form.reset();
				if (keep) keep.checked = true; // natjecanje ostaje za idućeg sponzora
				status.className = 'kp-status is-ok'; status.textContent = 'Dodano ✓ Možete dodati sljedećeg.';
			});
		});
		box.querySelector('.kp-spons__list').addEventListener('click', function (ev) {
			var x = ev.target.closest('.kp-sp__x');
			if (!x || !window.confirm('Ukloniti ovog sponzora?')) return;
			var fd = new FormData();
			fd.append('remove', x.getAttribute('data-i'));
			postSpons(fd, box, status, function (err) {
				if (err) { status.className = 'kp-status is-err'; status.textContent = err.message; }
			});
		});
	});
	function openFromHash() {
		var m = /^#spons-(\d+)$/.exec(location.hash);
		if (!m) return;
		var box = document.getElementById('spons-' + m[1]);
		if (!box) return;
		var card = box.closest('.kp-team');
		if (card && card.hidden) { var all = document.querySelector('.kp-filter__btn[data-f="all"]'); if (all) all.click(); }
		box.open = true;
		setTimeout(function () { box.scrollIntoView({ behavior: 'smooth', block: 'start' }); var i = box.querySelector('input[name=name]'); if (i) i.focus({ preventScroll: true }); }, 30);
	}
	window.addEventListener('hashchange', openFromHash);
	openFromHash();

	// filter ekipa
	var fbtns = document.querySelectorAll('.kp-filter__btn');
	fbtns.forEach(function (b) {
		b.addEventListener('click', function () {
			var f = b.getAttribute('data-f');
			fbtns.forEach(function (o) { o.classList.toggle('is-on', o === b); });
			document.querySelectorAll('.kp-league').forEach(function (lg) {
				var any = false;
				lg.querySelectorAll('.kp-team').forEach(function (t) {
					var show = f === 'all' || t.getAttribute('data-lg') === f || (f === 'carried' && t.getAttribute('data-status') === 'carried');
					t.hidden = !show;
					if (show) any = true;
				});
				lg.hidden = !any;
			});
		});
	});
})();
