<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Admin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_init',            [ $this, 'maybe_render_pdf' ] );
		add_action( 'admin_init',            [ $this, 'handle_forms' ] );
		add_action( 'admin_init',            [ $this, 'redirect_legacy_pages' ], 5 );
		add_filter( 'parent_file',           [ $this, 'menu_parent' ] );
		add_filter( 'submenu_file',          [ $this, 'menu_highlight' ], 10, 2 );
		add_action( 'wp_ajax_kurs_assign_team',    [ $this, 'ajax_assign_team' ] );
		add_action( 'wp_ajax_kurs_remove_team',    [ $this, 'ajax_remove_team' ] );
		add_action( 'wp_ajax_kurs_set_logo',            [ $this, 'ajax_set_logo' ] );
		add_action( 'wp_ajax_kurs_set_photo',           [ $this, 'ajax_set_photo' ] );
		add_action( 'wp_ajax_kurs_add_squad',           [ $this, 'ajax_add_squad' ] );
		add_action( 'wp_ajax_kurs_remove_squad',        [ $this, 'ajax_remove_squad' ] );
		add_action( 'wp_ajax_kurs_quick_add_player',    [ $this, 'ajax_quick_add_player' ] );
		add_action( 'wp_ajax_kurs_search_players',      [ $this, 'ajax_search_players' ] );
		add_action( 'wp_ajax_kurs_update_squad_player', [ $this, 'ajax_update_squad_player' ] );
		add_action( 'wp_ajax_kurs_create_team',          [ $this, 'ajax_create_team' ] );
		add_action( 'wp_ajax_kurs_generate_fixtures',        [ $this, 'ajax_generate_fixtures' ] );
		add_action( 'wp_ajax_kurs_record_add_event',    [ $this, 'ajax_record_add_event' ] );
		add_action( 'wp_ajax_kurs_record_del_event',    [ $this, 'ajax_record_del_event' ] );
		add_action( 'wp_ajax_kurs_record_del_foul',     [ $this, 'ajax_record_del_foul' ] );
		add_action( 'wp_ajax_kurs_record_update_event', [ $this, 'ajax_record_update_event' ] );
		add_action( 'wp_ajax_kurs_record_set_captain',  [ $this, 'ajax_record_set_captain' ] );
		add_action( 'wp_ajax_kurs_record_set_absent',   [ $this, 'ajax_record_set_absent' ] );
		add_action( 'wp_ajax_kurs_record_set_referees', [ $this, 'ajax_record_set_referees' ] );
		add_action( 'wp_ajax_kurs_update_match',        [ $this, 'ajax_update_match' ] );
	}

	// ── Menus ─────────────────────────────────────────────────────────────────

	/*
	 * Izbornik: svako natjecanje ima SVOJU stranicu (aktualna sezona + odabir starijih)
	 * s karticama Pregled · Ekipe · Utakmice · Postavke — sve o ligi na jednom mjestu.
	 * Ekipe i igrači su zajednički registar (klub igra više sezona/natjecanja); u ligu
	 * se uvrštavaju na kartici „Ekipe" natjecanja.
	 */
	public function menus(): void {
		add_menu_page(
			'KURS Liga', 'KURS Liga', KURS_CAP,
			'kurs-dashboard', [ $this, 'pg_dashboard' ],
			'dashicons-shield', 25
		);
		add_submenu_page( 'kurs-dashboard', 'Pregled',         'Pregled',         KURS_CAP, 'kurs-dashboard', [ $this, 'pg_dashboard' ] );
		foreach ( self::HUBS as $slug => $league ) {
			$label = Kurs_Season::competition( $league )['label'] ?? $league;
			add_submenu_page( 'kurs-dashboard', $label, $label, KURS_CAP, $slug, [ $this, 'pg_hub' ] );
		}
		add_submenu_page( 'kurs-dashboard', 'Ekipe i igrači',  'Ekipe i igrači',  KURS_CAP, 'kurs-teams',     [ $this, 'pg_teams' ] );
		add_submenu_page( 'kurs-dashboard', 'Sve sezone',      'Sve sezone',      KURS_CAP, 'kurs-seasons',   [ $this, 'pg_seasons' ] );
		add_submenu_page( 'kurs-dashboard', 'Postavke',        'Postavke',        KURS_CAP, 'kurs-settings',  [ $this, 'pg_settings' ] );
		// Skrivene: zapisnik se otvara iz utakmice; stare adrese preusmjeravaju na stranicu natjecanja.
		add_submenu_page( null, 'Zapisnik utakmice', 'Zapisnik', KURS_CAP, 'kurs-match-record', [ $this, 'pg_match_record' ] );
		foreach ( [ 'kurs-season-setup', 'kurs-matches', 'kurs-roster' ] as $legacy ) {
			add_submenu_page( null, 'KURS Liga', 'KURS Liga', KURS_CAP, $legacy, '__return_null' );
		}
	}

	/** Stranice natjecanja u izborniku: slug → Kurs_Season::COMPETITIONS ključ. */
	public const HUBS = [ 'kurs-kc' => 'kc_liga', 'kurs-pan' => 'pan_ljetna' ];

	/** Slug admin stranice natjecanja za ligu sezone. */
	public static function hub_slug( string $league ): string {
		return Kurs_Season::is_pan_league( $league ) ? 'kurs-pan' : 'kurs-kc';
	}

	/**
	 * URL stranice natjecanja za sezonu: kartica (pregled|ekipe|utakmice|postavke) + dodatni parametri.
	 * Sezona koja ne postoji → stranica KC Lige (ona sama odabere aktualnu sezonu).
	 */
	public static function hub_url( int $season_id, string $tab = '', array $args = [] ): string {
		$season = $season_id ? Kurs_Season::get( $season_id ) : null;
		$query  = [ 'page' => $season ? self::hub_slug( (string) $season->league ) : 'kurs-kc' ];
		if ( $season ) $query['season_id'] = (int) $season->id;
		if ( $tab && $tab !== 'pregled' ) $query['tab'] = $tab;
		return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
	}

	/** Sezona kojoj pripada skupina (0 ako ne postoji). */
	public static function season_of_group( int $group_id ): int {
		global $wpdb;
		$p = $wpdb->prefix . KURS_PREFIX;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT d.season_id FROM {$p}groups g JOIN {$p}phases ph ON ph.id = g.phase_id
			 JOIN {$p}divisions d ON d.id = ph.division_id WHERE g.id = %d", $group_id ) );
	}

	/** Sezona kojoj pripada liga (divizija). */
	public static function season_of_division( int $division_id ): int {
		$div = $division_id ? Kurs_Season::get_division( $division_id ) : null;
		return $div ? (int) $div->season_id : 0;
	}

	/** Sezona kojoj pripada utakmica (i playoff, group_id = 0). */
	public static function season_of_match( int $match_id ): int {
		$m = Kurs_Match::get( $match_id );
		if ( ! $m ) return 0;
		if ( (int) $m->group_id ) return self::season_of_group( (int) $m->group_id );
		$pm  = Kurs_Playoff::find_by_match_id( $match_id );
		$div = $pm ? Kurs_Season::get_division( (int) $pm->division_id ) : null;
		return $div ? (int) $div->season_id : 0;
	}

	/**
	 * Stare stranice (Postavi ligu, Utakmice, Roster, uređivanje sezone) → nove adrese,
	 * s istim parametrima, da stari linkovi/bookmarki i dalje rade.
	 */
	public function redirect_legacy_pages(): void {
		$page = sanitize_key( $_GET['page'] ?? '' );
		if ( ! $page || ! current_user_can( KURS_CAP ) || ! empty( $_POST ) ) return;
		$sid  = absint( $_GET['season_id'] ?? 0 );
		$keep = fn( array $keys ) => array_filter( array_intersect_key( wp_unslash( $_GET ), array_flip( $keys ) ), fn( $v ) => $v !== '' );
		$to   = null;

		if ( $page === 'kurs-season-setup' ) {
			$to = self::hub_url( $sid, 'ekipe', $keep( [ 'created', 'groups_saved', 'groups_error' ] ) );
		} elseif ( $page === 'kurs-matches' ) {
			if ( ! $sid && ! empty( $_GET['group_id'] ) ) $sid = self::season_of_group( absint( $_GET['group_id'] ) );
			$to = self::hub_url( $sid, 'utakmice', $keep( [ 'group_id', 'playoff_div', 'generated', 'reset', 'generated_season' ] ) );
		} elseif ( $page === 'kurs-roster' ) {
			$to = add_query_arg( array_filter( [
				'page' => 'kurs-teams', 'action' => 'edit',
				'id' => absint( $_GET['team_id'] ?? 0 ), 'division_id' => absint( $_GET['division_id'] ?? 0 ),
			] ), admin_url( 'admin.php' ) );
		} elseif ( $page === 'kurs-seasons' && ( $_GET['action'] ?? '' ) === 'edit' && ! empty( $_GET['id'] ) ) {
			$to = self::hub_url( absint( $_GET['id'] ), 'postavke' );
		}
		if ( $to ) { wp_safe_redirect( $to ); exit; }
	}

	/** Skrivene stranice (zapisnik) i dalje drže otvoren izbornik KURS Liga… */
	public function menu_parent( $parent_file ) {
		return ( ( $_GET['page'] ?? '' ) === 'kurs-match-record' ) ? 'kurs-dashboard' : $parent_file;
	}

	/** …i označe natjecanje kojem utakmica pripada. */
	public function menu_highlight( $submenu_file, $parent_file ) {
		if ( ( $_GET['page'] ?? '' ) !== 'kurs-match-record' ) return $submenu_file;
		$season = Kurs_Season::get( self::season_of_match( absint( $_GET['match_id'] ?? 0 ) ) );
		return $season ? self::hub_slug( (string) $season->league ) : $submenu_file;
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function assets( string $hook ): void {
		if ( strpos( $hook, 'kurs-' ) === false ) return;
		wp_enqueue_media(); // Potrebno za Media Library modal
		wp_enqueue_style( 'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
			[], '4.6.13'
		);
		wp_enqueue_script( 'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr',
			[], '4.6.13', true
		);
		wp_enqueue_script( 'flatpickr-hr',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/hr.js',
			[ 'flatpickr' ], '4.6.13', true
		);
		wp_enqueue_style( 'kurs-admin-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Semi+Condensed:ital,wght@0,600;0,700;0,800;1,700;1,800&family=Space+Mono:wght@400;700&display=swap',
			[], null
		);
		wp_enqueue_style(  'kurs-admin', KURS_URL . 'admin/assets/admin.css', [ 'kurs-admin-fonts', 'flatpickr' ], KURS_VERSION );
		wp_enqueue_script( 'kurs-admin', KURS_URL . 'admin/assets/admin.js',  [ 'jquery', 'flatpickr', 'flatpickr-hr' ], KURS_VERSION, true );
		wp_localize_script( 'kurs-admin', 'KursAjax', [
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'kurs_ajax' ),
		] );
	}

	// ── PDF zapisnik (standalone) ─────────────────────────────────────────────

	/**
	 * Renderira standalone PDF zapisnik na admin_init — PRIJE nego WP ispiše
	 * admin header. Inače se match-record-pdf.php učita tek u page callbacku
	 * (nakon admin-header.php), pa header() puca s "headers already sent" i PDF
	 * se iscrta unutar admin sučelja umjesto kao samostalna stranica za ispis.
	 */
	public function maybe_render_pdf(): void {
		if ( ( $_GET['page'] ?? '' ) !== 'kurs-match-record' ) return;
		if ( ( $_GET['format'] ?? '' ) !== 'pdf' )            return;
		if ( ! current_user_can( KURS_CAP ) )         return;

		// match-record.php pripremi podatke i pogodi PDF granu (include + exit).
		require KURS_DIR . 'admin/views/match-record.php';
		exit;
	}

	// ── Form handlers ─────────────────────────────────────────────────────────

	public function handle_forms(): void {
		if ( ! current_user_can( KURS_CAP ) ) return;
		if ( empty( $_POST['kurs_action'] ) ) return;

		match ( sanitize_key( $_POST['kurs_action'] ) ) {
			'create_season' => $this->do_create_season(),
			'edit_season'   => $this->do_edit_season(),
			'delete_season' => $this->do_delete_season(),
			'toggle_season_lock' => $this->do_toggle_season_lock(),
			'set_group_count'    => $this->do_set_group_count(),
			'carry_over_teams'   => $this->do_carry_over_teams(),
			'generate_season_fixtures' => $this->do_generate_season_fixtures(),
			'add_match'     => $this->do_add_match(),
			'finish_match'        => $this->do_finish_match(),
			'reopen_match'        => $this->do_reopen_match(),
			'generate_playoff'    => $this->do_generate_playoff(),
			'reset_playoff'       => $this->do_reset_playoff(),
			'finish_playoff_match'=> $this->do_finish_playoff_match(),
			'reopen_playoff_match'=> $this->do_reopen_playoff_match(),
			'update_playoff_match'=> $this->do_update_playoff_match(),
			'delete_match'  => $this->do_delete_match(),
			'add_team'         => $this->do_add_team(),
			'edit_team'        => $this->do_edit_team(),
			'delete_team'      => $this->do_delete_team(),
			'remove_team_logo'   => $this->do_remove_team_logo(),
			'remove_team_photo'  => $this->do_remove_team_photo(),
			'save_team_banners'  => $this->do_save_team_banners(),
			'save_season_referees' => $this->do_save_season_referees(),
			'save_ticker_settings' => $this->do_save_ticker_settings(),
			default              => null,
		};
	}

	/**
	 * Nova sezona = natjecanje + godina. Naziv i struktura (lige, faza, skupine)
	 * nastaju automatski prema Kurs_Season::COMPETITIONS; zatim ide dodjela ekipa.
	 */
	private function do_create_season(): void {
		check_admin_referer( 'kurs_create_season' );
		$league = sanitize_key( $_POST['league'] ?? '' );
		$year   = absint( $_POST['year'] ?? 0 );
		if ( ! Kurs_Season::competition( $league ) || $year < 2000 || $year > 2100 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=kurs-seasons&invalid=1' ) );
			exit;
		}
		if ( $existing = Kurs_Season::find_for( $league, $year ) ) {
			wp_safe_redirect( admin_url( "admin.php?page=kurs-seasons&exists={$existing}" ) );
			exit;
		}
		$id = Kurs_Season::create( [
			'league'             => $league,
			'name'               => Kurs_Season::default_name( $league, $year ),
			'year'               => $year,
			'max_fouls_per_half' => $_POST['max_fouls_per_half'] ?? 5,
		] );
		if ( $id ) {
			Kurs_Season::scaffold( $id, $league, absint( $_POST['pan_groups'] ?? 4 ) );
			// Suci se prenose iz prethodne sezone natjecanja (dalje se uređuju u kartici Postavke).
			if ( $prev = Kurs_Season::previous_with_referees( $id ) ) {
				Kurs_Season::set_referees( $id, Kurs_Season::get_referees( (int) $prev->id ) );
			}
			// Starije aktivne sezone natjecanja idu u arhivu — inače i dalje pune „Čeka rezultat" i Pregled.
			$archived = [];
			foreach ( Kurs_Season::for_league( Kurs_Season::is_pan_league( $league ) ) as $old ) {
				if ( (int) $old->year < $year && $old->status === 'active' ) {
					Kurs_Season::update( (int) $old->id, [ 'status' => 'archived' ] );
					$archived[] = (int) $old->id;
				}
			}
			wp_safe_redirect( self::hub_url( $id, 'ekipe', array_filter( [ 'created' => 1, 'archived' => implode( ',', $archived ) ] ) ) );
			exit;
		}
	}

	/** Prijenos ekipa (i rostera s brojevima dresova) iz prethodne sezone natjecanja — kartica Ekipe. */
	private function do_carry_over_teams(): void {
		check_admin_referer( 'kurs_carry_over_teams' );
		$sid  = absint( $_POST['season_id'] ?? 0 );
		$back = self::hub_url( $sid, 'ekipe' );
		self::guard_unlocked( $sid, $back );
		$prev  = Kurs_Season::previous_season( $sid ); // izvor određuje server, ne forma
		$carry = array_filter( array_map( 'absint', (array) ( $_POST['carry'] ?? [] ) ) );
		if ( ! $prev || ! $carry ) { wp_safe_redirect( $back ); exit; }
		$n = Kurs_Season::carry_over_teams( (int) $prev->id, $sid, $carry, ! empty( $_POST['with_roster'] ) );
		wp_safe_redirect( add_query_arg( [ 'carried' => $n['teams'], 'carried_players' => $n['players'], 'carried_skipped' => $n['skipped'] ], $back ) );
		exit;
	}

	/** Pan: promjena broja skupina (2 ↔ 4) dok je to sigurno. */
	private function do_set_group_count(): void {
		check_admin_referer( 'kurs_set_group_count' );
		$sid = absint( $_POST['season_id'] );
		$did = absint( $_POST['division_id'] );
		$url = self::hub_url( $sid, 'ekipe' );
		if ( Kurs_Season::is_locked( $sid ) ) { wp_safe_redirect( $url ); exit; }

		$res = Kurs_Season::set_group_count( $did, absint( $_POST['count'] ?? 0 ) );
		$url = is_wp_error( $res )
			? add_query_arg( 'groups_error', rawurlencode( $res->get_error_message() ), $url )
			: add_query_arg( 'groups_saved', 1, $url );
		wp_safe_redirect( $url );
		exit;
	}

	/** Raspored (jednokružno) za SVE skupine sezone odjednom — postojeće utakmice se ne dupliraju. */
	private function do_generate_season_fixtures(): void {
		check_admin_referer( 'kurs_generate_season_fixtures' );
		$sid = absint( $_POST['season_id'] );
		if ( Kurs_Season::is_locked( $sid ) ) {
			wp_safe_redirect( self::hub_url( $sid, 'utakmice' ) );
			exit;
		}
		$created = 0;
		foreach ( Kurs_Season::get_divisions( $sid ) as $div ) {
			$ph = Kurs_Season::group_phase( (int) $div->id );
			if ( ! $ph ) continue;
			foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
				$created += Kurs_Match::generate_round_robin( (int) $g->id );
			}
		}
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'generated_season' => $created ] ) );
		exit;
	}

	private static function parse_hr_date( string $raw ): ?string {
		$raw = trim( $raw );
		if ( ! $raw ) return null;
		// Pretvori dd.mm.yyyy hh:mm → Y-m-d H:i:s za MySQL
		$dt = DateTime::createFromFormat( 'd.m.Y H:i', $raw )
		   ?: DateTime::createFromFormat( 'd.m.Y', $raw )
		   ?: ( strtotime( $raw ) ? ( new DateTime( '@' . strtotime( $raw ) ) ) : null );
		return $dt ? $dt->format( 'Y-m-d H:i:s' ) : null;
	}

	private function do_add_match(): void {
		check_admin_referer( 'kurs_add_match' );
		$gid  = absint( $_POST['group_id'] );
		$sid  = absint( $_POST['season_id'] ?? 0 ) ?: self::season_of_group( $gid );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'utakmice', [ 'group_id' => $gid ] ) );
		$date = ! empty( $_POST['match_date'] ) ? self::parse_hr_date( sanitize_text_field( $_POST['match_date'] ) ) : null;
		Kurs_Match::create(
			$gid,
			absint( $_POST['home_team'] ),
			absint( $_POST['away_team'] ),
			$date,
			absint( $_POST['round'] ?? 1 )
		);
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'group_id' => $gid ] ) );
		exit;
	}

	private function do_reopen_match(): void {
		check_admin_referer( 'kurs_reopen_match' );
		$mid = absint( $_POST['match_id'] );
		self::guard_unlocked( self::season_of_match( $mid ), admin_url( "admin.php?page=kurs-match-record&match_id={$mid}" ) );
		Kurs_Match::reopen( $mid );
		// Sinkroniziraj s playoff_matches ako je ovaj meč dio playoffa
		$pm = Kurs_Playoff::find_by_match_id( $mid );
		if ( $pm ) Kurs_Playoff::reopen_match( (int) $pm->id );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-match-record&match_id={$mid}" ) );
		exit;
	}

	private function do_finish_match(): void {
		check_admin_referer( 'kurs_finish_match' );
		$mid    = absint( $_POST['match_id'] );
		$sid    = absint( $_POST['season_id'] ?? 0 ) ?: self::season_of_match( $mid );
		$record = admin_url( "admin.php?page=kurs-match-record&match_id={$mid}" );
		self::guard_unlocked( $sid, $record );
		$home = absint( $_POST['home_score'] );
		$away = absint( $_POST['away_score'] );
		// Playoff meč zaključuje Kurs_Playoff (penali kod neriješenog, prolaz pobjednika) — on zaključi i zapisnik.
		$pm  = Kurs_Playoff::find_by_match_id( $mid );
		$res = $pm ? Kurs_Playoff::finish_match( (int) $pm->id, $home, $away, ...self::post_pens() ) : Kurs_Match::finish( $mid, $home, $away );
		$err = is_wp_error( $res ) ? [ 'pm_error' => 'draw' ] : [];
		if ( ! empty( $_POST['from_record'] ) ) {
			wp_safe_redirect( add_query_arg( $err, $record ) );
		} else {
			$m = Kurs_Match::get( $mid );
			wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'group_id' => (int) ( $m->group_id ?? 0 ) ] + $err ) );
		}
		exit;
	}

	private function do_edit_season(): void {
		check_admin_referer( 'kurs_edit_season' );
		$id     = absint( $_POST['season_id'] );
		$season = Kurs_Season::get( $id );
		$other  = $season ? Kurs_Season::find_for( (string) $season->league, absint( $_POST['year'] ?? 0 ) ) : 0;
		if ( $other && $other !== $id ) { // dvije sezone istog natjecanja iste godine zbunile bi „aktualnu" i izbornike
			wp_safe_redirect( self::hub_url( $id, 'postavke', [ 'year_taken' => $other ] ) );
			exit;
		}
		// Natjecanje (league) se nakon kreiranja ne mijenja — o njemu ovisi struktura lige.
		Kurs_Season::update( $id, [
			'name'               => $_POST['name']   ?? '',
			'year'               => $_POST['year']   ?? date( 'Y' ),
			'status'             => $_POST['status'] ?? 'active',
			'max_fouls_per_half' => $_POST['max_fouls_per_half'] ?? 5,
		] );
		wp_safe_redirect( self::hub_url( $id, 'postavke', [ 'updated' => 1 ] ) );
		exit;
	}

	/** Povratak na stranicu s koje je forma poslana (samo admin URL-ovi), inače $fallback. */
	private static function back_to( string $fallback ): string {
		$to = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		return ( $to && str_starts_with( $to, admin_url() ) ) ? $to : $fallback;
	}

	/**
	 * Zaključana sezona = konačni podaci. Forma se vraća na $back bez izmjene; AJAX vraća
	 * grešku s kodom `season_locked` (admin.js je prikaže kao poruku).
	 */
	private static function guard_unlocked( int $season_id, string $back = '' ): void {
		if ( ! $season_id || ! Kurs_Season::is_locked( $season_id ) ) return;
		if ( wp_doing_ajax() ) {
			wp_send_json_error( [ 'code' => 'season_locked', 'message' => 'Sezona je zaključana — podaci su konačni. Otključati je možeš u kartici Postavke natjecanja.' ] );
		}
		wp_safe_redirect( $back ?: self::hub_url( $season_id ) );
		exit;
	}

	/** Penali iz forme zaključivanja playoff meča (prazno polje = nije upisano). */
	private static function post_pens(): array {
		$pen = fn( string $k ) => ( isset( $_POST[ $k ] ) && $_POST[ $k ] !== '' ) ? absint( $_POST[ $k ] ) : null;
		return [ $pen( 'home_pen' ), $pen( 'away_pen' ) ];
	}

	private function do_delete_season(): void {
		check_admin_referer( 'kurs_delete_season' );
		$sid = absint( $_POST['season_id'] );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'postavke' ) );
		Kurs_Season::delete( $sid );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-seasons&deleted=1' ) );
		exit;
	}

	private function do_toggle_season_lock(): void {
		check_admin_referer( 'kurs_toggle_lock' );
		$id   = absint( $_POST['season_id'] );
		$lock = ! empty( $_POST['lock'] );          // 1 = zaključaj, prazno = otključaj
		Kurs_Season::set_locked( $id, $lock );
		do_action( 'kurs_data_changed' );
		wp_safe_redirect( add_query_arg( $lock ? 'locked' : 'unlocked', 1,
			remove_query_arg( [ 'locked', 'unlocked', 'updated' ], self::back_to( admin_url( 'admin.php?page=kurs-seasons' ) ) ) ) );
		exit;
	}

	private static function sanitize_team_color( string $raw ): string {
		$raw = trim( $raw );
		return ( $raw && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $raw ) ) ? $raw : '#1a3a6b';
	}

	private function do_add_team(): void {
		check_admin_referer( 'kurs_add_team' );
		$name = sanitize_text_field( $_POST['team_name'] ?? '' );
		global $wpdb;
		$dup = $name ? (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'kurs_ekipa' AND post_status = 'publish' AND post_title = %s LIMIT 1", $name
		) ) : 0;
		if ( $dup ) { // isti klub se upisuje jednom (kao i kod brzog dodavanja na stranici natjecanja)
			wp_safe_redirect( add_query_arg( 'exists', $dup, admin_url( 'admin.php?page=kurs-teams' ) ) );
			exit;
		}
		if ( $name ) {
			$id = wp_insert_post( [
				'post_title'  => $name,
				'post_type'   => 'kurs_ekipa',
				'post_status' => 'publish',
			] );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_kurs_team_color', self::sanitize_team_color( $_POST['team_color'] ?? '' ) );
			}
		}
		wp_safe_redirect( add_query_arg( 'added', 1, self::back_to( admin_url( 'admin.php?page=kurs-teams' ) ) ) );
		exit;
	}

	private function do_edit_team(): void {
		check_admin_referer( 'kurs_edit_team' );
		$id   = absint( $_POST['team_id'] );
		$name = sanitize_text_field( $_POST['team_name'] ?? '' );
		if ( $id && $name ) {
			wp_update_post( [ 'ID' => $id, 'post_title' => $name ] );
			update_post_meta( $id, '_kurs_team_color', self::sanitize_team_color( $_POST['team_color'] ?? '' ) );
			update_post_meta( $id, '_kurs_team_coach',       sanitize_text_field( $_POST['coach_name']  ?? '' ) );
			update_post_meta( $id, '_kurs_team_coach_phone', sanitize_text_field( $_POST['coach_phone'] ?? '' ) );
			update_post_meta( $id, '_kurs_team_rep',         sanitize_text_field( $_POST['rep_name']    ?? '' ) );
			update_post_meta( $id, '_kurs_team_rep_phone',   sanitize_text_field( $_POST['rep_phone']   ?? '' ) );
			update_post_meta( $id, '_kurs_team_captain',     absint( $_POST['team_captain'] ?? 0 ) );
		}
		$redirect = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : admin_url( 'admin.php?page=kurs-teams&updated=1' );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function do_remove_team_logo(): void {
		$id = absint( $_POST['team_id'] ?? 0 );
		check_admin_referer( 'kurs_remove_logo_' . $id );
		if ( $id ) delete_post_thumbnail( $id );
		$redirect = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : admin_url( 'admin.php?page=kurs-teams' );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function do_remove_team_photo(): void {
		$id = absint( $_POST['team_id'] ?? 0 );
		check_admin_referer( 'kurs_remove_photo_' . $id );
		if ( $id ) delete_post_meta( $id, '_kurs_team_photo' );
		$redirect = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : admin_url( 'admin.php?page=kurs-teams' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/** Spremi sve banere (sponzore) ekipe — više slika, svaka sa svojim linkom. */
	private function do_save_team_banners(): void {
		$id = absint( $_POST['team_id'] ?? 0 );
		check_admin_referer( 'kurs_save_banners_' . $id );
		if ( $id ) {
			$ids   = array_map( 'absint', (array) ( $_POST['banner_ids'] ?? [] ) );
			$links = array_map( 'esc_url_raw', (array) wp_unslash( $_POST['banner_links'] ?? [] ) );
			$banners = [];
			foreach ( $ids as $i => $att_id ) {
				if ( ! $att_id ) continue;
				$banners[] = [ 'id' => $att_id, 'link' => $links[ $i ] ?? '' ];
			}
			update_post_meta( $id, '_kurs_team_banners', wp_json_encode( $banners ) );
		}
		$redirect = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : admin_url( 'admin.php?page=kurs-teams' );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function do_save_ticker_settings(): void {
		check_admin_referer( 'kurs_ticker_settings' );
		$val = ( ( $_POST['kurs_ticker_offseason'] ?? '' ) === 'hidden' ) ? 'hidden' : 'champions';
		update_option( Kurs_Ticker::OPT_OFFSEASON, $val );
		if ( class_exists( 'Kurs_Ticker' ) ) Kurs_Ticker::flush();
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-settings&ticker_saved=1' ) );
		exit;
	}

	/** Suci sezone (kartica Postavke) — retci Ime/Prezime; u zapisniku se nude kao prijedlozi. */
	private function do_save_season_referees(): void {
		check_admin_referer( 'kurs_save_season_referees' );
		$id      = absint( $_POST['season_id'] ?? 0 );
		$ime     = array_filter( (array) wp_unslash( $_POST['ref_ime'] ?? [] ), 'is_scalar' );
		$prezime = array_filter( (array) wp_unslash( $_POST['ref_prezime'] ?? [] ), 'is_scalar' );
		$rows    = [];
		foreach ( $prezime as $i => $p ) $rows[] = [ 'ime' => $ime[ $i ] ?? '', 'prezime' => $p ];
		if ( $id ) Kurs_Season::set_referees( $id, $rows );
		wp_safe_redirect( self::hub_url( $id, 'postavke', [ 'refs_saved' => 1 ] ) );
		exit;
	}

	/** Ekipa s utakmicama se ne briše (raspale bi se ljestvice i zapisnici); bez njih → očisti i lige/rostere. */
	private function do_delete_team(): void {
		check_admin_referer( 'kurs_delete_team' );
		global $wpdb;
		$p   = $wpdb->prefix . KURS_PREFIX;
		$tid = absint( $_POST['team_id'] );
		$n   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}matches WHERE home_team_id = %d OR away_team_id = %d", $tid, $tid ) );
		if ( $n ) {
			wp_safe_redirect( admin_url( "admin.php?page=kurs-teams&in_use={$n}" ) );
			exit;
		}
		$wpdb->delete( "{$p}group_teams", [ 'team_id' => $tid ] );
		$wpdb->delete( "{$p}squads",      [ 'team_id' => $tid ] );
		wp_delete_post( $tid, true );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-teams&deleted=1' ) );
		exit;
	}

	private function do_delete_match(): void {
		check_admin_referer( 'kurs_delete_match' );
		$mid = absint( $_POST['match_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		$m   = Kurs_Match::get( $mid );
		$gid = $m ? (int) $m->group_id : 0;
		$sid = $sid ?: self::season_of_group( $gid );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'utakmice', [ 'group_id' => $gid ] ) );
		Kurs_Match::delete( $mid ); // sam okida kurs_data_changed
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'group_id' => $gid ] ) );
		exit;
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_assign_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$gid = absint( $_POST['group_id'] );
		$tid = absint( $_POST['team_id'] );
		if ( ! $gid || get_post_type( $tid ) !== 'kurs_ekipa' ) wp_send_json_error( [ 'message' => 'Nepoznata ekipa.' ] );
		if ( Kurs_Season::is_locked( self::season_of_group( $gid ) ) ) wp_send_json_error( [ 'message' => 'Sezona je zaključana.' ] );

		global $wpdb;
		$div_id = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT ph.division_id FROM ' . $wpdb->prefix . KURS_PREFIX . 'groups g JOIN ' . $wpdb->prefix . KURS_PREFIX . 'phases ph ON ph.id = g.phase_id WHERE g.id = %d', $gid ) );
		$already = Kurs_Season::team_group_in_division( $div_id, $tid );
		if ( $already ) {
			wp_send_json_error( [ 'message' => (int) $already->id === $gid
				? 'Ekipa je već u ovoj ligi.'
				: sprintf( 'Ekipa je već u skupini „%s" — ekipa igra samo u jednoj skupini.', $already->name ) ] );
		}
		Kurs_Season::assign_team( $gid, $tid );
		wp_send_json_success( [
			'team_id'   => $tid,
			'team_name' => get_post_field( 'post_title', $tid ),
			'badge'     => kurs_team_badge_html( $tid, 22 ),
			'roster'    => add_query_arg( [ 'page' => 'kurs-teams', 'action' => 'edit', 'id' => $tid, 'division_id' => $div_id ], admin_url( 'admin.php' ) ),
		] );
	}

	public function ajax_remove_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$gid = absint( $_POST['group_id'] );
		$tid = absint( $_POST['team_id'] );
		if ( Kurs_Season::is_locked( self::season_of_group( $gid ) ) ) wp_send_json_error( [ 'message' => 'Sezona je zaključana.' ] );
		$n = Kurs_Season::team_match_count( $gid, $tid );
		if ( $n ) {
			wp_send_json_error( [ 'message' => sprintf( 'Ekipa već ima %d utakmica u ovoj ligi. Najprije ih obriši u kartici Utakmice, pa je onda makni iz lige.', $n ) ] );
		}
		Kurs_Season::remove_team( $gid, $tid );
		wp_send_json_success();
	}

	// ── AJAX: Logo ────────────────────────────────────────────────────────────

	public function ajax_set_logo(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$team_id = absint( $_POST['team_id'] );
		$att_id  = absint( $_POST['attachment_id'] );

		if ( get_post_type( $team_id ) !== 'kurs_ekipa' || ! wp_attachment_is_image( $att_id ) ) wp_send_json_error( 'Nevažeći podaci.' );

		set_post_thumbnail( $team_id, $att_id );
		$url = wp_get_attachment_image_url( $att_id, [ 40, 40 ] );

		wp_send_json_success( [ 'url' => $url ] );
	}

	public function ajax_set_photo(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$team_id = absint( $_POST['team_id'] );
		$att_id  = absint( $_POST['attachment_id'] );

		if ( get_post_type( $team_id ) !== 'kurs_ekipa' || ! wp_attachment_is_image( $att_id ) ) wp_send_json_error( 'Nevažeći podaci.' );

		update_post_meta( $team_id, '_kurs_team_photo', $att_id );
		$url = wp_get_attachment_image_url( $att_id, 'medium' );

		wp_send_json_success( [ 'url' => $url ] );
	}

	// ── AJAX: Roster ──────────────────────────────────────────────────────────

	public function ajax_add_squad(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$player_id   = absint( $_POST['player_id'] );
		$team_id     = absint( $_POST['team_id'] );
		$division_id = absint( $_POST['division_id'] );
		$jersey      = absint( $_POST['jersey'] ?? 0 );

		if ( ! $player_id || ! $team_id || ! $division_id ) wp_send_json_error( 'Nevažeći podaci.' );
		self::guard_unlocked( self::season_of_division( $division_id ) );
		// Igrač u jednoj ligi igra samo za jednu ekipu (u drugoj ligi iste sezone smije).
		$other = Kurs_Season::player_team_in_division( $player_id, $division_id );
		if ( $other && $other !== $team_id ) {
			wp_send_json_error( sprintf( '%s je u ovoj ligi već u ekipi „%s". Najprije ga makni iz te ekipe.', get_the_title( $player_id ), get_the_title( $other ) ) );
		}

		Kurs_Season::add_to_squad( $player_id, $team_id, $division_id, $jersey );

		wp_send_json_success( [
			'player_id'   => $player_id,
			'player_name' => get_the_title( $player_id ),
			'jersey'      => $jersey,
			'type'        => kurs_player_type( $player_id ),
		] );
	}

	public function ajax_remove_squad(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		self::guard_unlocked( self::season_of_division( absint( $_POST['division_id'] ) ) );
		Kurs_Season::remove_from_squad(
			absint( $_POST['player_id'] ),
			absint( $_POST['team_id'] ),
			absint( $_POST['division_id'] )
		);
		wp_send_json_success();
	}

	public function ajax_quick_add_player(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( ! $name ) wp_send_json_error( 'Ime igrača je obavezno.' );
		self::guard_unlocked( self::season_of_division( absint( $_POST['division_id'] ?? 0 ) ) );

		$player_id = wp_insert_post( [
			'post_title'  => $name,
			'post_type'   => 'kurs_igrac',
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $player_id ) ) wp_send_json_error( $player_id->get_error_message() );

		$type = ( ( $_POST['type'] ?? '' ) === 'goalkeeper' ) ? 'goalkeeper' : 'player';
		update_post_meta( $player_id, '_kurs_player_type', $type );

		$team_id     = absint( $_POST['team_id'] ?? 0 );
		$division_id = absint( $_POST['division_id'] ?? 0 );
		$jersey      = absint( $_POST['jersey'] ?? 0 );

		if ( $team_id && $division_id ) {
			Kurs_Season::add_to_squad( $player_id, $team_id, $division_id, $jersey );
		}

		wp_send_json_success( [
			'player_id'   => $player_id,
			'player_name' => $name,
			'jersey'      => $jersey,
			'type'        => $type,
		] );
	}

	// ── AJAX: Brzo kreiranje ekipe (iz dodjele ekipa) ────────────────────────

	public function ajax_create_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$name = sanitize_text_field( $_POST['team_name'] ?? '' );
		if ( ! $name ) wp_send_json_error( 'Naziv ekipe je obavezan.' );

		// Provjeri duplikat
		global $wpdb;
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'kurs_ekipa' AND post_status = 'publish' AND post_title = %s
			 LIMIT 1",
			$name
		) );

		if ( $existing_id ) {
			wp_send_json_error( [
				'code'    => 'duplicate',
				'message' => "Ekipa \"{$name}\" već postoji.",
				'team_id' => $existing_id,
				'name'    => $name,
			] );
		}

		$team_id = wp_insert_post( [
			'post_title'  => $name,
			'post_type'   => 'kurs_ekipa',
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $team_id ) ) wp_send_json_error( $team_id->get_error_message() );

		wp_send_json_success( [ 'team_id' => $team_id, 'name' => $name ] );
	}

	// ── AJAX: Pretraga igrača (autocomplete + duplikat check) ────────────────

	public function ajax_search_players(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$term = sanitize_text_field( $_POST['term'] ?? '' );
		if ( strlen( $term ) < 1 ) {
			wp_send_json_success( [] );
		}

		global $wpdb;
		$like    = '%' . $wpdb->esc_like( $term ) . '%';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title
				 FROM   {$wpdb->posts}
				 WHERE  post_type = 'kurs_igrac'
				   AND  post_status = 'publish'
				   AND  post_title LIKE %s
				 ORDER  BY post_title
				 LIMIT  10",
				$like
			)
		);

		wp_send_json_success(
			array_map( fn( $r ) => [ 'id' => (int) $r->ID, 'name' => $r->post_title ], $results ?: [] )
		);
	}

	// ── AJAX: Uredi igrača u rosteri (ime + broj dresa) ───────────────────────

	public function ajax_update_squad_player(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$player_id   = absint( $_POST['player_id'] );
		$team_id     = absint( $_POST['team_id'] );
		$division_id = absint( $_POST['division_id'] );
		$jersey      = absint( $_POST['jersey'] ?? 0 );
		$name        = sanitize_text_field( $_POST['name'] ?? '' );

		if ( ! $player_id || ! $team_id || ! $division_id ) {
			wp_send_json_error( 'Nevažeći podaci.' );
		}
		self::guard_unlocked( self::season_of_division( $division_id ) );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . KURS_PREFIX . 'squads',
			[ 'jersey_no' => $jersey ?: null ],
			[ 'player_id' => $player_id, 'team_id' => $team_id, 'division_id' => $division_id ]
		);

		if ( $name ) {
			wp_update_post( [ 'ID' => $player_id, 'post_title' => $name ] );
		}

		if ( isset( $_POST['type'] ) ) {
			update_post_meta( $player_id, '_kurs_player_type', ( $_POST['type'] === 'goalkeeper' ) ? 'goalkeeper' : 'player' );
		}

		wp_send_json_success( [
			'player_id' => $player_id,
			'name'      => $name ?: get_the_title( $player_id ),
			'jersey'    => $jersey,
			'type'      => kurs_player_type( $player_id ),
		] );
	}

	// ── AJAX: Generiraj raspored jedne skupine (svaka s svakom) ──────────────

	public function ajax_generate_fixtures(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$group_id = absint( $_POST['group_id'] );
		if ( ! $group_id ) wp_send_json_error( 'Nedostaje group_id.' );
		self::guard_unlocked( self::season_of_group( $group_id ) );
		if ( count( Kurs_Season::get_group_teams( $group_id ) ) < 2 ) wp_send_json_error( 'Potrebne su najmanje 2 ekipe.' );

		wp_send_json_success( [ 'created' => Kurs_Match::generate_round_robin( $group_id ) ] );
	}

	// ── AJAX: Ažuriraj utakmicu (datum, kolo) ────────────────────────────────

	public function ajax_update_match(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$match_id = absint( $_POST['match_id'] );
		$date  = ! empty( $_POST['match_date'] ) ? self::parse_hr_date( sanitize_text_field( $_POST['match_date'] ) ) : null;
		$round = absint( $_POST['round'] ?? 1 ) ?: 1;

		if ( ! $match_id ) wp_send_json_error( 'Nevažeći match_id.' );
		self::guard_unlocked( self::season_of_match( $match_id ) );

		global $wpdb;
		$pm = Kurs_Playoff::find_by_match_id( $match_id );
		$wpdb->update(
			$wpdb->prefix . KURS_PREFIX . 'matches',
			$pm ? [ 'match_date' => $date ] : [ 'match_date' => $date, 'round' => $round ], // playoff nema kolo
			[ 'id' => $match_id ]
		);
		if ( $pm ) Kurs_Playoff::update_date( (int) $pm->id, $date ); // termin i u bracketu
		do_action( 'kurs_data_changed' ); // ticker / aktivna liga ovise o datumu i kolu

		$ts = $date ? strtotime( $date ) : 0;
		wp_send_json_success( [
			'match_date'   => $date,
			'display_day'  => $ts ? date( 'j.n.', $ts ) : '',
			'display_time' => $ts ? date( 'H:i', $ts )  : '',
			'round'        => $round,
		] );
	}

	// ── AJAX: Zapisnik — dodaj event ─────────────────────────────────────────

	public function ajax_record_add_event(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$match_id  = absint( $_POST['match_id'] );
		$team_id   = absint( $_POST['team_id'] );
		$player_id = absint( $_POST['player_id'] ?? 0 ) ?: null;
		$type      = sanitize_key( $_POST['event_type'] ?? 'goal' );
		$minute    = absint( $_POST['minute'] ?? 0 ) ?: null;
		$half      = ( absint( $_POST['half'] ?? 1 ) === 2 ) ? 2 : 1;

		if ( ! $match_id || ! $team_id ) wp_send_json_error( 'Nevažeći podaci.' );
		self::guard_unlocked( self::season_of_match( $match_id ) );
		if ( ! in_array( $type, [ 'goal', 'penalty', 'own_goal', 'yellow_card', 'second_yellow', 'red_card', 'foul' ], true ) ) {
			wp_send_json_error( 'Nevažeći tip eventa.' );
		}

		// Isključeni igrač (crveni / drugi žuti / 2× žuti) ne smije dobiti nove golove/kartone.
		if ( $type !== 'foul' && $player_id && in_array( $player_id, Kurs_Match::get_sent_off( $match_id ), true ) ) {
			wp_send_json_error( 'Igrač je isključen iz utakmice — nije mu moguće pripisati nove događaje.' );
		}

		$event_id = Kurs_Match::add_event( $match_id, $type, $team_id, $player_id, $minute, $half );
		if ( ! $event_id ) wp_send_json_error( 'Greška pri dodavanju.' );

		$player_name = $player_id ? get_the_title( $player_id ) : '';

		wp_send_json_success( array_merge( [
			'event' => [
				'id'          => $event_id,
				'team_id'     => $team_id,
				'player_id'   => $player_id,
				'player_name' => $player_name,
				'event_type'  => $type,
				'minute'      => $minute ?: 0,
				'half'        => $half,
			],
		], $this->record_state( $match_id ) ) );
	}

	// ── AJAX: Zapisnik — obriši event ────────────────────────────────────────

	public function ajax_record_del_event(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$event_id = absint( $_POST['event_id'] );
		if ( ! $event_id ) wp_send_json_error( 'Nevažeći event_id.' );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT match_id FROM ' . $wpdb->prefix . KURS_PREFIX . 'match_events WHERE id = %d',
			$event_id
		) );
		if ( ! $row ) wp_send_json_error( 'Event ne postoji.' );
		self::guard_unlocked( self::season_of_match( (int) $row->match_id ) );

		Kurs_Match::delete_event( $event_id );

		wp_send_json_success( $this->record_state( (int) $row->match_id ) );
	}

	// ── AJAX: Zapisnik — ukloni zadnji prekršaj (undo) ───────────────────────

	public function ajax_record_del_foul(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$match_id = absint( $_POST['match_id'] );
		$team_id  = absint( $_POST['team_id'] );
		$half     = ( absint( $_POST['half'] ?? 1 ) === 2 ) ? 2 : 1;

		if ( ! $match_id || ! $team_id ) wp_send_json_error( 'Nevažeći podaci.' );
		self::guard_unlocked( self::season_of_match( $match_id ) );

		Kurs_Match::delete_last_foul( $match_id, $team_id, $half );
		wp_send_json_success( $this->record_state( $match_id ) );
	}

	// ── AJAX: Uredi postojeći događaj (ispravi pogrešan unos) ────────────────

	public function ajax_record_update_event(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$event_id = absint( $_POST['event_id'] );
		$match_id = absint( $_POST['match_id'] );
		$team_id  = absint( $_POST['team_id'] );
		$player   = absint( $_POST['player_id'] ?? 0 ) ?: null;
		$type     = sanitize_key( $_POST['event_type'] ?? '' );
		$minute   = isset( $_POST['minute'] ) ? max( 0, absint( $_POST['minute'] ) ) : null;
		$half     = ( absint( $_POST['half'] ?? 1 ) === 2 ) ? 2 : 1;

		$allowed = [ 'goal', 'penalty', 'own_goal', 'yellow_card', 'second_yellow', 'red_card' ];
		if ( ! $event_id || ! $match_id || ! $team_id || ! in_array( $type, $allowed, true ) ) wp_send_json_error( 'Nevažeći podaci.' );
		if ( $type === 'goal' && ! $player ) wp_send_json_error( 'Gol mora imati strijelca.' );
		self::guard_unlocked( self::season_of_match( $match_id ) );

		Kurs_Match::update_event( $event_id, $team_id, $player, $type, $minute, $half );

		$event = [
			'id'          => $event_id,
			'event_type'  => $type,
			'team_id'     => $team_id,
			'player_id'   => $player,
			'player_name' => $player ? get_the_title( $player ) : '',
			'minute'      => $minute,
			'half'        => $half,
		];
		wp_send_json_success( array_merge( [ 'event' => $event ], $this->record_state( $match_id ) ) );
	}

	// ── AJAX: Kapetan / prisutnost igrača ───────────────────────────────────

	public function ajax_record_set_captain(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$match_id = absint( $_POST['match_id'] );
		$team_id  = absint( $_POST['team_id'] );
		$player   = absint( $_POST['player_id'] ?? 0 );
		if ( ! $match_id || ! $team_id ) wp_send_json_error( 'Nevažeći podaci.' );
		self::guard_unlocked( self::season_of_match( $match_id ) );
		Kurs_Match::set_match_captain( $match_id, $team_id, $player );
		wp_send_json_success( [ 'team_id' => $team_id, 'player_id' => $player ] );
	}

	public function ajax_record_set_absent(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$match_id = absint( $_POST['match_id'] );
		$team_id  = absint( $_POST['team_id'] );
		$player   = absint( $_POST['player_id'] ?? 0 );
		$absent   = ! empty( $_POST['absent'] );
		if ( ! $match_id || ! $team_id || ! $player ) wp_send_json_error( 'Nevažeći podaci.' );
		self::guard_unlocked( self::season_of_match( $match_id ) );
		Kurs_Match::set_absent( $match_id, $team_id, $player, $absent );
		wp_send_json_success( [ 'player_id' => $player, 'absent' => $absent ] );
	}

	public function ajax_record_set_referees(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$match_id = absint( $_POST['match_id'] );
		if ( ! $match_id ) wp_send_json_error( 'Nevažeći podaci.' );
		self::guard_unlocked( self::season_of_match( $match_id ) );
		$r1 = sanitize_text_field( wp_unslash( $_POST['referee1'] ?? '' ) );
		$r2 = sanitize_text_field( wp_unslash( $_POST['referee2'] ?? '' ) );
		Kurs_Match::set_referees( $match_id, $r1, $r2 );
		Kurs_Season::add_referees( self::season_of_match( $match_id ), [ $r1, $r2 ] ); // zapamti među sucima sezone
		wp_send_json_success( [ 'referee1' => $r1, 'referee2' => $r2 ] );
	}

	// ── Helper: izračun rezultata iz eventa ──────────────────────────────────

	private function compute_score( object $match ): array {
		$h = 0; $a = 0;
		foreach ( Kurs_Match::get_events( (int) $match->id ) as $ev ) {
			if ( $ev->event_type === 'goal' || $ev->event_type === 'penalty' ) {
				if ( (int) $ev->team_id === (int) $match->home_team_id ) $h++; else $a++;
			} elseif ( $ev->event_type === 'own_goal' ) {
				if ( (int) $ev->team_id === (int) $match->home_team_id ) $a++; else $h++;
			}
		}
		return [ $h, $a ];
	}

	/** Trenutno stanje zapisnika (rezultat, akumulirani prekršaji, isključeni). */
	private function record_state( int $match_id ): array {
		$match = Kurs_Match::get( $match_id );
		$score = $match ? $this->compute_score( $match ) : [ 0, 0 ];
		return [
			'home_score'  => $score[0],
			'away_score'  => $score[1],
			'foul_counts' => Kurs_Match::get_foul_counts( $match_id ),
			'sent_off'    => Kurs_Match::get_sent_off( $match_id ),
		];
	}

	// ── Page renderers ────────────────────────────────────────────────────────

	public function pg_dashboard():    void { require KURS_DIR . 'admin/views/dashboard.php'; }
	public function pg_hub():          void { require KURS_DIR . 'admin/views/season-hub.php'; }
	public function pg_seasons():      void { require KURS_DIR . 'admin/views/seasons.php'; }
	public function pg_teams():        void { require KURS_DIR . 'admin/views/teams.php'; }
	public function pg_settings():     void { require KURS_DIR . 'admin/views/settings.php'; }
	// ── Playoff handlers ─────────────────────────────────────────────────────

	private function do_generate_playoff(): void {
		check_admin_referer( 'kurs_generate_playoff' );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 ) ?: self::season_of_division( $did );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] ) );
		$div = Kurs_Season::get_division( $did );
		if ( $div && empty( $div->has_playoff ) ) { // divizija se igra bez playoffa
			wp_safe_redirect( self::hub_url( $sid, 'utakmice' ) );
			exit;
		}
		Kurs_Playoff::generate( $did );
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did, 'generated' => 1 ] ) );
		exit;
	}

	private function do_reset_playoff(): void {
		check_admin_referer( 'kurs_reset_playoff' );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 ) ?: self::season_of_division( $did );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] ) );
		Kurs_Playoff::reset( $did );
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did, 'reset' => 1 ] ) );
		exit;
	}

	private function do_finish_playoff_match(): void {
		check_admin_referer( 'kurs_finish_playoff_match' );
		$id  = absint( $_POST['playoff_match_id'] );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 ) ?: self::season_of_division( $did );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] ) );
		$res = Kurs_Playoff::finish_match( $id, absint( $_POST['home_score'] ), absint( $_POST['away_score'] ), ...self::post_pens() );
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] + ( is_wp_error( $res ) ? [ 'pm_error' => 'draw' ] : [] ) ) );
		exit;
	}

	private function do_reopen_playoff_match(): void {
		check_admin_referer( 'kurs_reopen_playoff_match' );
		$id  = absint( $_POST['playoff_match_id'] );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 ) ?: self::season_of_division( $did );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] ) );
		Kurs_Playoff::reopen_match( $id );
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] ) );
		exit;
	}

	private function do_update_playoff_match(): void {
		check_admin_referer( 'kurs_update_playoff_match' );
		$id  = absint( $_POST['playoff_match_id'] );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 ) ?: self::season_of_division( $did );
		self::guard_unlocked( $sid, self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] ) );
		$res = Kurs_Playoff::update_teams(
			$id,
			absint( $_POST['home_team_id'] ?? 0 ) ?: null,
			absint( $_POST['away_team_id'] ?? 0 ) ?: null,
			sanitize_text_field( $_POST['home_label'] ?? '' ),
			sanitize_text_field( $_POST['away_label'] ?? '' )
		);
		if ( ! empty( $_POST['match_date'] ) ) {
			$date = self::parse_hr_date( $_POST['match_date'] );
			Kurs_Playoff::update_date( $id, $date ); // usklađuje i zapisnik
		}
		wp_safe_redirect( self::hub_url( $sid, 'utakmice', [ 'playoff_div' => $did ] + ( is_wp_error( $res ) ? [ 'pm_error' => 'teams' ] : [] ) ) );
		exit;
	}

	public function pg_match_record(): void { require KURS_DIR . 'admin/views/match-record.php'; }
}
