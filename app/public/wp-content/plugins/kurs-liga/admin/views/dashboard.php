<?php
defined( 'ABSPATH' ) || exit;

/*
 * KURS Liga → Pregled: ulaz u oba natjecanja (aktualna sezona svakog) + ono što
 * traži pažnju u svim aktivnim sezonama (utakmice bez rezultata, ovaj tjedan).
 */
global $wpdb;
$p   = $wpdb->prefix . KURS_PREFIX;
$now = current_time( 'mysql' );

/* Utakmice aktivnih (nezaključanih) sezona — i playoff (group_id = 0) */
$from = "FROM {$p}matches m
	LEFT JOIN {$p}groups g ON g.id = m.group_id
	LEFT JOIN {$p}phases ph ON ph.id = g.phase_id
	LEFT JOIN {$p}playoff_matches pm ON pm.match_id = m.id
	JOIN {$p}divisions d ON d.id = COALESCE( ph.division_id, pm.division_id )
	JOIN {$p}seasons s ON s.id = d.season_id
	JOIN {$wpdb->posts} h ON h.ID = m.home_team_id
	JOIN {$wpdb->posts} a ON a.ID = m.away_team_id
	WHERE s.status = 'active' AND s.locked = 0";
$cols = 'm.id, m.round, m.match_date, m.status, m.home_score, m.away_score, h.post_title AS home_name, a.post_title AS away_name,
	d.name AS div_name, s.league, s.id AS season_id, pm.round AS po_round';
$overdue  = $wpdb->get_results( $wpdb->prepare( "SELECT $cols $from AND m.status <> 'finished' AND m.match_date < %s ORDER BY m.match_date LIMIT 10", $now ) );
$week     = $wpdb->get_results( $wpdb->prepare( "SELECT $cols $from AND m.status <> 'finished' AND m.match_date >= %s AND m.match_date < %s ORDER BY m.match_date LIMIT 20",
	$now, date( 'Y-m-d 00:00:00', strtotime( '+8 days', strtotime( $now ) ) ) ) );
$recent   = $wpdb->get_results( "SELECT $cols $from AND m.status = 'finished' ORDER BY m.match_date DESC, m.id DESC LIMIT 8" );

