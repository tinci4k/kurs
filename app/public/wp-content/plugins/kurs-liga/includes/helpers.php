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

	$base = "width:{$size}px;height:{$size}px;border-radius:50%;flex-shrink:0;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;";

	if ( $thumb ) {
		return "<div style=\"{$base}{$extra_css}\"><img src=\"" . esc_url( $thumb ) . "\" alt=\"\" style=\"width:100%;height:100%;object-fit:cover;\"></div>";
	}

	$font  = max( 9, intval( $size * 0.35 ) );
	return "<div style=\"{$base}background:{$color};color:{$text};font-family:'Barlow Semi Condensed',sans-serif;font-weight:800;font-style:italic;font-size:{$font}px;{$extra_css}\">" . esc_html( $abbr ) . "</div>";
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

/* ── Baza sudaca (za autocomplete u zapisniku) ───────────────────────────── */

/** Spremljeni suci — sortirani niz imena (option `kurs_referees`). */
function kurs_get_referees(): array {
	$list = get_option( 'kurs_referees', [] );
	if ( ! is_array( $list ) ) $list = [];
	$list = array_values( array_unique( array_filter( array_map( 'trim', $list ) ) ) );
	natcasesort( $list );
	return array_values( $list );
}

/** Dodaj imena u listu sudaca (bez duplikata). */
function kurs_add_referees( array $names ): void {
	$list = get_option( 'kurs_referees', [] );
	if ( ! is_array( $list ) ) $list = [];
	$changed = false;
	foreach ( $names as $n ) {
		$n = trim( (string) $n );
		if ( $n === '' || in_array( $n, $list, true ) ) continue;
		$list[]  = $n;
		$changed = true;
	}
	if ( $changed ) update_option( 'kurs_referees', array_values( $list ) );
}

/**
 * Grb/ikona lige (inline SVG, dizajn „KURS Ikone"). Tip se odredi iz teksta
 * (liga + divizija): „bez briga" → Liga bez briga, „pan/ljet" → Pan Ljetna,
 * inače KC Liga. $size = visina u px.
 */
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
	// KC Liga (zadano) — grb/štit
	$h = $size; $w = (int) round( $size * 52 / 60 );
	return '<svg class="kurs-licon kurs-licon--kc" viewBox="0 0 52 60" width="' . $w . '" height="' . $h . '" style="' . $style . '" aria-hidden="true" focusable="false">'
		. '<defs><linearGradient id="kcg' . $uid . '" x1="0" y1="26" x2="0" y2="58" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#E11D1D"/><stop offset=".55" stop-color="#F4820F"/><stop offset="1" stop-color="#FFD60A"/></linearGradient>'
		. '<clipPath id="kcc' . $uid . '"><path d="M5 6 Q5 3 8 3 L44 3 Q47 3 47 6 L47 33 Q47 46 26 57 Q5 46 5 33 Z"/></clipPath></defs>'
		. '<g clip-path="url(#kcc' . $uid . ')"><rect width="52" height="60" fill="#0e0e11"/>'
		. '<path d="M0 31 L6 25 L12 32 L18 25 L24 32 L30 25 L36 32 L42 25 L48 31 L52 28 L52 60 L0 60 Z" fill="url(#kcg' . $uid . ')"/>'
		. '<g transform="translate(26 45)"><circle r="6.6" fill="#fff" stroke="#111" stroke-width="1.1"/><polygon points="0,-2.7 2.57,-0.83 1.59,2.18 -1.59,2.18 -2.57,-0.83" fill="#111"/><path d="M0 -2.7 L0 -6.6 M2.57 -0.83 L6.3 -2 M1.59 2.18 L3.9 5.2 M-1.59 2.18 L-3.9 5.2 M-2.57 -0.83 L-6.3 -2" stroke="#111" stroke-width=".9" fill="none"/></g></g>'
		. '<path d="M5 6 Q5 3 8 3 L44 3 Q47 3 47 6 L47 33 Q47 46 26 57 Q5 46 5 33 Z" fill="none" stroke="#E11D1D" stroke-width="3"/>'
		. '<text x="26" y="19" text-anchor="middle" font-family="\'Barlow Semi Condensed\',sans-serif" font-weight="900" font-style="italic" font-size="16" fill="#FFD60A">KC</text>'
		. '<text x="26" y="28.5" text-anchor="middle" font-family="\'Barlow Semi Condensed\',sans-serif" font-weight="900" font-style="italic" font-size="8.5" fill="#FFD60A" letter-spacing=".5">LIGA</text></svg>';
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

