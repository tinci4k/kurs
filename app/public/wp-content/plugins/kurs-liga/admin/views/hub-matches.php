<?php
defined( 'ABSPATH' ) || exit;

/*
 * Stranica natjecanja → kartica „Utakmice": odabir lige / skupine / playoffa,
 * raspored po kolima (zapisnik, datum i kolo, brisanje) + ljestvica; playoff bracket.
 * Varijable iz season-hub.php: $season, $season_id, $divisions, $accent, $is_locked, $hub_url.
 */

$group_id       = absint( $_GET['group_id']    ?? 0 );
$playoff_div_id = absint( $_GET['playoff_div'] ?? 0 );

/* Skupine sezone (po ligama) + lige koje imaju playoff */
$all_groups   = [];
$playoff_divs = [];
foreach ( $divisions as $div ) {
	$ph     = Kurs_Season::group_phase( (int) $div->id );
	$groups = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
	foreach ( $groups as $g ) {
		$g->div_name = $div->name;
		$g->div_id   = (int) $div->id;
		$g->label    = count( $groups ) === 1 ? $div->name : $g->name; // KC: skupina = liga
		$all_groups[] = $g;
	}
	if ( $groups && ! empty( $div->has_playoff ) ) $playoff_divs[] = $div;
}

/* Odabir mora pripadati ovoj sezoni; zadano prva liga/skupina */
if ( $group_id && ! in_array( $group_id, array_map( fn( $g ) => (int) $g->id, $all_groups ), true ) ) $group_id = 0;
if ( $playoff_div_id && ! in_array( $playoff_div_id, array_map( fn( $d ) => (int) $d->id, $playoff_divs ), true ) ) $playoff_div_id = 0;
if ( ! $group_id && ! $playoff_div_id && $all_groups ) $group_id = (int) $all_groups[0]->id;

$current_group = null;
foreach ( $all_groups as $g ) {
	if ( (int) $g->id === $group_id ) { $current_group = $g; break; }
}
$view      = $playoff_div_id ? 'playoff' : 'group';
$bar_color = $accent;
?>

<?php if ( isset( $_GET['generated_season'] ) ) :
	$gen = absint( $_GET['generated_season'] ); ?>
<div class="notice notice-success is-dismissible"><p>
	<?php echo $gen
		? sprintf( 'Raspored je generiran — %d novih utakmica. Datume upiši preko ✎ uz svaku utakmicu.', $gen )
		: 'Sve utakmice već postoje — ništa novo nije dodano.'; ?>
</p></div>
<?php endif; ?>

