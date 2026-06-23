<?php
/**
 * Template Name: Liga Hub
 *
 * Koristi se za stranice "KC Liga" i "Pan Ljetna".
 * URL params: ?season_id=X &division_id=Y &tab=tablica|raspored|statistika
 */
get_header();

if ( ! class_exists( 'Kurs_Season' ) ) {
	echo '<div class="container kurs-empty">KURS Liga plugin nije aktivan.</div>';
	get_footer();
	return;
}

$logo_uri = get_template_directory_uri() . '/assets/img/';

/* Odredi radi li se o KC Ligi ili Pan Ljetnoj na temelju sluga stranice */
$current_slug = get_post_field( 'post_name', get_the_ID() );
$is_pan       = ( $current_slug === 'pan-ljetna' );
$league_label = $is_pan ? 'Pan Ljetna' : 'KC Liga';
$league_sub   = $is_pan ? 'Ljetno natjecanje · Koprivnica' : 'Zimsko dvoransko natjecanje · Koprivnica';
$accent       = $is_pan ? 'var(--c-pan)' : 'var(--c-kc)';
$hub_class    = $is_pan ? 'league-hub--pan' : '';

/* Sezone OVE lige (KC ili Pan) — najnovije prvo; default = aktualna sezona lige.
   Time je izbornik "KC Liga" vezan na KC sezone, a "Pan Ljetna" na Pan sezone;
   nova sezona postaje aktualna čim je u backendu označiš kao "Aktivna". */
$all_seasons = Kurs_Season::for_league( $is_pan );
$valid_ids   = array_map( fn( $s ) => (int) $s->id, $all_seasons );
$season_id   = absint( $_GET['season_id'] ?? 0 );
if ( $season_id && ! in_array( $season_id, $valid_ids, true ) ) {
	$season_id = 0; // sezona pripada drugoj ligi — ignoriraj i vrati se na aktualnu
}
if ( ! $season_id && $all_seasons ) {
	$current   = Kurs_Season::current_for_league( $is_pan );
	$season_id = (int) ( $current->id ?? $all_seasons[0]->id );
}
$season = $season_id ? Kurs_Season::get( $season_id ) : null;

/* Divizije u sezoni */
$divisions   = $season_id ? Kurs_Season::get_divisions( $season_id ) : [];
$division_id = absint( $_GET['division_id'] ?? 0 );
if ( ! $division_id && $divisions ) {
	$division_id = (int) $divisions[0]->id;
}
$division = $division_id ? Kurs_Season::get_division( $division_id ) : null;

/* Prikaz: tablica | raspored | statistika */
$tab = in_array( $_GET['tab'] ?? '', [ 'tablica', 'raspored', 'statistika' ] ) ? $_GET['tab'] : 'tablica';

$base_url = get_permalink();

function kurs_hub_url( string $base, int $sid, int $did, string $tab ): string {
	return add_query_arg( [ 'season_id' => $sid, 'division_id' => $did, 'tab' => $tab ], $base );
}
?>

<div class="<?php echo esc_attr( $hub_class ); ?>">

