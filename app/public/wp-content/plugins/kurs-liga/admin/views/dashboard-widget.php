<?php
defined( 'ABSPATH' ) || exit;

/*
 * Nadzorna ploča WordPressa → widget „KURS — utakmice i zapisnici" (Kurs_Admin::dashboard_widget).
 * Za ljude koji unose rezultate: odmah nakon prijave vide što čeka zapisnik i što se igra sljedeće.
 */
$now      = current_time( 'mysql' );
$today    = substr( $now, 0, 10 );
$tomorrow = date( 'Y-m-d', strtotime( '+1 day', strtotime( $today ) ) );
$overdue  = Kurs_Admin::active_matches( "m.status <> 'finished' AND m.match_date < %s", [ $now ], 'm.match_date', 15 );
$upcoming = Kurs_Admin::active_matches( "m.status <> 'finished' AND m.match_date >= %s", [ $now ], 'm.match_date', 40 );

/* Sljedeća dva dana igranja */
$play_days = [];
foreach ( $upcoming as $m ) {
	$d = substr( $m->match_date, 0, 10 );
	if ( ! isset( $play_days[ $d ] ) && count( $play_days ) === 2 ) break;
	$play_days[ $d ][] = $m;
}
$more = count( $upcoming ) - array_sum( array_map( 'count', $play_days ) );

$wd        = [ 'nedjelja', 'ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota' ];
$day_label = function ( string $d ) use ( $wd, $today, $tomorrow ): string {
	$ts   = strtotime( $d );
	$date = $wd[ (int) date( 'w', $ts ) ] . ', ' . date( 'j. n. Y.', $ts );
	return ( $d === $today ? 'Danas · ' : ( $d === $tomorrow ? 'Sutra · ' : '' ) ) . $date;
};

/* Utakmice aktualne sezone svakog natjecanja (gumbi na dnu) */
$hubs = [];
foreach ( Kurs_Admin::HUBS as $league ) {
	$season = Kurs_Season::current_for_league( Kurs_Season::is_pan_league( $league ) );
	if ( $season ) $hubs[] = [ 'label' => Kurs_Season::competition( $league )['label'] ?? $season->name, 'url' => Kurs_Admin::hub_url( (int) $season->id, 'utakmice' ) ];
}

$row = function ( object $m, bool $late ) {
	?>
	<div class="kurs-dw__match">
		<span class="kurs-dw__time"><?php echo esc_html( $late ? Kurs_Admin::match_when( $m ) : date( 'H:i', strtotime( $m->match_date ) ) ); ?></span>
		<span class="kurs-dw__teams">
			<b><?php echo esc_html( $m->home_name ); ?></b> – <b><?php echo esc_html( $m->away_name ); ?></b>
			<small><?php echo Kurs_Admin::league_tag( $m ); ?> <?php echo esc_html( Kurs_Admin::match_stage( $m ) ); ?></small>
		</span>
		<a class="button <?php echo $late ? 'button-primary' : ''; ?> kurs-dw__btn" href="<?php echo esc_url( Kurs_Admin::record_url( (int) $m->id ) ); ?>"><?php echo $late ? 'Unesi zapisnik' : 'Zapisnik'; ?></a>
	</div>
	<?php
};
?>
<div class="kurs-dw">

	<?php if ( $overdue ) : ?>
	<div class="kurs-dw__sec kurs-dw__sec--warn">
		<h3 class="kurs-dw__h">Čeka rezultat <span class="kurs-dw__count"><?php echo count( $overdue ); ?></span></h3>
		<p class="kurs-dw__hint">Ove utakmice su prošle, a zapisnik još nije završen.</p>
		<?php foreach ( $overdue as $m ) $row( $m, true ); ?>
	</div>
	<?php endif; ?>

	<div class="kurs-dw__sec">
		<h3 class="kurs-dw__h">Nadolazeće utakmice</h3>
		<?php if ( $play_days ) : ?>
			<?php foreach ( $play_days as $d => $ms ) : ?>
			<div class="kurs-dw__day<?php echo $d === $today ? ' is-today' : ''; ?>"><?php echo esc_html( $day_label( $d ) ); ?></div>
			<?php foreach ( $ms as $m ) $row( $m, false ); ?>
			<?php endforeach; ?>
			<?php if ( $more > 0 ) : ?>
			<p class="kurs-dw__hint">Još <?php echo (int) $more; ?> utakmica kasnije — vidi Utakmice natjecanja ispod.</p>
			<?php endif; ?>
		<?php else : ?>
			<p class="kurs-dw__empty">Nema zakazanih utakmica. Kad se upiše raspored, ovdje će biti utakmice s gumbom za zapisnik.</p>
		<?php endif; ?>
	</div>

	<p class="kurs-dw__hint kurs-dw__how">Zapisnik: upiši tko je igrao, golove i kartone, pa na dnu klikni <b>„Završi i spremi rezultat”</b>.</p>

	<div class="kurs-dw__foot">
		<?php foreach ( $hubs as $h ) : ?>
		<a class="button" href="<?php echo esc_url( $h['url'] ); ?>">Utakmice — <?php echo esc_html( $h['label'] ); ?></a>
		<?php endforeach; ?>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga — pregled</a>
	</div>
</div>
