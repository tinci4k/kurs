<?php
/**
 * „Iz lige" widget za vijesti (tema 1.0.53) — sadržaj se mijenja s fazom sezone, da je uvijek koristan:
 *
 *   pre    — sezona još nije počela: odbrojavanje do prvog kola + parovi tog dana
 *   today  — danas se igra: današnje utakmice s rezultatima (uživo dok zapisničar unosi)
 *   mid    — sezona traje: sljedeće kolo (datum, odbrojavanje, parovi) + vrh tablice svake lige
 *   post   — sezona gotova, ništa ne slijedi: prvaci (playoff pobjednik ili prvi na ljestvici)
 *
 * Natjecanje = ono čija je utakmica vremenski najbliža (kurskc_active_league). Keš 10 min,
 * ruši se na kurs_data_changed. Zamjenjuje stare „Vrh tablice" + „Iz lige · uživo" kartice.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'kurs_data_changed', function () { delete_transient( 'kurskc_league_widget' ); delete_transient( 'kurskc_league_widget_c' ); } );

/** $compact (1.0.57): najviše 4 utakmice + „još N" — za uski stupac uz izdvojenu vijest na /vijesti/. */
function kurskc_league_widget( bool $compact = false ): string {
	if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Rounds' ) || ! function_exists( 'kurskc_active_league' ) ) return '';
	$key    = $compact ? 'kurskc_league_widget_c' : 'kurskc_league_widget';
	$cached = get_transient( $key );
	if ( is_string( $cached ) ) return $cached;
	$html = kurskc_league_widget_build( $compact );
	set_transient( $key, $html, 10 * MINUTE_IN_SECONDS );
	return $html;
}

/** „subota, 14. 11." + odbrojavanje („danas", „sutra", „za 3 dana"). */
function kurskc_lw_day( string $date, string $today ): array {
	$dani = [ 'nedjelja', 'ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota' ];
	$ts   = strtotime( $date );
	$diff = (int) round( ( strtotime( substr( $date, 0, 10 ) ) - strtotime( $today ) ) / DAY_IN_SECONDS );
	$when = $diff === 0 ? 'danas' : ( $diff === 1 ? 'sutra' : ( $diff > 1 ? 'za ' . $diff . ( $diff % 10 === 1 && $diff % 100 !== 11 ? ' dan' : ' dana' ) : '' ) );
	return [ $dani[ (int) date( 'w', $ts ) ] . ', ' . date( 'j. n.', $ts ), $when ];
}

