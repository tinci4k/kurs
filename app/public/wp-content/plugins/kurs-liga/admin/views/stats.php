<?php
/**
 * KURS Liga → Statistika (1.30.0) — anonimna statistika posjeta (Kurs_Stats).
 * Ispis (Ctrl+P / gumb) daje izvještaj na jednoj-dvije stranice bez WP sučelja — za sponzore.
 */
defined( 'ABSPATH' ) || exit;

[ $od, $do, $naziv, $key ] = Kurs_Stats::range();
$len     = (int) round( ( strtotime( $do ) - strtotime( $od ) ) / DAY_IN_SECONDS ) + 1;
$p_do    = date( 'Y-m-d', strtotime( $od ) - DAY_IN_SECONDS );
$p_od    = date( 'Y-m-d', strtotime( $od ) - $len * DAY_IN_SECONDS );
$tot     = Kurs_Stats::totals( $od, $do );
$prev    = Kurs_Stats::totals( $p_od, $p_do );
$daily   = Kurs_Stats::daily( $od, $do );
$first   = Kurs_Stats::first_day();
// hrvatski zapis brojeva neovisno o jeziku sučelja (1.260 · 3,0 · 70 %)
$nf      = fn( $n, int $dec = 0 ) => number_format( (float) $n, $dec, ',', '.' );
$pct     = fn( $x ) => number_format( $x * 100, 0, ',', '.' ) . ' %';
$base    = admin_url( 'admin.php?page=kurs-stats' );

/** Promjena prema prethodnom razdoblju jednake duljine. */
$delta = function ( float $now, float $was ) use ( $first, $p_od ) {
	if ( ! $first || $first > $p_od || $was <= 0 ) return '<span class="ks-delta ks-delta--na">nema usporedbe</span>';
	$ch = ( $now - $was ) / $was;
	$cl = round( abs( $ch ) * 100 ) < 1 ? 'eq' : ( $ch > 0 ? 'up' : 'down' );
	return '<span class="ks-delta ks-delta--' . $cl . '">' . ( $cl === 'up' ? '▲ +' : ( $cl === 'down' ? '▼ ' : '≈ ' ) ) . number_format( $ch * 100, 0, ',', '.' ) . ' %</span>';
};

/** Lista s vodoravnim trakama: [ [ naziv(html), vrijednost, opis? ] ]. */
$bars = function ( array $rows, string $unit = '' ) use ( $nf ) {
	if ( ! $rows ) return '<p class="ks-empty">Još nema podataka.</p>';
	$max = max( array_map( fn( $r ) => (int) $r[1], $rows ) ) ?: 1;
	$sum = array_sum( array_map( fn( $r ) => (int) $r[1], $rows ) ) ?: 1;
	$h   = '<ul class="ks-bars">';
	foreach ( $rows as $r ) {
		$h .= '<li><div class="ks-bars__row"><span class="ks-bars__lbl">' . $r[0] . '</span><span class="ks-bars__val">' . $nf( $r[1] ) . $unit . ' <em>' . number_format( $r[1] / $sum * 100, 0, ',', '.' ) . ' %</em></span></div>'
			. '<div class="ks-bars__track"><i style="width:' . max( 1, round( $r[1] / $max * 100 ) ) . '%"></i></div></li>';
	}
	return $h . '</ul>';
};

