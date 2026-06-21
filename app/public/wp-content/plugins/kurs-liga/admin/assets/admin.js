/* KURS Liga — Admin JS v1.2 */
jQuery( function ( $ ) {
	const url   = KursAjax.url;
	const nonce = KursAjax.nonce;

	// Kontekst stranice (team_id + division_id iz roster stranice)
	const $ctx       = $( '#kurs-roster-ctx' );
	const TEAM_ID     = $ctx.length ? parseInt( $ctx.data( 'team-id' ) )     : 0;
	const DIVISION_ID = $ctx.length ? parseInt( $ctx.data( 'division-id' ) ) : 0;

	// ══════════════════════════════════════════════════════════════════════════
	// Liga setup: Grupe
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '.js-add-group', function ( e ) {
		e.preventDefault();
		const $mgr     = $( this ).closest( '.kurs-groups-mgr' );
		const phase_id = $mgr.data( 'phase-id' );
		const $inp     = $mgr.find( '.js-group-name' );
		const name     = $inp.val().trim();
		if ( ! name ) { $inp.focus(); return; }

		const $btn = $( this ).prop( 'disabled', true ).text( '...' );
		$.post( url, { action: 'kurs_add_group', nonce, phase_id, name } ).done( res => {
			if ( res.success ) {
				$mgr.find( '.kurs-chips' ).append(
					`<span class="kurs-chip"><span>${h( res.data.name )}</span>` +
					`<button class="kurs-chip__del" data-group-id="${res.data.id}">&times;</button></span>`
				);
				$inp.val( '' ).focus();
			}
		} ).always( () => $btn.prop( 'disabled', false ).text( '+ Dodaj grupu' ) );
	} );

	$( document ).on( 'click', '.kurs-chip__del', function () {
		const $chip    = $( this ).closest( '.kurs-chip' );
		const group_id = $( this ).data( 'group-id' );
		if ( ! confirm( 'Obriši grupu i sve utakmice u njoj?' ) ) return;
		$.post( url, { action: 'kurs_del_group', nonce, group_id } ).done( res => {
			if ( res.success ) $chip.remove();
		} );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Liga setup: Dodjela ekipa grupama
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '.kurs-btn-add-team', function () {
		const $col     = $( this ).closest( '.kurs-group-col' );
		const group_id = $col.data( 'group-id' );
		const $hidden  = $col.find( '.kurs-team-selected-id' );
		const team_id  = $hidden.val();

		if ( ! team_id ) {
			$col.find( '.kurs-team-search' ).focus();
			return;
		}

		const $btn = $( this ).prop( 'disabled', true );
		$.post( url, { action: 'kurs_assign_team', nonce, group_id, team_id } ).done( res => {
			if ( res.success ) {
				$col.find( '.kurs-group-col__teams' ).append(
					`<div class="kurs-group-team" data-team-id="${res.data.team_id}">` +
					`<span>${h( res.data.team_name )}</span>` +
					`<button class="kurs-btn-rm" data-group-id="${group_id}" data-team-id="${res.data.team_id}">&times;</button>` +
					`</div>`
				);
				updateGroupCount( $col );
				// Resetiraj autocomplete polja
				$col.find( '.kurs-team-search' ).val( '' );
				$hidden.val( '' );
				closeTeamAC();
			} else {
				alert( 'Ekipa je već u ovoj grupi.' );
			}
		} ).always( () => $btn.prop( 'disabled', false ) );
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
		const $wrap  = $( this ).closest( '.kurs-team-ac-wrap' );
		const $list  = $wrap.find( '.kurs-team-ac-list:visible' );
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

	// Klik na stavku u team autocomplete
	$( document ).on( 'click', '.kurs-team-ac-item', function () {
		const $item   = $( this );
		const $wrap   = $item.closest( '.kurs-team-ac-wrap' );
		const $add    = $item.closest( '.kurs-group-col__add' );
		const team_id = $item.data( 'team-id' );
		const name    = $item.data( 'name' );

		$add.find( '.kurs-team-search' ).val( name );
		$add.find( '.kurs-team-selected-id' ).val( team_id );
		closeTeamAC( $wrap );
	} );

	// Klik van autocomplete liste → zatvori
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.kurs-team-ac-wrap' ).length ) {
			closeTeamAC();
		}
	} );

	function renderTeamAC( teams, $wrap ) {
		let $list = $wrap.find( '.kurs-team-ac-list' );
		if ( ! $list.length ) {
			$list = $( '<div class="kurs-team-ac-list"></div>' ).appendTo( $wrap );
		}
		$list.empty();
		teams.forEach( t => {
			$( `<div class="kurs-team-ac-item" data-team-id="${t.id}" data-name="${h( t.name )}">` +
			   `<span>${h( t.name )}</span>` +
			   `</div>` ).appendTo( $list );
		} );
		$list.show();
	}

	function closeTeamAC( $wrap ) {
		if ( $wrap ) {
			$wrap.find( '.kurs-team-ac-list' ).hide().empty();
		} else {
			$( '.kurs-team-ac-list' ).hide().empty();
		}
	}

	$( document ).on( 'click', '.kurs-btn-rm', function () {
		const $row     = $( this ).closest( '.kurs-group-team' );
		const $col     = $( this ).closest( '.kurs-group-col' );
		const group_id = $( this ).data( 'group-id' );
		const team_id  = $( this ).data( 'team-id' );
		$.post( url, { action: 'kurs_remove_team', nonce, group_id, team_id } ).done( res => {
			if ( res.success ) { $row.remove(); updateGroupCount( $col ); }
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

				// Dodaj u pool prikaz
				const initials = team_name.substring( 0, 2 ).toUpperCase();
				$( '#kurs-team-pool-list' ).append(
					`<div class="kurs-team-pill" data-team-id="${team_id}">` +
					`<span class="kurs-avatar">${h( initials )}</span>` +
					`<span>${h( team_name )}</span>` +
					`</div>`
				);

				// Dodaj u window.KursTeams da autocomplete odmah vidi novu ekipu
				if ( typeof window.KursTeams !== 'undefined' ) {
					window.KursTeams.push( { id: team_id, name: team_name } );
					window.KursTeams.sort( ( a, b ) => a.name.localeCompare( b.name, 'hr' ) );
				}

				// Ažuriraj brojač u naslovu poola
				const currentCount = parseInt( $( '#pool-count' ).text() ) || 0;
				$( '#pool-count' ).text( '(' + ( currentCount + 1 ) + ')' );

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
	// Logo ekipe — WordPress Media Library modal
	// ══════════════════════════════════════════════════════════════════════════

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
					alert( res.data || 'Greška.' );
				}
			} ).always( () => $btn.prop( 'disabled', false ) );
	} );

	// ══════════════════════════════════════════════════════════════════════════
	// Roster: Kreiraj novog igrača + check duplikata
	// ══════════════════════════════════════════════════════════════════════════

	$( document ).on( 'click', '#btn-quick-add', function () {
		const name   = $( '#new-player-name' ).val().trim();
		const jersey = parseInt( $( '#new-player-jersey' ).val() ) || 0;

		if ( ! name ) { showMsg( '#quick-add-msg', 'Unesi ime igrača.' ); return; }

		closeAC();

		// Provjera duplikata
		$.post( url, { action: 'kurs_search_players', nonce, term: name } ).done( res => {
			if ( ! res.success ) { doCreatePlayer( name, jersey ); return; }

			const exact = res.data.find( p => p.name.toLowerCase() === name.toLowerCase() );
			if ( exact ) {
				showDupModal( exact, name, jersey );
			} else {
				doCreatePlayer( name, jersey );
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
		doCreatePlayer( d.name, d.jersey );
	} );

	$( document ).on( 'click', '#btn-dup-cancel, #kurs-dup-backdrop', closeDupModal );

	function showDupModal( exact, name, jersey ) {
		$( '#kurs-dup-msg' ).html(
			`Igrač <strong>${h( exact.name )}</strong> već postoji u bazi igrača.`
		);
		$( '#kurs-dup-modal' )
			.data( 'payload', { player_id: exact.id, player_name: exact.name, name, jersey } )
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

	function doCreatePlayer( name, jersey ) {
		showMsg( '#quick-add-msg', 'Kreiranje...' );
		$.post( url, { action: 'kurs_quick_add_player', nonce, name, team_id: TEAM_ID, division_id: DIVISION_ID, jersey } )
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

		$row.find( '.squad-cell-jersey' ).html(
			`<input type="number" class="small-text squad-edit-jersey" value="${jersey}" min="0" max="99" style="width:52px;">`
		);
		$row.find( '.squad-cell-name' ).html(
			`<input type="text" class="regular-text squad-edit-name" value="${escAttr( name )}" style="max-width:190px;">`
		);
		$row.find( '.squad-actions' ).html(
			`<button class="button button-primary button-small btn-save-squad" data-player-id="${player_id}">Spremi</button> ` +
			`<button class="button button-small btn-cancel-squad" data-player-id="${player_id}" ` +
			`data-name="${escAttr( name )}" data-jersey="${ $row.data( 'jersey' ) || 0 }">Odustani</button>`
		);
		$row.find( '.squad-edit-name' ).focus().select();
	} );

	$( document ).on( 'click', '.btn-cancel-squad', function () {
		const player_id = $( this ).data( 'player-id' );
		const name      = $( this ).data( 'name' );
		const jersey    = $( this ).data( 'jersey' );
		restoreSquadRow( player_id, name, jersey );
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

		if ( ! name ) { alert( 'Ime igrača ne može biti prazno.' ); $btn.prop( 'disabled', false ); return; }

		$.post( url, {
			action: 'kurs_update_squad_player',
			nonce, player_id,
			team_id: TEAM_ID, division_id: DIVISION_ID,
			jersey, name,
		} ).done( res => {
			if ( res.success ) {
				restoreSquadRow( player_id, res.data.name, res.data.jersey, true );
				// Ažuriraj ime u select listi (ako je igrač tamo)
				$( '#squad-player-select option[value="' + player_id + '"]' ).text( res.data.name );
			} else {
				alert( res.data || 'Greška.' );
				$btn.prop( 'disabled', false );
			}
		} );
	} );

	function restoreSquadRow( player_id, name, jersey, updateData ) {
		const $row          = $( '#squad-row-' + player_id );
		const jersey_display = jersey ? jersey : '&mdash;';

		if ( updateData ) {
			$row.data( 'name', name ).data( 'jersey', jersey );
		}

		$row.find( '.squad-cell-jersey' ).html(
			`<span class="kurs-jersey">${jersey_display}</span>`
		);
		$row.find( '.squad-cell-name' ).html( `<strong>${h( name )}</strong>` );
		$row.find( '.squad-actions' ).html(
			`<button class="button button-small kurs-btn-edit-squad" data-player-id="${player_id}">Uredi</button> ` +
			`<button class="button button-small kurs-btn-rm-squad" data-player-id="${player_id}">&times;</button>`
		);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Helpers
	// ══════════════════════════════════════════════════════════════════════════

	function appendSquadRow( data ) {
		const jersey_display = data.jersey ? data.jersey : '&mdash;';
		const html =
			`<tr id="squad-row-${data.player_id}"
			     data-player-id="${data.player_id}"
			     data-team-id="${TEAM_ID}"
			     data-division-id="${DIVISION_ID}"
			     data-name="${h( data.player_name )}"
			     data-jersey="${data.jersey || 0}">
			    <td class="squad-cell-jersey" style="text-align:center;">
			        <span class="kurs-jersey">${jersey_display}</span>
			    </td>
			    <td class="squad-cell-name">
			        <strong>${h( data.player_name )}</strong>
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
		$( '#squad-count-label' ).text( n + ' ' + ( n === 1 ? 'igrač' : 'igrača' ) );
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
} );
