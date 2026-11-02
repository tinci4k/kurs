<?php
/**
 * Template Name: Liga Hub
 *
 * Koristi se za stranice "KC Liga" i "Pan Ljetna".
 * URL params: ?season_id=X &division_id=Y &tab=tablica|kola|raspored|statistika
 *             &kolo=N|playoff (samo za tab=kola; zadano = zadnje odigrano kolo)
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

/* Lige (divizije) u sezoni — division_id iz URL-a mora pripadati odabranoj sezoni */
$divisions   = $season_id ? Kurs_Season::get_divisions( $season_id ) : [];
$div_ids     = array_map( fn( $d ) => (int) $d->id, $divisions );
$division_id = absint( $_GET['division_id'] ?? 0 );
if ( ! in_array( $division_id, $div_ids, true ) ) {
	$division_id = $div_ids[0] ?? 0;
}
$division    = $division_id ? Kurs_Season::get_division( $division_id ) : null;
$has_playoff = ! empty( $division->has_playoff );
$has_bracket = $division_id && class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( $division_id );

/* Prikaz: tablica | kola | raspored | statistika */
$tabs    = [ 'tablica' => 'Tablica', 'kola' => 'Po kolima', 'raspored' => 'Raspored', 'statistika' => 'Statistika' ];
$req_tab = is_string( $_GET['tab'] ?? null ) ? sanitize_key( $_GET['tab'] ) : '';
$tab     = isset( $tabs[ $req_tab ] ) ? $req_tab : 'tablica';

/* Kolo (samo tab „Po kolima"): broj kola ili „playoff"; zadano = playoff ako je
   generiran, inače zadnje kolo s odigranom utakmicom */
$rounds = [];
$kolo   = 0;
if ( $tab === 'kola' && $division_id ) {
	$rounds   = Kurs_Rounds::round_status( $division_id );
	$req_kolo = is_string( $_GET['kolo'] ?? null ) ? sanitize_key( $_GET['kolo'] ) : '';
	if ( $req_kolo === 'playoff' && $has_bracket ) {
		$kolo = 'playoff';
	} elseif ( isset( $rounds[ absint( $req_kolo ) ] ) ) {
		$kolo = absint( $req_kolo );
	} else {
		$kolo       = Kurs_Rounds::default_round( $division_id );
		$groups_done = $rounds && ! array_filter( $rounds, fn( $st ) => $st['finished'] < $st['total'] );
		if ( $has_bracket && ( ! $kolo || ( $req_kolo === '' && ( $groups_done || Kurskc_Media_Kit::playoff_started( $division_id ) ) ) ) ) {
			$kolo = 'playoff';
		}
	}
}

$base_url = get_permalink();

