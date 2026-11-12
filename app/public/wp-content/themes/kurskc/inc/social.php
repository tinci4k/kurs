<?php
/**
 * Društvene mreže + dijeljenje članaka + Open Graph (tema 1.0.53).
 *
 * - Poveznice na mreže: Izgled → Prilagodi → „KURS — društvene mreže" (theme_mod kurskc_social_*).
 *   Prazno = ikona se ne prikazuje. Facebook je zadano stranica KC lige.
 * - Prikaz: podnožje, bočni stupac vijesti („Pratite nas"), kraj članka, stranica lige.
 * - Dijeljenje: Facebook, WhatsApp, Viber, Telegram, X, e-mail, kopiraj link + „Podijeli" (Web Share API na mobitelu).
 * - Open Graph / Twitter kartica u <head> — naslov, sažetak i slika kad se link zalijepi na Facebook/WhatsApp.
 */
defined( 'ABSPATH' ) || exit;

const KURSKC_SOCIAL_DEFAULTS = [
	'facebook'     => 'https://www.facebook.com/profile.php?id=100054493718852',  // Facebook stranica KC lige
	'facebook_pan' => 'https://www.facebook.com/panljetnaligakoprivnica/',  // Facebook stranica Pan Ljetne lige (1.0.58)
	'instagram' => '',
	'youtube'   => '',
	'tiktok'    => '',
];

