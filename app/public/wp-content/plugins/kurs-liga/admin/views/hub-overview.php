<?php
defined( 'ABSPATH' ) || exit;

/*
 * Stranica natjecanja → kartica „Pregled": početna sezone.
 *   1. što treba napraviti (ekipe → raspored → termini → rezultati → playoff → zaključavanje)
 *   2. lige: ekipe, odigrana kola, vodeći, sljedeće kolo
 *   3. utakmice koje čekaju rezultat, sljedeće utakmice, zadnji rezultati
 * Varijable iz season-hub.php: $season, $season_id, $divisions, $is_pan, $accent, $is_locked, $hub_url.
 */

global $wpdb;
$p   = $wpdb->prefix . KURS_PREFIX;
$now = current_time( 'mysql' );

/* ── Lige ─────────────────────────────────────────────────────────────── */
$leagues = [];
$tot     = [ 'teams' => 0, 'matches' => 0, 'finished' => 0, 'undated' => 0, 'overdue' => 0, 'short' => 0 ];
foreach ( $divisions as $d ) {
	$did    = (int) $d->id;
	$ph     = Kurs_Season::group_phase( $did );
	$groups = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
	$teams  = 0;
	foreach ( $groups as $g ) {
		$n = count( Kurs_Season::get_group_teams( (int) $g->id ) );
		$teams += $n;
		if ( $n < 2 ) $tot['short']++;
	}
	$st = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS total, SUM(m.status = 'finished') AS fin,
		        SUM(m.status <> 'finished' AND m.match_date IS NULL) AS undated,
		        SUM(m.status <> 'finished' AND m.match_date < %s) AS overdue
		 FROM {$p}matches m JOIN {$p}groups g ON g.id = m.group_id JOIN {$p}phases ph ON ph.id = g.phase_id
		 WHERE ph.division_id = %d", $now, $did ) );
	$rounds  = Kurs_Rounds::round_status( $did );
	$last    = (int) $st->fin ? Kurs_Rounds::default_round( $did ) : 0; // zadnje kolo s rezultatom
	// Sljedeće = prvo kolo s neodigranom utakmicom u budućnosti (odgođene iz prošlosti su „čeka rezultat")
	$next    = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT MIN(m.round) FROM {$p}matches m JOIN {$p}groups g ON g.id = m.group_id JOIN {$p}phases ph ON ph.id = g.phase_id
		 WHERE ph.division_id = %d AND m.status <> 'finished' AND ( m.match_date IS NULL OR m.match_date >= %s )", $did, $now ) ) ?: null;
	$leader = null;
	if ( count( $groups ) === 1 && (int) $st->fin ) {
		$leader = Kurs_Match::get_standings( (int) $groups[0]->id )[0] ?? null;
	}
	$bracket  = ! empty( $d->has_playoff ) && class_exists( 'Kurs_Playoff' ) ? Kurs_Playoff::get_bracket( $did ) : [];
	$final    = $bracket['final'][1] ?? null;
	$side     = $final ? Kurs_Playoff::winner_side( $final ) : null;
	$champion = $side ? ( $side === 'home' ? $final->home_name : $final->away_name ) : null;

	$leagues[] = [
		'div' => $d, 'groups' => count( $groups ), 'first_group' => (int) ( $groups[0]->id ?? 0 ), 'teams' => $teams,
		'total' => (int) $st->total, 'finished' => (int) $st->fin, 'undated' => (int) $st->undated, 'overdue' => (int) $st->overdue,
		'rounds' => count( $rounds ), 'last' => $last,
		'next' => $next, 'next_date' => $next ? Kurs_Rounds::date_label( Kurs_Rounds::matches( $did, $next ) ) : '',
		'leader' => $leader, 'has_playoff' => ! empty( $d->has_playoff ), 'bracket' => (bool) $bracket, 'champion' => $champion,
		'color' => stripos( $d->name, 'briga' ) !== false ? '#B07A1B' : $accent,
	];
	$tot['teams']    += $teams;
	$tot['matches']  += (int) $st->total;
	$tot['finished'] += (int) $st->fin;
	$tot['undated']  += (int) $st->undated;
	$tot['overdue']  += (int) $st->overdue;
}

/* ── Utakmice sezone (i playoff: group_id = 0 → liga preko playoff_matches) ── */
$from = "FROM {$p}matches m
	LEFT JOIN {$p}groups g ON g.id = m.group_id
	LEFT JOIN {$p}phases ph ON ph.id = g.phase_id
	LEFT JOIN {$p}playoff_matches pm ON pm.match_id = m.id
	JOIN {$p}divisions d ON d.id = COALESCE( ph.division_id, pm.division_id )
	JOIN {$wpdb->posts} h ON h.ID = m.home_team_id
	JOIN {$wpdb->posts} a ON a.ID = m.away_team_id
	WHERE d.season_id = %d";
