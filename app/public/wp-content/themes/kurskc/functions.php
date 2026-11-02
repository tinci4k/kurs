<?php
defined( 'ABSPATH' ) || exit;

define( 'KURSKC_VERSION', '1.0.42' );
define( 'KURSKC_DIR', get_template_directory() );
define( 'KURSKC_URI', get_template_directory_uri() );

require_once KURSKC_DIR . '/inc/post-types.php';
require_once KURSKC_DIR . '/inc/enqueue.php';
require_once KURSKC_DIR . '/inc/nav-menus.php';
require_once KURSKC_DIR . '/inc/news.php';
require_once KURSKC_DIR . '/inc/sponsors.php';
require_once KURSKC_DIR . '/inc/media-kit.php';

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
		[
			'title'    => 'Za medije',
			'slug'     => 'za-medije',
			'template' => 'page-za-medije.php',
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
	if ( ! get_page_by_path( 'kc-liga' ) || ! get_page_by_path( 'pan-ljetna' ) || ! get_page_by_path( 'utakmica' ) || ! get_page_by_path( 'za-medije' ) ) {
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
<p>👤 <strong>Predsjednik udruge:</strong> Tomislav Zalar<br>📝 <strong>Naziv:</strong> KOPRIVNIČKA UDRUGA ZA REKREATIVNI SPORT (KURS)<br>🔢 <strong>OIB:</strong> 84116319927<br>📍 <strong>Adresa:</strong> Trg Zlate Bartl 4, 48000 Koprivnica<br>📧 <strong>Glavni mail:</strong> <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a></p>
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
 * Kreiraj "Kolačići" (politika kolačića) stranicu — editabilno u Gutenbergu.
 * Sadržaj odražava stvarno stanje: samo nužni WordPress kolačići, bez analitike/praćenja.
 */
add_action( 'init', function () {
	if ( get_page_by_path( 'kolacici' ) ) return;

	$content = '<!-- wp:paragraph -->
<p><em>Zadnje ažurirano: lipanj 2026.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ova Politika kolačića objašnjava što su kolačići, koje kolačiće koristi mrežna stranica <strong>kurskc.hr</strong> (Koprivnička udruga za rekreativni sport — KURS) te kako njima možete upravljati.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Što su kolačići?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Kolačići (engl. „cookies") male su tekstualne datoteke koje se pohranjuju na vaše računalo ili mobilni uređaj kada posjetite mrežnu stranicu. Omogućuju da stranica zapamti vaše radnje i postavke (primjerice prijavu) tijekom određenog razdoblja.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Koje kolačiće koristimo?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Naša stranica koristi <strong>isključivo nužne (funkcionalne) kolačiće</strong> koje postavlja sustav WordPress. <strong>Ne koristimo kolačiće za analitiku, oglašavanje niti praćenje</strong> na društvenim mrežama.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Posjetitelji koji samo pregledavaju javni dio stranice (vijesti, lige, tablice, rasporedi) u pravilu <strong>ne dobivaju nikakve kolačiće</strong>. Nužni kolačići postavljaju se tek kada se ovlašteni korisnik (urednik ili administrator) <strong>prijavi</strong> u administracijsko sučelje.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Nužni (funkcionalni) kolačići</h3>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>Kolačić</th><th>Svrha</th><th>Trajanje</th></tr></thead><tbody><tr><td>wordpress_[hash]</td><td>Provjera autentičnosti korisnika tijekom prijave</td><td>Sesija</td></tr><tr><td>wordpress_logged_in_[hash]</td><td>Pamti da je korisnik prijavljen i tko je</td><td>Sesija / do odjave</td></tr><tr><td>wp-settings-[id], wp-settings-time-[id]</td><td>Postavke izgleda administracijskog sučelja</td><td>do 1 godine</td></tr><tr><td>wordpress_test_cookie</td><td>Provjerava podržava li preglednik kolačiće</td><td>Sesija</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Vanjske usluge</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Google Fonts</strong> — za prikaz tipografije fontovi se učitavaju s Googleovih poslužitelja (fonts.googleapis.com, fonts.gstatic.com). Pritom se Googleu prenosi vaša IP adresa, ali se ne postavljaju kolačići. Više informacija: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Googleova politika privatnosti</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Poveznice na društvene mreže</strong> — stranica sadrži poveznice na naše Facebook profile. Klikom na njih odlazite na Facebook, gdje vrijede njihovi kolačići i pravila privatnosti.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Upravljanje kolačićima</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Kolačiće u svakom trenutku možete pregledati, blokirati ili obrisati putem postavki svog preglednika. Napominjemo da blokiranje nužnih kolačića može onemogućiti prijavu u administraciju stranice.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Google Chrome</a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><a href="https://support.mozilla.org/hr/kb/" target="_blank" rel="noopener">Mozilla Firefox</a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><a href="https://support.apple.com/hr-hr/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><a href="https://support.microsoft.com/hr-hr/microsoft-edge" target="_blank" rel="noopener">Microsoft Edge</a></li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Izmjene ove politike</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Politiku kolačića možemo povremeno ažurirati kako bismo je uskladili s promjenama na stranici ili propisima. Najnovija verzija uvijek je dostupna na ovoj stranici.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Kontakt</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Za pitanja o ovoj Politici kolačića javite se na <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a> ili putem <a href="/kontakt/">kontakt stranice</a>.</p>
<!-- /wp:paragraph -->';

	wp_insert_post( [
		'post_title'   => 'Kolačići',
		'post_name'    => 'kolacici',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
	] );
}, 27 );

/**
 * Kreiraj "Privatnost" (politika privatnosti) stranicu — editabilno u Gutenbergu.
 */
add_action( 'init', function () {
	if ( get_page_by_path( 'privatnost' ) ) return;

	$content = '<!-- wp:paragraph -->
<p><em>Zadnje ažurirano: lipanj 2026.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ova Politika privatnosti objašnjava kako Koprivnička udruga za rekreativni sport (KURS) prikuplja, koristi i štiti osobne podatke u vezi s mrežnom stranicom <strong>kurskc.hr</strong>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Voditelj obrade</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Koprivnička udruga za rekreativni sport (KURS)</strong><br>OIB: 84116319927<br>Adresa: Trg Zlate Bartl 4, 48000 Koprivnica<br>E-pošta: <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Koje podatke prikupljamo i zašto</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Sudionici natjecanja</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Radi organizacije i javnog prikaza natjecanja objavljujemo podatke o ekipama i igračima (ime i prezime, eventualnu fotografiju, broj dresa te natjecateljsku statistiku — golovi, kartoni i slično). Pravna osnova je legitimni interes udruge u vođenju i prikazu natjecanja, odnosno privola sudionika dana prijavom ekipe.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Urednici i administratori</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Za osobe koje uređuju sadržaj vodimo korisničke račune (ime i e-pošta) potrebne za pristup administraciji stranice.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Kontakt e-poštom</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ako nam se javite e-poštom, obrađujemo vašu adresu i sadržaj poruke isključivo radi odgovora na vaš upit.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Tehnički podaci</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Poslužitelj može bilježiti standardne tehničke zapise (IP adresa, vrsta preglednika, vrijeme pristupa) radi sigurnosti i ispravnog rada stranice. Više o kolačićima pročitajte u <a href="/kolacici/">Politici kolačića</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Dijeljenje podataka</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Podatke ne prodajemo niti ustupamo trećim stranama u marketinške svrhe. Podaci mogu biti dostupni pružatelju usluge poslužitelja (hosting) u mjeri nužnoj za rad stranice te Googleu pri učitavanju fontova (prijenos IP adrese). Javno objavljeni natjecateljski podaci dostupni su svim posjetiteljima stranice.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Razdoblje čuvanja</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Osobne podatke čuvamo onoliko dugo koliko je potrebno za navedene svrhe. Natjecateljska evidencija (rezultati i statistike) čuva se i arhivira kao trajna povijest natjecanja udruge.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Vaša prava</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>U skladu s Općom uredbom o zaštiti podataka (GDPR) imate pravo na pristup svojim podacima, ispravak, brisanje, ograničenje obrade te prigovor na obradu. Zahtjev možete poslati na <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a>. Također imate pravo podnijeti pritužbu Agenciji za zaštitu osobnih podataka (AZOP).</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Izmjene ove politike</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Politiku privatnosti možemo povremeno ažurirati. Najnovija verzija uvijek je dostupna na ovoj stranici.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Kontakt</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Za sva pitanja o zaštiti podataka javite se na <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a>.</p>
<!-- /wp:paragraph -->';

	wp_insert_post( [
		'post_title'   => 'Privatnost',
		'post_name'    => 'privatnost',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
	] );
}, 28 );

/**
 * Kreiraj "Uvjeti korištenja" stranicu — editabilno u Gutenbergu.
 * Slug mora biti "uvjeti-koristenja" (poveznica u footeru).
 */
add_action( 'init', function () {
	if ( get_page_by_path( 'uvjeti-koristenja' ) ) return;

	$content = '<!-- wp:paragraph -->
<p><em>Zadnje ažurirano: lipanj 2026.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Korištenjem mrežne stranice <strong>kurskc.hr</strong> prihvaćate ove Uvjete korištenja. Molimo da ih pažljivo pročitate.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Opće odredbe</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Stranicu kurskc.hr uređuje i njome upravlja Koprivnička udruga za rekreativni sport (KURS), OIB: 84116319927, sa sjedištem na adresi Trg Zlate Bartl 4, 48000 Koprivnica. Stranica je informativnog karaktera te služi za objavu vijesti, rasporeda, rezultata i tablica malonogometnih natjecanja u organizaciji udruge.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Točnost podataka</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Trudimo se da svi objavljeni podaci (rasporedi, rezultati, tablice, statistike) budu točni i ažurni, no ne jamčimo da su u svakom trenutku bez pogrešaka. Rasporedi i termini mogu se mijenjati. Udruga ne odgovara za eventualnu štetu nastalu oslanjanjem na objavljene podatke.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Intelektualno vlasništvo</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Sadržaj stranice (tekstovi, fotografije, logotipi, grafika i drugi materijali) vlasništvo je udruge KURS ili nositelja prava te je zaštićen propisima o autorskom pravu. Preuzimanje, umnožavanje ili objava sadržaja bez prethodnog dopuštenja nije dopušteno, osim dijeljenja poveznica na stranicu.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Ponašanje korisnika</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Korisnici se obvezuju stranicu koristiti u skladu sa zakonom i dobrim običajima te neće poduzimati radnje koje bi mogle ugroziti rad, sigurnost ili dostupnost stranice.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Poveznice na treće strane</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Stranica može sadržavati poveznice na vanjske stranice (primjerice društvene mreže ili sponzore). Udruga ne odgovara za sadržaj, pravila ni dostupnost tih stranica.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Zaštita podataka</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Obrada osobnih podataka uređena je <a href="/privatnost/">Politikom privatnosti</a>, a korištenje kolačića <a href="/kolacici/">Politikom kolačića</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Izmjene uvjeta</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Zadržavamo pravo izmjene ovih Uvjeta korištenja u bilo kojem trenutku. Izmjene stupaju na snagu objavom na ovoj stranici.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Mjerodavno pravo i kontakt</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Na ove Uvjete primjenjuje se pravo Republike Hrvatske. Za sva pitanja javite se na <a href="mailto:kurskc@gmail.com">kurskc@gmail.com</a>.</p>
<!-- /wp:paragraph -->';

	wp_insert_post( [
		'post_title'   => 'Uvjeti korištenja',
		'post_name'    => 'uvjeti-koristenja',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
	] );
}, 29 );

/**
 * Jednokratno: dodaj adresu sjedišta u Privatnost / Uvjete / Kontakt (postojeće
 * stranice). Surgički str_replace na poznatom sidru — ne dira ostatak sadržaja,
 * preskače ako adresa već postoji, i izvrši se jednom (opcija kurs_addr_v1).
 */
add_action( 'init', function () {
	if ( get_option( 'kurs_addr_v1' ) ) return;

	$addr = 'Trg Zlate Bartl 4, 48000 Koprivnica';
	$map  = [
		'privatnost' => [
			'OIB: 84116319927<br>E-pošta:',
			'OIB: 84116319927<br>Adresa: ' . $addr . '<br>E-pošta:',
		],
		'uvjeti-koristenja' => [
			'(KURS), OIB: 84116319927. Stranica',
			'(KURS), OIB: 84116319927, sa sjedištem na adresi ' . $addr . '. Stranica',
		],
		'kontakt' => [
			'<strong>OIB:</strong> 84116319927<br>📧',
			'<strong>OIB:</strong> 84116319927<br>📍 <strong>Adresa:</strong> ' . $addr . '<br>📧',
		],
	];

	$all_present = true;
	foreach ( $map as $slug => $pair ) {
		$page = get_page_by_path( $slug );
		if ( ! $page ) { $all_present = false; continue; }              // još ne postoji → pokušaj idući put
		if ( strpos( $page->post_content, $addr ) !== false ) continue;  // već ima adresu
		$new = str_replace( $pair[0], $pair[1], $page->post_content );
		if ( $new !== $page->post_content ) {
			wp_update_post( [ 'ID' => $page->ID, 'post_content' => $new ] );
		}
	}
	if ( $all_present ) update_option( 'kurs_addr_v1', 1 );
}, 30 );

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
