<?php
defined( 'ABSPATH' ) || exit;

function kurskc_enqueue_assets() {
	wp_enqueue_style( 'kurskc-main', KURSKC_URI . '/assets/css/main.css', [], KURSKC_VERSION );
	wp_enqueue_script( 'kurskc-main', KURSKC_URI . '/assets/js/main.js', [], KURSKC_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'kurskc_enqueue_assets' );