$cols = 'm.id, m.round, m.match_date, m.status, m.home_score, m.away_score, h.post_title AS home_name, a.post_title AS away_name,
	d.name AS div_name, g.name AS group_name, pm.round AS po_round, ( SELECT COUNT(*) FROM ' . $p . 'groups g2 WHERE g2.phase_id = g.phase_id ) AS group_count';
$overdue  = $wpdb->get_results( $wpdb->prepare( "SELECT $cols $from AND m.status <> 'finished' AND m.match_date < %s ORDER BY m.match_date LIMIT 12", $season_id, $now ) );
$upcoming = $wpdb->get_results( $wpdb->prepare( "SELECT $cols $from AND m.status <> 'finished' AND m.match_date >= %s ORDER BY m.match_date LIMIT 8", $season_id, $now ) );
$recent   = $wpdb->get_results( $wpdb->prepare( "SELECT $cols $from AND m.status = 'finished' ORDER BY m.match_date DESC, m.id DESC LIMIT 8", $season_id ) );

$po_names   = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => 'Za 3. mjesto' ];
$match_meta = function ( $m ) use ( $divisions, $po_names ): string {
	$where = $m->po_round ? ( $po_names[ $m->po_round ] ?? 'Playoff' ) : ( (int) $m->round . '. kolo' );
	$league = count( $divisions ) > 1 ? $m->div_name : ( (int) $m->group_count > 1 ? $m->group_name : '' );
	return trim( ( $league ? $league . ' · ' : '' ) . $where );
};
$record_url = fn( $m ) => admin_url( 'admin.php?page=kurs-match-record&match_id=' . (int) $m->id );
$days       = [ 'ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub' ];
$when       = fn( $m ) => $m->match_date ? $days[ (int) date( 'w', strtotime( $m->match_date ) ) ] . ' ' . date( 'j.n. · H:i', strtotime( $m->match_date ) ) : 'bez termina';

/* ── Što treba napraviti ─────────────────────────────────────────────── */
$group_stage_done = $tot['matches'] && $tot['finished'] >= $tot['matches'];
$po_leagues       = array_filter( $leagues, fn( $l ) => $l['has_playoff'] );
$steps = [
	[
		'label' => 'Ekipe u ligama',
		'state' => $tot['short'] ? 'todo' : 'done',
		'text'  => $tot['short'] ? 'Uvrsti ekipe u lige' . ( count( $divisions ) > 1 || $is_pan ? ' (svaka liga/skupina treba barem 2)' : '' ) : $tot['teams'] . ' ekipa',
		'url'   => $hub_url( 'ekipe' ),
	],
	[
		'label' => 'Raspored',
		'state' => $tot['matches'] ? 'done' : ( $tot['short'] ? 'wait' : 'todo' ),
		'text'  => $tot['matches'] ? $tot['matches'] . ' utakmica' : 'Generiraj raspored',
		'url'   => $hub_url( $tot['matches'] ? 'utakmice' : 'ekipe' ),
	],
	[
		'label' => 'Termini',
		'state' => ! $tot['matches'] ? 'wait' : ( $tot['undated'] ? 'todo' : 'done' ),
		'text'  => ! $tot['matches'] ? '—' : ( $tot['undated'] ? $tot['undated'] . ' utakmica bez termina' : 'Sve utakmice imaju termin' ),
		'url'   => $hub_url( 'utakmice' ),
	],
	[
		'label' => 'Rezultati',
		'state' => ! $tot['matches'] ? 'wait' : ( $tot['overdue'] ? 'todo' : ( $group_stage_done ? 'done' : 'live' ) ),
		'text'  => ! $tot['matches'] ? '—' : ( $tot['overdue'] ? $tot['overdue'] . ' utakmica čeka rezultat' : $tot['finished'] . ' / ' . $tot['matches'] . ' odigrano' ),
		'url'   => $tot['overdue'] ? '#ceka-rezultat' : $hub_url( 'utakmice' ),
	],
];
foreach ( $po_leagues as $l ) {
	$steps[] = [
		'label' => 'Playoff' . ( count( $po_leagues ) > 1 ? ' · ' . $l['div']->name : '' ),
		'state' => $l['champion'] ? 'done' : ( $l['bracket'] ? 'live' : ( $group_stage_done ? 'todo' : 'wait' ) ),
		'text'  => $l['champion'] ? 'Prvak: ' . $l['champion'] : ( $l['bracket'] ? 'U tijeku' : ( $group_stage_done ? 'Generiraj bracket' : 'Nakon skupina' ) ),
		'url'   => $hub_url( 'utakmice', [ 'playoff_div' => (int) $l['div']->id ] ),
	];
}
$season_over = $tot['matches'] && $group_stage_done && ( ! $po_leagues || ! array_filter( $po_leagues, fn( $l ) => ! $l['champion'] ) );
$steps[] = [
	'label' => 'Zaključavanje',
	'state' => $is_locked ? 'done' : ( $season_over ? 'todo' : 'wait' ),
	'text'  => $is_locked ? 'Sezona zaključana' : ( $season_over ? 'Sve odigrano — zaključaj sezonu' : 'Na kraju sezone' ),
	'url'   => $hub_url( 'postavke' ),
];
$state_icon = [ 'done' => '✓', 'todo' => '!', 'live' => '●', 'wait' => '·' ];
?>

