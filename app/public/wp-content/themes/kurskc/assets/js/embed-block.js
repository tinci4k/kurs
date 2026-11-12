/* Blok „KURS — iz lige” (kurs/liga): odabir lige + prikaza u samom bloku i pregled s CSS-om teme.
   Bez build koraka — čisti wp.* globali. PHP: inc/embed-block.php. */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks) return;
	var el = wp.element.createElement;
	var useState = wp.element.useState, useEffect = wp.element.useEffect, useRef = wp.element.useRef, Fragment = wp.element.Fragment;
	var C = wp.components, BE = wp.blockEditor;
	var D = window.KURSKC_EMBED || { opts: {}, css: [] };
	var opts = D.opts || {};
	var divIds = (D.order && D.order.length) ? D.order : Object.keys(opts); // redoslijed iz PHP-a: najnovija sezona prva

	function divOf(v) { var m = /^[a-z]+:(\d+(?:\+\d+)*)/.exec(v || ''); return m ? m[1] : ''; } // „3+4” = obje lige
	function labelOf(v) {
		var d = opts[divOf(v)];
		if (!d) return '';
		var it = (d.items || []).filter(function (i) { return i[0] === v; })[0];
		return d.season + ' · ' + d.division + (it ? ' — ' + it[1] : '');
	}
	function divLabel(id) { var d = opts[id]; return d ? d.season + ' — ' + d.division : id; }

	var icon = el('svg', { viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true },
		el('path', { d: 'M5 3h14a1 1 0 0 1 1 1v9.5c0 3.6-3.4 6.2-8 7.5-4.6-1.3-8-3.9-8-7.5V4a1 1 0 0 1 1-1Z', fill: '#111317' }),
		el('path', { d: 'M5 13c1.5-1 2 .8 3.4-.6 1-1 .3-2.6 1.6-2.9.2 1.4 1.3 1.7 2 .9.9-1 .2-2.7 1.4-3.4.1 1.8 1.5 2.3 2.4 1.5.5 1 .4 2.6 1.2 3.4.9.8 1.6.4 2-.2v1.8c0 3.3-3.1 5.7-7 6.9-3.9-1.2-7-3.6-7-6.9Z', fill: '#F47B16' }),
		el('circle', { cx: 12, cy: 15.5, r: 2.4, fill: '#fff', stroke: '#111', 'stroke-width': .8 }));

	/* Odabir: liga → što prikazati. Mijenja atribut odmah (pregled se osvježi). */
	function Picker(props) {
		var value = props.value;
		var st = useState(divOf(value) || divIds[0] || ''); var div = st[0], setDiv = st[1];
		var items = (opts[div] && opts[div].items) || [];
		return el('div', { className: 'kurskc-eb__picker' },
			el(C.SelectControl, {
				label: 'Liga i sezona', value: div, __nextHasNoMarginBottom: true,
				options: divIds.map(function (id) { return { value: id, label: divLabel(id) }; }),
				onChange: function (d) { setDiv(d); var first = (opts[d] && opts[d].items[0]) ? opts[d].items[0][0] : ''; props.onChange(first); }
			}),
			el(C.SelectControl, {
				label: 'Što prikazati', value: divOf(value) === div ? value : '', __nextHasNoMarginBottom: true,
				options: [{ value: '', label: '— odaberi —' }].concat(items.map(function (i) { return { value: i[0], label: i[1] }; })),
				onChange: function (v) { props.onChange(v); }
			}),
			el('p', { className: 'kurskc-eb__help' },
				'„Kolo” i „Ljestvica nakon kola” ostaju zauvijek na stanju tog kola (dobro za izvještaj). ',
				'„Ljestvica — uvijek aktualna” i „Cijeli raspored” se mijenjaju kako liga napreduje.')
		);
	}

	/* Pregled u iframeu s CSS-om teme — izgleda kao na webu, ne miješa se s uređivačem. */
	function Preview(props) {
		var hs = useState(''); var html = hs[0], setHtml = hs[1];
		var ls = useState(true); var loading = ls[0], setLoading = ls[1];
		var hh = useState(160); var height = hh[0], setHeight = hh[1];
		var ref = useRef(null);
		useEffect(function () {
			var alive = true;
			setLoading(true);
			wp.apiFetch({ path: '/kurskc/v1/embed?value=' + encodeURIComponent(props.value) })
				.then(function (r) { if (alive) { setHtml(r && r.html ? r.html : ''); setLoading(false); } })
				.catch(function () { if (alive) { setHtml(''); setLoading(false); } });
			return function () { alive = false; };
		}, [props.value]);
		function fit() {
			var f = ref.current;
			try { var b = f.contentDocument.body; setHeight(Math.max(80, b.scrollHeight + 8)); } catch (e) {}
		}
		if (loading) return el('div', { className: 'kurskc-eb__loading' }, el(C.Spinner), ' Učitavam pregled…');
		if (!html) return el('div', { className: 'kurskc-eb__empty' }, 'Za ovaj odabir još nema podataka (npr. kolo bez unesenih utakmica).');
		var doc = '<!doctype html><html><head><meta charset="utf-8">' +
			(D.css || []).map(function (u) { return '<link rel="stylesheet" href="' + u + '">'; }).join('') +
			'<style>html,body{background:#fff!important;margin:0}body{padding:4px 2px}a{pointer-events:none}</style></head>' +
			'<body><div class="post-embed">' + html + '</div></body></html>';
		return el('iframe', {
			ref: ref, srcDoc: doc, title: 'Pregled', className: 'kurskc-eb__frame',
			style: { width: '100%', height: height + 'px', border: 0, display: 'block' },
			onLoad: function () { fit(); setTimeout(fit, 400); setTimeout(fit, 1200); }
		});
	}

	wp.blocks.registerBlockType('kurs/liga', {
		apiVersion: 2,
		title: 'KURS — iz lige',
		description: 'Kolo (rezultati, ljestvica, strijelci), samo rezultati ili najava, ljestvica, raspored ili playoff iz KC lige / Pan lige.',
		category: 'kurs',
		icon: icon,
		keywords: ['kolo', 'rezultati', 'ljestvica', 'tablica', 'raspored', 'liga', 'kurs', 'strijelci'],
		attributes: { value: { type: 'string', default: '' } },
		supports: { html: false },
		edit: function (props) {
			var value = props.attributes.value;
			var es = useState(!value); var editing = es[0], setEditing = es[1];
			var blockProps = BE.useBlockProps({ className: 'kurskc-eb' });
			var set = function (v) { props.setAttributes({ value: v }); };
			return el('div', blockProps,
				el(BE.BlockControls, null, el(C.ToolbarGroup, null,
					el(C.ToolbarButton, { icon: 'edit', label: editing ? 'Gotovo' : 'Promijeni odabir', isPressed: editing, onClick: function () { setEditing(!editing); } }))),
				el(BE.InspectorControls, null, el(C.PanelBody, { title: 'Iz lige', initialOpen: true }, el(Picker, { value: value, onChange: set }))),
				el('div', { className: 'kurskc-eb__head' },
					el('span', { className: 'kurskc-eb__badge' }, 'KURS — iz lige'),
					el('span', { className: 'kurskc-eb__label' }, value ? labelOf(value) : 'Odaberi ligu i što prikazati'),
					value ? el(C.Button, { variant: 'secondary', size: 'small', onClick: function () { setEditing(!editing); } }, editing ? 'Gotovo' : 'Promijeni') : null),
				(editing || !value) ? el(Picker, { value: value, onChange: set }) : null,
				value ? el(Preview, { value: value }) : el('p', { className: 'kurskc-eb__hint' }, 'Nakon odabira ovdje se odmah vidi kako će izgledati u vijesti.')
			);
		},
		save: function () { return null; }
	});

})(window.wp);
