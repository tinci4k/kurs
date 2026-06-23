<?php
defined( 'ABSPATH' ) || exit;

define( 'KURSKC_VERSION', '1.0.27' );
define( 'KURSKC_DIR', get_template_directory() );
define( 'KURSKC_URI', get_template_directory_uri() );

require_once KURSKC_DIR . '/inc/post-types.php';
require_once KURSKC_DIR . '/inc/enqueue.php';
require_once KURSKC_DIR . '/inc/nav-menus.php';
require_once KURSKC_DIR . '/inc/news.php';
require_once KURSKC_DIR . '/inc/sponsors.php';

/* Helpers iz plugina — badge, boja ekipe itd. */
if ( ! function_exists( 'kurs_team_badge_html' ) ) {
	$_kurs_helpers = WP_PLUGIN_DIR . '/kurs-liga/includes/helpers.php';
	if ( file_exists( $_kurs_helpers ) ) require_once $_kurs_helpers;
	unset( $_kurs_helpers );
}

function kurskc_setup() {
	load_theme_textdomain( 'kurskc', KURSKC_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'custom-logo' );
}
add_action( 'after_setup_theme', 'kurskc_setup' );

/**
 * Automatski stvori neophodne stranice ako još ne postoje.
 * Pokreće se jednom pri aktivaciji teme.
 */
function kurskc_create_pages(): void {
	$pages = [
		[
			'title'    => 'KC Liga',
			'slug'     => 'kc-liga',
			'template' => 'page-liga.php',
		],
		[
			'title'    => 'Pan Ljetna',
			'slug'     => 'pan-ljetna',
			'template' => 'page-liga.php',
		],
		[
			'title'    => 'Utakmica',
			'slug'     => 'utakmica',
			'template' => 'page-utakmica.php',
		],
	];

	foreach ( $pages as $page ) {
		$existing = get_page_by_path( $page['slug'] );
		if ( $existing ) {
			// Samo osiguraj da ima pravi template
			if ( get_post_meta( $existing->ID, '_wp_page_template', true ) !== $page['template'] ) {
				update_post_meta( $existing->ID, '_wp_page_template', $page['template'] );
			}
			continue;
		}

		$id = wp_insert_post( [
			'post_title'   => $page['title'],
			'post_name'    => $page['slug'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		] );

		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wp_page_template', $page['template'] );
		}
	}
}
add_action( 'after_switch_theme', 'kurskc_create_pages' );
// Pokretaj i pri svakom loadu ako stranica nedostaje (sigurnosna mreža)
add_action( 'init', function () {
	if ( ! get_page_by_path( 'kc-liga' ) || ! get_page_by_path( 'pan-ljetna' ) || ! get_page_by_path( 'utakmica' ) ) {
		kurskc_create_pages();
	}
}, 20 );

/**
 * Kreiraj "O nama" stranicu s predpopunjenim sadržajem.
 */
add_action( 'init', function () {
	if ( get_page_by_path( 'o-kurs-u' ) ) return;

	$content = '<!-- wp:heading -->
<h2 class="wp-block-heading">Tko smo mi?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Koprivnička udruga za rekreativni sport (KURS) je institucija i glavni pokretač amaterskog, rekreativnog i veteranskog malog nogometa u Koprivnici i cijeloj Podravini. Udrugu godinama uspješno i predano vodi predsjednik <strong>Tomislav Zalar</strong>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Naša misija je jednostavna: okupljati zajednicu, promicati zdrav život, sportski duh i strast prema nogometu. Kroz natjecanja u našoj organizaciji svake godine prođe stotine sportaša – od potpunih rekreativaca i lokalnih zaljubljenika u loptu do profesionalaca iz 1. HNL i inozemstva.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Naša glavna natjecanja</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Svojim djelovanjem pokrivamo cijelu godinu kroz dva prepoznatljiva sportska brenda, s posebnim naglaskom na našu zimsku tradiciju:</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">1. Zimski ponos: KC Liga &amp; Liga bez briga</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Kada zahladi, KURS seli u dvoranu i pokreće natjecanje koje ima kultni status među domaćom publikom te predstavlja temelj našeg djelovanja. Utakmice se igraju vikendima u Sportskoj dvorani Srednje škole Koprivnica, a ovaj ponosni projekt nedavno je proslavio svoju jubilarnu <strong>14. sezonu</strong>. Natjecanje je strogo podijeljeno u dvije divizije:</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>KC Liga (Regularni dio):</strong> Namijenjena ozbiljnim rekreativcima i ekipama koje se žestoko bore za prestižni naslov prvaka, uz užarenu atmosferu i sjajnu posjećenost na tribinama.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Liga bez briga (Veteranski dio):</strong> Istinska duša zimskog turnira. Ovdje rezultat ostaje u drugom planu, a fokus je na druženju starijih igrača, liječnika, profesora, obrtnika i odvjetnika (gdje i sama udruga KURS ponosno ima svoju ekipu).</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">2. Pan Ljetna liga Koprivnica</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ovo natjecanje predstavlja vrhunac ljetnog "hakla" i slovi za jedan od najjačih ljetnih malonogometnih turnira u regiji.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Lokacija:</strong> Otvoreni tereni s vrhunskom umjetnom travom na Gradskom stadionu "Ivan Kušek Apaš".</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Razina natjecanja:</strong> Turnir privlači elitne malonogometaše i profesionalne ekipe iz cijele sjeverozapadne Hrvatske jer je nastup dopušten svima.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Ulog i partneri:</strong> Uz snažnu podršku Grada Koprivnice i kompanije Carlsberg Croatia (glavni sponzor Pan pivo), osiguravamo ozbiljne nagradne fondove (prva nagrada redovito iznosi <strong>5.000 eura</strong>, dok ukupan fond dostiže i do <strong>9.000 eura</strong>).</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Više od lokalnih okvira: Pioniri mininogometa</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>KURS je odigrao povijesnu i veliku ulogu u popularizaciji mininogometa (inačice malog nogometa na umjetnoj travi s većim golovima) na razini cijele Hrvatske.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Povijesna zanimljivost:</strong> U suradnji s međunarodnim organizacijama, KURS je u Koprivnici organizirao prestižni međunarodni "Kup nacija" u mininogometu, ugostivši reprezentacije poput Španjolske i Kazahstana. Za potrebe tog turnira, privremeno smo postavili pravu umjetnu travu unutar dvorane, što je bio vrhunski organizacijski pothvat koji je poslužio kao službena uvertira za Europsko prvenstvo.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Bilo da ste igrač, sponzor ili vjerni navijač na tribinama – <strong>dobrodošli u veliku KURS obitelj!</strong></p>
<!-- /wp:paragraph -->';

	$id = wp_insert_post( [
		'post_title'   => 'O KURS-u',
		'post_name'    => 'o-kurs-u',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
	] );

	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', 'page-o-nama.php' );
	}
}, 25 );

