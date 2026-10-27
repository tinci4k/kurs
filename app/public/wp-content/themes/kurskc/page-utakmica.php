<?php
/**
 * Template Name: Centar utakmice
 *
 * URL: /utakmica/?match_id=X
 */
get_header();

if ( ! class_exists( 'Kurs_Match' ) ) {
	echo '<div class="container kurs-empty">KURS Liga plugin nije aktivan.</div>';
	get_footer();
	return;
}

$match_id = absint( $_GET['match_id'] ?? 0 );
if ( ! $match_id ) {
	echo '<div class="container kurs-empty">Nije odabrana utakmica.</div>';
	get_footer();
	return;
}

$match = Kurs_Match::get( $match_id );
if ( ! $match ) {
	echo '<div class="container kurs-empty">Utakmica ne postoji.</div>';
	get_footer();
	return;
}

global $wpdb;
$p    = $wpdb->prefix . 'kurs_';
$info = $wpdb->get_row( $wpdb->prepare(
	"SELECT g.name AS group_name, d.id AS division_id, d.name AS div_name,
	        s.name AS season_name, ph.name AS phase_name
	 FROM   {$p}groups    g
	 JOIN   {$p}phases   ph ON ph.id = g.phase_id
	 JOIN   {$p}divisions d  ON d.id  = ph.division_id
	 JOIN   {$p}seasons   s  ON s.id  = d.season_id
	 WHERE  g.id = %d",
	$match->group_id
) );

$division_id = $info ? (int) $info->division_id : 0;

/* Playoff utakmica (group_id = 0) — liga iz bracketa, da rade postave i breadcrumb */
$po_match = ( ! $info && class_exists( 'Kurs_Playoff' ) ) ? Kurs_Playoff::find_by_match_id( $match_id ) : null;
if ( $po_match ) {
	$division_id = (int) $po_match->division_id;
	$po_div      = Kurs_Season::get_division( $division_id );
}
$events      = Kurs_Match::get_events( $match_id );
/* Sastavi: spremljena postava utakmice (prije završetka = roster lige), bez igrača koji nisu igrali */
$absent      = Kurs_Match::get_absent( $match_id );
$played      = fn( array $rows ) => array_filter( $rows, fn( $r ) => ! in_array( (int) $r->player_id, $absent, true ) );
$home_squad  = $division_id ? $played( Kurs_Match::lineup( $match_id, (int) $match->home_team_id, $division_id ) ) : [];
$away_squad  = $division_id ? $played( Kurs_Match::lineup( $match_id, (int) $match->away_team_id, $division_id ) ) : [];

$home_badge = kurs_team_badge_html( (int) $match->home_team_id, 64 );
$away_badge = kurs_team_badge_html( (int) $match->away_team_id, 64 );

/* Rezultat */
if ( $match->status === 'finished' ) {
	$score = (int) $match->home_score . ' : ' . (int) $match->away_score;
	$status_label = 'Završeno';
	$status_class = 'semafor__status--done';
} else {
	$score = '– : –';
	$status_label = $match->match_date ? date_i18n( 'j.n.Y., H:i', strtotime( $match->match_date ) ) : 'Zakazano';
	$status_class = 'semafor__status--sched';
}

$liga_url = kurs_league_url( $division_id, [ 'tab' => 'raspored' ] );
$po_names = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => 'Utakmica za 3. mjesto' ];
?>