<!-- ── Što treba napraviti ─────────────────────────────────────────────── -->
<ol class="kurs-hub-steps">
	<?php foreach ( $steps as $i => $s ) : ?>
	<li class="kurs-hub-step is-<?php echo esc_attr( $s['state'] ); ?>">
		<a href="<?php echo esc_url( $s['url'] ); ?>">
			<span class="kurs-hub-step__ico"><?php echo esc_html( $state_icon[ $s['state'] ] ); ?></span>
			<span class="kurs-hub-step__txt">
				<span class="kurs-hub-step__lbl"><?php echo ( $i + 1 ) . '. ' . esc_html( $s['label'] ); ?></span>
				<span class="kurs-hub-step__sub"><?php echo esc_html( $s['text'] ); ?></span>
			</span>
		</a>
	</li>
	<?php endforeach; ?>
</ol>

<!-- ── Lige ─────────────────────────────────────────────────────────────── -->
<div class="kurs-hub-leagues">
	<?php foreach ( $leagues as $l ) :
		$d   = $l['div'];
		$pct = $l['total'] ? round( 100 * $l['finished'] / $l['total'] ) : 0;
		$pub = kurs_league_url( (int) $d->id, [ 'tab' => 'kola' ] ) . '#kola';
		$grp = $l['first_group'];
	?>
	<div class="kurs-hub-league" style="--lg:<?php echo esc_attr( $l['color'] ); ?>;">
		<div class="kurs-hub-league__hd">
			<?php echo kurs_league_icon( $season->league . ' ' . $d->name, 34 ); ?>
			<div>
				<h3><?php echo esc_html( $d->name ); ?></h3>
				<div class="kurs-muted"><?php echo (int) $l['teams']; ?> ekipa<?php echo $l['groups'] > 1 ? ' · ' . (int) $l['groups'] . ' skupine' : ''; ?> · <?php echo (int) $l['total']; ?> utakmica</div>
			</div>
		</div>

		<?php if ( $l['rounds'] ) : ?>
		<div class="kurs-hub-league__prog">
			<div class="kurs-hub-league__bar"><span style="width:<?php echo (int) $pct; ?>%;"></span></div>
			<div class="kurs-hub-league__prog-txt">
				<strong><?php echo $l['last'] ? (int) $l['last'] . '. kolo od ' . (int) $l['rounds'] : 'Počinje ' . ( $l['next_date'] ?: 'uskoro' ); ?></strong>
				<span class="kurs-muted"><?php echo (int) $l['finished']; ?> od <?php echo (int) $l['total']; ?> utakmica</span>
			</div>
		</div>
		<?php else : ?>
		<p class="kurs-muted" style="margin:12px 0;">Raspored još nije napravljen.</p>
		<?php endif; ?>

		<dl class="kurs-hub-league__facts">
			<?php if ( $l['champion'] ) : ?>
			<div><dt>Prvak</dt><dd>&#127942; <?php echo esc_html( $l['champion'] ); ?></dd></div>
			<?php elseif ( $l['leader'] ) : ?>
			<div><dt>Vodi</dt><dd><?php echo esc_html( $l['leader']['team_name'] ); ?> <span class="kurs-muted">· <?php echo (int) $l['leader']['Pts']; ?> bod.</span></dd></div>
			<?php endif; ?>
			<?php if ( $l['next'] ) : ?>
			<div><dt>Sljedeće</dt><dd><?php echo (int) $l['next']; ?>. kolo<?php echo $l['next_date'] ? ' <span class="kurs-muted">· ' . esc_html( $l['next_date'] ) . '</span>' : ''; ?></dd></div>
			<?php endif; ?>
			<?php if ( $l['overdue'] ) : ?>
			<div class="is-warn"><dt>Čeka rezultat</dt><dd><?php echo (int) $l['overdue']; ?> utakmica</dd></div>
			<?php endif; ?>
		</dl>

		<div class="kurs-hub-league__foot">
			<a class="kurs-btn kurs-btn--dark kurs-btn--sm" href="<?php echo esc_url( $hub_url( 'utakmice', $grp ? [ 'group_id' => (int) $grp ] : [] ) ); ?>">Utakmice i tablica</a>
			<?php if ( $l['has_playoff'] ) : ?>
			<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( $hub_url( 'utakmice', [ 'playoff_div' => (int) $d->id ] ) ); ?>">Playoff</a>
			<?php endif; ?>
			<?php if ( $l['last'] && $l['finished'] ) : ?>
			<a class="kurs-btn kurs-btn--ghost kurs-btn--sm" href="<?php echo esc_url( $pub ); ?>" target="_blank" rel="noopener">Na webu &#8599;</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<!-- ── Utakmice ─────────────────────────────────────────────────────────── -->