function kurs_hub_url( string $base, int $sid, int $did, string $tab, array $extra = [] ): string {
	return add_query_arg( array_merge( [ 'season_id' => $sid, 'division_id' => $did, 'tab' => $tab ], $extra ), $base );
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
		<?php foreach ( $divisions as $d ) : /* u „Po kolima" zadrži isto kolo i u drugoj ligi */ ?>
		<a href="<?php echo esc_url( kurs_hub_url( $base_url, $season_id, (int) $d->id, $tab, $tab === 'kola' && $kolo ? [ 'kolo' => $kolo ] : [] ) ); ?>"
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

	<div class="hub-tabs__end">
		<div class="view-tabs">
			<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( kurs_hub_url( $base_url, $season_id, $division_id, $slug ) ); ?>"
			   class="<?php echo $tab === $slug ? 'is-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</div>
		<?php if ( $season_id ) : /* paket za druge portale — na svakom tabu; u „Po kolima" za prikazano kolo */ ?>
		<a class="hub-media" href="<?php echo esc_url( add_query_arg( array_filter( [ 'liga' => $is_pan ? 'pan' : 'kc', 'season_id' => $season_id, 'kolo' => $tab === 'kola' ? $kolo : 0 ] ), home_url( '/za-medije/' ) ) ); ?>"
		   title="Rezultati, ljestvica i strijelci kola za druge portale (HTML, tekst, slika)">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4.5-4.5M12 15l4.5-4.5M4 17v3h16v-3"/></svg>
			Za medije
		</a>
		<?php endif; ?>
	</div>
</div>

<div class="container">
<div class="hub-body">
<div class="hub-main">

<?php if ( ! $division_id ) : ?>
<p class="kurs-empty">Odaberi sezonu i diviziju.</p>

<?php elseif ( $tab === 'tablica' ) :
	/* ── Tablica ──────────────────────────────────────────── */
	$ph      = Kurs_Season::group_phase( $division_id );
	$groups  = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
	$printed = 0;
	foreach ( $groups as $grp ) {
		$html = kurs_render_standings( (int) $grp->id, [
			'title'         => count( $groups ) > 1 ? $grp->name : '',
			'playoff_spots' => $has_playoff ? null : 0, // KC lige nemaju playoff → bez oznake prolaza
		] );
		if ( $html ) { echo $html; $printed++; }
	}
	if ( ! $printed ) : ?>
	<p class="kurs-empty">Ljestvica će biti dostupna kad se ekipe rasporede u ligu.</p>
	<?php endif;
	if ( $has_bracket ) : ?>
	<h3 class="standings-title">Playoff</h3>
	<?php echo kurs_render_playoff_bracket( $division_id );
	endif;

elseif ( $tab === 'kola' ) :
	/* ── Po kolima: rezultati, ljestvica i strijelci nakon N. kola ── */
	if ( ! $rounds && ! $has_bracket ) : ?>
	<p class="kurs-empty">Raspored za ovu ligu još nije objavljen.</p>
	<?php else :
		$nums     = array_keys( $rounds );
		$idx      = $kolo === 'playoff' ? count( $nums ) : (int) array_search( $kolo, $nums, true );
		$prev     = $nums[ $idx - 1 ] ?? null;
		$next     = $kolo === 'playoff' ? null : ( $nums[ $idx + 1 ] ?? ( $has_bracket ? 'playoff' : null ) );
		$kolo_url = fn( $k ) => kurs_hub_url( $base_url, $season_id, $division_id, 'kola', [ 'kolo' => $k ] ) . '#kola';
	?>
	<nav class="round-nav" id="kola" aria-label="Kola">
		<?php if ( $prev !== null ) : ?>
		<a class="round-nav__arrow" href="<?php echo esc_url( $kolo_url( $prev ) ); ?>" aria-label="Prethodno kolo">&lsaquo;</a>
		<?php else : ?>
		<span class="round-nav__arrow is-disabled" aria-hidden="true">&lsaquo;</span>
		<?php endif; ?>
		<div class="round-nav__pills">
			<?php foreach ( $rounds as $r => $st ) :
				$state = $st['finished'] >= $st['total'] ? 'done' : ( $st['finished'] ? 'partial' : 'upcoming' );
				$hint  = [ 'done' => 'odigrano', 'partial' => 'odigrano ' . $st['finished'] . '/' . $st['total'], 'upcoming' => 'nije odigrano' ][ $state ];
			?>
			<a href="<?php echo esc_url( $kolo_url( $r ) ); ?>" class="round-pill round-pill--<?php echo $state; ?><?php echo $kolo === $r ? ' is-active' : ''; ?>"
			   title="<?php echo esc_attr( $r . '. kolo · ' . $hint ); ?>"<?php echo $kolo === $r ? ' aria-current="page"' : ''; ?>><?php echo (int) $r; ?>.</a>
			<?php endforeach; ?>
			<?php if ( $has_bracket ) : ?>
			<a href="<?php echo esc_url( $kolo_url( 'playoff' ) ); ?>" class="round-pill round-pill--playoff<?php echo $kolo === 'playoff' ? ' is-active' : ''; ?>"<?php echo $kolo === 'playoff' ? ' aria-current="page"' : ''; ?>>Playoff</a>
			<?php endif; ?>
		</div>
		<?php if ( $next !== null ) : ?>
		<a class="round-nav__arrow" href="<?php echo esc_url( $kolo_url( $next ) ); ?>" aria-label="Sljedeće kolo">&rsaquo;</a>
		<?php else : ?>
		<span class="round-nav__arrow is-disabled" aria-hidden="true">&rsaquo;</span>
		<?php endif; ?>
	</nav>

	<?php if ( $kolo === 'playoff' ) : ?>
	<div class="round-head">
		<div>
			<div class="round-head__kicker"><?php echo esc_html( $division->name ?? '' ); ?></div>
			<h2 class="round-head__title">Playoff</h2>
		</div>
	</div>
	<?php echo kurs_render_playoff_bracket( $division_id ); ?>

	<?php else :
		$st        = $rounds[ $kolo ] ?? [ 'total' => 0, 'finished' => 0 ];
		$r_date    = Kurs_Rounds::date_label( Kurs_Rounds::matches( $division_id, $kolo ) );
		$media_url = add_query_arg( [ 'liga' => $is_pan ? 'pan' : 'kc', 'season_id' => $season_id, 'kolo' => $kolo ], home_url( '/za-medije/' ) );
	?>
	<div class="round-head">
		<div>
			<div class="round-head__kicker"><?php echo esc_html( $division->name ?? '' ); ?></div>
			<h2 class="round-head__title"><?php echo (int) $kolo; ?>. kolo</h2>
			<?php if ( $r_date ) : ?><div class="round-head__date"><?php echo esc_html( $r_date ); ?></div><?php endif; ?>
		</div>
		<?php if ( $st['finished'] ) : ?>
		<a class="round-head__media" href="<?php echo esc_url( $media_url ); ?>">
			<span>Za medije</span> Preuzmi rezultate &rarr;
		</a>
		<?php endif; ?>
	</div>

	<section class="round-sec">
		<h3 class="round-sec__title"><?php echo $st['finished'] ? 'Rezultati' : 'Utakmice'; ?></h3>
		<?php echo kurs_render_round_results( $division_id, $kolo ); ?>
	</section>

	<?php if ( $st['finished'] ) :
		$tables = Kurs_Rounds::standings( $division_id, $kolo );
	?>
	<section class="round-sec">
		<h3 class="round-sec__title">Ljestvica nakon <?php echo (int) $kolo; ?>. kola</h3>
		<?php foreach ( $tables as $t ) :
			echo kurs_render_standings( (int) $t['group']->id, [
				'rows'          => $t['rows'],
				'show_move'     => true,
				'title'         => count( $tables ) > 1 ? $t['group']->name : '',
				'playoff_spots' => $has_playoff ? null : 0,
			] );
			if ( $t['unplayed'] ) : ?>
		<p class="round-note">Neodigranih utakmica zaključno s ovim kolom: <strong><?php echo (int) $t['unplayed']; ?></strong> — nisu uračunate u ljestvicu.</p>
		<?php endif;
		endforeach; ?>
	</section>

	<section class="round-sec">
		<h3 class="round-sec__title">Strijelci nakon <?php echo (int) $kolo; ?>. kola</h3>
		<?php echo kurs_render_round_scorers( $division_id, $kolo ) ?: '<p class="kurs-empty">Nema evidentiranih golova.</p>'; ?>
	</section>
	<?php else : ?>
	<p class="round-note">Ovo kolo još nije odigrano — ljestvica i strijelci pojavit će se nakon prvih rezultata.</p>
	<?php endif; ?>
	<?php endif; // kolo
	endif; // ima kola

elseif ( $tab === 'raspored' ) :
	/* ── Raspored: skupine + playoff, po danu (oznaka kola / playoff runde) ── */
	echo kurs_render_schedule( $division_id, [ 'round_labels' => true ] ) ?: '<p class="kurs-empty">Nema utakmica za ovu diviziju.</p>';

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
