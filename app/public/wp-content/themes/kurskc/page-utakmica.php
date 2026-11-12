<?php
/**
 * Template Name: Centar utakmice
 *
 * URL: /utakmica/?match_id=X  ·  &zapisnik=1 → zapisnik za ispis / PDF (samo završene utakmice, 1.0.54)
 */
if ( ! empty( $_GET['zapisnik'] ) && defined( 'KURS_DIR' ) && class_exists( 'Kurs_Match' ) ) {
	$zm = Kurs_Match::get( absint( $_GET['match_id'] ?? 0 ) );
	if ( $zm && $zm->status === 'finished' ) {
		$_GET['format'] = 'pdf';
		$GLOBALS['kurs_record_public'] = true;
		header( 'X-Robots-Tag: noindex' );
		require KURS_DIR . 'admin/views/match-record.php'; // PDF grana: include + exit
		exit;
	}
}
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
	// Datum i sat kao dva dijela — na mobitelu idu jedan ispod drugog (ne preklapaju dugi naziv ekipe)
	$status_label = $match->match_date ? date_i18n( 'j.n.Y.', strtotime( $match->match_date ) ) . "
" . date_i18n( 'H:i', strtotime( $match->match_date ) ) : 'Zakazano';
	$status_class = 'semafor__status--sched';
}

$liga_url = kurs_league_url( $division_id, [ 'tab' => 'raspored' ] );
$po_names = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => 'Utakmica za 3. mjesto' ];

/* ── Dodatni podaci za centar utakmice (tema 1.0.52) ─────────────────────── */
$hid      = (int) $match->home_team_id;
$aid      = (int) $match->away_team_id;
$finished = $match->status === 'finished';
$ts       = $match->match_date ? strtotime( $match->match_date ) : 0;
$div_obj  = $division_id ? Kurs_Season::get_division( $division_id ) : null;
$sea_obj  = $div_obj ? Kurs_Season::get( (int) $div_obj->season_id ) : null;
$rnd_lbl  = $po_match ? ( $po_names[ $po_match->round ] ?? 'Playoff' ) : ( $match->round ? (int) $match->round . '. kolo' : '' );
$dani     = [ 'nedjelja', 'ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota' ];
$when_day = $ts ? $dani[ (int) date( 'w', $ts ) ] . ', ' . date( 'j. n. Y.', $ts ) : '';
$when_hm  = $ts && date( 'H:i', $ts ) !== '00:00' ? date( 'H:i', $ts ) : '';
$sea_name = $sea_obj->name ?? '';
$div_name = $div_obj->name ?? '';
$kicker   = implode( ' · ', array_filter( [ $sea_name, ( $div_name && stripos( $sea_name, $div_name ) === false ) ? $div_name : '', $rnd_lbl ] ) );
$side_of  = fn( $e ) => ( ( (int) $e->team_id === $hid ) xor ( $e->event_type === 'own_goal' ) ) ? 'home' : 'away'; // autogol ide protivniku
$surname  = function ( string $n ): string { $p = preg_split( '/\s+/', trim( $n ) ); return (string) end( $p ); };

$goal_t  = [ 'goal', 'penalty', 'own_goal' ];
$card_t  = [ 'yellow_card', 'second_yellow', 'red_card' ];
$tl      = array_values( array_filter( $events, fn( $e ) => in_array( $e->event_type, array_merge( $goal_t, $card_t ), true ) ) );
usort( $tl, fn( $a, $b ) => [ (int) ( $a->half ?: 1 ), $a->minute === null ? PHP_INT_MAX : (int) $a->minute, (int) $a->id ]
	<=> [ (int) ( $b->half ?: 1 ), $b->minute === null ? PHP_INT_MAX : (int) $b->minute, (int) $b->id ] );
$has_half = (bool) array_filter( $tl, fn( $e ) => $e->half !== null );
$has_min  = (bool) array_filter( $tl, fn( $e ) => $e->minute !== null );