<!-- Banner -->
<div class="league-banner <?php echo $is_pan ? 'league-banner--pan' : ''; ?>">
	<div class="league-banner__hatch"></div>
	<div class="league-banner__left">
		<div class="league-banner__icon">
			<?php echo kurs_league_icon( $is_pan ? 'Pan Ljetna' : 'KC Liga', 54 ); ?>
		</div>
		<div>
			<div class="league-banner__name"><?php echo esc_html( $league_label ); ?></div>
			<div class="league-banner__sub"><?php echo esc_html( $league_sub ); ?></div>
			<?php if ( $season && ! empty( $season->locked ) ) : ?>
			<span class="league-banner__final">&#128274; Završeno · konačni rezultati</span>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $all_seasons ) :
		$cur_season_name = $season->name ?? ( $all_seasons[0]->name ?? '' );
	?>
	<div class="hub-dd hub-dd--season" id="season-dd">
		<button type="button" class="hub-dd__toggle" aria-haspopup="listbox" aria-expanded="false">
			<?php echo kurs_league_icon( $is_pan ? 'Pan Ljetna' : 'KC Liga', 20 ); ?>
			<span class="hub-dd__current"><?php echo esc_html( $cur_season_name ); ?></span>
			<span class="hub-dd__caret" aria-hidden="true">▾</span>
		</button>
		<ul class="hub-dd__menu" role="listbox">
			<?php foreach ( $all_seasons as $s ) :
				$s_active = (int) $s->id === $season_id;
			?>
			<li role="option" aria-selected="<?php echo $s_active ? 'true' : 'false'; ?>">
				<a href="<?php echo esc_url( kurs_hub_url( $base_url, (int) $s->id, 0, $tab ) ); ?>" class="hub-dd__opt<?php echo $s_active ? ' is-active' : ''; ?>">
					<?php echo kurs_league_icon( $is_pan ? 'Pan Ljetna' : 'KC Liga', 18 ); ?>
					<?php echo esc_html( $s->name ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
</div>

<!-- Tabs: División toggle + view tabs -->
<div class="hub-tabs" id="liga">
	<?php if ( count( $divisions ) > 1 ) : ?>
	<div class="div-toggle">
		<?php foreach ( $divisions as $d ) : ?>
		<a href="<?php echo esc_url( kurs_hub_url( $base_url, $season_id, (int) $d->id, $tab ) ); ?>"
		   class="<?php echo ( (int) $d->id === $division_id ) ? 'is-active' : ''; ?>">
			<?php echo esc_html( $d->name ); ?>
		</a>
		<?php endforeach; ?>
	</div>
	<?php elseif ( $division ) : ?>
	<div class="div-toggle">
		<a class="is-active"><?php echo esc_html( $division->name ); ?></a>
	</div>
	<?php else : ?>
	<div></div>
	<?php endif; ?>

	<div class="view-tabs">
		<?php foreach ( [ 'tablica' => 'Tablica', 'raspored' => 'Raspored', 'statistika' => 'Statistika' ] as $slug => $label ) : ?>
		<a href="<?php echo esc_url( kurs_hub_url( $base_url, $season_id, $division_id, $slug ) ); ?>"
		   class="<?php echo $tab === $slug ? 'is-active' : ''; ?>">
			<?php echo esc_html( $label ); ?>
		</a>
		<?php endforeach; ?>
	</div>
</div>

<div class="container">
<div class="hub-body">
<div class="hub-main">

<?php if ( ! $division_id ) : ?>
<p class="kurs-empty">Odaberi sezonu i diviziju.</p>

<?php elseif ( $tab === 'tablica' ) :
	/* ── Tablica ──────────────────────────────────────────── */
	$phases = Kurs_Season::get_phases( $division_id );
	if ( empty( $phases ) ) : ?>
	<p class="kurs-empty">Nema skupina za ovu diviziju.</p>
	<?php else :
		foreach ( $phases as $ph ) :
			$groups = Kurs_Season::get_groups( (int) $ph->id );
			foreach ( $groups as $grp ) :
				$standings     = Kurs_Match::get_standings( (int) $grp->id );
				$grp_spots     = isset( $grp->playoff_spots ) ? (int) $grp->playoff_spots : 2;
				if ( ! $standings ) continue;
	?>
	<?php if ( count( $groups ) > 1 || count( $phases ) > 1 ) : ?>
	<h3 style="font-family:var(--font-cond);font-weight:800;font-style:italic;font-size:16px;text-transform:uppercase;margin-bottom:8px;color:var(--c-muted);">
		<?php echo esc_html( $ph->name . ( $grp->name ? ' · ' . $grp->name : '' ) ); ?>
	</h3>
	<?php endif; ?>
	<div class="standings">
		<div class="standings__head">
			<div>#</div><div>Klub</div>
			<div>O</div><div>P</div><div>N</div><div>I</div>
			<div>GR</div><div>Bod</div>
		</div>
		<?php foreach ( $standings as $i => $row ) :
			$pos       = $i + 1;
			$playoff   = $i < $grp_spots;
			$is_cutoff = $i === $grp_spots - 1;
		?>
		<a href="<?php echo esc_url( get_post_permalink( $row['team_id'] ) ); ?>"
		   class="standings__row <?php echo $playoff ? 'playoff' : ''; ?><?php echo $is_cutoff ? ' standings__row--cutoff' : ''; ?>"
		   style="text-decoration:none;color:inherit;">
			<div class="st-pos <?php echo $playoff ? 'is-playoff' : ''; ?>"><?php echo $pos; ?></div>
			<div class="st-club">
				<div class="team-badge">
					<?php echo kurs_team_badge_html( (int) $row['team_id'], 32 ); ?>
				</div>
				<span style="font-weight:600;"><?php echo esc_html( $row['team_name'] ); ?></span>
			</div>
			<div><?php echo (int) $row['P']; ?></div>
			<div><?php echo (int) $row['W']; ?></div>
			<div><?php echo (int) $row['D']; ?></div>
			<div><?php echo (int) $row['L']; ?></div>
			<div class="st-gd <?php echo $row['GD'] >= 0 ? 'pos' : 'neg'; ?>" title="Gol razlika: <?php echo ( $row['GD'] >= 0 ? '+' : '' ) . (int) $row['GD']; ?>">
				<?php echo (int) $row['GF'] . ':' . (int) $row['GA']; ?>
			</div>
			<div class="st-pts"><?php echo (int) $row['Pts']; ?></div>
		</a>
		<?php endforeach; ?>
		<?php if ( $grp_spots > 0 ) : ?>
		<div class="standings__legend">
			<span><i></i>Playoff (1–<?php echo (int) $grp_spots; ?>)</span>
		</div>
		<?php endif; ?>
	</div>
	<?php
			endforeach; // groups
		endforeach; // phases
	endif;

elseif ( $tab === 'raspored' ) :
	/* ── Raspored ─────────────────────────────────────────── */
	global $wpdb;
	$p = $wpdb->prefix . 'kurs_';
	$matches = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.id, m.round, m.match_date, m.status, m.home_score, m.away_score,
		        h.post_title AS home_name, a.post_title AS away_name
		 FROM   {$p}matches m
		 JOIN   {$p}groups  g  ON g.id  = m.group_id
		 JOIN   {$p}phases  ph ON ph.id = g.phase_id
		 JOIN   {$wpdb->posts} h ON h.ID = m.home_team_id
		 JOIN   {$wpdb->posts} a ON a.ID = m.away_team_id
		 WHERE  ph.division_id = %d
		 ORDER  BY (m.match_date IS NULL), m.match_date, m.round, m.id",
		$division_id
	) );
	if ( ! $matches ) : ?>
	<p class="kurs-empty">Nema utakmica za ovu diviziju.</p>
	<?php else :
		$cur_date = null;
	?>
	<div class="schedule">
	<?php foreach ( $matches as $m ) :
		$d = $m->match_date ? date( 'Y-m-d', strtotime( $m->match_date ) ) : 'tbd';
		if ( $d !== $cur_date ) :
			$cur_date = $d;
	?>
		<div class="sched-round-label"><?php echo esc_html( kurs_schedule_date_label( $m->match_date ) ); ?></div>
	<?php endif; ?>
		<a href="<?php echo esc_url( add_query_arg( 'match_id', $m->id, home_url( '/utakmica/' ) ) ); ?>"
		   class="sched-match">
			<div class="sched-match__home"><?php echo esc_html( $m->home_name ); ?></div>
			<?php if ( $m->status === 'finished' ) : ?>
			<div class="sched-match__score"><?php echo (int) $m->home_score; ?> : <?php echo (int) $m->away_score; ?></div>
			<?php else : ?>
			<div>
				<?php if ( $m->match_date ) : ?>
				<div class="sched-match__score--vs"><?php echo esc_html( date_i18n( 'H:i', strtotime( $m->match_date ) ) ); ?></div>
				<?php else : ?>
				<div class="sched-match__score--vs">vs</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<div class="sched-match__away"><?php echo esc_html( $m->away_name ); ?></div>
		</a>
	<?php endforeach; ?>
	</div>
	<?php endif;