/* ── Dijeljeni frontend renderi (liga hub, ugradnja u objave, sidebar widgeti) ── */

/**
 * Ljestvica jedne grupe. Markup/klase identični page-liga.php (tema ima CSS).
 *
 * @param array $args [ playoff_spots, show_title, title, limit, link_teams, show_legend ]
 */
function kurs_render_standings( int $group_id, array $args = [] ): string {
	if ( ! class_exists( 'Kurs_Match' ) ) return '';
	$a = array_merge( [
		'playoff_spots' => null, 'show_title' => false, 'title' => '',
		'limit' => null, 'link_teams' => true, 'show_legend' => true,
	], $args );

	$standings = Kurs_Match::get_standings( $group_id );
	if ( ! $standings ) return '';

	global $wpdb;
	$p     = $wpdb->prefix . KURS_PREFIX;
	$ginfo = $wpdb->get_row( $wpdb->prepare(
		"SELECT g.name AS gname, g.playoff_spots AS spots, ph.name AS phname
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
			$href = $a['link_teams'] ? ' href="' . esc_url( get_post_permalink( $row['team_id'] ) ) . '"' : '';
		?>
		<<?php echo $tag . $href; ?> class="standings__row <?php echo $playoff ? 'playoff' : ''; ?><?php echo $is_cutoff ? ' standings__row--cutoff' : ''; ?>" style="text-decoration:none;color:inherit;">
			<div class="st-pos <?php echo $playoff ? 'is-playoff' : ''; ?>"><?php echo $pos; ?></div>
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
 * Playoff bracket divizije. Markup/klase = admin (.kurs-bracket*); CSS je portan
 * u temu (main.css). Bez admin akcija — meč linka na zapisnik ako je povezan.
 */
function kurs_render_playoff_bracket( int $division_id, array $args = [] ): string {
	if ( ! class_exists( 'Kurs_Playoff' ) ) return '';
	$bracket = Kurs_Playoff::get_bracket( $division_id );
	if ( ! $bracket ) return '';

	$labels = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => 'Ut. za 3. mjesto' ];
	$order  = [ 'qf', 'sf', 'final', '3rd' ];

	ob_start(); ?>
	<div class="kurs-bracket">
		<?php foreach ( $order as $round ) :
			if ( empty( $bracket[ $round ] ) ) continue;
			$matches = $bracket[ $round ]; ksort( $matches );
		?>
		<div class="kurs-bracket__round">
			<div class="kurs-bracket__round-label"><?php echo esc_html( $labels[ $round ] ?? ucfirst( $round ) ); ?></div>
			<div class="kurs-bracket__matches">
				<?php foreach ( $matches as $slot => $m ) :
					$finished  = $m->status === 'finished';
					$home_name = $m->home_name ?: $m->home_label ?: '?';
					$away_name = $m->away_name ?: $m->away_label ?: '?';
					$home_wins = $finished && (int) $m->home_score > (int) $m->away_score;
					$away_wins = $finished && (int) $m->away_score > (int) $m->home_score;
					$mid  = (int) ( $m->match_id ?? 0 );
					$url  = $mid ? add_query_arg( 'match_id', $mid, home_url( '/utakmica/' ) ) : '';
					$tag  = $url ? 'a' : 'div';
					$href = $url ? ' href="' . esc_url( $url ) . '"' : '';
				?>
				<<?php echo $tag . $href; ?> class="kurs-bracket__match <?php echo $finished ? 'is-finished' : ''; ?>" style="text-decoration:none;color:inherit;">
					<div class="kurs-bracket__team <?php echo $home_wins ? 'is-winner' : ''; ?>">
						<div class="kurs-bracket__team-badge"><?php echo $m->home_team_id ? kurs_team_badge_html( (int) $m->home_team_id, 22 ) : '<div class="kurs-bracket__tbd">?</div>'; ?></div>
						<span class="kurs-bracket__team-name"><?php echo esc_html( $home_name ); ?></span>
						<?php if ( $finished ) : ?><span class="kurs-bracket__score <?php echo $home_wins ? 'is-winner' : ''; ?>"><?php echo (int) $m->home_score; ?></span><?php endif; ?>
					</div>
					<div class="kurs-bracket__team <?php echo $away_wins ? 'is-winner' : ''; ?>">
						<div class="kurs-bracket__team-badge"><?php echo $m->away_team_id ? kurs_team_badge_html( (int) $m->away_team_id, 22 ) : '<div class="kurs-bracket__tbd">?</div>'; ?></div>
						<span class="kurs-bracket__team-name"><?php echo esc_html( $away_name ); ?></span>
						<?php if ( $finished ) : ?><span class="kurs-bracket__score <?php echo $away_wins ? 'is-winner' : ''; ?>"><?php echo (int) $m->away_score; ?></span><?php endif; ?>
					</div>
					<div class="kurs-bracket__meta">
						<?php if ( $m->match_date ) : ?><span><?php echo esc_html( date_i18n( 'j.n. H:i', strtotime( $m->match_date ) ) ); ?></span><?php else : ?><span class="kurs-muted">Datum TBD</span><?php endif; ?>
						<span class="kurs-bracket__meta-label"><?php echo esc_html( $labels[ $round ] ?? '' ); ?> #<?php echo (int) $slot; ?></span>
					</div>
				</<?php echo $tag; ?>>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
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
 * Raspored (fixtures) jedne divizije. Markup/klase = page-liga.php (.schedule*).
 * Grupirano po datumu (ne po kolu).
 *
 * @param array $args [ limit => int|null, status => 'finished'|'scheduled'|null ]
 */
function kurs_render_schedule( int $division_id, array $args = [] ): string {
	global $wpdb;
	$p = $wpdb->prefix . KURS_PREFIX;
	$a = array_merge( [ 'limit' => null, 'status' => null ], $args );

	$where  = 'ph.division_id = %d';
	$params = [ $division_id ];
	if ( in_array( $a['status'], [ 'finished', 'scheduled' ], true ) ) {
		$where   .= ' AND m.status = %s';
		$params[] = $a['status'];
	}
	$limit_sql = $a['limit'] ? ' LIMIT ' . (int) $a['limit'] : '';

	$matches = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.id, m.round, m.match_date, m.status, m.home_score, m.away_score,
		        h.post_title AS home_name, a.post_title AS away_name
		 FROM {$p}matches m
		 JOIN {$p}groups  g  ON g.id  = m.group_id
		 JOIN {$p}phases  ph ON ph.id = g.phase_id
		 JOIN {$wpdb->posts} h ON h.ID = m.home_team_id
		 JOIN {$wpdb->posts} a ON a.ID = m.away_team_id
		 WHERE {$where}
		 ORDER BY (m.match_date IS NULL), m.match_date, m.round, m.id{$limit_sql}",
		$params
	) );
	if ( ! $matches ) return '';

	ob_start(); ?>
	<div class="schedule">
		<?php $cur = null; foreach ( $matches as $m ) :
			$d = $m->match_date ? date( 'Y-m-d', strtotime( $m->match_date ) ) : 'tbd';
			if ( $d !== $cur ) : $cur = $d; ?>
		<div class="sched-round-label"><?php echo esc_html( kurs_schedule_date_label( $m->match_date ) ); ?></div>
		<?php endif; ?>
		<a href="<?php echo esc_url( add_query_arg( 'match_id', $m->id, home_url( '/utakmica/' ) ) ); ?>" class="sched-match">
			<div class="sched-match__home"><?php echo esc_html( $m->home_name ); ?></div>
			<?php if ( $m->status === 'finished' ) : ?>
			<div class="sched-match__score"><?php echo (int) $m->home_score; ?> : <?php echo (int) $m->away_score; ?></div>
			<?php else : ?>
			<div><div class="sched-match__score--vs"><?php echo $m->match_date ? esc_html( date_i18n( 'H:i', strtotime( $m->match_date ) ) ) : 'vs'; ?></div></div>
			<?php endif; ?>
			<div class="sched-match__away"><?php echo esc_html( $m->away_name ); ?></div>
		</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
