<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Admin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_init',            [ $this, 'handle_forms' ] );
		add_action( 'wp_ajax_kurs_add_group',      [ $this, 'ajax_add_group' ] );
		add_action( 'wp_ajax_kurs_del_group',      [ $this, 'ajax_del_group' ] );
		add_action( 'wp_ajax_kurs_assign_team',    [ $this, 'ajax_assign_team' ] );
		add_action( 'wp_ajax_kurs_remove_team',    [ $this, 'ajax_remove_team' ] );
		add_action( 'wp_ajax_kurs_set_logo',            [ $this, 'ajax_set_logo' ] );
		add_action( 'wp_ajax_kurs_add_squad',           [ $this, 'ajax_add_squad' ] );
		add_action( 'wp_ajax_kurs_remove_squad',        [ $this, 'ajax_remove_squad' ] );
		add_action( 'wp_ajax_kurs_quick_add_player',    [ $this, 'ajax_quick_add_player' ] );
		add_action( 'wp_ajax_kurs_search_players',      [ $this, 'ajax_search_players' ] );
		add_action( 'wp_ajax_kurs_update_squad_player', [ $this, 'ajax_update_squad_player' ] );
		add_action( 'wp_ajax_kurs_create_team',         [ $this, 'ajax_create_team' ] );
	}

	// ── Menus ─────────────────────────────────────────────────────────────────

	public function menus(): void {
		add_menu_page(
			'KURS Liga', 'KURS Liga', 'manage_options',
			'kurs-dashboard', [ $this, 'pg_dashboard' ],
			'dashicons-awards', 25
		);
		add_submenu_page( 'kurs-dashboard', 'Dashboard',    'Dashboard',    'manage_options', 'kurs-dashboard',    [ $this, 'pg_dashboard' ] );
		add_submenu_page( 'kurs-dashboard', 'Sezone',       'Sezone',       'manage_options', 'kurs-seasons',      [ $this, 'pg_seasons' ] );
		add_submenu_page( 'kurs-dashboard', 'Postavi ligu', 'Postavi ligu', 'manage_options', 'kurs-season-setup', [ $this, 'pg_setup' ] );
		add_submenu_page( 'kurs-dashboard', 'Utakmice',     'Utakmice',     'manage_options', 'kurs-matches',      [ $this, 'pg_matches' ] );
		add_submenu_page( 'kurs-dashboard', 'Ekipe',        'Ekipe',        'manage_options', 'kurs-teams',        [ $this, 'pg_teams' ] );
		// Skrivena stranica — dostupna via URL ali ne u meniju
		add_submenu_page( null, 'Roster', 'Roster', 'manage_options', 'kurs-roster', [ $this, 'pg_roster' ] );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function assets( string $hook ): void {
		if ( strpos( $hook, 'kurs-' ) === false ) return;
		wp_enqueue_media(); // Potrebno za Media Library modal
		wp_enqueue_style(  'kurs-admin', KURS_URL . 'admin/assets/admin.css', [], KURS_VERSION );
		wp_enqueue_script( 'kurs-admin', KURS_URL . 'admin/assets/admin.js',  [ 'jquery' ], KURS_VERSION, true );
		wp_localize_script( 'kurs-admin', 'KursAjax', [
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'kurs_ajax' ),
		] );
	}

	// ── Form handlers ─────────────────────────────────────────────────────────

	public function handle_forms(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( empty( $_POST['kurs_action'] ) ) return;

		match ( sanitize_key( $_POST['kurs_action'] ) ) {
			'create_season' => $this->do_create_season(),
			'edit_season'   => $this->do_edit_season(),
			'delete_season' => $this->do_delete_season(),
			'add_division'  => $this->do_add_division(),
			'add_phase'     => $this->do_add_phase(),
			'add_match'     => $this->do_add_match(),
			'finish_match'  => $this->do_finish_match(),
			'delete_match'  => $this->do_delete_match(),
			'add_team'      => $this->do_add_team(),
			'edit_team'     => $this->do_edit_team(),
			'delete_team'   => $this->do_delete_team(),
			default         => null,
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
		$sid = absint( $_POST['season_id'] );
		Kurs_Season::create_division( $sid, sanitize_text_field( $_POST['div_name'] ) );
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

	private function do_add_match(): void {
		check_admin_referer( 'kurs_add_match' );
		$gid  = absint( $_POST['group_id'] );
		$sid  = absint( $_POST['season_id'] ?? 0 );
		$date = ! empty( $_POST['match_date'] ) ? sanitize_text_field( $_POST['match_date'] ) : null;
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

	private function do_finish_match(): void {
		check_admin_referer( 'kurs_finish_match' );
		$mid = absint( $_POST['match_id'] );
		$sid = absint( $_POST['season_id'] ?? 0 );
		Kurs_Match::finish( $mid, absint( $_POST['home_score'] ), absint( $_POST['away_score'] ) );
		$m = Kurs_Match::get( $mid );
		wp_safe_redirect( admin_url( "admin.php?page=kurs-matches&season_id={$sid}&group_id={$m->group_id}" ) );
		exit;
	}

	private function do_edit_season(): void {
		check_admin_referer( 'kurs_edit_season' );
		$id = absint( $_POST['season_id'] );
		Kurs_Season::update( $id, [
			'league' => $_POST['league'] ?? '',
			'name'   => $_POST['name']   ?? '',
			'year'   => $_POST['year']   ?? date( 'Y' ),
			'status' => $_POST['status'] ?? 'active',
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

	private function do_add_team(): void {
		check_admin_referer( 'kurs_add_team' );
		$name = sanitize_text_field( $_POST['team_name'] ?? '' );
		if ( $name ) {
			wp_insert_post( [
				'post_title'  => $name,
				'post_type'   => 'kurs_ekipa',
				'post_status' => 'publish',
			] );
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
		}
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-teams&updated=1' ) );
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
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );
		$id = Kurs_Season::create_group( absint( $_POST['phase_id'] ), sanitize_text_field( $_POST['name'] ) );
		$id
			? wp_send_json_success( [ 'id' => $id, 'name' => sanitize_text_field( $_POST['name'] ) ] )
			: wp_send_json_error( 'Greška pri dodavanju grupe.' );
	}

	public function ajax_del_group(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );
		Kurs_Season::delete_group( absint( $_POST['group_id'] ) );
		wp_send_json_success();
	}

	public function ajax_assign_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );
		$gid = absint( $_POST['group_id'] );
		$tid = absint( $_POST['team_id'] );
		Kurs_Season::assign_team( $gid, $tid );
		wp_send_json_success( [ 'team_id' => $tid, 'team_name' => get_the_title( $tid ) ] );
	}

	public function ajax_remove_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );
		Kurs_Season::remove_team( absint( $_POST['group_id'] ), absint( $_POST['team_id'] ) );
		wp_send_json_success();
	}

	// ── AJAX: Logo ────────────────────────────────────────────────────────────

	public function ajax_set_logo(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

		$team_id = absint( $_POST['team_id'] );
		$att_id  = absint( $_POST['attachment_id'] );

		if ( ! $team_id || ! $att_id ) wp_send_json_error( 'Nevažeći podaci.' );

		set_post_thumbnail( $team_id, $att_id );
		$url = wp_get_attachment_image_url( $att_id, [ 40, 40 ] );

		wp_send_json_success( [ 'url' => $url ] );
	}

	// ── AJAX: Roster ──────────────────────────────────────────────────────────

	public function ajax_add_squad(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

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
		] );
	}

	public function ajax_remove_squad(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

		Kurs_Season::remove_from_squad(
			absint( $_POST['player_id'] ),
			absint( $_POST['team_id'] ),
			absint( $_POST['division_id'] )
		);
		wp_send_json_success();
	}

	public function ajax_quick_add_player(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( ! $name ) wp_send_json_error( 'Ime igrača je obavezno.' );

		$player_id = wp_insert_post( [
			'post_title'  => $name,
			'post_type'   => 'kurs_igrac',
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $player_id ) ) wp_send_json_error( $player_id->get_error_message() );

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
		] );
	}

	// ── AJAX: Brzo kreiranje ekipe (iz dodjele ekipa) ────────────────────────

	public function ajax_create_team(): void {
		check_ajax_referer( 'kurs_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

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
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

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
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

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

		wp_send_json_success( [
			'player_id' => $player_id,
			'name'      => $name ?: get_the_title( $player_id ),
			'jersey'    => $jersey,
		] );
	}

	// ── Page renderers ────────────────────────────────────────────────────────

	public function pg_dashboard(): void { require KURS_DIR . 'admin/views/dashboard.php'; }
	public function pg_seasons():   void { require KURS_DIR . 'admin/views/seasons.php'; }
	public function pg_setup():     void { require KURS_DIR . 'admin/views/season-setup.php'; }
	public function pg_matches():   void { require KURS_DIR . 'admin/views/matches.php'; }
	public function pg_teams():     void { require KURS_DIR . 'admin/views/teams.php'; }
	public function pg_roster():    void { require KURS_DIR . 'admin/views/roster.php'; }
}
