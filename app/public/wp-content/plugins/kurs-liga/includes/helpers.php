<?php
defined( 'ABSPATH' ) || exit;

/**
 * Vraća hex boju ekipe (post meta _kurs_team_color).
 * Default: #1a3a6b (tamno plava)
 */
function kurs_team_color( int $team_id ): string {
	$color = get_post_meta( $team_id, '_kurs_team_color', true );
	return ( $color && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $color ) ) ? $color : '#1a3a6b';
}

/** Druga (sekundarna) boja dresa ili '' za jednobojni dres (1.32.0, postmeta _kurs_team_color2). */
function kurs_team_color2( int $team_id ): string {
	$c = (string) get_post_meta( $team_id, '_kurs_team_color2', true );
	return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '';
}

/** Mali dres u bojama ekipe (SVG): tijelo = glavna boja, rukavi i ovratnik = druga (ili glavna). */
function kurs_kit_svg( string $c1, string $c2 = '', int $size = 28 ): string {
	$c2 = $c2 ?: $c1;
	$h  = (int) round( $size * 110 / 120 );
	return '<svg class="kurs-kit" width="' . $size . '" height="' . $h . '" viewBox="0 0 120 110" aria-hidden="true">'
		. '<path d="M38 8 L24 14 L28 42 L28 104 L92 104 L92 42 L96 14 L82 8 Q60 22 38 8 Z" fill="' . esc_attr( $c1 ) . '"/>'
		. '<path d="M24 14 L4 30 L16 50 L28 42 Z M96 14 L116 30 L104 50 L92 42 Z M38 8 Q60 22 82 8 L78 5 Q60 17 42 5 Z" fill="' . esc_attr( $c2 ) . '"/>'
		. '<path d="M38 8 L24 14 L4 30 L16 50 L28 42 L28 104 L92 104 L92 42 L104 50 L116 30 L96 14 L82 8 Q60 22 38 8 Z" fill="none" stroke="#111317" stroke-opacity=".28" stroke-width="3" stroke-linejoin="round"/>'
		. '</svg>';
}

/**
 * Vraća boju teksta (bijelu ili crnu) koja je čitljiva na danoj pozadini.
 */
