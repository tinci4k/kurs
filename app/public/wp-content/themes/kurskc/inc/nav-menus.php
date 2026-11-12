<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lige (divizije) aktualne sezone natjecanja — za izravne poveznice u izborniku i podnožju
 * (tema 1.0.47): [ [ 'id', 'name', 'url', 'icon' ], … ]. Prazno bez plugina ili sezone.
 */
function kurskc_league_divisions( bool $is_pan ): array {
	static $cache = [];
	if ( isset( $cache[ (int) $is_pan ] ) ) return $cache[ (int) $is_pan ];
	$out = [];
	if ( class_exists( 'Kurs_Season' ) && function_exists( 'kurs_league_url' ) ) {
		$season = Kurs_Season::current_for_league( $is_pan );
		foreach ( $season ? Kurs_Season::get_divisions( (int) $season->id ) : [] as $d ) {
			$out[] = [
				'id'   => (int) $d->id,
				'name' => $d->name,
				'url'  => kurs_league_url( (int) $d->id ) . '#liga',
				'icon' => $is_pan ? 'Pan Ljetna' : $d->name,
			];
		}
	}
	return $cache[ (int) $is_pan ] = $out;
}

function kurskc_register_menus() {
	register_nav_menus( [
		'primary' => 'Glavni izbornik',
		'footer'  => 'Footer izbornik',
	] );
}
add_action( 'after_setup_theme', 'kurskc_register_menus' );
