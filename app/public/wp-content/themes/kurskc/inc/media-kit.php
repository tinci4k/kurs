<?php
defined( 'ABSPATH' ) || exit;

/**
 * „Za medije" — paket rezultata jednog kola (rezultati sa strijelcima, ljestvica,
 * strijelci) koji drugi portali kopiraju u svoje objave, s KURS brandingom.
 *
 * Podaci su isti kao u „Po kolima" (Kurs_Rounds). HTML je namjerno „e-mail stila":
 *   - samo inline stilovi i tablice — WordPress (kses) reže <style>, <script>,
 *     <svg>, data: URI-je i dio CSS svojstava kod korisnika bez unfiltered_html;
 *   - hex boje (rgba() kses ne propušta), apsolutni URL-ovi slika i poveznica;
 *   - sve u JEDNOM redu i bez komentara — klasični uređivač (wpautop) bi inače
 *     ubacio <p>/<br> između redaka.
 * Ide u blok „Prilagođeni HTML" (Gutenberg) ili karticu „Tekst" (klasični uređivač).
 */
class Kurskc_Media_Kit {

	const DARK   = '#111317';
	const PAPER  = '#ffffff';
	const STRIPE = '#f7f7f1';
	const LINE   = '#ecece4';
	const MUTED  = '#7d7f87';
	const GOLD   = '#FDE206';
	const WIN    = '#1F7A43';
	const KC     = '#DA0F1E';
	const LBB    = '#B07A1B';
	const PAN    = '#F47B16';

	// Bez navodnika — kses odbacuje font-family s navodnicima (višerječna imena su i ovako ispravan CSS).
	// Portal nema Barlow pa pada na Arial Narrow/Segoe UI/Consolas; PNG (naša stranica) ima prave fontove.
	const F_HEAD = 'Barlow Semi Condensed,Arial Narrow,Arial,sans-serif';
	const F_BODY = 'Barlow,Segoe UI,Roboto,Helvetica,Arial,sans-serif';
	const F_MONO = 'Barlow,Segoe UI,Roboto,Helvetica,Arial,sans-serif'; // 1.0.54: bez Space Mono

	const PO_LABELS = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale' ];

	/* ── Podaci ──────────────────────────────────────────────────────────── */

	/** Boja lige: KC crvena, Liga bez briga brončana, Pan narančasta. */
	public static function accent( string $division_name, bool $is_pan ): string {
		if ( $is_pan ) return self::PAN;
		return stripos( $division_name, 'briga' ) !== false ? self::LBB : self::KC;
	}

	/** „KC Liga" → „KC lige" (naslov „Rezultati 9. kola KC lige"). */
	public static function genitive( string $name ): string {
		$map = [ 'kc liga' => 'KC lige', 'liga bez briga' => 'Lige bez briga', 'pan ljetna liga' => 'Pan ljetne lige' ];
		return $map[ mb_strtolower( trim( $name ) ) ] ?? $name;
	}

	/** Kola sezone (unija svih liga): [ kolo => [ 'total', 'finished', 'date' ] ]. */
	public static function season_rounds( array $divisions ): array {
		$out = [];
		foreach ( $divisions as $d ) {
			foreach ( Kurs_Rounds::round_status( (int) $d->id ) as $r => $st ) {
				$out[ $r ]['total']    = ( $out[ $r ]['total'] ?? 0 ) + $st['total'];
				$out[ $r ]['finished'] = ( $out[ $r ]['finished'] ?? 0 ) + $st['finished'];
				$out[ $r ]['date']     ??= Kurs_Rounds::date_label( Kurs_Rounds::matches( (int) $d->id, $r ) );
			}
		}
		ksort( $out );
		return $out;
	}

	/** Ima li playoff odigranu utakmicu (za zadani odabir „Playoff"). */
	public static function playoff_started( int $division_id ): bool {
		foreach ( Kurs_Playoff::get_bracket( $division_id ) as $round ) {
			foreach ( $round as $m ) if ( $m->status === 'finished' ) return true;
		}
		return false;
	}