/**
 * Kreiraj "Kontakt" stranicu s predpopunjenim sadržajem (editabilno u Gutenbergu).
 * Stvara se samo ako ne postoji — kasnije uređivanje korisnika se ne pregazi.
 */
add_action( 'init', function () {
	if ( get_page_by_path( 'kontakt' ) ) return;

	$img = 'http://kurshr.local/wp-content/uploads/2026/06/643990420_1427935099032934_3449115669718761723_n-1.jpg';

	$content = '<!-- wp:paragraph -->
<p>Za sva operativna pitanja, rasporede utakmica, tehničke detalje i organizaciju natjecanja na terenu, vaša prva adresa je naš voditelj operative. Za službene upite i suradnje obratite se vodstvu udruge.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="' . esc_url( $img ) . '" alt="KURS — Koprivnička udruga za rekreativni sport"/></figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading">📞 Primarni kontakt za lige i natjecanja</h2>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"24px","right":"26px","bottom":"24px","left":"26px"}},"border":{"radius":"10px"},"color":{"background":"#ffffff"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:10px;background-color:#ffffff;padding-top:24px;padding-right:26px;padding-bottom:24px;padding-left:26px">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">👤 Valentin Knapić — Voditelj natjecanja</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><em>(i samoprozvani predsjednik u sjeni)</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>📞 <strong>Mobitel:</strong> <a href="tel:+385996652351">099-665-2351</a><br>📧 <strong>E-mail:</strong> <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Mala klupska napomena:</em> Iako službeni statuti, papiri i pečati udruge jasno kažu tko sjedi u predsjedničkoj fotelji, Valentin čvrsto živi u uvjerenju da je on zapravo vrhovni šef, direktor i izbornik cijelog KURS-a. Ostatak vodstva ga u tom uvjerenju mudro i velikodušno ostavlja – barem dokle god rasporedi izlaze na vrijeme, a lige funkcioniraju bez greške! Ako trebate prijaviti ekipu ili riješiti bilo kakvu dilemu oko zapisnika, slobodno se javite našem „šefu".</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">🏛️ Vodstvo i službeni podaci udruge</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Za sve službene upite, dopise, sponzorstva i poslovne suradnje, ovdje su pravni podaci i službeno vodstvo naše udruge:</p>
<!-- /wp:paragraph -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"24px","right":"26px","bottom":"24px","left":"26px"}},"border":{"radius":"10px"},"color":{"background":"#ffffff"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:10px;background-color:#ffffff;padding-top:24px;padding-right:26px;padding-bottom:24px;padding-left:26px">
<!-- wp:paragraph -->
<p>👤 <strong>Predsjednik udruge:</strong> Tomislav Zalar<br>📝 <strong>Naziv:</strong> KOPRIVNIČKA UDRUGA ZA REKREATIVNI SPORT (KURS)<br>🔢 <strong>OIB:</strong> 84116319927<br>📧 <strong>Glavni mail:</strong> <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">📍 Gdje igramo?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ovisno o tome gori li dvoranski parket ili ljetna umjetna trava, naša natjecanja možete uživo pratiti na dvije dobro poznate lokacije u Koprivnici:</p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"spacing":{"padding":{"top":"22px","right":"24px","bottom":"22px","left":"24px"}},"border":{"radius":"10px"},"color":{"background":"#ffffff"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:10px;background-color:#ffffff;padding-top:22px;padding-right:24px;padding-bottom:22px;padding-left:24px">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">🏠 Zimski period</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>KC Liga &amp; Liga bez briga</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="https://maps.app.goo.gl/6j3qPHXP2tzxmGFJ7" target="_blank" rel="noopener">Sportska dvorana Srednje škole Koprivnica</a><br>📍 Trg slobode 7, 48000 Koprivnica</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"spacing":{"padding":{"top":"22px","right":"24px","bottom":"22px","left":"24px"}},"border":{"radius":"10px"},"color":{"background":"#ffffff"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:10px;background-color:#ffffff;padding-top:22px;padding-right:24px;padding-bottom:22px;padding-left:24px">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">☀️ Ljetni period</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Pan Ljetna liga Koprivnica</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Gradski stadion „Ivan Kušek Apaš" (tereni s umjetnom travom)<br>📍 Ulica Mihovila Pavleka Miškine 10, 48000 Koprivnica</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:heading -->
<h2 class="wp-block-heading">📢 Pratite nas na društvenim mrežama</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Dok se na našem novom webu kuha kompletna povijesna baza podataka, najnovije fotogalerije, rasprave i brze obavijesti s terena i dalje možete pratiti na našim službenim Facebook stranicama:</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"#F47B16","text":"#ffffff"},"border":{"radius":"6px"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background wp-element-button" style="border-radius:6px;color:#ffffff;background-color:#F47B16" href="https://www.facebook.com/" target="_blank" rel="noreferrer noopener">⚽ Pan Ljetna liga Koprivnica</a></div>
<!-- /wp:button -->

<!-- wp:button {"style":{"color":{"background":"#DA0F1E","text":"#ffffff"},"border":{"radius":"6px"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background wp-element-button" style="border-radius:6px;color:#ffffff;background-color:#DA0F1E" href="https://www.facebook.com/" target="_blank" rel="noreferrer noopener">🏆 Zimska KC Liga</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->';

	$id = wp_insert_post( [
		'post_title'   => 'Kontakt',
		'post_name'    => 'kontakt',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
	] );

	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', 'page-kontakt.php' );
	}
}, 26 );

/**
 * Relativno vrijeme na hrvatskom (npr. "prije 3 sata", "jučer", "22.6.2026.").
 */
function kurs_time_ago( string $date_str ): string {
	$time = strtotime( $date_str );
	$diff = current_time( 'timestamp' ) - $time;

	if ( $diff < 60 )       return 'upravo sad';
	if ( $diff < 3600 )     return 'prije ' . round( $diff / 60 ) . ' min';
	if ( $diff < 7200 )     return 'prije 1 sat';
	if ( $diff < 86400 )    return 'prije ' . round( $diff / 3600 ) . ' h';
	if ( $diff < 172800 )   return 'jučer';
	if ( $diff < 604800 )   return 'prije ' . round( $diff / 86400 ) . ' dana';
	if ( $diff < 1209600 )  return 'prije tjedan dana';
	if ( $diff < 2592000 )  return 'prije ' . round( $diff / 604800 ) . ' tjedna';

	return date_i18n( 'j.n.Y.', $time );
}

/**
 * Flush rewrite rules jednom ako je potrebno (npr. novi CPT)
 */
function kurskc_maybe_flush(): void {
	if ( get_option( 'kurskc_flush_needed' ) ) {
		flush_rewrite_rules();
		delete_option( 'kurskc_flush_needed' );
	}
}
add_action( 'init', 'kurskc_maybe_flush', 99 );

/**
 * Filter feeda vijesti po kategoriji preko query parametra ?kat=slug.
 * (Izbjegava se arhiva taksonomije jer slug kategorije kolidira sa slugom
 *  stranice lige — npr. „kc-liga" — pa filtriramo na CPT arhivi /vijesti/.)
 */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( ! $q->is_post_type_archive( 'vijest' ) ) return;
	$kat = isset( $_GET['kat'] ) ? sanitize_title( wp_unslash( $_GET['kat'] ) ) : '';
	if ( ! $kat ) return;
	$q->set( 'tax_query', [ [
		'taxonomy' => 'vijest_kategorija',
		'field'    => 'slug',
		'terms'    => $kat,
	] ] );
} );

add_action( 'after_switch_theme', function () {
	update_option( 'kurskc_flush_needed', 1 );
	flush_rewrite_rules();
} );

/**
 * Sakrij defaultni WP "Posts" iz admin izbornika — koristimo CPT vijest.
 */
add_action( 'admin_menu', function () {
	remove_menu_page( 'edit.php' );           // Objave (Posts)
	remove_menu_page( 'edit-comments.php' );  // Komentari
} );

// Preusmjeri ako netko direktno otvori /wp-admin/edit.php
add_action( 'load-edit.php', function () {
	if ( isset( $_GET['post_type'] ) && $_GET['post_type'] !== 'post' ) return;
	if ( ! isset( $_GET['post_type'] ) ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=vijest' ) );
		exit;
	}
} );
