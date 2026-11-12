<?php
defined( 'ABSPATH' ) || exit;

function kurskc_enqueue_assets() {
	wp_enqueue_style(
		'kurskc-fonts',
		'https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,400;0,500;0,600;0,700;1,500;1,600&family=Barlow+Semi+Condensed:ital,wght@0,500;0,600;0,700;0,800;1,700;1,800&display=swap',
		[],
		null
	);
	wp_enqueue_style( 'kurskc-main', KURSKC_URI . '/assets/css/main.css', [ 'kurskc-fonts' ], KURSKC_VERSION );
	wp_enqueue_script( 'kurskc-main', KURSKC_URI . '/assets/js/main.js', [], KURSKC_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'kurskc_enqueue_assets' );
