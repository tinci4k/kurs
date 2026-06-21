<?php
defined( 'ABSPATH' ) || exit;

$action    = sanitize_key( $_GET['action'] ?? '' );
$edit_id   = absint( $_GET['id'] ?? 0 );
$edit_item = ( $action === 'edit' && $edit_id ) ? Kurs_Season::get( $edit_id ) : null;
?>
<div class="wrap kurs-wrap">
	<h1>Sezone natjecanja</h1>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je ažurirana.</p></div>
	<?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je obrisana.</p></div>
	<?php elseif ( ! empty( $_GET['added'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je kreirana.</p></div>
	<?php endif; ?>

	<div class="kurs-two-col">

		<!-- ── Lista sezona ───────────────────────────────────────────────── -->
		<div>
			<h2>Sve sezone</h2>
			<?php $seasons = Kurs_Season::all(); ?>
			<?php if ( empty( $seasons ) ) : ?>
				<p class="kurs-muted">Nema sezona. Dodaj prvu desno.</p>
			<?php else : ?>
			<table class="widefat striped kurs-table">
				<thead>
					<tr>
						<th>Naziv sezone</th>
						<th>Liga / natjecanje</th>
						<th>God.</th>
						<th>Status</th>
						<th style="width:220px;">Akcije</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $seasons as $s ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $s->name ); ?></strong></td>
						<td>
							<span class="kurs-league-name"><?php echo esc_html( $s->league ); ?></span>
						</td>
						<td><?php echo esc_html( $s->year ); ?></td>
						<td>
							<span class="kurs-badge kurs-badge--<?php echo esc_attr( $s->status ); ?>">
								<?php echo $s->status === 'active' ? 'Aktivna' : 'Arhiva'; ?>
							</span>
						</td>
						<td class="kurs-row-actions">
							<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $s->id . '&step=2' ); ?>"
							   class="button button-small">Postavi</a>
							<a href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $s->id ); ?>"
							   class="button button-small">Utakmice</a>
							<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons&action=edit&id=' . $s->id ); ?>"
							   class="button button-small kurs-btn-edit">&#9998; Uredi</a>
							<form method="post" style="display:inline;"
							      onsubmit="return confirm('Sigurno želiš obrisati sezonu &quot;<?php echo esc_js( $s->name ); ?>&quot;?\n\nOvo će obrisati sve divizije, grupe, utakmice i statistike ove sezone. Radnja se ne može poništiti!');">
								<?php wp_nonce_field( 'kurs_delete_season' ); ?>
								<input type="hidden" name="kurs_action" value="delete_season">
								<input type="hidden" name="season_id"   value="<?php echo (int) $s->id; ?>">
								<button type="submit" class="button button-small kurs-btn-delete">&#128465; Obriši</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

		<!-- ── Forma (nova sezona ili uredi) ─────────────────────────────── -->
		<div>
			<?php if ( $edit_item ) : ?>
			<!-- ── UREDI SEZONU ── -->
			<h2>
				Uredi sezonu
				<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>"
				   class="page-title-action" style="font-size:.75em;">Odustani</a>
			</h2>
			<div class="kurs-card">
				<form method="post">
					<?php wp_nonce_field( 'kurs_edit_season' ); ?>
					<input type="hidden" name="kurs_action" value="edit_season">
					<input type="hidden" name="season_id"   value="<?php echo (int) $edit_item->id; ?>">

					<table class="form-table">
						<tr>
							<th><label for="edit_league">Liga / natjecanje</label></th>
							<td>
								<input type="text" name="league" id="edit_league" class="regular-text"
								       value="<?php echo esc_attr( $edit_item->league ); ?>"
								       placeholder="Npr. Pan Ljetna Liga" required>
								<p class="description">Slobodan unos — npr. "Pan Ljetna Liga", "KC Liga", "Memorijalni turnir".</p>
							</td>
						</tr>
						<tr>
							<th><label for="edit_name">Puni naziv sezone</label></th>
							<td>
								<input type="text" name="name" id="edit_name" class="regular-text"
								       value="<?php echo esc_attr( $edit_item->name ); ?>" required>
								<p class="description">Npr. "Pan Ljetna Liga 2026" — prikazuje se na web stranici.</p>
							</td>
						</tr>
						<tr>
							<th><label for="edit_year">Godina</label></th>
							<td>
								<input type="number" name="year" id="edit_year" class="small-text"
								       value="<?php echo (int) $edit_item->year; ?>" min="2000" max="2100" required>
							</td>
						</tr>
						<tr>
							<th><label for="edit_status">Status</label></th>
							<td>
								<select name="status" id="edit_status">
									<option value="active"   <?php selected( $edit_item->status, 'active' ); ?>>Aktivna</option>
									<option value="archived" <?php selected( $edit_item->status, 'archived' ); ?>>Arhiva</option>
								</select>
								<p class="description">Arhivirane sezone se ne prikazuju na dashboardu, ali su dostupne u povijesti.</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">Spremi promjene</button>
						<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>" class="button button-large">Odustani</a>
					</p>
				</form>
			</div>

			<?php else : ?>
			<!-- ── NOVA SEZONA ── -->
			<h2>Nova sezona</h2>
			<div class="kurs-card">
				<form method="post">
					<?php wp_nonce_field( 'kurs_create_season' ); ?>
					<input type="hidden" name="kurs_action" value="create_season">

					<table class="form-table">
						<tr>
							<th><label for="kurs_league">Liga / natjecanje</label></th>
							<td>
								<input type="text" name="league" id="kurs_league" class="regular-text"
								       placeholder="Npr. Pan Ljetna Liga" required>
								<p class="description">Slobodan unos — npr. "Pan Ljetna Liga", "KC Liga", "Memorijalni turnir".</p>
							</td>
						</tr>
						<tr>
							<th><label for="kurs_name">Puni naziv sezone</label></th>
							<td>
								<input type="text" name="name" id="kurs_name" class="regular-text"
								       placeholder="Pan Ljetna Liga 2026" required>
								<p class="description">Prikazuje se na web stranici.</p>
							</td>
						</tr>
						<tr>
							<th><label for="kurs_year">Godina</label></th>
							<td>
								<input type="number" name="year" id="kurs_year" class="small-text"
								       value="<?php echo (int) date( 'Y' ); ?>" min="2000" max="2100" required>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">
							Kreiraj sezonu &rarr;
						</button>
					</p>
				</form>
			</div>
			<?php endif; ?>
		</div>

	</div>
</div>