elseif ( $tab === 'statistika' ) :
	/* ── Statistika strijelaca ─────────────────────────────── */
	$scorers = Kurs_Match::get_top_scorers( $division_id, 20 );
	if ( ! $scorers ) : ?>
	<p class="kurs-empty">Nema evidentiranih golova.</p>
	<?php else : ?>
	<div class="widget">
		<div class="widget__title">&#127942; Strijelci</div>
		<?php foreach ( $scorers as $i => $sc ) :
			$abbr = $sc->team_name ? strtoupper( mb_substr( $sc->team_name, 0, 2 ) ) : '?';
		?>
		<div class="scorer-row">
			<div class="scorer-left">
				<div class="scorer-badge"><?php echo esc_html( $abbr ); ?></div>
				<div>
					<div class="scorer-name"><?php echo esc_html( $sc->player_name ?: '—' ); ?></div>
					<div style="font-family:var(--font-mono);font-size:9px;color:var(--c-muted);"><?php echo esc_html( $sc->team_name ?: '' ); ?></div>
				</div>
			</div>
			<div class="scorer-goals"><?php echo (int) $sc->goals; ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif;
endif; ?>

</div><!-- /.hub-main -->

<!-- Side rail — uvijek vidljiv kada je odabrana divizija -->
<aside class="hub-side">
<?php if ( $division_id ) :
	/* ── Strijelci (uvijek vidljivo) ──────────────────────── */
	$scorers = Kurs_Match::get_top_scorers( $division_id, 10 );
