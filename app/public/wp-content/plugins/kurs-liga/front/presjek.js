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
