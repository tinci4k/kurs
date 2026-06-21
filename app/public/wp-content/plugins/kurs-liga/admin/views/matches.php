<?php
defined( 'ABSPATH' ) || exit;

$season_id = absint( $_GET['season_id'] ?? 0 );
$group_id  = absint( $_GET['group_id']  ?? 0 );

$seasons = Kurs_Season::all();
if ( ! $season_id && ! empty( $seasons ) ) $season_id = (int) $seasons[0]->id;

$season    = $season_id ? Kurs_Season::get( $season_id ) : null;
$divisions = $season_id ? Kurs_Season::get_divisions( $season_id ) : [];

// Flatten svih grupa u odabranoj sezoni
$all_groups = [];
foreach ( $divisions as $div ) {
	foreach ( Kurs_Season::get_phases( (int) $div->id ) as $ph ) {
		foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
			$g->div_name   = $div->name;
			$g->phase_name = $ph->name;
			$g->phase_type = $ph->type;
			$g->div_id     = (int) $div->id;
			$all_groups[]  = $g;
		}
	}
}

if ( ! $group_id && ! empty( $all_groups ) ) $group_id = (int) $all_groups[0]->id;

$current_group = null;
foreach ( $all_groups as $g ) {
	if ( (int) $g->id === $group_id ) { $current_group = $g; break; }
}
?>
<div class="wrap kurs-wrap">
	<h1>Utakmice &amp; tablice</h1>

	<!-- ── Selektor sezone i grupe ────────────────────────────────────────── -->
	<div class="kurs-selector-bar">
		<form method="get">
			<input type="hidden" name="page" value="kurs-matches">
			<label class="kurs-selector-bar__label">Sezona</label>
			<select name="season_id" onchange="this.form.submit()">
				<?php foreach ( $seasons as $s ) : ?>
				<option value="<?php echo $s->id; ?>" <?php selected( $s->id, $season_id ); ?>>
					<?php echo esc_html( $s->name ); ?>
				</option>
				<?php endforeach; ?>
			</select>

			<?php if ( ! empty( $all_groups ) ) : ?>
			<label class="kurs-selector-bar__label">Skupina</label>
			<select name="group_id" onchange="this.form.submit()">
				<?php foreach ( $all_groups as $g ) : ?>
				<option value="<?php echo $g->id; ?>" <?php selected( $g->id, $group_id ); ?>>
					<?php echo esc_html( $g->div_name . ' — ' . $g->name ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>

			<?php if ( $season_id ) : ?>
			<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $season_id . '&step=2' ); ?>"
			   class="button" style="margin-left:.5rem;">Postavi ligu</a>
			<?php endif; ?>
		</form>
	</div>

	<?php if ( ! $group_id ) : ?>
	<div class="kurs-empty-state">
		<p>Nema postavljenih grupa u ovoj sezoni.</p>
		<?php if ( $season_id ) : ?>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $season_id . '&step=2' ); ?>"
		   class="button button-primary">Postavi ligu &rarr;</a>
		<?php endif; ?>
	</div>

	<?php else :
		$matches     = Kurs_Match::get_by_group( $group_id );
		$standings   = Kurs_Match::get_standings( $group_id );
		$group_teams = Kurs_Season::get_group_teams( $group_id );
	?>

	<div class="kurs-matches-layout">

		<!-- ── Ljestvica ──────────────────────────────────────────────────── -->
		<div class="kurs-panel">
			<h2>
				Ljestvica
				<?php if ( $current_group ) : ?>
				— <span style="color:#1a3a6b;"><?php echo esc_html( $current_group->div_name . ' / ' . $current_group->name ); ?></span>
				<?php endif; ?>
			</h2>

			<?php if ( empty( $standings ) ) : ?>
			<p class="kurs-muted">
				Nema završenih utakmica.
				<?php if ( empty( $group_teams ) ) : ?>
					<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $season_id . '&step=3' ); ?>">Dodaj ekipe u grupu →</a>
				<?php endif; ?>
			</p>
			<?php else : ?>
			<table class="kurs-standings">
				<thead>
					<tr>
						<th>#</th>
						<th class="kurs-standings__name">Ekipa</th>
						<th title="Odigrano">U</th>
						<th title="Pobjede">P</th>
						<th title="Remiji">R</th>
						<th title="Porazi">Pr</th>
						<th title="Golovi za">GZ</th>
						<th title="Golovi protiv">GP</th>
						<th title="Gol razlika">GR</th>
						<th title="Bodovi" class="kurs-standings__pts">Bod</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $standings as $i => $r ) : ?>
					<tr<?php echo $i < 2 ? ' class="kurs-standings__top"' : ''; ?>>
						<td class="kurs-standings__pos"><?php echo $i + 1; ?>.</td>
						<td class="kurs-standings__name"><strong><?php echo esc_html( $r['team_name'] ); ?></strong></td>
						<td><?php echo $r['P']; ?></td>
						<td><?php echo $r['W']; ?></td>
						<td><?php echo $r['D']; ?></td>
						<td><?php echo $r['L']; ?></td>
						<td><?php echo $r['GF']; ?></td>
						<td><?php echo $r['GA']; ?></td>
						<td><?php echo ( $r['GD'] >= 0 ? '+' : '' ) . $r['GD']; ?></td>
						<td class="kurs-standings__pts"><strong><?php echo $r['Pts']; ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="kurs-muted" style="font-size:.75rem;margin-top:.5rem;">
				Pobjeda = 3 boda &bull; Remi = 1 bod &bull; Poraz = 0 bodova
			</p>
			<?php endif; ?>
		</div>

		<!-- ── Utakmice ───────────────────────────────────────────────────── -->
		<div class="kurs-panel">
			<h2>Utakmice</h2>

			<?php if ( count( $group_teams ) >= 2 ) : ?>
			<details class="kurs-add-match">
				<summary>+ Dodaj utakmicu</summary>
				<form method="post" style="margin-top:.75rem;">
					<?php wp_nonce_field( 'kurs_add_match' ); ?>
					<input type="hidden" name="kurs_action" value="add_match">
					<input type="hidden" name="group_id"   value="<?php echo $group_id; ?>">
					<input type="hidden" name="season_id"  value="<?php echo $season_id; ?>">
					<div class="kurs-form-row">
						<select name="home_team" required>
							<option value="">Domaćin</option>
							<?php foreach ( $group_teams as $t ) : ?>
							<option value="<?php echo $t->team_id; ?>"><?php echo esc_html( $t->team_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<strong style="padding:0 .25rem;">vs</strong>
						<select name="away_team" required>
							<option value="">Gost</option>
							<?php foreach ( $group_teams as $t ) : ?>
							<option value="<?php echo $t->team_id; ?>"><?php echo esc_html( $t->team_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="kurs-form-row" style="margin-top:.5rem;">
						<label>Kolo:</label>
						<input type="number" name="round" class="small-text" value="1" min="1">
						<label>Datum i sat:</label>
						<input type="datetime-local" name="match_date">
						<button type="submit" class="button button-primary">Dodaj</button>
					</div>
				</form>
			</details>
			<?php elseif ( empty( $group_teams ) ) : ?>
			<div class="kurs-notice">
				<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $season_id . '&step=3' ); ?>">
					Dodaj ekipe u grupu →
				</a>
			</div>
			<?php endif; ?>

			<?php if ( empty( $matches ) ) : ?>
			<p class="kurs-muted" style="margin-top:1rem;">Nema utakmica u ovoj skupini.</p>
			<?php else :
				$cur_round = null;
				foreach ( $matches as $m ) :
					if ( $cur_round !== $m->round ) {
						$cur_round = $m->round;
						echo '<div class="kurs-round-hd">Kolo ' . (int) $cur_round . '</div>';
					}
			?>
			<div class="kurs-match kurs-match--<?php echo esc_attr( $m->status ); ?>">
				<div class="kurs-match__teams">
					<span class="kurs-match__home"><?php echo esc_html( $m->home_name ); ?></span>

					<?php if ( $m->status === 'finished' ) : ?>
					<span class="kurs-score"><?php echo (int) $m->home_score; ?>&nbsp;:&nbsp;<?php echo (int) $m->away_score; ?></span>
					<?php else : ?>
					<form method="post" class="kurs-result-form">
						<?php wp_nonce_field( 'kurs_finish_match' ); ?>
						<input type="hidden" name="kurs_action" value="finish_match">
						<input type="hidden" name="match_id"    value="<?php echo $m->id; ?>">
						<input type="hidden" name="season_id"   value="<?php echo $season_id; ?>">
						<input type="number" name="home_score" class="kurs-si" value="0" min="0" max="99" required>
						<span>:</span>
						<input type="number" name="away_score" class="kurs-si" value="0" min="0" max="99" required>
						<button type="submit" class="button button-small button-primary">&#10003; Zaključi</button>
					</form>
					<?php endif; ?>

					<span class="kurs-match__away"><?php echo esc_html( $m->away_name ); ?></span>
				</div>

				<div class="kurs-match__meta">
					<?php if ( $m->match_date ) : ?>
					<span class="kurs-match__date">
						<?php echo esc_html( date_i18n( 'j. M Y, H:i', strtotime( $m->match_date ) ) ); ?>
					</span>
					<?php endif; ?>
					<form method="post" class="kurs-del-match-form" onsubmit="return confirm('Obriši ovu utakmicu?');">
						<?php wp_nonce_field( 'kurs_delete_match' ); ?>
						<input type="hidden" name="kurs_action" value="delete_match">
						<input type="hidden" name="match_id"    value="<?php echo $m->id; ?>">
						<input type="hidden" name="season_id"   value="<?php echo $season_id; ?>">
						<button type="submit" class="kurs-del-btn" title="Obriši utakmicu">&times;</button>
					</form>
				</div>
			</div>
			<?php endforeach; endif; ?>
		</div>

	</div>
	<?php endif; ?>
</div>
