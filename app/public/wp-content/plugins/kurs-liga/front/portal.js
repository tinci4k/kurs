/* KURS — /portal-ekipe/: roster, sponzori i zahtjevi ekipe (Kurs_Portal::ajax). */
(function () {
	'use strict';
	var K = window.KPT || {};
	var S = K.state || {};
	var who = document.getElementById('kp-who');
	var PEND = { player_edit: 'ispravak čeka odobrenje', player_remove: 'uklanjanje čeka odobrenje' };

	try { if (who) { who.value = localStorage.getItem('kp-who') || ''; who.addEventListener('change', function () { localStorage.setItem('kp-who', who.value.trim()); }); } } catch (e) {}

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function send(fd, done) {
		fd.append('action', 'kurs_portal');
		fd.append('e', K.e);
		fd.append('k', K.k);
		fd.append('who', who ? who.value.trim() : '');
		var hp = document.getElementById('kp-web');
		fd.append('web', hp ? hp.value : '');
		fetch(K.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) throw new Error((res && res.data && res.data.msg) || 'Greška pri slanju.');
				S = res.data; render(); done(null);
			})
			.catch(function (e) { done(e); });
	}

	/* ── Roster ─────────────────────────────────────────────────────────── */
	function renderRoster() {
		var ul = document.getElementById('kpt-roster');
		var addReq = (S.requests || []).filter(function (r) { return r.status === 'pending' && r.type === 'player_add'; });
		document.getElementById('kpt-n').textContent = (S.roster || []).length + ' na popisu';
		ul.innerHTML = (S.roster || []).map(function (p) {
			var pend = (p.pending || []).filter(function (t, i, a) { return a.indexOf(t) === i; }).map(function (t) { return '<span class="kpt-pend">' + esc(PEND[t] || 'čeka odobrenje') + '</span>'; }).join('');
			return '<li class="kpt-pl" data-id="' + p.id + '">' +
				'<span class="kpt-pl__no' + (p.number ? '' : ' is-empty') + '" title="Broj dresa">' + (p.number ? esc(p.number) : 'bez br.') + '</span>' +
				'<span class="kpt-pl__name">' + esc(p.name) + (p.gk ? ' <em class="kpt-gk">GK</em>' : '') + pend + '</span>' +
				(K.locked ? '' : '<span class="kpt-pl__act"><button type="button" class="kpt-link" data-act="edit">Ispravi</button><button type="button" class="kpt-link kpt-link--red" data-act="remove">Nije više u ekipi</button></span>') +
				'</li>';
		}).join('') + addReq.map(function (r) {
			return '<li class="kpt-pl kpt-pl--new"><span class="kpt-pl__no">+</span><span class="kpt-pl__name">' + esc(r.desc) + '<span class="kpt-pend">novi igrač čeka odobrenje</span></span></li>';
		}).join('') || '<li class="kpt-empty">Na popisu još nema igrača.</li>';
	}

	document.getElementById('kpt-roster').addEventListener('click', function (ev) {
		var b = ev.target.closest('button[data-act]');
		if (!b) return;
		var li = b.closest('.kpt-pl');
		var id = +li.getAttribute('data-id');
		var p = (S.roster || []).filter(function (x) { return x.id === id; })[0];
		if (!p) return;
		if (b.getAttribute('data-act') === 'remove') {
			if (!window.confirm(p.name + ' više nije u ekipi? KURS će ga ukloniti s popisa nakon odobrenja (odigrane utakmice ostaju).')) return;
			var fd = new FormData(); fd.append('do', 'player_remove'); fd.append('player_id', id);
			send(fd, function (e) { if (e) window.alert(e.message); });
			return;
		}
		if (li.querySelector('.kpt-edit')) return;
		var f = document.createElement('form');
		f.className = 'kpt-edit';
		f.innerHTML = '<input type="text" name="name" maxlength="80" value="' + esc(p.name) + '" aria-label="Ime i prezime">' +
			'<input type="number" name="number" min="0" max="99" value="' + (p.number || '') + '" placeholder="br." aria-label="Broj">' +
			'<button type="submit" class="kp-btn">Pošalji</button><button type="button" class="kpt-link" data-x>Odustani</button><span class="kp-status"></span>';
		li.appendChild(f);
		f.querySelector('input').focus();
		f.querySelector('[data-x]').addEventListener('click', function () { f.remove(); });
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(f); fd.append('do', 'player_edit'); fd.append('player_id', id);
			var st = f.querySelector('.kp-status'); st.className = 'kp-status'; st.textContent = 'Šaljem…';
			send(fd, function (err) { if (err) { st.className = 'kp-status is-err'; st.textContent = err.message; } });
		});
	});

	/* ── Sponzori ───────────────────────────────────────────────────────── */
	function renderSpons() {
		var ul = document.getElementById('kpt-spons');
		var add = (S.requests || []).filter(function (r) { return r.status === 'pending' && r.type === 'sponsor_add'; });
		ul.innerHTML = (S.sponsors || []).map(function (s) {
			return '<li class="kpt-sp">' + (s.img ? '<img src="' + esc(s.img) + '" alt="">' : '') +
				'<span class="kpt-sp__txt"><b>' + esc(s.name) + '</b><small>' + (s.link ? esc(s.link.replace(/^https?:\/\/(www\.)?/, '')) : 'na webu, bez linka') + '</small>' +
				(s.pending ? '<span class="kpt-pend">uklanjanje čeka odobrenje</span>' : '') + '</span>' +
				(s.pending ? '' : '<button type="button" class="kpt-link kpt-link--red" data-att="' + s.id + '">Ukloni</button>') + '</li>';
		}).join('') + add.map(function (r) {
			return '<li class="kpt-sp kpt-sp--new">' + (r.img ? '<img src="' + esc(r.img) + '" alt="">' : '') + '<span class="kpt-sp__txt"><b>' + esc(r.desc) + '</b><span class="kpt-pend">čeka odobrenje</span></span></li>';
		}).join('') || '<li class="kpt-empty">Još nema sponzora na webu.</li>';
	}
	document.getElementById('kpt-spons').addEventListener('click', function (ev) {
		var b = ev.target.closest('button[data-att]');
		if (!b || !window.confirm('Ukloniti ovog sponzora s profila ekipe?')) return;
		var fd = new FormData(); fd.append('do', 'sponsor_remove'); fd.append('att', b.getAttribute('data-att'));
		send(fd, function (e) { if (e) window.alert(e.message); });
	});

	/* ── Zahtjevi ───────────────────────────────────────────────────────── */
	function renderReqs() {
		var ol = document.getElementById('kpt-reqs');
		ol.innerHTML = (S.requests || []).map(function (r) {
			return '<li class="kpt-rq kpt-rq--' + esc(r.status) + '">' +
				'<span class="kpt-rq__st">' + esc(r.statusL) + '</span>' +
				'<span class="kpt-rq__body"><b>' + esc(r.typeL) + '</b>' + (r.desc ? ' — ' + esc(r.desc) : '') +
				(r.note ? '<em class="kpt-rq__note">KURS: ' + esc(r.note) + '</em>' : '') +
				'<small>' + (r.who ? esc(r.who) + ' · ' : '') + esc(r.at) + '</small></span>' +
				(r.status === 'pending' ? '<button type="button" class="kpt-link" data-cancel="' + r.id + '">Povuci</button>' : '') + '</li>';
		}).join('') || '<li class="kpt-empty">Još niste ništa poslali.</li>';
	}
	document.getElementById('kpt-reqs').addEventListener('click', function (ev) {
		var b = ev.target.closest('button[data-cancel]');
		if (!b || !window.confirm('Povući ovaj zahtjev?')) return;
		var fd = new FormData(); fd.append('do', 'cancel'); fd.append('id', b.getAttribute('data-cancel'));
		send(fd, function (e) { if (e) window.alert(e.message); });
	});

	/* ── Logo (jedan) ───────────────────────────────────────────────────── */
	function renderLogo() {
		var box = document.getElementById('kpt-logo-now'), txt = document.getElementById('kpt-logo-txt');
		if (!box) return;
		var pend = (S.requests || []).filter(function (r) { return r.status === 'pending' && r.type === 'logo'; })[0];
		box.innerHTML = S.logo ? '<img src="' + esc(S.logo) + '" alt="">' : '<em>nema</em>';
		txt.innerHTML = (S.logo ? 'Trenutni logo na webu.' : 'Ekipa još nema logo na webu.') +
			(pend ? '<br><span class="kpt-pend">novi logo čeka odobrenje</span>' + (pend.img ? ' <img class="kpt-logo-now__new" src="' + esc(pend.img) + '" alt="">' : '') : '');
	}

	/* ── Boje dresa ─────────────────────────────────────────────────────── */
	var kit = { color: (S.colors && S.colors.c1 || '#1a3a6b').toLowerCase(), color2: (S.colors && S.colors.c2 || '').toLowerCase() };
	function kitSvg(c1, c2) {
		c2 = c2 || c1;
		return '<svg viewBox="0 0 120 110" width="150" height="138" aria-hidden="true">' +
			'<path d="M38 8 L24 14 L28 42 L28 104 L92 104 L92 42 L96 14 L82 8 Q60 22 38 8 Z" fill="' + c1 + '"/>' +
			'<path d="M24 14 L4 30 L16 50 L28 42 Z M96 14 L116 30 L104 50 L92 42 Z M38 8 Q60 22 82 8 L78 5 Q60 17 42 5 Z" fill="' + c2 + '"/>' +
			'<path d="M38 8 L24 14 L4 30 L16 50 L28 42 L28 104 L92 104 L92 42 L104 50 L116 30 L96 14 L82 8 Q60 22 38 8 Z" fill="none" stroke="#111317" stroke-opacity=".3" stroke-width="2.5" stroke-linejoin="round"/></svg>';
	}
	function renderKit() {
		var f = document.querySelector('.kpt-kit');
		if (!f) return;
		var pend = (S.requests || []).filter(function (r) { return r.status === 'pending' && r.type === 'colors'; })[0];
		document.getElementById('kpt-kit').innerHTML = kitSvg(kit.color, kit.color2) +
			'<small>' + (kit.color2 ? 'dvobojni dres' : 'jednobojni dres') + '</small>' + (pend ? '<span class="kpt-pend">promjena boja čeka odobrenje</span>' : '');
		f.querySelector('[name=color]').value = kit.color;
		f.querySelector('[name=color2]').value = kit.color2;
		f.querySelectorAll('.kpt-sw').forEach(function (sw) {
			var key = sw.getAttribute('data-for'), val = kit[key], hit = false;
			sw.querySelectorAll('.kpt-sw__b').forEach(function (b) { var on = b.getAttribute('data-c') === val; b.classList.toggle('is-on', on); hit = hit || on; });
			var own = sw.querySelector('.kpt-sw__own');
			own.classList.toggle('is-on', !hit && !!val);
			if (!hit && val) own.style.background = val; else own.style.background = '';
		});
	}
	document.querySelectorAll('.kpt-sw').forEach(function (sw) {
		var key = sw.getAttribute('data-for');
		sw.addEventListener('click', function (e) {
			var b = e.target.closest('.kpt-sw__b');
			if (!b) return;
			kit[key] = b.getAttribute('data-c'); renderKit();
		});
		var own = sw.querySelector('input[type=color]');
		own.value = kit[key] || '#ffffff';
		own.addEventListener('input', function () { kit[key] = own.value.toLowerCase(); renderKit(); });
	});

	function render() {
		renderRoster(); renderSpons(); renderReqs(); renderLogo(); renderKit();
		var lg = document.getElementById('kpt-logo');
		if (S.logo && lg && !lg.querySelector('img')) lg.innerHTML = '<img src="' + esc(S.logo) + '" alt="">';
	}

	/* ── Obrasci ────────────────────────────────────────────────────────── */
	document.querySelectorAll('.kpt-form').forEach(function (form) {
		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var st = form.querySelector('.kp-status');
			var btn = form.querySelector('button[type=submit]');
			var fd = new FormData(form);
			var file = form.querySelector('input[type=file]');
			if (file && file.required && !file.files.length) { st.className = 'kp-status is-err'; st.textContent = 'Odaberite sliku.'; return; }
			if (file && file.files[0] && file.files[0].size > 5 * 1024 * 1024) { st.className = 'kp-status is-err'; st.textContent = 'Slika je veća od 5 MB.'; return; }
			if (form.querySelector('[name=tier]') && !fd.get('tier')) { st.className = 'kp-status is-err'; st.textContent = 'Odaberite razinu: glavni, srednji ili mali.'; return; }
			var nm = form.querySelector('input[name=name]');
			if (nm && nm.required && !nm.value.trim()) { st.className = 'kp-status is-err'; st.textContent = 'Upišite naziv.'; nm.focus(); return; }
			fd.append('do', form.getAttribute('data-do'));
			btn.disabled = true; st.className = 'kp-status'; st.textContent = file && file.files.length ? 'Šaljem sliku…' : 'Šaljem…';
			send(fd, function (err) {
				btn.disabled = false;
				if (err) { st.className = 'kp-status is-err'; st.textContent = err.message; return; }
				if (['info', 'colors'].indexOf(form.getAttribute('data-do')) < 0) form.reset();
				st.className = 'kp-status is-ok'; st.textContent = 'Poslano ✓ KURS će to pregledati.';
			});
		});
	});

	render();
})();
