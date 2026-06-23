<?php
defined( 'ABSPATH' ) || exit;

// ── Color picker helper ──────────────────────────────────────────────────────
function kurs_color_picker_html( string $field_name, string $current_color, string $picker_id ): void {
	// 12 predefiranih boja, 6 po redu
	$presets = [
		'#DA0F1E', '#F47B16', '#FDE206', '#1F7A43', '#1a3a6b', '#0ea5e9',
		'#7c3aed', '#c0392b', '#ffffff', '#111317', '#6b7280', '#e8e8e8',
	];
	?>
	<div class="kurs-cp-wrap" id="cp-wrap-<?php echo esc_attr( $picker_id ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>"
		       id="cp-val-<?php echo esc_attr( $picker_id ); ?>"
		       value="<?php echo esc_attr( $current_color ); ?>">

		<button type="button" class="kurs-cp-trigger" id="cp-btn-<?php echo esc_attr( $picker_id ); ?>">
			<span class="kurs-cp-trigger__dot"
			      id="cp-dot-<?php echo esc_attr( $picker_id ); ?>"
			      style="background:<?php echo esc_attr( $current_color ); ?>;"></span>
			<span id="cp-label-<?php echo esc_attr( $picker_id ); ?>">
				<?php echo esc_html( strtoupper( $current_color ) ); ?>
			</span>
			<span class="kurs-cp-trigger__caret">▼</span>
		</button>

		<div class="kurs-cp-dropdown" id="cp-drop-<?php echo esc_attr( $picker_id ); ?>">
			<div class="kurs-cp-presets">
				<?php foreach ( $presets as $pc ) :
					$light     = in_array( strtolower($pc), ['#ffffff','#e8e8e8','#fde206'], true );
					$is_active = strtolower( $current_color ) === strtolower( $pc );
				?>
				<button type="button"
				        class="kurs-cp-swatch<?php echo $is_active ? ' is-active' : ''; ?><?php echo $light ? ' kurs-cp-swatch--white' : ''; ?>"
				        data-color="<?php echo esc_attr( $pc ); ?>"
				        data-picker="<?php echo esc_attr( $picker_id ); ?>"
				        style="background:<?php echo esc_attr( $pc ); ?>;"
				        title="<?php echo esc_attr( $pc ); ?>"></button>
				<?php endforeach; ?>
			</div>
			<hr class="kurs-cp-divider">
			<div class="kurs-cp-custom-label">Vlastita boja</div>
			<div class="kurs-cp-custom-row">
				<input type="color" class="kurs-cp-wheel"
				       id="cp-wheel-<?php echo esc_attr( $picker_id ); ?>"
				       value="<?php echo esc_attr( $current_color ); ?>"
				       title="Otvori paletu boja">
				<input type="text" class="kurs-cp-hex"
				       id="cp-hex-<?php echo esc_attr( $picker_id ); ?>"
				       value="<?php echo esc_attr( strtoupper( $current_color ) ); ?>"
				       maxlength="7" placeholder="#RRGGBB" spellcheck="false">
			</div>
		</div>
	</div>
	<?php
}

$action  = sanitize_key( $_GET['action'] ?? '' );
$edit_id = absint( $_GET['id'] ?? 0 );

