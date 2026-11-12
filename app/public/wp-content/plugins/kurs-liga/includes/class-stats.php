<?php
/**
 * Statistika posjeta (1.30.0) — vlastito, anonimno brojanje bez kolačića.
 *
 * Svaki pregled javne stranice (tema) pošalje mali beacon na REST `kurs/v1/posjet`
 * (navigator.sendBeacon iz wp_footer — botovi koji ne izvršavaju JS se ne broje).
 * Sprema se jedan redak u `wp_kurs_stats`: dan, vrsta stranice (+ ID objekta, liga),
 * očišćeni put, izvor (Facebook/tražilice/izravno/…), vrsta uređaja i dnevni anonimni
 * identifikator posjetitelja.
 *
 * Privatnost: IP adresa se NE sprema. Identifikator = sha256( dnevna sol | IP | UA | host ),
 * skraćen na 16 znakova; sol se mijenja svaki dan i stara se briše, pa isti posjetitelj
 * sutra ima drugi identifikator (nema praćenja kroz vrijeme, nema kolačića ni localStorage).
 * „Posjet" = jedan posjetitelj u jednom danu (redak s entry=1 = prvi pregled tog dana).
 * Prijavljeni korisnici (urednici, admini, zapisničari) se ne broje.
 *
 * Admin: KURS Liga → Statistika (admin/views/stats.php). Redci stariji od RETAIN_DAYS
 * brišu se dnevnim cronom `kurs_stats_cleanup`.
 *
 * PAZI: migracija lokalne baze na produkciju pregazi produkcijsku statistiku.
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Stats {

	const RETAIN_DAYS = 760; // ~25 mjeseci — usporedba s istim mjesecom prošle godine
	const SALT_OPT    = 'kurs_stats_salt';
	const DEDUPE_SEC  = 30;  // isti posjetitelj + isti put unutar 30 s = osvježavanje, ne broji se
	const BOT_RE      = '/bot|crawl|spider|slurp|facebookexternalhit|facebot|embedly|preview|headless|lighthouse|pagespeed|gtmetrix|curl|wget|python|httpclient|okhttp|java\/|monitor|uptime|scanner|axios|node-fetch|go-http/i';

	/** Vrste stranica (ptype) → naziv u adminu. */
	const TYPES = [
		'naslovna' => 'Naslovna',
		'liga'     => 'Liga (tablica, kola, raspored, statistika)',
		'utakmica' => 'Utakmice',
		'ekipa'    => 'Ekipe',
		'igrac'    => 'Igrači',
		'vijest'   => 'Vijesti (članci)',
		'vijesti'  => 'Popis vijesti',
		'mediji'   => 'Za medije',
		'pretraga' => 'Pretraga',
		'stranica' => 'Ostale stranice',
		'404'      => 'Nepostojeće stranice (404)',
		'ostalo'   => 'Ostalo',
	];

	const SOURCES = [
		'izravno'   => 'Izravno (upisana adresa, oznake, aplikacije)',
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'trazilice' => 'Tražilice (Google, Bing…)',
		'ostalo'    => 'Druge stranice',
		'interno'   => 'Unutar weba',
	];

	const DEVICES = [ 'mobitel' => 'Mobitel', 'tablet' => 'Tablet', 'racunalo' => 'Računalo' ];

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . 'stats';
	}

	public static function init(): void {
		add_action( 'wp_footer',          [ __CLASS__, 'beacon' ], 99 );
		add_action( 'rest_api_init',      [ __CLASS__, 'routes' ] );
		add_action( 'admin_menu',         [ __CLASS__, 'admin_menu' ], 21 );
		add_action( 'kurs_stats_cleanup', [ __CLASS__, 'cleanup' ] );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( 'kurs_stats_cleanup' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'kurs_stats_cleanup' );
		} );
	}

	/* ── Kontekst stranice (server) ─────────────────────────────────────── */

	/** [ ptype, obj_id, league ] trenutnog pregleda. */
	public static function context(): array {
		$t = 'ostalo'; $o = 0; $lg = '';
		$qid = (int) get_queried_object_id();
		if ( is_front_page() ) {
			$t = 'naslovna';
		} elseif ( is_singular( 'vijest' ) ) {
			$t = 'vijest'; $o = $qid;
		} elseif ( is_singular( 'kurs_ekipa' ) ) {
			$t = 'ekipa'; $o = $qid;
		} elseif ( is_singular( 'kurs_igrac' ) ) {
			$t = 'igrac'; $o = $qid;
		} elseif ( is_post_type_archive( 'vijest' ) || is_home() || is_tax( 'vijest_kategorija' ) ) {
			$t = 'vijesti';
		} elseif ( is_search() ) {
			$t = 'pretraga';
		} elseif ( is_404() ) {
			$t = '404';
		} elseif ( is_page() ) {
			$slug = (string) get_post_field( 'post_name', $qid );
			if ( $slug === 'kc-liga' || $slug === 'pan-ljetna' ) {
				$t = 'liga'; $lg = $slug === 'kc-liga' ? 'kc' : 'pan';
				$o = (int) ( $_GET['season_id'] ?? 0 );
			} elseif ( $slug === 'utakmica' ) {
				$t = 'utakmica'; $o = (int) ( $_GET['match_id'] ?? 0 );
				$lg = $o ? self::match_league( $o ) : '';
			} elseif ( $slug === 'za-medije' ) {
				$t = 'mediji'; $lg = ( $_GET['liga'] ?? '' ) === 'pan' ? 'pan' : 'kc';
			} else {
				$t = 'stranica'; $o = $qid;
			}
		}
		return [ $t, $o, $lg ];
	}

	private static function match_league( int $match_id ): string {
		if ( ! class_exists( 'Kurs_Match' ) ) return '';
		$div = Kurs_Season::get_division( Kurs_Match::division_of( $match_id ) );
		$s   = $div ? Kurs_Season::get( (int) $div->season_id ) : null;
		return $s ? ( Kurs_Season::is_pan_league( (string) $s->league ) ? 'pan' : 'kc' ) : '';
	}

	/** Beacon u podnožju teme — samo za neprijavljene posjetitelje. */
	public static function beacon(): void {
		if ( is_admin() || is_user_logged_in() || is_feed() || is_preview() ) return;
		[ $t, $o, $lg ] = self::context();
		$cfg = wp_json_encode( [ 'u' => rest_url( 'kurs/v1/posjet' ), 't' => $t, 'o' => $o, 'l' => $lg ] );
		?>
<script>/* KURS statistika posjeta — anonimno, bez kolačića */(function(c){try{if(navigator.webdriver)return;var b=JSON.stringify({t:c.t,o:c.o,l:c.l,p:location.pathname+location.search,r:document.referrer});if(navigator.sendBeacon&&navigator.sendBeacon(c.u,new Blob([b],{type:'text/plain'})))return;fetch(c.u,{method:'POST',body:b,keepalive:true,credentials:'omit'});}catch(e){}})(<?php echo $cfg; ?>);</script>
		<?php
	}

	/* ── Zapis posjeta (REST) ──────────────────────────────────────────── */

	public static function routes(): void {
		register_rest_route( 'kurs/v1', '/posjet', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'hit' ],
			'permission_callback' => '__return_true',
		] );
	}

	public static function hit( WP_REST_Request $req ): WP_REST_Response {
		$none = new WP_REST_Response( null, 204 );
		$ua   = substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 400 );
		if ( $ua === '' || preg_match( self::BOT_RE, $ua ) ) return $none;
		$d = json_decode( (string) $req->get_body(), true );
		if ( ! is_array( $d ) ) return $none;

		$t    = isset( self::TYPES[ $d['t'] ?? '' ] ) ? (string) $d['t'] : 'ostalo';
		$o    = max( 0, (int) ( $d['o'] ?? 0 ) );
		$lg   = in_array( $d['l'] ?? '', [ 'kc', 'pan' ], true ) ? (string) $d['l'] : '';
		$raw  = substr( (string) ( $d['p'] ?? '/' ), 0, 600 );
		$path = self::clean_path( $raw );
		[ $src, $ref ] = self::source( (string) ( $d['r'] ?? '' ), $raw );
		$dev  = self::device( $ua );
		$vis  = self::visitor( $ua );
		$day  = current_time( 'Y-m-d' );
		$now  = current_time( 'mysql' );

		global $wpdb;
		$tb = self::table();
		// osvježavanje iste stranice unutar DEDUPE_SEC se ne broji
		$dup = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM $tb WHERE day = %s AND visitor = %s AND path = %s AND ts > %s LIMIT 1",
			$day, $vis, $path, gmdate( 'Y-m-d H:i:s', strtotime( $now ) - self::DEDUPE_SEC )
		) );
		if ( $dup ) return $none;
		$seen = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $tb WHERE day = %s AND visitor = %s LIMIT 1", $day, $vis ) );

		$wpdb->insert( $tb, [
			'ts' => $now, 'day' => $day, 'visitor' => $vis, 'entry' => $seen ? 0 : 1,
			'ptype' => $t, 'obj' => $o, 'league' => $lg, 'path' => $path,
			'src' => $src, 'ref' => $ref, 'device' => $dev,
		] );
		return $none;
	}

	/** Put bez nebitnih parametara (utm, fbclid, pretraga…) — zadržava samo parametre webu bitne. */
	public static function clean_path( string $raw ): string {
		$parts = wp_parse_url( $raw );
		$path  = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$keep  = [];
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $q );
			foreach ( [ 'tab', 'season_id', 'division_id', 'kolo', 'match_id', 'liga', 'tip' ] as $k ) {
				if ( isset( $q[ $k ] ) && is_scalar( $q[ $k ] ) && $q[ $k ] !== '' ) $keep[ $k ] = sanitize_key( (string) $q[ $k ] );
			}
		}
		return substr( $path . ( $keep ? '?' . http_build_query( $keep ) : '' ), 0, 190 );
	}

	/** [ izvor, domena ] iz referrera (i fbclid/utm_source u adresi stranice). */
	public static function source( string $referrer, string $page ): array {
		parse_str( (string) wp_parse_url( $page, PHP_URL_QUERY ), $q );
		$utm = strtolower( (string) ( $q['utm_source'] ?? '' ) );
		if ( isset( $q['fbclid'] ) || in_array( $utm, [ 'facebook', 'fb', 'm.facebook.com' ], true ) ) return [ 'facebook', 'facebook.com' ];
		if ( in_array( $utm, [ 'instagram', 'ig' ], true ) ) return [ 'instagram', 'instagram.com' ];

		$referrer = trim( $referrer );
		if ( $referrer === '' ) return [ 'izravno', '' ];
		if ( str_starts_with( $referrer, 'android-app://' ) ) {
			$app = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
			if ( str_contains( $app, 'facebook' ) ) return [ 'facebook', 'facebook.com' ];
			if ( str_contains( $app, 'instagram' ) ) return [ 'instagram', 'instagram.com' ];
			if ( str_contains( $app, 'google' ) ) return [ 'trazilice', 'google' ];
			return [ 'ostalo', substr( $app, 0, 100 ) ];
		}
		$host = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
		if ( $host === '' ) return [ 'izravno', '' ];
		$host = preg_replace( '/^(www|m|l|lm|mobile|web)\./', '', $host );
		$own  = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( $host === $own ) return [ 'interno', '' ];
		if ( preg_match( '/(^|\.)(facebook\.com|fb\.com|fb\.me|messenger\.com)$/', $host ) ) return [ 'facebook', 'facebook.com' ];
		if ( preg_match( '/(^|\.)instagram\.com$/', $host ) ) return [ 'instagram', 'instagram.com' ];
		if ( preg_match( '/(^|\.)(google\.[a-z.]+|bing\.com|duckduckgo\.com|yahoo\.[a-z.]+|yandex\.[a-z.]+|ecosia\.org|search\.brave\.com|startpage\.com)$/', $host ) ) {
			return [ 'trazilice', preg_replace( '/\..*$/', '', $host ) === 'search' ? 'brave' : preg_replace( '/\..*$/', '', $host ) ];
		}
		return [ 'ostalo', substr( $host, 0, 100 ) ];
	}

	public static function device( string $ua ): string {
		if ( preg_match( '/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $ua ) ) return 'tablet';
		if ( preg_match( '/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|Opera Mini/i', $ua ) ) return 'mobitel';
		return 'racunalo';
	}

	/** Dnevni anonimni identifikator — IP se ne sprema, sol se mijenja svaki dan. */
	private static function visitor( string $ua ): string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = trim( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
		}
		return substr( hash( 'sha256', self::salt() . '|' . $ip . '|' . $ua . '|' . home_url() ), 0, 16 );
	}

	private static function salt(): string {
		$today = current_time( 'Y-m-d' );
		$s     = get_option( self::SALT_OPT );
		if ( ! is_array( $s ) || ( $s['d'] ?? '' ) !== $today || empty( $s['s'] ) ) {
			$s = [ 'd' => $today, 's' => wp_generate_password( 32, true, true ) ];
			update_option( self::SALT_OPT, $s, false );
		}
		return (string) $s['s'];
	}

	public static function cleanup(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE day < %s', gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) - self::RETAIN_DAYS * DAY_IN_SECONDS ) ) );
	}

	/* ── Izvještaji (admin) ────────────────────────────────────────────── */

	public static function admin_menu(): void {
		add_submenu_page( 'kurs-dashboard', 'Statistika posjeta', 'Statistika', KURS_CAP, 'kurs-stats', [ __CLASS__, 'page' ] );
	}

	public static function page(): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemate ovlasti.' );
		include KURS_DIR . 'admin/views/stats.php';
	}

	/** Raspon iz GET-a: [ od, do, naziv, ključ ]. */
	public static function range(): array {
		$today = current_time( 'Y-m-d' );
		$key   = sanitize_key( $_GET['raspon'] ?? '30' );
		$ts    = strtotime( $today );
		$od    = (string) ( $_GET['od'] ?? '' );
		$do    = (string) ( $_GET['do'] ?? '' );
		if ( $key === 'vlastito' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $od ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $do ) && $od <= $do ) {
			return [ $od, $do, date_i18n( 'j. n. Y.', strtotime( $od ) ) . ' – ' . date_i18n( 'j. n. Y.', strtotime( $do ) ), $key ];
		}
		$mjeseci = [ 1 => 'siječanj', 'veljača', 'ožujak', 'travanj', 'svibanj', 'lipanj', 'srpanj', 'kolovoz', 'rujan', 'listopad', 'studeni', 'prosinac' ];
		switch ( $key ) {
			case '7':      return [ date( 'Y-m-d', $ts - 6 * DAY_IN_SECONDS ), $today, 'zadnjih 7 dana', $key ];
			case '90':     return [ date( 'Y-m-d', $ts - 89 * DAY_IN_SECONDS ), $today, 'zadnjih 90 dana', $key ];
			case 'mjesec': return [ date( 'Y-m-01', $ts ), $today, $mjeseci[ (int) date( 'n', $ts ) ] . ' ' . date( 'Y', $ts ) . '.', $key ];
			case 'prosli':
				$f = strtotime( date( 'Y-m-01', $ts ) . ' -1 month' );
				return [ date( 'Y-m-01', $f ), date( 'Y-m-t', $f ), $mjeseci[ (int) date( 'n', $f ) ] . ' ' . date( 'Y', $f ) . '.', $key ];
			case 'godina': return [ date( 'Y-01-01', $ts ), $today, date( 'Y', $ts ) . '. godina', $key ];
			default:       return [ date( 'Y-m-d', $ts - 29 * DAY_IN_SECONDS ), $today, 'zadnjih 30 dana', '30' ];
		}
	}

	/** Zbirni brojevi za raspon: pregledi, posjeti, udio mobitela, pregledi po posjetu. */
	public static function totals( string $od, string $do ): array {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT COUNT(*) AS views, COALESCE(SUM(entry),0) AS visits,
			        COALESCE(SUM(entry AND device = "mobitel"),0) AS mob
			 FROM ' . self::table() . ' WHERE day BETWEEN %s AND %s', $od, $do
		) );
		$views = (int) ( $r->views ?? 0 ); $visits = (int) ( $r->visits ?? 0 );
		return [
			'views'  => $views,
			'visits' => $visits,
			'ppv'    => $visits ? $views / $visits : 0,
			'mob'    => $visits ? (int) $r->mob / $visits : 0,
		];
	}

	public static function daily( string $od, string $do ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT day, COUNT(*) AS views, SUM(entry) AS visits FROM ' . self::table() . ' WHERE day BETWEEN %s AND %s GROUP BY day', $od, $do
		), OBJECT_K );
		$out = [];
		for ( $t = strtotime( $od ), $end = strtotime( $do ); $t <= $end; $t += DAY_IN_SECONDS ) {
			$d = date( 'Y-m-d', $t );
			$out[ $d ] = [ 'views' => (int) ( $rows[ $d ]->views ?? 0 ), 'visits' => (int) ( $rows[ $d ]->visits ?? 0 ) ];
		}
		return $out;
	}

	/** Pregledi po vrsti stranice (liga i utakmice razdvojene po natjecanju). */
	public static function by_type( string $od, string $do ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT ptype, IF(ptype IN ("liga","utakmica","mediji"), league, "") AS lg, COUNT(*) AS views, COUNT(DISTINCT day, visitor) AS visits
			 FROM ' . self::table() . ' WHERE day BETWEEN %s AND %s GROUP BY ptype, lg ORDER BY views DESC', $od, $do
		) );
	}

	/** Posjeti po izvoru (prvi pregled u danu). */
	public static function by_source( string $od, string $do ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT src, COUNT(*) AS visits FROM ' . self::table() . ' WHERE entry = 1 AND day BETWEEN %s AND %s GROUP BY src ORDER BY visits DESC', $od, $do
		) );
	}

	public static function top_referrers( string $od, string $do, int $limit = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT ref, COUNT(*) AS visits FROM ' . self::table() . ' WHERE entry = 1 AND src = "ostalo" AND ref <> "" AND day BETWEEN %s AND %s GROUP BY ref ORDER BY visits DESC LIMIT %d', $od, $do, $limit
		) );
	}

	public static function by_device( string $od, string $do ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT device, COUNT(*) AS visits FROM ' . self::table() . ' WHERE entry = 1 AND day BETWEEN %s AND %s GROUP BY device ORDER BY visits DESC', $od, $do
		) );
	}

	/** Posjeti po danu u tjednu (1 = ponedjeljak … 7 = nedjelja). */
	public static function by_weekday( string $od, string $do ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT WEEKDAY(day) + 1 AS wd, COUNT(*) AS visits FROM ' . self::table() . ' WHERE entry = 1 AND day BETWEEN %s AND %s GROUP BY wd', $od, $do
		), OBJECT_K );
		$out = [];
		for ( $i = 1; $i <= 7; $i++ ) $out[ $i ] = (int) ( $rows[ $i ]->visits ?? 0 );
		return $out;
	}

	/** Najgledaniji objekti jedne vrste (vijest, utakmica, ekipa, igrac) s nazivom i poveznicom. */
	public static function top_objects( string $ptype, string $od, string $do, int $limit = 10 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT obj, COUNT(*) AS views, COUNT(DISTINCT day, visitor) AS visits FROM ' . self::table() . '
			 WHERE ptype = %s AND obj > 0 AND day BETWEEN %s AND %s GROUP BY obj ORDER BY views DESC LIMIT %d', $ptype, $od, $do, $limit
		) );
		foreach ( $rows as $r ) {
			$r->title = ''; $r->url = '';
			if ( $ptype === 'utakmica' ) {
				$m = class_exists( 'Kurs_Match' ) ? Kurs_Match::get( (int) $r->obj ) : null;
				if ( $m ) {
					$r->title = $m->home_name . ' – ' . $m->away_name . ( $m->status === 'finished' ? ' ' . (int) $m->home_score . ':' . (int) $m->away_score : '' );
					$r->url   = add_query_arg( 'match_id', (int) $r->obj, home_url( '/utakmica/' ) );
				}
			} elseif ( get_post( (int) $r->obj ) ) {
				$r->title = get_the_title( (int) $r->obj );
				$r->url   = get_permalink( (int) $r->obj );
			}
			if ( $r->title === '' ) $r->title = '#' . (int) $r->obj . ' (obrisano)';
		}
		return $rows;
	}

	/** Najposjećenije adrese (put). */
	public static function top_paths( string $od, string $do, int $limit = 15 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT path, MAX(ptype) AS ptype, COUNT(*) AS views, COUNT(DISTINCT day, visitor) AS visits FROM ' . self::table() . '
			 WHERE day BETWEEN %s AND %s GROUP BY path ORDER BY views DESC LIMIT %d', $od, $do, $limit
		) );
	}

	/** Prvi dan s podacima (za napomenu „brojanje od …"). */
	public static function first_day(): string {
		global $wpdb;
		return (string) $wpdb->get_var( 'SELECT MIN(day) FROM ' . self::table() );
	}
}