	/** Sve što paket treba za jednu ligu i kolo (ili 'playoff'). */
	public static function division_data( object $division, $round, bool $is_pan ): array {
		$did = (int) $division->id;
		$d   = [
			'did'    => $did,
			'name'   => $division->name,
			'accent' => self::accent( $division->name, $is_pan ),
			'spots'  => ! empty( $division->has_playoff ),
			'url'    => kurs_league_url( $did, [ 'tab' => 'kola', 'kolo' => $round ] ) . '#kola',
		];

		if ( $round === 'playoff' ) {
			$d['playoff'] = [];
			foreach ( Kurs_Playoff::get_bracket( $did ) as $r => $slots ) {
				if ( ! isset( self::PO_LABELS[ $r ] ) ) continue;
				ksort( $slots );
				$list = array_values( $slots );
				$objs = array_values( array_filter( array_map( fn( $m ) => $m->match_id && $m->status === 'finished'
					? (object) [ 'id' => (int) $m->match_id, 'home_team_id' => (int) $m->home_team_id ] : null, $list ) ) );
				$d['playoff'][ $r ] = [ 'matches' => $list, 'goals' => Kurs_Rounds::goals_by_match( $did, $objs ) ];
			}
			$final = $d['playoff']['final']['matches'][0] ?? null;
			$side = $final ? Kurs_Playoff::winner_side( $final ) : null;
			if ( $side ) $d['champion'] = $side === 'home' ? $final->home_name : $final->away_name;
			$all = $d['playoff'] ? array_merge( ...array_values( array_map( fn( $x ) => $x['matches'], $d['playoff'] ) ) ) : [];
			$d['date']     = Kurs_Rounds::date_label( $all );
			$d['finished'] = count( array_filter( $all, fn( $m ) => $m->status === 'finished' ) );
			return $d;
		}

		$matches       = Kurs_Rounds::matches( $did, (int) $round );
		$d['matches']  = $matches;
		$d['goals']    = Kurs_Rounds::goals_by_match( $did, $matches );
		$d['byes']     = Kurs_Rounds::byes( $matches );
		$d['date']     = Kurs_Rounds::date_label( $matches );
		$d['finished'] = count( array_filter( $matches, fn( $m ) => $m->status === 'finished' ) );
		$d['tables']   = $d['finished'] ? Kurs_Rounds::standings( $did, (int) $round ) : [];
		$d['scorers']  = $d['finished'] ? Kurs_Rounds::scorers( $did, (int) $round ) : [ 'groups' => [], 'own' => [] ];
		return $d;
	}

	/* ── HTML ────────────────────────────────────────────────────────────── */

	private static function e( $s ): string { return esc_html( (string) $s ); }

	/* Poveznice (1.0.57): rezultat/sat → stranica utakmice, ekipa → profil ekipe, strijelac → profil igrača.
	   Inline stilovi da portal ne oboji/podcrta tekst svojim stilom poveznica. */
	private static function a( string $inner, string $url, string $title = '' ): string {
		if ( ! $url ) return $inner;
		return '<a href="' . esc_url( $url ) . '"' . ( $title ? ' title="' . esc_attr( $title ) . '"' : '' ) . ' style="color:inherit;text-decoration:none;border:0;box-shadow:none;background:none;">' . $inner . '</a>';
	}
	private static function match_url( int $id ): string { return $id ? add_query_arg( 'match_id', $id, home_url( '/utakmica/' ) ) : ''; }
	private static function team_url( int $id, int $did = 0 ): string { return $id && function_exists( 'kurs_team_url' ) && get_post_status( $id ) === 'publish' ? kurs_team_url( $id, $did ) : ''; }
	private static function player_url( int $id ): string { return $id && get_post_status( $id ) === 'publish' ? (string) get_permalink( $id ) : ''; }
	/** Strijelci utakmice s poveznicama na igrače („Ranilović 3, Rac 2"). */
	private static function scorers_links( array $list ): string {
		return implode( ', ', array_map( fn( $sc ) => self::a( self::e( $sc['name'] ), self::player_url( (int) ( $sc['pid'] ?? 0 ) ) ) . ( $sc['goals'] > 1 ? ' ' . (int) $sc['goals'] : '' ) . ( ! empty( $sc['own'] ) ? ' (ag)' : '' ), $list ) );
	}

	/** Tablica bez okvira/razmaka — štiti od stilova tablica s portala. */
	private static function table( string $inner, string $style = '' ): string {
		return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;border:0;margin:0;padding:0;background:transparent;' . $style . '">' . $inner . '</table>';
	}

	private static function td( string $inner, string $style = '', string $attr = '' ): string {
		return '<td' . $attr . ' style="border:0;margin:0;' . $style . '">' . $inner . '</td>';
	}

