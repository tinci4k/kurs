<?php
/**
 * Portal ekipe (1.31.0) — /portal-ekipe/?e=ID&k=KLJUČ: svaka ekipa ima svoj tajni link na kojem
 * provjerava i ispravlja popis igrača, šalje logo, sponzore ekipe i kontakt voditelja/trenera.
 *
 * Ništa se ne mijenja odmah: svaka promjena je ZAHTJEV (tablica `wp_kurs_requests`) koji KURS
 * odobrava ili odbija u wp-admin → KURS Liga → Zahtjevi ekipa. Tek odobrenjem se primijeni
 * (roster tekuće lige, istaknuta slika ekipe, `_kurs_team_banners`, `_kurs_team_rep/coach`).
 *
 * Ključ = HMAC( 'kurs-portal|ID|verzija', wp_salt('auth') ) — ovisi o solima instalacije (lokalno ≠ live,
 * link uvijek kopiraj iz admina). „Novi link" poveća `_kurs_portal_v` i stari prestaje raditi.
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Portal {

	const SLUG        = 'portal-ekipe';
	const MAX_PENDING = 80;
	const MAX_SPONS   = 3;   // sponzora ekipe (na webu + na čekanju) — ekipe imaju jednog ili dva (1.32.0)
	const COLORS      = [ '#DA0F1E', '#F47B16', '#FDE206', '#1F7A43', '#1a3a6b', '#0ea5e9', '#7c3aed', '#8b1a1a', '#ffffff', '#111317', '#6b7280', '#e8e8e8' ];

	const TYPES = [
		'player_add'     => 'Novi igrač',
		'player_edit'    => 'Ispravak igrača',
		'player_remove'  => 'Igrač više nije u ekipi',
		'logo'           => 'Novi logo ekipe',
		'colors'         => 'Boje dresa',
		'sponsor_add'    => 'Novi sponzor ekipe',
		'sponsor_remove' => 'Uklanjanje sponzora',
		'info'           => 'Kontakt ekipe',
		'note'           => 'Poruka KURS-u',
	];
	const TIERS  = [ 'glavni' => 'Glavni', 'srednji' => 'Srednji', 'mali' => 'Mali' ];
	const STATUS = [ 'pending' => 'Čeka odobrenje', 'approved' => 'Odobreno', 'rejected' => 'Odbijeno', 'cancelled' => 'Povučeno' ];

	public static function init(): void {
		add_action( 'init',              [ __CLASS__, 'rewrite' ] );
		add_action( 'init',              [ __CLASS__, 'heal_rewrite' ], 103 );
		add_filter( 'query_vars',        fn( $v ) => array_merge( $v, [ 'kurs_portal' ] ) );
		add_action( 'template_redirect', [ __CLASS__, 'render' ], 0 );
		add_action( 'wp_ajax_kurs_portal',        [ __CLASS__, 'ajax' ] );
		add_action( 'wp_ajax_nopriv_kurs_portal', [ __CLASS__, 'ajax' ] );
		add_action( 'admin_menu',        [ __CLASS__, 'admin_menu' ], 19 );
		add_action( 'admin_post_kurs_request_decide', [ __CLASS__, 'handle_decide' ] );
		add_action( 'admin_post_kurs_portal_newlink', [ __CLASS__, 'handle_newlink' ] );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . 'requests';
	}

	/* ── Ključ i link ──────────────────────────────────────────────────── */

	public static function key( int $team_id ): string {
		$v = (int) get_post_meta( $team_id, '_kurs_portal_v', true );
		return substr( hash_hmac( 'sha256', 'kurs-portal|' . $team_id . '|' . $v, wp_salt( 'auth' ) ), 0, 14 );
	}

	public static function url( int $team_id ): string {
		return add_query_arg( [ 'e' => $team_id, 'k' => self::key( $team_id ) ], home_url( '/' . self::SLUG . '/' ) );
	}

	/** ID ekipe iz zahtjeva ako je ključ ispravan, inače 0. */
	private static function team_from_request(): int {
		$team = absint( $_REQUEST['e'] ?? 0 );
		$k    = (string) ( $_REQUEST['k'] ?? '' );
		if ( ! $team || $k === '' || get_post_type( $team ) !== 'kurs_ekipa' || get_post_status( $team ) !== 'publish' ) return 0;
		return hash_equals( self::key( $team ), $k ) ? $team : 0;
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?kurs_portal=1', 'top' );
	}

	public static function heal_rewrite(): void {
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && get_option( 'permalink_structure' ) && ! isset( $rules[ '^' . self::SLUG . '/?$' ] ) ) flush_rewrite_rules( false );
	}

	/* ── Kontekst ekipe: liga u kojoj se trenutno vodi roster ─────────────── */

	/** Aktualna liga ekipe: prva nezaključana aktualna sezona, inače najnovija. */
	public static function context( int $team_id ): ?object {
		$ctxs = Kurs_Season::get_team_contexts( $team_id );
		foreach ( $ctxs as $c ) {
			$cur = Kurs_Season::current_for_league( Kurs_Season::is_pan_league( (string) $c->league ) );
			if ( $cur && (int) $cur->id === (int) $c->season_id && ! Kurs_Season::is_locked( (int) $c->season_id ) ) return $c;
		}
		return $ctxs[0] ?? null;
	}

	/* ── Zahtjevi ──────────────────────────────────────────────────────── */

	public static function requests( int $team_id = 0, string $status = '', int $limit = 200 ): array {
		global $wpdb;
		$w = [ '1=1' ]; $a = [];
		if ( $team_id ) { $w[] = 'team_id = %d'; $a[] = $team_id; }
		if ( $status === 'done' ) $w[] = "status <> 'pending'";
		elseif ( $status ) { $w[] = 'status = %s'; $a[] = $status; }
		$a[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $w ) . ' ORDER BY ' . ( $status === 'pending' ? 'team_id, id' : 'id DESC' ) . ' LIMIT %d', ...$a ) );
		foreach ( $rows as $r ) $r->data = json_decode( (string) $r->data, true ) ?: [];
		return $rows;
	}

	public static function pending_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE status = 'pending'" );
	}

	/** Ljudski opis zahtjeva (bez HTML-a). */
	public static function describe( object $r ): string {
		$d = $r->data;
		switch ( $r->type ) {
			case 'player_add':    return trim( ( $d['name'] ?? '' ) . ( ! empty( $d['number'] ) ? ' · br. ' . (int) $d['number'] : '' ) . ( ! empty( $d['gk'] ) ? ' · golman' : '' ) );
			case 'player_edit':
				$ch = [];
				if ( isset( $d['name'] ) && $d['name'] !== ( $d['old_name'] ?? '' ) ) $ch[] = ( $d['old_name'] ?? '' ) . ' → ' . $d['name'];
				if ( array_key_exists( 'number', $d ) && (int) $d['number'] !== (int) ( $d['old_number'] ?? 0 ) ) $ch[] = 'broj ' . ( (int) ( $d['old_number'] ?? 0 ) ?: '–' ) . ' → ' . ( (int) $d['number'] ?: '–' );
				if ( isset( $d['name'] ) && $d['name'] !== ( $d['old_name'] ?? '' ) ) return implode( ', ', $ch );
				return ( $d['old_name'] ?? '' ) . ': ' . ( $ch ? implode( ', ', $ch ) : 'bez promjene' );
			case 'player_remove': return (string) ( $d['old_name'] ?? '' );
			case 'logo':          return 'nova slika loga';
			case 'sponsor_add':   return trim( ( $d['name'] ?? '' ) . ( ! empty( $d['link'] ) ? ' · ' . preg_replace( '#^https?://(www\.)?#', '', $d['link'] ) : '' ) );
			case 'colors':        return 'glavna ' . ( $d['color'] ?? '' ) . ' · ' . ( ! empty( $d['color2'] ) ? 'druga ' . $d['color2'] : 'jednobojni dres' );
			case 'sponsor_remove': return (string) ( $d['label'] ?? 'sponzor' );
			case 'info':
				$p = [];
				foreach ( [ 'rep' => 'voditelj', 'rep_phone' => 'tel. voditelja', 'coach' => 'trener', 'coach_phone' => 'tel. trenera' ] as $k => $l ) if ( isset( $d[ $k ] ) ) $p[] = $l . ': ' . ( $d[ $k ] !== '' ? $d[ $k ] : '(prazno)' );
				return implode( ' · ', $p );
			case 'note':          return (string) ( $d['text'] ?? '' );
		}
		return '';
	}

	private static function add_request( int $team, int $div, string $type, array $data, string $who ): int {
		global $wpdb;
		$wpdb->insert( self::table(), [
			'team_id' => $team, 'division_id' => $div, 'type' => $type, 'data' => wp_json_encode( $data ),
			'status' => 'pending', 'who' => $who, 'created' => current_time( 'mysql' ),
		] );
		return (int) $wpdb->insert_id;
	}

	/* ── Stranica ──────────────────────────────────────────────────────── */

	public static function render(): void {
		if ( ! get_query_var( 'kurs_portal' ) ) return;
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		$team_id = self::team_from_request();
		if ( ! $team_id ) {
			status_header( 404 );
			echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Link nije ispravan</title><p style="font-family:sans-serif;padding:40px;max-width:520px">Link nije ispravan ili je zamijenjen novim. Zatražite novi link od KURS-a.</p>';
			exit;
		}
		require_once KURS_DIR . 'admin/class-admin.php';
		wp_enqueue_style( 'kurs-admin-fonts', Kurs_Admin::FONTS_URL, [], null );
		wp_enqueue_style( 'kurs-presjek', KURS_URL . 'front/presjek.css', [ 'kurs-admin-fonts' ], KURS_VERSION );
		wp_enqueue_style( 'kurs-portal', KURS_URL . 'front/portal.css', [ 'kurs-presjek' ], KURS_VERSION );
		status_header( 200 );
		require KURS_DIR . 'front/portal.php';
		exit;
	}

	/** Podaci za stranicu i AJAX odgovor (roster s oznakama zahtjeva na čekanju, sponzori, kontakt, zahtjevi). */
	public static function state( int $team_id ): array {
		$ctx    = self::context( $team_id );
		$div    = $ctx ? (int) $ctx->division_id : 0;
		$reqs   = self::requests( $team_id, '', 60 );
		$pend   = array_filter( $reqs, fn( $r ) => $r->status === 'pending' );
		$by_pl  = [];
		foreach ( $pend as $r ) if ( ! empty( $r->data['player_id'] ) ) $by_pl[ (int) $r->data['player_id'] ][] = $r->type;
		$roster = [];
		foreach ( $div ? Kurs_Season::get_squad( $team_id, $div ) : [] as $sq ) {
			$roster[] = [
				'id'      => (int) $sq->player_id,
				'name'    => (string) $sq->player_name,
				'number'  => (int) $sq->jersey_no,
				'gk'      => function_exists( 'kurs_player_type' ) && kurs_player_type( (int) $sq->player_id ) === 'goalkeeper',
				'pending' => $by_pl[ (int) $sq->player_id ] ?? [],
			];
		}
		$spons = [];
		foreach ( self::team_sponsors( $team_id ) as $s ) {
			$s['pending'] = (bool) array_filter( $pend, fn( $r ) => $r->type === 'sponsor_remove' && (int) ( $r->data['att'] ?? 0 ) === (int) $s['id'] );
			$spons[]      = $s;
		}
		return [
			'roster'   => $roster,
			'sponsors' => $spons,
			'logo'     => (string) get_the_post_thumbnail_url( $team_id, 'medium' ),
			'colors'   => [ 'c1' => kurs_team_color( $team_id ), 'c2' => kurs_team_color2( $team_id ) ],
			'info'     => [
				'rep'         => (string) get_post_meta( $team_id, '_kurs_team_rep', true ),
				'rep_phone'   => (string) get_post_meta( $team_id, '_kurs_team_rep_phone', true ),
				'coach'       => (string) get_post_meta( $team_id, '_kurs_team_coach', true ),
				'coach_phone' => (string) get_post_meta( $team_id, '_kurs_team_coach_phone', true ),
			],
			'requests' => array_map( fn( $r ) => [
				'id'     => (int) $r->id,
				'type'   => $r->type,
				'typeL'  => self::TYPES[ $r->type ] ?? $r->type,
				'desc'   => self::describe( $r ),
				'img'    => ! empty( $r->data['att'] ) && in_array( $r->type, [ 'logo', 'sponsor_add' ], true ) ? (string) wp_get_attachment_image_url( (int) $r->data['att'], 'thumbnail' ) : '',
				'status' => $r->status,
				'statusL' => $r->type === 'note' && $r->status === 'approved' ? 'Pročitano' : ( self::STATUS[ $r->status ] ?? $r->status ),
				'note'   => (string) $r->admin_note,
				'who'    => (string) $r->who,
				'at'     => self::when( (string) $r->created ),
			], $reqs ),
		];
	}

	/** Sponzori ekipe na webu (`_kurs_team_banners`): id (prilog), naziv, razina, link, slika. */
	public static function team_sponsors( int $team_id ): array {
		$raw  = get_post_meta( $team_id, '_kurs_team_banners', true );
		$list = is_array( $raw ) ? $raw : ( is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : [] );
		$out  = [];
		foreach ( is_array( $list ) ? $list : [] as $b ) {
			$id = (int) ( $b['id'] ?? 0 );
			if ( ! $id ) continue;
			$out[] = [ 'id' => $id, 'name' => (string) ( $b['name'] ?? get_the_title( $id ) ), 'tier' => (string) ( $b['tier'] ?? '' ),
				'link' => (string) ( $b['link'] ?? '' ), 'img' => (string) wp_get_attachment_image_url( $id, 'medium' ) ];
		}
		return $out;
	}

	public static function when( string $mysql ): string {
		$ts = strtotime( $mysql );
		return $ts ? date( 'j. n. Y. \u H:i', $ts ) : '';
	}

	/* ── AJAX (bez prijave, štiti ga ključ) ────────────────────────────── */

	public static function ajax(): void {
		$team = self::team_from_request();
		if ( ! $team ) wp_send_json_error( [ 'msg' => 'Link nije ispravan ili je zamijenjen novim.' ], 403 );
		if ( ! empty( $_POST['web'] ) ) wp_send_json_success( self::state( $team ) ); // zamka za botove

		global $wpdb;
		$do  = sanitize_key( $_POST['do'] ?? '' );
		$who = mb_substr( sanitize_text_field( wp_unslash( $_POST['who'] ?? '' ) ), 0, 80 );
		$ctx = self::context( $team );
		$div = $ctx ? (int) $ctx->division_id : 0;
		$err = fn( string $m ) => wp_send_json_error( [ 'msg' => $m ], 400 );

		if ( $do === 'state' ) wp_send_json_success( self::state( $team ) );

		if ( $do === 'cancel' ) {
			$id = absint( $_POST['id'] ?? 0 );
			$wpdb->update( self::table(), [ 'status' => 'cancelled', 'decided' => current_time( 'mysql' ) ], [ 'id' => $id, 'team_id' => $team, 'status' => 'pending' ] );
			wp_send_json_success( self::state( $team ) );
		}

		$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE team_id = %d AND status = 'pending'", $team ) );
		if ( $n >= self::MAX_PENDING ) $err( 'Previše zahtjeva čeka odobrenje — pričekajte da ih KURS pregleda.' );

		$squad_ids = $div ? array_map( fn( $s ) => (int) $s->player_id, Kurs_Season::get_squad( $team, $div ) ) : [];
		$name_of   = fn( int $pid ) => get_the_title( $pid );
		$clean_name = fn( string $s ) => trim( preg_replace( '/\s+/u', ' ', mb_substr( sanitize_text_field( wp_unslash( $s ) ), 0, 80 ) ) );

		switch ( $do ) {
			case 'player_add':
				if ( ! $div ) $err( 'Ekipa trenutno nije u ligi.' );
				$name = $clean_name( (string) ( $_POST['name'] ?? '' ) );
				if ( mb_strlen( $name ) < 3 || ! str_contains( $name, ' ' ) ) $err( 'Upišite ime i prezime igrača.' );
				$num = min( 99, absint( $_POST['number'] ?? 0 ) );
				self::add_request( $team, $div, 'player_add', [ 'name' => $name, 'number' => $num, 'gk' => ! empty( $_POST['gk'] ) ], $who );
				break;

			case 'player_edit':
			case 'player_remove':
				$pid = absint( $_POST['player_id'] ?? 0 );
				if ( ! in_array( $pid, $squad_ids, true ) ) $err( 'Igrač nije na popisu ekipe.' );
				$cur = 0;
				foreach ( Kurs_Season::get_squad( $team, $div ) as $s ) if ( (int) $s->player_id === $pid ) $cur = (int) $s->jersey_no;
				if ( $do === 'player_remove' ) {
					self::add_request( $team, $div, 'player_remove', [ 'player_id' => $pid, 'old_name' => $name_of( $pid ) ], $who );
					break;
				}
				$name = $clean_name( (string) ( $_POST['name'] ?? '' ) );
				$num  = min( 99, absint( $_POST['number'] ?? 0 ) );
				if ( mb_strlen( $name ) < 3 ) $err( 'Upišite ime i prezime.' );
				if ( $name === $name_of( $pid ) && $num === $cur ) $err( 'Niste ništa promijenili.' );
				self::add_request( $team, $div, 'player_edit', [ 'player_id' => $pid, 'old_name' => $name_of( $pid ), 'name' => $name, 'old_number' => $cur, 'number' => $num ], $who );
				break;

			case 'logo':
				$att = self::upload( 'file', 'Logo ekipe: ' . get_the_title( $team ) );
				self::add_request( $team, $div, 'logo', [ 'att' => $att ], $who );
				break;

			case 'sponsor_add':
				$name = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 120 );
				if ( $name === '' ) $err( 'Upišite naziv sponzora.' );
				$pend_sp = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE team_id = %d AND status = 'pending' AND type = 'sponsor_add'", $team ) );
				if ( count( self::team_sponsors( $team ) ) + $pend_sp >= self::MAX_SPONS ) $err( 'Ekipa može imati najviše ' . self::MAX_SPONS . ' sponzora — za novog prvo uklonite postojećeg.' );
				$link = self::clean_link( (string) wp_unslash( $_POST['link'] ?? '' ) );
				if ( $link === null ) $err( 'Link nije ispravan (npr. https://sponzor.hr).' );
				if ( empty( $_FILES['file']['name'] ) ) $err( 'Dodajte logo ili baner sponzora.' );
				$att = self::upload( 'file', 'Sponzor ekipe ' . get_the_title( $team ) . ': ' . $name );
				self::add_request( $team, $div, 'sponsor_add', [ 'name' => $name, 'link' => $link, 'att' => $att ], $who );
				break;

			case 'colors':
				$c1 = strtolower( (string) ( $_POST['color'] ?? '' ) );
				$c2 = strtolower( (string) ( $_POST['color2'] ?? '' ) );
				if ( ! preg_match( '/^#[0-9a-f]{6}$/', $c1 ) ) $err( 'Odaberite glavnu boju dresa.' );
				if ( $c2 !== '' && ! preg_match( '/^#[0-9a-f]{6}$/', $c2 ) ) $err( 'Druga boja nije ispravna.' );
				if ( $c2 === $c1 ) $c2 = '';
				if ( $c1 === strtolower( kurs_team_color( $team ) ) && $c2 === kurs_team_color2( $team ) ) $err( 'To su već vaše boje.' );
				self::add_request( $team, $div, 'colors', [ 'color' => $c1, 'color2' => $c2 ], $who );
				break;

			case 'sponsor_remove':
				$att = absint( $_POST['att'] ?? 0 );
				$s   = array_values( array_filter( self::team_sponsors( $team ), fn( $x ) => $x['id'] === $att ) );
				if ( ! $s ) $err( 'Sponzor nije na webu.' );
				self::add_request( $team, $div, 'sponsor_remove', [ 'att' => $att, 'label' => $s[0]['name'] ], $who );
				break;

			case 'info':
				$data = [];
				foreach ( [ 'rep' => '_kurs_team_rep', 'rep_phone' => '_kurs_team_rep_phone', 'coach' => '_kurs_team_coach', 'coach_phone' => '_kurs_team_coach_phone' ] as $f => $meta ) {
					$v = mb_substr( sanitize_text_field( wp_unslash( $_POST[ $f ] ?? '' ) ), 0, 80 );
					if ( $v !== (string) get_post_meta( $team, $meta, true ) ) $data[ $f ] = $v;
				}
				if ( ! $data ) $err( 'Niste ništa promijenili.' );
				self::add_request( $team, $div, 'info', $data, $who );
				break;

			case 'note':
				$t = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) ), 0, 2000 );
				if ( trim( $t ) === '' ) $err( 'Upišite poruku.' );
				self::add_request( $team, $div, 'note', [ 'text' => $t ], $who );
				break;

			default:
				$err( 'Nepoznata radnja.' );
		}
		wp_send_json_success( self::state( $team ) );
	}

	/** http(s) link ili '' (prazno), null = neispravan. */
	private static function clean_link( string $link ): ?string {
		$link = trim( $link );
		if ( $link === '' ) return '';
		if ( ! preg_match( '#^https?://#i', $link ) ) $link = 'https://' . $link;
		$link = esc_url_raw( $link, [ 'http', 'https' ] );
		$host = (string) wp_parse_url( $link, PHP_URL_HOST );
		return $link && filter_var( $link, FILTER_VALIDATE_URL ) && str_contains( $host, '.' ) ? $link : null;
	}

	/** Upload slike (≤ 5 MB) u Medije; vraća ID priloga ili prekida s greškom. */
	private static function upload( string $field, string $title ): int {
		if ( empty( $_FILES[ $field ]['name'] ) ) wp_send_json_error( [ 'msg' => 'Odaberite sliku.' ], 400 );
		$f = $_FILES[ $field ];
		if ( (int) $f['size'] > 5 * MB_IN_BYTES ) wp_send_json_error( [ 'msg' => 'Slika je veća od 5 MB.' ], 400 );
		$mimes = [ 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' ];
		$chk   = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $mimes );
		if ( empty( $chk['type'] ) || ! @getimagesize( $f['tmp_name'] ) ) wp_send_json_error( [ 'msg' => 'Datoteka mora biti slika (PNG, JPG, WEBP ili GIF).' ], 400 );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_handle_upload( $field, 0, [ 'post_title' => $title ], [ 'test_form' => false, 'mimes' => $mimes ] );
		if ( is_wp_error( $att ) ) wp_send_json_error( [ 'msg' => 'Spremanje slike nije uspjelo: ' . $att->get_error_message() ], 400 );
		update_post_meta( $att, '_kurs_portal_upload', 1 );
		return (int) $att;
	}

	/* ── Admin: Zahtjevi ekipa ─────────────────────────────────────────── */

	public static function admin_menu(): void {
		$n     = self::pending_count();
		$label = 'Zahtjevi ekipa' . ( $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '' );
		add_submenu_page( 'kurs-dashboard', 'Zahtjevi ekipa', $label, KURS_CAP, 'kurs-requests', [ __CLASS__, 'admin_page' ] );
	}

	public static function admin_page(): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemaš ovlasti.' );
		include KURS_DIR . 'admin/views/requests.php';
	}

	/** Ekipe aktualnih sezona (za popis linkova): [ [team_id, name, league label, division_id] ]. */
	public static function current_teams(): array {
		$out = [];
		foreach ( [ false, true ] as $pan ) {
			$s = Kurs_Season::current_for_league( $pan );
			if ( ! $s ) continue;
			foreach ( Kurs_Season::get_divisions( (int) $s->id ) as $d ) {
				$ph = Kurs_Season::group_phase( (int) $d->id );
				foreach ( $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [] as $g ) {
					foreach ( Kurs_Season::get_group_teams( (int) $g->id ) as $t ) {
						$out[ (int) $t->team_id ] ??= [ 'id' => (int) $t->team_id, 'name' => $t->team_name, 'league' => stripos( $s->name, $d->name ) !== false ? $s->name : $d->name . ' · ' . $s->name, 'locked' => Kurs_Season::is_locked( (int) $s->id ) ];
					}
				}
			}
		}
		uasort( $out, fn( $a, $b ) => [ $a['locked'], $a['league'], $a['name'] ] <=> [ $b['locked'], $b['league'], $b['name'] ] );
		return array_values( $out );
	}

	public static function handle_newlink(): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemaš ovlasti.' );
		check_admin_referer( 'kurs_portal_newlink' );
		$team = absint( $_POST['team_id'] ?? 0 );
		if ( get_post_type( $team ) === 'kurs_ekipa' ) update_post_meta( $team, '_kurs_portal_v', (int) get_post_meta( $team, '_kurs_portal_v', true ) + 1 );
		wp_safe_redirect( add_query_arg( [ 'page' => 'kurs-requests', 'tab' => 'linkovi', 'msg' => 'newlink', 'tm' => $team ], admin_url( 'admin.php' ) ) . '#tm-' . $team );
		exit;
	}

	/** Odobri / odbij jedan ili više zahtjeva (POST ids[]). */
	public static function handle_decide(): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemaš ovlasti.' );
		check_admin_referer( 'kurs_request_decide' );
		$ids    = array_filter( array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) ) );
		$action = ( $_POST['decision'] ?? '' ) === 'approve' ? 'approve' : 'reject';
		$note   = mb_substr( sanitize_text_field( wp_unslash( $_POST['admin_note'] ?? '' ) ), 0, 255 );
		$ok = 0; $errors = [];
		global $wpdb;
		foreach ( $ids as $id ) {
			$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE id = %d AND status = 'pending'", $id ) );
			if ( ! $r ) continue;
			$r->data = json_decode( (string) $r->data, true ) ?: [];
			if ( $action === 'approve' ) {
				$res = self::apply( $r );
				if ( is_wp_error( $res ) ) { $errors[] = get_the_title( (int) $r->team_id ) . ': ' . $res->get_error_message(); continue; }
			}
			$wpdb->update( self::table(), [
				'status' => $action === 'approve' ? 'approved' : 'rejected', 'decided' => current_time( 'mysql' ),
				'decided_by' => get_current_user_id(), 'admin_note' => $note,
			], [ 'id' => $id ] );
			$ok++;
		}
		if ( $ok ) do_action( 'kurs_data_changed' );
		set_transient( 'kurs_req_notice_' . get_current_user_id(), [ 'ok' => $ok, 'action' => $action, 'errors' => $errors ], 60 );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=kurs-requests' ) );
		exit;
	}

	/** Igrač istog imena (bez obzira na velika/mala slova). */
	public static function find_player( string $name ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'kurs_igrac' AND post_status = 'publish' AND LOWER(post_title) = LOWER(%s) ORDER BY ID LIMIT 1", $name ) );
	}

	/** Primijeni odobreni zahtjev. */
	public static function apply( object $r ): true|\WP_Error {
		$team = (int) $r->team_id; $div = (int) $r->division_id; $d = $r->data;
		$roster_types = [ 'player_add', 'player_edit', 'player_remove' ];
		if ( in_array( $r->type, $roster_types, true ) ) {
			$div_o = Kurs_Season::get_division( $div );
			if ( ! $div_o ) return new \WP_Error( 'div', 'liga više ne postoji' );
			if ( Kurs_Season::is_locked( (int) $div_o->season_id ) ) return new \WP_Error( 'locked', 'sezona je zaključana' );
		}
		switch ( $r->type ) {
			case 'player_add':
				$pid = self::find_player( (string) $d['name'] );
				if ( ! $pid ) {
					$pid = wp_insert_post( [ 'post_title' => (string) $d['name'], 'post_type' => 'kurs_igrac', 'post_status' => 'publish' ], true );
					if ( is_wp_error( $pid ) ) return $pid;
					update_post_meta( $pid, '_kurs_player_type', ! empty( $d['gk'] ) ? 'goalkeeper' : 'player' );
				}
				$other = Kurs_Season::player_team_in_division( $pid, $div );
				if ( $other && $other !== $team ) Kurs_Season::remove_from_squad( $pid, $other, $div ); // prijelaz iz druge ekipe iste lige
				Kurs_Season::add_to_squad( $pid, $team, $div, (int) ( $d['number'] ?? 0 ) );
				return true;
			case 'player_edit':
				$pid = (int) $d['player_id'];
				if ( get_post_type( $pid ) !== 'kurs_igrac' ) return new \WP_Error( 'pl', 'igrač ne postoji' );
				if ( isset( $d['name'] ) && $d['name'] !== get_the_title( $pid ) ) {
					wp_update_post( [ 'ID' => $pid, 'post_title' => (string) $d['name'], 'post_name' => wp_unique_post_slug( sanitize_title( (string) $d['name'] ), $pid, 'publish', 'kurs_igrac', 0 ) ] );
				}
				if ( Kurs_Season::player_team_in_division( $pid, $div ) === $team ) Kurs_Season::add_to_squad( $pid, $team, $div, (int) ( $d['number'] ?? 0 ) );
				return true;
			case 'player_remove':
				Kurs_Season::remove_from_squad( (int) $d['player_id'], $team, $div );
				return true;
			case 'logo':
				if ( ! wp_attachment_is_image( (int) $d['att'] ) ) return new \WP_Error( 'att', 'slika je obrisana' );
				set_post_thumbnail( $team, (int) $d['att'] );
				return true;
			case 'sponsor_add':
			case 'sponsor_remove':
				$raw  = get_post_meta( $team, '_kurs_team_banners', true );
				$list = is_array( $raw ) ? $raw : ( is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : [] );
				$list = is_array( $list ) ? $list : [];
				if ( ! $list && ( $old = (int) get_post_meta( $team, '_kurs_team_banner', true ) ) ) $list = [ [ 'id' => $old, 'link' => (string) get_post_meta( $team, '_kurs_team_banner_link', true ) ] ];
				if ( $r->type === 'sponsor_add' ) {
					if ( ! wp_attachment_is_image( (int) $d['att'] ) ) return new \WP_Error( 'att', 'slika je obrisana' );
					$list[] = [ 'id' => (int) $d['att'], 'link' => (string) $d['link'], 'name' => (string) $d['name'] ];
				} else {
					$list = array_values( array_filter( $list, fn( $b ) => (int) ( $b['id'] ?? 0 ) !== (int) $d['att'] ) );
				}
				update_post_meta( $team, '_kurs_team_banners', wp_json_encode( $list ) );
				return true;
			case 'colors':
				update_post_meta( $team, '_kurs_team_color', (string) $d['color'] );
				if ( ! empty( $d['color2'] ) ) update_post_meta( $team, '_kurs_team_color2', (string) $d['color2'] ); else delete_post_meta( $team, '_kurs_team_color2' );
				return true;
			case 'info':
				foreach ( [ 'rep' => '_kurs_team_rep', 'rep_phone' => '_kurs_team_rep_phone', 'coach' => '_kurs_team_coach', 'coach_phone' => '_kurs_team_coach_phone' ] as $f => $meta ) {
					if ( array_key_exists( $f, $d ) ) update_post_meta( $team, $meta, (string) $d[ $f ] );
				}
				return true;
			case 'note':
				return true; // samo se označi kao pročitano
		}
		return new \WP_Error( 'type', 'nepoznata vrsta zahtjeva' );
	}
}
