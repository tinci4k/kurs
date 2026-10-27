<?php
/**
 * Profil igrača (dizajn F) — karijera po sezonama i divizijama.
 */
if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Match' ) ) {
	wp_redirect( home_url( '/' ) ); // nema arhive /igraci/
	exit;
}

get_header();

global $wpdb;
$p         = $wpdb->prefix . KURS_PREFIX;
$player_id = (int) get_the_ID();
$player    = get_post( $player_id );
$thumb     = get_the_post_thumbnail_url( $player_id, 'large' );

/* ── Karijera: sve sezone × lige × ekipe (roster ekipe koja je u toj ligi) ─────────────── */
$career = $wpdb->get_results( $wpdb->prepare(
	"SELECT s.id AS season_id, s.name AS season_name, s.year AS season_year,
	        d.id AS division_id, d.name AS division_name, s.league AS league,
	        sq.team_id, t.post_title AS team_name, sq.jersey_no
	 FROM   {$p}squads sq
	 JOIN   {$p}divisions d  ON d.id  = sq.division_id
	 JOIN   {$p}seasons   s  ON s.id  = d.season_id
	 JOIN   {$wpdb->posts} t ON t.ID  = sq.team_id
	 WHERE  sq.player_id = %d
	   AND  EXISTS ( SELECT 1 FROM {$p}group_teams gt JOIN {$p}groups g ON g.id = gt.group_id JOIN {$p}phases ph ON ph.id = g.phase_id
	                 WHERE gt.team_id = sq.team_id AND ph.division_id = sq.division_id )
	 ORDER  BY s.year DESC, s.id DESC, d.sort_order ASC, d.id ASC",
	$player_id
) );
/* Brojke strogo po ligi (skupine + playoff): nastupi = završene utakmice ekipe bez onih na kojima je igrač bio odsutan */
foreach ( $career as $cr ) {
	$cr->matches_played = $cr->goals = $cr->yellows = $cr->reds = 0;
	$mids = Kurs_Match::team_match_ids( (int) $cr->team_id, (int) $cr->division_id );
	if ( ! $mids ) continue;
	$ev = $wpdb->get_row( $wpdb->prepare(
		"SELECT SUM(event_type IN ('goal','penalty'))           AS goals,
		        SUM(event_type = 'yellow_card')                 AS yellows,
		        SUM(event_type IN ('red_card','second_yellow')) AS reds,
		        COUNT(DISTINCT CASE WHEN event_type = 'absent' THEN match_id END) AS absent
		 FROM   {$p}match_events
		 WHERE  player_id = %d AND team_id = %d AND match_id IN (" . implode( ',', $mids ) . ')',
		$player_id, (int) $cr->team_id
	) );
	$cr->matches_played = count( $mids ) - (int) $ev->absent;
	$cr->goals          = (int) $ev->goals;
	$cr->yellows        = (int) $ev->yellows;
	$cr->reds           = (int) $ev->reds;
}

$current     = $career ? $career[0] : null;
$cur_sid     = $current ? (int) $current->season_id : 0;
$season_lbl  = $current ? $current->season_name : '';
$season_gols = 0;
$season_utak = 0;
foreach ( $career as $cr ) {
	if ( (int) $cr->season_id === $cur_sid ) {
		$season_gols += (int) $cr->goals;
		$season_utak += (int) $cr->matches_played;
	}
}

$ptype     = kurs_player_type( $player_id );
$ptype_lbl = kurs_player_type_label( $ptype );
$jersey    = $current && $current->jersey_no ? (int) $current->jersey_no : 0;
$birthyear = (int) get_post_meta( $player_id, '_kurs_player_birthyear', true );
$age       = $birthyear ? ( (int) current_time( 'Y' ) - $birthyear ) : 0;

/* Koliko divizija po sezoni (za isticanje "isti igrač, više divizija") */
$season_counts = [];
foreach ( $career as $r ) {
	$season_counts[ $r->season_id ] = ( $season_counts[ $r->season_id ] ?? 0 ) + 1;
}
$multi_note = '';
foreach ( $career as $r ) {
	if ( ( $season_counts[ $r->season_id ] ?? 0 ) > 1 ) {
		$srows = array_filter( $career, fn( $x ) => $x->season_id === $r->season_id );
		$parts = array_map( fn( $x ) => $x->division_name . ' (' . $x->team_name . ')', $srows );
		$multi_note = sprintf(
			'Isti igrač, sezona %s — igra %s. Statistike se vode odvojeno po diviziji.',
			$r->season_name, implode( ' i ', $parts )
		);
		break;
	}
}

/* ── Helperi ─────────────────────────────────────────────── */
function igrac_crest( int $team_id, int $size, int $font ): string {
	$thumb = get_the_post_thumbnail_url( $team_id, 'thumbnail' );
	$base  = "width:{$size}px;height:{$size}px;font-size:{$font}px;";
	if ( $thumb ) {
		return '<span class="pf-crest" style="' . $base . '"><img src="' . esc_url( $thumb ) . '" alt="" style="width:100%;height:100%;object-fit:cover;"></span>';
	}
	$color = kurs_team_color( $team_id );
	$text  = kurs_team_text_color( $color );
	$abbr  = mb_strtoupper( mb_substr( get_the_title( $team_id ), 0, 2 ) );
	return '<span class="pf-crest" style="' . $base . 'background:' . esc_attr( $color ) . ';color:' . esc_attr( $text ) . ';">' . esc_html( $abbr ) . '</span>';
}
/** Chip za ligu/diviziju: [bg, color, border, label]. */
function igrac_div_chip( string $league, string $division ): array {
	$ll = mb_strtolower( $league );
	$dl = mb_strtolower( $division );
	if ( str_contains( $ll, 'pan' ) || str_contains( $ll, 'ljet' ) ) {
		return [ '#fdf0e3', '#b3590c', '#f0cba1', $division ?: 'Pan Ljetna' ];
	}
	if ( str_contains( $dl, 'briga' ) ) {
		return [ '#f7eedd', '#8a6314', '#e1cda0', '★ ' . $division ];
	}
	return [ '#fdecec', '#DA0F1E', '#f1c4c0', 'KC · ' . $division ];
}
?>

<div class="container">
<div class="pf">

	<!-- Breadcrumb -->
	<div class="pf-crumb"><?php if ( $current ) : ?><a href="<?php echo esc_url( kurs_team_url( (int) $current->team_id, (int) $current->division_id ) ); ?>"><?php echo esc_html( $current->team_name ); ?></a> / <?php endif; ?><?php echo esc_html( $player->post_title ); ?></div>

	<!-- Hero -->
	<div class="pf-hero">
		<div class="pf-hero__tex" aria-hidden="true"></div>
		<div class="pf-photo">
			<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $player->post_title ); ?>"><?php endif; ?>
		</div>
		<div class="pf-hero__info">
			<div class="pf-kicker"><?php echo esc_html( mb_strtoupper( $ptype_lbl ) ); ?><?php if ( $jersey ) echo ' · #' . $jersey; ?></div>
			<h1 class="pf-name"><?php echo esc_html( $player->post_title ); ?></h1>
			<?php if ( $current ) : ?>
			<div class="pf-team">
				<a href="<?php echo esc_url( kurs_team_url( (int) $current->team_id, (int) $current->division_id ) ); ?>">
					<?php echo igrac_crest( (int) $current->team_id, 26, 10 ); ?>
					<span class="pf-team__name"><?php echo esc_html( $current->team_name ); ?></span>
				</a>
				<?php if ( $age ) : ?><span class="pf-age">· <?php echo (int) $age; ?> god.</span><?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<div class="pf-hero__stats">
			<div class="pf-stat">
				<div class="pf-stat__num pf-stat__num--gold"><?php echo (int) $season_gols; ?></div>
				<div class="pf-stat__lbl">Golova<?php if ( $season_lbl ) echo ' ' . esc_html( $season_lbl ); ?></div>
			</div>
			<div class="pf-stat">
				<div class="pf-stat__num"><?php echo (int) $season_utak; ?></div>
				<div class="pf-stat__lbl">Utakmica</div>
			</div>
		</div>
	</div>

	<!-- Statistički karton -->
	<div class="pf-section">
		<div class="pf-sec-head">
			<div class="pf-sec-title">Statistički karton</div>
			<div class="pf-legend">
				<span><i style="background:#DA0F1E;"></i>Glavna</span>
				<span><i style="background:#B07A1B;"></i>Bez briga</span>
				<span><i style="background:#F47B16;"></i>Pan Ljetna</span>
			</div>
		</div>

		<?php if ( $career ) : ?>
		<div class="pf-table-wrap">
			<div class="pf-table">
				<div class="pf-row pf-row--head">
					<div>Sezona</div><div>Ekipa</div><div>Liga / divizija</div>
					<div class="pf-head-c">Utak</div><div class="pf-head-c">Gol</div><div class="pf-head-c">Žuti</div><div class="pf-head-c">Crv</div>
				</div>
				<?php
				$prev_sid = null;
				foreach ( $career as $row ) :
					$cont = ( $prev_sid === $row->season_id ); // ista sezona kao prethodni red
					$prev_sid = $row->season_id;
					list( $cbg, $cfg, $cbr, $clabel ) = igrac_div_chip( $row->league, $row->division_name );
				?>
				<div class="pf-row pf-row--data<?php echo $cont ? ' pf-row--cont' : ''; ?>">
					<div class="pf-season<?php echo $cont ? ' pf-season--mut' : ''; ?>"><?php echo esc_html( $row->season_name ); ?></div>
					<div class="pf-team-cell">
						<a href="<?php echo esc_url( kurs_team_url( (int) $row->team_id, (int) $row->division_id ) ); ?>">
							<?php echo igrac_crest( (int) $row->team_id, 22, 9 ); ?>
							<span><?php echo esc_html( $row->team_name ); ?></span>
						</a>
					</div>
					<div>
						<span class="pf-chip" style="background:<?php echo esc_attr( $cbg ); ?>;color:<?php echo esc_attr( $cfg ); ?>;border-color:<?php echo esc_attr( $cbr ); ?>;"><?php echo esc_html( $clabel ); ?></span>
					</div>
					<div class="pf-c--mut"><?php echo (int) $row->matches_played; ?></div>
					<div class="pf-gol"><?php echo (int) $row->goals; ?></div>
					<div class="pf-c--mut"><?php echo (int) $row->yellows; ?></div>
					<div class="pf-c--mut"><?php echo (int) $row->reds; ?></div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php if ( $multi_note ) : ?>
		<div class="pf-note"><?php echo esc_html( $multi_note ); ?></div>
		<?php endif; ?>
		<?php else : ?>
		<div class="kurs-empty">Nema evidencije nastupa za ovog igrača.</div>
		<?php endif; ?>
	</div>

</div>
</div>

<?php get_footer(); ?>