	/**
	 * „Pilula" (rezultat, broj golova) kao blok fiksne širine i visine (height =
	 * line-height) — najpredvidljivije u tuđim temama; inline span s paddingom
	 * ovisi o line-heightu portala.
	 */
	private static function pill( string $text, string $bg, string $fg, int $size, int $w, int $h, bool $center = true ): string {
		return '<div style="width:' . $w . 'px;height:' . $h . 'px;line-height:' . $h . 'px;margin:' . ( $center ? '0 auto' : '0' ) . ';padding:0;background:' . $bg . ';color:' . $fg
			. ';font-family:' . self::F_HEAD . ';font-weight:800;font-size:' . $size . 'px;text-align:center;white-space:nowrap;overflow:hidden;">' . self::e( $text ) . '</div>';
	}

	private static function heading( string $text, string $accent, string $sub = '' ): string {
		return '<div style="border-left:5px solid ' . $accent . ';padding:1px 0 1px 11px;margin:0 0 12px;">'
			. '<div style="font-family:' . self::F_HEAD . ';font-weight:800;font-style:italic;font-size:21px;line-height:1.1;text-transform:uppercase;color:' . self::DARK . ';letter-spacing:-0.2px;">' . self::e( $text ) . '</div>'
			. ( $sub ? '<div style="font-family:' . self::F_MONO . ';font-size:11px;line-height:1.4;color:' . self::MUTED . ';margin-top:3px;">' . self::e( $sub ) . '</div>' : '' )
			. '</div>';
	}

	private static function subheading( string $text ): string {
		return '<div style="font-family:' . self::F_HEAD . ';font-weight:800;font-style:italic;font-size:15px;line-height:1.2;text-transform:uppercase;color:' . self::DARK . ';margin:20px 0 8px;">' . self::e( $text ) . '</div>';
	}

	private static function note( string $text ): string {
		return '<div style="font-family:' . self::F_MONO . ';font-size:10.5px;line-height:1.5;color:' . self::MUTED . ';margin:6px 0 0;">' . self::e( $text ) . '</div>';
	}

	/** Jedna utakmica: ekipe + rezultat, ispod strijelci domaćina / gosta. */
	private static function match_rows( string $home, string $away, ?string $score, string $hs, string $as, string $note, string $murl = '', string $sub = '' ): string {
		$line = 'border-top:1px solid ' . self::LINE . ';';
		$team = 'width:42%;font-family:' . self::F_BODY . ';font-weight:700;font-size:15px;line-height:1.25;color:' . self::DARK . ';vertical-align:middle;';
		$mid  = $score !== null
			? self::pill( $score, self::DARK, '#ffffff', 18, 68, 32 )
				// ispod rezultata: penali u doigravanju (1.0.62) — „pen. 4:5"
				. ( $sub ? '<span style="display:block;margin-top:4px;font-family:' . self::F_MONO . ';font-size:11.5px;font-weight:700;letter-spacing:.02em;color:' . self::DARK . ';white-space:nowrap;">' . self::e( $sub ) . '</span>' : '' )
			: '<span style="font-family:' . self::F_MONO . ';font-size:11px;color:' . self::MUTED . ';white-space:nowrap;">' . self::e( $note ?: '–' ) . '</span>';
		$mid  = self::a( $mid, $murl, 'Detalji utakmice' );
		$pad  = ( $hs || $as ) ? '10px' : '11px';
		$html = '<tr>'
			. self::td( $home, $team . $line . 'padding:' . $pad . ' 12px ' . ( ( $hs || $as ) ? '3px' : '11px' ) . ' 0;text-align:right;' )
			. self::td( $mid, 'width:16%;text-align:center;vertical-align:middle;white-space:nowrap;' . $line . 'padding:' . $pad . ' 0 ' . ( ( $hs || $as ) ? '3px' : '11px' ) . ';' )
			. self::td( $away, $team . $line . 'padding:' . $pad . ' 0 ' . ( ( $hs || $as ) ? '3px' : '11px' ) . ' 12px;text-align:left;' )
			. '</tr>';
		if ( $hs || $as ) {
			$sc = 'font-family:' . self::F_BODY . ';font-size:12.5px;line-height:1.35;color:#5b5e66;vertical-align:top;padding-bottom:10px;';
			$html .= '<tr>'
				. self::td( $hs, $sc . 'padding-right:12px;text-align:right;' )
				. self::td( '', 'padding:0;' )
				. self::td( $as, $sc . 'padding-left:12px;text-align:left;' )
				. '</tr>';
		}
		return $html;
	}