<div class="kurs-hub-lists">
	<?php if ( $overdue ) : ?>
	<div class="kurs-panel kurs-panel--warn" id="ceka-rezultat">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">Čeka rezultat</h3><span class="kurs-muted"><?php echo count( $overdue ); ?></span></div>
		<div class="kurs-panel__body" style="padding:0;">
			<?php foreach ( $overdue as $m ) : ?>
			<div class="kurs-hub-match">
				<div class="kurs-hub-match__when"><?php echo esc_html( $when( $m ) ); ?></div>
				<div class="kurs-hub-match__body">
					<div class="kurs-hub-match__teams"><?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?></div>
					<div class="kurs-hub-match__meta"><?php echo esc_html( $match_meta( $m ) ); ?></div>
				</div>
				<a class="kurs-btn kurs-btn--red kurs-btn--sm" href="<?php echo esc_url( $record_url( $m ) ); ?>">Unesi zapisnik</a>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="kurs-panel">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">Sljedeće utakmice</h3></div>
		<div class="kurs-panel__body" style="padding:0;">
			<?php if ( $upcoming ) : foreach ( $upcoming as $m ) : ?>
			<div class="kurs-hub-match">
				<div class="kurs-hub-match__when"><?php echo esc_html( $when( $m ) ); ?></div>
				<div class="kurs-hub-match__body">
					<div class="kurs-hub-match__teams"><?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?></div>
					<div class="kurs-hub-match__meta"><?php echo esc_html( $match_meta( $m ) ); ?></div>
				</div>
				<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( $record_url( $m ) ); ?>">Zapisnik</a>
			</div>
			<?php endforeach; else : ?>
			<div class="kurs-panel__empty"><?php echo $tot['undated'] ? 'Nema zakazanih utakmica — ' . (int) $tot['undated'] . ' utakmica još nema termin.' : 'Nema zakazanih utakmica.'; ?></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="kurs-panel">
		<div class="kurs-panel__head">
			<h3 class="kurs-panel__title">Zadnji rezultati</h3>
			<?php if ( $recent ) : ?>
			<a class="kurs-muted" style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;text-decoration:none;" target="_blank" rel="noopener"
			   href="<?php echo esc_url( add_query_arg( [ 'liga' => $is_pan ? 'pan' : 'kc', 'season_id' => $season_id ], home_url( '/za-medije/' ) ) ); ?>">Paket za medije &#8599;</a>
			<?php endif; ?>
		</div>
		<div class="kurs-panel__body" style="padding:0;">
			<?php if ( $recent ) : foreach ( $recent as $m ) : ?>
			<a class="kurs-hub-match kurs-hub-match--link" href="<?php echo esc_url( $record_url( $m ) ); ?>">
				<div class="kurs-hub-match__when"><?php echo esc_html( $when( $m ) ); ?></div>
				<div class="kurs-hub-match__body">
					<div class="kurs-hub-match__teams"><?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?></div>
					<div class="kurs-hub-match__meta"><?php echo esc_html( $match_meta( $m ) ); ?></div>
				</div>
				<div class="kurs-hub-match__score"><?php echo (int) $m->home_score; ?> : <?php echo (int) $m->away_score; ?></div>
			</a>
			<?php endforeach; else : ?>
			<div class="kurs-panel__empty">Još nema odigranih utakmica.</div>
			<?php endif; ?>
		</div>
	</div>
</div>
