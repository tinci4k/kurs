<?php
defined( 'ABSPATH' ) || exit;

// Ekipe (kurs_ekipa) i Igrači (kurs_igrac) registrira kurs-liga plugin.

function kurskc_register_post_types() {

	// Vijesti / Novosti
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
	] );
}
add_action( 'init', 'kurskc_register_post_types' );