	private static function results_html( array $d ): string {
		$rows   = '';
		$groups = [];
		foreach ( $d['matches'] as $m ) $groups[ $m->group_name ][] = $m;
		foreach ( $groups as $gname => $list ) {
			if ( count( $groups ) > 1 ) {
				$rows .= '<tr>' . self::td( self::e( $gname ), 'padding:14px 0 6px;font-family:' . self::F_HEAD . ';font-weight:800;font-style:italic;font-size:13px;text-transform:uppercase;color:' . $d['accent'] . ';', ' colspan="3"' ) . '</tr>';
			}
			foreach ( $list as $m ) {
				$done = $m->status === 'finished';
				$gl   = $done ? ( $d['goals'][ (int) $m->id ] ?? [] ) : [];
				$ts   = $m->match_date ? strtotime( $m->match_date ) : 0;
				$note = ! $ts ? 'termin naknadno' : ( $ts < current_time( 'timestamp' ) ? 'odgođeno' : date( 'H:i', $ts ) );
				$did   = (int) ( $d['did'] ?? 0 );
				$rows .= self::match_rows(
					self::a( self::e( $m->home_name ), self::team_url( (int) $m->home_team_id, $did ) ),
					self::a( self::e( $m->away_name ), self::team_url( (int) $m->away_team_id, $did ) ),
					$done ? (int) $m->home_score . ' : ' . (int) $m->away_score : null,
					self::scorers_links( $gl['home'] ?? [] ), self::scorers_links( $gl['away'] ?? [] ),
					$note, self::match_url( (int) $m->id )
				);
			}
			$gid = (int) $list[0]->group_id;
			if ( isset( $d['byes'][ $gid ] ) ) {
				$rows .= '<tr>' . self::td( 'Slobodna u ovom kolu: <strong style="color:' . self::DARK . ';">' . self::e( $d['byes'][ $gid ] ) . '</strong>',
					'padding:9px 0 2px;border-top:1px solid ' . self::LINE . ';font-family:' . self::F_MONO . ';font-size:11px;color:' . self::MUTED . ';', ' colspan="3"' ) . '</tr>';
			}
		}
		return self::table( $rows );
	}

	private static function standings_html( array $t, array $d ): string {
		$spots = $d['spots'] ? (int) $t['group']->playoff_spots : 0;
		$h     = 'background:' . self::DARK . ';color:#ffffff;font-family:' . self::F_HEAD . ';font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.4px;padding:8px 6px;text-align:center;';
		$rows  = '<tr style="background:' . self::DARK . ';">'
			. self::td( '#', $h . 'width:34px;padding-left:12px;text-align:left;' )
			. self::td( 'Klub', $h . 'text-align:left;' )
			. self::td( 'O', $h . 'width:30px;' ) . self::td( 'P', $h . 'width:30px;' ) . self::td( 'N', $h . 'width:30px;' ) . self::td( 'I', $h . 'width:30px;' )
			. self::td( 'Gol.', $h . 'width:58px;' ) . self::td( 'Bod', $h . 'width:44px;padding-right:12px;text-align:right;' )
			. '</tr>';
		foreach ( $t['rows'] as $i => $r ) {
			$bg   = 'background:' . ( $i % 2 ? self::STRIPE : self::PAPER ) . ';';
			$cut  = $spots && $i === $spots - 1 ? 'border-bottom:2px dashed ' . $d['accent'] . ';' : 'border-bottom:1px solid ' . self::LINE . ';';
			$c    = $bg . $cut . 'padding:8px 6px;vertical-align:middle;font-family:' . self::F_BODY . ';font-size:14px;color:' . self::DARK . ';';
			$move = '';
			if ( isset( $r['move'] ) && $r['move'] !== null && $r['move'] !== 0 ) {
				$up   = $r['move'] > 0;
				$move = '<span style="margin-left:4px;font-family:' . self::F_MONO . ';font-size:9.5px;font-weight:700;color:' . ( $up ? self::WIN : self::KC ) . ';">' . ( $up ? '&#9650;' : '&#9660;' ) . abs( (int) $r['move'] ) . '</span>';
			}
			$pos_border = $i < $spots ? 'border-left:4px solid ' . $d['accent'] . ';padding-left:8px;' : 'padding-left:12px;';
			$rows .= '<tr>'
				. self::td( '<span style="font-family:' . self::F_HEAD . ';font-weight:800;font-size:16px;">' . ( $i + 1 ) . '.</span>' . $move, $c . $pos_border . 'white-space:nowrap;' )
				. self::td( self::a( self::e( $r['team_name'] ), self::team_url( (int) $r['team_id'], (int) ( $d['did'] ?? 0 ) ) ), $c . 'font-weight:600;text-align:left;' )
				. self::td( (int) $r['P'], $c . 'text-align:center;' ) . self::td( (int) $r['W'], $c . 'text-align:center;' )
				. self::td( (int) $r['D'], $c . 'text-align:center;' ) . self::td( (int) $r['L'], $c . 'text-align:center;' )
				. self::td( (int) $r['GF'] . ':' . (int) $r['GA'], $c . 'text-align:center;font-family:' . self::F_MONO . ';font-size:12px;white-space:nowrap;' )
				. self::td( (int) $r['Pts'], $c . 'text-align:right;padding-right:12px;font-family:' . self::F_HEAD . ';font-weight:800;font-size:17px;' )
				. '</tr>';
		}
		$out = self::table( $rows, 'border:1px solid ' . self::DARK . ';' );
		if ( $spots ) $out .= self::note( 'Prve ' . $spots . ' ekipe prolaze u playoff.' );
		if ( $t['unplayed'] ) $out .= self::note( 'Neodigranih utakmica zaključno s ovim kolom: ' . $t['unplayed'] . ' (nisu uračunate).' );
		return $out;
	}