// Strijelci i isključenja po strani (za semafor), poluvrijeme, statistika
$sc_side = [ 'home' => [], 'away' => [] ];
$red_side = [ 'home' => [], 'away' => [] ];
$stat    = [ 'yellow' => [ 0, 0 ], 'red' => [ 0, 0 ], 'foul1' => [ 0, 0 ], 'foul2' => [ 0, 0 ] ];
$ht      = [ 0, 0 ];
foreach ( $events as $e ) {
	$i = (int) $e->team_id === $hid ? 0 : 1;
	if ( $e->event_type === 'yellow_card' ) $stat['yellow'][ $i ]++;
	if ( in_array( $e->event_type, [ 'red_card', 'second_yellow' ], true ) ) { $stat['red'][ $i ]++; $red_side[ $i ? 'away' : 'home' ][] = $surname( (string) $e->player_name ); }
	if ( $e->event_type === 'foul' ) $stat[ (int) $e->half === 2 ? 'foul2' : 'foul1' ][ $i ]++;
}
foreach ( $tl as $e ) {
	if ( ! in_array( $e->event_type, $goal_t, true ) ) continue;
	$s   = $side_of( $e );
	$key = (int) $e->player_id . ( $e->event_type === 'own_goal' ? 'og' : '' );
	$sc_side[ $s ][ $key ] ??= [ 'name' => $surname( (string) $e->player_name ?: 'nepoznat' ), 'pid' => (int) $e->player_id, 'min' => [], 'og' => $e->event_type === 'own_goal', 'pen' => 0, 'n' => 0 ];
	$sc_side[ $s ][ $key ]['n']++;
	if ( $e->minute !== null ) $sc_side[ $s ][ $key ]['min'][] = (int) $e->minute . "'";
	if ( $e->event_type === 'penalty' ) $sc_side[ $s ][ $key ]['pen']++;
	if ( $has_half && (int) $e->half !== 2 ) $ht[ $s === 'home' ? 0 : 1 ]++;
}
$sc_line = function ( array $list ): string {
	return implode( ', ', array_map( function ( $s ) {
		$tail = $s['min'] ? ' ' . implode( ', ', $s['min'] ) : ( $s['n'] > 1 ? ' ' . $s['n'] : '' );
		$tail .= $s['pen'] ? ' (p)' : '';
		$tail .= $s['og'] ? ' (ag)' : '';
		return kurs_player_link( $s['pid'], $s['name'] ) . esc_html( $tail );
	}, $list ) );
};

// Postave: oznake po igraču (golovi, kartoni) + kapetan
$pl_mark = [];
foreach ( $events as $e ) {
	$pid = (int) $e->player_id;
	if ( ! $pid ) continue;
	if ( in_array( $e->event_type, [ 'goal', 'penalty' ], true ) ) $pl_mark[ $pid ]['g'] = ( $pl_mark[ $pid ]['g'] ?? 0 ) + 1;
	if ( $e->event_type === 'own_goal' ) $pl_mark[ $pid ]['og'] = ( $pl_mark[ $pid ]['og'] ?? 0 ) + 1;
	if ( $e->event_type === 'yellow_card' ) $pl_mark[ $pid ]['y'] = 1;
	if ( in_array( $e->event_type, [ 'red_card', 'second_yellow' ], true ) ) $pl_mark[ $pid ]['r'] = 1;
}
$cap      = [ $hid => Kurs_Match::get_match_captain( $match_id, $hid ), $aid => Kurs_Match::get_match_captain( $match_id, $aid ) ];
$has_nums = (bool) array_filter( array_merge( $home_squad, $away_squad ), fn( $r ) => ! empty( $r->jersey_no ) );

// Ostale utakmice istog kola (sve lige sezone) — večer u dvorani
$others = [];
if ( $sea_obj && ! $po_match && $match->round ) {
	foreach ( Kurs_Season::get_divisions( (int) $sea_obj->id ) as $od ) {
		foreach ( Kurs_Rounds::matches( (int) $od->id, (int) $match->round ) as $om ) { $om->did = (int) $od->id; $others[] = $om; }
	}
	usort( $others, fn( $a, $b ) => [ (string) $a->match_date, (int) $a->id ] <=> [ (string) $b->match_date, (int) $b->id ] );
}
$multi_div = $sea_obj && count( Kurs_Season::get_divisions( (int) $sea_obj->id ) ) > 1;

