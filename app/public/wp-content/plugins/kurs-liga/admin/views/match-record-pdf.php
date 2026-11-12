<?php
/**
 * Zapisnik za ispis / PDF — uvijek JEDNA A4 stranica (1.26.0). Otvara se s ?format=pdf
 * (admin: kurs-match-record, /zapisnik/); &prazni=1 = prazan zapisnik s popisom igrača za ručno pisanje.
 *
 * Raspored po uzoru na službeni zapisnik: zaglavlje · rezultat (+ poluvrijeme) · dvije tablice ekipa
 * (br., ime, igrao, golovi, Ž, C) s prekršajima po poluvremenu i voditeljem · kretanje rezultata ·
 * opomene i isključenja · suci, zapisničar, napomene. Tablice imaju rezervne retke, a ako sadržaj ipak
 * prijeđe stranicu (npr. 16 igrača i 25 golova), skripta ga razmjerno smanji da stane na A4.
 *
 * Iz match-record.php: $match, $match_id, $info, $events, $home_squad, $away_squad, $is_finished,
 * $disp_home, $disp_away, $is_playoff, $playoff_m, $season_id.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'kurs_pdf_surname' ) ) {
	/** Ime za ćeliju kretanja rezultata: puno ime ako je kratko, inače inicijal + prezime. */
	function kurs_pdf_surname( string $name ): string {
		$name = trim( $name );
		if ( mb_strlen( $name ) <= 14 || ! str_contains( $name, ' ' ) ) return $name;
		$parts = explode( ' ', $name );
		return mb_substr( $parts[0], 0, 1 ) . '. ' . implode( ' ', array_slice( $parts, 1 ) );
	}
}

$blank   = ! empty( $_GET['prazni'] );
$home_id = (int) $match->home_team_id;
$away_id = (int) $match->away_team_id;
$ts      = $match->match_date ? strtotime( $match->match_date ) : 0;

$pdf_season = $season_id ? Kurs_Season::get( $season_id ) : null;
$max_fouls  = $pdf_season ? max( 1, (int) ( $pdf_season->max_fouls_per_half ?? 5 ) ) : 5;
$absent     = $blank ? [] : array_map( 'intval', Kurs_Match::get_absent( $match_id ) );
$fouls      = $blank ? [] : Kurs_Match::get_foul_counts( $match_id );
$captains   = $blank ? [] : [ $home_id => Kurs_Match::get_match_captain( $match_id, $home_id ), $away_id => Kurs_Match::get_match_captain( $match_id, $away_id ) ];
$ev_list    = $blank ? [] : $events;

// Po igraču: golovi (gol + penal), žuti, crveni (drugi žuti = Ž + C)
$pstat = [];
foreach ( $ev_list as $e ) {
	$pid = (int) $e->player_id;
	if ( ! $pid ) continue;
	$pstat[ $pid ] ??= [ 'g' => 0, 'y' => 0, 'r' => 0 ];
	match ( $e->event_type ) {
		'goal', 'penalty' => $pstat[ $pid ]['g']++,
		'yellow_card'     => $pstat[ $pid ]['y']++,
		'second_yellow'   => [ $pstat[ $pid ]['y']++, $pstat[ $pid ]['r']++ ],
		'red_card'        => $pstat[ $pid ]['r']++,
		default           => null,
	};
}