	private static function scorers_html( array $sc ): string {
		$rows  = '';
		$first = true;
		foreach ( $sc['groups'] as $goals => $list ) {
			$pill  = $first ? self::pill( (string) $goals, self::GOLD, self::DARK, 15, 32, 26, false ) : self::pill( (string) $goals, self::DARK, '#ffffff', 15, 32, 26, false );
			$first = false;
			$names = implode( ', ', array_map( fn( $s ) => '<strong style="font-weight:700;color:' . self::DARK . ';">' . self::a( self::e( $s['name'] ), self::player_url( (int) ( $s['pid'] ?? 0 ) ) ) . '</strong><span style="color:' . self::MUTED . ';"> (' . self::e( $s['team'] ) . ')</span>', $list ) );
			$rows .= '<tr>'
				. self::td( $pill, 'width:44px;padding:7px 0;vertical-align:top;border-bottom:1px solid ' . self::LINE . ';' )
				. self::td( $names, 'padding:8px 0;vertical-align:top;font-family:' . self::F_BODY . ';font-size:13.5px;line-height:1.45;color:' . self::DARK . ';border-bottom:1px solid ' . self::LINE . ';' )
				. '</tr>';
		}
		if ( $sc['own'] ) {
			$names = implode( ', ', array_map( fn( $s ) => '<strong style="font-weight:700;color:' . self::DARK . ';">' . self::e( $s['name'] . ( $s['goals'] > 1 ? ' ' . $s['goals'] : '' ) ) . '</strong><span style="color:' . self::MUTED . ';"> (' . self::e( $s['team'] ) . ')</span>', $sc['own'] ) );
			$rows .= '<tr>'
				. self::td( '<span style="font-family:' . self::F_MONO . ';font-size:10px;font-weight:700;color:' . self::MUTED . ';">AUTOGOL</span>', 'width:44px;padding:9px 0;vertical-align:top;' )
				. self::td( $names, 'padding:8px 0 8px 10px;vertical-align:top;font-family:' . self::F_BODY . ';font-size:13.5px;line-height:1.45;color:' . self::DARK . ';' )
				. '</tr>';
		}
		return self::table( $rows );
	}

	private static function playoff_html( array $d ): string {
		$out = '';
		if ( ! empty( $d['champion'] ) ) {
			$out .= self::table( '<tr>' . self::td(
				'<span style="font-family:' . self::F_MONO . ';font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">&#127942; Prvak</span><br>'
				. '<span style="font-family:' . self::F_HEAD . ';font-weight:800;font-style:italic;font-size:24px;line-height:1.1;text-transform:uppercase;">' . self::e( $d['champion'] ) . '</span>',
				'background:' . self::GOLD . ';color:' . self::DARK . ';padding:12px 14px;'
			) . '</tr>', 'margin:0 0 6px;' );
		}
		foreach ( $d['playoff'] as $r => $po ) {
			$rows = '';
			foreach ( $po['matches'] as $m ) {
				$done = $m->status === 'finished';
				$gl   = $done ? ( $po['goals'][ (int) $m->match_id ] ?? [] ) : [];
				$ts   = $m->match_date ? strtotime( $m->match_date ) : 0;
				$did   = (int) ( $d['did'] ?? 0 );
				$rows .= self::match_rows(
					$m->home_name ? self::a( self::e( $m->home_name ), self::team_url( (int) $m->home_team_id, $did ) ) : self::e( kurs_playoff_label( $m->home_label ) ),
					$m->away_name ? self::a( self::e( $m->away_name ), self::team_url( (int) $m->away_team_id, $did ) ) : self::e( kurs_playoff_label( $m->away_label ) ),
					$done ? (int) $m->home_score . ' : ' . (int) $m->away_score : null,
					self::scorers_links( $gl['home'] ?? [] ), self::scorers_links( $gl['away'] ?? [] ),
					$ts ? date( 'j. n. H:i', $ts ) : 'termin naknadno',
					self::match_url( (int) ( $m->match_id ?? 0 ) ),
					$done ? Kurs_Playoff::pen_label( $m ) : ''
				);
			}
			$out .= self::subheading( self::PO_LABELS[ $r ] ) . self::table( $rows );
		}
		return $out;
	}