function kurs_team_text_color( string $hex ): string {
	$hex = ltrim( $hex, '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	// Luminance formula (WCAG)
	$lum = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
	return $lum > 0.55 ? '#111317' : '#ffffff';
}

/**
 * Generira HTML badge/avatar za ekipu.
 *
 * @param int    $team_id   ID posta ekipe
 * @param int    $size      Veličina u px
 * @param string $img_size  WP thumbnail size
 * @param string $extra_css Dodatni inline CSS na elementu
 */
function kurs_team_badge_html( int $team_id, int $size = 32, string $img_size = 'thumbnail', string $extra_css = '' ): string {
	$thumb = get_the_post_thumbnail_url( $team_id, $img_size );
	$name  = get_the_title( $team_id ) ?: '';
	$abbr  = mb_strtoupper( mb_substr( $name, 0, 2 ) );
	$color = kurs_team_color( $team_id );
	$text  = kurs_team_text_color( $color );

	$base = "width:{$size}px;height:{$size}px;border-radius:50%;flex-shrink:0;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;";

	if ( $thumb ) {
		// Logo (1.29.0): bijela zaobljena pločica, cijeli logo (contain) — bez odrezanih rubova i tamnog oboda
		$r   = max( 4, (int) round( $size * 0.2 ) );
		$pad = max( 1, (int) round( $size * 0.06 ) );
		return "<div class=\"kurs-tbadge kurs-tbadge--logo\" style=\"{$base}border-radius:{$r}px;background:#fff;padding:{$pad}px;box-shadow:inset 0 0 0 1px rgba(17,19,23,.1);{$extra_css}\"><img src=\"" . esc_url( $thumb ) . "\" alt=\"\" style=\"width:100%;height:100%;object-fit:contain;display:block;\"></div>";
	}

	$font  = max( 9, intval( $size * 0.35 ) );
	$ring  = $text !== '#fff' && $text !== '#ffffff' ? 'box-shadow:inset 0 0 0 1px rgba(17,19,23,.14);' : ''; // svijetla boja ekipe — tanki obrub da se vidi
	$c2    = kurs_team_color2( $team_id );
	if ( $c2 ) $color = "linear-gradient(135deg, {$color} 0 50%, {$c2} 50% 100%)"; // dvobojni dres (1.32.0)
	return "<div class=\"kurs-tbadge\" style=\"{$base}background:{$color};color:{$text};{$ring}font-family:'Barlow Semi Condensed',sans-serif;font-weight:800;font-style:italic;font-size:{$font}px;{$extra_css}\">" . esc_html( $abbr ) . "</div>";
}

/**
 * Tip igrača: 'goalkeeper' ili 'player' (default 'player').
 * Sprema se kao postmeta _kurs_player_type na CPT-u kurs_igrac.
 */
function kurs_player_type( int $player_id ): string {
	$t = get_post_meta( $player_id, '_kurs_player_type', true );
	return $t === 'goalkeeper' ? 'goalkeeper' : 'player';
}

/**
 * Hrvatska oznaka tipa igrača.
 *
 * @param string $type  'goalkeeper' | 'player'
 * @param bool   $short Kratka oznaka (GLM / IGR) umjesto pune (Golman / Igrač).
 */
function kurs_player_type_label( string $type, bool $short = false ): string {
	if ( $type === 'goalkeeper' ) return $short ? 'GLM' : 'Golman';
	return $short ? 'IGR' : 'Igrač';
}

/**
 * Trener ekipe — vraća [ 'name' => …, 'phone' => … ] ili null ako nije unesen.
 */
function kurs_team_coach( int $team_id ): ?array {
	$name  = trim( (string) get_post_meta( $team_id, '_kurs_team_coach', true ) );
	$phone = trim( (string) get_post_meta( $team_id, '_kurs_team_coach_phone', true ) );
	if ( $name === '' && $phone === '' ) return null;
	return [ 'name' => $name, 'phone' => $phone ];
}

/**
 * Predstavnik (delegat) ekipe — vraća [ 'name' => …, 'phone' => … ] ili null.
 */
function kurs_team_rep( int $team_id ): ?array {
	$name  = trim( (string) get_post_meta( $team_id, '_kurs_team_rep', true ) );
	$phone = trim( (string) get_post_meta( $team_id, '_kurs_team_rep_phone', true ) );
	if ( $name === '' && $phone === '' ) return null;
	return [ 'name' => $name, 'phone' => $phone ];
}

/**
 * Svi sponzorski baneri ekipe (ekipa može imati više sponzora). Vraća niz
 * [ ['id','url','link'], … ]. Pohrana: postmeta `_kurs_team_banners` = JSON niz
 * objekata { id, link }. Fallback na stari single baner (`_kurs_team_banner` +
 * `_kurs_team_banner_link`) ako novi format nije postavljen.
 */
function kurs_team_banners( int $team_id, string $size = 'large' ): array {
	$raw  = get_post_meta( $team_id, '_kurs_team_banners', true );
	$list = is_array( $raw ) ? $raw : ( is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : [] );
	if ( ! is_array( $list ) ) $list = [];

	if ( ! $list ) { // fallback: stari single baner
		$old = (int) get_post_meta( $team_id, '_kurs_team_banner', true );
		if ( $old ) $list = [ [ 'id' => $old, 'link' => (string) get_post_meta( $team_id, '_kurs_team_banner_link', true ) ] ];
	}

	$out = [];
	foreach ( $list as $b ) {
		$id = (int) ( $b['id'] ?? 0 );
		if ( ! $id ) continue;
		$url = wp_get_attachment_image_url( $id, $size );
		if ( ! $url ) continue;
		$link = trim( (string) ( $b['link'] ?? '' ) );
		$out[] = [ 'id' => $id, 'url' => $url, 'link' => $link ? esc_url( $link ) : '' ];
	}
	return $out;
}

/** Prvi (glavni) baner ekipe — kompatibilnost sa starim pozivima. */
function kurs_team_banner( int $team_id, string $size = 'large' ): ?array {
	$all = kurs_team_banners( $team_id, $size );
	return $all[0] ?? null;
}

/**
 * Grb/ikona lige (KC Liga = službeni grb kao <img>, Liga bez briga / Pan Ljetna = inline SVG). Tip se odredi iz teksta
 * (liga + divizija): „bez briga" → Liga bez briga, „pan/ljet" → Pan Ljetna,
 * inače KC Liga. $size = visina u px.
 */
/** <link> oznake favicona (grb KC lige) za samostalne stranice pluginu (/zapisnik/, /presjek/, PDF) — datoteke su u temi. */
function kurs_favicon_links(): string {
	$base = get_template_directory_uri() . '/assets/img/';
	if ( ! file_exists( get_template_directory() . '/assets/img/favicon-32.png' ) ) return '';
	return '<link rel="icon" href="' . esc_url( $base . 'favicon.ico' ) . '" sizes="any">' . "
"
		. '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $base . 'favicon-32.png' ) . '">' . "
"
		. '<link rel="apple-touch-icon" href="' . esc_url( $base . 'apple-touch-icon.png' ) . '">' . "
";
}

function kurs_league_icon( string $text, int $size = 24 ): string {
	static $uid = 0; $uid++;
	$t     = mb_strtolower( $text );
	$style = 'display:inline-block;vertical-align:middle;flex:none;';

	if ( str_contains( $t, 'briga' ) ) {
		return '<svg class="kurs-licon kurs-licon--lbb" viewBox="0 0 52 52" width="' . $size . '" height="' . $size . '" style="' . $style . '" aria-hidden="true" focusable="false">'
			. '<rect x="2" y="2" width="48" height="48" rx="12" fill="#B07A1B"/>'
			. '<text x="26" y="34" text-anchor="middle" font-family="\'Barlow Semi Condensed\',sans-serif" font-weight="800" font-style="italic" font-size="22" fill="#FFF6E2">BB</text>'
			. '<path d="M18 41 Q26 47 34 41" fill="none" stroke="#FFF6E2" stroke-width="3" stroke-linecap="round"/></svg>';
	}
	if ( str_contains( $t, 'pan' ) || str_contains( $t, 'ljet' ) ) {
		return '<svg class="kurs-licon kurs-licon--pan" viewBox="0 0 52 52" width="' . $size . '" height="' . $size . '" style="' . $style . '" aria-hidden="true" focusable="false">'
			. '<rect x="2" y="2" width="48" height="48" rx="12" fill="#143842"/>'
			. '<g fill="#21505c"><circle cx="9" cy="9" r="1.3"/><circle cx="13.2" cy="9" r="1.3"/><circle cx="9" cy="13.2" r="1.3"/><circle cx="13.2" cy="13.2" r="1.3"/><circle cx="9" cy="17.4" r="1.3"/></g>'
			. '<rect x="16.5" y="8" width="19" height="9" rx="2.5" fill="#fff"/>'
			. '<text x="26" y="14.7" text-anchor="middle" font-family="Barlow,sans-serif" font-weight="900" font-style="italic" font-size="7.5" fill="#143842" letter-spacing=".5">PAN</text>'
			. '<text x="26" y="38" text-anchor="middle" font-family="Barlow,sans-serif" font-weight="900" font-style="italic" font-size="20" fill="#fff">LJ</text>'
			. '<rect x="17.5" y="40.5" width="17" height="2.8" rx="1.4" fill="#FFD60A"/></svg>';
	}
	// KC Liga (zadano) — službeni grb KC lige (assets/kc-liga-logo.png, 201×240, prozirna pozadina; od 1.28.0)
	$h = $size; $w = (int) round( $size * 201 / 240 );
	return '<img class="kurs-licon kurs-licon--kc" src="' . esc_url( KURS_URL . 'assets/kc-liga-logo.png' ) . '" width="' . $w . '" height="' . $h . '"'
		. ' style="' . $style . 'width:' . $w . 'px;height:' . $h . 'px;object-fit:contain" alt="" aria-hidden="true" loading="lazy" decoding="async">';
}

/**
 * Fotografija momčadi (skupna slika ekipe ispred gola) — vraća URL ili null.
 * Sprema se kao attachment ID u postmeta _kurs_team_photo. Razlikuje se od loga
 * (grb/badge) i sponzorskog banera.
 */
function kurs_team_photo( int $team_id, string $size = 'large' ): ?string {
	$att_id = (int) get_post_meta( $team_id, '_kurs_team_photo', true );
	if ( ! $att_id ) return null;
	$url = wp_get_attachment_image_url( $att_id, $size );
	return $url ?: null;
}

/**
 * URL Liga Huba za ligu (diviziju): /kc-liga/ ili /pan-ljetna/ prema natjecanju
 * sezone, sa season_id + division_id + dodatnim parametrima (npr. tab, kolo).
 */
function kurs_league_url( int $division_id, array $args = [] ): string {
	global $wpdb;
	$p   = $wpdb->prefix . KURS_PREFIX;
	$row = $division_id ? $wpdb->get_row( $wpdb->prepare(
		"SELECT s.id AS season_id, s.league FROM {$p}divisions d JOIN {$p}seasons s ON s.id = d.season_id WHERE d.id = %d",
		$division_id
	) ) : null;
	if ( ! $row ) return home_url( '/kc-liga/' );
	$slug = class_exists( 'Kurs_Season' ) && Kurs_Season::is_pan_league( (string) $row->league ) ? 'pan-ljetna' : 'kc-liga';
	return add_query_arg( array_merge( [ 'season_id' => (int) $row->season_id, 'division_id' => $division_id ], $args ), home_url( '/' . $slug . '/' ) );
}

/* ── Dijeljeni frontend renderi (liga hub, ugradnja u objave, sidebar widgeti) ── */

/**
 * Ljestvica jedne grupe. Markup/klase identični page-liga.php (tema ima CSS).
 *
 * @param array $args [ playoff_spots, show_title, title, limit, link_teams, show_legend,
 *                      max_round (ljestvica nakon N. kola), rows (gotovi redovi, npr. iz
 *                      Kurs_Rounds::standings), show_move (strelica pomaka iz $row['move']) ]
 */
function kurs_render_standings( int $group_id, array $args = [] ): string {
	if ( ! class_exists( 'Kurs_Match' ) ) return '';
	$a = array_merge( [
		'playoff_spots' => null, 'show_title' => false, 'title' => '',
		'limit' => null, 'link_teams' => true, 'show_legend' => true,
		'max_round' => null, 'rows' => null, 'show_move' => false,
	], $args );

	$standings = $a['rows'] ?? Kurs_Match::get_standings( $group_id, $a['max_round'] ? (int) $a['max_round'] : null );
	if ( ! $standings ) return '';

	global $wpdb;
	$p     = $wpdb->prefix . KURS_PREFIX;
	$ginfo = $wpdb->get_row( $wpdb->prepare(
		"SELECT g.name AS gname, g.playoff_spots AS spots, ph.name AS phname, ph.division_id
		 FROM {$p}groups g JOIN {$p}phases ph ON ph.id = g.phase_id WHERE g.id = %d", $group_id ) );

	$spots = $a['playoff_spots'] !== null ? (int) $a['playoff_spots'] : ( $ginfo ? (int) $ginfo->spots : 2 );
	$rows  = $a['limit'] ? array_slice( $standings, 0, (int) $a['limit'] ) : $standings;
	$title = $a['title'];
	if ( $a['show_title'] && ! $title && $ginfo ) {
		$title = $ginfo->phname . ( $ginfo->gname ? ' · ' . $ginfo->gname : '' );
	}

	ob_start(); ?>
	<?php if ( $title ) : ?><h3 class="standings-title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
	<div class="standings">
		<div class="standings__head">
			<div>#</div><div>Klub</div><div>O</div><div>P</div><div>N</div><div>I</div><div>GR</div><div>Bod</div>
		</div>
		<?php foreach ( $rows as $i => $row ) :
			$pos = $i + 1; $playoff = $i < $spots; $is_cutoff = $i === $spots - 1;
			$tag  = $a['link_teams'] ? 'a' : 'div';
			$href = $a['link_teams'] ? ' href="' . esc_url( kurs_team_url( (int) $row['team_id'], (int) ( $ginfo->division_id ?? 0 ) ) ) . '"' : '';
		?>
		<<?php echo $tag . $href; ?> class="standings__row <?php echo $playoff ? 'playoff' : ''; ?><?php echo $is_cutoff ? ' standings__row--cutoff' : ''; ?>" style="text-decoration:none;color:inherit;">
			<div class="st-pos <?php echo $playoff ? 'is-playoff' : ''; ?>"><?php echo $pos; ?><?php
				if ( $a['show_move'] && isset( $row['move'] ) && $row['move'] !== null ) {
					$mv = (int) $row['move'];
					echo $mv > 0 ? '<span class="st-move st-move--up" title="Skok za ' . $mv . ' mj.">&#9650;' . $mv . '</span>'
						: ( $mv < 0 ? '<span class="st-move st-move--down" title="Pad za ' . abs( $mv ) . ' mj.">&#9660;' . abs( $mv ) . '</span>'
						: '<span class="st-move st-move--same" title="Bez promjene">&ndash;</span>' );
				}
			?></div>
			<div class="st-club">
				<div class="team-badge"><?php echo kurs_team_badge_html( (int) $row['team_id'], 32 ); ?></div>
				<span style="font-weight:600;"><?php echo esc_html( $row['team_name'] ); ?></span>
			</div>
			<div><?php echo (int) $row['P']; ?></div>
			<div><?php echo (int) $row['W']; ?></div>
			<div><?php echo (int) $row['D']; ?></div>
			<div><?php echo (int) $row['L']; ?></div>
			<div class="st-gd <?php echo $row['GD'] >= 0 ? 'pos' : 'neg'; ?>" title="Gol razlika: <?php echo ( $row['GD'] >= 0 ? '+' : '' ) . (int) $row['GD']; ?>"><?php echo (int) $row['GF'] . ':' . (int) $row['GA']; ?></div>
			<div class="st-pts"><?php echo (int) $row['Pts']; ?></div>
		</<?php echo $tag; ?>>
		<?php endforeach; ?>
		<?php if ( $a['show_legend'] && $spots > 0 ) : ?>
		<div class="standings__legend"><span><i></i>Playoff (1–<?php echo (int) $spots; ?>)</span></div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Čitljiv naziv još nepoznatog playoff sudionika: „QF1-W" → „Pobjednik ČF 1",
 * „SF2-W" → „Pobjednik PF 2", „A1" → „1. skupine A". Ostalo vraća kako jest.
 */
/** Link na profil ekipe u kontekstu lige (bez lige profil otvara najnoviju ligu ekipe). */
function kurs_team_url( int $team_id, int $division_id = 0 ): string {
	$url = (string) get_post_permalink( $team_id );
	return $division_id ? add_query_arg( 'division_id', $division_id, $url ) : $url;
}

/** Link na profil igrača ('' kad igrač ne postoji / nije objavljen). */
function kurs_player_url( int $player_id ): string {
	if ( ! $player_id || get_post_status( $player_id ) !== 'publish' ) return '';
	return (string) get_permalink( $player_id );
}

/**
 * Ime igrača / ekipe kao poveznica na profil (1.29.0) — svugdje gdje se ime prikazuje.
 * Bez ID-a ili profila vraća samo escapirani tekst. U retku koji je i sam poveznica
 * (utakmica) koristi se uzorak .has-row-link + .kc-row-link (vidi main.css).
 */
function kurs_player_link( int $player_id, string $name, string $class = 'kurs-plink' ): string {
	$url = kurs_player_url( $player_id );
	return $url ? '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name );
}
function kurs_team_link( int $team_id, string $name, int $division_id = 0, string $class = 'kurs-tlink', bool $wbr = false ): string {
	$label = esc_html( $name );
	if ( $wbr ) $label = str_replace( '/', '/<wbr>', $label );
	if ( ! $team_id || get_post_status( $team_id ) !== 'publish' ) return $label;
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( kurs_team_url( $team_id, $division_id ) ) . '">' . $label . '</a>';
}

/** Strijelci utakmice („Ranilović 3, Rac 2, Marić (ag)") s poveznicama na igrače. */
function kurs_scorers_html( array $list ): string {
	return implode( ', ', array_map(
		fn( $s ) => kurs_player_link( (int) ( $s['pid'] ?? 0 ), (string) $s['name'] ) . ( $s['goals'] > 1 ? ' ' . (int) $s['goals'] : '' ) . ( $s['own'] ? ' (ag)' : '' ),
		$list
	) );
}

/** Oznaka runde utakmice: „3. kolo" ili naziv playoff runde ($po_round iz playoff_matches.round). */
function kurs_round_label( $round, ?string $po_round = null ): string {
	$po = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => 'Ut. za 3. mjesto' ];
	return $po_round ? ( $po[ $po_round ] ?? 'Playoff' ) : (int) $round . '. kolo';
}

/**
 * Kratka oznaka kola za uske stupce (1.29.0): [ veliko, malo ] — „5." / „kolo", „1/4" / „finale",
 * „1/2" / „finale", pehar / „finale", „3." / „mjesto". Veliko je HTML (pehar je inline SVG).
 */
function kurs_round_short( $round, ?string $po_round = null ): array {
	if ( ! $po_round ) return [ esc_html( (int) $round . '.' ), 'kolo' ];
	$cup = '<svg class="kurs-cup" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 3h10v2h3v3a4 4 0 0 1-4 4h-.3A5 5 0 0 1 13 14.9V18h3v3H8v-3h3v-3.1A5 5 0 0 1 8.3 12H8a4 4 0 0 1-4-4V5h3V3Zm0 4H6v1a2 2 0 0 0 1 1.7V7Zm10 0v2.7A2 2 0 0 0 18 8V7h-1Z"/></svg>';
	return match ( $po_round ) {
		'qf'    => [ '1/4', 'finale' ],
		'sf'    => [ '1/2', 'finale' ],
		'final' => [ $cup, 'finale' ],
		'3rd'   => [ '3.', 'mjesto' ],
		default => [ 'PO', 'playoff' ],
	};
}

function kurs_playoff_label( ?string $label ): string {
	$label = trim( (string) $label );
	if ( preg_match( '/^(QF|SF)(\d)-W$/i', $label, $m ) ) {
		return 'Pobjednik ' . ( strtoupper( $m[1] ) === 'QF' ? 'ČF' : 'PF' ) . ' ' . $m[2];
	}
	if ( preg_match( '/^([A-H])(\d)$/i', $label, $m ) ) {
		return $m[2] . '. skupine ' . strtoupper( $m[1] );
	}
	return $label !== '' ? $label : '?';
}

/**
 * Playoff bracket divizije. Markup/klase = admin (.kurs-bracket*); CSS je portan
 * u temu (main.css). Bez admin akcija — meč linka na zapisnik ako je povezan.
 */
function kurs_render_playoff_bracket( int $division_id, array $args = [] ): string {
	if ( ! class_exists( 'Kurs_Playoff' ) ) return '';
	$bracket = Kurs_Playoff::get_bracket( $division_id );
	if ( ! $bracket ) return '';

	$labels = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => 'Ut. za 3. mjesto' ];

	/* Jedan meč bracketa (link na stranicu utakmice kad ima zapisnik) */
	$render = function ( object $m, string $round, int $slot ) use ( $labels ): string {
		ob_start();
		$finished  = $m->status === 'finished';
		$home_name = $m->home_name ?: kurs_playoff_label( $m->home_label );
		$away_name = $m->away_name ?: kurs_playoff_label( $m->away_label );
		$side      = Kurs_Playoff::winner_side( $m );
		$home_wins = $side === 'home';
		$away_wins = $side === 'away';
		$pen       = Kurs_Playoff::pen_label( $m );
		$mid  = (int) ( $m->match_id ?? 0 );
		$url  = $mid ? add_query_arg( 'match_id', $mid, home_url( '/utakmica/' ) ) : '';
	?>
	<div class="kurs-bracket__match has-row-link <?php echo $finished ? 'is-finished' : ''; ?>">
		<?php if ( $url ) : ?><a class="kc-row-link" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $home_name . ' – ' . $away_name ); ?>"></a><?php endif; ?>
		<div class="kurs-bracket__team <?php echo $home_wins ? 'is-winner' : ''; ?>">
			<div class="kurs-bracket__team-badge"><?php echo $m->home_team_id ? kurs_team_badge_html( (int) $m->home_team_id, 22 ) : '<div class="kurs-bracket__tbd">?</div>'; ?></div>
			<span class="kurs-bracket__team-name"><?php echo $m->home_name ? kurs_team_link( (int) $m->home_team_id, $home_name, (int) $m->division_id ) : esc_html( $home_name ); ?></span>
			<?php if ( $finished ) : ?><span class="kurs-bracket__score <?php echo $home_wins ? 'is-winner' : ''; ?>"><?php echo (int) $m->home_score; ?></span><?php endif; ?>
		</div>
		<div class="kurs-bracket__team <?php echo $away_wins ? 'is-winner' : ''; ?>">
			<div class="kurs-bracket__team-badge"><?php echo $m->away_team_id ? kurs_team_badge_html( (int) $m->away_team_id, 22 ) : '<div class="kurs-bracket__tbd">?</div>'; ?></div>
			<span class="kurs-bracket__team-name"><?php echo $m->away_name ? kurs_team_link( (int) $m->away_team_id, $away_name, (int) $m->division_id ) : esc_html( $away_name ); ?></span>
			<?php if ( $finished ) : ?><span class="kurs-bracket__score <?php echo $away_wins ? 'is-winner' : ''; ?>"><?php echo (int) $m->away_score; ?></span><?php endif; ?>
		</div>
		<div class="kurs-bracket__meta">
			<?php if ( $m->match_date ) : ?><span><?php echo esc_html( date_i18n( 'j.n. H:i', strtotime( $m->match_date ) ) ); ?></span><?php else : ?><span class="kurs-muted">Termin naknadno</span><?php endif; ?>
			<?php if ( $pen ) : ?><span class="kurs-bracket__pen"><?php echo esc_html( $pen ); ?></span><?php endif; ?>
			<span class="kurs-bracket__meta-label"><?php echo esc_html( $labels[ $round ] ?? '' ); ?> #<?php echo (int) $slot; ?></span>
		</div>
	</div>
		<?php
		return ob_get_clean();
	};

	ob_start(); ?>
	<div class="kurs-bracket">
		<?php foreach ( [ 'qf', 'sf', 'final' ] as $round ) :
			if ( empty( $bracket[ $round ] ) ) continue;
			$matches = $bracket[ $round ]; ksort( $matches );
		?>
		<div class="kurs-bracket__round">
			<div class="kurs-bracket__round-label"><?php echo esc_html( $labels[ $round ] ); ?></div>
			<div class="kurs-bracket__matches">
				<?php foreach ( $matches as $slot => $m ) echo $render( $m, $round, (int) $slot ); ?>
			</div>
			<?php if ( $round === 'final' && ! empty( $bracket['3rd'] ) ) : /* utakmica za 3. mjesto ispod finala, ne kao zaseban stupac */ ?>
			<div class="kurs-bracket__third">
				<div class="kurs-bracket__round-label"><?php echo esc_html( $labels['3rd'] ); ?></div>
				<?php foreach ( $bracket['3rd'] as $slot => $m ) echo $render( $m, '3rd', (int) $slot ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Oznaka lige za zajednički prikaz više liga (1.29.0): [ tone => kc|lbb|pan, short => KC|LBB|PAN, name ].
 */
function kurs_division_badge( int $division_id ): array {
	static $cache = [];
	if ( isset( $cache[ $division_id ] ) ) return $cache[ $division_id ];
	$div    = class_exists( 'Kurs_Season' ) ? Kurs_Season::get_division( $division_id ) : null;
	$season = $div ? Kurs_Season::get( (int) $div->season_id ) : null;
	$name   = $div->name ?? '';
	$tone   = ( $season && Kurs_Season::is_pan_league( (string) $season->league ) ) ? 'pan' : ( stripos( $name, 'briga' ) !== false ? 'lbb' : 'kc' );
	return $cache[ $division_id ] = [ 'tone' => $tone, 'short' => [ 'kc' => 'KC', 'lbb' => 'LBB', 'pan' => 'PAN' ][ $tone ], 'name' => $name ];
}

/** Mala oznaka lige uz utakmicu u zajedničkom prikazu. */
function kurs_division_chip( int $division_id, string $extra = '' ): string {
	$b = kurs_division_badge( $division_id );
	return '<span class="lg-chip lg-chip--' . esc_attr( $b['tone'] ) . ( $extra ? ' ' . esc_attr( $extra ) : '' ) . '" title="' . esc_attr( $b['name'] ) . '">' . esc_html( $b['short'] ) . '</span>';
}

/**
 * Rezultati jednog kola sa strijelcima ispod svake utakmice (pregled po kolima).
 * Za ligu s više skupina utakmice su grupirane po skupini. Tema ima CSS (.rr-*).
 * $division_ids može biti i niz liga (1.29.0) — utakmice obiju liga kronološki, s oznakom lige.
 */
function kurs_render_round_results( $division_ids, int $round ): string {
	if ( ! class_exists( 'Kurs_Rounds' ) ) return '';
	$dids    = array_values( array_filter( array_map( 'intval', (array) $division_ids ) ) );
	$multi_d = count( $dids ) > 1;
	$matches = $goals = $byes = [];
	foreach ( $dids as $did ) {
		$ms = Kurs_Rounds::matches( $did, $round );
		foreach ( $ms as $m ) $m->did = $did;
		$goals += Kurs_Rounds::goals_by_match( $did, $ms );
		$playing = [];
		foreach ( $ms as $m ) { $playing[ (int) $m->group_id ][ (int) $m->home_team_id ] = true; $playing[ (int) $m->group_id ][ (int) $m->away_team_id ] = true; }
		foreach ( $playing as $gid => $ids ) { // ista pravila kao Kurs_Rounds::byes(), ali s ID-em ekipe (za poveznicu)
			$teams = Kurs_Season::get_group_teams( $gid );
			if ( count( $teams ) % 2 === 0 ) continue;
			$idle = array_values( array_filter( $teams, fn( $t ) => ! isset( $ids[ (int) $t->team_id ] ) ) );
			if ( count( $idle ) === 1 ) $byes[] = [ 'did' => $did, 'gid' => (int) $gid, 'tid' => (int) $idle[0]->team_id, 'team' => $idle[0]->team_name ];
		}
		$matches = array_merge( $matches, $ms );
	}
	if ( ! $matches ) return '';
	if ( $multi_d ) { // jedna kronološka lista za cijelu večer
		usort( $matches, fn( $a, $b ) => [ $a->match_date === null, (string) $a->match_date, (int) $a->id ] <=> [ $b->match_date === null, (string) $b->match_date, (int) $b->id ] );
		$by_group = [ '' => $matches ];
	} else {
		$by_group = [];
		foreach ( $matches as $m ) $by_group[ $m->group_name ][] = $m;
	}
	$multi = ! $multi_d && count( $by_group ) > 1;

	ob_start(); ?>
	<div class="round-results<?php echo $multi_d ? ' round-results--multi' : ''; ?>">
		<?php foreach ( $by_group as $gname => $list ) : ?>
		<?php if ( $multi ) : $gdate = Kurs_Rounds::date_label( $list ); ?>
		<div class="round-results__group"><span><?php echo esc_html( $gname ); ?></span><?php if ( $gdate ) : ?><em><?php echo esc_html( $gdate ); ?></em><?php endif; ?></div>
		<?php endif; ?>
		<?php foreach ( $list as $m ) :
			$did  = (int) $m->did;
			$done = $m->status === 'finished';
			$gl   = $done ? ( $goals[ (int) $m->id ] ?? [] ) : [];
			$hs   = kurs_scorers_html( $gl['home'] ?? [] );
			$as   = kurs_scorers_html( $gl['away'] ?? [] );
			$ts   = $m->match_date ? strtotime( $m->match_date ) : 0;
			$note = ! $ts ? 'Termin nije određen'
				: ( $ts < current_time( 'timestamp' ) ? 'Nije odigrano (' . kurs_schedule_date_label( $m->match_date ) . ')' : kurs_schedule_date_label( $m->match_date ) );
		?>
		<div class="rr-match has-row-link<?php echo $done ? ' is-finished' : ''; ?>">
			<a class="kc-row-link" href="<?php echo esc_url( add_query_arg( 'match_id', (int) $m->id, home_url( '/utakmica/' ) ) ); ?>" aria-label="<?php echo esc_attr( $m->home_name . ' – ' . $m->away_name ); ?>"></a>
			<?php if ( $multi_d ) echo kurs_division_chip( $did, 'rr-match__lg' ); ?>
			<div class="rr-match__team rr-match__team--home"><span class="rr-match__name"><?php echo kurs_team_link( (int) $m->home_team_id, $m->home_name, $did ); ?></span><?php echo kurs_team_badge_html( (int) $m->home_team_id, 26 ); ?></div>
			<?php if ( $done ) : ?>
			<div class="rr-match__score"><?php echo (int) $m->home_score; ?><i>:</i><?php echo (int) $m->away_score; ?></div>
			<?php else : ?>
			<div class="rr-match__score rr-match__score--tbd"><?php echo $ts ? esc_html( date( 'H:i', $ts ) ) : 'vs'; ?></div>
			<?php endif; ?>
			<div class="rr-match__team rr-match__team--away"><?php echo kurs_team_badge_html( (int) $m->away_team_id, 26 ); ?><span class="rr-match__name"><?php echo kurs_team_link( (int) $m->away_team_id, $m->away_name, $did ); ?></span></div>
			<?php if ( $hs || $as ) : ?>
			<div class="rr-match__scorers"><div class="rr-match__sc rr-match__sc--home"><?php echo $hs; ?></div><div class="rr-match__sc rr-match__sc--away"><?php echo $as; ?></div></div>
			<?php elseif ( ! $done ) : ?>
			<div class="rr-match__note"><?php echo esc_html( $note ); ?></div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
		<?php foreach ( $byes as $by ) :
			if ( ! $multi_d && $by['gid'] !== (int) $list[0]->group_id ) continue; ?>
		<div class="rr-bye"><?php echo $multi_d ? kurs_division_chip( $by['did'] ) . ' ' : ''; ?>Slobodna u ovom kolu: <strong><?php echo kurs_team_link( $by['tid'], $by['team'], $by['did'] ); ?></strong></div>
		<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Strijelci lige nakon N. kola, grupirani po broju golova (kao u medijima) + autogolovi. */
function kurs_render_round_scorers( int $division_id, ?int $round = null ): string {
	if ( ! class_exists( 'Kurs_Rounds' ) ) return '';
	$sc = Kurs_Rounds::scorers( $division_id, $round );
	if ( ! $sc['groups'] && ! $sc['own'] ) return '';

	ob_start(); ?>
	<div class="round-scorers">
		<?php foreach ( $sc['groups'] as $goals => $list ) : ?>
		<div class="rs-row">
			<div class="rs-goals"><?php echo (int) $goals; ?></div>
			<div class="rs-names">
				<?php foreach ( $list as $i => $s ) : ?><span class="rs-name"><?php echo kurs_player_link( (int) ( $s['pid'] ?? 0 ), $s['name'] ); ?> <em><?php echo kurs_team_link( (int) ( $s['team_id'] ?? 0 ), $s['team'], $division_id ); ?></em></span><?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
		<?php if ( $sc['own'] ) : ?>
		<div class="rs-row rs-row--own">
			<div class="rs-goals" title="Autogol">AG</div>
			<div class="rs-names">
				<?php foreach ( $sc['own'] as $s ) : ?><span class="rs-name"><?php echo kurs_player_link( (int) ( $s['pid'] ?? 0 ), $s['name'] ) . ( $s['goals'] > 1 ? ' ' . (int) $s['goals'] : '' ); ?> <em><?php echo kurs_team_link( (int) ( $s['team_id'] ?? 0 ), $s['team'], $division_id ); ?></em></span><?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Hrvatska oznaka datuma za raspored (npr. „Subota, 12. srpnja 2026."). */
function kurs_schedule_date_label( ?string $datetime ): string {
	if ( empty( $datetime ) || $datetime === '0000-00-00 00:00:00' ) return 'Termin nije određen';
	$ts = strtotime( $datetime );
	if ( ! $ts ) return 'Termin nije određen';
	$dani = [ 'Nedjelja', 'Ponedjeljak', 'Utorak', 'Srijeda', 'Četvrtak', 'Petak', 'Subota' ];
	$mj   = [ '', 'siječnja', 'veljače', 'ožujka', 'travnja', 'svibnja', 'lipnja', 'srpnja', 'kolovoza', 'rujna', 'listopada', 'studenoga', 'prosinca' ];
	return $dani[ (int) date( 'w', $ts ) ] . ', ' . (int) date( 'j', $ts ) . '. ' . $mj[ (int) date( 'n', $ts ) ] . ' ' . date( 'Y', $ts ) . '.';
}

/**
 * Raspored (fixtures) divizije: skupine + playoff (Kurs_Rounds::schedule), grupirano po datumu.
 * Markup/klase .schedule* — isti na stranici lige (tab Raspored) i u ugradnji u vijesti.
 * $division_ids može biti i niz liga (1.29.0) — obje lige kronološki u istom danu, s oznakom lige.
 *
 * @param array $args [ limit => int|null, status => 'finished'|'scheduled'|null,
 *                      round_labels => bool (oznaka kola/playoff runde uz datum) ]
 */
function kurs_render_schedule( $division_ids, array $args = [] ): string {
	if ( ! class_exists( 'Kurs_Rounds' ) ) return '';
	$a       = array_merge( [ 'limit' => null, 'status' => null, 'round_labels' => false ], $args );
	$dids    = array_values( array_filter( array_map( 'intval', (array) $division_ids ) ) );
	$multi_d = count( $dids ) > 1;
	$matches = [];
	foreach ( $dids as $did ) {
		foreach ( Kurs_Rounds::schedule( $did ) as $m ) { $m->did = $did; $matches[] = $m; }
	}
	if ( $multi_d ) {
		$po = [ 'qf' => 1, 'sf' => 2, '3rd' => 3, 'final' => 4 ];
		usort( $matches, fn( $x, $y ) => [ $x->match_date === null, (string) $x->match_date, $po[ $x->po_round ] ?? 0, (int) $x->round, (int) $x->id ]
			<=> [ $y->match_date === null, (string) $y->match_date, $po[ $y->po_round ] ?? 0, (int) $y->round, (int) $y->id ] );
	}
	if ( in_array( $a['status'], [ 'finished', 'scheduled' ], true ) ) {
		$matches = array_values( array_filter( $matches, fn( $m ) => ( $m->status === 'finished' ) === ( $a['status'] === 'finished' ) ) );
	}
	if ( $a['limit'] ) $matches = array_slice( $matches, 0, (int) $a['limit'] );
	if ( ! $matches ) return '';

	// Oznaka dana: kola skupina („1., 4. i 5. kolo" kad se odgođena igra kasnije) i playoff runde („Polufinale · Finale")
	$day = fn( $m ) => $m->match_date ? substr( $m->match_date, 0, 10 ) : 'tbd';
	$day_label = [];
	if ( $a['round_labels'] ) {
		$rounds = []; $po = [];
		foreach ( $matches as $m ) {
			if ( $m->po_round ) $po[ $day( $m ) ][ $m->po_round ] = kurs_round_label( 0, $m->po_round );
			elseif ( $m->round ) $rounds[ $day( $m ) ][ (int) $m->round ] = true;
		}
		foreach ( array_unique( array_merge( array_keys( $rounds ), array_keys( $po ) ) ) as $d ) {
			$rs    = array_keys( $rounds[ $d ] ?? [] );
			sort( $rs );
			$last  = array_pop( $rs );
			$parts = $last ? [ ( $rs ? implode( '., ', $rs ) . '. i ' : '' ) . $last . '. kolo' ] : [];
			$day_label[ $d ] = implode( ' · ', array_merge( $parts, array_values( $po[ $d ] ?? [] ) ) );
		}
	}

	ob_start(); ?>
	<div class="schedule<?php echo $multi_d ? ' schedule--multi' : ''; ?>">
		<?php $cur = null; foreach ( $matches as $m ) :
			if ( $day( $m ) !== $cur ) : $cur = $day( $m ); ?>
		<div class="sched-round-label"><?php if ( ! empty( $day_label[ $cur ] ) ) : ?><span class="sched-round-label__no"><?php echo esc_html( $day_label[ $cur ] ); ?></span><?php endif; ?><?php echo esc_html( kurs_schedule_date_label( $m->match_date ) ); ?></div>
		<?php endif;
			$did  = (int) $m->did;
			$murl = $m->id ? add_query_arg( 'match_id', $m->id, home_url( '/utakmica/' ) ) : ''; // playoff par bez zapisnika nema stranicu utakmice
			$pen  = isset( $m->home_pen, $m->away_pen ) ? sprintf( 'pen. %d:%d', $m->home_pen, $m->away_pen ) : '';
		?>
		<div class="sched-match has-row-link<?php echo $m->po_round ? ' sched-match--po' : ''; ?>">
			<?php if ( $murl ) : ?><a class="kc-row-link" href="<?php echo esc_url( $murl ); ?>" aria-label="<?php echo esc_attr( $m->home_name . ' – ' . $m->away_name ); ?>"></a><?php endif; ?>
			<?php if ( $multi_d ) echo kurs_division_chip( $did, 'sched-match__lg' ); ?>
			<div class="sched-match__home"><?php echo kurs_team_link( (int) ( $m->home_team_id ?? 0 ), (string) $m->home_name, $did ); ?></div>
			<?php if ( $m->status === 'finished' ) : ?>
			<div class="sched-match__score"><?php echo (int) $m->home_score; ?> : <?php echo (int) $m->away_score; ?><?php if ( $pen ) : ?><span class="sched-match__pen"><?php echo esc_html( $pen ); ?></span><?php endif; ?></div>
			<?php else : ?>
			<div><div class="sched-match__score--vs"><?php echo $m->match_date ? esc_html( date_i18n( 'H:i', strtotime( $m->match_date ) ) ) : 'vs'; ?></div></div>
			<?php endif; ?>
			<div class="sched-match__away"><?php echo kurs_team_link( (int) ( $m->away_team_id ?? 0 ), (string) $m->away_name, $did ); ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