// ── EDIT PODSTRANICA ────────────────────────────────────────────────────────
if ( $action === 'edit' && $edit_id ) {
	$edit_team = get_post( $edit_id );
	if ( ! $edit_team || $edit_team->post_type !== 'kurs_ekipa' ) wp_die( 'Ekipa ne postoji.' );

	// Roster podaci (isti kao roster.php)
	$division_id = absint( $_GET['division_id'] ?? 0 );
	$contexts    = Kurs_Season::get_team_contexts( $edit_id );
	$active_ctx  = null;
	foreach ( $contexts as $ctx ) {
		if ( (int) $ctx->division_id === $division_id ) { $active_ctx = $ctx; break; }
	}
	if ( ! $division_id && ! empty( $contexts ) ) {
		$division_id = (int) $contexts[0]->division_id;
		$active_ctx  = $contexts[0];
	}
	$squad     = $division_id ? Kurs_Season::get_squad( $edit_id, $division_id ) : [];
	$squad_ids = array_map( 'intval', array_column( $squad, 'player_id' ) );
	$all_players = get_posts( [ 'post_type' => 'kurs_igrac', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
	$available   = array_filter( $all_players, fn( $p ) => ! in_array( (int) $p->ID, $squad_ids, true ) );

	$cur_color   = kurs_team_color( $edit_id );
	$presets     = [ '#DA0F1E','#1a3a6b','#1F7A43','#F47B16','#FDE206','#7c3aed','#0ea5e9','#111317','#ffffff','#6b7280' ];
	$coach       = kurs_team_coach( $edit_id );
	$rep         = kurs_team_rep( $edit_id );
	$banners     = kurs_team_banners( $edit_id, 'medium' );
	$photo       = kurs_team_photo( $edit_id, 'medium' );
	$cur_captain = (int) get_post_meta( $edit_id, '_kurs_team_captain', true );
	global $wpdb;
	$cap_players = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT sq.player_id, p.post_title AS name
		 FROM {$wpdb->prefix}kurs_squads sq
		 JOIN {$wpdb->posts} p ON p.ID = sq.player_id
		 WHERE sq.team_id = %d AND p.post_status = 'publish'
		 ORDER BY p.post_title ASC",
		$edit_id
	) );

	$back_url = admin_url( 'admin.php?page=kurs-teams' . ( ! empty( $_GET['updated'] ) ? '&updated=1' : '' ) );
	?>
	<div class="wrap kurs-wrap">

		<div class="kurs-breadcrumb">
			<a href="<?php echo admin_url( 'admin.php?page=kurs-teams' ); ?>">Ekipe</a>
			<span class="kurs-breadcrumb__sep">/</span>
			<?php echo esc_html( $edit_team->post_title ); ?>
		</div>

		<div class="kurs-page-hd">
			<div style="display:flex;align-items:center;gap:14px;">
				<?php echo kurs_team_badge_html( $edit_id, 42 ); ?>
				<h1 style="margin:0;"><?php echo esc_html( $edit_team->post_title ); ?></h1>
			</div>
			<a href="<?php echo admin_url( 'admin.php?page=kurs-teams' ); ?>" class="kurs-btn kurs-btn--ghost kurs-btn--sm">&larr; Natrag na ekipe</a>
		</div>

		<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="kurs-notice kurs-notice--ok">&#10003; Ekipa je ažurirana.</div>
		<?php endif; ?>

		<div class="kurs-team-edit-layout">

			<!-- ── Lijevo: Osnovno ─────────────────────────────────────────── -->
			<div>

				<!-- Naziv i boja -->
				<div class="kurs-panel">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Osnovno</h3></div>
					<div class="kurs-panel__body" style="padding:18px;">
						<form method="post">
							<?php wp_nonce_field( 'kurs_edit_team' ); ?>
							<input type="hidden" name="kurs_action" value="edit_team">
							<input type="hidden" name="team_id"     value="<?php echo $edit_id; ?>">
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}&updated=1" ) ); ?>">
							<div class="kurs-form-col" style="gap:14px;">
								<div>
									<label class="kurs-field-label" style="display:block;margin-bottom:4px;">Naziv ekipe</label>
									<input type="text" name="team_name" class="regular-text" style="width:100%;"
									       value="<?php echo esc_attr( $edit_team->post_title ); ?>" required>
								</div>
								<div>
									<label class="kurs-field-label" style="display:block;margin-bottom:6px;">Boja dresova</label>
									<?php kurs_color_picker_html( 'team_color', $cur_color, 'edit-' . $edit_id ); ?>
								</div>

								<div style="border-top:1px solid var(--ka-border);padding-top:14px;">
									<div class="kurs-field-label" style="margin-bottom:8px;">Trener i predstavnik <span class="kurs-muted" style="font-weight:400;text-transform:none;letter-spacing:0;">(neobavezno)</span></div>
									<div class="kurs-stack-mobile" style="display:grid;grid-template-columns:1fr 140px;gap:10px;">
										<div>
											<label class="kurs-muted" style="display:block;font-size:11px;margin-bottom:3px;">Trener — ime</label>
											<input type="text" name="coach_name" class="regular-text" style="width:100%;"
											       value="<?php echo esc_attr( $coach['name'] ?? '' ); ?>" placeholder="npr. Ivan Horvat">
										</div>
										<div>
											<label class="kurs-muted" style="display:block;font-size:11px;margin-bottom:3px;">Telefon</label>
											<input type="text" name="coach_phone" class="regular-text" style="width:100%;"
											       value="<?php echo esc_attr( $coach['phone'] ?? '' ); ?>" placeholder="091…">
										</div>
										<div>
											<label class="kurs-muted" style="display:block;font-size:11px;margin-bottom:3px;">Predstavnik — ime</label>
											<input type="text" name="rep_name" class="regular-text" style="width:100%;"
											       value="<?php echo esc_attr( $rep['name'] ?? '' ); ?>" placeholder="npr. Marko Marić">
										</div>
										<div>
											<label class="kurs-muted" style="display:block;font-size:11px;margin-bottom:3px;">Telefon</label>
											<input type="text" name="rep_phone" class="regular-text" style="width:100%;"
											       value="<?php echo esc_attr( $rep['phone'] ?? '' ); ?>" placeholder="098…">
										</div>
									</div>
								</div>

								<div style="border-top:1px solid var(--ka-border);padding-top:14px;">
									<label class="kurs-field-label" style="display:block;margin-bottom:4px;">Kapetan momčadi <span class="kurs-muted" style="font-weight:400;text-transform:none;letter-spacing:0;">(zadani — u zapisniku označen s (C))</span></label>
									<select name="team_captain" class="regular-text" style="width:100%;">
										<option value="0">— bez kapetana —</option>
										<?php foreach ( $cap_players as $cp ) : ?>
										<option value="<?php echo (int) $cp->player_id; ?>" <?php selected( $cur_captain, (int) $cp->player_id ); ?>><?php echo esc_html( $cp->name ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php if ( empty( $cap_players ) ) : ?><p class="kurs-muted" style="font-size:11px;margin:4px 0 0;">Dodaj igrače u roster da bi mogao odabrati kapetana.</p><?php endif; ?>
								</div>

								<button type="submit" class="kurs-btn kurs-btn--red">Spremi promjene</button>
							</div>
						</form>
					</div>
				</div>

				<!-- Logo -->
				<div class="kurs-panel" style="margin-top:16px;">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Logo ekipe</h3></div>
					<div class="kurs-panel__body" style="padding:18px;">
						<div style="display:flex;align-items:center;gap:16px;">
							<div id="logo-cell-<?php echo $edit_id; ?>">
								<?php echo kurs_team_badge_html( $edit_id, 64 ); ?>
							</div>
							<div>
								<button class="kurs-btn kurs-btn--outline kurs-btn--sm kurs-logo-btn"
								        data-team-id="<?php echo $edit_id; ?>">
									&#128247; <?php echo has_post_thumbnail( $edit_id ) ? 'Zamijeni logo' : 'Postavi logo'; ?>
								</button>
								<?php if ( has_post_thumbnail( $edit_id ) ) : ?>
								<form method="post" style="display:inline;margin-left:8px;">
									<?php wp_nonce_field( 'kurs_remove_logo_' . $edit_id ); ?>
									<input type="hidden" name="kurs_action" value="remove_team_logo">
									<input type="hidden" name="team_id"     value="<?php echo $edit_id; ?>">
									<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}" ) ); ?>">
									<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm"
									        onclick="return confirm('Ukloniti logo?')">&#215; Ukloni</button>
								</form>
								<?php endif; ?>
								<p class="kurs-muted" style="margin-top:8px;font-size:12px;">
									Preporučena veličina: 200×200px, PNG s prozirnom pozadinom.
								</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Baneri (sponzori) — više sponzora, svaki sa svojim linkom -->
				<div class="kurs-panel" style="margin-top:16px;">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Baneri (sponzori)</h3></div>
					<div class="kurs-panel__body" style="padding:18px;">
						<form method="post">
							<?php wp_nonce_field( 'kurs_save_banners_' . $edit_id ); ?>
							<input type="hidden" name="kurs_action" value="save_team_banners">
							<input type="hidden" name="team_id"     value="<?php echo $edit_id; ?>">
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}" ) ); ?>">

							<div id="kurs-banner-list">
								<?php foreach ( $banners as $bn ) : ?>
								<div class="kurs-banner-row">
									<input type="hidden" name="banner_ids[]" value="<?php echo (int) $bn['id']; ?>">
									<img src="<?php echo esc_url( $bn['url'] ); ?>" alt="" class="kurs-banner-row__img">
									<input type="url" name="banner_links[]" value="<?php echo esc_attr( $bn['link'] ); ?>" placeholder="https://sponzor.hr (link, neobavezno)" class="kurs-banner-row__link">
									<button type="button" class="kurs-banner-remove" title="Ukloni baner">&times;</button>
								</div>
								<?php endforeach; ?>
								<?php if ( ! $banners ) : ?>
								<div class="kurs-banner-empty kurs-banner-empty--list">Nema banera</div>
								<?php endif; ?>
							</div>

							<div style="display:flex;gap:8px;align-items:center;margin-top:12px;flex-wrap:wrap;">
								<button type="button" class="kurs-btn kurs-btn--outline kurs-btn--sm kurs-banner-add">&#128444; Dodaj baner</button>
								<button type="submit" class="kurs-btn kurs-btn--red kurs-btn--sm">Spremi banere</button>
							</div>
							<p class="kurs-muted" style="margin-top:8px;font-size:12px;">
								Široka slika (npr. 1200×300px). Ekipa može imati više sponzora — svaki baner ima svoj link. Prikazuju se na dnu javnog profila ekipe.
							</p>
						</form>
					</div>
				</div>

				<!-- Slika momčadi -->
				<div class="kurs-panel" style="margin-top:16px;">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Slika momčadi</h3></div>
					<div class="kurs-panel__body" style="padding:18px;">
						<div id="photo-cell-<?php echo $edit_id; ?>" style="margin-bottom:12px;">
							<?php if ( $photo ) : ?>
							<img src="<?php echo esc_url( $photo ); ?>" alt="" style="max-width:100%;height:auto;border-radius:6px;border:1px solid var(--ka-border);display:block;">
							<?php else : ?>
							<div class="kurs-banner-empty">Nema slike momčadi</div>
							<?php endif; ?>
						</div>
						<button class="kurs-btn kurs-btn--outline kurs-btn--sm kurs-photo-btn" data-team-id="<?php echo $edit_id; ?>">
							&#128247; <?php echo $photo ? 'Zamijeni sliku' : 'Postavi sliku'; ?>
						</button>
						<?php if ( $photo ) : ?>
						<form method="post" style="display:inline;margin-left:8px;">
							<?php wp_nonce_field( 'kurs_remove_photo_' . $edit_id ); ?>
							<input type="hidden" name="kurs_action" value="remove_team_photo">
							<input type="hidden" name="team_id"     value="<?php echo $edit_id; ?>">
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}" ) ); ?>">
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm" onclick="return confirm('Ukloniti sliku momčadi?')">&#215; Ukloni</button>
						</form>
						<?php endif; ?>
						<p class="kurs-muted" style="margin-top:8px;font-size:12px;">Skupna fotografija ekipe (npr. ispred gola). Prikazuje se na javnom profilu ekipe.</p>
					</div>
				</div>

			</div>

			<!-- ── Desno: Igrači ───────────────────────────────────────────── -->
			<div>
				<div class="kurs-panel">
					<div class="kurs-panel__head">
						<h3 class="kurs-panel__title">Igrači</h3>
						<?php if ( ! empty( $contexts ) ) : ?>
						<form method="get" style="display:flex;gap:6px;align-items:center;">
							<input type="hidden" name="page"   value="kurs-teams">
							<input type="hidden" name="action" value="edit">
							<input type="hidden" name="id"     value="<?php echo $edit_id; ?>">
							<select name="division_id" class="kurs-sel" onchange="this.form.submit()" style="font-size:12px;">
								<?php foreach ( $contexts as $ctx ) : ?>
								<option value="<?php echo (int) $ctx->division_id; ?>" <?php selected( $ctx->division_id, $division_id ); ?>>
									<?php echo esc_html( $ctx->season_name . ' — ' . $ctx->division_name ); ?>
								</option>
								<?php endforeach; ?>
							</select>
						</form>
						<?php endif; ?>
					</div>
					<div class="kurs-panel__body" style="padding:0 0 4px;">

					<?php if ( empty( $contexts ) ) : ?>
					<div style="padding:16px 18px;">
						<p class="kurs-muted">Ekipa još nije raspoređena ni u jednu grupu.</p>
						<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>" class="kurs-btn kurs-btn--outline kurs-btn--sm">
							Sezone → Postavi ligu
						</a>
					</div>
					<?php elseif ( ! $division_id ) : ?>
					<div style="padding:16px 18px;">
						<p class="kurs-muted">Odaberi sezonu/diviziju gore.</p>
					</div>
					<?php else : ?>

					<!-- Skriveni kontekst za JS (isti kao roster.php) -->
					<div id="kurs-roster-ctx"
					     data-team-id="<?php echo $edit_id; ?>"
					     data-division-id="<?php echo $division_id; ?>"
					     style="display:none;"></div>

					<!-- Tablica rostera -->
					<?php if ( empty( $squad ) ) : ?>
					<p class="kurs-muted" id="squad-empty-msg" style="padding:14px 18px;">Nema prijavljenih igrača za ovu sezonu/diviziju.</p>
					<?php else : ?>
					<p class="kurs-muted" id="squad-empty-msg" style="padding:14px 18px;display:none;">Nema prijavljenih igrača.</p>
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
							    data-team-id="<?php echo $edit_id; ?>"
							    data-division-id="<?php echo $division_id; ?>"
							    data-name="<?php echo esc_attr( $sq->player_name ); ?>"
							    data-jersey="<?php echo (int) $sq->jersey_no; ?>"
						    data-type="<?php echo esc_attr( kurs_player_type( (int) $sq->player_id ) ); ?>">
								<td class="squad-cell-jersey" style="text-align:center;">
									<span class="kurs-jersey"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : '&mdash;'; ?></span>
								</td>
								<td class="squad-cell-name">
									<strong><?php echo esc_html( $sq->player_name ); ?></strong><?php if ( kurs_player_type( (int) $sq->player_id ) === 'goalkeeper' ) : ?> <span class="kurs-pos-tag kurs-pos-tag--gk">GLM</span><?php endif; ?>
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

					<!-- Dodaj igrača -->
					<div style="padding:14px 18px;border-top:1px solid var(--ka-border);">
						<div class="kurs-stack-mobile" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

							<!-- Postojeći igrač -->
							<div>
								<div class="kurs-field-label" style="margin-bottom:6px;">Dodaj postojećeg igrača</div>
								<?php if ( empty( $available ) ) : ?>
								<p class="kurs-muted" id="available-empty-msg" style="font-size:12px;">Svi dostupni igrači su već u rosteri.</p>
								<div id="add-existing-form" style="display:none;">
								<?php else : ?>
								<p class="kurs-muted" id="available-empty-msg" style="font-size:12px;display:none;">Svi dostupni igrači su već u rosteri.</p>
								<div id="add-existing-form">
								<?php endif; ?>
									<select id="squad-player-select" class="kurs-sel" style="width:100%;margin-bottom:6px;">
										<option value="">— Odaberi igrača —</option>
										<?php foreach ( $available as $pl ) : ?>
										<option value="<?php echo $pl->ID; ?>"><?php echo esc_html( $pl->post_title ); ?></option>
										<?php endforeach; ?>
									</select>
									<div style="display:flex;gap:8px;align-items:flex-end;">
										<div>
											<label class="kurs-field-label" for="squad-jersey" style="display:block;margin-bottom:4px;">Broj dresa</label>
											<div class="kurs-jersey-field" title="Broj na dresu (0–99)">
												<span class="kurs-jersey-field__hash">#</span>
												<input type="number" id="squad-jersey" placeholder="—" min="0" max="99">
											</div>
										</div>
										<button class="kurs-btn kurs-btn--red kurs-btn--sm" id="btn-add-squad">+ Dodaj</button>
									</div>
								</div>
							</div>

							<!-- Novi igrač -->
							<div>
								<div class="kurs-field-label" style="margin-bottom:6px;">Kreiraj novog igrača</div>
								<div class="kurs-ac-wrap" style="margin-bottom:6px;">
									<input type="text" id="new-player-name" class="kurs-field-input" placeholder="Ime i prezime" autocomplete="off" style="width:100%;">
								</div>
								<select id="new-player-type" class="kurs-sel" style="width:100%;margin-bottom:6px;">
									<option value="player">Igrač</option>
									<option value="goalkeeper">Golman</option>
								</select>
								<div style="display:flex;gap:8px;align-items:flex-end;">
									<div>
										<label class="kurs-field-label" for="new-player-jersey" style="display:block;margin-bottom:4px;">Broj dresa</label>
										<div class="kurs-jersey-field" title="Broj na dresu (0–99)">
											<span class="kurs-jersey-field__hash">#</span>
											<input type="number" id="new-player-jersey" placeholder="—" min="0" max="99">
										</div>
									</div>
									<button class="kurs-btn kurs-btn--red kurs-btn--sm" id="btn-quick-add">+ Kreiraj i dodaj</button>
								</div>
								<p id="quick-add-msg" class="kurs-muted" style="margin-top:4px;font-size:11px;display:none;"></p>
							</div>

						</div>
					</div>

					<?php endif; ?>
					</div>
				</div>
			</div>

		</div>
	</div>

	<!-- Duplikat modal (kopija iz roster.php) -->
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
	<?php
	return; // Izlaz — ne prikazuj listu ispod
}

