<?php
/**
 * Blok „KURS — iz lige” (kurs/liga, tema 1.0.47): ugradnja kola, rezultata, ljestvice,
 * rasporeda ili playoffa u tekst vijesti — bilo gdje u sadržaju.
 *
 * - Dinamički blok: sprema samo atribut `value` (isti zapis kao stari _kurs_embed:
 *   round|table|results:DIV:KOLO, live|schedule|bracket:DIV), HTML se gradi pri prikazu
 *   preko kurskc_embed_render() — kolo/ljestvica nakon kola ostaju na stanju tog kola.
 * - U uređivaču: odabir lige i prikaza u samom bloku + pregled u <iframe> s CSS-om teme
 *   (REST GET kurskc/v1/embed, samo za edit_posts). Pregled radi i u „Pretpregledu”
 *   jer je blok dio sadržaja (stari _kurs_embed se vidio tek nakon objave).
 * - Stari zapis _kurs_embed (ispod teksta) i dalje se prikazuje; metabox nudi „Pretvori u blok”.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	wp_register_script(
		'kurskc-embed-block',
		KURSKC_URI . '/assets/js/embed-block.js',
		[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch', 'wp-data' ],
		KURSKC_VERSION,
		true
	);
	wp_register_style( 'kurskc-embed-block-editor', false, [], KURSKC_VERSION );
	wp_add_inline_style( 'kurskc-embed-block-editor', kurskc_embed_block_editor_css() );
	register_block_type( 'kurs/liga', [
		'api_version'     => 2,
		'editor_script'   => 'kurskc-embed-block',
		'editor_style'    => 'kurskc-embed-block-editor',
		'attributes'      => [ 'value' => [ 'type' => 'string', 'default' => '' ] ],
		'supports'        => [ 'html' => false, 'align' => false ],
		'render_callback' => 'kurskc_embed_block_render',
	] );
} );

function kurskc_embed_block_editor_css(): string {
	return '.kurskc-eb{border:1px solid #dcdcd4;border-radius:8px;padding:12px;background:#fafaf7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px}'
		. '.kurskc-eb__head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}'
		. '.kurskc-eb__badge{font:700 11px/1 monospace;text-transform:uppercase;letter-spacing:.06em;background:#111317;color:#fff;padding:5px 8px;border-radius:4px}'
		. '.kurskc-eb__label{font-weight:600;flex:1;min-width:200px}'
		. '.kurskc-eb__picker{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;padding:12px;background:#fff;border:1px solid #e6e6de;border-radius:6px}'
		. '.kurskc-eb__picker .kurskc-eb__help{grid-column:1/-1;margin:0;color:#666;font-size:12px}'
		. '.kurskc-eb__loading,.kurskc-eb__empty,.kurskc-eb__hint{color:#666;font-size:13px;padding:14px;background:#fff;border-radius:6px;margin:0}'
		. '.kurskc-eb__frame{background:#fff;border-radius:6px}'
		. '@media(max-width:700px){.kurskc-eb__picker{grid-template-columns:1fr}}';
}

function kurskc_embed_block_render( array $attrs ): string {
	if ( ! function_exists( 'kurskc_embed_render' ) ) return '';
	$e    = kurskc_embed_parse( (string) ( $attrs['value'] ?? '' ) );
	$html = $e ? kurskc_embed_render( $e ) : '';
	return $html ? '<div class="post-embed post-embed--block">' . $html . '</div>' : '';
}

/** Podaci za uređivač (lige i mogućnosti) — samo kad se uređivač otvara. */
add_action( 'enqueue_block_editor_assets', function () {
	if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Rounds' ) ) return;
	$fonts = 'https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,400;0,500;0,600;0,700;1,500;1,600&family=Barlow+Semi+Condensed:ital,wght@0,500;0,600;0,700;0,800;1,700;1,800&display=swap';
	$opts = kurskc_embed_options();
	wp_add_inline_script( 'kurskc-embed-block', 'window.KURSKC_EMBED = ' . wp_json_encode( [
		'opts'  => $opts,
		'order' => array_map( 'strval', array_keys( $opts ) ), // najnovija sezona prva (JS objekt bi ih poredao po ID-u)
		'css'  => [ $fonts, KURSKC_URI . '/assets/css/main.css?ver=' . KURSKC_VERSION ],
	], JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
} );

/** Kategorija „KURS” u izborniku blokova (na vrhu). */
add_filter( 'block_categories_all', function ( $cats ) {
	return array_merge( [ [ 'slug' => 'kurs', 'title' => 'KURS', 'icon' => null ] ], $cats );
} );

/** Pregled za uređivač: GET /wp-json/kurskc/v1/embed?value=round:3:1 → { html } */
add_action( 'rest_api_init', function () {
	register_rest_route( 'kurskc/v1', '/embed', [
		'methods'             => 'GET',
		'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		'args'                => [ 'value' => [ 'type' => 'string', 'required' => true ] ],
		'callback'            => function ( WP_REST_Request $r ) {
			$e = kurskc_embed_parse( (string) $r['value'] );
			return [ 'html' => $e ? kurskc_embed_render( $e ) : '' ];
		},
	] );
} );
