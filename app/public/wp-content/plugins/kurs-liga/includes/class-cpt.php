<?php
defined( 'ABSPATH' ) || exit;

class Kurs_CPT {

	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
	}

	public function register(): void {

		register_post_type( 'kurs_ekipa', [
			'labels' => [
				'name'          => 'Ekipe',
				'singular_name' => 'Ekipa',
				'add_new_item'  => 'Dodaj ekipu',
				'edit_item'     => 'Uredi ekipu',
				'search_items'  => 'Pretraži ekipe',
				'not_found'     => 'Nema ekipa.',
			],
			'public'       => true,
			'show_in_menu' => false, // uređuje se unutar KURS Liga plugina, ne kao zaseban izbornik
			'menu_icon'    => 'dashicons-groups',
			'supports'     => [ 'title', 'thumbnail', 'editor' ],
			'has_archive'  => false,
			'rewrite'      => [ 'slug' => 'ekipe' ],
			'show_in_rest' => true,
		] );

		register_post_type( 'kurs_igrac', [
			'labels' => [
				'name'          => 'Igrači',
				'singular_name' => 'Igrač',
				'add_new_item'  => 'Dodaj igrača',
				'edit_item'     => 'Uredi igrača',
				'search_items'  => 'Pretraži igrače',
				'not_found'     => 'Nema igrača.',
			],
			'public'       => true,
			'show_in_menu' => false, // uređuje se unutar KURS Liga plugina, ne kao zaseban izbornik
			'menu_icon'    => 'dashicons-admin-users',
			'supports'     => [ 'title', 'thumbnail' ],
			'has_archive'  => false,
			'rewrite'      => [ 'slug' => 'igraci' ],
			'show_in_rest' => true,
		] );
	}
}