// Kretanje rezultata (poluvrijeme → minuta → redoslijed unosa) i rezultat na poluvremenu
$goals = array_values( array_filter( $ev_list, fn( $e ) => in_array( $e->event_type, [ 'goal', 'penalty', 'own_goal' ], true ) ) );
usort( $goals, fn( $a, $b ) => [ (int) $a->half ?: 1, $a->minute === null ? 999 : (int) $a->minute, (int) $a->id ] <=> [ (int) $b->half ?: 1, $b->minute === null ? 999 : (int) $b->minute, (int) $b->id ] );
$prog = []; $h = 0; $a = 0; $ht = [ 0, 0 ];
foreach ( $goals as $g ) {
	$for_home = ( (int) $g->team_id === $home_id ) !== ( $g->event_type === 'own_goal' );
	$for_home ? $h++ : $a++;
	if ( (int) $g->half !== 2 ) $ht = [ $h, $a ];
	$jersey = '';
	foreach ( (int) $g->team_id === $home_id ? $home_squad : $away_squad as $sq ) {
		if ( (int) $sq->player_id === (int) $g->player_id && $sq->jersey_no ) { $jersey = '#' . (int) $sq->jersey_no . ' '; break; }
	}
	$prog[] = [
		'score' => "$h:$a",
		'who'   => ( $g->minute !== null ? (int) $g->minute . "' " : '' ) . $jersey . kurs_pdf_surname( (string) $g->player_name )
		           . ( $g->event_type === 'penalty' ? ' (P)' : ( $g->event_type === 'own_goal' ? ' (AG)' : '' ) ),
	];
}
$prog_cells = max( $blank ? 20 : 10, (int) ceil( count( $prog ) / 10 ) * 10 ); // prazni: 2 reda za ručno

// Opomene i isključenja
$cards = array_values( array_filter( $ev_list, fn( $e ) => in_array( $e->event_type, [ 'yellow_card', 'second_yellow', 'red_card' ], true ) ) );
usort( $cards, fn( $a, $b ) => [ (int) $a->half ?: 1, $a->minute === null ? 999 : (int) $a->minute, (int) $a->id ] <=> [ (int) $b->half ?: 1, $b->minute === null ? 999 : (int) $b->minute, (int) $b->id ] );
$card_rows  = max( $blank ? 5 : 3, count( $cards ) );
$card_label = [ 'yellow_card' => 'Žuti', 'second_yellow' => '2. žuti → crveni', 'red_card' => 'Crveni' ];

// Retci tablica ekipa: isti broj na obje strane, barem 12 (prazni retci za igrače koji nisu u rosteru)
$squad_rows = max( 12, count( $home_squad ), count( $away_squad ) ) + ( $blank ? 2 : 0 );

$stage = $is_playoff && $playoff_m ? kurs_round_label( null, (string) $playoff_m->round ) : 'Kolo ' . (int) $match->round;
$comp  = $info ? $info->season_name : '';
$sub   = $info ? trim( $info->div_name . ( $info->group_name && $info->group_name !== $info->div_name ? ' · ' . $info->group_name : '' ) ) : '';
if ( ! $info && $is_playoff && $playoff_m ) {
	global $wpdb;
	$di   = $wpdb->get_row( $wpdb->prepare( "SELECT d.name AS div_name, s.name AS season_name FROM {$wpdb->prefix}kurs_divisions d JOIN {$wpdb->prefix}kurs_seasons s ON s.id = d.season_id WHERE d.id = %d", $playoff_m->division_id ) );
	$comp = $di->season_name ?? '';
	$sub  = $di->div_name ?? '';
}
$pens  = $is_playoff && $playoff_m && ! $blank ? Kurs_Playoff::pen_label( $playoff_m ) : '';
$refs  = $blank ? [ '', '' ] : [ (string) ( $match->referee1 ?? '' ), (string) ( $match->referee2 ?? '' ) ];
$blank_url = add_query_arg( 'prazni', 1 );
$full_url  = remove_query_arg( 'prazni' );

