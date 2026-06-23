<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Admin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_init',            [ $this, 'maybe_render_pdf' ] );
		add_action( 'admin_init',            [ $this, 'handle_forms' ] );
		add_action( 'wp_ajax_kurs_add_group',      [ $this, 'ajax_add_group' ] );
		add_action( 'wp_ajax_kurs_del_group',      [ $this, 'ajax_del_group' ] );
		add_action( 'wp_ajax_kurs_assign_team',    [ $this, 'ajax_assign_team' ] );
		add_action( 'wp_ajax_kurs_remove_team',    [ $this, 'ajax_remove_team' ] );
		add_action( 'wp_ajax_kurs_set_logo',            [ $this, 'ajax_set_logo' ] );
		add_action( 'wp_ajax_kurs_set_banner',          [ $this, 'ajax_set_banner' ] );
		add_action( 'wp_ajax_kurs_set_photo',           [ $this, 'ajax_set_photo' ] );
		add_action( 'wp_ajax_kurs_add_squad',           [ $this, 'ajax_add_squad' ] );
		add_action( 'wp_ajax_kurs_remove_squad',        [ $this, 'ajax_remove_squad' ] );
		add_action( 'wp_ajax_kurs_quick_add_player',    [ $this, 'ajax_quick_add_player' ] );
		add_action( 'wp_ajax_kurs_search_players',      [ $this, 'ajax_search_players' ] );
		add_action( 'wp_ajax_kurs_update_squad_player', [ $this, 'ajax_update_squad_player' ] );
		add_action( 'wp_ajax_kurs_create_team',          [ $this, 'ajax_create_team' ] );
		add_action( 'wp_ajax_kurs_generate_fixtures',        [ $this, 'ajax_generate_fixtures' ] );
		add_action( 'wp_ajax_kurs_update_playoff_spots',     [ $this, 'ajax_update_playoff_spots' ] );
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

	public function menus(): void {
		add_menu_page(
			'KURS Liga', 'KURS Liga', KURS_CAP,
			'kurs-dashboard', [ $this, 'pg_dashboard' ],
			'dashicons-shield', 25
		);
		add_submenu_page( 'kurs-dashboard', 'Dashboard',    'Dashboard',    KURS_CAP, 'kurs-dashboard',    [ $this, 'pg_dashboard' ] );
		add_submenu_page( 'kurs-dashboard', 'Sezone',       'Sezone',       KURS_CAP, 'kurs-seasons',      [ $this, 'pg_seasons' ] );
		add_submenu_page( 'kurs-dashboard', 'Utakmice',     'Utakmice',     KURS_CAP, 'kurs-matches',      [ $this, 'pg_matches' ] );
		add_submenu_page( 'kurs-dashboard', 'Ekipe',        'Ekipe',        KURS_CAP, 'kurs-teams',        [ $this, 'pg_teams' ] );
		// Skrivene stranice — dostupne preko gumba "Postavi"/konteksta, ne u glavnom meniju.
		// "Postavi ligu" treba odabranu sezonu pa se otvara iz liste Sezona / Dashboarda, ne kao samostavna stavka.
		add_submenu_page( null, 'Postavi ligu',      'Postavi ligu', KURS_CAP, 'kurs-season-setup', [ $this, 'pg_setup' ] );
		add_submenu_page( null, 'Roster',            'Roster',       KURS_CAP, 'kurs-roster',       [ $this, 'pg_roster' ] );
		add_submenu_page( null, 'Zapisnik utakmice', 'Zapisnik',     KURS_CAP, 'kurs-match-record', [ $this, 'pg_match_record' ] );
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
			'add_division'  => $this->do_add_division(),
			'edit_division' => $this->do_edit_division(),
			'delete_division' => $this->do_delete_division(),
			'add_phase'     => $this->do_add_phase(),
			'delete_phase'  => $this->do_delete_phase(),
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
			'remove_team_banner' => $this->do_remove_team_banner(),
			'remove_team_photo'  => $this->do_remove_team_photo(),
			'save_team_banners'  => $this->do_save_team_banners(),
			'save_referees'      => $this->do_save_referees(),
			'save_ticker_settings' => $this->do_save_ticker_settings(),
			default              => null,
		};
	}

	private function do_create_season(): void {
		check_admin_referer( 'kurs_create_season' );
		$id = Kurs_Season::create( $_POST );
		if ( $id ) {
			wp_safe_redirect( admin_url( "admin.php?page=kurs-season-setup&season_id={$id}&step=2" ) );
			exit;
		}
	}

	private function do_add_division(): void {
		check_admin_referer( 'kurs_season_setup' );
		$sid         = absint( $_POST['season_id'] );
		$has_playoff = ! empty( $_POST['has_playoff'] ) ? 1 : 0;
		$advance     = max( 0, absint( $_POST['advance_per_group'] ?? 2 ) );
		Kurs_Season::create_division( $sid, sanitize_text_field( $_POST['div_name'] ), 0, $has_playoff, $advance );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-season-setup&season_id={$sid}&step=2" ) );
		exit;
	}

	private function do_edit_division(): void {
		check_admin_referer( 'kurs_season_setup' );
		$sid         = absint( $_POST['season_id'] );
		$did         = absint( $_POST['division_id'] );
		$has_playoff = ! empty( $_POST['has_playoff'] ) ? 1 : 0;
		$advance     = max( 0, absint( $_POST['advance_per_group'] ?? 2 ) );
		Kurs_Season::update_division_format( $did, $has_playoff, $advance );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-season-setup&season_id={$sid}&step=2" ) );
		exit;
	}

	private function do_add_phase(): void {
		check_admin_referer( 'kurs_season_setup' );
		$sid = absint( $_POST['season_id'] );
		$did = absint( $_POST['division_id'] );
		Kurs_Season::create_phase( $did, sanitize_text_field( $_POST['phase_name'] ), $_POST['phase_type'] ?? 'group' );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-season-setup&season_id={$sid}&step=2" ) );
		exit;
	}

	private function do_delete_phase(): void {
		check_admin_referer( 'kurs_season_setup' );
		$sid = absint( $_POST['season_id'] );
		$pid = absint( $_POST['phase_id'] );
		Kurs_Season::delete_phase( $pid );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-season-setup&season_id={$sid}&step=2" ) );
		exit;
	}

	private function do_delete_division(): void {
		check_admin_referer( 'kurs_season_setup' );
		$sid = absint( $_POST['season_id'] );
		$did = absint( $_POST['division_id'] );
		Kurs_Season::delete_division( $did );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-season-setup&season_id={$sid}&step=2" ) );
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
		$sid  = absint( $_POST['season_id'] ?? 0 );
		$date = ! empty( $_POST['match_date'] ) ? self::parse_hr_date( sanitize_text_field( $_POST['match_date'] ) ) : null;
		Kurs_Match::create(
			$gid,
			absint( $_POST['home_team'] ),
			absint( $_POST['away_team'] ),
			$date,
			absint( $_POST['round'] ?? 1 )
		);
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&group_id={$gid}" ) );
		exit;
	}

	private function do_reopen_match(): void {
		check_admin_referer( 'kurs_reopen_match' );
		$mid = absint( $_POST['match_id'] );
		Kurs_Match::reopen( $mid );
		// Sinkroniziraj s playoff_matches ako je ovaj meč dio playoffa
		$pm = Kurs_Playoff::find_by_match_id( $mid );
		if ( $pm ) Kurs_Playoff::reopen_match( (int) $pm->id );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-match-record&match_id={$mid}" ) );
		exit;
	}

	private function do_finish_match(): void {
		check_admin_referer( 'kurs_finish_match' );
		$mid  = absint( $_POST['match_id'] );
		$sid  = absint( $_POST['season_id'] ?? 0 );
		$home = absint( $_POST['home_score'] );
		$away = absint( $_POST['away_score'] );
		Kurs_Match::finish( $mid, $home, $away );
		// Sinkroniziraj s playoff_matches ako je ovaj meč dio playoffa
		$pm = Kurs_Playoff::find_by_match_id( $mid );
		if ( $pm ) {
			Kurs_Playoff::finish_match( (int) $pm->id, $home, $away );
		}
		if ( ! empty( $_POST['from_record'] ) ) {
			wp_safe_redirect( admin_url( "admin.php?page=kurs-match-record&match_id={$mid}" ) );
		} else {
			$m = Kurs_Match::get( $mid );
			wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&group_id={$m->group_id}" ) );
		}
		exit;
	}

	private function do_edit_season(): void {
		check_admin_referer( 'kurs_edit_season' );
		$id = absint( $_POST['season_id'] );
		Kurs_Season::update( $id, [
			'league'             => $_POST['league'] ?? '',
			'name'               => $_POST['name']   ?? '',
			'year'               => $_POST['year']   ?? date( 'Y' ),
			'status'             => $_POST['status'] ?? 'active',
			'max_fouls_per_half' => $_POST['max_fouls_per_half'] ?? 5,
		] );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-seasons&updated=1' ) );
		exit;
	}

	private function do_delete_season(): void {
		check_admin_referer( 'kurs_delete_season' );
		Kurs_Season::delete( absint( $_POST['season_id'] ) );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-seasons&deleted=1' ) );
		exit;
	}

	private function do_toggle_season_lock(): void {
		check_admin_referer( 'kurs_toggle_lock' );
		$id   = absint( $_POST['season_id'] );
		$lock = ! empty( $_POST['lock'] );          // 1 = zaključaj, prazno = otključaj
		Kurs_Season::set_locked( $id, $lock );
		do_action( 'kurs_data_changed' );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-seasons&' . ( $lock ? 'locked=1' : 'unlocked=1' ) ) );
		exit;
	}

	private static function sanitize_team_color( string $raw ): string {
		$raw = trim( $raw );
		return ( $raw && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $raw ) ) ? $raw : '#1a3a6b';
	}

	private function do_add_team(): void {
		check_admin_referer( 'kurs_add_team' );
		$name = sanitize_text_field( $_POST['team_name'] ?? '' );
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
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-teams&added=1' ) );
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

	private function do_remove_team_banner(): void {
		$id = absint( $_POST['team_id'] ?? 0 );
		check_admin_referer( 'kurs_remove_banner_' . $id );
		if ( $id ) delete_post_meta( $id, '_kurs_team_banner' );
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
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-dashboard&ticker_saved=1' ) );
		exit;
	}

	/** Spremi bazu sudaca (jedan po retku) — koriste se kao prijedlozi u zapisniku. */
	private function do_save_referees(): void {
		check_admin_referer( 'kurs_save_referees' );
		$raw   = (string) wp_unslash( $_POST['referees'] ?? '' );
		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
		$lines = array_values( array_unique( array_map( 'sanitize_text_field', $lines ) ) );
		update_option( 'kurs_referees', $lines );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-dashboard&refs_saved=1' ) );
		exit;
	}

	private function do_delete_team(): void {
		check_admin_referer( 'kurs_delete_team' );
		wp_delete_post( absint( $_POST['team_id'] ), true );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-teams&deleted=1' ) );
		exit;
	}

	private function do_delete_match(): void {
		check_admin_referer( 'kurs_delete_match' );
		$mid = absint( $_POST['match_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		$m   = Kurs_Match::get( $mid );
		$gid = $m ? (int) $m->group_id : 0;
		Kurs_Match::delete( $mid );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&group_id={$gid}" ) );
		exit;
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_add_group(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$id = Kurs_Season::create_group( absint( $_POST['phase_id'] ), sanitize_text_field( $_POST['name'] ) );
		$id
			? wp_send_json_success( [ 'id' => $id, 'name' => sanitize_text_field( $_POST['name'] ) ] )
			: wp_send_json_error( 'Greška pri dodavanju grupe.' );
	}

	public function ajax_del_group(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		Kurs_Season::delete_group( absint( $_POST['group_id'] ) );
		wp_send_json_success();
	}

	public function ajax_assign_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$gid = absint( $_POST['group_id'] );
		$tid = absint( $_POST['team_id'] );
		Kurs_Season::assign_team( $gid, $tid );
		wp_send_json_success( [ 'team_id' => $tid, 'team_name' => get_the_title( $tid ) ] );
	}

	public function ajax_remove_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		Kurs_Season::remove_team( absint( $_POST['group_id'] ), absint( $_POST['team_id'] ) );
		wp_send_json_success();
	}

	// ── AJAX: Logo ────────────────────────────────────────────────────────────

	public function ajax_set_logo(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$team_id = absint( $_POST['team_id'] );
		$att_id  = absint( $_POST['attachment_id'] );

		if ( ! $team_id || ! $att_id ) wp_send_json_error( 'Nevažeći podaci.' );

		set_post_thumbnail( $team_id, $att_id );
		$url = wp_get_attachment_image_url( $att_id, [ 40, 40 ] );

		wp_send_json_success( [ 'url' => $url ] );
	}

	public function ajax_set_banner(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$team_id = absint( $_POST['team_id'] );
		$att_id  = absint( $_POST['attachment_id'] );

		if ( ! $team_id || ! $att_id ) wp_send_json_error( 'Nevažeći podaci.' );

		update_post_meta( $team_id, '_kurs_team_banner', $att_id );
		$url = wp_get_attachment_image_url( $att_id, 'medium' );

		wp_send_json_success( [ 'url' => $url ] );
	}

	public function ajax_set_photo(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$team_id = absint( $_POST['team_id'] );
		$att_id  = absint( $_POST['attachment_id'] );

		if ( ! $team_id || ! $att_id ) wp_send_json_error( 'Nevažeći podaci.' );

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

	// ── AJAX: Playoff spots po grupi ─────────────────────────────────────────

	public function ajax_update_playoff_spots(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$group_id = absint( $_POST['group_id'] );
		$spots    = absint( $_POST['spots'] );

		if ( ! $group_id ) wp_send_json_error( 'Nevažeći group_id.' );

		/* Provjeri da ne prelazi broj ekipa u grupi */
		$team_count = count( Kurs_Season::get_group_teams( $group_id ) );
		$spots      = min( $spots, $team_count );

		Kurs_Season::update_group_playoff_spots( $group_id, $spots );
		wp_send_json_success( [ 'spots' => $spots ] );
	}

	// ── AJAX: Generiraj raspored (svaka s svakom) ────────────────────────────

	public function ajax_generate_fixtures(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$group_id = absint( $_POST['group_id'] );
		if ( ! $group_id ) wp_send_json_error( 'Nedostaje group_id.' );

		$teams = Kurs_Season::get_group_teams( $group_id );
		if ( count( $teams ) < 2 ) wp_send_json_error( 'Potrebne su najmanje 2 ekipe.' );

		$ids = array_values( array_map( fn( $t ) => (int) $t->team_id, $teams ) );

		// Skup već postojećih parova
		$existing_pairs = [];
		foreach ( Kurs_Match::get_by_group( $group_id ) as $m ) {
			$k = min( $m->home_team_id, $m->away_team_id ) . '_' . max( $m->home_team_id, $m->away_team_id );
			$existing_pairs[ $k ] = true;
		}

		$count = count( $ids );
		if ( $count % 2 === 1 ) { $ids[] = 0; $count++; } // bye za neparan broj ekipa

		$half     = $count / 2;
		$rotation = array_slice( $ids, 1 );
		$created  = 0;

		for ( $round = 1; $round <= $count - 1; $round++ ) {
			$rt = array_merge( [ $ids[0] ], $rotation );
			for ( $i = 0; $i < $half; $i++ ) {
				$h = $rt[ $i ];
				$a = $rt[ $count - 1 - $i ];
				if ( $h === 0 || $a === 0 ) continue;
				$k = min( $h, $a ) . '_' . max( $h, $a );
				if ( isset( $existing_pairs[ $k ] ) ) continue;
				Kurs_Match::create( $group_id, $h, $a, null, $round );
				$existing_pairs[ $k ] = true;
				$created++;
			}
			// Rotiraj: zadnji element ide na početak
			$last = array_pop( $rotation );
			array_unshift( $rotation, $last );
		}

		wp_send_json_success( [ 'created' => $created ] );
	}

	// ── AJAX: Ažuriraj utakmicu (datum, kolo) ────────────────────────────────

	public function ajax_update_match(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );

		$match_id = absint( $_POST['match_id'] );
		$date  = ! empty( $_POST['match_date'] ) ? self::parse_hr_date( sanitize_text_field( $_POST['match_date'] ) ) : null;
		$round = absint( $_POST['round'] ?? 1 ) ?: 1;

		if ( ! $match_id ) wp_send_json_error( 'Nevažeći match_id.' );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . KURS_PREFIX . 'matches',
			[ 'match_date' => $date, 'round' => $round ],
			[ 'id' => $match_id ]
		);

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
		Kurs_Match::set_absent( $match_id, $team_id, $player, $absent );
		wp_send_json_success( [ 'player_id' => $player, 'absent' => $absent ] );
	}

	public function ajax_record_set_referees(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( KURS_CAP ) ) wp_die( '', 403 );
		$match_id = absint( $_POST['match_id'] );
		if ( ! $match_id ) wp_send_json_error( 'Nevažeći podaci.' );
		$r1 = sanitize_text_field( wp_unslash( $_POST['referee1'] ?? '' ) );
		$r2 = sanitize_text_field( wp_unslash( $_POST['referee2'] ?? '' ) );
		Kurs_Match::set_referees( $match_id, $r1, $r2 );
		if ( function_exists( 'kurs_add_referees' ) ) kurs_add_referees( [ $r1, $r2 ] ); // zapamti u bazi
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
	public function pg_seasons():      void { require KURS_DIR . 'admin/views/seasons.php'; }
	public function pg_setup():        void { require KURS_DIR . 'admin/views/season-setup.php'; }
	public function pg_matches():      void { require KURS_DIR . 'admin/views/matches.php'; }
	public function pg_teams():        void { require KURS_DIR . 'admin/views/teams.php'; }
	// ── Playoff handlers ─────────────────────────────────────────────────────

	private function do_generate_playoff(): void {
		check_admin_referer( 'kurs_generate_playoff' );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		$div = Kurs_Season::get_division( $did );
		if ( $div && empty( $div->has_playoff ) ) { // divizija se igra bez playoffa
			wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}" ) );
			exit;
		}
		Kurs_Playoff::generate( $did );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&playoff_div={$did}&generated=1" ) );
		exit;
	}

	private function do_reset_playoff(): void {
		check_admin_referer( 'kurs_reset_playoff' );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		Kurs_Playoff::reset( $did );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&playoff_div={$did}&reset=1" ) );
		exit;
	}

	private function do_finish_playoff_match(): void {
		check_admin_referer( 'kurs_finish_playoff_match' );
		$id  = absint( $_POST['playoff_match_id'] );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		Kurs_Playoff::finish_match( $id, absint( $_POST['home_score'] ), absint( $_POST['away_score'] ) );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&playoff_div={$did}" ) );
		exit;
	}

	private function do_reopen_playoff_match(): void {
		check_admin_referer( 'kurs_reopen_playoff_match' );
		$id  = absint( $_POST['playoff_match_id'] );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		Kurs_Playoff::reopen_match( $id );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&playoff_div={$did}" ) );
		exit;
	}

	private function do_update_playoff_match(): void {
		check_admin_referer( 'kurs_update_playoff_match' );
		$id  = absint( $_POST['playoff_match_id'] );
		$did = absint( $_POST['division_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		Kurs_Playoff::update_teams(
			$id,
			absint( $_POST['home_team_id'] ?? 0 ) ?: null,
			absint( $_POST['away_team_id'] ?? 0 ) ?: null,
			sanitize_text_field( $_POST['home_label'] ?? '' ),
			sanitize_text_field( $_POST['away_label'] ?? '' )
		);
		if ( ! empty( $_POST['match_date'] ) ) {
			$date = self::parse_hr_date( $_POST['match_date'] );
			Kurs_Playoff::update_date( $id, $date );
		}
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&playoff_div={$did}" ) );
		exit;
	}

	public function pg_roster():       void { require KURS_DIR . 'admin/views/roster.php'; }
	public function pg_match_record(): void { require KURS_DIR . 'admin/views/match-record.php'; }
	public function pg_playoff():      void { require KURS_DIR . 'admin/views/playoff.php'; }
}
