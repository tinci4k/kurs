<?php
/**
 * /zapisnik/ — zapisnik utakmice izvan wp-admina, za zapisničare (1.24.0).
 *
 * Zapisničar (uloga `kurs_zapisnicar`, Kurs_Roles) ima samo KURS_RECORD_CAP: nakon prijave
 * dolazi ovdje, vidi popis utakmica (čeka rezultat · nadolazeće · nedavno završene) i otvara
 * zapisnik — isti `admin/views/match-record.php` + admin.js/admin.css i isti AJAX handleri
 * kao u administraciji (admin-ajax.php). wp-admin mu je zatvoren, traka administracije skrivena.
 *
 * Adrese: /zapisnik/ (popis), /zapisnik/?match_id=X (zapisnik), &format=pdf (ispis).
 * Stranica je samostalan dokument (bez teme) — ispisuje samo svoje stilove i skripte.
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Zapisnik {

	const SLUG = 'zapisnik';

	public static function init(): void {
		add_action( 'init',              [ __CLASS__, 'rewrite' ] );
		add_action( 'init',              [ __CLASS__, 'heal_rewrite' ], 101 );
		add_filter( 'query_vars',        fn( $v ) => array_merge( $v, [ 'kurs_zapisnik' ] ) );
		add_action( 'template_redirect', [ __CLASS__, 'render' ], 0 );
		add_action( 'admin_init',        [ __CLASS__, 'keep_out_of_admin' ], 1 );
		add_filter( 'show_admin_bar',    fn( $show ) => self::recorder_only() ? false : $show );
		add_filter( 'login_redirect',    [ __CLASS__, 'login_redirect' ], 10, 3 );
	}

	/** URL popisa (bez $match_id) ili zapisnika utakmice. */
	public static function url( int $match_id = 0, array $args = [] ): string {
		$url = home_url( '/' . self::SLUG . '/' );
		if ( $match_id ) $args = [ 'match_id' => $match_id ] + $args;
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/** Korisnik koji smije SAMO voditi zapisnik (uloga Zapisničar) — ne administrator ni urednik. */
	public static function recorder_only( ?WP_User $user = null ): bool {
		$user = $user ?: wp_get_current_user();
		return $user && $user->exists()
			&& $user->has_cap( KURS_RECORD_CAP )
			&& ! $user->has_cap( KURS_CAP )
			&& ! $user->has_cap( 'edit_posts' );
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?kurs_zapisnik=1', 'top' );
	}

	/** Spremljena pravila bez /zapisnik/ (nova verzija, migracija baze) → regeneriraj jednom. */
	public static function heal_rewrite(): void {
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && get_option( 'permalink_structure' ) && ! isset( $rules[ '^' . self::SLUG . '/?$' ] ) ) {
			flush_rewrite_rules( false );
		}
	}

	/** Zapisničar u wp-adminu → /zapisnik/ (AJAX i vlastiti profil/lozinka ostaju dostupni). */
	public static function keep_out_of_admin(): void {
		if ( wp_doing_ajax() || ! self::recorder_only() ) return;
		global $pagenow;
		if ( in_array( $pagenow, [ 'profile.php', 'admin-ajax.php' ], true ) ) return;
		wp_safe_redirect( self::url() );
		exit;
	}

	/** Nakon prijave zapisničar ide ravno na popis utakmica. */
	public static function login_redirect( $to, $requested, $user ) {
		return ( $user instanceof WP_User && self::recorder_only( $user ) ) ? self::url() : $to;
	}

	/** /zapisnik/ — forme zapisnika, PDF ili stranica (popis / zapisnik / prijava). */
	public static function render(): void {
		if ( ! get_query_var( 'kurs_zapisnik' ) ) return;

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		require_once KURS_DIR . 'admin/class-admin.php'; // statični pomoćnici + handleri zapisnika (klasa se ne instancira)

		$can      = current_user_can( KURS_RECORD_CAP );
		$match_id = absint( $_GET['match_id'] ?? 0 );

		if ( $can && $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['kurs_action'] ) ) {
			Kurs_Admin::handle_record_forms(); // završi / otvori za ispravak → redirect + exit
			wp_safe_redirect( self::url( $match_id ) );
			exit;
		}

		if ( $can && $match_id && ( $_GET['format'] ?? '' ) === 'pdf' ) {
			$GLOBALS['kurs_record_front'] = true;
			require KURS_DIR . 'admin/views/match-record.php'; // PDF grana: include + exit
			exit;
		}

		if ( $can && $match_id && ! Kurs_Match::get( $match_id ) ) {
			wp_safe_redirect( self::url() );
			exit;
		}

		if ( $can ) Kurs_Admin::enqueue_ui();
		else wp_enqueue_style( 'kurs-admin-fonts', Kurs_Admin::FONTS_URL, [], null );
		wp_enqueue_style( 'kurs-zapisnik', KURS_URL . 'front/zapisnik.css', [ $can ? 'kurs-admin' : 'kurs-admin-fonts' ], KURS_VERSION );

		status_header( 200 );
		require KURS_DIR . 'front/zapisnik.php';
		exit;
	}

	/**
	 * Utakmice za popis: [ 'late' => prošle a nezavršene, 'days' => [ datum => utakmice ] (sljedeća
	 * dva dana igranja, danas uključen), 'more' => broj kasnijih, 'done' => nedavno završene ].
	 */
	public static function lists(): array {
		$now      = current_time( 'mysql' );
		$today    = substr( $now, 0, 10 );
		$late     = Kurs_Admin::active_matches( "m.status <> 'finished' AND m.match_date < %s", [ $today . ' 00:00:00' ], 'm.match_date', 30 );
		$upcoming = Kurs_Admin::active_matches( "m.status <> 'finished' AND m.match_date >= %s", [ $today . ' 00:00:00' ], 'm.match_date', 60 );
		$done     = Kurs_Admin::active_matches( "m.status = 'finished'", [], 'm.match_date DESC, m.id DESC', 12 );

		$days = [];
		foreach ( $upcoming as $m ) {
			$d = substr( (string) $m->match_date, 0, 10 );
			if ( ! isset( $days[ $d ] ) && count( $days ) === 2 ) break;
			$days[ $d ][] = $m;
		}
		$more = count( $upcoming ) - array_sum( array_map( 'count', $days ) );

		return [ 'late' => $late, 'days' => $days, 'more' => max( 0, $more ), 'done' => $done, 'today' => $today ];
	}

	/** „subota, 14. 11. 2026." (+ „Danas · " / „Sutra · "). */
	public static function day_label( string $date, string $today ): string {
		$wd  = [ 'nedjelja', 'ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota' ];
		$ts  = strtotime( $date );
		$tom = date( 'Y-m-d', strtotime( '+1 day', strtotime( $today ) ) );
		$pre = $date === $today ? 'Danas · ' : ( $date === $tom ? 'Sutra · ' : '' );
		return $pre . $wd[ (int) date( 'w', $ts ) ] . ', ' . date( 'j. n. Y.', $ts );
	}
}
