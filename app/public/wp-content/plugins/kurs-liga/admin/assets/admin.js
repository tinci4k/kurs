/* KURS Liga — Admin JS v1.2 */

/* ── Flatpickr config ─────────────────────────────────────────────────────── */
( function initFlatpickr() {
	if ( typeof flatpickr === 'undefined' ) return;

	if ( flatpickr.l10ns && flatpickr.l10ns.hr ) {
		flatpickr.localize( flatpickr.l10ns.hr );
	}

	const FP_CONFIG = {
		enableTime:     true,
		time_24hr:      true,
		dateFormat:     'd.m.Y H:i',
		allowInput:     true,
		disableMobile:  true,
	};

	/* Inicijalni inputi (dodaj utakmicu forma) */
	document.querySelectorAll( 'input.kurs-datepicker' ).forEach( el => {
		flatpickr( el, FP_CONFIG );
	} );

	/* Inline edit forme se renderiraju statično pa se inicijaliziraju odmah,
	   ali neke mogu biti skrivene — to je OK, flatpickr radi i na display:none */
} )();

jQuery( function ( $ ) {
	const url   = KursAjax.url;
	const nonce = KursAjax.nonce;

	// Zaključana sezona: server odbija izmjenu (code season_locked) — javi zašto se ništa nije dogodilo.
	$( document ).ajaxSuccess( function ( e, xhr ) {
		const r = xhr.responseJSON;
		if ( r && r.success === false && r.data && r.data.code === 'season_locked' ) alert( r.data.message );
	} );

	// Kontekst stranice (team_id + division_id iz roster stranice)
	const $ctx       = $( '#kurs-roster-ctx' );
	const TEAM_ID     = $ctx.length ? parseInt( $ctx.data( 'team-id' ) )     : 0;
	const DIVISION_ID = $ctx.length ? parseInt( $ctx.data( 'division-id' ) ) : 0;

	// ══════════════════════════════════════════════════════════════════════════
	// Liga setup: Dodjela ekipa grupama
	// ══════════════════════════════════════════════════════════════════════════

	/* Uvrsti ekipu u ligu/skupinu (iz pretrage u stupcu ili gumbom u popisu ekipa) */
	function assignTeam( group_id, team_id, $btn ) {
		const $col = $( `.kurs-group-col[data-group-id="${group_id}"]` );
		if ( $btn ) $btn.prop( 'disabled', true );
		return $.post( url, { action: 'kurs_assign_team', nonce, group_id, team_id } ).done( res => {
			if ( res.success ) {
				const d = res.data;
				$col.find( '.kurs-group-col__teams' ).append(
					`<div class="kurs-group-team" data-team-id="${d.team_id}">` +
					( d.badge || '' ) +
					`<span>${h( d.team_name )}</span>` +
					( d.roster ? `<a class="kurs-group-team__roster" href="${d.roster}" title="Igrači ekipe u ovoj ligi">0 igr.</a>` : '' ) +
					`<button class="kurs-btn-rm" data-group-id="${group_id}" data-team-id="${d.team_id}" title="Makni iz lige">&times;</button>` +
					`</div>`
				);
				updateGroupCount( $col );
				markPool( d.team_id, true );
			} else {
				alert( ( res.data && res.data.message ) || 'Ekipu nije moguće dodati.' );
			}
		} ).always( () => { if ( $btn ) $btn.prop( 'disabled', false ); } );
	}

	/* Popis ekipa (lijevo): označi koje su već u nekoj ligi ove sezone */
	function markPool( team_id, assigned ) {
		const $pill = $( `.kurs-team-pill[data-team-id="${team_id}"]` );
		if ( ! $pill.length ) return;
		const inAny = assigned || $( `.kurs-group-col .kurs-group-team[data-team-id="${team_id}"]` ).length > 0;
		$pill.toggleClass( 'is-assigned', inAny );
		const $free = $( '#kurs-pool-free-count' );
		if ( $free.length ) $free.text( $( '.kurs-team-pill:not(.is-assigned)' ).length );
	}

	$( document ).on( 'click', '.kurs-btn-add-team', function () {
		const $col     = $( this ).closest( '.kurs-group-col' );
		const $hidden  = $col.find( '.kurs-team-selected-id' );
		const team_id  = $hidden.val();

		if ( ! team_id ) {
			$col.find( '.kurs-team-search' ).focus();
			return;
		}
		assignTeam( $col.data( 'group-id' ), team_id, $( this ) ).done( res => {
			if ( res.success ) {
				$col.find( '.kurs-team-search' ).val( '' );
				$hidden.val( '' );
				closeTeamAC();
			}
		} );
	} );

	$( document ).on( 'click', '.kurs-pool-assign', function () {
		assignTeam( $( this ).data( 'group-id' ), $( this ).closest( '.kurs-team-pill' ).data( 'team-id' ), $( this ) );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Liga setup: Team autocomplete (search + select u grupu)
	// ══════════════════════════════════════════════════════════════════════════

	const teamData = () => ( typeof window.KursTeams !== 'undefined' ? window.KursTeams : [] );

	$( document ).on( 'input', '.kurs-team-search', function () {
		const $inp  = $( this );
		const term  = $inp.val().trim().toLowerCase();
		const $wrap = $inp.closest( '.kurs-team-ac-wrap' );

		// Resetiraj odabir svaki put kad korisnik tipka
		$inp.closest( '.kurs-group-col__add' ).find( '.kurs-team-selected-id' ).val( '' );

		if ( term.length < 1 ) { closeTeamAC( $wrap ); return; }

		const matches = teamData()
			.filter( t => t.name.toLowerCase().includes( term ) )
			.slice( 0, 10 );

		if ( matches.length ) {
			renderTeamAC( matches, $wrap );
		} else {
			closeTeamAC( $wrap );
		}
	} );

	$( document ).on( 'keydown', '.kurs-team-search', function ( e ) {
		const $list  = $( '#kurs-team-ac-floating:visible' );
		if ( ! $list.length ) return;

		if ( e.key === 'Escape' ) { closeTeamAC( $wrap ); return; }

		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			const $items = $list.find( '.kurs-team-ac-item' );
			const idx    = $items.index( $items.filter( '.is-focused' ) );
			$items.removeClass( 'is-focused' ).eq( Math.min( idx + 1, $items.length - 1 ) ).addClass( 'is-focused' );
		}
		if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			const $items = $list.find( '.kurs-team-ac-item' );
			const idx    = $items.index( $items.filter( '.is-focused' ) );
			$items.removeClass( 'is-focused' ).eq( Math.max( idx - 1, 0 ) ).addClass( 'is-focused' );
		}
		if ( e.key === 'Enter' ) {
			const $focused = $list.find( '.kurs-team-ac-item.is-focused' );
			if ( $focused.length ) { e.preventDefault(); $focused.trigger( 'click' ); }
		}
	} );

	// Klik na stavku u team autocomplete (lista je u <body>)
	$( document ).on( 'click', '.kurs-team-ac-item', function () {
		const $item = $( this );
		const $wrap = $( '#kurs-team-ac-floating' ).data( 'wrap' );
		if ( ! $wrap ) return;
		$wrap.find( '.kurs-team-search' ).val( $item.data( 'name' ) );
		$wrap.find( '.kurs-team-selected-id' ).val( $item.data( 'team-id' ) );
		closeTeamAC();
	} );

	// Klik van autocomplete liste → zatvori
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.kurs-team-ac-wrap, .kurs-team-ac-list' ).length ) {
			closeTeamAC();
		}
	} );

	// Lista se renderira u <body> (portal) i pozicionira fiksno ispod inputa —
	// inače je reže overflow:hidden roditelja (.kurs-group-col), pa je skrivena.
	function renderTeamAC( teams, $wrap ) {
		const $inp = $wrap.find( '.kurs-team-search' );
		let $list  = $( '#kurs-team-ac-floating' );
		if ( ! $list.length ) {
			$list = $( '<div class="kurs-team-ac-list" id="kurs-team-ac-floating"></div>' ).appendTo( 'body' );
		}
		$list.data( 'wrap', $wrap ).empty();
		teams.forEach( t => {
			$( `<div class="kurs-team-ac-item" data-team-id="${t.id}" data-name="${h( t.name )}">` +
			   `<span>${h( t.name )}</span>` +
			   `</div>` ).appendTo( $list );
		} );
		positionTeamAC( $inp, $list );
		$list.show();
	}

	function positionTeamAC( $inp, $list ) {
		if ( ! $inp || ! $inp.length ) return;
		const r = $inp[0].getBoundingClientRect();
		$list.css( { position: 'fixed', top: ( r.bottom + 2 ) + 'px', left: r.left + 'px', right: 'auto', width: r.width + 'px' } );
	}

	function closeTeamAC() {
		$( '#kurs-team-ac-floating' ).hide().empty().removeData( 'wrap' );
	}

	// Repozicioniraj otvorenu listu pri skrolu/resizeu (fixed pozicija).
	$( window ).on( 'scroll resize', function () {
		const $list = $( '#kurs-team-ac-floating:visible' );
		if ( ! $list.length ) return;
		const $wrap = $list.data( 'wrap' );
		if ( $wrap ) positionTeamAC( $wrap.find( '.kurs-team-search' ), $list );
	} );

	$( document ).on( 'click', '.kurs-btn-rm', function () {
		const $row     = $( this ).closest( '.kurs-group-team' );
		const $col     = $( this ).closest( '.kurs-group-col' );
		const group_id = $( this ).data( 'group-id' );
		const team_id  = $( this ).data( 'team-id' );
		$.post( url, { action: 'kurs_remove_team', nonce, group_id, team_id } ).done( res => {
			if ( res.success ) { $row.remove(); updateGroupCount( $col ); markPool( team_id, false ); }
			else alert( ( res.data && res.data.message ) || 'Ekipu nije moguće maknuti.' );
		} );
	} );

	function updateGroupCount( $col ) {
		const n = $col.find( '.kurs-group-team' ).length;
		$col.find( '.kurs-cnt' ).text( n + ' ekipa' );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Liga setup: Brzo kreiranje ekipe na koraku "Dodjela ekipa"
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '#btn-create-team-setup', function () {
		const $btn  = $( this ).prop( 'disabled', true );
		const name  = $( '#new-team-name-setup' ).val().trim();
		const $msg  = $( '#new-team-setup-msg' );

		if ( ! name ) {
			$msg.text( 'Unesi naziv ekipe.' ).css( 'color', '' ).show();
			$btn.prop( 'disabled', false );
			return;
		}

		$msg.text( 'Kreiram...' ).css( 'color', '#888' ).show();

		$.post( url, { action: 'kurs_create_team', nonce, team_name: name } ).done( res => {
			if ( res.success ) {
				const { team_id, name: team_name } = res.data;

				// Dodaj u popis ekipa (na vrh, s gumbima za uvrštavanje u ligu/skupinu)
				const initials = team_name.substring( 0, 2 ).toUpperCase();
				const tpl      = document.getElementById( 'kurs-pool-assign-tpl' );
				$( '#kurs-team-pool-list' ).prepend(
					`<div class="kurs-team-pill" data-team-id="${team_id}">` +
					`<span class="kurs-avatar">${h( initials )}</span>` +
					`<span class="kurs-team-pill__name">${h( team_name )}</span>` +
					`<span class="kurs-team-pill__go">${tpl ? tpl.innerHTML : ''}</span>` +
					`</div>`
				);
				markPool( team_id, false );

				// Dodaj u window.KursTeams da autocomplete odmah vidi novu ekipu
				if ( typeof window.KursTeams !== 'undefined' ) {
					window.KursTeams.push( { id: team_id, name: team_name } );
					window.KursTeams.sort( ( a, b ) => a.name.localeCompare( b.name, 'hr' ) );
				}

				// Nova ekipa je u poolu bez lige → brojač „Bez lige u ovoj sezoni"
				$( '#kurs-pool-free-count' ).text( ( parseInt( $( '#kurs-pool-free-count' ).text() ) || 0 ) + 1 );

				$( '#new-team-name-setup' ).val( '' );
				$msg.text( '✓ ' + team_name + ' kreirana.' ).css( 'color', '#1e6f26' );
				setTimeout( () => $msg.fadeOut(), 3000 );

			} else if ( res.data && res.data.code === 'duplicate' ) {
				$msg.text( res.data.message + ' (već postoji u sustavu)' ).css( 'color', '#856404' );
			} else {
				const errMsg = typeof res.data === 'string' ? res.data : ( res.data && res.data.message ) || 'Greška.';
				$msg.text( errMsg ).css( 'color', '#c00' );
			}
		} ).always( () => $btn.prop( 'disabled', false ) );
	} );

	// Enter u polju naziva ekipe == klikni gumb
	$( document ).on( 'keydown', '#new-team-name-setup', function ( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); $( '#btn-create-team-setup' ).trigger( 'click' ); }
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Boja dresova — novi dropdown color picker
	// ══════════════════════════════════════════════════════════════════════════

	function cpApplyColor( pickerId, color ) {
		var c = color.trim();
		if ( ! /^#[0-9a-fA-F]{3,6}$/.test( c ) ) return;
		$( '#cp-val-'   + pickerId ).val( c );
		$( '#cp-dot-'   + pickerId ).css( 'background', c );
		$( '#cp-label-' + pickerId ).text( c.toUpperCase() );
		$( '#cp-wheel-' + pickerId ).val( c );
		$( '#cp-hex-'   + pickerId ).val( c.toUpperCase() );
		// Active swatch
		$( '#cp-drop-' + pickerId + ' .kurs-cp-swatch' ).removeClass( 'is-active' );
		$( '#cp-drop-' + pickerId + ' .kurs-cp-swatch' ).each( function () {
			if ( $( this ).data( 'color' ).toUpperCase() === c.toUpperCase() ) {
				$( this ).addClass( 'is-active' );
			}
		} );
	}

	// Toggle dropdown — pozicionira s fixed koordinatama da izbjegne overflow:hidden
	$( document ).on( 'click', '.kurs-cp-trigger', function ( e ) {
		e.stopPropagation();
		var pickerId = $( this ).attr( 'id' ).replace( 'cp-btn-', '' );
		var $drop    = $( '#cp-drop-' + pickerId );
		var isOpen   = $drop.hasClass( 'is-open' );

		$( '.kurs-cp-dropdown.is-open' ).removeClass( 'is-open' );
		if ( isOpen ) return;

		var rect = this.getBoundingClientRect();
		var dropW = 210;
		var left  = rect.left;
		// Provjeri ne izlazi li desno iz viewporta
		if ( left + dropW > window.innerWidth - 8 ) {
			left = window.innerWidth - dropW - 8;
		}
		$drop.css( { top: ( rect.bottom + 5 ) + 'px', left: left + 'px' } );
		$drop.addClass( 'is-open' );
	} );

	// Klik na preset swatch
	$( document ).on( 'click', '.kurs-cp-swatch', function ( e ) {
		e.stopPropagation();
		var pickerId = $( this ).data( 'picker' );
		cpApplyColor( pickerId, $( this ).data( 'color' ) );
	} );

	// Color wheel change
	$( document ).on( 'input', '.kurs-cp-wheel', function () {
		var pickerId = $( this ).attr( 'id' ).replace( 'cp-wheel-', '' );
		cpApplyColor( pickerId, $( this ).val() );
	} );

	// Hex input change
	$( document ).on( 'input change', '.kurs-cp-hex', function () {
		var pickerId = $( this ).attr( 'id' ).replace( 'cp-hex-', '' );
		var val = $( this ).val().trim();
		if ( val.charAt(0) !== '#' ) val = '#' + val;
		cpApplyColor( pickerId, val );
	} );

	// Zatvori dropdown klikom izvan
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.kurs-cp-wrap' ).length ) {
			$( '.kurs-cp-dropdown.is-open' ).removeClass( 'is-open' );
		}
	} );

	// ── Legacy swatch picker (ostaje za season-setup i sl.) ─────────────────
	$( document ).on( 'click', '.kurs-color-swatch', function () {
		const $swatch = $( this );
		const color   = $swatch.data( 'color' );
		const $wrap   = $swatch.closest( '.kurs-color-row' );
		$wrap.find( '.kurs-color-swatch' ).removeClass( 'is-active' );
		$swatch.addClass( 'is-active' );
		$wrap.find( 'input[type="color"]' ).val( color );
	} );
	$( document ).on( 'input', '.kurs-color-custom', function () {
		$( this ).closest( '.kurs-color-row' ).find( '.kurs-color-swatch' ).removeClass( 'is-active' );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Logo ekipe — WordPress Media Library modal
	// ══════════════════════════════════════════════════════════════════════════

	// Baneri (sponzori): „Dodaj baner" → media (više) → redovi u listi (spremaju se formom)
	$( document ).on( 'click', '.kurs-banner-add', function ( e ) {
		e.preventDefault();
		if ( typeof wp === 'undefined' || ! wp.media ) {
			alert( 'WordPress Media Library nije dostupna. Osvježi stranicu i pokušaj ponovo.' );
			return;
		}
		const frame = wp.media( {
			title:    'Odaberi banere ekipe',
			button:   { text: 'Dodaj u banere' },
			multiple: true,
			library:  { type: 'image' },
		} );
		frame.on( 'select', function () {
			const $list = $( '#kurs-banner-list' );
			$list.find( '.kurs-banner-empty--list' ).remove();
			frame.state().get( 'selection' ).toJSON().forEach( function ( a ) {
				const thumb = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
				$list.append(
					'<div class="kurs-banner-row">' +
					'<input type="hidden" name="banner_ids[]" value="' + a.id + '">' +
					'<img src="' + thumb + '" alt="" class="kurs-banner-row__img">' +
					'<input type="url" name="banner_links[]" value="" placeholder="https://sponzor.hr (link, neobavezno)" class="kurs-banner-row__link">' +
					'<button type="button" class="kurs-banner-remove" title="Ukloni baner">&times;</button>' +
					'</div>'
				);
			} );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.kurs-banner-remove', function () {
		$( this ).closest( '.kurs-banner-row' ).remove();
	} );

	// Prijenos ekipa iz prethodne sezone (kartica Ekipe): sve kao prošle sezone / nijedna
	$( document ).on( 'click', '.kurs-carry [data-carry]', function () {
		const none = $( this ).data( 'carry' ) === 'none';
		$( this ).closest( 'form' ).find( '.kurs-carry__sel' ).each( function () {
			$( this ).val( none ? '0' : String( $( this ).data( 'default' ) ) );
		} );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Suci sezone (Stranica natjecanja → Postavke) — redovi Ime / Prezime
	// ══════════════════════════════════════════════════════════════════════════

	const $refRows = $( '#kurs-ref-rows' );
	if ( $refRows.length ) {
		let refsDirty = false;
		const refKey  = ( ime, prezime ) => ( ime + ' ' + prezime ).trim().toLowerCase();

		function addRef( ime = '', prezime = '' ) {
			const $row = $( $( '#kurs-ref-row-tpl' ).html() ).appendTo( $refRows );
			const $in  = $row.find( 'input' );
			$in.eq( 0 ).val( ime );
			$in.eq( 1 ).val( prezime );
			return $row;
		}
		// Suci prethodne sezone kojih ovdje još nema
		function importable() {
			const have = $refRows.find( '.kurs-ref-row' ).map( function () {
				const $in = $( this ).find( 'input' );
				return refKey( $in.eq( 0 ).val(), $in.eq( 1 ).val() );
			} ).get();
			return ( $( '#kurs-ref-import' ).data( 'refs' ) || [] ).filter( r => ! have.includes( refKey( r.ime, r.prezime ) ) );
		}
		function refreshImport() {
			const n = importable().length;
			$( '#kurs-ref-import' ).prop( 'hidden', ! n ).find( '.kurs-ref-import__n' ).text( '(' + n + ')' );
		}
		function refsChanged() {
			const n = $refRows.children( '.kurs-ref-row' ).length;
			$( '#kurs-ref-count' ).text( n );
			$( '#kurs-ref-empty' ).prop( 'hidden', n > 0 );
			$( '#kurs-ref-dirty' ).prop( 'hidden', false );
			refsDirty = true;
			refreshImport();
		}

		$( '#kurs-ref-add' ).on( 'click', function () {
			addRef().find( 'input' ).first().trigger( 'focus' );
			refsChanged();
		} );
		$refRows.on( 'click', '.kurs-ref-remove', function () {
			$( this ).closest( '.kurs-ref-row' ).remove();
			refsChanged();
		} );
		$refRows.on( 'input', 'input', refsChanged );

		// Enter: Ime → Prezime → sljedeći (ili novi) redak, umjesto slanja forme
		$refRows.on( 'keydown', 'input', function ( e ) {
			if ( e.key !== 'Enter' ) return;
			e.preventDefault();
			const $row = $( this ).closest( '.kurs-ref-row' );
			const $in  = $row.find( 'input' );
			if ( this === $in[0] ) { $in.eq( 1 ).trigger( 'focus' ); return; }
			let $next = $row.next( '.kurs-ref-row' );
			if ( ! $next.length ) {
				if ( ! $in.eq( 0 ).val().trim() && ! $in.eq( 1 ).val().trim() ) return; // prazan zadnji redak
				$next = addRef();
				refsChanged();
			}
			$next.find( 'input' ).first().trigger( 'focus' );
		} );

		$( '#kurs-ref-import' ).on( 'click', function () {
			importable().forEach( r => addRef( r.ime, r.prezime ) );
			refsChanged();
		} );
		refreshImport();

		$( '#kurs-refs-editor' ).on( 'submit', () => { refsDirty = false; } );
		$( window ).on( 'beforeunload', () => ( refsDirty ? true : undefined ) );
	}

	$( document ).on( 'click', '.kurs-photo-btn', function ( e ) {
		e.preventDefault();
		const $btn    = $( this );
		const team_id = $btn.data( 'team-id' );

		if ( typeof wp === 'undefined' || ! wp.media ) {
			alert( 'WordPress Media Library nije dostupna. Osvježi stranicu i pokušaj ponovo.' );
			return;
		}

		const frame = wp.media( {
			title:    'Odaberi sliku momčadi',
			button:   { text: 'Postavi sliku' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			const att = frame.state().get( 'selection' ).first().toJSON();
			$btn.prop( 'disabled', true ).text( '...' );

			$.post( url, { action: 'kurs_set_photo', nonce, team_id, attachment_id: att.id } ).done( res => {
				if ( res.success ) {
					$( '#photo-cell-' + team_id ).html(
						`<img src="${res.data.url}" alt="" style="max-width:100%;height:auto;border-radius:6px;border:1px solid var(--ka-border);display:block;">`
					);
					$btn.prop( 'disabled', false ).text( '✓ Slika postavljena' );
					setTimeout( () => $btn.text( '📷 Zamijeni sliku' ), 2000 );
				} else {
					alert( 'Greška pri postavljanju slike.' );
					$btn.prop( 'disabled', false ).text( '📷 Postavi sliku' );
				}
			} );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.kurs-logo-btn', function ( e ) {
		e.preventDefault();
		const $btn    = $( this );
		const team_id = $btn.data( 'team-id' );

		if ( typeof wp === 'undefined' || ! wp.media ) {
			alert( 'WordPress Media Library nije dostupna. Osvježi stranicu i pokušaj ponovo.' );
			return;
		}

		const frame = wp.media( {
			title:    'Odaberi logo ekipe',
			button:   { text: 'Postavi ovaj logo' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			const att = frame.state().get( 'selection' ).first().toJSON();
			$btn.prop( 'disabled', true ).text( '...' );

			$.post( url, { action: 'kurs_set_logo', nonce, team_id, attachment_id: att.id } ).done( res => {
				if ( res.success ) {
					$( '#logo-cell-' + team_id ).html(
						`<img src="${res.data.url}" style="width:40px;height:40px;object-fit:cover;border-radius:50%;display:block;">`
					);
					$btn.text( '✓ Postavljeno' );
					setTimeout( () => $btn.prop( 'disabled', false ).text( '📷 Logo' ), 2000 );
				} else {
					alert( 'Greška pri postavljanju loga.' );
					$btn.prop( 'disabled', false ).text( '📷 Logo' );
				}
			} );
		} );

		frame.open();
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Roster: Autocomplete za "Kreiraj novog igrača"
	// ══════════════════════════════════════════════════════════════════════════

	let acTimer;

	$( document ).on( 'input', '#new-player-name', function () {
		const term = $( this ).val().trim();
		clearTimeout( acTimer );

		if ( term.length < 2 ) { closeAC(); return; }

		acTimer = setTimeout( () => {
			$.post( url, { action: 'kurs_search_players', nonce, term } ).done( res => {
				if ( res.success && res.data.length ) {
					renderAC( res.data );
				} else {
					closeAC();
				}
			} );
		}, 220 );
	} );

	$( document ).on( 'keydown', '#new-player-name', function ( e ) {
		const $list = $( '.kurs-ac-list:visible' );
		if ( ! $list.length ) return;

		if ( e.key === 'Escape' ) { closeAC(); return; }
		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			const $items = $list.find( '.kurs-ac-item' );
			const idx    = $items.index( $items.filter( '.is-focused' ) );
			$items.removeClass( 'is-focused' ).eq( Math.min( idx + 1, $items.length - 1 ) ).addClass( 'is-focused' );
		}
		if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			const $items = $list.find( '.kurs-ac-item' );
			const idx    = $items.index( $items.filter( '.is-focused' ) );
			$items.removeClass( 'is-focused' ).eq( Math.max( idx - 1, 0 ) ).addClass( 'is-focused' );
		}
		if ( e.key === 'Enter' ) {
			const $focused = $list.find( '.kurs-ac-item.is-focused' );
			if ( $focused.length ) { e.preventDefault(); $focused.trigger( 'click' ); }
		}
	} );

	// Klik van autocomplete → zatvori
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.kurs-ac-wrap' ).length ) closeAC();
	} );

	function renderAC( players ) {
		const $wrap = $( '.kurs-ac-wrap' );
		let $list = $wrap.find( '.kurs-ac-list' );
		if ( ! $list.length ) {
			$list = $( '<div class="kurs-ac-list"></div>' ).appendTo( $wrap );
		}

		$list.empty();
		players.forEach( p => {
			const already = $( '#squad-row-' + p.id ).length > 0;
			const badge   = already
				? `<span class="kurs-ac-badge kurs-ac-badge--in">u rosteru</span>`
				: `<span class="kurs-ac-badge">postoji u bazi</span>`;

			$( `<div class="kurs-ac-item${already ? ' is-in-squad' : ''}"
			         data-player-id="${p.id}"
			         data-name="${h( p.name )}">
			        <span class="kurs-ac-name">${h( p.name )}</span>
			        ${badge}
			    </div>` ).appendTo( $list );
		} );
		$list.show();
	}

	function closeAC() {
		$( '.kurs-ac-list' ).hide().empty();
	}

	// Klik na autocomplete prijedlog → dodaj u roster (ne kreiraj novog)
	$( document ).on( 'click', '.kurs-ac-item', function () {
		const player_id   = $( this ).data( 'player-id' );
		if ( ! player_id ) return; // team autocomplete koristi .kurs-team-ac-item, ovo je samo za igrače
		const player_name = $( this ).data( 'name' );
		closeAC();

		if ( $( '#squad-row-' + player_id ).length ) {
			showMsg( '#quick-add-msg', '⚠ Igrač je već u rosteru.', 'warning' );
			return;
		}

		const jersey = parseInt( $( '#new-player-jersey' ).val() ) || 0;
		doAddExisting( player_id, player_name, jersey );

		$( '#new-player-name' ).val( '' );
		$( '#new-player-jersey' ).val( '' );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Roster: dodaj postojećeg igrača iz selecta
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '#btn-add-squad', function () {
		const $btn      = $( this ).prop( 'disabled', true );
		const player_id = $( '#squad-player-select' ).val();
		const jersey    = parseInt( $( '#squad-jersey' ).val() ) || 0;

		if ( ! player_id ) { alert( 'Odaberi igrača.' ); $btn.prop( 'disabled', false ); return; }

		const player_name = $( '#squad-player-select option:selected' ).text();

		$.post( url, { action: 'kurs_add_squad', nonce, player_id, team_id: TEAM_ID, division_id: DIVISION_ID, jersey } )
			.done( res => {
				if ( res.success ) {
					appendSquadRow( res.data );
					$( '#squad-player-select option[value="' + player_id + '"]' ).remove();
					$( '#squad-jersey' ).val( '' );
					refreshExistingPanel();
				} else {
					if ( ! ( res.data && res.data.code === 'season_locked' ) ) alert( res.data || 'Greška.' );
				}
			} ).always( () => $btn.prop( 'disabled', false ) );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Roster: Kreiraj novog igrača + check duplikata
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '#btn-quick-add', function () {
		const name   = $( '#new-player-name' ).val().trim();
		const jersey = parseInt( $( '#new-player-jersey' ).val() ) || 0;
		const type   = $( '#new-player-type' ).val() === 'goalkeeper' ? 'goalkeeper' : 'player';

		if ( ! name ) { showMsg( '#quick-add-msg', 'Unesi ime igrača.' ); return; }

		closeAC();

		// Provjera duplikata
		$.post( url, { action: 'kurs_search_players', nonce, term: name } ).done( res => {
			if ( ! res.success ) { doCreatePlayer( name, jersey, type ); return; }

			const exact = res.data.find( p => p.name.toLowerCase() === name.toLowerCase() );
			if ( exact ) {
				showDupModal( exact, name, jersey, type );
			} else {
				doCreatePlayer( name, jersey, type );
			}
		} );
	} );

	// ── Duplikat modal akcije ─────────────────────────────────────────────────

	$( document ).on( 'click', '#btn-dup-add-existing', function () {
		const d = $( '#kurs-dup-modal' ).data( 'payload' );
		closeDupModal();

		if ( $( '#squad-row-' + d.player_id ).length ) {
			showMsg( '#quick-add-msg', '⚠ Igrač je već u rosteru.', 'warning' );
			return;
		}
		doAddExisting( d.player_id, d.player_name, d.jersey );
		clearNewPlayerInputs();
	} );

	$( document ).on( 'click', '#btn-dup-create-new', function () {
		const d = $( '#kurs-dup-modal' ).data( 'payload' );
		closeDupModal();
		doCreatePlayer( d.name, d.jersey, d.type );
	} );

	$( document ).on( 'click', '#btn-dup-cancel, #kurs-dup-backdrop', closeDupModal );

	function showDupModal( exact, name, jersey, type ) {
		$( '#kurs-dup-msg' ).html(
			`Igrač <strong>${h( exact.name )}</strong> već postoji u bazi igrača.`
		);
		$( '#kurs-dup-modal' )
			.data( 'payload', { player_id: exact.id, player_name: exact.name, name, jersey, type } )
			.show();
	}

	function closeDupModal() {
		$( '#kurs-dup-modal' ).hide().removeData( 'payload' );
	}

	function doAddExisting( player_id, player_name, jersey ) {
		showMsg( '#quick-add-msg', 'Dodajem...' );
		$.post( url, { action: 'kurs_add_squad', nonce, player_id, team_id: TEAM_ID, division_id: DIVISION_ID, jersey } )
			.done( res => {
				if ( res.success ) {
					appendSquadRow( res.data );
					$( '#squad-player-select option[value="' + player_id + '"]' ).remove();
					refreshExistingPanel();
					showMsg( '#quick-add-msg', '✓ ' + h( res.data.player_name ) + ' dodan.', 'ok' );
					setTimeout( () => $( '#quick-add-msg' ).fadeOut(), 3000 );
				} else {
					showMsg( '#quick-add-msg', res.data || 'Greška.' );
				}
			} );
	}

	function doCreatePlayer( name, jersey, type ) {
		showMsg( '#quick-add-msg', 'Kreiranje...' );
		$.post( url, { action: 'kurs_quick_add_player', nonce, name, team_id: TEAM_ID, division_id: DIVISION_ID, jersey, type } )
			.done( res => {
				if ( res.success ) {
					appendSquadRow( res.data );
					clearNewPlayerInputs();
					refreshExistingPanel();
					showMsg( '#quick-add-msg', '✓ ' + h( res.data.player_name ) + ' kreiran i dodan.', 'ok' );
					setTimeout( () => $( '#quick-add-msg' ).fadeOut(), 3000 );
				} else {
					showMsg( '#quick-add-msg', res.data || 'Greška.' );
				}
			} );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Roster: Ukloni igrača
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '.kurs-btn-rm-squad', function () {
		const player_id   = $( this ).data( 'player-id' );
		const $row        = $( '#squad-row-' + player_id );
		const player_name = $row.data( 'name' );

		if ( ! confirm( 'Ukloni ' + player_name + ' iz rostera?' ) ) return;

		$( this ).prop( 'disabled', true );
		$.post( url, { action: 'kurs_remove_squad', nonce, player_id, team_id: TEAM_ID, division_id: DIVISION_ID } )
			.done( res => {
				if ( res.success ) {
					$row.fadeOut( 180, function () {
						$( this ).remove();
						updateSquadCount();
						refreshExistingPanel( { id: player_id, name: player_name } );
					} );
				}
			} );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Roster: Inline uređivanje retka
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '.kurs-btn-edit-squad', function () {
		const player_id = $( this ).data( 'player-id' );
		const $row      = $( '#squad-row-' + player_id );
		const name      = $row.data( 'name' );
		const jersey    = $row.data( 'jersey' ) || '';
		const type      = $row.data( 'type' ) || 'player';

		$row.find( '.squad-cell-jersey' ).html(
			`<input type="number" class="small-text squad-edit-jersey" value="${jersey}" min="0" max="99" style="width:52px;">`
		);
		$row.find( '.squad-cell-name' ).html(
			`<input type="text" class="regular-text squad-edit-name" value="${escAttr( name )}" style="max-width:150px;"> ` +
			`<select class="squad-edit-type">` +
			`<option value="player"${type !== 'goalkeeper' ? ' selected' : ''}>Igrač</option>` +
			`<option value="goalkeeper"${type === 'goalkeeper' ? ' selected' : ''}>Golman</option>` +
			`</select>`
		);
		$row.find( '.squad-actions' ).html(
			`<button class="button button-primary button-small btn-save-squad" data-player-id="${player_id}">Spremi</button> ` +
			`<button class="button button-small btn-cancel-squad" data-player-id="${player_id}" ` +
			`data-name="${escAttr( name )}" data-jersey="${ $row.data( 'jersey' ) || 0 }" data-type="${type}">Odustani</button>`
		);
		$row.find( '.squad-edit-name' ).focus().select();
	} );

	$( document ).on( 'click', '.btn-cancel-squad', function () {
		const player_id = $( this ).data( 'player-id' );
		const name      = $( this ).data( 'name' );
		const jersey    = $( this ).data( 'jersey' );
		const type      = $( this ).data( 'type' );
		restoreSquadRow( player_id, name, jersey, false, type );
	} );

	// Enter u edit polju == spremi
	$( document ).on( 'keydown', '.squad-edit-name, .squad-edit-jersey', function ( e ) {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			$( this ).closest( 'tr' ).find( '.btn-save-squad' ).trigger( 'click' );
		}
		if ( e.key === 'Escape' ) {
			$( this ).closest( 'tr' ).find( '.btn-cancel-squad' ).trigger( 'click' );
		}
	} );

	$( document ).on( 'click', '.btn-save-squad', function () {
		const $btn      = $( this ).prop( 'disabled', true );
		const player_id = $btn.data( 'player-id' );
		const $row      = $( '#squad-row-' + player_id );
		const name      = $row.find( '.squad-edit-name' ).val().trim();
		const jersey    = parseInt( $row.find( '.squad-edit-jersey' ).val() ) || 0;
		const type      = $row.find( '.squad-edit-type' ).val() === 'goalkeeper' ? 'goalkeeper' : 'player';

		if ( ! name ) { alert( 'Ime igrača ne može biti prazno.' ); $btn.prop( 'disabled', false ); return; }

		$.post( url, {
			action: 'kurs_update_squad_player',
			nonce, player_id,
			team_id: TEAM_ID, division_id: DIVISION_ID,
			jersey, name, type,
		} ).done( res => {
			if ( res.success ) {
				restoreSquadRow( player_id, res.data.name, res.data.jersey, true, res.data.type );
				// Ažuriraj ime u select listi (ako je igrač tamo)
				$( '#squad-player-select option[value="' + player_id + '"]' ).text( res.data.name );
			} else {
				if ( ! ( res.data && res.data.code === 'season_locked' ) ) alert( res.data || 'Greška.' );
				$btn.prop( 'disabled', false );
			}
		} );
	} );

	function restoreSquadRow( player_id, name, jersey, updateData, type ) {
		const $row          = $( '#squad-row-' + player_id );
		const jersey_display = jersey ? jersey : '&mdash;';
		if ( type === undefined ) type = $row.data( 'type' ) || 'player';

		if ( updateData ) {
			$row.data( 'name', name ).data( 'jersey', jersey ).data( 'type', type );
		}

		$row.find( '.squad-cell-jersey' ).html(
			`<span class="kurs-jersey">${jersey_display}</span>`
		);
		$row.find( '.squad-cell-name' ).html( `<strong>${h( name )}</strong>${playerTypeTag( type )}` );
		$row.find( '.squad-actions' ).html(
			`<button class="button button-small kurs-btn-edit-squad" data-player-id="${player_id}">Uredi</button> ` +
			`<button class="button button-small kurs-btn-rm-squad" data-player-id="${player_id}">&times;</button>`
		);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Helpers
	// ══════════════════════════════════════════════════════════════════════════

	function playerTypeTag( type ) {
		return type === 'goalkeeper'
			? ' <span class="kurs-pos-tag kurs-pos-tag--gk">GLM</span>'
			: '';
	}

	function appendSquadRow( data ) {
		const jersey_display = data.jersey ? data.jersey : '&mdash;';
		const type           = data.type === 'goalkeeper' ? 'goalkeeper' : 'player';
		const html =
			`<tr id="squad-row-${data.player_id}"
			     data-player-id="${data.player_id}"
			     data-team-id="${TEAM_ID}"
			     data-division-id="${DIVISION_ID}"
			     data-name="${h( data.player_name )}"
			     data-jersey="${data.jersey || 0}"
			     data-type="${type}">
			    <td class="squad-cell-jersey" style="text-align:center;">
			        <span class="kurs-jersey">${jersey_display}</span>
			    </td>
			    <td class="squad-cell-name">
			        <strong>${h( data.player_name )}</strong>${playerTypeTag( type )}
			    </td>
			    <td class="squad-actions" style="text-align:center;">
			        <button class="button button-small kurs-btn-edit-squad" data-player-id="${data.player_id}">Uredi</button>
			        <button class="button button-small kurs-btn-rm-squad"   data-player-id="${data.player_id}">&times;</button>
			    </td>
			</tr>`;

		$( '#kurs-squad-list' ).append( html );
		$( '#kurs-roster-table' ).show();
		$( '#squad-empty-msg' ).hide();
		updateSquadCount();
	}

	function updateSquadCount() {
		const n = $( '#kurs-squad-list tr' ).length;
		if ( n === 0 ) {
			$( '#kurs-roster-table' ).hide();
			$( '#squad-empty-msg' ).show();
		}
	}

	function refreshExistingPanel( addPlayer ) {
		// Ako su svi igrači u rosteru sakrijemo formu, inače je prikazujemo
		const squadIds = [];
		$( '#kurs-squad-list tr' ).each( function () {
			squadIds.push( parseInt( $( this ).data( 'player-id' ) ) );
		} );

		if ( addPlayer ) {
			// Igrač je uklonjen iz rostera → dodaj nazad u select
			const $sel = $( '#squad-player-select' );
			if ( $sel.length && ! $sel.find( `option[value="${addPlayer.id}"]` ).length ) {
				$sel.append( `<option value="${addPlayer.id}">${h( addPlayer.name )}</option>` );
				sortSelect( $sel );
			}
		}

		const $sel2 = $( '#squad-player-select' );
		const empty  = $sel2.length && $sel2.find( 'option' ).length <= 1;

		$( '#add-existing-form' ).toggle( ! empty );
		$( '#available-empty-msg' ).toggle( empty );
	}

	function sortSelect( $sel ) {
		const opts = $sel.find( 'option:not(:first)' ).sort( ( a, b ) =>
			$( a ).text().localeCompare( $( b ).text(), 'hr' )
		);
		$sel.find( 'option:not(:first)' ).remove();
		$sel.append( opts );
	}

	function clearNewPlayerInputs() {
		$( '#new-player-name' ).val( '' );
		$( '#new-player-jersey' ).val( '' );
		$( '#new-player-type' ).val( 'player' );
	}

	function showMsg( selector, msg, type ) {
		const $el = $( selector );
		$el.text( msg ).show();
		if ( type === 'warning' ) $el.css( 'color', '#856404' );
		else if ( type === 'ok' ) $el.css( 'color', '#1e6f26' );
		else $el.css( 'color', '' );
	}

	function h( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function escAttr( s ) {
		return String( s ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Utakmice: Generiraj raspored (svaka s svakom)
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '.kurs-btn-gen-fixtures', function () {
		const $btn     = $( this );
		const group_id = $btn.data( 'group-id' );
		const $msg     = $( '#gen-fixtures-msg' );

		if ( ! confirm( 'Generirati sve utakmice za ovu skupinu?\n\nSvaka ekipa igrat će s svakom drugom ekipom. Već postojeće utakmice neće biti duplicirane.' ) ) return;

		$btn.prop( 'disabled', true ).text( 'Generiram...' );
		$msg.text( '' ).hide();

		$.post( url, { action: 'kurs_generate_fixtures', nonce, group_id } ).done( res => {
			if ( res.success ) {
				const n = res.data.created;
				if ( n > 0 ) {
					$msg.text( '✓ Generirano ' + n + ' novih utakmica. Osvježi stranicu.' ).css( 'color', '#1e6f26' ).show();
					setTimeout( () => window.location.reload(), 1200 );
				} else {
					$msg.text( 'Sve utakmice već postoje.' ).css( 'color', '#856404' ).show();
					$btn.prop( 'disabled', false ).text( '↺ Generiraj sve utakmice (svaka s svakom)' );
				}
			} else {
				$msg.text( 'Greška: ' + ( res.data || 'Nepoznato' ) ).css( 'color', '#c00' ).show();
				$btn.prop( 'disabled', false ).text( '↺ Generiraj sve utakmice (svaka s svakom)' );
			}
		} );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Utakmice: Inline edit datuma i kola
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '.kurs-btn-edit-match', function () {
		const match_id = $( this ).data( 'match-id' );
		const $form    = $( '#match-edit-' + match_id );
		$form.slideToggle( 150 );
	} );

	$( document ).on( 'click', '.medit-cancel', function () {
		const match_id = $( this ).data( 'match-id' );
		$( '#match-edit-' + match_id ).slideUp( 150 );
	} );

	$( document ).on( 'click', '.medit-save', function () {
		const $btn     = $( this ).prop( 'disabled', true );
		const match_id = $btn.data( 'match-id' );
		const $form    = $( '#match-edit-' + match_id );
		const date     = $form.find( '.medit-date' ).val();
		const round    = $form.find( '.medit-round' ).val();

		$.post( url, { action: 'kurs_update_match', nonce, match_id, match_date: date, round } ).done( res => {
			if ( res.success ) {
				const $dtBox = $( '#match-date-' + match_id );
				if ( res.data.display_day ) {
					$dtBox.html(
						`<span class="kurs-match__dt-day">${h( res.data.display_day )}</span>` +
						`<span class="kurs-match__dt-time">${h( res.data.display_time )}</span>`
					);
				} else {
					$dtBox.html( '<span class="kurs-match__dt-none">—</span>' );
				}
				$form.slideUp( 150 );
				$btn.prop( 'disabled', false );
			} else {
				alert( 'Greška pri spremi.' );
				$btn.prop( 'disabled', false );
			}
		} );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Zapisnik utakmice (match-record stranica)
	// ══════════════════════════════════════════════════════════════════════════

	if ( typeof window.KursMatchId !== 'undefined' ) {

		const MATCH_ID  = window.KursMatchId;
		const HOME_TEAM = window.KursHomeTeamId;
		const AWAY_TEAM = window.KursAwayTeamId;
		const HOME_NAME = window.KursHomeName;
		const AWAY_NAME = window.KursAwayName;

		// Popuni select igrača prema odabranoj ekipi
		let SENT_OFF    = ( window.KursSentOff || [] ).map( Number );
		let CUR_HALF    = parseInt( window.KursCurHalf ) === 2 ? 2 : 1;
		const MAX_FOULS = parseInt( window.KursMaxFouls ) || 5;

		function squadFor( who )  { return ( who === 'away' ? window.KursAwaySquad : window.KursHomeSquad ) || []; }
		function teamIdFor( who ) { return who === 'away' ? AWAY_TEAM : HOME_TEAM; }
		function activeWho( $m )  { return $m.find( '.kurs-team-opt.is-active' ).data( 'team' ) || 'home'; }

		// Poluvrijeme odabrano u gol/karton modalu (1 ili 2)
		function modalHalf( $m )  { return parseInt( $m.find( '.kurs-half-opt.is-active' ).data( 'half' ) ) === 2 ? 2 : 1; }
		function setModalHalf( $m, half ) {
			const hv = parseInt( half ) === 2 ? 2 : 1;
			$m.find( '.kurs-half-opt' ).removeClass( 'is-active' );
			$m.find( '.kurs-half-opt[data-half="' + hv + '"]' ).addClass( 'is-active' );
		}

		// Popuni <select> igrača za modal, bez isključenih igrača
		function fillModalPlayers( prefix, who ) {
			const $sel = $( '#' + prefix + '-player' );
			$sel.empty().append( '<option value="">— Igrač —</option>' );
			squadFor( who ).forEach( p => {
				if ( SENT_OFF.indexOf( parseInt( p.id ) ) !== -1 ) return;
				const lbl = ( p.jersey ? '#' + p.jersey + ' ' : '' ) + p.name + ( p.gk ? ' (G)' : '' ) + ( p.cap ? ' (C)' : '' );
				$sel.append( `<option value="${p.id}">${h( lbl )}</option>` );
			} );
		}

		// Otvaranje / zatvaranje modala
		function openModal( id ) {
			const $m = $( '#kurs-modal-' + id );
			if ( ! $m.length ) return;
			$m.removeData( 'edit-event-id' ); // svjež unos (ne uređivanje)
			if ( id === 'gol' || id === 'karton' ) {
				$( '#' + id + '-player' ).val( '' );
				$( '#' + id + '-minute' ).val( '' );
				fillModalPlayers( id, activeWho( $m ) );
				setModalHalf( $m, CUR_HALF );
			}
			if ( id === 'prekrsaj' ) { setModalHalf( $m, CUR_HALF ); refreshPrekrsajModal(); }
			$m.removeAttr( 'hidden' );
		}
		function closeModal( $m ) { $m.attr( 'hidden', 'hidden' ); }

		$( document ).on( 'click', '[data-modal]', function () { openModal( $( this ).data( 'modal' ) ); } );
		$( document ).on( 'click', '.kurs-rec-modal [data-close], .kurs-rec-modal .kurs-modal-backdrop', function () {
			closeModal( $( this ).closest( '.kurs-rec-modal' ) );
		} );
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) $( '.kurs-rec-modal' ).attr( 'hidden', 'hidden' );
		} );

		// Prebacivanje momčadi unutar modala
		$( document ).on( 'click', '.kurs-team-opt', function () {
			const $opt = $( this ), $modal = $opt.closest( '.kurs-rec-modal' );
			$modal.find( '.kurs-team-opt' ).removeClass( 'is-active' );
			$opt.addClass( 'is-active' );
			const prefix = $modal.attr( 'id' ).replace( 'kurs-modal-', '' );
			if ( prefix === 'gol' || prefix === 'karton' ) fillModalPlayers( prefix, $opt.data( 'team' ) );
		} );

		// Prebacivanje poluvremena unutar gol/karton/prekršaj modala
		$( document ).on( 'click', '.kurs-half-opt', function () {
			const $opt = $( this );
			$opt.closest( '.kurs-half-toggle-modal' ).find( '.kurs-half-opt' ).removeClass( 'is-active' );
			$opt.addClass( 'is-active' );
			if ( $opt.closest( '#kurs-modal-prekrsaj' ).length ) refreshPrekrsajModal();
		} );

		// ── Dodaj event ──────────────────────────────────────────────────────

		$( document ).on( 'click', '#gol-submit',    function () { submitEvent( 'gol', this ); } );
		$( document ).on( 'click', '#karton-submit', function () { submitEvent( 'karton', this ); } );

		function submitEvent( prefix, btn ) {
			const $modal = $( '#kurs-modal-' + prefix );
			const $btn   = $( btn ).prop( 'disabled', true );
			const team_id    = teamIdFor( activeWho( $modal ) );
			const player_id  = parseInt( $( '#' + prefix + '-player' ).val() ) || '';
			const event_type = $( '#' + prefix + '-type' ).val();
			const minute     = parseInt( $( '#' + prefix + '-minute' ).val() ) || 0;
			const $err = $( '#' + prefix + '-err' );

			if ( prefix === 'gol' && ! player_id ) { $err.text( 'Odaberi strijelca — igrač je obavezan za gol.' ).removeAttr( 'hidden' ); $btn.prop( 'disabled', false ); return; }
			if ( ! minute ) { $err.text( 'Unesi minutu.' ).removeAttr( 'hidden' ); $btn.prop( 'disabled', false ); return; }
			$err.attr( 'hidden', 'hidden' );

			const editId = $modal.data( 'edit-event-id' ) || 0;
			const data   = { action: editId ? 'kurs_record_update_event' : 'kurs_record_add_event',
				nonce, match_id: MATCH_ID, team_id, player_id, event_type, minute, half: modalHalf( $modal ) };
			if ( editId ) data.event_id = editId;

			$.post( url, data )
				.done( res => {
					if ( res.success ) {
						CUR_HALF = modalHalf( $modal ); // zapamti zadnje korišteno poluvrijeme
						if ( editId ) updateMatchEventRow( res.data.event );
						else appendMatchEvent( res.data.event );
						applyState( res.data );
						$( '#' + prefix + '-minute' ).val( '' );
						$( '#' + prefix + '-player' ).val( '' );
						$modal.removeData( 'edit-event-id' );
						$( '#no-events-msg' ).hide();
						closeModal( $modal );
					} else {
						$err.text( res.data || 'Greška.' ).removeAttr( 'hidden' );
					}
				} ).always( () => $btn.prop( 'disabled', false ) );
		}

		// Akumulirani prekršaj — klik na momčad logira faul u trenutno poluvrijeme
		$( document ).on( 'click', '.kurs-prekrsaj-team', function () {
			const $modal  = $( '#kurs-modal-prekrsaj' );
			const team_id = teamIdFor( $( this ).data( 'team' ) );
			const half    = modalHalf( $modal );
			CUR_HALF = half; // zapamti zadnje korišteno poluvrijeme
			$.post( url, { action: 'kurs_record_add_event', nonce, match_id: MATCH_ID, team_id, event_type: 'foul', half } )
				.done( res => { if ( res.success ) { applyState( res.data ); refreshPrekrsajModal(); } } );
		} );

		// Ukloni zadnji prekršaj (undo misklika)
		$( document ).on( 'click', '.kurs-prekrsaj-minus', function () {
			const $modal  = $( '#kurs-modal-prekrsaj' );
			const team_id = teamIdFor( $( this ).data( 'team' ) );
			const half    = modalHalf( $modal );
			$.post( url, { action: 'kurs_record_del_foul', nonce, match_id: MATCH_ID, team_id, half } )
				.done( res => { if ( res.success ) { applyState( res.data ); refreshPrekrsajModal(); } } );
		} );

		// ── Uredi postojeći događaj (otvori modal popunjen) ───────────────────
		function updateMatchEventRow( ev ) {
			$( '#event-' + ev.id ).remove();   // ukloni stari red
			appendMatchEvent( ev );            // ponovo umetni (sortirano, u pravo poluvrijeme)
		}

		$( document ).on( 'click', '.kurs-edit-event', function () {
			const $btn   = $( this );
			const type   = $btn.data( 'type' );
			const prefix = ( type === 'goal' || type === 'penalty' || type === 'own_goal' ) ? 'gol' : 'karton';
			const $m     = $( '#kurs-modal-' + prefix );
			const teamId = parseInt( $btn.data( 'team-id' ) );
			const who    = teamId === AWAY_TEAM ? 'away' : 'home';

			$m.find( '.kurs-team-opt' ).removeClass( 'is-active' );
			$m.find( '.kurs-team-opt[data-team="' + who + '"]' ).addClass( 'is-active' );
			fillModalPlayers( prefix, who );
			$( '#' + prefix + '-player' ).val( String( $btn.data( 'player-id' ) || '' ) );
			$( '#' + prefix + '-type' ).val( type );
			$( '#' + prefix + '-minute' ).val( $btn.data( 'minute' ) || '' );
			setModalHalf( $m, parseInt( $btn.data( 'half' ) ) === 2 ? 2 : 1 );
			$( '#' + prefix + '-err' ).attr( 'hidden', 'hidden' );
			$m.data( 'edit-event-id', $btn.data( 'event-id' ) );
			$m.removeAttr( 'hidden' );
		} );

		// ── Postava: kapetan + prisutnost ─────────────────────────────────────
		function rebuildSquadFromTable( teamId ) {
			const $tbl = $( '.kurs-squad-table[data-team-id="' + teamId + '"]' );
			if ( ! $tbl.length ) return;
			const arr = [];
			$tbl.find( '.kurs-sq-row:not(.is-absent)' ).each( function () {
				const $r = $( this );
				arr.push( {
					id:     parseInt( $r.data( 'player-id' ) ),
					name:   $r.data( 'name' ),
					jersey: parseInt( $r.data( 'jersey' ) ) || 0,
					gk:     parseInt( $r.data( 'gk' ) ) === 1,
					cap:    ! $r.find( '.kurs-sq-badge--cap' ).prop( 'hidden' ),
				} );
			} );
			if ( teamId === HOME_TEAM ) window.KursHomeSquad = arr;
			else if ( teamId === AWAY_TEAM ) window.KursAwaySquad = arr;
		}

		$( document ).on( 'click', '.kurs-sq-cap', function () {
			const $row    = $( this ).closest( '.kurs-sq-row' );
			const team_id = parseInt( $row.data( 'team-id' ) );
			const pid     = parseInt( $row.data( 'player-id' ) );
			$.post( url, { action: 'kurs_record_set_captain', nonce, match_id: MATCH_ID, team_id, player_id: pid } ).done( res => {
				if ( res.success ) {
					$( '.kurs-squad-table[data-team-id="' + team_id + '"] .kurs-sq-badge--cap' ).attr( 'hidden', 'hidden' );
					$row.find( '.kurs-sq-badge--cap' ).removeAttr( 'hidden' );
					rebuildSquadFromTable( team_id );
				}
			} );
		} );

		$( document ).on( 'click', '.kurs-sq-absent', function () {
			const $row       = $( this ).closest( '.kurs-sq-row' );
			const team_id    = parseInt( $row.data( 'team-id' ) );
			const pid        = parseInt( $row.data( 'player-id' ) );
			const willAbsent = ! $row.hasClass( 'is-absent' );
			$.post( url, { action: 'kurs_record_set_absent', nonce, match_id: MATCH_ID, team_id, player_id: pid, absent: willAbsent ? 1 : 0 } ).done( res => {
				if ( res.success ) {
					$row.toggleClass( 'is-absent', willAbsent );
					rebuildSquadFromTable( team_id );
				}
			} );
		} );

		// ── Suci (2 po utakmici) — spremi/zaključaj + otključaj + autocomplete ──
		function lockRefs( locked ) {
			$( '#ref-1, #ref-2' ).prop( 'readonly', locked );
			$( '#ref-save' ).prop( 'hidden', locked );
			$( '#ref-unlock' ).prop( 'hidden', ! locked );
		}
		$( document ).on( 'click', '#ref-save', function () {
			const $btn = $( this ).prop( 'disabled', true );
			$.post( url, { action: 'kurs_record_set_referees', nonce, match_id: MATCH_ID, referee1: $( '#ref-1' ).val(), referee2: $( '#ref-2' ).val() } )
				.done( res => {
					if ( res.success ) {
						lockRefs( true ); // zaključaj polja nakon spremanja
						const $m = $( '#ref-msg' ).removeAttr( 'hidden' );
						setTimeout( () => $m.attr( 'hidden', 'hidden' ), 2000 );
						[ res.data.referee1, res.data.referee2 ].forEach( function ( n ) {
							if ( n && ! $( '#kurs-ref-list option' ).filter( function () { return this.value === n; } ).length ) {
								$( '<option>' ).val( n ).appendTo( '#kurs-ref-list' );
							}
						} );
					}
				} ).always( () => $btn.prop( 'disabled', false ) );
		} );
		$( document ).on( 'click', '#ref-unlock', function () { lockRefs( false ); $( '#ref-1' ).trigger( 'focus' ); } );

		// Primijeni stanje s servera (rezultat, akumulirani prekršaji, isključeni)
		function applyState( data ) {
			if ( typeof data.home_score !== 'undefined' ) {
				$( '#live-home' ).text( data.home_score );
				$( '#live-away' ).text( data.away_score );
				syncFinishScore( data.home_score, data.away_score );
			}
			if ( data.foul_counts ) updateFoulsUI( data.foul_counts );
			if ( data.sent_off ) { SENT_OFF = data.sent_off.map( Number ); refreshOpenModalPlayers(); }
		}

		function updateFoulsUI( counts ) {
			window.KursFoulCounts = counts;
			$( '#kurs-fouls .kurs-foul-team' ).each( function () {
				const tid = parseInt( $( this ).data( 'team' ) );
				const tc  = counts[ tid ] || counts[ String( tid ) ] || { 1: 0, 2: 0 };
				$( this ).find( '.kurs-foul-cell' ).each( function () {
					const hh = parseInt( $( this ).data( 'half' ) );
					const n  = parseInt( tc[ hh ] || tc[ String( hh ) ] || 0 );
					$( this ).attr( 'data-count', n )
						.toggleClass( 'is-warn',  n === MAX_FOULS )
						.toggleClass( 'is-penal', n >  MAX_FOULS )
						.find( '.kurs-foul-cell__n' ).text( n );
				} );
			} );
		}

		function refreshPrekrsajModal() {
			const half   = modalHalf( $( '#kurs-modal-prekrsaj' ) );
			const counts = window.KursFoulCounts || {};
			[ 'home', 'away' ].forEach( who => {
				const tid = teamIdFor( who );
				const tc  = counts[ tid ] || counts[ String( tid ) ] || { 1: 0, 2: 0 };
				$( '#prekrsaj-cnt-' + who ).text( parseInt( tc[ half ] || tc[ String( half ) ] || 0 ) );
			} );
		}

		function refreshOpenModalPlayers() {
			$( '.kurs-rec-modal:not([hidden])' ).each( function () {
				const prefix = $( this ).attr( 'id' ).replace( 'kurs-modal-', '' );
				if ( prefix === 'gol' || prefix === 'karton' ) fillModalPlayers( prefix, activeWho( $( this ) ) );
			} );
		}

		// (Uklonjen stari "Kraj 1. poluvremena" toggle — poluvrijeme se sada bira
		//  u svakom modalu i pamti se zadnje korišteno preko CUR_HALF.)

		// ── Obriši event ─────────────────────────────────────────────────────

		$( document ).on( 'click', '.kurs-del-event', function () {
			if ( ! confirm( 'Obriši ovaj događaj?' ) ) return;
			const event_id = $( this ).data( 'event-id' );

			$.post( url, { action: 'kurs_record_del_event', nonce, event_id } ).done( res => {
				if ( res.success ) {
					$( '#event-' + event_id ).remove();
					applyState( res.data );
					// Sakrij grupe poluvremena koje su ostale bez događaja
					$( '.kurs-half-group' ).each( function () {
						if ( ! $( this ).find( '.kurs-event-row' ).length ) $( this ).attr( 'hidden', 'hidden' );
					} );
					if ( ! $( '#kurs-events-list .kurs-event-row' ).length ) {
						$( '#no-events-msg' ).show();
					}
				}
			} );
		} );

		// ── Auto-sync rezultata iz golova ────────────────────────────────────────

		function syncFinishScore( home, away ) {
			const $h = $( '#finish-home' );
			const $a = $( '#finish-away' );
			/* Ažuriraj samo ako je u auto-modu (readonly) */
			if ( $h.prop( 'readonly' ) ) {
				$h.val( home ).addClass( 'kurs-si--updated' );
				$a.val( away ).addClass( 'kurs-si--updated' );
				setTimeout( () => {
					$h.removeClass( 'kurs-si--updated' );
					$a.removeClass( 'kurs-si--updated' );
				}, 800 );
			}
		}

		/* Ručni unos toggle */
		$( document ).on( 'click', '#finish-manual-toggle', function () {
			const $h  = $( '#finish-home' );
			const $a  = $( '#finish-away' );
			const $lbl = $( '#finish-auto-label' );
			const isAuto = $h.prop( 'readonly' );

			if ( isAuto ) {
				$h.prop( 'readonly', false ).focus();
				$a.prop( 'readonly', false );
				$( this ).text( 'Vrati na auto' ).addClass( 'kurs-btn--red' ).removeClass( 'kurs-btn--ghost' );
				$lbl.html( '&#9888; Ručni unos — rezultat se ne računa automatski' ).addClass( 'kurs-finish-auto-label--manual' );
			} else {
				$h.prop( 'readonly', true );
				$a.prop( 'readonly', true );
				$( this ).text( 'Ručni unos' ).removeClass( 'kurs-btn--red' ).addClass( 'kurs-btn--ghost' );
				$lbl.html( '&#9679; Rezultat se automatski računa iz unesenih golova' ).removeClass( 'kurs-finish-auto-label--manual' );
				/* Sinkronizira na trenutni live score */
				syncFinishScore(
					parseInt( $( '#live-home' ).text() ) || 0,
					parseInt( $( '#live-away' ).text() ) || 0
				);
			}
		} );

		// ── PDF download ─────────────────────────────────────────────────────

		/* #btn-download-pdf je sada izravan <a href> link (target=_blank) na PDF
		   prikaz koji se sam otvori u dijalogu za ispis → "Spremi kao PDF".
		   Bez window.open jer ga blokatori skočnih prozora znaju blokirati. */

		// ── Spremi datum i kolo ──────────────────────────────────────────────

		$( document ).on( 'click', '#btn-save-match-info', function () {
			const $btn  = $( this ).prop( 'disabled', true );
			const date  = $( '#rec-date' ).val();
			const round = $( '#rec-round' ).val();
			const $msg  = $( '#save-match-msg' );

			$.post( url, { action: 'kurs_update_match', nonce, match_id: MATCH_ID, match_date: date, round } ).done( res => {
				if ( res.success ) {
					const day = res.data.display_day, time = res.data.display_time;
					$( '#record-date-display' ).html( day
						? `<span class="kurs-match__dt-day">${h(day)}</span><span class="kurs-record-info__sep">/</span><span class="kurs-match__dt-time">${h(time)}</span>`
						: '<span class="kurs-match__dt-none">Datum nije postavljen</span>'
					);
					$msg.text( '✓ Spremljeno.' ).css( 'color', '#1e6f26' ).show();
					setTimeout( () => $msg.fadeOut(), 2500 );
				} else {
					$msg.text( 'Greška pri spremi.' ).css( 'color', '#c00' ).show();
				}
			} ).always( () => $btn.prop( 'disabled', false ) );
		} );

		// ── Helper: dodaj event red u listu ─────────────────────────────────

		function appendMatchEvent( ev ) {
			if ( ev.event_type === 'foul' ) return; // prekršaji se ne prikazuju u tijeku
			const isHome = parseInt( ev.team_id ) === HOME_TEAM;
			const half   = parseInt( ev.half ) === 2 ? 2 : 1;
			const icons  = { goal: '⚽', penalty: '⚽', own_goal: '⚽', yellow_card: '🟨', second_yellow: '🟨🟥', red_card: '🟥' };
			const tags   = { penalty: '(P)', own_goal: '(AG)' };
			const icon   = icons[ ev.event_type ] || '⚽';
			const tag    = tags[ ev.event_type ] ? ' <span class="kurs-event__og">' + tags[ ev.event_type ] + '</span>' : '';
			const tname  = isHome ? HOME_NAME : AWAY_NAME;
			const css    = isHome ? 'kurs-event--home' : 'kurs-event--away';

			const html =
				`<div class="kurs-event-row ${css}" id="event-${ev.id}" data-event-id="${ev.id}" data-half="${half}">` +
				`<span class="kurs-event__min">${ev.minute}'</span>` +
				`<span class="kurs-event__icon">${icon}</span>` +
				`<span class="kurs-event__name">${h( ev.player_name || '—' )}${tag}</span>` +
				`<span class="kurs-event__team">${h( tname )}</span>` +
				`<button class="kurs-edit-event no-print" title="Uredi" data-event-id="${ev.id}" data-type="${ev.event_type}" data-team-id="${ev.team_id}" data-player-id="${ev.player_id || ''}" data-minute="${ev.minute || ''}" data-half="${half}">&#9998;</button>` +
				`<button class="kurs-del-event no-print" data-event-id="${ev.id}" title="Obriši">&times;</button>` +
				`</div>`;

			// Prikaži grupu poluvremena i umetni red sortiran po minuti unutar nje
			$( '#kurs-half-group-' + half ).removeAttr( 'hidden' );
			const $container = $( '#kurs-events-half-' + half );
			let inserted = false;
			$container.find( '.kurs-event-row' ).each( function () {
				const m = parseInt( $( this ).find( '.kurs-event__min' ).text() );
				if ( ev.minute < m ) { $( this ).before( html ); inserted = true; return false; }
			} );
			if ( ! inserted ) $container.append( html );
			$( '#no-events-msg' ).hide();
		}
	}

} );