	/**
	 * Aktualna ljestvica lige u obliku division_data() (za ugradnju „Ljestvica — uvijek aktualna").
	 */
	public static function live_data( object $division, bool $is_pan ): array {
		$did    = (int) $division->id;
		$tables = [];
		$ph     = Kurs_Season::group_phase( $did );
		foreach ( $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [] as $g ) {
			$rows = Kurs_Match::get_standings( (int) $g->id );
			if ( $rows ) $tables[] = [ 'group' => $g, 'rows' => $rows, 'unplayed' => 0 ];
		}
		return [
			'did' => $did, 'name' => $division->name, 'accent' => self::accent( $division->name, $is_pan ), 'spots' => ! empty( $division->has_playoff ),
			'url'  => kurs_league_url( $did, [ 'tab' => 'tablica' ] ) . '#liga',
			'matches' => [], 'goals' => [], 'byes' => [], 'date' => '', 'finished' => 0,
			'tables' => $tables, 'scorers' => [ 'groups' => [], 'own' => [] ],
		];
	}

	/**
	 * Cijeli paket (jedna kartica) za odabrane lige.
	 * Isti HTML koristi i ugradnja u vijesti (blok „KURS — iz lige", tema 1.0.51) — izgled je identičan paketu za medije.
	 * @param array      $datas  rezultat division_data() / live_data() za svaku ligu
	 * @param int|string $round  broj kola, 'playoff' ili 'live' (aktualna ljestvica)
	 * @param array      $opts   [ parts => ['results','table','scorers'] (što prikazati) ]
	 */
	public static function html( array $datas, $round, string $competition, string $season_name, array $opts = [] ): string {
		$parts   = $opts['parts'] ?? [ 'results', 'table', 'scorers' ];
		$is_live = $round === 'live';
		$home  = home_url( '/' );
		$host  = wp_parse_url( $home, PHP_URL_HOST );
		$logo  = get_template_directory_uri() . '/assets/img/kurs-media-logo.png';
		$is_po = $round === 'playoff';
		$dates = array_values( array_unique( array_filter( array_column( $datas, 'date' ) ) ) );
		$title = $competition . ' · ' . ( $is_po ? 'Playoff' : ( $is_live ? 'Ljestvica' : (int) $round . '. kolo' ) );
		$sub   = implode( ' · ', array_filter( [ count( $dates ) === 1 ? $dates[0] : '', $season_name ] ) );

		$html  = '<div style="max-width:680px;margin:0 auto 24px;background:' . self::PAPER . ';border:1px solid #dcdcd3;font-family:' . self::F_BODY . ';color:' . self::DARK . ';text-align:left;line-height:1.4;">';
		// zaglavlje
		$html .= self::table( '<tr>'
			. self::td( '<a href="' . esc_url( $home ) . '" style="text-decoration:none;border:0;box-shadow:none;"><img src="' . esc_url( $logo ) . '" width="92" height="36" alt="KURS" style="width:92px;height:auto;border:0;margin:0;"></a>', 'padding:18px 20px;vertical-align:middle;background:' . self::DARK . ';' )
			. self::td(
				'<div style="font-family:' . self::F_HEAD . ';font-weight:800;font-style:italic;font-size:25px;line-height:1;text-transform:uppercase;color:#ffffff;letter-spacing:-0.3px;">' . self::e( $title ) . '</div>'
				. ( $sub ? '<div style="font-family:' . self::F_MONO . ';font-size:11px;line-height:1.4;color:#a7a9b0;margin-top:6px;">' . self::e( $sub ) . '</div>' : '' ),
				'padding:18px 20px;vertical-align:middle;text-align:right;background:' . self::DARK . ';'
			)
			. '</tr>', 'background:' . self::DARK . ';' );
		// trobojna crta (KC crvena · zlatna · Pan narančasta)
		$html .= self::table( '<tr>'
			. self::td( '', 'height:4px;padding:0;font-size:0;line-height:0;background:' . self::KC . ';width:34%;' )
			. self::td( '', 'height:4px;padding:0;font-size:0;line-height:0;background:' . self::GOLD . ';width:33%;' )
			. self::td( '', 'height:4px;padding:0;font-size:0;line-height:0;background:' . self::PAN . ';width:33%;' )
			. '</tr>' );

		foreach ( $datas as $i => $d ) {
			$html .= '<div style="padding:22px 20px 6px;' . ( $i ? 'border-top:8px solid #f1f1ea;' : '' ) . '">';
			if ( $is_po ) {
				$html .= self::heading( 'Playoff ' . self::genitive( $d['name'] ), $d['accent'], $d['date'] );
				$html .= self::playoff_html( $d );
			} elseif ( $is_live || ! in_array( 'results', $parts, true ) ) { // samo ljestvica (aktualna ili nakon kola)
				$html .= self::heading( ( $is_live ? 'Ljestvica ' : 'Ljestvica nakon ' . (int) $round . '. kola ' ) . self::genitive( $d['name'] ), $d['accent'] );
				foreach ( $d['tables'] as $t ) {
					if ( count( $d['tables'] ) > 1 ) {
						$html .= '<div style="font-family:' . self::F_HEAD . ';font-weight:800;font-style:italic;font-size:13px;text-transform:uppercase;color:' . $d['accent'] . ';margin:12px 0 5px;">' . self::e( $t['group']->name ) . '</div>';
					}
					$html .= self::standings_html( $t, $d );
				}
				if ( ! $d['tables'] ) $html .= self::note( 'Ljestvica će biti dostupna nakon prvih rezultata.' );
			} elseif ( ! $d['matches'] ) {
				$html .= self::heading( $d['name'], $d['accent'] ) . self::note( 'U ovom kolu nema utakmica.' );
			} else {
				$kind  = $d['finished'] ? 'Rezultati' : 'Najava';
				$html .= self::heading( $kind . ' ' . (int) $round . '. kola ' . self::genitive( $d['name'] ), $d['accent'], count( $dates ) === 1 ? '' : $d['date'] );
				$html .= self::results_html( $d );
				if ( $d['tables'] && in_array( 'table', $parts, true ) ) {
					$html .= self::subheading( 'Ljestvica nakon ' . (int) $round . '. kola' );
					foreach ( $d['tables'] as $t ) {
						if ( count( $d['tables'] ) > 1 ) {
							$html .= '<div style="font-family:' . self::F_HEAD . ';font-weight:800;font-style:italic;font-size:13px;text-transform:uppercase;color:' . $d['accent'] . ';margin:12px 0 5px;">' . self::e( $t['group']->name ) . '</div>';
						}
						$html .= self::standings_html( $t, $d );
					}
				}
				if ( in_array( 'scorers', $parts, true ) && ( $d['scorers']['groups'] || $d['scorers']['own'] ) ) {
					$html .= self::subheading( 'Strijelci' ) . self::scorers_html( $d['scorers'] );
				}
			}
			$html .= '<div style="height:16px;line-height:16px;font-size:0;">&nbsp;</div></div>';
		}

		// podnožje — izvor + poveznica na pregled kola
		$html .= self::table( '<tr>'
			. self::td( 'Izvor: <a href="' . esc_url( $home ) . '" style="color:' . self::GOLD . ';font-weight:700;text-decoration:none;border:0;box-shadow:none;">KURS · ' . self::e( $host ) . '</a><br><span style="color:#8d8f96;font-size:11px;">Koprivnička udruga za rekreativni sport</span>',
				'padding:14px 20px;vertical-align:middle;background:' . self::DARK . ';color:#c9cac4;font-family:' . self::F_BODY . ';font-size:13px;line-height:1.4;' )
			. self::td( '<a href="' . esc_url( $datas[0]['url'] ?? $home ) . '" style="color:#ffffff;font-family:' . self::F_HEAD . ';font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.3px;text-decoration:none;border:0;box-shadow:none;white-space:nowrap;">Zapisnici i statistika &rarr;</a>',
				'padding:14px 20px;vertical-align:middle;text-align:right;background:' . self::DARK . ';' )
			. '</tr>', 'background:' . self::DARK . ';' );
		$html .= '</div>';

		return preg_replace( '/>\s+</', '><', str_replace( [ "\r", "\n", "\t" ], ' ', $html ) );
	}