// Ljestvica skupine (s istaknutim ekipama)
$table = ( ! $po_match && $match->group_id ) ? Kurs_Match::get_standings( (int) $match->group_id ) : [];

// Međusobni susreti (sve sezone)
$h2h = $wpdb->get_results( $wpdb->prepare(
	"SELECT m.id, m.match_date, m.home_team_id, m.away_team_id, m.home_score, m.away_score, h.post_title AS home_name, a.post_title AS away_name
	 FROM {$p}matches m JOIN {$wpdb->posts} h ON h.ID = m.home_team_id JOIN {$wpdb->posts} a ON a.ID = m.away_team_id
	 WHERE m.status = 'finished' AND m.id <> %d AND ( ( m.home_team_id = %d AND m.away_team_id = %d ) OR ( m.home_team_id = %d AND m.away_team_id = %d ) )
	 ORDER BY m.match_date DESC LIMIT 6",
	$match_id, $hid, $aid, $aid, $hid
) ) ?: [];

$refs = array_filter( [ trim( (string) ( $match->referee1 ?? '' ) ), trim( (string) ( $match->referee2 ?? '' ) ) ] );
$match_url = fn( int $id ) => add_query_arg( 'match_id', $id, home_url( '/utakmica/' ) );
?>

<div class="match-wrap mc">

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

	<!-- Semafor -->
	<div class="semafor">
		<div class="semafor__stripe"></div>
		<div class="semafor__hatch"></div>
		<?php if ( $kicker ) : ?>
		<div class="semafor__kicker">
			<?php if ( $division_id && function_exists( 'kurs_league_icon' ) ) echo kurs_league_icon( ( $sea_obj && Kurs_Season::is_pan_league( (string) $sea_obj->league ) ) ? 'Pan Ljetna' : $div_name, 20 ); ?>
			<span><?php echo esc_html( $kicker ); ?></span>
		</div>
		<?php endif; ?>
		<div class="semafor__inner">
			<div class="semafor__team">
				<a href="<?php echo esc_url( kurs_team_url( $hid, (int) $division_id ) ); ?>" class="semafor__badge" style="text-decoration:none;"><?php echo $home_badge; ?></a>
				<div class="semafor__name"><?php echo kurs_team_link( $hid, $match->home_name, (int) $division_id, 'kurs-tlink', true ); // dugi naziv se lomi iza „/” ?></div>
				<?php if ( $sc_side['home'] ) : ?><div class="semafor__scorers"><span class="semafor__ball" aria-hidden="true"></span><?php echo $sc_line( $sc_side['home'] ); ?></div><?php endif; ?>
				<?php if ( $red_side['home'] ) : ?><div class="semafor__reds"><span class="semafor__redcard" aria-hidden="true"></span><?php echo esc_html( implode( ', ', $red_side['home'] ) ); ?></div><?php endif; ?>
			</div>

			<div class="semafor__center">
				<div class="semafor__score"><?php echo esc_html( $score ); ?></div>
				<?php if ( $po_match && ( $pen = Kurs_Playoff::pen_label( $po_match ) ) ) : ?>
				<div class="semafor__pen"><?php echo esc_html( $pen ); ?></div>
				<?php endif; ?>
				<?php if ( $finished && $has_half ) : ?><div class="semafor__ht">Poluvrijeme <?php echo (int) $ht[0]; ?>:<?php echo (int) $ht[1]; ?></div><?php endif; ?>
				<div class="semafor__status <?php echo $status_class; ?>"><?php echo $finished ? 'Završeno' : ( $ts ? 'Najava' : 'Zakazano' ); ?></div>
				<?php if ( $when_day ) : ?>
				<div class="semafor__when"><span><?php echo esc_html( $when_day ); ?></span><?php if ( $when_hm ) : ?><b><?php echo esc_html( $when_hm ); ?></b><?php endif; ?></div>
				<?php endif; ?>
			</div>

			<div class="semafor__team">
				<a href="<?php echo esc_url( kurs_team_url( $aid, (int) $division_id ) ); ?>" class="semafor__badge" style="text-decoration:none;"><?php echo $away_badge; ?></a>
				<div class="semafor__name"><?php echo kurs_team_link( $aid, $match->away_name, (int) $division_id, 'kurs-tlink', true ); ?></div>
				<?php if ( $sc_side['away'] ) : ?><div class="semafor__scorers"><span class="semafor__ball" aria-hidden="true"></span><?php echo $sc_line( $sc_side['away'] ); ?></div><?php endif; ?>
				<?php if ( $red_side['away'] ) : ?><div class="semafor__reds"><span class="semafor__redcard" aria-hidden="true"></span><?php echo esc_html( implode( ', ', $red_side['away'] ) ); ?></div><?php endif; ?>
			</div>
		</div>
	</div>

	<div class="mc-grid">
	<div class="mc-main">

		<!-- Tijek utakmice: domaćin lijevo, gost desno, minuta i rezultat u sredini -->
		<section class="mc-sec">
			<h2 class="match-sec-title">Tijek utakmice</h2>
			<?php if ( ! $tl ) : ?>
			<p class="tl-empty"><?php echo $finished ? 'Za ovu utakmicu nisu evidentirani strijelci ni kartoni.' : 'Utakmica još nije odigrana' . ( $when_day ? ' — početak ' . esc_html( $when_day . ( $when_hm ? ' u ' . $when_hm : '' ) ) : '' ) . '. Tijek će se ovdje pojavljivati uživo dok zapisničar vodi zapisnik.'; ?></p>
			<?php else :
				$run = [ 0, 0 ]; $cur_half = null;
				$lbl = [ 'goal' => 'Gol', 'penalty' => 'Gol (penal)', 'own_goal' => 'Autogol', 'yellow_card' => 'Žuti karton', 'second_yellow' => 'Drugi žuti', 'red_card' => 'Crveni karton' ];
			?>
			<div class="mc-tl">
				<?php foreach ( $tl as $e ) :
					$h = (int) ( $e->half ?: 1 );
					if ( $has_half && $h !== $cur_half ) :
						if ( $cur_half === 1 ) : ?><div class="mc-tl__sep">Poluvrijeme <b><?php echo $run[0]; ?>:<?php echo $run[1]; ?></b></div><?php endif;
						$cur_half = $h; ?>
				<div class="mc-tl__half"><?php echo $h; ?>. poluvrijeme</div>
					<?php endif;
					$is_goal = in_array( $e->event_type, $goal_t, true );
					$s       = $is_goal ? $side_of( $e ) : ( (int) $e->team_id === $hid ? 'home' : 'away' );
					if ( $is_goal ) $run[ $s === 'home' ? 0 : 1 ]++;
					$ico  = $is_goal ? 'goal' : ( $e->event_type === 'yellow_card' ? 'yellow' : 'red' );
					$who  = '<span class="mc-tl__ico mc-tl__ico--' . $ico . '" aria-hidden="true"></span><span class="mc-tl__txt"><b>' . kurs_player_link( (int) $e->player_id, $e->player_name ?: 'nepoznat' ) . '</b><small>' . esc_html( $lbl[ $e->event_type ] ?? '' ) . '</small></span>';
				?>
				<div class="mc-tl__row mc-tl__row--<?php echo $s; ?><?php echo $is_goal ? ' is-goal' : ''; ?>">
					<div class="mc-tl__side mc-tl__side--home"><?php echo $s === 'home' ? $who : ''; ?></div>
					<div class="mc-tl__mid">
						<?php if ( $is_goal && $has_min ) : /* bez minuta pravi redoslijed golova nije poznat — bez međurezultata */ ?><span class="mc-tl__score"><?php echo $run[0]; ?>:<?php echo $run[1]; ?></span><?php endif; ?>
						<?php if ( $e->minute !== null ) : ?><span class="mc-tl__min"><?php echo (int) $e->minute; ?>'</span><?php elseif ( ! $is_goal ) : ?><span class="mc-tl__min">·</span><?php endif; ?>
					</div>
					<div class="mc-tl__side mc-tl__side--away"><?php echo $s === 'away' ? $who : ''; ?></div>
				</div>
				<?php endforeach; ?>
				<?php if ( $finished ) : ?><div class="mc-tl__sep mc-tl__sep--end">Kraj <b><?php echo (int) $match->home_score; ?>:<?php echo (int) $match->away_score; ?></b></div><?php endif; ?>
			</div>
			<?php if ( ! $has_min ) : ?><p class="mc-note">Minute golova za ovu utakmicu nisu evidentirane, pa redoslijed nije nužno stvaran.</p><?php endif; ?>
			<?php endif; ?>
		</section>

		<?php
		$rows = [ [ 'Golovi', $finished ? [ (int) $match->home_score, (int) $match->away_score ] : [ count( array_filter( $tl, fn( $e ) => in_array( $e->event_type, $goal_t, true ) && $side_of( $e ) === 'home' ) ), count( array_filter( $tl, fn( $e ) => in_array( $e->event_type, $goal_t, true ) && $side_of( $e ) === 'away' ) ) ] ],
			[ 'Žuti kartoni', $stat['yellow'] ], [ 'Crveni kartoni', $stat['red'] ],
			[ 'Prekršaji 1. poluvrijeme', $stat['foul1'] ], [ 'Prekršaji 2. poluvrijeme', $stat['foul2'] ] ];
		$rows = array_values( array_filter( $rows, fn( $r, $i ) => $i === 0 || array_sum( $r[1] ) > 0, ARRAY_FILTER_USE_BOTH ) );
		if ( $finished || count( $rows ) > 1 ) : ?>
		<section class="mc-sec">
			<h2 class="match-sec-title">Statistika</h2>
			<div class="mc-stats">
				<div class="mc-stats__hd"><span><?php echo esc_html( $match->home_name ); ?></span><span><?php echo esc_html( $match->away_name ); ?></span></div>
				<?php foreach ( $rows as [ $label, $v ] ) : $tot = max( 1, $v[0] + $v[1] ); ?>
				<div class="mc-stat">
					<div class="mc-stat__nums"><b class="<?php echo $v[0] > $v[1] ? 'is-more' : ''; ?>"><?php echo (int) $v[0]; ?></b><span><?php echo esc_html( $label ); ?></span><b class="<?php echo $v[1] > $v[0] ? 'is-more' : ''; ?>"><?php echo (int) $v[1]; ?></b></div>
					<div class="mc-stat__bar"><i style="width:<?php echo round( 100 * $v[0] / $tot ); ?>%"></i><i style="width:<?php echo round( 100 * $v[1] / $tot ); ?>%"></i></div>
				</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<!-- Sastavi -->
		<section class="mc-sec">
			<h2 class="match-sec-title dark">Sastavi</h2>
			<div class="lineup-cols">
				<?php foreach ( [ [ $hid, $match->home_name, $home_squad ], [ $aid, $match->away_name, $away_squad ] ] as [ $tid, $tname, $squad ] ) :
					$coach = kurs_team_coach( $tid ); $rep = kurs_team_rep( $tid ); ?>
				<div class="lineup-team">
					<div class="lineup-head">
						<div class="lineup-badge"><?php echo kurs_team_badge_html( $tid, 36 ); ?></div>
						<div class="lineup-team-name"><?php echo kurs_team_link( $tid, $tname, (int) $division_id ); ?></div>
					</div>
					<?php if ( $squad ) :
						foreach ( $squad as $sq ) :
							$pid = (int) $sq->player_id; $mk = $pl_mark[ $pid ] ?? []; ?>
					<div class="lineup-player">
						<?php if ( $has_nums ) : ?><span class="lineup-no"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : ''; ?></span><?php endif; ?>
						<span class="lineup-player__name"><?php echo kurs_player_link( $pid, $sq->player_name ); ?><?php if ( function_exists( 'kurs_player_type' ) && kurs_player_type( $pid ) === 'goalkeeper' ) echo ' <em class="lineup-tag" title="Golman">G</em>'; ?><?php if ( $cap[ $tid ] === $pid ) echo ' <em class="lineup-tag lineup-tag--c" title="Kapetan">C</em>'; ?></span>
						<span class="lineup-marks">
							<?php for ( $g = 0; $g < (int) ( $mk['g'] ?? 0 ); $g++ ) echo '<i class="lm lm--goal" title="Gol"></i>'; ?>
							<?php if ( ! empty( $mk['og'] ) ) echo '<i class="lm lm--og" title="Autogol"></i>'; ?>
							<?php if ( ! empty( $mk['y'] ) ) echo '<i class="lm lm--y" title="Žuti karton"></i>'; ?>
							<?php if ( ! empty( $mk['r'] ) ) echo '<i class="lm lm--r" title="Crveni karton"></i>'; ?>
						</span>
					</div>
					<?php endforeach; else : ?>
					<p class="mc-note">Sastav još nije objavljen.</p>
					<?php endif; ?>
					<?php if ( $coach || $rep ) : ?>
					<div class="lineup-officials">
						<?php if ( $coach ) : ?><div><span class="lineup-officials__role">Trener</span> <?php echo esc_html( $coach['name'] ); ?></div><?php endif; ?>
						<?php if ( $rep && ( ! $coach || $rep['name'] !== $coach['name'] ) ) : ?><div><span class="lineup-officials__role">Voditelj</span> <?php echo esc_html( $rep['name'] ); ?></div><?php endif; ?>
					</div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</section>
	</div><!-- /.mc-main -->

	<aside class="mc-side">
		<!-- O utakmici -->
		<section class="mc-card">
			<h3 class="mc-card__title">O utakmici</h3>
			<dl class="mc-facts">
				<?php if ( $sea_name ) : ?><dt>Natjecanje</dt><dd><a href="<?php echo esc_url( kurs_league_url( $division_id ) ); ?>"><?php echo esc_html( trim( $sea_name . ( $div_name && stripos( $sea_name, $div_name ) === false ? ' · ' . $div_name : '' ) ) ); ?></a></dd><?php endif; ?>
				<?php if ( $rnd_lbl ) : ?><dt>Kolo</dt><dd><?php echo esc_html( $rnd_lbl . ( $info && $info->group_name && count( Kurs_Season::get_groups( (int) ( Kurs_Season::group_phase( $division_id )->id ?? 0 ) ) ) > 1 ? ' · ' . $info->group_name : '' ) ); ?></dd><?php endif; ?>
				<?php if ( $when_day ) : ?><dt>Termin</dt><dd><?php echo esc_html( ucfirst( $when_day ) . ( $when_hm ? ' u ' . $when_hm : '' ) ); ?></dd><?php endif; ?>
				<?php if ( $refs ) : ?><dt><?php echo count( $refs ) > 1 ? 'Suci' : 'Sudac'; ?></dt><dd><?php echo esc_html( implode( ', ', $refs ) ); ?></dd><?php endif; ?>
				<dt>Status</dt><dd><?php echo $finished ? 'Završeno' : 'Nije još odigrano'; ?></dd>
			</dl>
			<?php if ( $finished ) : ?>
			<a class="mc-record-btn" href="<?php echo esc_url( add_query_arg( [ 'match_id' => $match_id, 'zapisnik' => 1 ], home_url( '/utakmica/' ) ) ); ?>" target="_blank" rel="noopener nofollow">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z"/></svg>
				Zapisnik utakmice <small>ispis · PDF</small>
			</a>
			<?php endif; ?>
		</section>

		<?php if ( count( $others ) > 1 ) : ?>
		<section class="mc-card">
			<h3 class="mc-card__title"><?php echo $multi_div ? 'Ostale utakmice večeri' : 'Ostale utakmice kola'; ?></h3>
			<div class="mc-others">
				<?php foreach ( $others as $om ) :
					$is_cur = (int) $om->id === $match_id;
					$od     = $om->status === 'finished'; ?>
				<a class="mc-om<?php echo $is_cur ? ' is-current' : ''; ?>" href="<?php echo esc_url( $match_url( (int) $om->id ) ); ?>"<?php echo $is_cur ? ' aria-current="page"' : ''; ?>>
					<span class="mc-om__time"><?php echo $om->match_date && date( 'H:i', strtotime( $om->match_date ) ) !== '00:00' ? esc_html( date( 'H:i', strtotime( $om->match_date ) ) ) : ''; ?><?php if ( $multi_div && function_exists( 'kurs_division_chip' ) ) echo kurs_division_chip( (int) $om->did ); ?></span>
					<span class="mc-om__teams"><span><?php echo esc_html( $om->home_name ); ?></span><span><?php echo esc_html( $om->away_name ); ?></span></span>
					<span class="mc-om__score"><?php if ( $od ) : ?><b><?php echo (int) $om->home_score; ?></b><b><?php echo (int) $om->away_score; ?></b><?php else : ?><em>–</em><?php endif; ?></span>
				</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( $table ) : ?>
		<section class="mc-card">
			<h3 class="mc-card__title">Ljestvica<?php echo $info && $info->group_name && count( Kurs_Season::get_groups( (int) ( Kurs_Season::group_phase( $division_id )->id ?? 0 ) ) ) > 1 ? ' · ' . esc_html( $info->group_name ) : ''; ?></h3>
			<table class="mc-table">
				<thead><tr><th>#</th><th>Klub</th><th>O</th><th>GR</th><th>Bod</th></tr></thead>
				<tbody>
				<?php foreach ( $table as $i => $r ) : $hl = in_array( (int) $r['team_id'], [ $hid, $aid ], true ); ?>
					<tr class="<?php echo $hl ? 'is-hl' : ''; ?>">
						<td><?php echo $i + 1; ?>.</td>
						<td><?php echo kurs_team_link( (int) $r['team_id'], $r['team_name'], (int) $division_id ); ?></td>
						<td><?php echo (int) $r['P']; ?></td>
						<td><?php echo (int) $r['GF']; ?>:<?php echo (int) $r['GA']; ?></td>
						<td><b><?php echo (int) $r['Pts']; ?></b></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<a class="mc-card__more" href="<?php echo esc_url( kurs_league_url( $division_id, [ 'tab' => 'tablica' ] ) . '#liga' ); ?>">Cijela ljestvica &rarr;</a>
		</section>
		<?php endif; ?>

		<section class="mc-card">
			<h3 class="mc-card__title">Međusobni susreti</h3>
			<?php if ( $h2h ) :
				$w = [ $hid => 0, $aid => 0 ]; $dr = 0;
				foreach ( $h2h as $hm ) {
					if ( (int) $hm->home_score === (int) $hm->away_score ) { $dr++; continue; }
					$win = (int) $hm->home_score > (int) $hm->away_score ? (int) $hm->home_team_id : (int) $hm->away_team_id;
					if ( isset( $w[ $win ] ) ) $w[ $win ]++;
				} ?>
			<div class="mc-h2h__sum"><span><b><?php echo $w[ $hid ]; ?></b> pobj.</span><span><b><?php echo $dr; ?></b> nerij.</span><span><b><?php echo $w[ $aid ]; ?></b> pobj.</span></div>
			<div class="mc-others">
				<?php foreach ( $h2h as $hm ) : ?>
				<a class="mc-om" href="<?php echo esc_url( $match_url( (int) $hm->id ) ); ?>">
					<span class="mc-om__time"><?php echo $hm->match_date ? esc_html( date( 'j.n.Y.', strtotime( $hm->match_date ) ) ) : ''; ?></span>
					<span class="mc-om__teams"><span><?php echo esc_html( $hm->home_name ); ?></span><span><?php echo esc_html( $hm->away_name ); ?></span></span>
					<span class="mc-om__score"><b><?php echo (int) $hm->home_score; ?></b><b><?php echo (int) $hm->away_score; ?></b></span>
				</a>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
			<p class="mc-note">Ekipe se dosad nisu sastale<?php echo $finished ? ' prije ove utakmice' : ''; ?>.</p>
			<?php endif; ?>
		</section>
	</aside>
	</div><!-- /.mc-grid -->

</div>

<?php get_footer(); ?>
