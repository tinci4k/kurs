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

define( 'KURS_VERSION', '1.33.2' );
define( 'KURS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'KURS_URL',     plugin_dir_url( __FILE__ ) );
define( 'KURS_PREFIX',  'kurs_' );
define( 'KURS_CAP',     'manage_kurs_liga' ); // ovlast za pristup KURS administraciji (uz admina i urednici)
define( 'KURS_RECORD_CAP', 'kurs_zapisnik' );  // vođenje zapisnika utakmice (/zapisnik/) — uloga Zapisničar + svi s KURS_CAP

require_once KURS_DIR . 'includes/class-database.php';
require_once KURS_DIR . 'includes/helpers.php';
require_once KURS_DIR . 'includes/class-playoff.php';
require_once KURS_DIR . 'includes/class-cpt.php';
require_once KURS_DIR . 'includes/class-season.php';
require_once KURS_DIR . 'includes/class-match.php';
require_once KURS_DIR . 'includes/class-players.php';
require_once KURS_DIR . 'includes/class-ticker.php';
require_once KURS_DIR . 'includes/class-rounds.php';
require_once KURS_DIR . 'includes/class-roles.php';
require_once KURS_DIR . 'includes/class-zapisnik.php';
require_once KURS_DIR . 'includes/class-presjek.php';
require_once KURS_DIR . 'includes/class-stats.php';
require_once KURS_DIR . 'includes/class-portal.php';
require_once KURS_DIR . 'includes/class-sponsors.php';
require_once KURS_DIR . 'includes/class-branding.php';

new Kurs_CPT();
Kurs_Ticker::init();
Kurs_Roles::init();
Kurs_Zapisnik::init();
Kurs_Presjek::init();
Kurs_Stats::init();
Kurs_Portal::init();
Kurs_Sponsors::init();
Kurs_Branding::init();

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

/* Samoizlječenje rewrite pravila: ako u spremljenim pravilima nema /ekipe/ (npr. nakon
 * migracije baze ili flusha prije registracije CPT-ova), profili ekipa/igrača i /vijesti/
 * vraćaju 404 → regeneriraj ih. Na `init` 100 su CPT-ovi plugina i teme već registrirani. */
add_action( 'init', function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && get_option( 'permalink_structure' ) && ! preg_grep( '#^ekipe/#', array_keys( $rules ) ) ) {
		flush_rewrite_rules( false );
	}
}, 100 );