<?php if ( count( $all_groups ) + count( $playoff_divs ) > 1 ) : ?>
<!-- ── Liga / skupina / playoff ──────────────────────────────────────────── -->
<nav class="kurs-hub-sub" aria-label="Liga">
	<?php foreach ( $all_groups as $g ) : ?>
	<a href="<?php echo esc_url( $hub_url( 'utakmice', [ 'group_id' => (int) $g->id ] ) ); ?>"
	   class="<?php echo $view === 'group' && $group_id === (int) $g->id ? 'is-active' : ''; ?>"><?php echo esc_html( $g->label ); ?></a>
	<?php endforeach; ?>
	<?php foreach ( $playoff_divs as $div ) : ?>
	<a href="<?php echo esc_url( $hub_url( 'utakmice', [ 'playoff_div' => (int) $div->id ] ) ); ?>"
	   class="kurs-hub-sub__po<?php echo $view === 'playoff' && $playoff_div_id === (int) $div->id ? ' is-active' : ''; ?>">&#127942; Playoff<?php echo count( $playoff_divs ) > 1 ? ' · ' . esc_html( $div->name ) : ''; ?></a>
	<?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if ( $view === 'playoff' ) :
	/* ════════════════════════════════════════════════════════════════════
	   PLAYOFF PRIKAZ
	   ════════════════════════════════════════════════════════════════════ */
	$bracket     = Kurs_Playoff::get_bracket( $playoff_div_id );
	$has_bracket = ! empty( $bracket );
	$all_teams   = get_posts( [ 'post_type' => 'kurs_ekipa', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );

	$round_labels = [
		'qf'    => 'Četvrtfinale',
		'sf'    => 'Polufinale',
		'final' => 'Finale',
		'3rd'   => 'Ut. za 3. mjesto',
	];
	$round_order = [ 'qf', 'sf', 'final', '3rd' ];

?>

	<!-- Playoff akcije -->
	<?php if ( ! $is_locked ) : ?>
	<div style="display:flex;gap:10px;align-items:center;margin:14px 0 10px;flex-wrap:wrap;">
		<?php if ( ! $has_bracket ) :
			global $wpdb;
			$open_group = (int) $wpdb->get_var( $wpdb->prepare( // neodigrane utakmice skupina → ljestvice još nisu konačne
				"SELECT COUNT(*) FROM {$wpdb->prefix}kurs_matches m JOIN {$wpdb->prefix}kurs_groups g ON g.id = m.group_id
				 JOIN {$wpdb->prefix}kurs_phases ph ON ph.id = g.phase_id WHERE ph.division_id = %d AND m.status <> 'finished'", $playoff_div_id ) );
			$gen_msg = $open_group
				? sprintf( 'U skupinama još ima %d neodigranih utakmica — ljestvice nisu konačne pa ni parovi playoffa. Svejedno generirati bracket?', $open_group )
				: 'Generirati playoff bracket iz grupnih ljestvica?';
		?>
		<?php if ( $open_group ) : ?><span class="kurs-muted" style="font-size:12px;">&#9888; Neodigrano u skupinama: <?php echo $open_group; ?></span><?php endif; ?>
		<form method="post" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( $gen_msg ) ); ?>);">
			<?php wp_nonce_field('kurs_generate_playoff'); ?>
			<input type="hidden" name="kurs_action"  value="generate_playoff">
			<input type="hidden" name="division_id"  value="<?php echo $playoff_div_id; ?>">
			<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
			<button type="submit" class="kurs-btn kurs-btn--red">&#9654; Generiraj bracket</button>
		</form>
		<?php else : ?>
		<form method="post" onsubmit="return confirm('Obrisati cijeli bracket?\n\nBrišu se i svi playoff rezultati i zapisnici (strijelci, kartoni). Radnja se ne može poništiti!');">
			<?php wp_nonce_field('kurs_reset_playoff'); ?>
			<input type="hidden" name="kurs_action"  value="reset_playoff">
			<input type="hidden" name="division_id"  value="<?php echo $playoff_div_id; ?>">
			<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
			<button type="submit" class="kurs-btn kurs-btn--outline kurs-btn--sm">&#8635; Resetiraj bracket</button>
		</form>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( isset($_GET['generated']) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Playoff bracket generiran iz grupnih ljestvica.</div>
	<?php elseif ( isset($_GET['reset']) ) : ?>
	<div class="kurs-notice">Bracket je resetiran.</div>
	<?php elseif ( ( $_GET['pm_error'] ?? '' ) === 'draw' ) : ?>
	<div class="kurs-notice kurs-notice--err">Utakmica nije zaključena: neriješeno je, a u playoffu mora biti pobjednik — upiši i rezultat penala.</div>
	<?php elseif ( ( $_GET['pm_error'] ?? '' ) === 'teams' ) : ?>
	<div class="kurs-notice kurs-notice--err">Ekipe nisu promijenjene: zapisnik te utakmice već ima golove ili kartone (termin se sprema normalno).</div>
	<?php endif; ?>

	<?php if ( ! $has_bracket ) : ?>
	<div class="kurs-empty-state">
		<div class="kurs-empty-state__icon">&#127942;</div>
		<div class="kurs-empty-state__title">Bracket nije generiran</div>
		<div class="kurs-empty-state__sub">Klikni "Generiraj bracket" da se playoff automatski popuni iz grupnih ljestvica.</div>
	</div>
	<?php else : ?>

	<div class="kurs-bracket">
		<?php foreach ( $round_order as $round ) :
			if ( empty( $bracket[$round] ) ) continue;
			$matches = $bracket[$round];
			ksort( $matches );
		?>
		<div class="kurs-bracket__round">
			<div class="kurs-bracket__round-label"><?php echo esc_html( $round_labels[$round] ?? ucfirst($round) ); ?></div>
			<div class="kurs-bracket__matches">
				<?php foreach ( $matches as $slot => $m ) :
					$finished  = $m->status === 'finished';
					$home_name = $m->home_name ?: $m->home_label ?: '?';
					$away_name = $m->away_name ?: $m->away_label ?: '?';
					$side      = Kurs_Playoff::winner_side( $m );
					$home_wins = $side === 'home';
					$away_wins = $side === 'away';
					$pen       = Kurs_Playoff::pen_label( $m );
				?>
				<div class="kurs-bracket__match <?php echo $finished ? 'is-finished' : ''; ?>" id="pm-<?php echo $m->id; ?>">

					<div class="kurs-bracket__team <?php echo $home_wins ? 'is-winner' : ''; ?>">
						<div class="kurs-bracket__team-badge">
							<?php echo $m->home_team_id ? kurs_team_badge_html( (int)$m->home_team_id, 22 ) : '<div class="kurs-bracket__tbd">?</div>'; ?>
						</div>
						<span class="kurs-bracket__team-name"><?php echo esc_html( $home_name ); ?></span>
						<?php if ( $finished ) : ?>
						<span class="kurs-bracket__score <?php echo $home_wins ? 'is-winner' : ''; ?>"><?php echo (int)$m->home_score; ?></span>
						<?php endif; ?>
					</div>

					<div class="kurs-bracket__team <?php echo $away_wins ? 'is-winner' : ''; ?>">
						<div class="kurs-bracket__team-badge">
							<?php echo $m->away_team_id ? kurs_team_badge_html( (int)$m->away_team_id, 22 ) : '<div class="kurs-bracket__tbd">?</div>'; ?>
						</div>
						<span class="kurs-bracket__team-name"><?php echo esc_html( $away_name ); ?></span>
						<?php if ( $finished ) : ?>
						<span class="kurs-bracket__score <?php echo $away_wins ? 'is-winner' : ''; ?>"><?php echo (int)$m->away_score; ?></span>
						<?php endif; ?>
					</div>

					<div class="kurs-bracket__meta">
						<?php if ( $m->match_date ) : ?>
						<span><?php echo esc_html( date( 'j.n. H:i', strtotime( $m->match_date ) ) ); ?></span>
						<?php else : ?>
						<span class="kurs-muted">Termin naknadno</span>
						<?php endif; ?>
						<?php if ( $pen ) : ?><span class="kurs-bracket__pen"><?php echo esc_html( $pen ); ?></span><?php endif; ?>
						<span class="kurs-bracket__meta-label"><?php echo esc_html( $round_labels[$round] ?? '' ); ?> #<?php echo $slot; ?></span>
					</div>

					<div class="kurs-bracket__actions no-print">
						<?php
						if ( $m->home_team_id && $m->away_team_id ) {
							$mid = Kurs_Playoff::ensure_match_record( (int)$m->id );
							if ( $mid ) {
								$record_url = admin_url( "admin.php?page=kurs-match-record&match_id={$mid}" );
								echo '<a href="' . esc_url( $record_url ) . '" class="kurs-btn kurs-btn--outline kurs-btn--xs">&#128196; Zapisnik</a>';
							}
						}
						?>
						<?php if ( $is_locked ) : ?>
						<?php elseif ( $finished ) : ?>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field('kurs_reopen_playoff_match'); ?>
							<input type="hidden" name="kurs_action"       value="reopen_playoff_match">
							<input type="hidden" name="playoff_match_id"  value="<?php echo $m->id; ?>">
							<input type="hidden" name="division_id"       value="<?php echo $playoff_div_id; ?>">
							<input type="hidden" name="season_id"         value="<?php echo $season_id; ?>">
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--xs">&#9998; Otvori za ispravak</button>
						</form>
						<?php else : ?>
						<button class="kurs-btn kurs-btn--outline kurs-btn--xs pm-edit-btn"
						        data-id="<?php echo $m->id; ?>">&#9998; Uredi</button>
						<?php endif; ?>
					</div>

					<?php if ( ! $finished && ! $is_locked ) : ?>
					<div class="kurs-pm-edit-panel" id="pm-edit-<?php echo $m->id; ?>" style="display:none;">

						<form method="post" class="kurs-pm-score-form">
							<?php wp_nonce_field('kurs_finish_playoff_match'); ?>
							<input type="hidden" name="kurs_action"       value="finish_playoff_match">
							<input type="hidden" name="playoff_match_id"  value="<?php echo $m->id; ?>">
							<input type="hidden" name="division_id"       value="<?php echo $playoff_div_id; ?>">
							<input type="hidden" name="season_id"         value="<?php echo $season_id; ?>">
							<div class="kurs-pm-score-row">
								<label class="kurs-field-label" style="font-size:9px;"><?php echo esc_html( $home_name ); ?></label>
								<input type="number" name="home_score" class="kurs-si kurs-pm-si" min="0" max="99" required placeholder="0">
								<span class="kurs-finish-score__sep">:</span>
								<input type="number" name="away_score" class="kurs-si kurs-pm-si" min="0" max="99" required placeholder="0">
								<label class="kurs-field-label" style="font-size:9px;"><?php echo esc_html( $away_name ); ?></label>
							</div>
							<div class="kurs-pm-score-row kurs-pm-pen-row" title="Upiši samo ako je utakmica završila neriješeno">
								<span class="kurs-field-label" style="font-size:9px;">Penali</span>
								<input type="number" name="home_pen" class="kurs-si kurs-pm-si" min="0" max="99" placeholder="–">
								<span class="kurs-finish-score__sep">:</span>
								<input type="number" name="away_pen" class="kurs-si kurs-pm-si" min="0" max="99" placeholder="–">
								<span class="kurs-field-label" style="font-size:9px;">samo kod neriješenog</span>
							</div>
							<button type="submit" class="kurs-btn kurs-btn--red kurs-btn--sm" style="width:100%;margin-top:6px;">&#10003; Završi utakmicu</button>
						</form>

						<hr style="border:none;border-top:1px solid var(--ka-border);margin:10px 0;">

						<form method="post">
							<?php wp_nonce_field('kurs_update_playoff_match'); ?>
							<input type="hidden" name="kurs_action"       value="update_playoff_match">
							<input type="hidden" name="playoff_match_id"  value="<?php echo $m->id; ?>">
							<input type="hidden" name="division_id"       value="<?php echo $playoff_div_id; ?>">
							<input type="hidden" name="season_id"         value="<?php echo $season_id; ?>">

							<div class="kurs-field-wrap" style="margin-bottom:6px;">
								<span class="kurs-field-label">Domaćin</span>
								<select name="home_team_id" class="kurs-sel" style="width:100%;">
									<option value="">— Odaberi ekipu —</option>
									<?php foreach ( $all_teams as $t ) : ?>
									<option value="<?php echo $t->ID; ?>" <?php selected( $m->home_team_id, $t->ID ); ?>>
										<?php echo esc_html( $t->post_title ); ?>
									</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="kurs-field-wrap" style="margin-bottom:6px;">
								<span class="kurs-field-label">Gost</span>
								<select name="away_team_id" class="kurs-sel" style="width:100%;">
									<option value="">— Odaberi ekipu —</option>
									<?php foreach ( $all_teams as $t ) : ?>
									<option value="<?php echo $t->ID; ?>" <?php selected( $m->away_team_id, $t->ID ); ?>>
										<?php echo esc_html( $t->post_title ); ?>
									</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="kurs-field-wrap" style="margin-bottom:6px;">
								<span class="kurs-field-label">Datum i sat</span>
								<input type="text" name="match_date" class="kurs-datepicker kurs-field-input"
								       value="<?php echo $m->match_date ? esc_attr( date( 'd.m.Y H:i', strtotime($m->match_date) ) ) : ''; ?>"
								       placeholder="dd.mm.yyyy hh:mm" autocomplete="off">
							</div>
							<button type="submit" class="kurs-btn kurs-btn--dark kurs-btn--sm" style="width:100%;">Spremi izmjene</button>
						</form>
					</div>
					<?php endif; ?>

				</div><!-- /.kurs-bracket__match -->
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div><!-- /.kurs-bracket -->

	<?php endif; /* has_bracket */ ?>

<?php else :
	/* ════════════════════════════════════════════════════════════════════
	   GRUPNI PRIKAZ (utakmice + tablica)
	   ════════════════════════════════════════════════════════════════════ */
	if ( ! $group_id ) : ?>
	<div class="kurs-empty-state">
		<p>Nema postavljenih grupa u ovoj sezoni.</p>
		<?php if ( $season_id ) : ?>
		<a href="<?php echo esc_url( $hub_url( 'ekipe' ) ); ?>"
		   class="kurs-btn kurs-btn--red" style="margin-top:14px;">Uvrsti ekipe u lige &rarr;</a>
		<?php endif; ?>
	</div>

	<?php else :
		global $wpdb;
		$matches       = Kurs_Match::get_by_group( $group_id );
		$standings     = Kurs_Match::get_standings( $group_id );
		$group_teams   = Kurs_Season::get_group_teams( $group_id );
		$grp_data      = $wpdb->get_row( $wpdb->prepare(
			'SELECT playoff_spots FROM ' . $wpdb->prefix . KURS_PREFIX . 'groups WHERE id = %d',
			$group_id
		) );
		$playoff_spots = $grp_data ? (int) $grp_data->playoff_spots : 2;
	?>

	<div class="kurs-matches-layout">

		<!-- ══════════════════════════════════════════════════════════════════
		     LJESTVICA
		     ══════════════════════════════════════════════════════════════════ -->
		<div class="kurs-panel">
			<div class="kurs-panel__head">
				<h3 class="kurs-panel__title">
					Ljestvica
					<?php if ( $current_group ) : ?>
					<span style="font-weight:400;font-style:normal;font-size:.75em;color:var(--ka-muted);margin-left:8px;">
						<?php echo esc_html( $current_group->label === $current_group->div_name ? $current_group->div_name : $current_group->div_name . ' / ' . $current_group->name ); ?>
					</span>
					<?php endif; ?>
				</h3>
				<?php if ( $standings ) : ?>
				<span style="font-family:var(--font-mono);font-size:9px;color:var(--ka-muted);text-transform:uppercase;letter-spacing:.04em;">
					<?php echo count( $standings ); ?> ekipa
				</span>
				<?php endif; ?>
			</div>

			<div class="kurs-panel__body" style="padding:0 18px 14px;">
				<?php if ( empty( $standings ) ) : ?>
				<p class="kurs-muted" style="padding:20px 0;">
					Nema završenih utakmica.
					<?php if ( empty( $group_teams ) ) : ?>
					<a href="<?php echo esc_url( $hub_url( 'ekipe' ) ); ?>">
						Uvrsti ekipe u skupinu &rarr;
					</a>
					<?php endif; ?>
				</p>
				<?php else : ?>

				<table class="kurs-standings" style="margin-top:8px;">
					<thead>
						<tr>
							<th class="kurs-standings__pos">#</th>
							<th class="kurs-standings__name" style="text-align:left;">Ekipa</th>
							<th title="Odigrano">U</th>
							<th title="Pobjede">P</th>
							<th title="Remiji">R</th>
							<th title="Porazi">Pr</th>
							<th title="Gol razlika">GR</th>
							<th class="kurs-standings__pts" title="Bodovi">Bod</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $standings as $i => $r ) :
						$is_top        = $i < $playoff_spots;
						$is_cutoff     = $i === $playoff_spots - 1;
						$team_thumb    = get_the_post_thumbnail_url( (int) $r['team_id'], 'thumbnail' );
						$abbr          = mb_strtoupper( mb_substr( $r['team_name'], 0, 2 ) );
						$row_class     = [];
						if ( $is_top )    $row_class[] = 'kurs-standings__top';
						if ( $is_cutoff ) $row_class[] = 'standings-playoff-cutoff';
					?>
					<tr <?php echo $row_class ? 'class="' . implode( ' ', $row_class ) . '"' : ''; ?>>
						<td class="kurs-standings__pos">
							<span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;<?php echo $is_top ? 'background:' . $bar_color . ';color:#fff;font-family:var(--font-mono);font-size:10px;font-weight:700;' : 'color:var(--ka-muted);font-family:var(--font-mono);font-size:11px;'; ?>" title="<?php echo $is_top ? 'Prolazi u playoff' : ''; ?>">
								<?php echo $i + 1; ?>
							</span>
						</td>
						<td class="kurs-standings__name">
							<div style="display:flex;align-items:center;gap:8px;">
								<div style="width:28px;height:28px;border-radius:50%;overflow:hidden;flex-shrink:0;background:var(--ka-dark);display:flex;align-items:center;justify-content:center;">
									<?php if ( $team_thumb ) : ?>
									<img src="<?php echo esc_url( $team_thumb ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
									<?php else : ?>
									<span style="font-family:var(--font-mono);font-size:9px;color:#fff;font-weight:700;"><?php echo esc_html( $abbr ); ?></span>
									<?php endif; ?>
								</div>
								<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'kurs-teams', 'action' => 'edit', 'id' => (int) $r['team_id'], 'division_id' => (int) $current_group->div_id ], admin_url( 'admin.php' ) ) ); ?>"
								   style="font-family:var(--font-cond);font-weight:700;font-size:14px;text-decoration:none;color:var(--ka-dark);">
									<?php echo esc_html( $r['team_name'] ); ?>
								</a>
							</div>
						</td>
						<td style="font-family:var(--font-mono);font-size:11px;"><?php echo (int) $r['P']; ?></td>
						<td style="font-family:var(--font-mono);font-size:11px;color:var(--ka-green);font-weight:700;"><?php echo (int) $r['W']; ?></td>
						<td style="font-family:var(--font-mono);font-size:11px;color:var(--ka-muted);"><?php echo (int) $r['D']; ?></td>
						<td style="font-family:var(--font-mono);font-size:11px;color:var(--ka-red);"><?php echo (int) $r['L']; ?></td>
						<td style="font-family:var(--font-mono);font-size:11px;<?php echo (int) $r['GD'] >= 0 ? 'color:var(--ka-green);' : 'color:var(--ka-red);'; ?>" title="Gol razlika: <?php echo ( (int) $r['GD'] >= 0 ? '+' : '' ) . (int) $r['GD']; ?>">
							<?php echo (int) $r['GF'] . ':' . (int) $r['GA']; ?>
						</td>
						<td class="kurs-standings__pts">
							<span style="font-family:var(--font-cond);font-weight:900;font-style:italic;font-size:20px;color:<?php echo $is_top ? $bar_color : 'var(--ka-dark)'; ?>;">
								<?php echo (int) $r['Pts']; ?>
							</span>
						</td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="kurs-muted" style="margin-top:10px;font-family:var(--font-mono);font-size:9px;text-transform:uppercase;letter-spacing:.04em;">
					Pobjeda = 3 boda &bull; Remi = 1 bod &bull; Poraz = 0 bodova
				</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- ══════════════════════════════════════════════════════════════════
		     UTAKMICE
		     ══════════════════════════════════════════════════════════════════ -->
		<div class="kurs-panel">

			<div class="kurs-panel__head kurs-sched-head">
				<h3 class="kurs-panel__title">Raspored</h3>
				<?php if ( count( $group_teams ) >= 2 && ! $is_locked ) : ?>
				<div class="kurs-sched-head__actions">
					<label class="kurs-btn kurs-btn--outline kurs-btn--sm" for="kurs-add-manual-chk" style="cursor:pointer;">
						+ Dodaj ručno
					</label>
					<button class="kurs-btn kurs-btn--dark kurs-btn--sm kurs-btn-gen-fixtures"
					        data-group-id="<?php echo $group_id; ?>">
						&#8635; Generiraj sve
					</button>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( count( $group_teams ) >= 2 && ! $is_locked ) : ?>
			<input type="checkbox" id="kurs-add-manual-chk" class="kurs-add-manual-chk" style="display:none;">
			<div class="kurs-add-match-drop">
				<form method="post">
					<?php wp_nonce_field( 'kurs_add_match' ); ?>
					<input type="hidden" name="kurs_action" value="add_match">
					<input type="hidden" name="group_id"   value="<?php echo $group_id; ?>">
					<input type="hidden" name="season_id"  value="<?php echo $season_id; ?>">
					<div class="kurs-add-match-grid">
						<div class="kurs-add-match-grid__row">
							<select name="home_team" required class="kurs-sel">
								<option value="">Domaćin</option>
								<?php foreach ( $group_teams as $t ) : ?>
								<option value="<?php echo $t->team_id; ?>"><?php echo esc_html( $t->team_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="kurs-vs-sep">vs</span>
							<select name="away_team" required class="kurs-sel">
								<option value="">Gost</option>
								<?php foreach ( $group_teams as $t ) : ?>
								<option value="<?php echo $t->team_id; ?>"><?php echo esc_html( $t->team_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="kurs-add-match-grid__row">
							<label class="kurs-field-wrap">
								<span class="kurs-field-label">Kolo</span>
								<input type="number" name="round" value="1" min="1" class="kurs-field-input kurs-field-input--xs">
							</label>
							<label class="kurs-field-wrap">
								<span class="kurs-field-label">Datum i sat</span>
								<input type="text" name="match_date" class="kurs-datepicker kurs-field-input"
								       placeholder="dd.mm.yyyy hh:mm" autocomplete="off">
							</label>
							<button type="submit" class="kurs-btn kurs-btn--red">Dodaj utakmicu</button>
						</div>
					</div>
				</form>
			</div>
			<?php endif; ?>

			<div id="gen-fixtures-msg" class="kurs-notice" style="display:none;margin:0;border-radius:0;border:none;border-bottom:1px solid var(--ka-border);"></div>

			<?php if ( empty( $group_teams ) ) : ?>
			<div class="kurs-panel__empty">
				<a href="<?php echo esc_url( $hub_url( 'ekipe' ) ); ?>">
					Uvrsti ekipe u skupinu &rarr;
				</a>
			</div>
			<?php endif; ?>

			<div class="kurs-sched-body">
			<?php if ( ! empty( $group_teams ) && empty( $matches ) ) : ?>
			<p class="kurs-muted" style="padding:20px 18px;">Nema utakmica. Klikni "Generiraj sve" ili dodaj ručno.</p>
			<?php elseif ( ! empty( $matches ) ) :
				/* Ekipe koje igraju više puta u istom kolu (krivo upisano kolo) — kvari pregled po kolima */
				$round_dupes = [];
				$seen        = [];
				foreach ( $matches as $m ) {
					foreach ( [ $m->home_team_id => $m->home_name, $m->away_team_id => $m->away_name ] as $tid => $tname ) {
						$key = (int) $m->round . '_' . (int) $tid;
						if ( isset( $seen[ $key ] ) ) $round_dupes[ (int) $m->round ][ (int) $tid ] = $tname;
						$seen[ $key ] = true;
					}
				}
				$cur_round = null;
				foreach ( $matches as $m ) :
					$is_new_round = ( (int) $m->round !== $cur_round );
					if ( $is_new_round ) {
						if ( $cur_round !== null ) echo '</div>';
						$cur_round = (int) $m->round;
						echo '<div class="kurs-round-block">';
						echo '<div class="kurs-round-hd"><span class="kurs-round-hd__label">Kolo ' . $cur_round . '</span>';
						if ( ! empty( $round_dupes[ $cur_round ] ) ) {
							echo '<span class="kurs-round-warn" title="Provjeri kolo uz te utakmice (✎)">&#9888; Više puta u ovom kolu: '
								. esc_html( implode( ', ', $round_dupes[ $cur_round ] ) ) . '</span>';
						}
						echo '</div>';
					}
			?>
				<div class="kurs-match kurs-match--<?php echo esc_attr( $m->status ); ?>" id="match-<?php echo (int) $m->id; ?>">

					<div class="kurs-match__row">
						<div class="kurs-match__dt" id="match-date-<?php echo (int) $m->id; ?>">
							<?php if ( $m->match_date ) : ?>
							<span class="kurs-match__dt-day"><?php echo esc_html( date( 'j.n.', strtotime( $m->match_date ) ) ); ?></span>
							<span class="kurs-match__dt-time"><?php echo esc_html( date( 'H:i', strtotime( $m->match_date ) ) ); ?></span>
							<?php else : ?>
							<span class="kurs-match__dt-none">—</span>
							<?php endif; ?>
						</div>

						<div class="kurs-match__teams">
							<span class="kurs-match__home"><?php echo esc_html( $m->home_name ); ?></span>

							<?php if ( $m->status === 'finished' ) : ?>
							<span class="kurs-score"><?php echo (int) $m->home_score; ?> : <?php echo (int) $m->away_score; ?></span>
							<?php else : ?>
							<span class="kurs-score kurs-score--sched">
								<?php echo $m->match_date ? esc_html( date_i18n( 'H:i', strtotime( $m->match_date ) ) ) : 'vs'; ?>
							</span>
							<?php endif; ?>

							<span class="kurs-match__away"><?php echo esc_html( $m->away_name ); ?></span>
						</div>

						<div class="kurs-match__actions">
							<a href="<?php echo admin_url( 'admin.php?page=kurs-match-record&match_id=' . (int) $m->id ); ?>"
							   class="kurs-btn kurs-btn--outline kurs-btn--sm" title="Zapisnik">
								Zapisnik
							</a>
							<?php if ( ! $is_locked ) : ?>
							<button class="kurs-btn kurs-btn--ghost kurs-btn--sm kurs-btn-edit-match"
							        data-match-id="<?php echo (int) $m->id; ?>"
							        data-date="<?php echo esc_attr( $m->match_date ?? '' ); ?>"
							        data-round="<?php echo (int) $m->round; ?>"
							        title="Uredi datum/kolo">
								&#9998;
							</button>
							<form method="post" class="kurs-del-match-form"
							      onsubmit="return confirm('Obriši ovu utakmicu?');">
								<?php wp_nonce_field( 'kurs_delete_match' ); ?>
								<input type="hidden" name="kurs_action" value="delete_match">
								<input type="hidden" name="match_id"    value="<?php echo $m->id; ?>">
								<input type="hidden" name="season_id"   value="<?php echo $season_id; ?>">
								<button type="submit" class="kurs-del-btn" title="Obriši">&times;</button>
							</form>
							<?php endif; ?>
						</div>
					</div>

					<div class="kurs-match-edit-form" id="match-edit-<?php echo (int) $m->id; ?>" style="display:none;">
						<div class="kurs-match-edit-inner">
							<label class="kurs-field-wrap">
								<span class="kurs-field-label">Kolo</span>
								<input type="number" class="small-text medit-round kurs-field-input kurs-field-input--xs"
								       value="<?php echo (int) $m->round; ?>" min="1" max="99">
							</label>
							<label class="kurs-field-wrap">
								<span class="kurs-field-label">Datum i sat</span>
								<input type="text" class="medit-date kurs-datepicker kurs-field-input"
								       value="<?php echo $m->match_date ? esc_attr( date( 'd.m.Y H:i', strtotime( $m->match_date ) ) ) : ''; ?>"
								       placeholder="dd.mm.yyyy hh:mm" autocomplete="off">
							</label>
							<button class="kurs-btn kurs-btn--red kurs-btn--sm medit-save"
							        data-match-id="<?php echo (int) $m->id; ?>">Spremi</button>
							<button class="kurs-btn kurs-btn--ghost kurs-btn--sm medit-cancel"
							        data-match-id="<?php echo (int) $m->id; ?>">Odustani</button>
						</div>
					</div>
				</div>
			<?php endforeach;
				if ( $cur_round !== null ) echo '</div>';
				endif; ?>
			</div><!-- /.kurs-sched-body -->
		</div>

	</div><!-- /.kurs-matches-layout -->
	<?php endif; // group_id
endif; // view ?>

<?php if ( $view === 'playoff' ) : ?>
<script>
document.querySelectorAll('.pm-edit-btn').forEach(function(btn) {
	btn.addEventListener('click', function() {
		var id    = btn.dataset.id;
		var panel = document.getElementById('pm-edit-' + id);
		if (!panel) return;
		var open  = panel.style.display !== 'none';
		document.querySelectorAll('.kurs-pm-edit-panel').forEach(function(p) { p.style.display = 'none'; });
		document.querySelectorAll('.pm-edit-btn').forEach(function(b) { b.classList.remove('is-active'); });
		if (!open) {
			panel.style.display = 'block';
			btn.classList.add('is-active');
			if (typeof flatpickr !== 'undefined') {
				panel.querySelectorAll('input.kurs-datepicker:not(.flatpickr-input)').forEach(function(el) {
					flatpickr(el, { enableTime: true, time_24hr: true, dateFormat: 'd.m.Y H:i', allowInput: true, disableMobile: true });
				});
			}
		}
	});
});
</script>
<?php endif; ?>
