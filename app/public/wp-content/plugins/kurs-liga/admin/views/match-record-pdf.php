<?php
/* Standalone PDF view — rendered when ?format=pdf */
defined( 'ABSPATH' ) || exit;

// $match, $match_id, $info, $events, $ev_home, $ev_away,
// $home_squad, $away_squad, $is_finished, $disp_home, $disp_away
// are available from parent scope (match-record.php)

$ts      = $match->match_date ? strtotime( $match->match_date ) : 0;
$date_hr = $ts ? date( 'j.n.Y', $ts ) : '';
$time_hr = $ts ? date( 'H:i', $ts )   : '';

$home_badge_html = kurs_team_badge_html( (int) $match->home_team_id, 80 );
$away_badge_html = kurs_team_badge_html( (int) $match->away_team_id, 80 );
$home_color      = kurs_team_color( (int) $match->home_team_id );
$away_color      = kurs_team_color( (int) $match->away_team_id );

$font_url = 'https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:ital,wght@0,400;0,700;1,800;1,900&family=Space+Mono&display=swap';

header( 'Content-Type: text/html; charset=utf-8' );
?><!DOCTYPE html>
<html lang="hr">
<head>
<meta charset="UTF-8">
<title>Zapisnik — <?php echo esc_html( $match->home_name ); ?> vs <?php echo esc_html( $match->away_name ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="<?php echo esc_url( $font_url ); ?>" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
	--ka-red:    #DA0F1E;
	--ka-orange: #F47B16;
	--ka-yellow: #FDE206;
	--ka-dark:   #111317;
	--ka-mid:    #1e2229;
	--ka-bg:     #F4F4EE;
	--ka-border: #e0ddd6;
	--font-cond: 'Barlow Semi Condensed', sans-serif;
	--font-mono: 'Space Mono', monospace;
}

body {
	background: var(--ka-bg);
	font-family: var(--font-cond);
	color: var(--ka-dark);
	font-size: 13px;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}

/* ── Stripes ── */
.pdf-stripes {
	display: flex;
	height: 5px;
}
.pdf-stripes span { flex: 1; }

/* ── Hero ── */
.pdf-hero {
	background: var(--ka-dark);
	color: #fff;
	padding: 32px 40px 28px;
}
.pdf-hero__league {
	font-family: var(--font-mono);
	font-size: 9px;
	text-transform: uppercase;
	letter-spacing: .12em;
	color: rgba(255,255,255,.45);
	text-align: center;
	margin-bottom: 24px;
}
.pdf-hero__league-sep { margin: 0 8px; opacity: .4; }

