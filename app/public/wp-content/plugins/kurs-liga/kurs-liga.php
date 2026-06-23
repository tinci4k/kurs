<?php
/**
 * Plugin Name:  KURS Liga
 * Plugin URI:   https://kurskc.hr
 * Description:  Sustav upravljanja malonogometnim ligama — KURS (Koprivnička udruga za rekreativni sport).
 * Version:      1.2.0
 * Author:       Amondi Media
 * Author URI:   https://amondi-media.com
 * Text Domain:  kurs-liga
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'KURS_VERSION', '1.14.2' );
define( 'KURS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'KURS_URL',     plugin_dir_url( __FILE__ ) );
define( 'KURS_PREFIX',  'kurs_' );
define( 'KURS_CAP',     'manage_kurs_liga' ); // ovlast za pristup KURS administraciji (uz admina i urednici)

require_once KURS_DIR . 'includes/class-database.php';
require_once KURS_DIR . 'includes/helpers.php';
require_once KURS_DIR . 'includes/class-playoff.php';
require_once KURS_DIR . 'includes/class-cpt.php';
require_once KURS_DIR . 'includes/class-season.php';
require_once KURS_DIR . 'includes/class-match.php';
require_once KURS_DIR . 'includes/class-ticker.php';
require_once KURS_DIR . 'includes/class-roles.php';

new Kurs_CPT();
Kurs_Ticker::init();
Kurs_Roles::init();

add_action( 'plugins_loaded', [ 'Kurs_Database', 'maybe_upgrade' ] );

if ( is_admin() ) {
	require_once KURS_DIR . 'admin/class-admin.php';
	new Kurs_Admin();
}

register_activation_hook( __FILE__, [ 'Kurs_Database', 'install' ] );
register_activation_hook( __FILE__, function () {
	( new Kurs_CPT() )->register();
	flush_rewrite_rules();
} );
register_activation_hook( __FILE__, [ 'Kurs_Roles', 'grant_caps' ] );

/* Flush jednom ako je potrebno (npr. nakon deaktivacije/aktivacije) */
add_action( 'init', function () {
	if ( get_option( 'kurs_flush_needed' ) ) {
		flush_rewrite_rules();
		delete_option( 'kurs_flush_needed' );
	}
}, 99 );

/* Jednokratni flush — briše se sam */
add_action( 'init', function () {
	if ( ! get_option( 'kurs_cpt_flushed_v2' ) ) {
		flush_rewrite_rules();
		update_option( 'kurs_cpt_flushed_v2', 1 );
	}
}, 100 );