$team_block = function ( int $tid, string $name, array $squad, string $role ) use ( $squad_rows, $absent, $pstat, $captains, $fouls, $max_fouls, $blank ) {
	$coach = kurs_team_coach( $tid );
	$rep   = kurs_team_rep( $tid );
	$lead  = $rep['name'] ?? ( $coach['name'] ?? '' );
	?>
	<div class="z-team">
		<h2 class="z-team__h"><span class="z-dot" style="background:<?php echo esc_attr( kurs_team_color( $tid ) ); ?>"></span><?php echo esc_html( $role . ': ' . $name ); ?></h2>
		<table class="z-tbl z-squad">
			<thead><tr><th class="c-no">Br.</th><th>Prezime i ime</th><th class="c-ck">&#10003;</th><th class="c-n">Gol</th><th class="c-n">Ž</th><th class="c-n">C</th></tr></thead>
			<tbody>
			<?php for ( $i = 0; $i < $squad_rows; $i++ ) :
				$sq  = $squad[ $i ] ?? null;
				$pid = $sq ? (int) $sq->player_id : 0;
				$st  = $pstat[ $pid ] ?? [ 'g' => 0, 'y' => 0, 'r' => 0 ];
				$tags = [];
				if ( $sq && kurs_player_type( $pid ) === 'goalkeeper' ) $tags[] = 'GK';
				if ( $pid && ( $captains[ $tid ] ?? 0 ) === $pid ) $tags[] = 'K';
			?>
			<tr<?php echo $sq && ! $blank && in_array( $pid, $absent, true ) ? ' class="is-absent"' : ''; ?>>
				<td class="c-no"><?php echo $sq && $sq->jersey_no ? (int) $sq->jersey_no : ''; ?></td>
				<td class="c-name"><?php if ( $sq ) : ?><?php echo esc_html( $sq->player_name ); ?><?php if ( $tags ) : ?> <small>(<?php echo implode( ', ', $tags ); ?>)</small><?php endif; ?><?php endif; ?></td>
				<td class="c-ck"><?php echo $sq && ! $blank && ! in_array( $pid, $absent, true ) ? '&#10003;' : ''; ?></td>
				<td class="c-n"><?php echo $st['g'] ?: ''; ?></td>
				<td class="c-n"><?php echo $st['y'] ? ( $st['y'] > 1 ? $st['y'] . 'Ž' : 'Ž' ) : ''; ?></td>
				<td class="c-n"><?php echo $st['r'] ? 'C' : ''; ?></td>
			</tr>
			<?php endfor; ?>
			</tbody>
		</table>
		<?php foreach ( [ 1, 2 ] as $hh ) :
			$n = (int) ( $fouls[ $tid ][ $hh ] ?? 0 ); ?>
		<div class="z-fouls">
			<span class="z-fouls__l"><?php echo $hh; ?>. pol. – prekršaji</span>
			<?php for ( $k = 1; $k <= $max_fouls; $k++ ) : ?><span class="z-box<?php echo $k <= $n ? ' is-on' : ''; ?>"><?php echo $k; ?></span><?php endfor; ?>
			<span class="z-box z-box--pen<?php echo $n > $max_fouls ? ' is-on' : ''; ?>"><?php echo $n > $max_fouls ? '10 m × ' . ( $n - $max_fouls ) : '10 m'; ?></span>
		</div>
		<?php endforeach; ?>
		<div class="z-lines">
			<div class="z-line"><span>Voditelj ekipe:</span><b><?php echo esc_html( $lead ); ?></b></div>
			<div class="z-line z-line--sig"><span>potpis</span></div>
		</div>
	</div>
	<?php
};

