<?php
/**
 * Profil ekipe (E) — single-kurs_ekipa.php
 */
if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Match' ) ) {
	wp_redirect( home_url( '/ekipe/' ) );
	exit;
}

get_header();

global $wpdb;
$p       = $wpdb->prefix . KURS_PREFIX;
$team_id = (int) get_the_ID();
$team    = get_post( $team_id );
$thumb_l = get_the_post_thumbnail_url( $team_id, 'large' );
$thumb_s = get_the_post_thumbnail_url( $team_id, 'thumbnail' );

/* ── Konteksti (sezone/divizije u kojima ekipa sudjeluje) ── */
$contexts = Kurs_Season::get_team_contexts( $team_id );

/* Odredi aktivnu diviziju */
$division_id = isset( $_GET['division_id'] ) ? (int) $_GET['division_id'] : 0;
if ( $division_id && ! in_array( $division_id, array_column( $contexts, 'division_id' ), false ) ) {
	$division_id = 0;
}
if ( ! $division_id && $contexts ) {
	$division_id = (int) $contexts[0]->division_id;
}

/* ── Aktivni kontekst ───────────────────────────────────── */
$active_ctx = null;
foreach ( $contexts as $c ) {
	if ( (int) $c->division_id === $division_id ) { $active_ctx = $c; break; }
}

/* ── Pozicija u tablici ──────────────────────────────────── */
$team_pos = null;
if ( $division_id ) {
	foreach ( Kurs_Season::get_phases( $division_id ) as $ph ) {
		foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $grp ) {
			foreach ( Kurs_Match::get_standings( (int) $grp->id ) as $i => $row ) {
				if ( (int) $row['team_id'] === $team_id ) {
					$team_pos = array_merge( $row, [
						'pos'   => $i + 1,
						'total' => count( Kurs_Match::get_standings( (int) $grp->id ) ),
					] );
					break 3;
				}
			}
		}
	}
}

/* ── Forma: zadnjih 5 utakmica ──────────────────────────── */
$form = [];
if ( $division_id ) {
	$form = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.id, m.home_team_id, m.away_team_id, m.home_score, m.away_score
		 FROM   {$p}matches m
		 JOIN   {$p}groups  g  ON g.id  = m.group_id
		 JOIN   {$p}phases  ph ON ph.id = g.phase_id
		 WHERE  ph.division_id = %d AND m.status = 'finished'
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)
		 ORDER  BY m.match_date DESC, m.id DESC LIMIT 5",
		$division_id, $team_id, $team_id
	) );
}

/* ── Sljedeća utakmica ──────────────────────────────────── */
$next = null;
if ( $division_id ) {
	$next = $wpdb->get_row( $wpdb->prepare(
		"SELECT m.id, m.match_date, m.round,
		        h.post_title AS home_name, a.post_title AS away_name,
		        m.home_team_id, m.away_team_id
		 FROM   {$p}matches m
		 JOIN   {$p}groups  g  ON g.id  = m.group_id
		 JOIN   {$p}phases  ph ON ph.id = g.phase_id
		 JOIN   {$wpdb->posts} h ON h.ID = m.home_team_id
		 JOIN   {$wpdb->posts} a ON a.ID = m.away_team_id
		 WHERE  ph.division_id = %d AND m.status = 'scheduled'
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)
		 ORDER  BY m.match_date ASC, m.id ASC LIMIT 1",
		$division_id, $team_id, $team_id
	) );
}

/* ── Roster ─────────────────────────────────────────────── */
$squad = $division_id ? Kurs_Season::get_squad( $team_id, $division_id ) : [];

