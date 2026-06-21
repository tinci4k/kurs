<?php
defined( 'ABSPATH' ) || exit;

define( 'KURSKC_VERSION', '1.0.0' );
define( 'KURSKC_DIR', get_template_directory() );
define( 'KURSKC_URI', get_template_directory_uri() );

require_once KURSKC_DIR . '/inc/post-types.php';
require_once KURSKC_DIR . '/inc/enqueue.php';
require_once KURSKC_DIR . '/inc/nav-menus.php';

function kurskc_setup() {
	load_theme_textdomain( 'kurskc', KURSKC_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'customize-selective-refresh-widgets' );
}
add_action( 'after_setup_theme', 'kurskc_setup' );
