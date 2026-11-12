<?php
/**
 * Sponzori / partneri.
 *
 * CPT `sponzor` (logo = istaknuta slika, razina 1–3, link). Na frontu se iznad
 * footera prikazuje beskonačna traka logotipa: sivo-bijeli, u boji na hover,
 * a hover nad cijelom trakom zaustavlja vrtnju. Razine 1–3 = veličina (zasad;
 * vizualni dizajn po razinama doradit ćemo kad stigne).
 */

defined( 'ABSPATH' ) || exit;

/* ── CPT `sponzor` ───────────────────────────────────────────────────────── */
add_action( 'init', function () {
	register_post_type( 'sponzor', [
		'labels' => [
			'name'          => 'Sponzori',
			'singular_name' => 'Sponzor',
			'add_new'       => 'Dodaj sponzora',
			'add_new_item'  => 'Dodaj sponzora',
			'edit_item'     => 'Uredi sponzora',
			'new_item'      => 'Novi sponzor',
			'search_items'  => 'Pretraži sponzore',
			'not_found'     => 'Nema sponzora.',
			'menu_name'     => 'Sponzori',
		],
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => false, // uređuje se u KURS Liga → Sponzori (plugin Kurs_Sponsors, 1.31.0)
		'menu_icon'     => 'dashicons-awards',
		'menu_position' => 27,
		'supports'      => [ 'title', 'thumbnail', 'page-attributes' ], // page-attributes → "Redoslijed"
		'has_archive'   => false,
		'rewrite'       => false,
		'show_in_rest'  => false,
	] );
}, 11 );

/* ── Meta-okvir: razina + link ───────────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'kurskc_sponsor_opts', 'Sponzor — postavke', 'kurskc_sponsor_box', 'sponzor', 'side', 'high' );
} );

function kurskc_sponsor_box( $post ): void {
	wp_nonce_field( 'kurskc_sponsor_meta', 'kurskc_sponsor_nonce' );
	$tier = (int) get_post_meta( $post->ID, '_kurs_sponsor_tier', true ) ?: 2;
	$link = (string) get_post_meta( $post->ID, '_kurs_sponsor_link', true );

	echo '<p><label for="kurs_sponsor_tier"><strong>Razina (1–3):</strong></label><br>';
	echo '<select name="kurs_sponsor_tier" id="kurs_sponsor_tier" style="width:100%;">';
	foreach ( [ 1 => '1 — glavni (najveći)', 2 => '2 — srednji', 3 => '3 — mali' ] as $v => $lbl ) {
		printf( '<option value="%d" %s>%s</option>', $v, selected( $tier, $v, false ), esc_html( $lbl ) );
	}
	echo '</select></p>';

	echo '<p><label for="kurs_sponsor_link"><strong>Link (web):</strong></label><br>';
	echo '<input type="url" name="kurs_sponsor_link" id="kurs_sponsor_link" value="' . esc_attr( $link ) . '" placeholder="https://sponzor.hr" style="width:100%;"></p>';
	echo '<p style="color:#666;font-size:12px;margin-bottom:0;">Logo postavi kao <strong>istaknutu sliku</strong> (u izvornoj boji — na webu se prikazuje sivo, a u boji na hover).</p>';
}

add_action( 'save_post_sponzor', function ( $post_id ) {
	if ( ! isset( $_POST['kurskc_sponsor_nonce'] ) || ! wp_verify_nonce( $_POST['kurskc_sponsor_nonce'], 'kurskc_sponsor_meta' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	update_post_meta( $post_id, '_kurs_sponsor_tier', max( 1, min( 3, (int) ( $_POST['kurs_sponsor_tier'] ?? 2 ) ) ) );
	update_post_meta( $post_id, '_kurs_sponsor_link', esc_url_raw( $_POST['kurs_sponsor_link'] ?? '' ) );
} );

/* Stupac "Razina" u listi sponzora */
add_filter( 'manage_sponzor_posts_columns', function ( $cols ) {
	$cols['kurs_tier'] = 'Razina';
	return $cols;
} );
add_action( 'manage_sponzor_posts_custom_column', function ( $col, $post_id ) {
	if ( $col === 'kurs_tier' ) echo (int) ( get_post_meta( $post_id, '_kurs_sponsor_tier', true ) ?: 2 );
}, 10, 2 );

/* ── Frontend: traka sponzora (iznad footera, dizajn „KURS Sponzori") ──────── */

/** Placeholderi dok korisnik ne doda prave sponzore. */
function kurskc_sponsor_placeholders(): array {
	return [
		[ 'tier' => 3, 'name' => 'Bilogora',      'sub' => 'pekarstvo',  'abbr' => 'BP', 'color' => '#C0641A' ],
		[ 'tier' => 2, 'name' => 'Mura Sport',    'sub' => '',           'abbr' => 'MS', 'color' => '#0E8C8C' ],
		[ 'tier' => 3, 'name' => 'Podravac',      'sub' => 'logistika',  'abbr' => 'PT', 'color' => '#1D4E89' ],
		[ 'tier' => 1, 'name' => 'Kopriva Grupa', 'sub' => '',           'abbr' => 'KG', 'color' => '#1F7A43' ],
		[ 'tier' => 3, 'name' => 'Centar Gume',   'sub' => 'vulkanizer', 'abbr' => 'CG', 'color' => '#6B2FB3' ],
		[ 'tier' => 2, 'name' => 'Drava Banka',   'sub' => '',           'abbr' => 'DB', 'color' => '#1D4E89' ],
		[ 'tier' => 3, 'name' => 'Media Nord',    'sub' => 'marketing',  'abbr' => 'MN', 'color' => '#DA0F1E' ],
		[ 'tier' => 3, 'name' => 'Šoder Co.',     'sub' => 'trgovina',   'abbr' => 'ŠC', 'color' => '#B07A1B' ],
	];
}

