<?php
defined( 'ABSPATH' ) || exit;

$action    = sanitize_key( $_GET['action'] ?? '' );
$edit_id   = absint( $_GET['id'] ?? 0 );
$edit_item = ( $action === 'edit' && $edit_id ) ? Kurs_Season::get( $edit_id ) : null;
?>
<div class="wrap kurs-wrap">

	<div class="kurs-breadcrumb">
		<a href="<?php echo admin_url( 'admin.php?page=kurs-dashboard' ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span>
		Sezone
	</div>
	<div class="kurs-page-hd">
		<h1><?php echo $edit_item ? 'Uredi sezonu' : 'Sezone natjecanja'; ?></h1>
	</div>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je ažurirana.</p></div>
	<?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je obrisana.</p></div>
	<?php elseif ( ! empty( $_GET['added'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je kreirana.</p></div>
	<?php elseif ( ! empty( $_GET['locked'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je zaključana — podaci su sada konačni.</p></div>
	<?php elseif ( ! empty( $_GET['unlocked'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je otključana — uređivanje je ponovno moguće.</p></div>
	<?php endif; ?>

	<div class="kurs-two-col">

		<!-- ── Lista sezona ──────────────────────────────────────────────── -->
		<div>
			<?php $seasons = Kurs_Season::all(); ?>

			<?php if ( empty( $seasons ) ) : ?>
			<div class="kurs-empty-state">
				<p>Nema sezona. Kreiraj prvu sezonu &rarr;</p>
			</div>
			<?php else : ?>

			<div class="kurs-panel">
				<div class="kurs-panel__head">
					<h3 class="kurs-panel__title">Sve sezone</h3>
					<span style="font-family:var(--font-mono);font-size:9px;color:var(--ka-muted);text-transform:uppercase;">
						<?php echo count( $seasons ); ?> ukupno
					</span>
				</div>
				<div class="kurs-panel__body" style="padding:0;">
				<?php foreach ( $seasons as $s ) :
					$bar_color = Kurs_Season::is_pan_league( $s->league ) ? '#F47B16' : '#DA0F1E';
				?>
				<div style="display:grid;grid-template-columns:4px 1fr auto;align-items:center;padding:12px 18px;border-bottom:1px solid #f5f5ef;gap:14px;">
					<div style="width:4px;height:36px;border-radius:2px;background:<?php echo $bar_color; ?>;flex-shrink:0;"></div>
					<div>
						<div style="font-family:var(--font-cond);font-weight:800;font-size:15px;"><?php echo esc_html( $s->name ); ?></div>
						<div style="display:flex;align-items:center;gap:8px;margin-top:3px;">
							<?php echo kurs_league_icon( $s->league, 20 ); ?>
							<span class="kurs-tag kurs-tag--<?php echo esc_attr( $s->league ); ?>">
								<?php echo Kurs_Season::is_pan_league( $s->league ) ? 'Pan Ljetna' : 'KC Liga'; ?>
							</span>
							<span class="kurs-badge kurs-badge--<?php echo esc_attr( $s->status ); ?>">
								<?php echo $s->status === 'active' ? 'Aktivna' : 'Arhiva'; ?>
							</span>
							<span style="font-family:var(--font-mono);font-size:9px;color:var(--ka-muted);"><?php echo esc_html( $s->year ); ?></span>
							<?php if ( ! empty( $s->locked ) ) : ?>
							<span class="kurs-badge" style="background:#111317;color:#FDE206;">&#128274; Zaključano</span>
							<?php endif; ?>
						</div>
					</div>
					<div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end;">
						<?php if ( empty( $s->locked ) ) : ?>
						<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $s->id . '&step=2' ); ?>"
						   class="kurs-btn kurs-btn--red kurs-btn--sm">Postavi</a>
						<?php endif; ?>
						<a href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $s->id ); ?>"
						   class="kurs-btn kurs-btn--outline kurs-btn--sm">Utakmice</a>
						<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons&action=edit&id=' . $s->id ); ?>"
						   class="kurs-btn kurs-btn--ghost kurs-btn--sm">&#9998;</a>
						<form method="post" style="display:inline;"<?php echo empty( $s->locked ) ? ' onsubmit="return confirm(\'Zaključati sezonu &quot;' . esc_js( $s->name ) . '&quot;? Podaci postaju konačni i skriva se postavljanje lige. Možeš ju kasnije otključati.\')"' : ''; ?>>
							<?php wp_nonce_field( 'kurs_toggle_lock' ); ?>
							<input type="hidden" name="kurs_action" value="toggle_season_lock">
							<input type="hidden" name="season_id"   value="<?php echo (int) $s->id; ?>">
							<input type="hidden" name="lock"        value="<?php echo empty( $s->locked ) ? '1' : ''; ?>">
							<button type="submit" class="kurs-btn kurs-btn--sm <?php echo empty( $s->locked ) ? 'kurs-btn--dark' : 'kurs-btn--outline'; ?>" title="<?php echo empty( $s->locked ) ? 'Zaključaj sezonu (konačni podaci)' : 'Otključaj sezonu'; ?>">
								<?php echo empty( $s->locked ) ? '&#128274; Zaključaj' : '&#128275; Otključaj'; ?>
							</button>
						</form>
						<?php if ( empty( $s->locked ) ) : ?>
						<form method="post" style="display:inline;"
						      onsubmit="return confirm('Sigurno želiš obrisati sezonu &quot;<?php echo esc_js( $s->name ); ?>&quot;?\n\nOvo će obrisati sve podatke ove sezone. Radnja se ne može poništiti!');">
							<?php wp_nonce_field( 'kurs_delete_season' ); ?>
							<input type="hidden" name="kurs_action" value="delete_season">
							<input type="hidden" name="season_id"   value="<?php echo (int) $s->id; ?>">
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm" style="color:#c00 !important;border-color:#c00 !important;" title="Obriši sezonu">&times;</button>
						</form>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<!-- ── Forma ─────────────────────────────────────────────────────── -->
		<div>
			<div class="kurs-panel">
				<div class="kurs-panel__head">
					<h3 class="kurs-panel__title">
						<?php echo $edit_item ? 'Uredi sezonu' : 'Nova sezona'; ?>
					</h3>
					<?php if ( $edit_item ) : ?>
					<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>"
					   class="kurs-btn kurs-btn--ghost kurs-btn--sm">Odustani</a>
					<?php endif; ?>
				</div>
				<div class="kurs-panel__body" style="padding:18px;">

				<?php if ( $edit_item ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'kurs_edit_season' ); ?>
					<input type="hidden" name="kurs_action" value="edit_season">
					<input type="hidden" name="season_id"   value="<?php echo (int) $edit_item->id; ?>">

					<div class="kurs-form-col" style="gap:12px;">
						<div>
							<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Liga / natjecanje</label>
							<select name="league" style="width:100%;">
								<option value="kc_liga"    <?php selected( $edit_item->league, 'kc_liga' ); ?>>KC Liga</option>
								<option value="pan_ljetna" <?php selected( $edit_item->league, 'pan_ljetna' ); ?>>Pan Ljetna Liga</option>
							</select>
						</div>
						<div>
							<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Puni naziv sezone</label>
							<input type="text" name="name" class="regular-text" style="width:100%;"
							       value="<?php echo esc_attr( $edit_item->name ); ?>" required>
						</div>
						<div class="kurs-stack-mobile" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
							<div>
								<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Godina</label>
								<input type="number" name="year" class="small-text" style="width:100%;"
								       value="<?php echo (int) $edit_item->year; ?>" min="2000" max="2100" required>
							</div>
							<div>
								<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Status</label>
								<select name="status" style="width:100%;">
									<option value="active"   <?php selected( $edit_item->status, 'active' ); ?>>Aktivna</option>
									<option value="archived" <?php selected( $edit_item->status, 'archived' ); ?>>Arhiva</option>
								</select>
							</div>
						</div>
						<div>
								<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Max faulova po poluvremenu (akumulirani)</label>
								<input type="number" name="max_fouls_per_half" class="small-text" style="width:100%;"
								       value="<?php echo (int) ( $edit_item->max_fouls_per_half ?? 5 ); ?>" min="1" max="20" required>
								<p style="margin:4px 0 0;font-size:11px;color:var(--ka-muted);">Nakon ovog broja prekršaja po poluvremenu svaki je sljedeći akumulirani penal (zadano 5).</p>
							</div>
							<button type="submit" class="kurs-btn kurs-btn--red" style="margin-top:4px;">Spremi promjene</button>
					</div>
				</form>

				<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'kurs_create_season' ); ?>
					<input type="hidden" name="kurs_action" value="create_season">

					<div class="kurs-form-col" style="gap:12px;">
						<div>
							<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Liga / natjecanje</label>
							<select name="league" style="width:100%;">
								<option value="kc_liga">KC Liga</option>
								<option value="pan_ljetna">Pan Ljetna Liga</option>
							</select>
						</div>
						<div>
							<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Puni naziv sezone</label>
							<input type="text" name="name" class="regular-text" style="width:100%;"
							       placeholder="Npr. KC Liga 2026/27" required>
						</div>
						<div>
							<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Godina</label>
							<input type="number" name="year" class="small-text" style="width:100%;"
							       value="<?php echo (int) date( 'Y' ); ?>" min="2000" max="2100" required>
						</div>
						<div>
							<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);display:block;margin-bottom:4px;">Max faulova po poluvremenu (akumulirani)</label>
							<input type="number" name="max_fouls_per_half" class="small-text" style="width:100%;"
							       value="5" min="1" max="20" required>
							<p style="margin:4px 0 0;font-size:11px;color:var(--ka-muted);">Nakon ovog broja prekršaja po poluvremenu svaki je sljedeći akumulirani penal (zadano 5).</p>
						</div>
						<button type="submit" class="kurs-btn kurs-btn--red" style="margin-top:4px;">
							+ Kreiraj sezonu &rarr;
						</button>
					</div>
				</form>
				<div class="kurs-notice" style="margin-top:14px;">
					<strong>Sljedeći korak:</strong> Nakon kreiranja sezone, postavi grupe, dodaj ekipe i generiraj raspored utakmica.
				</div>
				<?php endif; ?>

				</div>
			</div>
		</div>

	</div>
</div>
