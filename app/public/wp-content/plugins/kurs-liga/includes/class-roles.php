<?php
/**
 * Uloge i ovlasti — pristup KURS administraciji za urednike.
 *
 * Plugin više ne gleda `manage_options` nego vlastitu ovlast `KURS_CAP`
 * (`manage_kurs_liga`), pa je možemo dodijeliti urednicima (uloga „Author")
 * bez davanja punih administratorskih prava.
 *
 * Urednik (Author) dobiva:
 *   • KURS_CAP            → pristup izborniku „KURS Liga" (sezone, utakmice, ekipe…)
 *   • edit_others_posts   → uređivanje SVIH vijesti i sponzora (ne samo svojih)
 *   • delete_others_posts → brisanje tuđih vijesti/sponzora
 * (Author već ima: edit_posts, publish_posts, delete_posts, upload_files →
 *  Mediji + dodavanje/objava vlastitih vijesti i sponzora.)
 *
 * Stranice (Kontakt, O nama, liga stranice) koriste `page` ovlasti i ostaju
 * isključivo administratorske.
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Roles {

	/** Verzija sheme ovlasti — povećaj da se grant ponovno primijeni. */
	const CAPS_VER = 1;

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_sync_caps' ] );
		add_action( 'admin_menu', [ __CLASS__, 'trim_menus_for_editors' ], 999 );
	}

	/** Primijeni ovlasti jednom (ili nakon povećanja CAPS_VER). */
	public static function maybe_sync_caps(): void {
		if ( (int) get_option( 'kurs_caps_ver' ) === self::CAPS_VER ) return;
		self::grant_caps();
		update_option( 'kurs_caps_ver', self::CAPS_VER );
	}

	/** Dodijeli ovlasti ulogama (poziva se i na aktivaciji plugina). */
	public static function grant_caps(): void {
		// Administrator MORA dobiti KURS_CAP — plugin više ne gleda manage_options.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( KURS_CAP ) ) {
			$admin->add_cap( KURS_CAP );
		}

		// Urednik = uloga „Author".
		$author = get_role( 'author' );
		if ( $author ) {
			foreach ( [ KURS_CAP, 'edit_others_posts', 'delete_others_posts' ] as $cap ) {
				$author->add_cap( $cap );
			}
		}
	}

	/**
	 * Urednicima (svima koji nisu administratori) ostavi u izborniku samo bitno:
	 * Nadzorna ploča, Mediji, Vijesti, Sponzori, KURS Liga, Profil. Ostalo sakrij.
	 */
	public static function trim_menus_for_editors(): void {
		if ( current_user_can( 'manage_options' ) ) return; // administratore ne diramo

		remove_menu_page( 'tools.php' );         // Alati
		remove_menu_page( 'edit-comments.php' ); // Komentari (ako su negdje vraćeni)
	}
}