// ── LISTA EKIPA ─────────────────────────────────────────────────────────────
$teams = get_posts( [
	'post_type'      => 'kurs_ekipa',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
] );
?>
<div class="wrap kurs-wrap">

	<div class="kurs-breadcrumb">
		<a href="<?php echo admin_url( 'admin.php?page=kurs-dashboard' ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span>
		Ekipe
	</div>
	<div class="kurs-page-hd">
		<h1>Ekipe</h1>
	</div>

	<?php if ( ! empty( $_GET['added'] ) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Ekipa je dodana.</div>
	<?php elseif ( ! empty( $_GET['updated'] ) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Ekipa je ažurirana.</div>
	<?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Ekipa je obrisana.</div>
	<?php endif; ?>

	<div class="kurs-two-col">

		<!-- ── Lista ekipa ───────────────────────────────────────────────── -->
		<div>
			<div class="kurs-panel">
				<div class="kurs-panel__head">
					<h3 class="kurs-panel__title">Sve ekipe</h3>
					<span style="font-family:var(--font-mono);font-size:9px;color:var(--ka-muted);text-transform:uppercase;">
						<?php echo count( $teams ); ?> ukupno
					</span>
				</div>

				<?php if ( empty( $teams ) ) : ?>
				<div class="kurs-panel__empty">Nema ekipa. Dodaj prvu ekipu &rarr;</div>
				<?php else : ?>
				<div class="kurs-panel__body" style="padding:0;">
				<?php foreach ( $teams as $t ) : ?>
				<div style="display:grid;grid-template-columns:48px 1fr auto;align-items:center;padding:10px 18px;border-bottom:1px solid #f5f5ef;gap:12px;">

					<div class="kurs-logo-cell" id="logo-cell-<?php echo $t->ID; ?>">
						<?php echo kurs_team_badge_html( $t->ID, 40 ); ?>
					</div>

					<div style="font-family:var(--font-cond);font-weight:800;font-size:15px;">
						<?php echo esc_html( $t->post_title ); ?>
					</div>

					<div style="display:flex;gap:5px;align-items:center;">
						<a href="<?php echo admin_url( 'admin.php?page=kurs-teams&action=edit&id=' . $t->ID ); ?>"
						   class="kurs-btn kurs-btn--outline kurs-btn--sm" title="Uredi ekipu">
							&#9998; Uredi
						</a>
						<form method="post" style="display:inline;"
						      onsubmit="return confirm('Sigurno želiš obrisati ekipu &quot;<?php echo esc_js( $t->post_title ); ?>&quot;?');">
							<?php wp_nonce_field( 'kurs_delete_team' ); ?>
							<input type="hidden" name="kurs_action" value="delete_team">
							<input type="hidden" name="team_id"     value="<?php echo (int) $t->ID; ?>">
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm"
							        style="color:#c00 !important;border-color:#c00 !important;">&times;</button>
						</form>
					</div>
				</div>
				<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- ── Dodaj ekipu ───────────────────────────────────────────────── -->
		<div>
			<div class="kurs-panel">
				<div class="kurs-panel__head">
					<h3 class="kurs-panel__title">Dodaj ekipu</h3>
				</div>
				<div class="kurs-panel__body" style="padding:18px;">
					<form method="post">
						<?php wp_nonce_field( 'kurs_add_team' ); ?>
						<input type="hidden" name="kurs_action" value="add_team">
						<div class="kurs-form-col" style="gap:12px;">
							<div>
								<label class="kurs-field-label" style="display:block;margin-bottom:4px;">Naziv ekipe</label>
								<input type="text" name="team_name" class="regular-text" style="width:100%;"
								       placeholder="Npr. FC Koprivnica" required>
							</div>
							<div>
								<label class="kurs-field-label" style="display:block;margin-bottom:6px;">Boja dresova</label>
								<?php kurs_color_picker_html( 'team_color', '#DA0F1E', 'add-new' ); ?>
							</div>
							<button type="submit" class="kurs-btn kurs-btn--red">+ Dodaj ekipu</button>
						</div>
					</form>
				</div>
			</div>
		</div>

	</div>
</div>