$po_names = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => 'Za 3. mjesto' ];
$tag      = function ( $m ): string {
	$cls = Kurs_Season::is_pan_league( (string) $m->league ) ? 'pan' : ( stripos( $m->div_name, 'briga' ) !== false ? 'lbb' : 'kc' );
	return '<span class="kurs-league-tag kurs-league-tag--' . $cls . '">' . esc_html( $m->div_name ) . '</span>';
};
$where    = fn( $m ) => $m->po_round ? ( $po_names[ $m->po_round ] ?? 'Playoff' ) : (int) $m->round . '. kolo';
$days     = [ 'ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub' ];
$when     = fn( $m ) => $m->match_date ? $days[ (int) date( 'w', strtotime( $m->match_date ) ) ] . ' ' . date( 'j.n. · H:i', strtotime( $m->match_date ) ) : '—';
$rec_url  = fn( $m ) => admin_url( 'admin.php?page=kurs-match-record&match_id=' . (int) $m->id );
?>
<div class="wrap kurs-wrap">

	<div class="kurs-breadcrumb">KURS Liga <span class="kurs-breadcrumb__sep">/</span> Pregled</div>
	<div class="kurs-page-hd">
		<h1>Pregled</h1>
		<div style="display:flex;gap:8px;flex-wrap:wrap;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams' ) ); ?>" class="kurs-btn kurs-btn--outline kurs-btn--sm">Ekipe i igrači</a>
			<a href="<?php echo esc_url( home_url( '/za-medije/' ) ); ?>" class="kurs-btn kurs-btn--outline kurs-btn--sm" target="_blank" rel="noopener">Za medije &#8599;</a>
		</div>
	</div>

	<!-- ── Natjecanja ─────────────────────────────────────────────────────── -->
	<div class="kurs-comp-cards">
		<?php foreach ( Kurs_Admin::HUBS as $slug => $league ) :
			$is_pan = Kurs_Season::is_pan_league( $league );
			$comp   = Kurs_Season::competition( $league );
			$season = Kurs_Season::current_for_league( $is_pan );
			$divs   = $season ? Kurs_Season::get_divisions( (int) $season->id ) : [];
			$hub    = admin_url( 'admin.php?page=' . $slug );
		?>
		<div class="kurs-comp-card kurs-comp-card--<?php echo $is_pan ? 'pan' : 'kc'; ?>">
			<a class="kurs-comp-card__hd" href="<?php echo esc_url( $hub ); ?>">
				<?php echo kurs_league_icon( $is_pan ? 'Pan Ljetna' : 'KC Liga', 48 ); ?>
				<span>
					<span class="kurs-comp-card__name"><?php echo esc_html( $comp['label'] ?? '' ); ?></span>
					<span class="kurs-comp-card__season">
						<?php echo $season ? esc_html( $season->name ) : 'Nema sezone'; ?>
						<?php if ( $season && ! empty( $season->locked ) ) : ?> · &#128274; zaključana<?php elseif ( $season && $season->status !== 'active' ) : ?> · arhiva<?php endif; ?>
					</span>
				</span>
			</a>
			<div class="kurs-comp-card__body">
				<?php if ( ! $divs ) : ?>
				<p class="kurs-muted" style="margin:0;">Za ovo natjecanje još nema sezone.</p>
				<?php endif; ?>
				<?php foreach ( $divs as $d ) :
					$did     = (int) $d->id;
					$rs      = Kurs_Rounds::round_status( $did );
					$played  = array_sum( array_column( $rs, 'finished' ) ) ? Kurs_Rounds::default_round( $did ) : 0; // zadnje kolo s rezultatom
					$ph      = Kurs_Season::group_phase( $did );
					$groups  = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
					$teams   = array_sum( array_map( fn( $g ) => count( Kurs_Season::get_group_teams( (int) $g->id ) ), $groups ) );
					$leader  = ( count( $groups ) === 1 && $played ) ? ( Kurs_Match::get_standings( (int) $groups[0]->id )[0] ?? null ) : null;
				?>
				<div class="kurs-comp-card__div">
					<strong><?php echo esc_html( $d->name ); ?></strong>
					<span class="kurs-muted">
						<?php echo (int) $teams; ?> ekipa
						<?php if ( $rs ) : ?> · <?php echo $played ? (int) $played . '. kolo od ' . count( $rs ) : count( $rs ) . ' kola, još se nije igralo'; ?><?php else : ?> · bez rasporeda<?php endif; ?>
						<?php if ( $leader ) : ?> · vodi <?php echo esc_html( $leader['team_name'] ); ?><?php endif; ?>
					</span>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="kurs-comp-card__foot">
				<a class="kurs-btn kurs-btn--red kurs-btn--sm" href="<?php echo esc_url( $hub ); ?>">Otvori <?php echo esc_html( $comp['label'] ?? '' ); ?> &rarr;</a>
				<?php if ( $season ) : ?>
				<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( Kurs_Admin::hub_url( (int) $season->id, 'utakmice' ) ); ?>">Utakmice</a>
				<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( Kurs_Admin::hub_url( (int) $season->id, 'ekipe' ) ); ?>">Ekipe</a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- ── Što traži pažnju ───────────────────────────────────────────────── -->
	<div class="kurs-dash-panels">

		<div class="kurs-form-col" style="gap:16px;">
			<?php if ( $overdue ) : ?>
			<div class="kurs-panel kurs-panel--warn">
				<div class="kurs-panel__head"><h3 class="kurs-panel__title">Čeka rezultat</h3><span class="kurs-muted"><?php echo count( $overdue ); ?></span></div>
				<div class="kurs-panel__body" style="padding:0;">
					<?php foreach ( $overdue as $m ) : ?>
					<div class="kurs-hub-match">
						<div class="kurs-hub-match__when"><?php echo esc_html( $when( $m ) ); ?></div>
						<div class="kurs-hub-match__body">
							<div class="kurs-hub-match__teams"><?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?></div>
							<div class="kurs-hub-match__meta"><?php echo $tag( $m ); ?> <?php echo esc_html( $where( $m ) ); ?></div>
						</div>
						<a class="kurs-btn kurs-btn--red kurs-btn--sm" href="<?php echo esc_url( $rec_url( $m ) ); ?>">Unesi zapisnik</a>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="kurs-panel">
				<div class="kurs-panel__head"><h3 class="kurs-panel__title">Ovaj tjedan</h3></div>
				<div class="kurs-panel__body" style="padding:0;">
					<?php if ( $week ) : foreach ( $week as $m ) : ?>
					<div class="kurs-hub-match">
						<div class="kurs-hub-match__when"><?php echo esc_html( $when( $m ) ); ?></div>
						<div class="kurs-hub-match__body">
							<div class="kurs-hub-match__teams"><?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?></div>
							<div class="kurs-hub-match__meta"><?php echo $tag( $m ); ?> <?php echo esc_html( $where( $m ) ); ?></div>
						</div>
						<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( $rec_url( $m ) ); ?>">Zapisnik</a>
					</div>
					<?php endforeach; else : ?>
					<div class="kurs-panel__empty">Ovaj tjedan nema zakazanih utakmica.</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="kurs-panel">
			<div class="kurs-panel__head"><h3 class="kurs-panel__title">Zadnji rezultati</h3></div>
			<div class="kurs-panel__body" style="padding:0;">
				<?php if ( $recent ) : foreach ( $recent as $m ) : ?>
				<a class="kurs-hub-match kurs-hub-match--link" href="<?php echo esc_url( $rec_url( $m ) ); ?>">
					<div class="kurs-hub-match__when"><?php echo esc_html( $when( $m ) ); ?></div>
					<div class="kurs-hub-match__body">
						<div class="kurs-hub-match__teams"><?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?></div>
						<div class="kurs-hub-match__meta"><?php echo $tag( $m ); ?> <?php echo esc_html( $where( $m ) ); ?></div>
					</div>
					<div class="kurs-hub-match__score"><?php echo (int) $m->home_score; ?> : <?php echo (int) $m->away_score; ?></div>
				</a>
				<?php endforeach; else : ?>
				<div class="kurs-panel__empty">Nema unesenih rezultata.</div>
				<?php endif; ?>
			</div>
		</div>

	</div>
</div>
