<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$p = $wpdb->prefix . KURS_PREFIX;

// ── Odabir sezone / divizije ─────────────────────────────────────────────────
$season_id   = absint( $_GET['season_id']   ?? 0 );
$division_id = absint( $_GET['division_id'] ?? 0 );

$all_seasons = $wpdb->get_results( "SELECT * FROM {$p}seasons ORDER BY id DESC" );
if ( ! $season_id && $all_seasons ) $season_id = (int) $all_seasons[0]->id;

$divisions = $season_id ? $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$p}divisions WHERE season_id = %d ORDER BY sort_order, id", $season_id
) ) : [];
if ( ! $division_id && $divisions ) $division_id = (int) $divisions[0]->id;

// ── Učitaj bracket ────────────────────────────────────────────────────────────
$bracket    = $division_id ? Kurs_Playoff::get_bracket( $division_id ) : [];
$has_bracket = ! empty( $bracket );

// Sve ekipe za manual override dropdown
$all_teams = get_posts( [ 'post_type' => 'kurs_ekipa', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );

$self_url = admin_url( "admin.php?page=kurs-playoff&season_id={$season_id}&division_id={$division_id}" );

// Labels rundi
$round_labels = [
	'qf'    => 'Četvrtfinale',
	'sf'    => 'Polufinale',
	'final' => 'Finale',
	'3rd'   => 'Utakmica za 3. mjesto',
];
$round_order = [ 'qf', 'sf', 'final', '3rd' ];
?>
<div class="wrap kurs-wrap kurs-playoff-wrap">

	<!-- Topbar -->
	<div class="kurs-page-head">
		<div>
			<h1 class="kurs-page-head__title">Playoff</h1>
			<p class="kurs-page-head__sub">Bracket, rezultati i napredovanje ekipa</p>
		</div>
		<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">

			<!-- Odabir sezone -->
			<form method="get">
				<input type="hidden" name="page" value="kurs-playoff">
				<select name="season_id" class="kurs-sel" onchange="this.form.submit()" style="min-width:160px;">
					<?php foreach ( $all_seasons as $s ) : ?>
					<option value="<?php echo $s->id; ?>" <?php selected( $season_id, $s->id ); ?>>
						<?php echo esc_html( $s->league . ' — ' . $s->name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</form>

			<!-- Odabir divizije -->
			<?php if ( count($divisions) > 1 ) : ?>
			<form method="get">
				<input type="hidden" name="page"      value="kurs-playoff">
				<input type="hidden" name="season_id" value="<?php echo $season_id; ?>">
				<select name="division_id" class="kurs-sel" onchange="this.form.submit()" style="min-width:160px;">
					<?php foreach ( $divisions as $d ) : ?>
					<option value="<?php echo $d->id; ?>" <?php selected( $division_id, $d->id ); ?>>
						<?php echo esc_html( $d->name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</form>
			<?php endif; ?>

			<?php if ( $division_id ) : ?>
			<!-- Generiraj / Reset -->
			<?php if ( ! $has_bracket ) : ?>
			<form method="post" onsubmit="return confirm('Generirati playoff bracket iz grupnih ljestvica?');">
				<?php wp_nonce_field('kurs_generate_playoff'); ?>
				<input type="hidden" name="kurs_action"  value="generate_playoff">
				<input type="hidden" name="division_id"  value="<?php echo $division_id; ?>">
				<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
				<button type="submit" class="kurs-btn kurs-btn--red">&#9654; Generiraj bracket</button>
			</form>
			<?php else : ?>
			<form method="post" onsubmit="return confirm('Obrisati cijeli bracket i početi iznova? Svi uneseni rezultati će biti izgubljeni!');">
				<?php wp_nonce_field('kurs_reset_playoff'); ?>
				<input type="hidden" name="kurs_action"  value="reset_playoff">
				<input type="hidden" name="division_id"  value="<?php echo $division_id; ?>">
				<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
				<button type="submit" class="kurs-btn kurs-btn--outline kurs-btn--sm">&#8635; Resetiraj bracket</button>
			</form>
			<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( isset($_GET['generated']) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Playoff bracket generiran iz grupnih ljestvica.</div>
	<?php elseif ( isset($_GET['reset']) ) : ?>
	<div class="kurs-notice">Bracket je resetiran. Možeš generirati novi.</div>
	<?php endif; ?>

	<?php if ( ! $has_bracket && $division_id ) : ?>
	<div class="kurs-empty-state">
		<div class="kurs-empty-state__icon">&#127942;</div>
		<div class="kurs-empty-state__title">Bracket nije generiran</div>
		<div class="kurs-empty-state__sub">Klikni "Generiraj bracket" gore desno da se playoff automatski popuni na temelju grupnih ljestvica.</div>
	</div>

	<?php else : ?>

	<!-- ── Bracket vizual ───────────────────────────────────────────────────── -->
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
					$finished = $m->status === 'finished';
					$home_name = $m->home_name ?: $m->home_label ?: '?';
					$away_name = $m->away_name ?: $m->away_label ?: '?';
					$home_wins = $finished && (int)$m->home_score > (int)$m->away_score;
					$away_wins = $finished && (int)$m->away_score > (int)$m->home_score;
				?>
				<div class="kurs-bracket__match <?php echo $finished ? 'is-finished' : ''; ?>" id="pm-<?php echo $m->id; ?>">

					<!-- Timovi + rezultat -->
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

					<!-- Datum + labeli -->
					<div class="kurs-bracket__meta">
						<?php if ( $m->match_date ) : ?>
						<span><?php echo esc_html( date( 'j.n. H:i', strtotime( $m->match_date ) ) ); ?></span>
						<?php else : ?>
						<span class="kurs-muted">Datum TBD</span>
						<?php endif; ?>
						<span class="kurs-bracket__meta-label"><?php echo esc_html( $round_labels[$round] ?? '' ); ?> #<?php echo $slot; ?></span>
					</div>

					<!-- Akcije -->
					<div class="kurs-bracket__actions no-print">
						<?php
						// "Zapisnik" link — kreira match record lazy ako trebamo
						$record_url = null;
						if ( $m->home_team_id && $m->away_team_id ) {
							$mid = Kurs_Playoff::ensure_match_record( (int)$m->id );
							if ( $mid ) {
								$record_url = admin_url( "admin.php?page=kurs-match-record&match_id={$mid}" );
							}
						}
						if ( $record_url ) : ?>
						<a href="<?php echo esc_url( $record_url ); ?>"
						   class="kurs-btn kurs-btn--outline kurs-btn--xs">&#128196; Zapisnik</a>
						<?php endif; ?>

						<?php if ( $finished ) : ?>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field('kurs_reopen_playoff_match'); ?>
							<input type="hidden" name="kurs_action"       value="reopen_playoff_match">
							<input type="hidden" name="playoff_match_id"  value="<?php echo $m->id; ?>">
							<input type="hidden" name="division_id"       value="<?php echo $division_id; ?>">
							<input type="hidden" name="season_id"         value="<?php echo $season_id; ?>">
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--xs">&#128274; Otključaj</button>
						</form>
						<?php else : ?>
						<button class="kurs-btn kurs-btn--outline kurs-btn--xs pm-edit-btn"
						        data-id="<?php echo $m->id; ?>">&#9998; Uredi</button>
						<?php endif; ?>
					</div>

					<!-- Edit panel (skriveni, otvara JS) -->
					<?php if ( ! $finished ) : ?>
					<div class="kurs-pm-edit-panel" id="pm-edit-<?php echo $m->id; ?>" style="display:none;">

						<!-- Rezultat -->
						<form method="post" class="kurs-pm-score-form">
							<?php wp_nonce_field('kurs_finish_playoff_match'); ?>
							<input type="hidden" name="kurs_action"       value="finish_playoff_match">
							<input type="hidden" name="playoff_match_id"  value="<?php echo $m->id; ?>">
							<input type="hidden" name="division_id"       value="<?php echo $division_id; ?>">
							<input type="hidden" name="season_id"         value="<?php echo $season_id; ?>">
							<div class="kurs-pm-score-row">
								<label class="kurs-field-label" style="font-size:9px;"><?php echo esc_html( $home_name ); ?></label>
								<input type="number" name="home_score" class="kurs-si kurs-pm-si" min="0" max="99" required placeholder="0">
								<span class="kurs-finish-score__sep">:</span>
								<input type="number" name="away_score" class="kurs-si kurs-pm-si" min="0" max="99" required placeholder="0">
								<label class="kurs-field-label" style="font-size:9px;"><?php echo esc_html( $away_name ); ?></label>
							</div>
							<button type="submit" class="kurs-btn kurs-btn--red kurs-btn--sm" style="width:100%;margin-top:6px;">&#10003; Zaključi</button>
						</form>

						<hr style="border:none;border-top:1px solid var(--ka-border);margin:10px 0;">

						<!-- Ručno postavljanje ekipa + datuma -->
						<form method="post">
							<?php wp_nonce_field('kurs_update_playoff_match'); ?>
							<input type="hidden" name="kurs_action"       value="update_playoff_match">
							<input type="hidden" name="playoff_match_id"  value="<?php echo $m->id; ?>">
							<input type="hidden" name="division_id"       value="<?php echo $division_id; ?>">
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

	<?php endif; ?>

</div>

<script>
document.querySelectorAll('.pm-edit-btn').forEach(function(btn) {
	btn.addEventListener('click', function() {
		var id    = btn.dataset.id;
		var panel = document.getElementById('pm-edit-' + id);
		if (!panel) return;
		var open  = panel.style.display !== 'none';
		// Zatvori sve ostale
		document.querySelectorAll('.kurs-pm-edit-panel').forEach(function(p) { p.style.display = 'none'; });
		document.querySelectorAll('.pm-edit-btn').forEach(function(b) { b.classList.remove('is-active'); });
		if (!open) {
			panel.style.display = 'block';
			btn.classList.add('is-active');
			// Init flatpickr na novopokazanom inputu
			if (typeof flatpickr !== 'undefined') {
				panel.querySelectorAll('input.kurs-datepicker:not(.flatpickr-input)').forEach(function(el) {
					flatpickr(el, { enableTime: true, time_24hr: true, dateFormat: 'd.m.Y H:i', allowInput: true, disableMobile: true });
				});
			}
		}
	});
});
</script>
