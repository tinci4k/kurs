<?php
/**
 * Sponzori lige u KURS sučelju (1.31.0) — KURS Liga → Sponzori (`kurs-sponsors`).
 *
 * Podaci ostaju u CPT-u `sponzor` iz teme (logo = istaknuta slika, `_kurs_sponsor_tier` 1–3,
 * `_kurs_sponsor_link`, redoslijed = menu_order) + novo `_kurs_sponsor_comp` (obje | kc | pan).
 * WordPressovi ekrani CPT-a preusmjeravaju se ovamo. Sponzori koje je KURS poslao kroz
 * presjek dodaju se na web jednim klikom (`_kurs_presjek_ref` = indeks u opciji presjeka).
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Sponsors {

	const TIERS = [ 1 => 'Glavni', 2 => 'Srednji', 3 => 'Mali' ];
	const TIER_FRONT = [ 1 => 'Glavni pokrovitelj — velika istaknuta kartica', 2 => 'Srebrni partner — srednja kartica', 3 => 'Partner — logo u traci' ];
	const COMPS = [ 'obje' => 'Obje lige', 'kc' => 'KC liga', 'pan' => 'Pan Ljetna liga' ];

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ], 22 );
		foreach ( [ 'save', 'trash', 'untrash', 'move', 'toggle', 'import' ] as $a ) {
			add_action( 'admin_post_kurs_sponsor_' . $a, [ __CLASS__, 'handle_' . $a ] );
		}
		// WordPressovi ekrani sponzora → naša stranica
		foreach ( [ 'load-edit.php', 'load-post-new.php', 'load-post.php' ] as $h ) add_action( $h, [ __CLASS__, 'redirect_cpt' ] );
	}

	public static function url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=kurs-sponsors' ) );
	}

	public static function admin_menu(): void {
		add_submenu_page( 'kurs-dashboard', 'Sponzori lige', 'Sponzori', KURS_CAP, 'kurs-sponsors', [ __CLASS__, 'page' ] );
	}

	public static function page(): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemaš ovlasti.' );
		include KURS_DIR . 'admin/views/sponsors.php';
	}

	public static function redirect_cpt(): void {
		global $typenow;
		$pt = $typenow ?: ( isset( $_GET['post'] ) ? get_post_type( absint( $_GET['post'] ) ) : ( $_GET['post_type'] ?? '' ) );
		if ( $pt !== 'sponzor' || ! current_user_can( KURS_CAP ) ) return;
		if ( isset( $_GET['post'] ) ) $to = self::url( [ 'edit' => absint( $_GET['post'] ) ] );
		elseif ( str_contains( (string) ( $_SERVER['PHP_SELF'] ?? '' ), 'post-new.php' ) ) $to = self::url( [ 'new' => 1 ] );
		else $to = self::url();
		wp_safe_redirect( $to );
		exit;
	}

	/** Svi sponzori (objavljeni i skriveni), po redoslijedu. */
	public static function all(): array {
		if ( ! post_type_exists( 'sponzor' ) ) return [];
		return get_posts( [ 'post_type' => 'sponzor', 'post_status' => [ 'publish', 'draft' ], 'numberposts' => -1, 'orderby' => [ 'menu_order' => 'ASC', 'ID' => 'ASC' ] ] );
	}

	private static function guard( string $nonce ): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemaš ovlasti.' );
		check_admin_referer( $nonce );
	}

	private static function back( array $args = [] ): void {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/** Redoslijed 0..n bez rupa. */
	private static function normalize( array $ids ): void {
		foreach ( array_values( $ids ) as $i => $id ) {
			if ( (int) get_post_field( 'menu_order', $id ) !== $i ) wp_update_post( [ 'ID' => $id, 'menu_order' => $i ] );
		}
	}

	public static function handle_save(): void {
		self::guard( 'kurs_sponsor_save' );
		$id   = absint( $_POST['id'] ?? 0 );
		$name = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 120 );
		if ( $name === '' ) self::back( [ $id ? 'edit' : 'new' => $id ?: 1, 'err' => 'name' ] );
		$tier = max( 1, min( 3, absint( $_POST['tier'] ?? 2 ) ) );
		$comp = isset( self::COMPS[ $_POST['comp'] ?? '' ] ) ? (string) $_POST['comp'] : 'obje';
		$link = trim( (string) wp_unslash( $_POST['link'] ?? '' ) );
		if ( $link !== '' && ! preg_match( '#^https?://#i', $link ) ) $link = 'https://' . $link;
		$link = $link !== '' ? esc_url_raw( $link, [ 'http', 'https' ] ) : '';
		$logo = absint( $_POST['logo'] ?? 0 );
		$status = ! empty( $_POST['visible'] ) ? 'publish' : 'draft';

		$data = [ 'post_title' => $name, 'post_type' => 'sponzor', 'post_status' => $status ];
		if ( $id && get_post_type( $id ) === 'sponzor' ) {
			$data['ID'] = $id;
			wp_update_post( $data );
		} else {
			$data['menu_order'] = count( self::all() );
			$id = wp_insert_post( $data );
			if ( is_wp_error( $id ) || ! $id ) self::back( [ 'err' => 'save' ] );
			if ( isset( $_POST['presjek_ref'] ) ) update_post_meta( $id, '_kurs_presjek_ref', absint( $_POST['presjek_ref'] ) );
		}
		update_post_meta( $id, '_kurs_sponsor_tier', $tier );
		update_post_meta( $id, '_kurs_sponsor_comp', $comp );
		update_post_meta( $id, '_kurs_sponsor_link', $link );
		if ( $logo && wp_attachment_is_image( $logo ) ) set_post_thumbnail( $id, $logo ); elseif ( ! $logo ) delete_post_thumbnail( $id );
		self::back( [ 'msg' => 'saved', 'hl' => $id ] );
	}

	public static function handle_trash(): void {
		self::guard( 'kurs_sponsor_trash' );
		$id = absint( $_POST['id'] ?? 0 );
		if ( get_post_type( $id ) === 'sponzor' ) wp_trash_post( $id );
		self::back( [ 'msg' => 'trashed', 'undo' => $id ] );
	}

	public static function handle_untrash(): void {
		self::guard( 'kurs_sponsor_untrash' );
		$id = absint( $_POST['id'] ?? 0 );
		if ( get_post_type( $id ) === 'sponzor' ) { wp_untrash_post( $id ); wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] ); }
		self::back( [ 'msg' => 'restored', 'hl' => $id ] );
	}

	public static function handle_toggle(): void {
		self::guard( 'kurs_sponsor_toggle' );
		$id = absint( $_POST['id'] ?? 0 );
		if ( get_post_type( $id ) === 'sponzor' ) wp_update_post( [ 'ID' => $id, 'post_status' => get_post_status( $id ) === 'publish' ? 'draft' : 'publish' ] );
		self::back( [ 'hl' => $id ] );
	}

	public static function handle_move(): void {
		self::guard( 'kurs_sponsor_move' );
		$id  = absint( $_POST['id'] ?? 0 );
		$dir = ( $_POST['dir'] ?? '' ) === 'up' ? -1 : 1;
		$ids = array_map( fn( $p ) => (int) $p->ID, self::all() );
		$i   = array_search( $id, $ids, true );
		if ( $i !== false && isset( $ids[ $i + $dir ] ) ) { [ $ids[ $i ], $ids[ $i + $dir ] ] = [ $ids[ $i + $dir ], $ids[ $i ] ]; }
		self::normalize( $ids );
		self::back( [ 'hl' => $id ] );
	}

	/** Sponzor poslan kroz presjek → novi sponzor na webu (KURS ga može još urediti). */
	public static function handle_import(): void {
		self::guard( 'kurs_sponsor_import' );
		$ref = absint( $_POST['ref'] ?? 0 );
		$src = null;
		foreach ( class_exists( 'Kurs_Presjek' ) ? Kurs_Presjek::sponsors( Kurs_Presjek::LEAGUE ) : [] as $s ) if ( (int) $s['i'] === $ref ) $src = $s;
		if ( ! $src ) self::back( [ 'err' => 'import' ] );
		$tier = [ 'glavni' => 1, 'srednji' => 2, 'mali' => 3 ][ $src['tier'] ] ?? 2;
		$id   = wp_insert_post( [ 'post_title' => $src['name'], 'post_type' => 'sponzor', 'post_status' => $src['att'] ? 'publish' : 'draft', 'menu_order' => count( self::all() ) ] );
		if ( ! $id || is_wp_error( $id ) ) self::back( [ 'err' => 'save' ] );
		update_post_meta( $id, '_kurs_sponsor_tier', $tier );
		update_post_meta( $id, '_kurs_sponsor_comp', $src['comp'] && isset( self::COMPS[ $src['comp'] ] ) ? $src['comp'] : 'obje' );
		update_post_meta( $id, '_kurs_sponsor_link', $src['link'] );
		update_post_meta( $id, '_kurs_presjek_ref', $ref );
		if ( $src['att'] ) set_post_thumbnail( $id, (int) $src['att'] );
		self::back( [ 'msg' => 'imported', 'hl' => $id ] );
	}

	/** Indeksi presjek-sponzora koji su već dodani na web. */
	public static function imported_refs(): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_kurs_presjek_ref' AND p.post_status <> 'trash'" ) );
	}
}
