<?php
/**
 * Paket 2026-11-13: rosteri HEP-ODS, Klikaj.hr / Zona tri, ENDURA fit, AL Nemčić/Klesarstvo Habek
 * (popisi s maila/WhatsAppa), odgovori iz presjeka (voditelji/treneri, imena) i sponzori lige
 * (Grad Koprivnica glavni, Marks više nije, Praona Drnje samo KC liga, Pan obje lige).
 */
defined( 'ABSPATH' ) || exit;

return function ( Kurs_Data_Patches $p ): void {

	/* ── Isti igrač: „Sedlanić” (Profesori, 2025/2026) je Igor Sedlanić, sada u Klikaju (potvrdio korisnik) ──
	   preimenuje se prije rostera, pa ga set_roster() za Klikaj nađe kao postojećeg igrača (povijest ostaje) */
	$p->rename_player_any( 'Profesori OBSKC', 'Sedlanić', 'Igor Sedlanić' );

	/* ── Rosteri (KC 2026/2027) ── */
	$p->set_roster( 'HEP-ODS', [
		'Goran Pakasin', 'Marko Đuranek', 'Goran Seretin', 'Renato Vencl', 'Saša Friščić', 'Mario Martinčić', 'Zdravko Matišić',
		'Tomislav Benotić', 'Josip Dukarić', 'Marino Šurić', 'Marijan Šimota', 'Kristijan Sokač', 'Nikola Besedić', 'Slavko Huzak', 'Mario Brunec',
	] );
	$p->set_roster( 'Klikaj.hr / Zona tri', [
		'Matija Đeno', 'Igor Fulir', 'Krešimir Kolarić', 'Mario Kovaček', 'Tomica Kovaček', 'Goran Maronić', 'Denis Maurović', 'Saša Radanović',
		'Igor Sedlanić', 'Mario Sertić', 'Elvis Sirovec', 'Denis Šipek', 'Atila Šolo', 'Marko Vitelj', 'Tomo Vitelj',
	] );
	$p->set_roster( 'ENDURA fit', [
		'Marijan Karner', 'Filip Betlehem', 'Marijan Salajec', 'Mario Lovasić', 'Tomislav Cvitković', 'Danijel Rožmarić', 'Ivica Lukač',
		'Igor Dokša', 'Antonio Ciler', 'Slaven Petrić', 'Goran Petrić', 'Dalibor Harmadi',
	] );
	$p->set_roster( 'AL Nemčić/Klesarstvo Habek', [
		'Danijel Nemčić', 'Dražen Nemčić', 'Mario Dodik', 'Dražen Polak', 'Danijel Klasić', 'Dražen Ledinski', 'Krešimir Belčić',
		'Tomislav Predragović', 'Mario Habek', 'Igor Krovina', 'Vlado Dragosavljević', 'Tomislav Foruglaš', 'Alen Kušter',
	] );

	/* ── Voditelji / treneri (popisi i presjek) ── */
	$p->set_meta( 'Klikaj.hr / Zona tri', '_kurs_team_rep', 'Goran Ogrizović', 'voditelj' );
	$p->set_meta( 'Profesori OBSKC', '_kurs_team_rep', 'Zlatko Martić', 'voditelj' );
	$p->set_meta( 'Čekićari', '_kurs_team_coach', 'Tihomir Henezi', 'trener' );

	/* ── Imena iz presjeka (ako na nekoj instalaciji još stoji stari oblik) ── */
	$p->rename_player_in_team( 'ZIP Drnje', 'Ilić', 'Matijas Ilić' );
	$p->rename_player_in_team( 'coARt', 'Marko Blažić', 'Marko Blažic' );
	$p->rename_player_in_team( 'Forma Legalis', 'Šimek', 'Zlatko Šimek' );

	/* ── Sponzori lige ── */
	$gk = $p->sponsor( 'Grad Koprivnica' );
	if ( ! $gk ) {
		$gk = (int) wp_insert_post( [ 'post_title' => 'Grad Koprivnica', 'post_type' => 'sponzor', 'post_status' => 'publish', 'menu_order' => 0 ] );
		$p->log[] = 'Sponzor dodan: Grad Koprivnica (glavni, obje lige, koprivnica.hr)';
	}
	update_post_meta( $gk, '_kurs_sponsor_tier', 1 );
	update_post_meta( $gk, '_kurs_sponsor_comp', 'obje' );
	update_post_meta( $gk, '_kurs_sponsor_link', 'https://www.koprivnica.hr/' );
	$logo = KURS_DIR . 'assets/sponsors/grad-koprivnica.png';
	if ( ! has_post_thumbnail( $gk ) && is_file( $logo ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( 'grad-koprivnica.png' );
		copy( $logo, $tmp );
		$att = media_handle_sideload( [ 'name' => 'grad-koprivnica.png', 'tmp_name' => $tmp ], $gk, 'Grad Koprivnica — logo' );
		if ( ! is_wp_error( $att ) ) { set_post_thumbnail( $gk, $att ); $p->log[] = 'Logo Grada Koprivnice postavljen'; }
		else $p->log[] = 'Logo Grada Koprivnice nije postavljen: ' . $att->get_error_message();
	} elseif ( ! has_post_thumbnail( $gk ) ) {
		$p->log[] = 'NAPOMENA: Grad Koprivnica još nema logo — dodaj ga u KURS Liga → Sponzori (bez loga se ne prikazuje u traci).';
	}

	if ( $pan = $p->sponsor( 'Pan' ) ) { update_post_meta( $pan, '_kurs_sponsor_comp', 'obje' ); $p->log[] = 'Sponzor Pan: obje lige'; }
	if ( $pr = $p->sponsor( 'Praona Drnje' ) ) { update_post_meta( $pr, '_kurs_sponsor_comp', 'kc' ); $p->log[] = 'Sponzor Praona Drnje: samo KC liga'; }
	if ( $mk = $p->sponsor( 'Marks' ) ) { wp_trash_post( $mk ); $p->log[] = 'Sponzor Marks uklonjen s weba (u košu — može se vratiti)'; }

	// redoslijed: Grad Koprivnica prvi, zatim ostali kako su bili
	$ids = get_posts( [ 'post_type' => 'sponzor', 'post_status' => [ 'publish', 'draft' ], 'numberposts' => -1, 'fields' => 'ids', 'orderby' => [ 'menu_order' => 'ASC', 'ID' => 'ASC' ] ] );
	$ids = array_values( array_unique( array_merge( [ $gk ], array_map( 'intval', $ids ) ) ) );
	foreach ( $ids as $i => $id ) if ( (int) get_post_field( 'menu_order', $id ) !== $i ) wp_update_post( [ 'ID' => $id, 'menu_order' => $i ] );
};