/* ── Golovi po igraču u toj diviziji ───────────────────── */
$scorer_map = [];
if ( $division_id ) {
	$sc_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.player_id, COUNT(*) AS goals
		 FROM   {$p}match_events e
		 JOIN   {$p}matches      m  ON m.id  = e.match_id
		 JOIN   {$p}groups       g  ON g.id  = m.group_id
		 JOIN   {$p}phases       ph ON ph.id = g.phase_id
		 WHERE  ph.division_id = %d AND e.team_id = %d
		   AND  e.event_type IN ('goal','penalty') AND m.status = 'finished'
		 GROUP  BY e.player_id",
		$division_id, $team_id
	) );
	foreach ( $sc_rows as $s ) $scorer_map[ (int) $s->player_id ] = (int) $s->goals;
}

/* ── Sve utakmice ekipe u toj diviziji ─────────────────── */
$all_matches = [];
if ( $division_id ) {
	$all_matches = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.id, m.round, m.match_date, m.status, m.home_score, m.away_score,
		        h.post_title AS home_name, a.post_title AS away_name,
		        m.home_team_id, m.away_team_id
		 FROM   {$p}matches m
		 JOIN   {$p}groups  g  ON g.id  = m.group_id
		 JOIN   {$p}phases  ph ON ph.id = g.phase_id
		 JOIN   {$wpdb->posts} h ON h.ID = m.home_team_id
		 JOIN   {$wpdb->posts} a ON a.ID = m.away_team_id
		 WHERE  ph.division_id = %d
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)
		 ORDER  BY m.round ASC, m.match_date ASC",
		$division_id, $team_id, $team_id
	) );
}

/* ── Statistika ekipe u diviziji (prosjeci + razdioba po poluvremenu) ───── */
$stats = null;
if ( $division_id ) {
	$ms = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.home_team_id, m.home_score, m.away_score
		 FROM   {$p}matches m
		 JOIN   {$p}groups g  ON g.id  = m.group_id
		 JOIN   {$p}phases ph ON ph.id = g.phase_id
		 WHERE  ph.division_id = %d AND m.status = 'finished'
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)",
		$division_id, $team_id, $team_id
	) );
	$N = count( $ms );
	if ( $N ) {
		$gf = $ga = $w = $d = $l = $cs = $btts = $big_win = 0;
		foreach ( $ms as $m ) {
			$home = (int) $m->home_team_id === $team_id;
			$for  = $home ? (int) $m->home_score : (int) $m->away_score;
			$ag   = $home ? (int) $m->away_score : (int) $m->home_score;
			$gf += $for; $ga += $ag;
			if ( $for > $ag ) $w++; elseif ( $for < $ag ) $l++; else $d++;
			if ( $ag === 0 ) $cs++;
			if ( $for > 0 && $ag > 0 ) $btts++;
			if ( $for - $ag > $big_win ) $big_win = $for - $ag;
		}
		$ev = $wpdb->get_results( $wpdb->prepare(
			"SELECT (CASE WHEN e.half = 2 THEN 2 ELSE 1 END) AS h,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type IN ('goal','penalty')          THEN 1 ELSE 0 END) AS gf_own,
			   SUM(CASE WHEN e.team_id <> %d AND e.event_type IN ('goal','penalty')          THEN 1 ELSE 0 END) AS ga_opp,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type = 'own_goal'                    THEN 1 ELSE 0 END) AS og_team,
			   SUM(CASE WHEN e.team_id <> %d AND e.event_type = 'own_goal'                    THEN 1 ELSE 0 END) AS og_opp,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type = 'yellow_card'                 THEN 1 ELSE 0 END) AS yellow,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type IN ('red_card','second_yellow') THEN 1 ELSE 0 END) AS red,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type = 'foul'                        THEN 1 ELSE 0 END) AS fouls
			 FROM   {$p}match_events e
			 JOIN   {$p}matches m  ON m.id  = e.match_id
			 JOIN   {$p}groups  g  ON g.id  = m.group_id
			 JOIN   {$p}phases  ph ON ph.id = g.phase_id
			 WHERE  ph.division_id = %d AND m.status = 'finished'
			   AND  (m.home_team_id = %d OR m.away_team_id = %d)
			 GROUP  BY h",
			$team_id, $team_id, $team_id, $team_id, $team_id, $team_id, $team_id, $division_id, $team_id, $team_id
		) );
		$half = [ 1 => [ 'gf' => 0, 'ga' => 0, 'fouls' => 0 ], 2 => [ 'gf' => 0, 'ga' => 0, 'fouls' => 0 ] ];
		$yellow = $red = 0;
		foreach ( $ev as $r ) {
			$h = (int) $r->h === 2 ? 2 : 1;
			$half[ $h ]['gf']    += (int) $r->gf_own + (int) $r->og_opp;
			$half[ $h ]['ga']    += (int) $r->ga_opp + (int) $r->og_team;
			$half[ $h ]['fouls'] += (int) $r->fouls;
			$yellow += (int) $r->yellow; $red += (int) $r->red;
		}
		$stats = [
			'N' => $N, 'gf' => $gf, 'ga' => $ga, 'w' => $w, 'd' => $d, 'l' => $l,
			'cs' => $cs, 'btts' => $btts, 'big_win' => $big_win,
			'yellow' => $yellow, 'red' => $red,
			'fouls' => $half[1]['fouls'] + $half[2]['fouls'], 'half' => $half,
		];
	}
}