function kurskc_league_widget_build( bool $compact = false ): string {
	$al = kurskc_active_league();
	if ( ! $al ) return '';
	$season = Kurs_Season::get( (int) $al['season_id'] );
	if ( ! $season ) return '';
	$is_pan = Kurs_Season::is_pan_league( (string) $season->league );
	$divs   = Kurs_Season::get_divisions( (int) $season->id );
	$multi  = count( $divs ) > 1;
	$today  = current_time( 'Y-m-d' );

	$all = [];
	foreach ( $divs as $d ) foreach ( Kurs_Rounds::schedule( (int) $d->id ) as $m ) { $m->did = (int) $d->id; $all[] = $m; }
	usort( $all, fn( $a, $b ) => [ (string) $a->match_date, (int) $a->id ] <=> [ (string) $b->match_date, (int) $b->id ] );
	$done     = array_filter( $all, fn( $m ) => $m->status === 'finished' );
	$todays   = array_values( array_filter( $all, fn( $m ) => $m->match_date && substr( $m->match_date, 0, 10 ) === $today ) );
	$upcoming = array_values( array_filter( $all, fn( $m ) => $m->status !== 'finished' && $m->match_date && substr( $m->match_date, 0, 10 ) > $today ) );

	$state = $todays ? 'today' : ( $upcoming ? ( $done ? 'mid' : 'pre' ) : ( $done ? 'post' : '' ) );
	if ( ! $state ) return '';

	$lg_url  = kurs_league_url( (int) $divs[0]->id );
	$comp    = $is_pan ? 'Pan Ljetna liga' : 'KC liga';
	$chip    = fn( $m ) => $multi && function_exists( 'kurs_division_chip' ) ? kurs_division_chip( (int) $m->did ) : '';
	$m_url   = fn( $m ) => $m->id ? add_query_arg( 'match_id', (int) $m->id, home_url( '/utakmica/' ) ) : $lg_url;
	$row     = function ( $m ) use ( $chip, $m_url ) {
		$fin  = $m->status === 'finished';
		$time = $m->match_date && date( 'H:i', strtotime( $m->match_date ) ) !== '00:00' ? date( 'H:i', strtotime( $m->match_date ) ) : '';
		return '<a class="lw-m' . ( $fin ? ' is-done' : '' ) . '" href="' . esc_url( $m_url( $m ) ) . '">'
			. '<span class="lw-m__t">' . esc_html( $time ) . $chip( $m ) . '</span>'
			. '<span class="lw-m__teams"><span>' . esc_html( $m->home_name ) . '</span><span>' . esc_html( $m->away_name ) . '</span></span>'
			. '<span class="lw-m__sc">' . ( $fin ? '<b>' . (int) $m->home_score . '</b><b>' . (int) $m->away_score . '</b>' : '<em>–</em>' ) . '</span></a>';
	};

	ob_start(); ?>
	<section class="news-widget news-widget--card lw lw--<?php echo esc_attr( $state ); ?>">
	<?php if ( $state === 'today' ) :
		$live = array_filter( $todays, fn( $m ) => $m->status !== 'finished' ); ?>
		<div class="lw__hd"><span class="lw__dot" aria-hidden="true"></span><span><?php echo $live ? 'Danas se igra' : 'Danas odigrano'; ?></span><em><?php echo esc_html( $comp ); ?></em></div>
		<div class="lw__list"><?php foreach ( $compact ? array_slice( $todays, 0, 4 ) : $todays as $m ) echo $row( $m ); ?></div>
		<?php if ( $compact && count( $todays ) > 4 ) : ?><div class="lw__more">+ još <?php echo count( $todays ) - 4; ?> utakmice danas</div><?php endif; ?>
		<a class="lw__cta" href="<?php echo esc_url( kurs_league_url( (int) $divs[0]->id, [ 'tab' => 'kola', 'kolo' => (int) $todays[0]->round, 'division_id' => 0 ] ) . '#kola' ); ?>">Rezultati kola i ljestvice &rarr;</a>

	<?php elseif ( $state === 'pre' || $state === 'mid' ) :
		$nday  = substr( $upcoming[0]->match_date, 0, 10 );
		$nlist = array_values( array_filter( $upcoming, fn( $m ) => substr( (string) $m->match_date, 0, 10 ) === $nday ) );
		[ $dlabel, $dwhen ] = kurskc_lw_day( $upcoming[0]->match_date, $today );
		$rlabel = $upcoming[0]->po_round ? kurs_round_label( 0, $upcoming[0]->po_round ) : ( (int) $upcoming[0]->round . '. kolo' ); ?>
		<div class="lw__hd"><span class="tick tick--red"></span><span><?php echo $state === 'pre' ? 'Početak sezone' : 'Sljedeće kolo'; ?></span><em><?php echo esc_html( $comp ); ?></em></div>
		<div class="lw__next">
			<div class="lw__next-r"><?php echo esc_html( $rlabel ); ?></div>
			<div class="lw__next-d"><?php echo esc_html( $dlabel ); ?><?php if ( $dwhen ) : ?> <b><?php echo esc_html( $dwhen ); ?></b><?php endif; ?></div>
		</div>
		<div class="lw__list"><?php foreach ( array_slice( $nlist, 0, $compact ? 4 : 8 ) as $m ) echo $row( $m ); ?></div>
		<?php if ( $compact && count( $nlist ) > 4 ) : ?><div class="lw__more">+ još <?php echo count( $nlist ) - 4; ?> utakmice tog dana</div><?php endif; ?>
		<?php if ( $state === 'mid' && ! $compact ) :
			foreach ( $divs as $d ) {
				$ph = Kurs_Season::group_phase( (int) $d->id );
				$gs = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
				if ( count( $gs ) !== 1 ) continue; // Pan skupine — vrh tablice nema smisla u 3 retka
				$rows = array_slice( Kurs_Match::get_standings( (int) $gs[0]->id ), 0, 3 );
				if ( ! $rows || ! array_sum( array_map( fn( $r ) => (int) $r['P'], $rows ) ) ) continue; ?>
		<div class="lw__top">
			<div class="lw__top-hd"><?php echo function_exists( 'kurs_division_chip' ) && $multi ? kurs_division_chip( (int) $d->id ) . ' ' : ''; ?>Vrh tablice · <?php echo esc_html( $d->name ); ?></div>
			<?php foreach ( $rows as $i => $r ) : ?>
			<a class="nw-st" href="<?php echo esc_url( kurs_team_url( (int) $r['team_id'], (int) $d->id ) ); ?>">
				<span class="nw-st__rank"><?php echo $i + 1; ?></span>
				<span class="nw-st__crest"><?php echo kurs_team_badge_html( (int) $r['team_id'], 20 ); ?></span>
				<span class="nw-st__name"><?php echo esc_html( $r['team_name'] ); ?></span>
				<span class="nw-st__pts"><?php echo (int) $r['Pts']; ?></span>
			</a>
			<?php endforeach; ?>
		</div>
			<?php }
		endif; ?>
		<a class="lw__cta" href="<?php echo esc_url( kurs_league_url( (int) $divs[0]->id, [ 'tab' => 'raspored', 'division_id' => 0 ] ) . '#liga' ); ?>">Cijeli raspored &rarr;</a>

	<?php else : /* post — prvaci */ ?>
		<div class="lw__hd"><span class="tick tick--gold"></span><span>Prvaci sezone</span><em><?php echo esc_html( $season->name ); ?></em></div>
		<div class="lw__champs">
		<?php foreach ( $divs as $d ) :
			$champ = 0;
			if ( class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( (int) $d->id ) ) {
				$final = Kurs_Playoff::get_bracket( (int) $d->id )['final'] ?? [];
				$f     = $final ? reset( $final ) : null;
				$side  = $f ? Kurs_Playoff::winner_side( $f ) : null;
				if ( $side ) $champ = (int) ( $side === 'home' ? $f->home_team_id : $f->away_team_id );
			} else {
				$ph = Kurs_Season::group_phase( (int) $d->id );
				$gs = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
				if ( count( $gs ) === 1 ) $champ = (int) ( Kurs_Match::get_standings( (int) $gs[0]->id )[0]['team_id'] ?? 0 );
			}
			if ( ! $champ ) continue; ?>
			<a class="lw-champ" href="<?php echo esc_url( kurs_team_url( $champ, (int) $d->id ) ); ?>">
				<span class="lw-champ__cup" aria-hidden="true">🏆</span>
				<?php echo kurs_team_badge_html( $champ, 34 ); ?>
				<span class="lw-champ__txt"><small><?php echo esc_html( $d->name ); ?></small><b><?php echo esc_html( get_the_title( $champ ) ); ?></b></span>
			</a>
		<?php endforeach; ?>
		</div>
		<a class="lw__cta" href="<?php echo esc_url( kurs_league_url( (int) $divs[0]->id, [ 'tab' => 'tablica' ] ) . '#liga' ); ?>">Konačne ljestvice &rarr;</a>
	<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