?>
	<div class="widget">
		<div class="widget__title">&#127942; Zlatna kopačka</div>
		<?php if ( $scorers ) :
			foreach ( $scorers as $rank => $sc ) :
				$player_url = $sc->player_id ? get_permalink( (int) $sc->player_id ) : '#';
		?>
		<div class="scorer-row">
			<div class="scorer-left">
				<div style="font-family:var(--font-mono);font-size:10px;color:var(--c-muted);width:18px;text-align:center;flex-shrink:0;"><?php echo $rank + 1; ?>.</div>
				<div class="scorer-badge">
					<?php echo kurs_team_badge_html( (int) ( $sc->team_id ?? 0 ), 28 ); ?>
				</div>
				<div>
					<a href="<?php echo esc_url( $player_url ); ?>" class="scorer-name" style="display:block;">
						<?php echo esc_html( $sc->player_name ?: '—' ); ?>
					</a>
					<div style="font-family:var(--font-mono);font-size:9px;color:var(--c-muted);"><?php echo esc_html( $sc->team_name ?: '' ); ?></div>
				</div>
			</div>
			<div class="scorer-goals"><?php echo (int) $sc->goals; ?></div>
		</div>
		<?php endforeach;
		else : ?>
		<p style="font-family:var(--font-mono);font-size:10px;color:var(--c-muted);padding:8px 0;">Nema evidentiranih golova.</p>
		<?php endif; ?>
		<?php if ( $scorers && count( $scorers ) >= 10 ) : ?>
		<a href="<?php echo esc_url( add_query_arg( [ 'season_id' => $season_id, 'division_id' => $division_id, 'tab' => 'statistika' ], get_permalink() ) ); ?>"
		   style="display:block;margin-top:10px;font-family:var(--font-cond);font-weight:700;font-size:12px;text-transform:uppercase;color:var(--c-muted);text-align:center;">
			Svi strijelci &rarr;
		</a>
		<?php endif; ?>
	</div>

	<?php
	/* ── Disciplina ─────────────────────────────────────────── */
	global $wpdb;
	$p2   = $wpdb->prefix . 'kurs_';
	$disc = $wpdb->get_row( $wpdb->prepare(
		"SELECT
		   SUM(CASE WHEN e.event_type='yellow_card' THEN 1 ELSE 0 END) AS yellow,
		   SUM(CASE WHEN e.event_type IN ('red_card','second_yellow') THEN 1 ELSE 0 END) AS red
		 FROM   {$p2}match_events e
		 JOIN   {$p2}matches      m  ON m.id  = e.match_id
		 JOIN   {$p2}groups       g  ON g.id  = m.group_id
		 JOIN   {$p2}phases       ph ON ph.id = g.phase_id
		 WHERE  ph.division_id = %d",
		$division_id
	) );
	if ( $disc && ( (int) $disc->yellow > 0 || (int) $disc->red > 0 ) ) : ?>
	<div class="widget">
		<div class="widget__title red">&#9888; Disciplina</div>
		<div class="disc-boxes">
			<div class="disc-box disc-box--y">
				<div class="disc-box__num"><?php echo (int) $disc->yellow; ?></div>
				<div class="disc-box__lbl">Žuti</div>
			</div>
			<div class="disc-box disc-box--r">
				<div class="disc-box__num"><?php echo (int) $disc->red; ?></div>
				<div class="disc-box__lbl">Crveni</div>
			</div>
		</div>
	</div>
	<?php endif;
endif; ?>
</aside>

</div><!-- /.hub-body -->
</div><!-- /.container -->
</div>

<?php get_footer(); ?>
