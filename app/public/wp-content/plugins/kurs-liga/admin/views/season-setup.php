<?php
defined( 'ABSPATH' ) || exit;

$season_id = absint( $_GET['season_id'] ?? 0 );
$step      = absint( $_GET['step'] ?? 2 );

if ( ! $season_id ) wp_die( 'Nevažeći ID sezone.' );

$season = Kurs_Season::get( $season_id );
if ( ! $season ) wp_die( 'Sezona ne postoji.' );

$divisions = Kurs_Season::get_divisions( $season_id );

$all_teams = get_posts( [
	'post_type'      => 'kurs_ekipa',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
] );

function kurs_setup_url( int $sid, int $s ): string {
	return admin_url( "admin.php?page=kurs-season-setup&season_id={$sid}&step={$s}" );
}
?>
<div class="wrap kurs-wrap">

	<h1>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>" class="kurs-back">&larr; Sezone</a>
		<?php echo esc_html( $season->name ); ?>
		<span class="kurs-tag kurs-tag--<?php echo esc_attr( $season->league ); ?>" style="font-size:.65em;vertical-align:middle;">
			<?php echo $season->league === 'pan_ljetna' ? 'Pan Ljetna Liga' : 'KC Liga'; ?>
		</span>
	</h1>

	<nav class="kurs-tabs">
		<a class="kurs-tab<?php echo $step === 2 ? ' is-active' : ''; ?>"
		   href="<?php echo kurs_setup_url( $season_id, 2 ); ?>">1 &mdash; Grupe &amp; faze</a>
		<a class="kurs-tab<?php echo $step === 3 ? ' is-active' : ''; ?>"
		   href="<?php echo kurs_setup_url( $season_id, 3 ); ?>">2 &mdash; Dodjela ekipa</a>
		<a class="kurs-tab"
		   href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $season_id ); ?>">3 &mdash; Utakmice &amp; tablice</a>
	</nav>

	<?php if ( $step === 2 ) : ?>
	<!-- ── KORAK 1: Divizije / Faze / Grupe ──────────────────────────────── -->

	<div class="kurs-setup-layout">

		<div class="kurs-setup-sidebar">
			<div class="kurs-card">
				<h3>Dodaj diviziju</h3>
				<p class="kurs-muted">Npr. "Glavna liga", "Liga bez briga", ili ostavi samo jednu za ligaški format bez podjele.</p>
				<form method="post">
					<?php wp_nonce_field( 'kurs_season_setup' ); ?>
					<input type="hidden" name="kurs_action" value="add_division">
					<input type="hidden" name="season_id"  value="<?php echo $season_id; ?>">
					<div class="kurs-form-row">
						<input type="text" name="div_name" class="regular-text" placeholder="Naziv divizije" required>
						<button type="submit" class="button button-primary">Dodaj</button>
					</div>
				</form>
			</div>

			<?php if ( ! empty( $divisions ) ) : ?>
			<div class="kurs-card" style="margin-top:1rem;">
				<p style="margin:0;">
					<a href="<?php echo kurs_setup_url( $season_id, 3 ); ?>" class="button button-primary" style="width:100%;text-align:center;">
						Nastavi: Dodjela ekipa &rarr;
					</a>
				</p>
			</div>
			<?php endif; ?>
		</div>

		<div class="kurs-setup-main">
			<?php if ( empty( $divisions ) ) : ?>
			<div class="kurs-empty-state">
				<p>Dodaj prvu diviziju s lijeve strane da nastaviš postavljanje.</p>
			</div>
			<?php endif; ?>

			<?php foreach ( $divisions as $div ) :
				$phases = Kurs_Season::get_phases( (int) $div->id );
			?>
			<div class="kurs-card">
				<div class="kurs-card__head">
					<h2><?php echo esc_html( $div->name ); ?></h2>
					<span class="kurs-muted"><?php echo count( $phases ); ?> faze</span>
				</div>

				<details class="kurs-add-phase">
					<summary>+ Dodaj fazu natjecanja</summary>
					<form method="post" style="margin-top:.75rem;">
						<?php wp_nonce_field( 'kurs_season_setup' ); ?>
						<input type="hidden" name="kurs_action"  value="add_phase">
						<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
						<input type="hidden" name="division_id"  value="<?php echo $div->id; ?>">
						<div class="kurs-form-row">
							<input type="text" name="phase_name" class="regular-text" placeholder="Npr. Grupna faza" required>
							<select name="phase_type">
								<option value="group">Grupna faza</option>
								<option value="playoff">Playoff</option>
							</select>
							<button type="submit" class="button">Dodaj fazu</button>
						</div>
					</form>
				</details>

				<?php if ( empty( $phases ) ) : ?>
					<p class="kurs-muted" style="margin:.75rem 0 0;">Dodaj fazu gore (npr. "Grupna faza").</p>
				<?php endif; ?>

				<?php foreach ( $phases as $ph ) :
					$groups = Kurs_Season::get_groups( (int) $ph->id );
				?>
				<div class="kurs-phase-block">
					<div class="kurs-phase-block__head">
						<h4><?php echo esc_html( $ph->name ); ?></h4>
						<span class="kurs-phase-pill kurs-phase-pill--<?php echo esc_attr( $ph->type ); ?>">
							<?php echo $ph->type === 'group' ? 'Grupna' : 'Playoff'; ?>
						</span>
					</div>

					<?php if ( $ph->type === 'group' ) : ?>
					<div class="kurs-groups-mgr" data-phase-id="<?php echo (int) $ph->id; ?>">
						<div class="kurs-chips">
							<?php foreach ( $groups as $g ) : ?>
							<span class="kurs-chip">
								<?php echo esc_html( $g->name ); ?>
								<button class="kurs-chip__del" data-group-id="<?php echo (int) $g->id; ?>" title="Obriši grupu">&times;</button>
							</span>
							<?php endforeach; ?>
						</div>
						<div class="kurs-form-row" style="margin-top:.5rem;">
							<input type="text" class="js-group-name small-text" placeholder="Npr. Grupa A">
							<button class="button js-add-group">+ Dodaj grupu</button>
						</div>
					</div>
					<?php else : ?>
					<p class="kurs-muted" style="margin:.4rem 0 0;font-size:.8rem;">
						Playoff bracket bit će dostupan u sljedećoj fazi razvoja.
					</p>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php elseif ( $step === 3 ) : ?>
	<!-- ── KORAK 2: Dodjela ekipa grupama ────────────────────────────────── -->
	<script>
	window.KursTeams = <?php echo wp_json_encode( array_map( fn( $t ) => [
		'id'   => $t->ID,
		'name' => $t->post_title,
	], $all_teams ) ); ?>;
	</script>

	<?php if ( empty( $divisions ) ) : ?>
	<div class="kurs-empty-state">
		<p>Prvo postavi divizije i grupe u
		   <a href="<?php echo kurs_setup_url( $season_id, 2 ); ?>">koraku 1</a>.
		</p>
	</div>
	<?php else : ?>

	<?php if ( empty( $all_teams ) ) : ?>
	<div class="kurs-notice">
		<strong>Nema ekipa u sustavu.</strong>
		<a href="<?php echo admin_url( 'post-new.php?post_type=kurs_ekipa' ); ?>">Dodaj ekipe →</a>
	</div>
	<?php endif; ?>

	<div class="kurs-assign-layout">

		<div class="kurs-team-pool">
			<h3>Dostupne ekipe <span class="kurs-muted" id="pool-count" style="font-size:.8em;">(<?php echo count( $all_teams ); ?>)</span></h3>
			<p class="kurs-muted">Odaberi ekipu iz padajućeg izbornika unutar grupe i klikni "Dodaj".</p>

			<?php if ( ! empty( $all_teams ) ) : ?>
			<div class="kurs-team-list" id="kurs-team-pool-list">
				<?php foreach ( $all_teams as $t ) : ?>
				<div class="kurs-team-pill" data-team-id="<?php echo $t->ID; ?>">
					<?php if ( has_post_thumbnail( $t->ID ) ) : ?>
						<?php echo get_the_post_thumbnail( $t->ID, [ 26, 26 ] ); ?>
					<?php else : ?>
						<span class="kurs-avatar"><?php echo esc_html( mb_strtoupper( mb_substr( $t->post_title, 0, 2 ) ) ); ?></span>
					<?php endif; ?>
					<span><?php echo esc_html( $t->post_title ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
			<div id="kurs-team-pool-list"></div>
			<?php endif; ?>

			<!-- ── Kreiraj novu ekipu ─────────────────────────────────────── -->
			<div class="kurs-new-team-form" style="margin-top:1rem;padding-top:.85rem;border-top:1px solid #e5e5e5;">
				<h4 style="margin:0 0 .4rem;font-size:.875rem;">+ Nova ekipa</h4>
				<input type="text" id="new-team-name-setup" class="regular-text"
				       placeholder="Naziv ekipe">
				<button class="button button-primary" id="btn-create-team-setup">Kreiraj ekipu</button>
				<p id="new-team-setup-msg" class="kurs-muted" style="margin-top:.35rem;display:none;"></p>
			</div>
		</div>

		<div class="kurs-groups-canvas">
			<?php foreach ( $divisions as $div ) :
				$phases = Kurs_Season::get_phases( (int) $div->id );
			?>
			<h2><?php echo esc_html( $div->name ); ?></h2>

			<?php foreach ( $phases as $ph ) :
				if ( $ph->type !== 'group' ) continue;
				$groups = Kurs_Season::get_groups( (int) $ph->id );
				if ( empty( $groups ) ) : ?>
				<p class="kurs-muted">Nema grupa u fazi "<?php echo esc_html( $ph->name ); ?>".
				   <a href="<?php echo kurs_setup_url( $season_id, 2 ); ?>">Dodaj grupe →</a></p>
				<?php continue; endif; ?>

			<p class="kurs-phase-label">
				<span class="kurs-phase-pill kurs-phase-pill--group"><?php echo esc_html( $ph->name ); ?></span>
			</p>

			<div class="kurs-group-cols">
				<?php foreach ( $groups as $g ) :
					$gteams = Kurs_Season::get_group_teams( (int) $g->id );
				?>
				<div class="kurs-group-col" data-group-id="<?php echo (int) $g->id; ?>">
					<div class="kurs-group-col__head">
						<span><?php echo esc_html( $g->name ); ?></span>
						<span class="kurs-muted kurs-cnt"><?php echo count( $gteams ); ?> ekipa</span>
					</div>
					<div class="kurs-group-col__teams">
						<?php foreach ( $gteams as $gt ) : ?>
						<div class="kurs-group-team" data-team-id="<?php echo (int) $gt->team_id; ?>">
							<span><?php echo esc_html( $gt->team_name ); ?></span>
							<button class="kurs-btn-rm"
							        data-group-id="<?php echo (int) $g->id; ?>"
							        data-team-id="<?php echo (int) $gt->team_id; ?>"
							        title="Ukloni iz grupe">&times;</button>
						</div>
						<?php endforeach; ?>
					</div>
					<div class="kurs-group-col__add">
						<div class="kurs-team-ac-wrap">
							<input type="text"
							       class="kurs-team-search small-text"
							       placeholder="Traži ekipu..."
							       autocomplete="off">
							<input type="hidden" class="kurs-team-selected-id" value="">
						</div>
						<button class="button button-small kurs-btn-add-team"
						        data-group-id="<?php echo (int) $g->id; ?>">Dodaj</button>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
			<?php endforeach; ?>

			<div style="margin-top:1.5rem;">
				<a href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $season_id ); ?>"
				   class="button button-primary button-large">
					Nastavi: Utakmice &amp; tablice &rarr;
				</a>
			</div>
		</div>

	</div>
	<?php endif; ?>
	<?php endif; ?>

</div>
