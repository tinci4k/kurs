<?php
defined( 'ABSPATH' ) || exit;

// Ekipe (kurs_ekipa) i Igrači (kurs_igrac) registrira kurs-liga plugin.

function kurskc_register_post_types() {

	// ── Vijesti ──────────────────────────────────────────────────────────────
	register_post_type( 'vijest', [
		'labels' => [
			'name'               => 'Vijesti',
			'singular_name'      => 'Vijest',
			'add_new_item'       => 'Dodaj vijest',
			'edit_item'          => 'Uredi vijest',
			'search_items'       => 'Pretraži vijesti',
			'not_found'          => 'Nema vijesti.',
		],
		'public'       => true,
		'menu_icon'    => 'dashicons-megaphone',
		'supports'     => [ 'title', 'thumbnail', 'editor', 'excerpt' ],
		'has_archive'  => true,
		'rewrite'      => [ 'slug' => 'vijesti' ],
		'show_in_rest' => true,
		'taxonomies'   => [ 'vijest_kategorija' ],
	] );

	// ── Kategorija vijesti ───────────────────────────────────────────────────
	register_taxonomy( 'vijest_kategorija', 'vijest', [
		'labels' => [
			'name'              => 'Kategorije',
			'singular_name'     => 'Kategorija',
			'search_items'      => 'Pretraži kategorije',
			'all_items'         => 'Sve kategorije',
			'edit_item'         => 'Uredi kategoriju',
			'update_item'       => 'Ažuriraj kategoriju',
			'add_new_item'      => 'Dodaj kategoriju',
			'new_item_name'     => 'Nova kategorija',
			'menu_name'         => 'Kategorije',
		],
		'hierarchical'      => true,   // kao kategorije, ne tagovi
		'public'            => true,
		'show_in_rest'      => true,   // vidljivo u Gutenbergu
		'rewrite'           => [ 'slug' => 'vijesti/kategorija' ],
		'show_admin_column' => true,
	] );
}
add_action( 'init', 'kurskc_register_post_types' );

/**
 * Kreiraj defaultne kategorije ako još ne postoje.
 * Pokreće se jednom nakon registracije taksonomije.
 */
add_action( 'init', function () {
	$defaults = [
		'vijesti'       => 'Vijesti',        // opće vijesti
		'kc-liga'       => 'KC Liga',
		'pan-ljetna'    => 'Pan Ljetna liga',
	];

	foreach ( $defaults as $slug => $name ) {
		if ( ! term_exists( $slug, 'vijest_kategorija' ) ) {
			wp_insert_term( $name, 'vijest_kategorija', [ 'slug' => $slug ] );
		}
	}
}, 20 );