$dani = [ 1 => 'Ponedjeljak', 'Utorak', 'Srijeda', 'Četvrtak', 'Petak', 'Subota', 'Nedjelja' ];
$lg_name = [ 'kc' => 'KC liga', 'pan' => 'Pan Ljetna liga' ];
?>
<div class="wrap ks">
	<style>
		.ks { --ink:#111317; --muted:#646970; --line:#e2e4e7; --track:#eef0f2; --bar:#111317; --up:#1F7A43; --down:#b32d2e; max-width:1280px; }
		.ks h1 { display:flex; align-items:center; gap:10px; }
		.ks-top { display:flex; flex-wrap:wrap; gap:10px 16px; align-items:center; justify-content:space-between; margin:14px 0 6px; }
		.ks-range { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
		.ks-range a { padding:6px 12px; border:1px solid var(--line); border-radius:16px; background:#fff; text-decoration:none; color:var(--ink); font-weight:500; }
		.ks-range a.is-on { background:var(--ink); color:#fff; border-color:var(--ink); }
		.ks-range form { display:flex; gap:6px; align-items:center; margin-left:6px; }
		.ks-note { color:var(--muted); margin:4px 0 18px; }
		.ks-kpis { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:16px; }
		.ks-kpi { background:#fff; border:1px solid var(--line); border-radius:8px; padding:14px 16px; }
		.ks-kpi b { display:block; font-size:30px; line-height:1.1; font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }
		.ks-kpi span { display:block; color:var(--muted); margin-top:2px; }
		.ks-kpi small { display:block; color:var(--muted); font-size:12px; margin-top:6px; }
		.ks-delta { font-weight:600; font-size:12px; }
		.ks-delta--up { color:var(--up); } .ks-delta--down { color:var(--down); } .ks-delta--eq, .ks-delta--na { color:var(--muted); font-weight:400; }
		.ks-card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px 18px; margin-bottom:16px; break-inside:avoid; }
		.ks-card h2 { margin:0 0 12px; font-size:15px; }
		.ks-card h2 small { font-weight:400; color:var(--muted); margin-left:6px; }
		.ks-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
		.ks-grid .ks-card { margin-bottom:0; }
		.ks-chart { position:relative; }
		.ks-chart svg { display:block; width:100%; height:auto; overflow:visible; }
		.ks-chart .ks-col { fill:var(--bar); }
		.ks-chart .ks-hit { fill:transparent; cursor:default; }
		.ks-chart .ks-hit:hover + .ks-col, .ks-chart g:hover .ks-col { fill:#DA0F1E; }
		.ks-chart .ks-grid-l { stroke:var(--line); stroke-width:1; }
		.ks-chart text { font-size:11px; fill:var(--muted); }
		.ks-tip { position:absolute; pointer-events:none; background:var(--ink); color:#fff; padding:6px 9px; border-radius:6px; font-size:12px; line-height:1.4; white-space:nowrap; transform:translate(-50%, -100%); display:none; z-index:2; }
		.ks-tip b { font-size:13px; }
		.ks-bars { margin:0; }
		.ks-bars li { margin:0 0 9px; }
		.ks-bars__row { display:flex; justify-content:space-between; gap:10px; align-items:baseline; }
		.ks-bars__lbl { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
		.ks-bars__val { font-weight:600; font-variant-numeric:tabular-nums; white-space:nowrap; }
		.ks-bars__val em { font-style:normal; font-weight:400; color:var(--muted); font-size:12px; margin-left:4px; }
		.ks-bars__track { height:6px; border-radius:3px; background:var(--track); margin-top:3px; overflow:hidden; }
		.ks-bars__track i { display:block; height:100%; border-radius:3px; background:var(--bar); }
		.ks-tbl { width:100%; border-collapse:collapse; }
		.ks-tbl th, .ks-tbl td { padding:6px 4px; border-bottom:1px solid var(--track); text-align:left; }
		.ks-tbl th { color:var(--muted); font-weight:500; font-size:12px; }
		.ks-tbl td.n, .ks-tbl th.n { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
		.ks-tbl td a { text-decoration:none; }
		.ks-tbl code { font-size:12px; background:none; padding:0; }
		.ks-empty { color:var(--muted); margin:0; }
		.ks-print-hd { display:none; }
		@media (max-width:960px) { .ks-kpis { grid-template-columns:repeat(2, minmax(0, 1fr)); } .ks-grid { grid-template-columns:1fr; } }
		@media print {
			#adminmenumain, #wpadminbar, #wpfooter, .notice, .ks-noprint, #screen-meta-links { display:none !important; }
			#wpcontent, #wpbody-content { margin:0 !important; padding:0 !important; }
			html.wp-toolbar { padding:0 !important; }
			body { background:#fff !important; }
			.ks { max-width:none; }
			.ks-print-hd { display:flex; justify-content:space-between; align-items:flex-end; border-bottom:3px solid #DA0F1E; padding-bottom:8px; margin-bottom:14px; }
			.ks-card, .ks-kpi { border-color:#ccc; }
			.ks-kpis { grid-template-columns:repeat(4, 1fr); }
			.ks-grid { grid-template-columns:1fr 1fr; }
			.ks-tbl td a { color:var(--ink); }
		}
	</style>

	<div class="ks-print-hd">
		<div><strong style="font-size:20px;">KURS — statistika posjeta kurskc.hr</strong><br><span style="color:#646970;">Koprivnička udruga za rekreativni sport</span></div>
		<div style="text-align:right;"><strong><?php echo esc_html( ucfirst( $naziv ) ); ?></strong><br><span style="color:#646970;"><?php echo esc_html( date_i18n( 'j. n. Y.', strtotime( $od ) ) . ' – ' . date_i18n( 'j. n. Y.', strtotime( $do ) ) ); ?></span></div>
	</div>

	<h1 class="ks-noprint">Statistika posjeta</h1>

	<div class="ks-top ks-noprint">
		<div class="ks-range">
			<?php foreach ( [ '7' => '7 dana', '30' => '30 dana', '90' => '90 dana', 'mjesec' => 'Ovaj mjesec', 'prosli' => 'Prošli mjesec', 'godina' => 'Ova godina' ] as $k => $l ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'raspon', $k, $base ) ); ?>" class="<?php echo $key === (string) $k ? 'is-on' : ''; ?>"><?php echo esc_html( $l ); ?></a>
			<?php endforeach; ?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="kurs-stats"><input type="hidden" name="raspon" value="vlastito">
				<input type="date" name="od" value="<?php echo esc_attr( $od ); ?>" aria-label="Od"> – <input type="date" name="do" value="<?php echo esc_attr( $do ); ?>" aria-label="Do">
				<button class="button<?php echo $key === 'vlastito' ? ' button-primary' : ''; ?>">Prikaži</button>
			</form>
		</div>
		<button type="button" class="button button-primary" onclick="window.print()">Ispiši / PDF izvještaj</button>
	</div>
	<p class="ks-note ks-noprint">
		<strong><?php echo esc_html( ucfirst( $naziv ) ); ?></strong> ·
		<?php echo $first ? 'brojanje od ' . esc_html( date_i18n( 'j. n. Y.', strtotime( $first ) ) ) : 'brojanje još nije zabilježilo nijedan posjet'; ?> ·
		anonimno i bez kolačića; prijavljeni korisnici (urednici, zapisničari) se ne broje.
		<span title="Posjet = jedan posjetitelj u jednom danu. Isti čovjek koji dođe tri dana zaredom = 3 posjeta.">ⓘ Što je posjet?</span>
	</p>

	<div class="ks-kpis">
		<div class="ks-kpi"><b><?php echo $nf( $tot['visits'] ); ?></b><span>Posjeta</span><small><?php echo $delta( $tot['visits'], $prev['visits'] ); ?> · prosjek <?php echo $nf( round( $tot['visits'] / max( 1, $len ) ) ); ?> dnevno</small></div>
		<div class="ks-kpi"><b><?php echo $nf( $tot['views'] ); ?></b><span>Pregleda stranica</span><small><?php echo $delta( $tot['views'], $prev['views'] ); ?></small></div>
		<div class="ks-kpi"><b><?php echo $nf( $tot['ppv'], 1 ); ?></b><span>Stranica po posjetu</span><small><?php echo $delta( $tot['ppv'], $prev['ppv'] ); ?></small></div>
		<div class="ks-kpi"><b><?php echo $tot['visits'] ? $pct( $tot['mob'] ) : '–'; ?></b><span>Posjeta s mobitela</span><small>ostalo računalo i tablet</small></div>
	</div>

	<?php
	/* ── Graf: posjeti po danu (jedna serija; hover = posjeti + pregledi) ── */
	$vals = array_column( $daily, 'visits' );
	$maxv = max( 1, max( $vals ?: [ 0 ] ) );
	$step = $maxv <= 5 ? 1 : ( $maxv <= 20 ? 5 : ( $maxv <= 100 ? 20 : ( $maxv <= 500 ? 100 : ( $maxv <= 2000 ? 500 : 1000 ) ) ) );
	$top  = (int) ceil( $maxv / $step ) * $step;
	$n    = count( $daily );
	$W    = 1000; $H = 220; $L = 40; $B = 24; $T = 8;
	$cw   = ( $W - $L ) / max( 1, $n );
	$bw   = max( 2, min( 34, $cw - 2 ) );
	$every = max( 1, (int) ceil( $n / 12 ) );
	$kr   = [ 'ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub' ];
	?>
	<div class="ks-card">
		<h2>Posjeti po danu <small class="ks-noprint">prijeđite mišem preko stupca za detalje</small></h2>
		<div class="ks-chart" id="ks-chart">
			<svg viewBox="0 0 <?php echo $W; ?> <?php echo $H; ?>" role="img" aria-label="Posjeti po danu, <?php echo esc_attr( $naziv ); ?>">
				<?php for ( $g = 0; $g <= $top; $g += $step ) : $y = $H - $B - ( $g / $top ) * ( $H - $B - $T ); ?>
				<line class="ks-grid-l" x1="<?php echo $L; ?>" x2="<?php echo $W; ?>" y1="<?php echo round( $y, 1 ); ?>" y2="<?php echo round( $y, 1 ); ?>"></line>
				<text x="<?php echo $L - 6; ?>" y="<?php echo round( $y + 4, 1 ); ?>" text-anchor="end"><?php echo $nf( $g ); ?></text>
				<?php endfor; ?>
				<?php $i = 0; foreach ( $daily as $d => $v ) :
					$x  = $L + $i * $cw + ( $cw - $bw ) / 2;
					$h  = ( $v['visits'] / $top ) * ( $H - $B - $T );
					$y  = $H - $B - $h;
					$ts = strtotime( $d );
					$r  = min( 4, $bw / 2, $h ); ?>
				<g data-d="<?php echo esc_attr( $kr[ (int) date( 'w', $ts ) ] . ', ' . date( 'j. n.', $ts ) ); ?>" data-v="<?php echo (int) $v['visits']; ?>" data-p="<?php echo (int) $v['views']; ?>">
					<rect class="ks-hit" x="<?php echo round( $L + $i * $cw, 1 ); ?>" y="<?php echo $T; ?>" width="<?php echo round( $cw, 1 ); ?>" height="<?php echo $H - $B - $T; ?>"></rect>
					<?php if ( $h > 0 ) : // stupac sa zaobljenim vrhom, ravnim dnom ?>
					<path class="ks-col" d="M<?php echo round( $x, 1 ); ?>,<?php echo round( $H - $B, 1 ); ?> V<?php echo round( $y + $r, 1 ); ?> Q<?php echo round( $x, 1 ); ?>,<?php echo round( $y, 1 ); ?> <?php echo round( $x + $r, 1 ); ?>,<?php echo round( $y, 1 ); ?> H<?php echo round( $x + $bw - $r, 1 ); ?> Q<?php echo round( $x + $bw, 1 ); ?>,<?php echo round( $y, 1 ); ?> <?php echo round( $x + $bw, 1 ); ?>,<?php echo round( $y + $r, 1 ); ?> V<?php echo round( $H - $B, 1 ); ?> Z"></path>
					<?php endif; ?>
					<?php if ( $i % $every === 0 ) : ?><text x="<?php echo round( $L + $i * $cw + $cw / 2, 1 ); ?>" y="<?php echo $H - 6; ?>" text-anchor="middle"><?php echo esc_html( date( 'j. n.', $ts ) ); ?></text><?php endif; ?>
				</g>
				<?php $i++; endforeach; ?>
			</svg>
			<div class="ks-tip" id="ks-tip"></div>
		</div>
	</div>
	<script>
	(function(){var c=document.getElementById('ks-chart'),t=document.getElementById('ks-tip');if(!c)return;
	c.querySelectorAll('g[data-d]').forEach(function(g){g.addEventListener('mouseenter',function(){var r=g.getBoundingClientRect(),cr=c.getBoundingClientRect();t.innerHTML='<b>'+g.dataset.d+'</b><br>'+g.dataset.v+' posjeta · '+g.dataset.p+' pregleda';t.style.left=(r.left-cr.left+r.width/2)+'px';t.style.top='0px';t.style.display='block';});g.addEventListener('mouseleave',function(){t.style.display='none';});});})();
	</script>

	<div class="ks-grid">
		<div class="ks-card">
			<h2>Što se gleda <small>pregledi po vrsti stranice</small></h2>
			<?php echo $bars( array_map( fn( $r ) => [
				esc_html( Kurs_Stats::TYPES[ $r->ptype ] ?? $r->ptype ) . ( $r->lg ? ' · <strong>' . esc_html( $lg_name[ $r->lg ] ?? $r->lg ) . '</strong>' : '' ),
				(int) $r->views,
			], Kurs_Stats::by_type( $od, $do ) ) ); ?>
		</div>
		<div class="ks-card">
			<h2>Odakle dolaze <small>posjeti po izvoru</small></h2>
			<?php echo $bars( array_map( fn( $r ) => [ esc_html( Kurs_Stats::SOURCES[ $r->src ] ?? $r->src ), (int) $r->visits ], Kurs_Stats::by_source( $od, $do ) ) ); ?>
			<?php $refs = Kurs_Stats::top_referrers( $od, $do ); if ( $refs ) : ?>
			<p class="ks-note" style="margin:10px 0 4px;">Druge stranice:</p>
			<table class="ks-tbl"><?php foreach ( $refs as $r ) : ?><tr><td><?php echo esc_html( $r->ref ); ?></td><td class="n"><?php echo $nf( $r->visits ); ?></td></tr><?php endforeach; ?></table>
			<?php endif; ?>
		</div>
		<div class="ks-card">
			<h2>Uređaji <small>posjeti</small></h2>
			<?php echo $bars( array_map( fn( $r ) => [ esc_html( Kurs_Stats::DEVICES[ $r->device ] ?? $r->device ), (int) $r->visits ], Kurs_Stats::by_device( $od, $do ) ) ); ?>
		</div>
		<div class="ks-card">
			<h2>Dan u tjednu <small>posjeti — vidi se učinak dana kad se igra</small></h2>
			<?php $wd = Kurs_Stats::by_weekday( $od, $do );
			echo array_sum( $wd ) ? $bars( array_map( fn( $i ) => [ esc_html( $dani[ $i ] ), $wd[ $i ] ], array_keys( $wd ) ) ) : '<p class="ks-empty">Još nema podataka.</p>'; ?>
		</div>
	</div>

	<?php
	$lists = [ 'utakmica' => 'Najgledanije utakmice', 'ekipa' => 'Najgledanije ekipe', 'igrac' => 'Najgledaniji igrači', 'vijest' => 'Najčitanije vijesti' ];
	?>
	<div class="ks-grid" style="margin-top:16px;">
		<?php foreach ( $lists as $pt => $title ) : $rows = Kurs_Stats::top_objects( $pt, $od, $do ); ?>
		<div class="ks-card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( $rows ) : ?>
			<table class="ks-tbl">
				<tr><th>#</th><th></th><th class="n">Pregleda</th><th class="n">Posjeta</th></tr>
				<?php foreach ( $rows as $i => $r ) : ?>
				<tr><td style="width:22px;color:#646970;"><?php echo $i + 1; ?>.</td><td><?php echo $r->url ? '<a href="' . esc_url( $r->url ) . '" target="_blank">' . esc_html( $r->title ) . '</a>' : esc_html( $r->title ); ?></td><td class="n"><?php echo $nf( $r->views ); ?></td><td class="n"><?php echo $nf( $r->visits ); ?></td></tr>
				<?php endforeach; ?>
			</table>
			<?php else : ?><p class="ks-empty">Još nema podataka.</p><?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="ks-card" style="margin-top:16px;">
		<h2>Najposjećenije adrese</h2>
		<?php $paths = Kurs_Stats::top_paths( $od, $do ); if ( $paths ) : ?>
		<table class="ks-tbl">
			<tr><th>Adresa</th><th>Vrsta</th><th class="n">Pregleda</th><th class="n">Posjeta</th></tr>
			<?php foreach ( $paths as $r ) : ?>
			<tr><td><a href="<?php echo esc_url( home_url( $r->path ) ); ?>" target="_blank"><code><?php echo esc_html( $r->path ); ?></code></a></td><td><?php echo esc_html( Kurs_Stats::TYPES[ $r->ptype ] ?? $r->ptype ); ?></td><td class="n"><?php echo $nf( $r->views ); ?></td><td class="n"><?php echo $nf( $r->visits ); ?></td></tr>
			<?php endforeach; ?>
		</table>
		<?php else : ?><p class="ks-empty">Još nema podataka.</p><?php endif; ?>
	</div>

	<p class="ks-note">Posjet = jedan posjetitelj u jednom danu (bez kolačića, pa se isti posjetitelj idući dan broji ponovno). Pregled = svako otvaranje stranice; osvježavanje iste stranice unutar 30 s ne broji se. Botovi i tražilični pauci se ne broje. Usporedba se odnosi na prethodno razdoblje iste duljine (<?php echo esc_html( date_i18n( 'j. n.', strtotime( $p_od ) ) . ' – ' . date_i18n( 'j. n. Y.', strtotime( $p_do ) ) ); ?>).</p>
</div>
