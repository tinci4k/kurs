<?php
/**
 * Plugin Name:  KURS Liga
 * Plugin URI:   https://kurskc.hr
 * Description:  Sustav upravljanja malonogometnim ligama — KURS (Koprivnička udruga za rekreativni sport).
 * Version:      1.0.0
 * Author:       Amondi Media
 * Author URI:   https://amondi-media.com
 * Text Domain:  kurs-liga
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'KURS_VERSION', '1.0.0' );
define( 'KURS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'KURS_URL',     plugin_dir_url( __FILE__ ) );
define( 'KURS_PREFIX',  'kurs_' );

require_once KURS_DIR . 'includes/class-database.php';
require_once KURS_DIR . 'includes/class-cpt.php';
require_once KURS_DIR . 'includes/class-season.php';
require_once KURS_DIR . 'includes/class-match.php';

new Kurs_CPT();

add_action( 'plugins_loaded', [ 'Kurs_Database', 'maybe_upgrade' ] );

if ( is_admin() ) {
	require_once KURS_DIR . 'admin/class-admin.php';
	new Kurs_Admin();
}

register_activation_hook( __FILE__, [ 'Kurs_Database', 'install' ] );
