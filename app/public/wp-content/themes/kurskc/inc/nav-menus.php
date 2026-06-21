<?php
defined( 'ABSPATH' ) || exit;

function kurskc_register_menus() {
	register_nav_menus( [
		'primary' => 'Glavni izbornik',
		'footer'  => 'Footer izbornik',
	] );
}
add_action( 'after_setup_theme', 'kurskc_register_menus' );