/** Render jednog sponzora (po razini). $it: tier, name, link, logo|abbr/sub/color. */
function kurskc_render_sponsor( array $it ): string {
	$tier  = (int) ( $it['tier'] ?? 3 );
	$tier  = max( 1, min( 3, $tier ) );
	$tag   = ! empty( $it['link'] ) ? 'a' : 'div';
	$href  = ! empty( $it['link'] ) ? ' href="' . esc_url( $it['link'] ) . '" target="_blank" rel="noopener nofollow sponsored"' : '';
	$title = ' title="' . esc_attr( $it['name'] ?? '' ) . '"';

	if ( ! empty( $it['logo'] ) ) {
		$content = '<span class="sp-logo"><img src="' . esc_url( $it['logo'] ) . '" alt="' . esc_attr( $it['name'] ?? '' ) . '" loading="lazy"></span>';
	} else {
		$abbr  = $it['abbr'] ?? mb_strtoupper( mb_substr( (string) ( $it['name'] ?? '' ), 0, 2 ) );
		$color = $it['color'] ?? '#1a3a6b';
		$content = '<span class="sp-txt"><span class="sp-txt__abbr" style="background:' . esc_attr( $color ) . ';">' . esc_html( $abbr ) . '</span>'
			. '<span class="sp-txt__col"><span class="sp-txt__name">' . esc_html( $it['name'] ?? '' ) . '</span>'
			. ( ! empty( $it['sub'] ) ? '<span class="sp-txt__sub">' . esc_html( $it['sub'] ) . '</span>' : '' ) . '</span></span>';
	}

	ob_start();
	if ( $tier === 1 ) : ?>
	<<?php echo $tag . $href . $title; ?> class="sp-item sp-t1">
		<span class="sp-t1__stripe" aria-hidden="true"></span>
		<span class="sp-t1__shine" aria-hidden="true"></span>
		<span class="sp-t1__tag"><span class="sp-star">★</span>Glavni pokrovitelj</span>
		<span class="sp-t1__body"><?php echo $content; ?></span>
	</<?php echo $tag; ?>>
	<?php elseif ( $tier === 2 ) : ?>
	<<?php echo $tag . $href . $title; ?> class="sp-item sp-t2">
		<span class="sp-t2__tag"><i></i>Srebrni partner</span>
		<?php echo $content; ?>
	</<?php echo $tag; ?>>
	<?php else : ?>
	<<?php echo $tag . $href . $title; ?> class="sp-item sp-t3"><?php echo $content; ?></<?php echo $tag; ?>>
	<?php endif;
	return ob_get_clean();
}

function kurskc_sponsors_strip(): string {
	$sponsors = get_posts( [
		'post_type'   => 'sponzor',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
	] );
	$sponsors = array_values( array_filter( $sponsors, fn( $s ) => has_post_thumbnail( $s ) ) );

	$items = [];
	if ( $sponsors ) {
		foreach ( $sponsors as $sp ) {
			$items[] = [
				'tier' => (int) get_post_meta( $sp->ID, '_kurs_sponsor_tier', true ) ?: 2,
				'name' => $sp->post_title,
				'link' => (string) get_post_meta( $sp->ID, '_kurs_sponsor_link', true ),
				'logo' => get_the_post_thumbnail_url( $sp->ID, 'medium' ),
			];
		}
	} else {
		$items = kurskc_sponsor_placeholders(); // dok nema pravih sponzora
	}
	if ( ! $items ) return '';

	$count = count( $items );
	// Traka je puna širina ekrana, a animacija skrola jednu „polovicu" trake (-50%).
	// Da se na desnom kraju ne pojavi prazan dio, svaka polovica mora biti šira od
	// najšireg ekrana → ponavljamo set dovoljno puta (cilj ~20 logotipa po polovici).
	$per_half = max( 1, (int) ceil( 20 / $count ) ); // koliko puta se set ponovi u jednoj polovici
	$copies   = 2 * $per_half;                        // paran broj kopija → bešavni -50% loop
	$dur      = max( 24, $per_half * $count * 5 );    // ista brzina bez obzira na broj kopija (~5s/logo)

	$inner = '';
	for ( $L = 0; $L < $copies; $L++ ) {
		foreach ( $items as $it ) $inner .= kurskc_render_sponsor( $it );
	}

	ob_start(); ?>
	<section class="sponsors" aria-label="Sponzori i partneri">
		<div class="sponsors__stripe" aria-hidden="true"></div>
		<div class="container sponsors__head">
			<div>
				<div class="sponsors__kicker">Hvala našim pokroviteljima</div>
				<div class="sponsors__title">Partneri turnira i liga</div>
			</div>
		</div>
		<div class="sponsors__viewport">
			<div class="sponsors__track" style="animation-duration:<?php echo (int) $dur; ?>s;"><?php echo $inner; ?></div>
		</div>
		<div class="container sponsors__cta">
			<span class="sponsors__cta-line"></span>
			<span class="sponsors__cta-txt">Želite postati pokrovitelj?</span>
			<a class="sponsors__cta-btn" href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>">Kontaktirajte nas →</a>
			<span class="sponsors__cta-line"></span>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