header( 'Content-Type: text/html; charset=utf-8' );
?><!DOCTYPE html>
<html lang="hr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Zapisnik — <?php echo esc_html( $match->home_name . ' : ' . $match->away_name ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --ink: #111317; --line: #2b2d33; --soft: #e9e9e3; --muted: #6d6f75; --red: #DA0F1E; --orange: #F47B16; --gold: #FDE206; }
html, body { background: #dcdcd4; }
body { font-family: 'Barlow Semi Condensed', 'Arial Narrow', Arial, sans-serif; color: var(--ink); font-size: 10.5pt; line-height: 1.25; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

/* Traka s alatima (ne ispisuje se) */
.z-bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 16px; background: var(--ink); color: #fff; }
.z-bar button, .z-bar a { font: 700 13px/1 'Barlow Semi Condensed', sans-serif; text-transform: uppercase; letter-spacing: .04em; padding: 10px 16px; border-radius: 6px; border: 0; cursor: pointer; text-decoration: none; }
.z-bar .is-primary { background: var(--red); color: #fff; }
.z-bar .is-ghost { background: rgba(255,255,255,.1); color: #fff; }
.z-bar__info { margin-left: auto; font-size: 12px; opacity: .65; }

/* A4 list: 210 × 297 mm, margine 10 mm → sadržaj 190 × 277 mm */
.z-sheet { width: 210mm; min-height: 297mm; margin: 16px auto 32px; padding: 10mm; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.18); }
#z-fit { width: 190mm; overflow: hidden; }
#zapisnik-doc { width: 190mm; transform-origin: top left; }

/* Zaglavlje */
.z-head { display: grid; grid-template-columns: 34mm 1fr 34mm; align-items: center; gap: 4mm; padding-bottom: 2.5mm; border-bottom: 2px solid var(--ink); }
.z-logo { display: flex; align-items: center; gap: 2mm; }
.z-logo__s { display: flex; gap: .8mm; }
.z-logo__s i { display: block; width: 2mm; height: 8mm; transform: skewX(-14deg); }
.z-logo b { font-weight: 800; font-style: italic; font-size: 20pt; letter-spacing: .02em; }
.z-title { text-align: center; }
.z-title__comp { font-weight: 800; font-size: 9pt; letter-spacing: .1em; text-transform: uppercase; color: var(--red); }
.z-title h1 { font-weight: 800; font-size: 17pt; letter-spacing: .03em; text-transform: uppercase; line-height: 1.05; }
.z-title__sub { font-weight: 600; font-size: 9.5pt; }
.z-when { border: 1.5px solid var(--ink); border-radius: 2mm; padding: 1.5mm 2mm; text-align: center; line-height: 1.15; }
.z-when small { display: block; font-size: 7.5pt; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
.z-when b { display: block; font-size: 12pt; font-weight: 800; }

/* Rezultat */
.z-score { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 4mm; margin: 3mm 0 3.5mm; }
.z-score__team { display: flex; align-items: center; gap: 2mm; font-weight: 800; font-size: 13pt; line-height: 1.1; }
.z-score__team--home { justify-content: flex-end; text-align: right; }
.z-score__box { min-width: 44mm; padding: 1mm 5mm 1.2mm; border: 2px solid var(--ink); border-radius: 2.5mm; text-align: center; }
.z-score__nums { display: flex; align-items: center; justify-content: center; gap: 3mm; font-weight: 800; font-size: 24pt; line-height: 1.05; }
.z-score__nums span:not(.z-score__sep) { min-width: 12mm; border-bottom: 1px solid var(--line); }
.z-score__ht { font-size: 8pt; color: var(--muted); }
.z-score__ht b { color: var(--ink); }
.z-dot { display: inline-block; flex: none; width: 3.2mm; height: 3.2mm; border-radius: .8mm; box-shadow: inset 0 0 0 .3mm rgba(0,0,0,.25); margin-right: 1.5mm; vertical-align: -.3mm; }

/* Ekipe */
.z-teams { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }
.z-team__h { display: flex; align-items: center; font-weight: 800; font-size: 10.5pt; text-transform: uppercase; letter-spacing: .02em; padding-bottom: .8mm; margin-bottom: 1mm; border-bottom: 1.5px solid var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.z-tbl { width: 100%; border-collapse: collapse; table-layout: fixed; }
.z-tbl th, .z-tbl td { border: .25mm solid var(--line); padding: 0 1.2mm; height: 5.1mm; font-size: 9.5pt; vertical-align: middle; }
.z-tbl th { height: 4.4mm; background: var(--soft); font-weight: 700; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em; text-align: center; }
.z-squad .c-no { width: 8mm; text-align: center; font-weight: 800; }
.z-squad .c-ck { width: 5.5mm; text-align: center; }
.z-squad .c-n  { width: 7mm; text-align: center; font-weight: 800; }
.z-squad .c-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.z-squad .c-name small { font-size: 7pt; color: var(--muted); }
.z-squad tr.is-absent .c-name { color: #9a9ca2; text-decoration: line-through; }
.z-fouls { display: flex; align-items: center; gap: .9mm; margin-top: 1.3mm; font-size: 8pt; }
.z-fouls__l { width: 24mm; }
.z-box { display: inline-flex; align-items: center; justify-content: center; min-width: 4.6mm; height: 4.6mm; padding: 0 .8mm; border: .25mm solid var(--line); font-size: 7pt; color: var(--muted); }
.z-box.is-on { background: var(--ink); color: #fff; font-weight: 700; }
.z-box--pen { min-width: 9mm; }
.z-box--pen.is-on { background: var(--red); border-color: var(--red); }
.z-lines { display: grid; grid-template-columns: 1fr 26mm; gap: 3mm; margin-top: 2mm; }
.z-line { display: flex; align-items: flex-end; gap: 1.5mm; min-height: 5.5mm; border-bottom: .25mm solid var(--line); font-size: 8pt; }
.z-line > span { flex: none; color: var(--muted); }
.z-line > b { font-weight: 600; font-size: 9.5pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.z-line--sig { justify-content: flex-end; }
.z-line--sig > span { font-size: 6.5pt; }

/* Sekcije */
.z-sec { margin-top: 3.5mm; }
.z-sec__h { font-weight: 800; font-size: 9.5pt; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 1mm; }
.z-prog { display: grid; grid-template-columns: repeat(10, 1fr); border-top: .25mm solid var(--line); border-left: .25mm solid var(--line); }
.z-prog > div { height: 9.5mm; padding: .6mm .5mm; border-right: .25mm solid var(--line); border-bottom: .25mm solid var(--line); text-align: center; overflow: hidden; }
.z-prog b { display: block; font-size: 11pt; font-weight: 800; line-height: 1.1; }
.z-prog small { display: block; font-size: 6.3pt; line-height: 1.1; color: #44464c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.z-cards .c-min { width: 11mm; text-align: center; }
.z-cards .c-team { width: 44mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.z-cards .c-no { width: 9mm; text-align: center; font-weight: 800; }
.z-cards .c-card { width: 32mm; text-align: center; font-weight: 700; }
.z-cards td.c-pl { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.z-officials { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5mm; margin-top: 3.5mm; }
.z-notes { height: 13mm; border: .25mm solid var(--line); }
.z-foot { margin-top: 2.5mm; padding-top: 1.2mm; border-top: .25mm solid #b9b9b2; font-size: 7pt; color: var(--muted); text-align: center; line-height: 1.3; }

@media print {
	@page { size: A4 portrait; margin: 10mm; }
	html, body { background: #fff; }
	.z-bar { display: none !important; }
	.z-sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
}
@media screen and (max-width: 820px) {
	.z-sheet { margin: 0; transform-origin: top left; }
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

<div class="z-bar">
	<button type="button" class="is-primary" onclick="window.print()">&#128424; Ispiši</button>
	<button type="button" class="is-ghost" id="pdf-download">&#11015; Preuzmi PDF</button>
	<?php if ( $blank ) : ?>
	<a class="is-ghost" href="<?php echo esc_url( $full_url ); ?>">Popunjeni zapisnik</a>
	<?php else : ?>
	<a class="is-ghost" href="<?php echo esc_url( $blank_url ); ?>">Prazni zapisnik</a>
	<?php endif; ?>
	<span class="z-bar__info">A4 · uspravno · jedna stranica</span>
</div>

<div class="z-sheet" id="z-sheet">
<div id="z-fit">
<div id="zapisnik-doc">

	<header class="z-head">
		<div class="z-logo">
			<span class="z-logo__s"><i style="background:#DA0F1E"></i><i style="background:#F47B16"></i><i style="background:#FDE206"></i></span>
			<b>KURS</b>
		</div>
		<div class="z-title">
			<div class="z-title__comp"><?php echo esc_html( $comp ); ?></div>
			<h1>Zapisnik utakmice</h1>
			<div class="z-title__sub"><?php echo esc_html( trim( $sub . ' · ' . $stage, ' ·' ) ); ?></div>
		</div>
		<div class="z-when">
			<small>Datum i sat</small>
			<b><?php echo $ts ? esc_html( date( 'j. n. Y.', $ts ) ) : '&nbsp;'; ?></b>
			<?php echo $ts ? esc_html( 'u ' . date( 'H:i', $ts ) ) : ''; ?>
		</div>
	</header>

	<div class="z-score">
		<div class="z-score__team z-score__team--home"><?php echo esc_html( $match->home_name ); ?><span class="z-dot" style="background:<?php echo esc_attr( kurs_team_color( $home_id ) ); ?>;margin:0 0 0 1.5mm"></span></div>
		<div class="z-score__box">
			<div class="z-score__nums"><span><?php echo $blank ? '&nbsp;' : (int) $disp_home; ?></span><span class="z-score__sep">:</span><span><?php echo $blank ? '&nbsp;' : (int) $disp_away; ?></span></div>
			<div class="z-score__ht">Poluvrijeme <b><?php echo $blank ? '___ : ___' : $ht[0] . ' : ' . $ht[1]; ?></b><?php echo $pens ? ' · penali <b>' . esc_html( $pens ) . '</b>' : ''; ?><?php echo ! $blank && ! $is_finished ? ' · <b>u tijeku</b>' : ''; ?></div>
		</div>
		<div class="z-score__team"><span class="z-dot" style="background:<?php echo esc_attr( kurs_team_color( $away_id ) ); ?>"></span><?php echo esc_html( $match->away_name ); ?></div>
	</div>

	<div class="z-teams">
		<?php $team_block( $home_id, $match->home_name, $home_squad, 'Domaćin' ); ?>
		<?php $team_block( $away_id, $match->away_name, $away_squad, 'Gost' ); ?>
	</div>

	<section class="z-sec">
		<h3 class="z-sec__h">Kretanje rezultata</h3>
		<div class="z-prog">
			<?php for ( $i = 0; $i < $prog_cells; $i++ ) : $p = $prog[ $i ] ?? null; ?>
			<div><?php if ( $p ) : ?><b><?php echo esc_html( $p['score'] ); ?></b><small><?php echo esc_html( $p['who'] ); ?></small><?php endif; ?></div>
			<?php endfor; ?>
		</div>
	</section>

	<section class="z-sec">
		<h3 class="z-sec__h">Opomene i isključenja</h3>
		<table class="z-tbl z-cards">
			<thead><tr><th class="c-min">Min.</th><th class="c-team">Ekipa</th><th class="c-no">Br.</th><th>Igrač</th><th class="c-card">Karton</th></tr></thead>
			<tbody>
			<?php for ( $i = 0; $i < $card_rows; $i++ ) :
				$c  = $cards[ $i ] ?? null;
				$no = '';
				if ( $c ) foreach ( (int) $c->team_id === $home_id ? $home_squad : $away_squad as $sq ) {
					if ( (int) $sq->player_id === (int) $c->player_id ) { $no = $sq->jersey_no ? (int) $sq->jersey_no : ''; break; }
				}
			?>
			<tr>
				<td class="c-min"><?php echo $c && $c->minute !== null ? (int) $c->minute . "'" : ( $c ? ( (int) $c->half === 2 ? '2. p.' : '1. p.' ) : '' ); ?></td>
				<td class="c-team"><?php echo $c ? esc_html( (int) $c->team_id === $home_id ? $match->home_name : $match->away_name ) : ''; ?></td>
				<td class="c-no"><?php echo $no; ?></td>
				<td class="c-pl"><?php echo $c ? esc_html( (string) $c->player_name ) : ''; ?></td>
				<td class="c-card"><?php echo $c ? esc_html( $card_label[ $c->event_type ] ?? '' ) : ''; ?></td>
			</tr>
			<?php endfor; ?>
			</tbody>
		</table>
	</section>

	<div class="z-officials">
		<div class="z-line"><span>Sudac:</span><b><?php echo esc_html( $refs[0] ); ?></b></div>
		<div class="z-line"><span>Sudac:</span><b><?php echo esc_html( $refs[1] ); ?></b></div>
		<div class="z-line"><span>Zapisničar:</span><b></b></div>
	</div>

	<section class="z-sec">
		<h3 class="z-sec__h">Napomene</h3>
		<div class="z-notes"></div>
	</section>

	<footer class="z-foot">
		KURS — Koprivnička udruga za rekreativni sport · Akumulirani prekršaj: od <?php echo (int) $max_fouls + 1; ?>. prekršaja u poluvremenu izvodi se s 10 m.
		<?php if ( ! $blank ) : ?>· Ispisano <?php echo esc_html( date_i18n( 'j. n. Y. \u H:i' ) ); ?><?php endif; ?>
	</footer>

</div><!-- /#zapisnik-doc -->
</div>
</div>

<script>
/* Jedna A4 stranica: sadržaj je složen za 190 × 277 mm; ako ga ima više (puno igrača, golova, kartona),
   razmjerno se smanji (scale) da stane — i na ispisu i u preuzetom PDF-u. */
( function () {
	var doc = document.getElementById( 'zapisnik-doc' );
	function mm( n ) { return n * 96 / 25.4; }
	var box = document.getElementById( 'z-fit' );
	function fit() {
		doc.style.transform = ''; doc.style.width = ''; box.style.height = '';
		var h = doc.scrollHeight, max = mm( 277 ) - 2, s = 1;
		if ( h > max ) {
			s = Math.floor( max / h * 1000 ) / 1000;
			doc.style.width = ( 190 / s ) + 'mm';            // šire pa smanji → stane i po širini
			h = doc.scrollHeight;
			s = Math.min( s, Math.floor( max / h * 1000 ) / 1000 );
			doc.style.transform = 'scale(' + s + ')';
			box.style.height = Math.ceil( h * s ) + 'px';
		}
		doc.setAttribute( 'data-fit', String( s ) );
	}
	window.kursFit = fit;
	if ( document.fonts && document.fonts.ready ) document.fonts.ready.then( fit ); else window.addEventListener( 'load', fit );
	fit();
	window.addEventListener( 'beforeprint', fit );

	var btn = document.getElementById( 'pdf-download' );
	btn.addEventListener( 'click', function () {
		if ( typeof html2canvas === 'undefined' || ! window.jspdf ) { alert( 'PDF se ne može izraditi (provjeri internet). Klikni „Ispiši” i odaberi „Spremi kao PDF”.' ); return; }
		var old = btn.textContent; btn.disabled = true; btn.textContent = 'Izrađujem…';
		html2canvas( document.getElementById( 'z-sheet' ), { scale: 2, backgroundColor: '#ffffff', useCORS: true } ).then( function ( canvas ) {
			var pdf = new window.jspdf.jsPDF( 'p', 'mm', 'a4' ), pw = 210, ph = 297;
			var w = pw, h = canvas.height * pw / canvas.width;
			if ( h > ph ) { h = ph; w = canvas.width * ph / canvas.height; } // uvijek jedna stranica
			pdf.addImage( canvas.toDataURL( 'image/jpeg', 0.92 ), 'JPEG', ( pw - w ) / 2, 0, w, h );
			pdf.save( 'zapisnik-<?php echo esc_js( sanitize_title( $match->home_name . '-' . $match->away_name ) ); ?>.pdf' );
		} ).catch( function () {
			alert( 'PDF nije izrađen. Klikni „Ispiši” i odaberi „Spremi kao PDF”.' );
		} ).then( function () { btn.disabled = false; btn.textContent = old; } );
	} );
} )();
</script>
</body>
</html>