.pdf-hero__body {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 20px;
}
.pdf-hero__team {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	flex: 1;
}
.pdf-hero__team-name {
	font-family: var(--font-cond);
	font-size: 18px;
	font-weight: 900;
	font-style: italic;
	text-transform: uppercase;
	text-align: center;
	line-height: 1.1;
}
.pdf-hero__team-role {
	font-family: var(--font-mono);
	font-size: 8px;
	text-transform: uppercase;
	letter-spacing: .1em;
	color: rgba(255,255,255,.4);
}
.pdf-hero__score {
	text-align: center;
	flex: none;
	width: 190px;   /* fiksna širina → rezultat uvijek horizontalno centriran */
}
.pdf-score-nums {
	font-family: var(--font-cond);
	font-size: 72px;
	font-weight: 900;
	font-style: italic;
	line-height: 1;
	display: grid;
	grid-template-columns: 1fr auto 1fr;
	align-items: center;
}
.pdf-score-nums > span:first-child { text-align: right; }
.pdf-score-nums > span:last-child  { text-align: left; }
.pdf-score-sep {
	color: rgba(255,255,255,.3);
	font-size: 56px;
	padding: 0 10px;
}
.pdf-status {
	display: inline-block;
	margin-top: 8px;
	padding: 3px 10px;
	border-radius: 3px;
	font-family: var(--font-mono);
	font-size: 9px;
	letter-spacing: .1em;
	text-transform: uppercase;
	font-weight: 700;
}
.pdf-status--finished { background: var(--ka-red); color: #fff; }
.pdf-status--live     { background: var(--ka-orange); color: #fff; }
.pdf-meta {
	margin-top: 12px;
	font-family: var(--font-mono);
	font-size: 13px;
	font-weight: 700;
	color: rgba(255,255,255,.82);
	text-align: center;
	letter-spacing: .03em;
	white-space: nowrap;
}
.pdf-meta span { margin: 0 6px; }
.pdf-meta span.pdf-meta-sep { color: rgba(255,255,255,.35); font-weight: 400; }

/* ── Body ── */
.pdf-body {
	padding: 28px 40px;
	display: flex;
	flex-direction: column;
	gap: 24px;
}

/* ── Section ── */
.pdf-section__title {
	font-family: var(--font-cond);
	font-size: 10px;
	font-weight: 700;
	font-style: italic;
	text-transform: uppercase;
	letter-spacing: .1em;
	color: var(--ka-red);
	padding-bottom: 6px;
	border-bottom: 2px solid var(--ka-red);
	margin-bottom: 10px;
}

/* ── Events ── */
.pdf-events { display: flex; flex-direction: column; gap: 3px; }
.pdf-event {
	display: grid;
	grid-template-columns: 36px 24px 1fr 120px;
	gap: 6px;
	align-items: center;
	padding: 5px 8px;
	border-radius: 4px;
	font-size: 12px;
}
.pdf-event--home { background: rgba(30,88,200,.08); border-left: 3px solid #1e58c8; }
.pdf-event--away { background: rgba(218,15,30,.06); border-left: 3px solid var(--ka-red); }
.pdf-event__min  { font-family: var(--font-mono); font-size: 10px; color: rgba(0,0,0,.4); }
.pdf-event__name { font-weight: 700; font-style: italic; }
.pdf-event__team { font-family: var(--font-mono); font-size: 9px; color: rgba(0,0,0,.4); text-align: right; }
.pdf-event__og   { color: var(--ka-red); font-size: 9px; font-style: normal; }
.pdf-half__title {
	font-family: var(--font-mono);
	font-size: 9px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .08em;
	color: rgba(0,0,0,.45);
	margin: 12px 0 6px;
}
.pdf-half__title:first-child { margin-top: 0; }

/* ── Squads ── */
.pdf-squads { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.pdf-squad-table { width: 100%; border-collapse: collapse; }
.pdf-squad-table tr { border-bottom: 1px solid var(--ka-border); }
.pdf-squad-table td { padding: 5px 4px; font-size: 12px; }
.pdf-squad-table td:first-child {
	width: 32px;
	font-family: var(--font-mono);
	font-size: 10px;
	font-weight: 700;
	background: var(--ka-dark);
	color: #fff;
	text-align: center;
	border-radius: 3px;
}
.pdf-squad__head {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 8px;
}
.pdf-squad__name {
	font-size: 13px;
	font-weight: 900;
	font-style: italic;
	text-transform: uppercase;
}
.pdf-squads > div { display: flex; flex-direction: column; }
.pdf-officials {
	order: 9;
	font-family: var(--font-mono);
	font-size: 9px;
	color: rgba(0,0,0,.55);
	line-height: 1.5;
	margin: 8px 0 0;
	padding-top: 7px;
	border-top: 1px dashed var(--ka-border);
}
.pdf-officials strong { color: var(--ka-dark); font-weight: 700; }
.pdf-refs { padding: 0 40px; margin: 0 0 14px; font-family: var(--font-mono); font-size: 11px; color: rgba(0,0,0,.6); }
.pdf-refs__label { font-weight: 700; color: var(--ka-dark); }

/* ── Signatures ── */
.pdf-sigs {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: 20px;
	margin-top: 12px;
}
.pdf-sig__label {
	font-family: var(--font-mono);
	font-size: 9px;
	color: rgba(0,0,0,.4);
	text-transform: uppercase;
	letter-spacing: .08em;
	margin-bottom: 28px;
}
.pdf-sig__line { border-bottom: 1.5px solid var(--ka-dark); }

/* ── Footer ── */
.pdf-footer {
	border-top: 1px solid var(--ka-border);
	padding: 10px 40px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.pdf-footer__brand {
	font-family: var(--font-cond);
	font-size: 11px;
	font-weight: 900;
	font-style: italic;
	text-transform: uppercase;
	color: var(--ka-dark);
	letter-spacing: .05em;
}
.pdf-footer__note {
	font-family: var(--font-mono);
	font-size: 8px;
	color: rgba(0,0,0,.35);
}

/* Alatna traka + A4 pretpregled na ekranu */
.pdf-toolbar { position: sticky; top: 0; z-index: 10; display: flex; gap: 10px; align-items: center; justify-content: center; padding: 12px; background: #111317; }
.pdf-toolbar button { font-family: var(--font-cond); font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; padding: 9px 16px; border-radius: 6px; border: none; cursor: pointer; }
.pdf-tb--primary { background: var(--ka-red); color: #fff; }
.pdf-tb--ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.3); }
.pdf-toolbar button[disabled] { opacity: .5; cursor: default; }
#zapisnik-doc { width: 794px; max-width: 100%; margin: 24px auto; background: #fff; box-shadow: 0 6px 30px rgba(0,0,0,.18); }
@media (max-width: 840px) { #zapisnik-doc { width: 100%; margin: 0; } }

@media print {
	body { background: white; }
	@page { margin: 0; size: A4; }
	.pdf-hero { background: var(--ka-dark) !important; -webkit-print-color-adjust: exact; }
	.pdf-toolbar { display: none !important; }
	#zapisnik-doc { width: auto; margin: 0; box-shadow: none; max-width: none; }
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
window.addEventListener('load', function () {
	var btn = document.getElementById('pdf-download');
	if ( ! btn ) return;
	btn.addEventListener('click', function () {
		var el = document.getElementById('zapisnik-doc');
		if ( ! el || typeof html2canvas === 'undefined' || ! window.jspdf ) {
			alert('PDF biblioteka nije učitana (provjeri internet). Koristi „Ispiši" pa „Spremi kao PDF".');
			return;
		}
		btn.disabled = true; var old = btn.textContent; btn.textContent = 'Generiram…';
		html2canvas(el, { scale: 2, backgroundColor: '#ffffff', useCORS: true }).then(function (canvas) {
			var img  = canvas.toDataURL('image/jpeg', 0.95);
			var pdf  = new window.jspdf.jsPDF('p', 'mm', 'a4');
			var pw   = pdf.internal.pageSize.getWidth();
			var ph   = pdf.internal.pageSize.getHeight();
			var imgH = canvas.height * pw / canvas.width;
			var left = imgH, pos = 0;
			pdf.addImage(img, 'JPEG', 0, pos, pw, imgH);
			left -= ph;
			while ( left > 0 ) { pos = left - imgH; pdf.addPage(); pdf.addImage(img, 'JPEG', 0, pos, pw, imgH); left -= ph; }
			pdf.save('zapisnik-<?php echo (int) $match_id; ?>.pdf');
			btn.disabled = false; btn.textContent = old;
		}).catch(function () {
			alert('Greška pri generiranju PDF-a. Koristi „Ispiši" kao zamjenu.');
			btn.disabled = false; btn.textContent = old;
		});
	});
});
</script>
</head>
<body>

<div class="pdf-toolbar no-print">
	<button type="button" id="pdf-download" class="pdf-tb--primary">&#11015; Preuzmi PDF</button>
	<button type="button" onclick="window.print()" class="pdf-tb--ghost">&#128424; Ispiši</button>
	<button type="button" onclick="window.close()" class="pdf-tb--ghost">Zatvori</button>
</div>

<div id="zapisnik-doc">

<!-- Stripes -->
<div class="pdf-stripes">
	<span style="background:#DA0F1E;"></span>
	<span style="background:#F47B16;"></span>
	<span style="background:#FDE206;"></span>
</div>

<!-- Hero -->
<div class="pdf-hero">
	<?php if ( $info ) : ?>
	<div class="pdf-hero__league">
		<?php echo esc_html( $info->season_name ); ?>
		<span class="pdf-hero__league-sep">/</span>
		<?php echo esc_html( $info->div_name ); ?>
		<span class="pdf-hero__league-sep">/</span>
		<?php echo esc_html( $info->group_name ); ?>
	</div>
	<?php endif; ?>

	<div class="pdf-hero__body">
		<div class="pdf-hero__team">
			<?php echo kurs_team_badge_html( (int) $match->home_team_id, 80 ); ?>
			<div class="pdf-hero__team-name"><?php echo esc_html( $match->home_name ); ?></div>
			<div class="pdf-hero__team-role">Domaćin</div>
		</div>

		<div class="pdf-hero__score">
			<div class="pdf-score-nums">
				<span><?php echo $disp_home; ?></span>
				<span class="pdf-score-sep">:</span>
				<span><?php echo $disp_away; ?></span>
			</div>
			<div class="pdf-status <?php echo $is_finished ? 'pdf-status--finished' : 'pdf-status--live'; ?>">
				<?php echo $is_finished ? '✓ Završeno' : '● U tijeku'; ?>
			</div>
			<?php if ( $date_hr ) : ?>
			<div class="pdf-meta">
				<span><?php echo esc_html( $date_hr ); ?></span>
				<span class="pdf-meta-sep">·</span>
				<span><?php echo esc_html( $time_hr ); ?></span>
				<span class="pdf-meta-sep">·</span>
				<span>Kolo <?php echo (int) $match->round; ?></span>
			</div>
			<?php endif; ?>
		</div>

		<div class="pdf-hero__team">
			<?php echo kurs_team_badge_html( (int) $match->away_team_id, 80 ); ?>
			<div class="pdf-hero__team-name"><?php echo esc_html( $match->away_name ); ?></div>
			<div class="pdf-hero__team-role">Gost</div>
		</div>
	</div>
</div>

<div class="pdf-body">

	<?php
	// Golovi i kartoni — razdvojeni po vrsti i poluvremenu (prekršaji se ne prikazuju)
	$pdf_icons  = [ 'goal' => '⚽', 'penalty' => '⚽', 'own_goal' => '⚽', 'yellow_card' => '🟨', 'second_yellow' => '🟨🟥', 'red_card' => '🟥' ];
	$pdf_tags   = [ 'penalty' => '(P)', 'own_goal' => '(AG)', 'second_yellow' => '(2Ž)' ];
	$goal_types = [ 'goal', 'penalty', 'own_goal' ];
	$card_types = [ 'yellow_card', 'second_yellow', 'red_card' ];
	$pdf_goals  = array_filter( $events, fn( $e ) => in_array( $e->event_type, $goal_types, true ) );
	$pdf_cards  = array_filter( $events, fn( $e ) => in_array( $e->event_type, $card_types, true ) );

	$render_half_events = function ( $list ) use ( $match, $pdf_icons, $pdf_tags ) {
		$by_half = [ 1 => [], 2 => [] ];
		foreach ( $list as $e ) { $by_half[ ( (int) $e->half === 2 ) ? 2 : 1 ][] = $e; }
		foreach ( [ 1 => '1. poluvrijeme', 2 => '2. poluvrijeme' ] as $hh => $hlabel ) {
			if ( empty( $by_half[ $hh ] ) ) continue;
			echo '<div class="pdf-half__title">' . $hlabel . '</div>';
			echo '<div class="pdf-events">';
			foreach ( $by_half[ $hh ] as $ev ) {
				$is_home = (int) $ev->team_id === (int) $match->home_team_id;
				$icon    = $pdf_icons[ $ev->event_type ] ?? '⚽';
				$tag     = $pdf_tags[ $ev->event_type ] ?? '';
				?>
				<div class="pdf-event <?php echo $is_home ? 'pdf-event--home' : 'pdf-event--away'; ?>">
					<span class="pdf-event__min"><?php echo $ev->minute !== null ? (int) $ev->minute . "'" : '–'; ?></span>
					<span><?php echo $icon; ?></span>
					<span class="pdf-event__name">
						<?php echo esc_html( $ev->player_name ?: '—' ); ?>
						<?php if ( $tag ) : ?><span class="pdf-event__og"><?php echo $tag; ?></span><?php endif; ?>
					</span>
					<span class="pdf-event__team"><?php echo esc_html( $is_home ? $match->home_name : $match->away_name ); ?></span>
				</div>
				<?php
			}
			echo '</div>';
		}
	};
	?>

	<!-- Golovi -->
	<div>
		<div class="pdf-section__title">Golovi</div>
		<?php if ( empty( $pdf_goals ) ) : ?>
		<p style="color:rgba(0,0,0,.4);font-style:italic;font-size:12px;">Nema evidentiranih golova.</p>
		<?php else : $render_half_events( $pdf_goals ); endif; ?>
	</div>

	<!-- Kartoni -->
	<div>
		<div class="pdf-section__title">Kartoni</div>
		<?php if ( empty( $pdf_cards ) ) : ?>
		<p style="color:rgba(0,0,0,.4);font-style:italic;font-size:12px;">Nema evidentiranih kartona.</p>
		<?php else : $render_half_events( $pdf_cards ); endif; ?>
	</div>

	<!-- Postave -->
	<div>
		<div class="pdf-section__title">Postave</div>
		<div class="pdf-squads">
			<div>
				<div class="pdf-squad__head">
					<?php echo kurs_team_badge_html( (int) $match->home_team_id, 22 ); ?>
					<span class="pdf-squad__name"><?php echo esc_html( $match->home_name ); ?></span>
				</div>
				<?php $coach_h = kurs_team_coach( (int) $match->home_team_id ); $rep_h = kurs_team_rep( (int) $match->home_team_id ); ?>
				<?php if ( $coach_h || $rep_h ) : ?>
				<div class="pdf-officials">
					<?php if ( $coach_h ) : ?>Trener: <strong><?php echo esc_html( $coach_h['name'] ); ?></strong><?php if ( $coach_h['phone'] ) echo ' · ' . esc_html( $coach_h['phone'] ); ?><br><?php endif; ?>
					<?php if ( $rep_h ) : ?>Predstavnik: <strong><?php echo esc_html( $rep_h['name'] ); ?></strong><?php if ( $rep_h['phone'] ) echo ' · ' . esc_html( $rep_h['phone'] ); ?><?php endif; ?>
				</div>
				<?php endif; ?>
				<?php if ( empty( $home_squad ) ) : ?>
				<p style="color:rgba(0,0,0,.4);font-size:11px;font-style:italic;">Nema prijavljenih igrača.</p>
				<?php else : ?>
				<table class="pdf-squad-table">
					<?php foreach ( $home_squad as $sq ) : ?>
					<tr>
						<td><?php echo $sq->jersey_no ? (int) $sq->jersey_no : '—'; ?></td>
						<td><?php echo esc_html( $sq->player_name ); ?></td>
					</tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>
			</div>
			<div>
				<div class="pdf-squad__head">
					<?php echo kurs_team_badge_html( (int) $match->away_team_id, 22 ); ?>
					<span class="pdf-squad__name"><?php echo esc_html( $match->away_name ); ?></span>
				</div>
				<?php $coach_a = kurs_team_coach( (int) $match->away_team_id ); $rep_a = kurs_team_rep( (int) $match->away_team_id ); ?>
				<?php if ( $coach_a || $rep_a ) : ?>
				<div class="pdf-officials">
					<?php if ( $coach_a ) : ?>Trener: <strong><?php echo esc_html( $coach_a['name'] ); ?></strong><?php if ( $coach_a['phone'] ) echo ' · ' . esc_html( $coach_a['phone'] ); ?><br><?php endif; ?>
					<?php if ( $rep_a ) : ?>Predstavnik: <strong><?php echo esc_html( $rep_a['name'] ); ?></strong><?php if ( $rep_a['phone'] ) echo ' · ' . esc_html( $rep_a['phone'] ); ?><?php endif; ?>
				</div>
				<?php endif; ?>
				<?php if ( empty( $away_squad ) ) : ?>
				<p style="color:rgba(0,0,0,.4);font-size:11px;font-style:italic;">Nema prijavljenih igrača.</p>
				<?php else : ?>
				<table class="pdf-squad-table">
					<?php foreach ( $away_squad as $sq ) : ?>
					<tr>
						<td><?php echo $sq->jersey_no ? (int) $sq->jersey_no : '—'; ?></td>
						<td><?php echo esc_html( $sq->player_name ); ?></td>
					</tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Suci -->
	<?php $pdf_refs = array_filter( [ $match->referee1 ?? '', $match->referee2 ?? '' ] ); ?>
	<?php if ( $pdf_refs ) : ?>
	<div class="pdf-refs"><span class="pdf-refs__label">Suci:</span> <?php echo esc_html( implode( ' · ', $pdf_refs ) ); ?></div>
	<?php endif; ?>

	<!-- Potpisi -->
	<div class="pdf-sigs">
		<div>
			<div class="pdf-sig__label">Potpis domaćin</div>
			<div class="pdf-sig__line"></div>
		</div>
		<div>
			<div class="pdf-sig__label">Sudac</div>
			<div class="pdf-sig__line"></div>
		</div>
		<div>
			<div class="pdf-sig__label">Potpis gost</div>
			<div class="pdf-sig__line"></div>
		</div>
	</div>

</div>

<div class="pdf-footer">
	<div class="pdf-footer__brand">KURS Liga · Koprivnica</div>
	<div class="pdf-footer__note">Generirano <?php echo date_i18n( 'j.n.Y. \u H:i' ); ?></div>
</div>

</div><!-- /#zapisnik-doc -->

</body>
</html>