add_action( 'customize_register', function ( WP_Customize_Manager $c ) {
	$c->add_section( 'kurskc_social', [ 'title' => 'KURS — društvene mreže', 'priority' => 160,
		'description' => 'Poveznice na profile KURS-a / KC lige. Prazno polje = ikona se ne prikazuje.' ] );
	foreach ( [ 'facebook' => 'Facebook — KC liga', 'facebook_pan' => 'Facebook — Pan Ljetna liga', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok' ] as $k => $label ) {
		$c->add_setting( "kurskc_social_$k", [ 'default' => KURSKC_SOCIAL_DEFAULTS[ $k ], 'sanitize_callback' => 'esc_url_raw' ] );
		$c->add_control( "kurskc_social_$k", [ 'label' => $label, 'section' => 'kurskc_social', 'type' => 'url' ] );
	}
} );

/** SVG ikone (24×24, currentColor). */
function kurskc_icon( string $name, int $size = 18 ): string {
	$p = [
		'facebook'  => '<path fill="currentColor" d="M14 8.5V6.8c0-.8.5-1 .9-1h2.4V2.1L14 2c-3.6 0-4.4 2.7-4.4 4.4v2.1H7.5v3.9h2.1V22h4.4v-9.6h3l.4-3.9H14Z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.4" cy="6.6" r="1.2" fill="currentColor"/>',
		'youtube'   => '<path fill="currentColor" d="M22.5 7.2a2.8 2.8 0 0 0-2-2C18.8 4.8 12 4.8 12 4.8s-6.8 0-8.5.4a2.8 2.8 0 0 0-2 2A29 29 0 0 0 1 12a29 29 0 0 0 .5 4.8 2.8 2.8 0 0 0 2 2c1.7.4 8.5.4 8.5.4s6.8 0 8.5-.4a2.8 2.8 0 0 0 2-2A29 29 0 0 0 23 12a29 29 0 0 0-.5-4.8ZM9.8 15.1V8.9l5.6 3.1-5.6 3.1Z"/>',
		'tiktok'    => '<path fill="currentColor" d="M16.6 2h-3.3v13.2a2.9 2.9 0 1 1-2.1-2.8V9a6.2 6.2 0 1 0 5.4 6.2V8.6a7.8 7.8 0 0 0 4.4 1.4V6.7a4.4 4.4 0 0 1-4.4-4.7Z"/>',
		'whatsapp'  => '<path fill="currentColor" d="M12 2a9.9 9.9 0 0 0-8.5 15l-1.4 5 5.2-1.4A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3.1.8.8-3-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5 0 1.5-.6 1.7-1.2s.2-1.1.2-1.2-.2-.2-.4-.3Z"/>',
		'viber'     => '<path fill="currentColor" d="M11.4 2C9 2 4.6 2.4 3.2 5.6c-.8 1.9-.9 4.2-.9 6.6 0 3 .5 5 2.4 6.4v3.1c0 .3.4.5.6.3l2.6-2.5c1.6.2 3 .2 4.5.1 2.9-.2 6.4-1 7.6-4.4.7-2 .8-6.6 0-8.5C18.6 3 14.2 2 11.4 2Zm6.1 14.4c-.9 2.3-3.6 2.8-6 3-1.4.1-2.7 0-4-.1l-1.8 1.8v-2.4c-1.7-1-2.1-2.7-2.1-5.3 0-2.1.1-4.1.8-5.7C5.5 5.3 9 5 11.4 5c2.4 0 5.9.4 7 2.7.6 1.6.5 5.6-.1 7.2Zm-2.3-2.2c-.4-.3-.9-.6-1.3-.8-.4-.2-.8 0-1 .3l-.3.4c-.2.2-.4.2-.6.1a5.4 5.4 0 0 1-3-3c-.1-.2 0-.4.1-.6l.4-.3c.3-.2.5-.6.3-1-.2-.5-.5-.9-.8-1.3-.3-.4-.8-.4-1.2-.1-.9.6-1.2 1.4-.8 2.4a10.4 10.4 0 0 0 5.6 5.5c1 .4 1.9.1 2.5-.8.3-.4.3-.8-.1-1.1ZM12.3 6.6c2.4.1 3.8 1.6 3.9 4 0 .2-.2.4-.4.4s-.4-.2-.4-.4c-.1-2-1.1-3.1-3.1-3.2-.2 0-.4-.2-.4-.4s.2-.4.4-.4Zm.4 1.6c1.2.1 2 .9 2 2.1 0 .2-.2.4-.4.4s-.4-.2-.4-.4c0-.8-.5-1.2-1.3-1.3-.2 0-.4-.2-.3-.4 0-.2.2-.4.4-.4Z"/>',
		'telegram'  => '<path fill="currentColor" d="M21.9 4.2 18.8 19c-.2 1-.8 1.3-1.7.8l-4.6-3.4-2.2 2.1c-.3.3-.5.5-1 .5l.3-4.7 8.6-7.8c.4-.3-.1-.5-.6-.2l-10.6 6.7-4.6-1.4c-1-.3-1-1 .2-1.5l17.9-6.9c.8-.3 1.6.2 1.4 1.5Z"/>',
		'x'         => '<path fill="currentColor" d="M17.8 3h3.1l-6.8 7.8L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8L17.8 3Zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5Z"/>',
		'mail'      => '<rect x="2.5" y="5" width="19" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5" fill="none" stroke="currentColor" stroke-width="2"/>',
		'link'      => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'share'     => '<circle cx="18" cy="5" r="2.6" fill="currentColor"/><circle cx="6" cy="12" r="2.6" fill="currentColor"/><circle cx="18" cy="19" r="2.6" fill="currentColor"/><path d="m8.3 10.8 7.4-4.4M8.3 13.2l7.4 4.4" stroke="currentColor" stroke-width="2"/>',
	][ $name ] ?? '';
	return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Postavljene mreže: [ key => [ 'label', 'url' ] ]. */
function kurskc_social_links(): array {
	$labels = [ 'facebook' => 'KC liga', 'facebook_pan' => 'Pan Ljetna liga', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok' ];
	$out    = [];
	foreach ( $labels as $k => $label ) {
		$url = (string) get_theme_mod( "kurskc_social_$k", KURSKC_SOCIAL_DEFAULTS[ $k ] );
		if ( $url ) $out[ $k ] = [ 'label' => $label, 'url' => $url, 'icon' => str_starts_with( $k, 'facebook' ) ? 'facebook' : $k ];
	}
	return $out;
}

/** Ikone mreža (podnožje i sl.). */
function kurskc_social_icons( string $class = 'social-icons' ): string {
	$html = '';
	foreach ( kurskc_social_links() as $k => $s ) {
		$html .= '<a class="' . esc_attr( $class ) . '__btn ' . esc_attr( $class ) . '__btn--' . $s['icon'] . '" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener" title="' . esc_attr( $s['label'] ) . '">' . kurskc_icon( $s['icon'], 17 ) . '<span>' . esc_html( $s['label'] ) . '</span></a>';
	}
	return $html ? '<div class="' . esc_attr( $class ) . '">' . $html . '</div>' : '';
}

/** Kartica „Pratite nas" — veliki gumbi s nazivom mreže (bočni stupac, kraj članka, stranica lige). */
function kurskc_follow_box( string $variant = 'card' ): string {
	$links = kurskc_social_links();
	if ( ! $links ) return '';
	ob_start(); ?>
	<section class="follow-box follow-box--<?php echo esc_attr( $variant ); ?>">
		<div class="follow-box__txt">
			<b>Pratite nas</b>
			<span>Rezultati, fotografije i najave kola prvi su na Facebook stranicama KC lige i Pan Ljetne lige.</span>
		</div>
		<div class="follow-box__btns">
			<?php foreach ( $links as $k => $s ) : ?>
			<a class="follow-btn follow-btn--<?php echo esc_attr( $k ); ?>" href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener"><?php echo kurskc_icon( $s['icon'], 18 ); ?><span><?php echo esc_html( $s['label'] ); ?></span></a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php return ob_get_clean();
}

/**
 * Gumbi za dijeljenje članka. $variant: 'compact' (ikone u zaglavlju) | 'full' (traka ispod članka).
 */
function kurskc_share_html( int $post_id, string $variant = 'full' ): string {
	$url   = get_permalink( $post_id );
	$title = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	$u     = rawurlencode( $url );
	$t     = rawurlencode( $title );
	$nets  = [
		'facebook' => [ 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u ],
		'whatsapp' => [ 'WhatsApp', 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ) ],
		'viber'    => [ 'Viber', 'viber://forward?text=' . rawurlencode( $title . ' ' . $url ) ],
		'telegram' => [ 'Telegram', 'https://t.me/share/url?url=' . $u . '&text=' . $t ],
		'x'        => [ 'X', 'https://x.com/intent/post?url=' . $u . '&text=' . $t ],
		'mail'     => [ 'E-mail', 'mailto:?subject=' . $t . '&body=' . rawurlencode( $title . "\n\n" . $url ) ],
	];
	ob_start(); ?>
	<div class="share share--<?php echo esc_attr( $variant ); ?>">
		<?php if ( $variant === 'full' ) : ?><span class="share__lbl">Podijeli članak</span><?php endif; ?>
		<div class="share__btns">
			<button type="button" class="share__btn share__btn--native" data-share-native data-title="<?php echo esc_attr( $title ); ?>" data-url="<?php echo esc_url( $url ); ?>" hidden><?php echo kurskc_icon( 'share', 17 ); ?><span>Podijeli</span></button>
			<?php foreach ( $nets as $k => [ $label, $href ] ) : ?>
			<a class="share__btn share__btn--<?php echo $k; ?>" href="<?php echo esc_url( $href, [ 'http', 'https', 'mailto', 'viber' ] ); ?>"<?php echo $k === 'mail' || $k === 'viber' ? '' : ' target="_blank" rel="noopener"'; ?> aria-label="Podijeli: <?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>"><?php echo kurskc_icon( $k, 17 ); ?><?php if ( $variant === 'full' ) : ?><span><?php echo esc_html( $label ); ?></span><?php endif; ?></a>
			<?php endforeach; ?>
			<button type="button" class="share__btn share__btn--link" data-copy-link="<?php echo esc_url( $url ); ?>" aria-label="Kopiraj poveznicu" title="Kopiraj poveznicu"><?php echo kurskc_icon( 'link', 17 ); ?><?php if ( $variant === 'full' ) : ?><span>Kopiraj link</span><?php endif; ?></button>
		</div>
	</div>
	<?php return ob_get_clean();
}

/* ── Open Graph / Twitter kartica (kad link nije već obrađen SEO pluginom) ── */
add_action( 'wp_head', function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'SEOPress' ) ) return;
	$site  = get_bloginfo( 'name' );
	$img   = get_template_directory_uri() . '/assets/img/og-default.png';
	$type  = 'website';
	$title = wp_get_document_title();
	$desc  = get_bloginfo( 'description' );
	$url   = home_url( add_query_arg( [] ) );
	if ( is_singular() ) {
		$pid   = get_queried_object_id();
		$type  = is_singular( 'vijest' ) || is_singular( 'post' ) ? 'article' : 'website';
		$title = html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' );
		$desc  = has_excerpt( $pid ) ? get_the_excerpt( $pid ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $pid ) ) ), 30, '…' );
		$url   = get_permalink( $pid );
		if ( has_post_thumbnail( $pid ) ) $img = get_the_post_thumbnail_url( $pid, 'large' );
	}
	$meta = [
		'og:site_name' => $site, 'og:locale' => 'hr_HR', 'og:type' => $type, 'og:title' => $title,
		'og:description' => wp_strip_all_tags( (string) $desc ), 'og:url' => $url, 'og:image' => $img,
	];
	foreach ( $meta as $k => $v ) if ( $v !== '' ) echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	if ( $desc ) echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( (string) $desc ) ) . '">' . "\n";
}, 5 );