	/* ── Obični tekst (kao u izvještajima portala) ──────────────────────── */

	public static function text( array $datas, $round, string $season_name ): string {
		$host  = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$lines = [];
		foreach ( $datas as $d ) {
			if ( $round === 'playoff' ) {
				$lines[] = mb_strtoupper( 'Playoff ' . self::genitive( $d['name'] ) ) . ( $d['date'] ? ' (' . $d['date'] . ')' : '' );
				$lines[] = '';
				foreach ( $d['playoff'] as $r => $po ) {
					$lines[] = mb_strtoupper( self::PO_LABELS[ $r ] );
					foreach ( $po['matches'] as $m ) {
						$done = $m->status === 'finished';
						$lines[] = ( $m->home_name ?: kurs_playoff_label( $m->home_label ) ) . ' – ' . ( $m->away_name ?: kurs_playoff_label( $m->away_label ) ) . ( $done ? ' ' . (int) $m->home_score . ':' . (int) $m->away_score . ( ( $p = Kurs_Playoff::pen_label( $m ) ) ? " ($p)" : '' ) : '' );
						if ( $done && ( $s = self::text_scorers( $po['goals'][ (int) $m->match_id ] ?? [] ) ) ) $lines[] = $s;
					}
					$lines[] = '';
				}
				if ( ! empty( $d['champion'] ) ) { $lines[] = 'PRVAK: ' . $d['champion']; $lines[] = ''; }
				continue;
			}
			if ( ! $d['matches'] ) continue;
			$kind    = $d['finished'] ? 'REZULTATI' : 'NAJAVA';
			$lines[] = $kind . ' ' . (int) $round . '. KOLA ' . mb_strtoupper( self::genitive( $d['name'] ) ) . ( $d['date'] ? ' (' . $d['date'] . ')' : '' );
			$lines[] = '';
			$multi   = count( array_unique( array_map( fn( $m ) => $m->group_name, $d['matches'] ) ) ) > 1;
			$cur     = null;
			foreach ( $d['matches'] as $m ) {
				if ( $multi && $m->group_name !== $cur ) { $cur = $m->group_name; $lines[] = mb_strtoupper( $cur ); }
				$done = $m->status === 'finished';
				$ts   = $m->match_date ? strtotime( $m->match_date ) : 0;
				$lines[] = $m->home_name . ' – ' . $m->away_name . ' ' . ( $done ? (int) $m->home_score . ':' . (int) $m->away_score
					: ( ! $ts ? '(termin naknadno)' : ( $ts < current_time( 'timestamp' ) ? '(odgođeno)' : '(' . date( 'H:i', $ts ) . ')' ) ) );
				if ( $done && ( $s = self::text_scorers( $d['goals'][ (int) $m->id ] ?? [] ) ) ) $lines[] = $s;
			}
			foreach ( $d['byes'] as $team ) $lines[] = 'Slobodna: ' . $team;
			$lines[] = '';

			foreach ( $d['tables'] as $t ) {
				$lines[] = mb_strtoupper( $d['name'] . ( count( $d['tables'] ) > 1 ? ' – ' . $t['group']->name : '' ) . ' – ljestvica' );
				foreach ( $t['rows'] as $i => $r ) {
					$lines[] = ( $i + 1 ) . '. ' . $r['team_name'] . ' ' . $r['P'] . ' ' . $r['W'] . ' ' . $r['D'] . ' ' . $r['L'] . ' ' . $r['GF'] . ':' . $r['GA'] . ' ' . $r['Pts'];
				}
				$lines[] = '';
			}
			if ( $d['scorers']['groups'] || $d['scorers']['own'] ) {
				$lines[] = 'STRIJELCI';
				foreach ( $d['scorers']['groups'] as $goals => $list ) {
					$lines[] = $goals . ' – ' . implode( ', ', array_map( fn( $s ) => $s['name'] . ' (' . $s['team'] . ')', $list ) );
				}
				if ( $d['scorers']['own'] ) {
					$lines[] = 'Autogol – ' . implode( ', ', array_map( fn( $s ) => $s['name'] . ( $s['goals'] > 1 ? ' ' . $s['goals'] : '' ) . ' (' . $s['team'] . ')', $d['scorers']['own'] ) );
				}
				$lines[] = '';
			}
		}
		$lines[] = 'Izvor: KURS – Koprivnička udruga za rekreativni sport (' . $host . ')';
		return implode( "\n", $lines );
	}

	/** „(Križanović 2, Sabolić / P. Lončar 3)" — kad strijelce ima samo jedna strana, bez kose crte. */
	private static function text_scorers( array $gl ): string {
		$h = Kurs_Rounds::scorers_text( $gl['home'] ?? [] );
		$a = Kurs_Rounds::scorers_text( $gl['away'] ?? [] );
		if ( ! $h && ! $a ) return '';
		return '(' . ( $h && $a ? $h . ' / ' . $a : $h . $a ) . ')';
	}
}
