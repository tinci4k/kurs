<?php
defined( 'ABSPATH' ) || exit;

$team_id     = absint( $_GET['team_id']     ?? 0 );
$division_id = absint( $_GET['division_id'] ?? 0 );

if ( ! $team_id ) wp_die( 'Nevažeći ID ekipe.' );

$team = get_post( $team_id );
if ( ! $team || $team->post_type !== 'kurs_ekipa' ) wp_die( 'Ekipa ne postoji.' );

$contexts   = Kurs_Season::get_team_contexts( $team_id );
$active_ctx = null;

foreach ( $contexts as $ctx ) {
	if ( (int) $ctx->division_id === $division_id ) {
		$active_ctx = $ctx;
		break;
	}
}

if ( ! $division_id && ! empty( $contexts ) ) {
	$division_id = (int) $contexts[0]->division_id;
	$active_ctx  = $contexts[0];
}

$squad     = $division_id ? Kurs_Season::get_squad( $team_id, $division_id ) : [];
$squad_ids = array_map( 'intval', array_column( $squad, 'player_id' ) );

$all_players = get_posts( [
	'post_type'      => 'kurs_igrac',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
] );
$available = array_filter( $all_players, fn( $p ) => ! in_array( (int) $p->ID, $squad_ids, true ) );
?>
<div class="wrap kurs-wrap">

	<h1>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-teams' ); ?>" class="kurs-back">&larr; Ekipe</a>
		<?php if ( has_post_thumbnail( $team_id ) ) : ?>
			<?php echo get_the_post_thumbnail( $team_id, [ 36, 36 ], [ 'class' => 'kurs-team-logo-inline' ] ); ?>
		<?php endif; ?>
		<?php echo esc_html( $team->post_title ); ?> &mdash; Roster igrača
	</h1>

	<?php if ( empty( $contexts ) ) : ?>
	<div class="kurs-notice" style="margin-top:1rem;">
		<strong>Ova ekipa još nije raspoređena ni u jednu grupu.</strong><br>
		Prvo dodaj ekipu u grupu:
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>">Sezone &rarr; Postavi ligu &rarr; Dodjela ekipa</a>
	</div>
	<?php else : ?>

	<div class="kurs-selector-bar">
		<form method="get">
			<input type="hidden" name="page"    value="kurs-roster">
			<input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
			<label class="kurs-selector-bar__label">Sezona / Divizija</label>
			<select name="division_id" onchange="this.form.submit()">
				<?php foreach ( $contexts as $ctx ) : ?>
				<option value="<?php echo (int) $ctx->division_id; ?>" <?php selected( $ctx->division_id, $division_id ); ?>>
					<?php echo esc_html( $ctx->season_name . ' — ' . $ctx->division_name ); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</form>
		<?php if ( $active_ctx ) : ?>
		<span class="kurs-selector-bar__label" style="margin-left:1rem;color:#888;">
			<?php echo esc_html( $active_ctx->league ); ?>
		</span>
		<?php endif; ?>
	</div>

	<?php if ( $division_id ) : ?>

	<!-- Skriveni kontekst za JS -->
	<div id="kurs-roster-ctx"
	     data-team-id="<?php echo $team_id; ?>"
	     data-division-id="<?php echo $division_id; ?>"
	     style="display:none;"></div>

	<div class="kurs-roster-layout">

		<!-- ── Trenutni roster ────────────────────────────────────────── -->
		<div class="kurs-panel">
			<div class="kurs-panel__head">
				<h2>
					Roster
					<span class="kurs-muted" style="font-size:.8em;" id="squad-count-label">
						<?php echo count( $squad ); ?> <?php echo count( $squad ) === 1 ? 'igrač' : 'igrača'; ?>
					</span>
				</h2>
				<?php if ( $active_ctx ) : ?>
				<div>
					<span class="kurs-tag kurs-tag--league"><?php echo esc_html( $active_ctx->season_name ); ?></span>
					<span class="kurs-phase-pill kurs-phase-pill--group"><?php echo esc_html( $active_ctx->division_name ); ?></span>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( empty( $squad ) ) : ?>
			<p class="kurs-muted" id="squad-empty-msg">Nema prijavljenih igrača za ovu sezonu/diviziju. Dodaj igrače desno.</p>
			<?php else : ?>
			<p class="kurs-muted" id="squad-empty-msg" style="display:none;">Nema prijavljenih igrača. Dodaj igrače desno.</p>
			<?php endif; ?>

			<table class="widefat kurs-roster-table" <?php echo empty( $squad ) ? 'style="display:none;"' : ''; ?> id="kurs-roster-table">
				<thead>
					<tr>
						<th style="width:52px;text-align:center;">Dres</th>
						<th>Igrač</th>
						<th style="width:130px;text-align:center;">Akcije</th>
					</tr>
				</thead>
				<tbody id="kurs-squad-list">
					<?php foreach ( $squad as $sq ) : ?>
					<tr id="squad-row-<?php echo (int) $sq->player_id; ?>"
					    data-player-id="<?php echo (int) $sq->player_id; ?>"
					    data-team-id="<?php echo $team_id; ?>"
					    data-division-id="<?php echo $division_id; ?>"
					    data-name="<?php echo esc_attr( $sq->player_name ); ?>"
					    data-jersey="<?php echo (int) $sq->jersey_no; ?>">
						<td class="squad-cell-jersey" style="text-align:center;">
							<span class="kurs-jersey"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : '&mdash;'; ?></span>
						</td>
						<td class="squad-cell-name">
							<strong><?php echo esc_html( $sq->player_name ); ?></strong>
						</td>
						<td class="squad-actions" style="text-align:center;">
							<button class="button button-small kurs-btn-edit-squad"
							        data-player-id="<?php echo (int) $sq->player_id; ?>">Uredi</button>
							<button class="button button-small kurs-btn-rm-squad"
							        data-player-id="<?php echo (int) $sq->player_id; ?>">&times;</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- ── Sidebar: dodaj igrače ──────────────────────────────────── -->
		<div class="kurs-roster-sidebar">

			<!-- Dodaj postojećeg igrača -->
			<div class="kurs-card">
				<h3>Dodaj postojećeg igrača</h3>
				<?php if ( empty( $available ) ) : ?>
					<p class="kurs-muted" id="available-empty-msg">Svi dostupni igrači su već u rosteri.</p>
					<div id="add-existing-form" style="display:none;">
				<?php else : ?>
					<p class="kurs-muted" id="available-empty-msg" style="display:none;">Svi dostupni igrači su već u rosteri.</p>
					<div id="add-existing-form">
				<?php endif; ?>
					<div class="kurs-form-col">
						<select id="squad-player-select" class="regular-text">
							<option value="">— Odaberi igrača —</option>
							<?php foreach ( $available as $pl ) : ?>
							<option value="<?php echo $pl->ID; ?>"><?php echo esc_html( $pl->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="kurs-form-row" style="margin-top:.5rem;">
							<label style="font-size:.85rem;white-space:nowrap;">Broj dresa</label>
							<input type="number" id="squad-jersey" class="small-text" placeholder="npr. 10" min="0" max="99">
							<button class="button button-primary" id="btn-add-squad">+ Dodaj</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Kreiraj novog igrača -->
			<div class="kurs-card" style="margin-top:1rem;">
				<h3>Kreiraj novog igrača</h3>
				<p class="kurs-muted">Dok tipkaš, prikazuju se igrači koji već postoje.</p>
				<div class="kurs-form-col">
					<div class="kurs-ac-wrap">
						<input type="text" id="new-player-name" class="regular-text" placeholder="Ime i prezime igrača" autocomplete="off">
					</div>
					<div class="kurs-form-row" style="margin-top:.5rem;">
						<label style="font-size:.85rem;white-space:nowrap;">Broj dresa</label>
						<input type="number" id="new-player-jersey" class="small-text" placeholder="npr. 7" min="0" max="99">
						<button class="button button-primary" id="btn-quick-add">+ Kreiraj i dodaj</button>
					</div>
					<p id="quick-add-msg" class="kurs-muted" style="margin-top:.4rem;display:none;"></p>
				</div>
				<p class="kurs-muted" style="margin-top:.75rem;border-top:1px solid #f0f0f0;padding-top:.6rem;">
					Za fotografiju igrača: <a href="<?php echo admin_url( 'edit.php?post_type=kurs_igrac' ); ?>">Igrači &rarr;</a>
				</p>
			</div>

			<!-- Info -->
			<div class="kurs-card kurs-card--info" style="margin-top:1rem;">
				<h4 style="margin:0 0 .4rem;font-size:.85rem;color:#555;">&#8505; Igrač u više liga</h4>
				<p class="kurs-muted">
					Isti igrač može biti prijavljen za različite ekipe ili sezone — svaka prijava se prati posebno.
					Odaberi drugu sezonu/diviziju gore.
				</p>
			</div>

		</div>
	</div>

	<?php else : ?>
	<div class="kurs-empty-state" style="margin-top:1.5rem;">
		<p>Odaberi sezonu/diviziju gore za upravljanje rosterom.</p>
	</div>
	<?php endif; ?>
	<?php endif; ?>
</div>

<!-- ── Duplikat modal ──────────────────────────────────────────────────────── -->
<div id="kurs-dup-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="kurs-dup-title">
	<div class="kurs-modal-backdrop" id="kurs-dup-backdrop"></div>
	<div class="kurs-modal-box">
		<h3 class="kurs-modal-title" id="kurs-dup-title">&#9888; Igrač već postoji</h3>
		<p id="kurs-dup-msg" class="kurs-modal-msg"></p>
		<p class="kurs-muted">Što želiš napraviti?</p>
		<div class="kurs-modal-actions">
			<button id="btn-dup-add-existing" class="button button-primary">Dodaj postojećeg u roster</button>
			<button id="btn-dup-create-new"   class="button">Kreiraj novog igrača</button>
			<button id="btn-dup-cancel"        class="button button-link">Odustani</button>
		</div>
	</div>
</div>
