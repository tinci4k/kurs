<?php
defined( 'ABSPATH' ) || exit;

$action    = sanitize_key( $_GET['action'] ?? '' );
$edit_id   = absint( $_GET['id'] ?? 0 );
$edit_team = ( $action === 'edit' && $edit_id ) ? get_post( $edit_id ) : null;

$teams = get_posts( [
	'post_type'      => 'kurs_ekipa',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
] );
?>
<div class="wrap kurs-wrap">
	<h1>Ekipe</h1>

	<?php if ( ! empty( $_GET['added'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Ekipa je dodana.</p></div>
	<?php elseif ( ! empty( $_GET['updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Ekipa je ažurirana.</p></div>
	<?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Ekipa je obrisana.</p></div>
	<?php endif; ?>

	<div class="kurs-two-col">

		<!-- ── Lista ekipa ───────────────────────────────────────────────── -->
		<div>
			<h2>Sve ekipe (<?php echo count( $teams ); ?>)</h2>

			<?php if ( empty( $teams ) ) : ?>
			<div class="kurs-empty-state">
				<span class="dashicons dashicons-groups" style="font-size:3rem;height:auto;width:auto;color:#ccc;"></span>
				<p>Nema ekipa. Dodaj prvu ekipu desno.</p>
			</div>
			<?php else : ?>
			<table class="widefat striped kurs-table">
				<thead>
					<tr>
						<th style="width:48px;">Logo</th>
						<th>Naziv ekipe</th>
						<th style="width:260px;">Akcije</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $teams as $t ) :
						$thumb_url = get_the_post_thumbnail_url( $t->ID, [ 40, 40 ] );
					?>
					<tr>
						<td>
							<div class="kurs-logo-cell" id="logo-cell-<?php echo $t->ID; ?>">
								<?php if ( $thumb_url ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>"
									     style="width:40px;height:40px;object-fit:cover;border-radius:50%;display:block;">
								<?php else : ?>
									<span class="kurs-avatar kurs-avatar--lg">
										<?php echo esc_html( mb_strtoupper( mb_substr( $t->post_title, 0, 2 ) ) ); ?>
									</span>
								<?php endif; ?>
							</div>
						</td>
						<td><strong><?php echo esc_html( $t->post_title ); ?></strong></td>
						<td class="kurs-row-actions">
							<!-- Logo: otvara WP Media Library modal -->
							<button class="button button-small kurs-logo-btn"
							        data-team-id="<?php echo $t->ID; ?>"
							        title="Postavi ili promijeni logo ekipe">
								&#128247; Logo
							</button>
							<!-- Uredi naziv -->
							<a href="<?php echo admin_url( 'admin.php?page=kurs-teams&action=edit&id=' . $t->ID ); ?>"
							   class="button button-small kurs-btn-edit">&#9998; Uredi</a>
							<!-- Upravljaj igračima -->
							<a href="<?php echo admin_url( 'admin.php?page=kurs-roster&team_id=' . $t->ID ); ?>"
							   class="button button-small kurs-btn-roster">&#128100; Igrači</a>
							<!-- Obriši -->
							<form method="post" style="display:inline;"
							      onsubmit="return confirm('Sigurno želiš obrisati ekipu &quot;<?php echo esc_js( $t->post_title ); ?>&quot;?\n\nEkipa će biti uklonjena iz svih grupa i rasporeda. Radnja se ne može poništiti!');">
								<?php wp_nonce_field( 'kurs_delete_team' ); ?>
								<input type="hidden" name="kurs_action" value="delete_team">
								<input type="hidden" name="team_id"     value="<?php echo (int) $t->ID; ?>">
								<button type="submit" class="button button-small kurs-btn-delete">&#128465; Obriši</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

		<!-- ── Forma (dodaj / uredi ekipu) ──────────────────────────────── -->
		<div>
			<?php if ( $edit_team ) : ?>
			<!-- ── UREDI EKIPU ── -->
			<h2>
				Uredi ekipu
				<a href="<?php echo admin_url( 'admin.php?page=kurs-teams' ); ?>"
				   class="page-title-action" style="font-size:.75em;">Odustani</a>
			</h2>
			<div class="kurs-card">
				<form method="post">
					<?php wp_nonce_field( 'kurs_edit_team' ); ?>
					<input type="hidden" name="kurs_action" value="edit_team">
					<input type="hidden" name="team_id"     value="<?php echo (int) $edit_team->ID; ?>">

					<table class="form-table">
						<tr>
							<th><label for="edit_team_name">Naziv ekipe</label></th>
							<td>
								<input type="text" name="team_name" id="edit_team_name" class="regular-text"
								       value="<?php echo esc_attr( $edit_team->post_title ); ?>" required>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">Spremi naziv</button>
						<a href="<?php echo admin_url( 'admin.php?page=kurs-teams' ); ?>" class="button button-large">Odustani</a>
					</p>
				</form>

				<hr style="margin:1.25rem 0;">
				<p class="kurs-muted">
					<strong>Logo ekipe</strong> — klikni "&#128247; Logo" gumb pokraj ekipe u listi za odabir iz medija.
				</p>
				<p class="kurs-muted">
					<a href="<?php echo admin_url( 'admin.php?page=kurs-roster&team_id=' . $edit_team->ID ); ?>">
						&#128100; Upravljaj igračima ove ekipe →
					</a>
				</p>
			</div>

			<?php else : ?>
			<!-- ── DODAJ EKIPU ── -->
			<h2>Dodaj ekipu</h2>
			<div class="kurs-card">
				<form method="post">
					<?php wp_nonce_field( 'kurs_add_team' ); ?>
					<input type="hidden" name="kurs_action" value="add_team">

					<table class="form-table">
						<tr>
							<th><label for="team_name">Naziv ekipe</label></th>
							<td>
								<input type="text" name="team_name" id="team_name" class="regular-text"
								       placeholder="Npr. FC Koprivnica" required>
								<p class="description">Puni naziv ekipe kao što se prikazuje na tablici.</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">+ Dodaj ekipu</button>
					</p>
				</form>

				<hr style="margin:.75rem 0;">
				<p class="kurs-muted">
					&#128247; <strong>Logo</strong> dodaj nakon kreiranja — klikni gumb "Logo" pokraj ekipe u listi.<br>
					&#128100; <strong>Igrači</strong> se dodaju po sezoni i ligi — klikni "Igrači" pokraj ekipe.
				</p>
			</div>
			<?php endif; ?>
		</div>

	</div>
</div>