/* ── Helper: boja prema ligi ───────────────────────────── */
function ekipa_league_color( string $league ): string {
	return ( str_contains( strtolower( $league ), 'pan' ) ) ? 'var(--c-pan)' : 'var(--c-kc)';
}
?>

<div class="team-profile">

	<!-- ── HERO ─────────────────────────────────────────────── -->
	<?php $team_color = kurs_team_color( $team_id ); ?>
	<div class="team-hero" style="border-bottom:4px solid <?php echo esc_attr( $team_color ); ?>;<?php if ( $thumb_l ) echo 'background-image:url(' . esc_url( $thumb_l ) . ');'; ?>">
		<div class="team-hero__overlay"></div>
		<div class="container team-hero__inner">
			<div class="team-hero__badge">
				<?php if ( $thumb_s ) : ?>
				<img src="<?php echo esc_url( $thumb_s ); ?>" alt="">
				<?php else : ?>
				<span style="background:<?php echo esc_attr( $team_color ); ?>;color:<?php echo esc_attr( kurs_team_text_color( $team_color ) ); ?>;"><?php echo esc_html( mb_strtoupper( mb_substr( $team->post_title, 0, 2 ) ) ); ?></span>
				<?php endif; ?>
			</div>
			<div>
				<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
					<?php foreach ( $contexts as $c ) : ?>
					<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-family:var(--font-mono);font-size:9px;text-transform:uppercase;font-weight:700;letter-spacing:.05em;background:<?php echo esc_attr( ekipa_league_color( $c->league ) ); ?>;color:#fff;">
						<?php echo esc_html( $c->division_name . ' · ' . $c->season_name ); ?>
					</span>
					<?php endforeach; ?>
				</div>
				<h1 class="team-hero__name"><?php echo esc_html( $team->post_title ); ?></h1>
			</div>
		</div>
	</div>

	<!-- ── LIGA / NATJECANJE — dropdown (auto-otvara aktualnu/najnoviju) ────── -->
	<?php if ( count( $contexts ) > 1 ) :
		$active_color = $active_ctx ? ekipa_league_color( $active_ctx->league ) : 'var(--c-kc)';
	?>
	<div class="hub-select-bar">
		<div class="container hub-select-inner">
			<span class="hub-select__label">Liga / natjecanje</span>
			<div class="hub-dd" id="team-league-dd">
				<button type="button" class="hub-dd__toggle" aria-haspopup="listbox" aria-expanded="false">
					<?php echo kurs_league_icon( ( $active_ctx->league ?? '' ) . ' ' . ( $active_ctx->division_name ?? '' ), 22 ); ?>
					<span class="hub-dd__current"><?php echo esc_html( ( $active_ctx->division_name ?? '' ) . ' · ' . ( $active_ctx->season_name ?? '' ) ); ?></span>
					<span class="hub-dd__caret" aria-hidden="true">▾</span>
				</button>
				<ul class="hub-dd__menu" role="listbox">
					<?php foreach ( $contexts as $c ) :
						$url    = add_query_arg( 'division_id', $c->division_id, get_permalink() );
						$active = (int) $c->division_id === $division_id;
						$dotc   = ekipa_league_color( $c->league );
					?>
					<li role="option" aria-selected="<?php echo $active ? 'true' : 'false'; ?>">
						<a href="<?php echo esc_url( $url ); ?>" class="hub-dd__opt<?php echo $active ? ' is-active' : ''; ?>">
							<?php echo kurs_league_icon( $c->league . ' ' . $c->division_name, 20 ); ?>
							<?php echo esc_html( $c->division_name . ' · ' . $c->season_name ); ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="container" style="padding-block:28px 44px;">
		<div class="team-body">

			<!-- ── LIJEVО: stats + forma + raspored ─────────────── -->
			<div class="team-main">

				<!-- Slika momčadi -->
				<?php $team_photo = kurs_team_photo( $team_id, 'large' ); ?>
				<?php if ( $team_photo ) : ?>
				<div class="team-photo">
					<img src="<?php echo esc_url( $team_photo ); ?>" alt="<?php echo esc_attr( $team->post_title ); ?>">
				</div>
				<?php endif; ?>

				<!-- Brze stats -->
				<?php if ( $team_pos ) : ?>
				<div class="stat-strip">
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['pos']; ?>.</div>
						<div class="stat-box__lbl">Pozicija</div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['Pts']; ?></div>
						<div class="stat-box__lbl">Bodova</div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['W']; ?>&thinsp;/&thinsp;<?php echo $team_pos['D']; ?>&thinsp;/&thinsp;<?php echo $team_pos['L']; ?></div>
						<div class="stat-box__lbl">Pobjede / Remiji / Porazi</div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['GF']; ?><span style="font-weight:400;opacity:.4;font-size:.7em;">:<?php echo $team_pos['GA']; ?></span></div>
						<div class="stat-box__lbl">Golovi</div>
					</div>
				</div>
				<?php elseif ( $division_id ) : ?>
				<div class="kurs-empty" style="margin-bottom:24px;">Nema odigranih utakmica u ovoj sezoni.</div>
				<?php endif; ?>

				<!-- Statistika (prosjeci + razdioba po poluvremenu) -->
				<?php if ( $stats ) :
					$N   = $stats['N'];
					$avg = function ( $x ) use ( $N ) { return $N ? rtrim( rtrim( number_format( $x / $N, 1 ), '0' ), '.' ) : '0'; };
				?>
				<div class="widget team-stats">
					<div class="widget__title">Statistika lige <span class="team-stats__sub"><?php echo (int) $N; ?> odigranih</span></div>

					<div class="ts-grid">
						<div class="ts-box ts-box--gf"><div class="ts-box__val"><?php echo $avg( $stats['gf'] ); ?></div><div class="ts-box__lbl">Golova zabije</div></div>
						<div class="ts-box ts-box--ga"><div class="ts-box__val"><?php echo $avg( $stats['ga'] ); ?></div><div class="ts-box__lbl">Golova primi</div></div>
						<div class="ts-box ts-box--fl"><div class="ts-box__val"><?php echo $avg( $stats['fouls'] ); ?></div><div class="ts-box__lbl">Prekršaja</div></div>
						<div class="ts-box ts-box--yc"><div class="ts-box__val"><?php echo $avg( $stats['yellow'] ); ?></div><div class="ts-box__lbl">Žutih</div></div>
					</div>
					<div class="ts-note">prosjek po utakmici</div>

					<div class="ts-halves">
						<div class="ts-halves__head">
							<span class="ts-halves__title">Po poluvremenu</span>
							<span class="ts-halves__leg"><i class="ts-leg ts-leg--1"></i>1. pol. <i class="ts-leg ts-leg--2"></i>2. pol.</span>
						</div>
						<?php
						$hrows = [
							[ 'Golovi zabijeni',  $stats['half'][1]['gf'],    $stats['half'][2]['gf'] ],
							[ 'Golovi primljeni', $stats['half'][1]['ga'],    $stats['half'][2]['ga'] ],
							[ 'Prekršaji',        $stats['half'][1]['fouls'], $stats['half'][2]['fouls'] ],
						];
						foreach ( $hrows as $hr ) :
							$tot = $hr[1] + $hr[2];
							$p1  = $tot ? round( $hr[1] / $tot * 100 ) : 50;
						?>
						<div class="ts-hrow">
							<div class="ts-hrow__lbl"><?php echo esc_html( $hr[0] ); ?></div>
							<div class="ts-hbar">
								<div class="ts-hbar__1" style="width:<?php echo (int) $p1; ?>%;"><?php if ( $hr[1] ) echo (int) $hr[1]; ?></div>
								<div class="ts-hbar__2" style="width:<?php echo 100 - (int) $p1; ?>%;"><?php if ( $hr[2] ) echo (int) $hr[2]; ?></div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="ts-extra">
						<span><strong><?php echo (int) $stats['cs']; ?></strong> čistih mreža</span>
						<span><strong><?php echo (int) $stats['btts']; ?></strong>× obje zabile</span>
						<span><strong><?php echo (int) $stats['red']; ?></strong> crvenih</span>
						<?php if ( $stats['big_win'] > 0 ) : ?><span>najveća pobjeda <strong>+<?php echo (int) $stats['big_win']; ?></strong></span><?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Forma -->
				<?php if ( $form ) : ?>
				<div class="widget" style="margin-bottom:20px;">
					<div class="widget__title">Forma (zadnjih <?php echo count( $form ); ?> utakmica)</div>
					<div style="display:flex;gap:6px;padding:12px 0 4px;flex-wrap:wrap;">
					<?php foreach ( array_reverse( $form ) as $fm ) :
						$home = (int) $fm->home_team_id === $team_id;
						$gf   = $home ? (int) $fm->home_score : (int) $fm->away_score;
						$ga   = $home ? (int) $fm->away_score : (int) $fm->home_score;
						if ( $gf > $ga )      { $r = 'P'; $bg = 'var(--c-win)'; }
						elseif ( $gf < $ga )  { $r = 'I'; $bg = 'var(--c-loss)'; }
						else                  { $r = 'N'; $bg = 'var(--c-draw)'; }
					?>
					<a href="<?php echo esc_url( add_query_arg( 'match_id', $fm->id, home_url( '/utakmica/' ) ) ); ?>"
					   title="<?php echo "$gf : $ga"; ?>"
					   style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:5px;background:<?php echo $bg; ?>;color:#fff;font-family:var(--font-mono);font-size:13px;font-weight:700;text-decoration:none;">
						<?php echo $r; ?>
					</a>
					<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Sljedeća utakmica -->
				<?php if ( $next ) : ?>
				<div class="next-match-card">
					<div class="next-match-card__label">Sljedeća &middot; <?php echo (int) $next->round; ?>. kolo</div>
					<div class="next-match-card__teams">
						<span><?php echo esc_html( $next->home_name ); ?></span>
						<span class="next-match-card__vs">vs</span>
						<span><?php echo esc_html( $next->away_name ); ?></span>
					</div>
					<?php if ( $next->match_date ) : ?>
					<div class="next-match-card__date">
						<?php echo esc_html( date_i18n( 'j.n.Y. · H:i', strtotime( $next->match_date ) ) ); ?>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<!-- Svi mečevi -->
				<?php if ( $all_matches ) : ?>
				<div class="widget" style="margin-top:24px;">
					<div class="widget__title">Sve utakmice u sezoni</div>
					<div class="schedule" style="margin-top:0;">
					<?php foreach ( $all_matches as $m ) :
						$home = (int) $m->home_team_id === $team_id;
						$opp  = esc_html( $home ? $m->away_name : $m->home_name );
						$gf   = $home ? (int) $m->home_score : (int) $m->away_score;
						$ga   = $home ? (int) $m->away_score : (int) $m->home_score;
						if ( $m->status === 'finished' ) {
							if ( $gf > $ga )     $sc = "<span style='color:var(--c-win);font-weight:700;'>$gf : $ga</span>";
							elseif ( $gf < $ga ) $sc = "<span style='color:var(--c-loss);font-weight:700;'>$gf : $ga</span>";
							else                 $sc = "<span style='color:var(--c-draw);font-weight:700;'>$gf : $ga</span>";
						} else {
							$sc = "<span style='color:var(--c-muted);'>" . ( $m->match_date ? esc_html( date_i18n( 'j.n. H:i', strtotime( $m->match_date ) ) ) : 'TBD' ) . "</span>";
						}
					?>
					<a href="<?php echo esc_url( add_query_arg( 'match_id', $m->id, home_url( '/utakmica/' ) ) ); ?>"
					   class="sched-match">
						<div style="font-family:var(--font-mono);font-size:10px;color:var(--c-muted);min-width:42px;"><?php echo (int) $m->round; ?>. kolo</div>
						<div class="sched-match__home"><?php echo $home ? '<strong>' . esc_html( get_the_title( $team_id ) ) . '</strong>' : $opp; ?></div>
						<div class="sched-match__score"><?php echo $sc; ?></div>
						<div class="sched-match__away"><?php echo $home ? $opp : '<strong>' . esc_html( get_the_title( $team_id ) ) . '</strong>'; ?></div>
					</a>
					<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

			</div><!-- /.team-main -->

			<!-- ── DESNO: roster + pozicija ─────────────────────── -->
			<aside class="team-side">

				<!-- Roster -->
				<div class="widget">
					<div class="widget__title">Roster<?php if ( $active_ctx ) echo ' · <span style="font-size:10px;font-weight:400;">' . esc_html( $active_ctx->division_name ) . '</span>'; ?></div>
					<?php if ( $squad ) : ?>
					<div style="display:flex;flex-direction:column;gap:2px;margin-top:6px;">
					<?php foreach ( $squad as $pl ) :
						$goals   = $scorer_map[ (int) $pl->player_id ] ?? 0;
						$ply_url = get_permalink( (int) $pl->player_id );
					?>
					<a href="<?php echo esc_url( $ply_url ); ?>" class="roster-row">
						<div class="roster-row__num"><?php echo esc_html( $pl->jersey_no ?: '—' ); ?></div>
						<div class="roster-row__name"><?php echo esc_html( $pl->player_name ); ?></div>
						<?php if ( kurs_player_type( (int) $pl->player_id ) === 'goalkeeper' ) : ?>
						<span class="roster-row__pos">GLM</span>
						<?php endif; ?>
						<?php if ( $goals ) : ?>
						<div class="roster-row__goals" title="Golovi u ovoj sezoni">⚽&thinsp;<?php echo $goals; ?></div>
						<?php endif; ?>
					</a>
					<?php endforeach; ?>
					</div>
					<?php else : ?>
					<p style="font-family:var(--font-mono);font-size:10px;color:var(--c-muted);padding:10px 0;">Roster nije unesen za ovu sezonu.</p>
					<?php endif; ?>
				</div>

				<!-- Pozicija -->
				<?php if ( $team_pos && $active_ctx ) :
					$liga_slug = str_contains( strtolower( $active_ctx->league ), 'pan' ) ? 'pan-ljetna' : 'kc-liga';
				?>
				<div class="widget" style="margin-top:16px;">
					<div class="widget__title">Tablica</div>
					<div style="display:flex;align-items:baseline;gap:6px;padding:12px 0 4px;">
						<span style="font-family:var(--font-cond);font-weight:800;font-style:italic;font-size:52px;line-height:1;color:<?php echo esc_attr( ekipa_league_color( $active_ctx->league ) ); ?>;">
							<?php echo $team_pos['pos']; ?>.
						</span>
						<span style="font-size:13px;color:var(--c-muted);">od <?php echo $team_pos['total']; ?></span>
					</div>
					<div style="font-size:11px;color:var(--c-muted);font-family:var(--font-mono);margin-bottom:12px;">
						<?php echo $team_pos['Pts']; ?> bod · GR <?php echo ( $team_pos['GD'] >= 0 ? '+' : '' ) . $team_pos['GD']; ?>
					</div>
					<a href="<?php echo esc_url( add_query_arg( [ 'division_id' => $division_id, 'tab' => 'tablica' ], home_url( "/$liga_slug/" ) ) ); ?>"
					   style="font-family:var(--font-cond);font-weight:700;font-size:12px;text-transform:uppercase;color:var(--c-muted);">
						Puna tablica &rarr;
					</a>
				</div>
				<?php endif; ?>

				<!-- Stožer (trener / predstavnik) -->
				<?php $t_coach = kurs_team_coach( $team_id ); $t_rep = kurs_team_rep( $team_id ); ?>
				<?php if ( $t_coach || $t_rep ) : ?>
				<div class="widget" style="margin-top:16px;">
					<div class="widget__title">Stožer</div>
					<div class="team-officials">
						<?php if ( $t_coach ) : ?>
						<div class="team-officials__row">
							<span class="team-officials__role">Trener</span>
							<span class="team-officials__name"><?php echo esc_html( $t_coach['name'] ); ?></span>
							<?php if ( $t_coach['phone'] ) : ?><span class="team-officials__phone"><?php echo esc_html( $t_coach['phone'] ); ?></span><?php endif; ?>
						</div>
						<?php endif; ?>
						<?php if ( $t_rep ) : ?>
						<div class="team-officials__row">
							<span class="team-officials__role">Predstavnik</span>
							<span class="team-officials__name"><?php echo esc_html( $t_rep['name'] ); ?></span>
							<?php if ( $t_rep['phone'] ) : ?><span class="team-officials__phone"><?php echo esc_html( $t_rep['phone'] ); ?></span><?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

			</aside><!-- /.team-side -->

		</div><!-- /.team-body -->
	</div>

	<!-- ── Sponzorski baneri (može ih biti više) ─────────────── -->
	<?php $team_banners = kurs_team_banners( $team_id, 'large' ); ?>
	<?php if ( $team_banners ) : ?>
	<div class="container team-banner-wrap">
		<div class="team-banner__label"><?php echo count( $team_banners ) > 1 ? 'Sponzori ekipe' : 'Sponzor ekipe'; ?></div>
		<?php foreach ( $team_banners as $bn ) : ?>
			<?php if ( $bn['link'] ) : ?>
			<a href="<?php echo esc_url( $bn['link'] ); ?>" target="_blank" rel="noopener nofollow sponsored" class="team-banner">
				<img src="<?php echo esc_url( $bn['url'] ); ?>" alt="<?php echo esc_attr( $team->post_title ); ?> — sponzor">
			</a>
			<?php else : ?>
			<div class="team-banner">
				<img src="<?php echo esc_url( $bn['url'] ); ?>" alt="<?php echo esc_attr( $team->post_title ); ?> — sponzor">
			</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

</div><!-- /.team-profile -->

<?php get_footer(); ?>
