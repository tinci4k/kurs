<?php
/**
 * Pretraga (tema 1.0.56): grupirani rezultati (ekipe · igrači · vijesti · stranice), isticanje pojma,
 * filteri ?tip=, i brzi prijedlozi dok se tipka (REST kurskc/v1/search, zaglavlje).
 * Baza je utf8mb4_unicode_520_ci → „cekicari" nalazi „Čekićari" (LIKE bez obzira na kvačice).
 */
defined( 'ABSPATH' ) || exit;

const KURSKC_SEARCH_TYPES = [
	'ekipe'   => [ 'kurs_ekipa', 'Ekipe' ],
	'igraci'  => [ 'kurs_igrac', 'Igrači' ],
	'vijesti' => [ 'vijest', 'Vijesti' ],
	'stranice'=> [ 'page', 'Stranice' ],
];

/** Glavni upit pretrage: samo vijesti (ostale grupe search.php dohvaća sam), 12 po stranici. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() ) return;
	$q->set( 'post_type', [ 'vijest' ] );
	$q->set( 'posts_per_page', 12 );
} );

/** Upit po vrsti. */
function kurskc_search_query( string $post_type, string $s, int $limit, int $paged = 1 ): WP_Query {
	return new WP_Query( [
		'post_type' => $post_type, 'post_status' => 'publish', 's' => $s, 'posts_per_page' => $limit, 'paged' => $paged,
		'orderby' => $post_type === 'vijest' ? 'relevance date' : 'title', 'order' => $post_type === 'vijest' ? 'DESC' : 'ASC',
		'no_found_rows' => false, 'ignore_sticky_posts' => true,
	] );
}

/** Regex za pojam bez obzira na kvačice (c↔č↔ć, s↔š, z↔ž, d↔đ). */
function kurskc_search_regex( string $s ): string {
	$map   = [ 'c' => '[cčćCČĆ]', 's' => '[sšSŠ]', 'z' => '[zžZŽ]', 'd' => '[dđDĐ]', 'č' => '[cčćCČĆ]', 'ć' => '[cčćCČĆ]', 'š' => '[sšSŠ]', 'ž' => '[zžZŽ]', 'đ' => '[dđDĐ]' ];
	$parts = [];
	foreach ( preg_split( '/\s+/u', trim( $s ) ) as $w ) {
		if ( mb_strlen( $w ) < 2 ) continue;
		$re = '';
		foreach ( preg_split( '//u', mb_strtolower( $w ), -1, PREG_SPLIT_NO_EMPTY ) as $ch ) $re .= $map[ $ch ] ?? preg_quote( $ch, '/' );
		$parts[] = $re;
	}
	return $parts ? '/(' . implode( '|', $parts ) . ')/iu' : '';
}

/** Istakni pojam (<mark>) u već escapiranom tekstu. */
function kurskc_search_mark( string $text, string $s ): string {
	$t  = esc_html( $text );
	$re = kurskc_search_regex( $s );
	return $re ? (string) preg_replace( $re, '<mark>$1</mark>', $t ) : $t;
}

/** Isječak teksta oko prvog pogotka. */
function kurskc_search_snippet( string $content, string $s, int $len = 170 ): string {
	$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $content ) ) ) );
	$re    = kurskc_search_regex( $s );
	$pos   = 0;
	if ( $re && preg_match( $re, $plain, $m, PREG_OFFSET_CAPTURE ) ) $pos = mb_strlen( substr( $plain, 0, $m[0][1] ) );
	$start = max( 0, $pos - 50 );
	$cut   = mb_substr( $plain, $start, $len );
	return ( $start ? '…' : '' ) . $cut . ( mb_strlen( $plain ) > $start + $len ? '…' : '' );
}

/** Zadnja ekipa igrača: [ team_id, naziv, division_id ] ili null. */
function kurskc_player_last_team( int $player_id ): ?array {
	global $wpdb;
	$p   = $wpdb->prefix . 'kurs_';
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT sq.team_id, sq.division_id, t.post_title AS team FROM {$p}squads sq JOIN {$wpdb->posts} t ON t.ID = sq.team_id
		 JOIN {$p}divisions d ON d.id = sq.division_id JOIN {$p}seasons s ON s.id = d.season_id
		 WHERE sq.player_id = %d ORDER BY s.year DESC, s.id DESC LIMIT 1", $player_id ) );
	return $row ? [ (int) $row->team_id, $row->team, (int) $row->division_id ] : null;
}

/* ── REST: brzi prijedlozi ── GET /wp-json/kurskc/v1/search?q=klik */
add_action( 'rest_api_init', function () {
	register_rest_route( 'kurskc/v1', '/search', [
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'args'    => [ 'q' => [ 'type' => 'string', 'required' => true ] ],
		'callback' => function ( WP_REST_Request $r ) {
			$s = trim( sanitize_text_field( (string) $r['q'] ) );
			if ( mb_strlen( $s ) < 2 ) return [ 'groups' => [] ];
			$groups = [];
			foreach ( [ 'ekipe' => 4, 'igraci' => 5, 'vijesti' => 4 ] as $key => $lim ) {
				[ $pt, $label ] = KURSKC_SEARCH_TYPES[ $key ];
				$items = [];
				foreach ( kurskc_search_query( $pt, $s, $lim )->posts as $post ) {
					$it = [ 'title' => kurskc_search_mark( get_the_title( $post ), $s ), 'url' => get_permalink( $post ), 'sub' => '', 'img' => '' ];
					if ( $pt === 'kurs_ekipa' && function_exists( 'kurs_team_badge_html' ) ) $it['img'] = kurs_team_badge_html( $post->ID, 28 );
					if ( $pt === 'kurs_igrac' ) { $lt = kurskc_player_last_team( $post->ID ); $it['sub'] = $lt ? esc_html( $lt[1] ) : ''; }
					if ( $pt === 'vijest' ) $it['sub'] = esc_html( get_the_date( 'j. n. Y.', $post ) );
					$items[] = $it;
				}
				if ( $items ) $groups[] = [ 'key' => $key, 'label' => $label, 'items' => $items ];
			}
			return [ 'groups' => $groups, 'all' => add_query_arg( 's', rawurlencode( $s ), home_url( '/' ) ) ];
		},
	] );
} );