<div class="match-wrap">

	<!-- Breadcrumb: Naslovnica / Liga / N. kolo (→ pregled tog kola) -->
	<div class="match-breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Naslovnica</a> /
		<?php if ( $info ) : ?>
		<a href="<?php echo esc_url( $liga_url ); ?>"><?php echo esc_html( $info->div_name ); ?></a> /
		<?php if ( $match->round ) : ?>
		<a href="<?php echo esc_url( kurs_league_url( $division_id, [ 'tab' => 'kola', 'kolo' => (int) $match->round ] ) . '#kola' ); ?>"><?php echo esc_html( (int) $match->round . '. kolo' ); ?></a>
		<?php endif; ?>
		<?php elseif ( $po_match ) : ?>
		<a href="<?php echo esc_url( $liga_url ); ?>"><?php echo esc_html( $po_div->name ?? 'Liga' ); ?></a> /
		<a href="<?php echo esc_url( kurs_league_url( $division_id, [ 'tab' => 'kola', 'kolo' => 'playoff' ] ) . '#kola' ); ?>"><?php echo esc_html( $po_names[ $po_match->round ] ?? 'Playoff' ); ?></a>
		<?php endif; ?>
	</div>

	<!-- Semafor / scoreboard -->
	<div class="semafor">
		<div class="semafor__stripe"></div>
		<div class="semafor__hatch"></div>
		<div class="semafor__inner">
			<div class="semafor__team">
				<a href="<?php echo esc_url( kurs_team_url( (int) $match->home_team_id, (int) $division_id ) ); ?>"
				   class="semafor__badge" style="text-decoration:none;">
					<?php echo $home_badge; ?>
				</a>
				<div class="semafor__name"><?php echo esc_html( $match->home_name ); ?></div>
			</div>

			<div class="semafor__center">
				<div class="semafor__score"><?php echo esc_html( $score ); ?></div>
				<?php if ( $po_match && ( $pen = Kurs_Playoff::pen_label( $po_match ) ) ) : ?>
				<div class="semafor__pen"><?php echo esc_html( $pen ); ?></div>
				<?php endif; ?>
				<div class="semafor__status <?php echo $status_class; ?>"><?php echo esc_html( $status_label ); ?></div>
				<?php if ( $match->match_date && $match->status === 'finished' ) : ?>
				<div class="semafor__date"><?php echo esc_html( date_i18n( 'j.n.Y.', strtotime( $match->match_date ) ) ); ?></div>
				<?php endif; ?>
			</div>

			<div class="semafor__team">
				<a href="<?php echo esc_url( kurs_team_url( (int) $match->away_team_id, (int) $division_id ) ); ?>"
				   class="semafor__badge" style="text-decoration:none;">
					<?php echo $away_badge; ?>
				</a>
				<div class="semafor__name"><?php echo esc_html( $match->away_name ); ?></div>
			</div>
		</div>
	</div>

	<!-- Body: eventi + postave -->
	<div class="match-body">
		<!-- Tijek utakmice -->
		<div class="match-events">
			<h2 class="match-sec-title">Tijek utakmice</h2>
			<?php
			$tl_events = array_filter( $events, fn( $e ) => ! in_array( $e->event_type, [ 'foul', 'captain', 'absent' ], true ) ); // kapetan/„nije igrao" nisu događaji
			if ( empty( $tl_events ) ) : ?>
			<p class="tl-empty">Nema evidentiranih događaja.</p>
			<?php else :
				$icon_map = [
					'goal'        => 'goal', 'penalty' => 'goal',
					'own_goal'    => 'goal',
					'yellow_card' => 'yellow',
					'second_yellow' => 'red', 'red_card'    => 'red',
				];
				$type_map = [
					'goal'        => 'gol', 'penalty' => 'gol (penal)',
					'own_goal'    => 'gol (vlastita mreža)',
					'yellow_card' => 'žuti karton',
					'second_yellow' => 'drugi žuti (crveni)', 'red_card'    => 'crveni karton',
				];
				$tl_by_half = [ 1 => [], 2 => [] ];
				foreach ( $tl_events as $ev ) { $tl_by_half[ ( (int) $ev->half === 2 ) ? 2 : 1 ][] = $ev; }
			?>
			<div class="kurs-event-timeline">
				<?php foreach ( [ 1 => '1. poluvrijeme', 2 => '2. poluvrijeme' ] as $hh => $hlabel ) :
					if ( empty( $tl_by_half[ $hh ] ) ) continue; ?>
				<h3 class="tl-half-title"><?php echo esc_html( $hlabel ); ?></h3>
				<?php foreach ( $tl_by_half[ $hh ] as $ev ) :
					$icon_cls = 'tl-icon--' . ( $icon_map[ $ev->event_type ] ?? 'goal' );
					$is_home  = (int) $ev->team_id === (int) $match->home_team_id;
					$team_lbl = $is_home ? $match->home_name : $match->away_name;
					$type_lbl = $type_map[ $ev->event_type ] ?? $ev->event_type;
				?>
				<div class="tl-row">
					<span class="tl-min"><?php echo (int) $ev->minute; ?>'</span>
					<span class="tl-icon"><span class="<?php echo esc_attr( $icon_cls ); ?>"></span></span>
					<span class="tl-player"><?php echo esc_html( $ev->player_name ?: '—' ); ?></span>
					<span class="tl-team"><?php echo esc_html( $team_lbl . ' · ' . $type_lbl ); ?></span>
				</div>
				<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<!-- Sastavi -->
		<div class="match-lineups">
			<h2 class="match-sec-title dark">Sastavi</h2>
			<div class="lineup-cols">
				<!-- Domaćin -->
				<div class="lineup-team">
					<div class="lineup-head">
						<div class="lineup-badge">
							<?php echo kurs_team_badge_html( (int) $match->home_team_id, 36 ); ?>
						</div>
						<div class="lineup-team-name"><?php echo esc_html( $match->home_name ); ?></div>
					</div>
					<?php $coach_h = kurs_team_coach( (int) $match->home_team_id ); $rep_h = kurs_team_rep( (int) $match->home_team_id ); ?>
					<?php if ( $coach_h || $rep_h ) : ?>
					<div class="lineup-officials">
						<?php if ( $coach_h ) : ?><div><span class="lineup-officials__role">Trener</span> <?php echo esc_html( $coach_h['name'] ); ?><?php if ( $coach_h['phone'] ) echo ' · ' . esc_html( $coach_h['phone'] ); ?></div><?php endif; ?>
						<?php if ( $rep_h ) : ?><div><span class="lineup-officials__role">Predstavnik</span> <?php echo esc_html( $rep_h['name'] ); ?><?php if ( $rep_h['phone'] ) echo ' · ' . esc_html( $rep_h['phone'] ); ?></div><?php endif; ?>
					</div>
					<?php endif; ?>
					<?php if ( $home_squad ) :
						foreach ( $home_squad as $sq ) : ?>
					<div class="lineup-player">
						<span class="lineup-no"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : '—'; ?></span>
						<a href="<?php echo esc_url( get_permalink( (int) $sq->player_id ) ); ?>"><?php echo esc_html( $sq->player_name ); ?></a>
					</div>
					<?php endforeach; else : ?>
					<p style="font-family:var(--font-mono);font-size:10px;color:var(--c-muted);">Nema prijavljenih igrača.</p>
					<?php endif; ?>
				</div>
				<!-- Gost -->
				<div class="lineup-team">
					<div class="lineup-head">
						<div class="lineup-badge">
							<?php echo kurs_team_badge_html( (int) $match->away_team_id, 36 ); ?>
						</div>
						<div class="lineup-team-name"><?php echo esc_html( $match->away_name ); ?></div>
					</div>
					<?php $coach_a = kurs_team_coach( (int) $match->away_team_id ); $rep_a = kurs_team_rep( (int) $match->away_team_id ); ?>
					<?php if ( $coach_a || $rep_a ) : ?>
					<div class="lineup-officials">
						<?php if ( $coach_a ) : ?><div><span class="lineup-officials__role">Trener</span> <?php echo esc_html( $coach_a['name'] ); ?><?php if ( $coach_a['phone'] ) echo ' · ' . esc_html( $coach_a['phone'] ); ?></div><?php endif; ?>
						<?php if ( $rep_a ) : ?><div><span class="lineup-officials__role">Predstavnik</span> <?php echo esc_html( $rep_a['name'] ); ?><?php if ( $rep_a['phone'] ) echo ' · ' . esc_html( $rep_a['phone'] ); ?></div><?php endif; ?>
					</div>
					<?php endif; ?>
					<?php if ( $away_squad ) :
						foreach ( $away_squad as $sq ) : ?>
					<div class="lineup-player">
						<span class="lineup-no"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : '—'; ?></span>
						<a href="<?php echo esc_url( get_permalink( (int) $sq->player_id ) ); ?>"><?php echo esc_html( $sq->player_name ); ?></a>
					</div>
					<?php endforeach; else : ?>
					<p style="font-family:var(--font-mono);font-size:10px;color:var(--c-muted);">Nema prijavljenih igrača.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

</div>

<?php get_footer(); ?>
