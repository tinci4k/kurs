<?php
defined( 'ABSPATH' ) || exit;

/*
 * Stranica natjecanja → kartica „Postavke": naziv, godina, status, max faulova; suci sezone;
 * zaključavanje sezone (konačni podaci) i brisanje.
 * Varijable iz season-hub.php: $season, $season_id, $comp, $is_locked, $hub_url.
 */
$self = $hub_url( 'postavke' );

$referees  = Kurs_Season::get_referees( $season_id );
$prev_refs = Kurs_Season::previous_with_referees( $season_id );
$ref_row   = fn( string $ime = '', string $prezime = '' ) => '<div class="kurs-ref-row">'
	. '<input type="text" name="ref_ime[]" value="' . esc_attr( $ime ) . '" placeholder="Ime" maxlength="50" class="kurs-ref-row__input" aria-label="Ime">'
	. '<input type="text" name="ref_prezime[]" value="' . esc_attr( $prezime ) . '" placeholder="Prezime" maxlength="50" class="kurs-ref-row__input" aria-label="Prezime">'
	. '<button type="button" class="kurs-ref-remove" title="Ukloni suca" aria-label="Ukloni suca">&times;</button>'
	. '</div>';
?>

<?php if ( ! empty( $_GET['updated'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Postavke sezone su spremljene.</p></div>
<?php elseif ( ! empty( $_GET['year_taken'] ) && ( $taken = Kurs_Season::get( absint( $_GET['year_taken'] ) ) ) ) : ?>
<div class="notice notice-error is-dismissible"><p>Postavke nisu spremljene: za tu godinu već postoji sezona „<?php echo esc_html( $taken->name ); ?>".</p></div>
<?php elseif ( ! empty( $_GET['refs_saved'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Suci su spremljeni.</p></div>
<?php elseif ( ! empty( $_GET['locked'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Sezona je zaključana — podaci su sada konačni.</p></div>
<?php elseif ( ! empty( $_GET['unlocked'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Sezona je otključana — uređivanje je ponovno moguće.</p></div>
<?php endif; ?>

<div class="kurs-hub-settings">

	<div class="kurs-form-col" style="gap:16px;">
	<div class="kurs-panel">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">Sezona</h3></div>
		<div class="kurs-panel__body" style="padding:18px;">
			<form method="post" class="kurs-form-col" style="gap:14px;">
				<?php wp_nonce_field( 'kurs_edit_season' ); ?>
				<input type="hidden" name="kurs_action" value="edit_season">
				<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">

				<label class="kurs-field-wrap">
					<span class="kurs-field-label">Naziv sezone</span>
					<input type="text" name="name" class="regular-text" style="width:100%;" value="<?php echo esc_attr( $season->name ); ?>" required>
				</label>
				<div class="kurs-stack-mobile" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
					<label class="kurs-field-wrap">
						<span class="kurs-field-label"><?php echo ! empty( $comp['span_year'] ) ? 'Godina početka' : 'Godina'; ?></span>
						<input type="number" name="year" style="width:100%;" value="<?php echo (int) $season->year; ?>" min="2000" max="2100" required>
					</label>
					<label class="kurs-field-wrap">
						<span class="kurs-field-label">Status</span>
						<select name="status" style="width:100%;">
							<option value="active"   <?php selected( $season->status, 'active' ); ?>>Aktivna — prikazuje se kao aktualna</option>
							<option value="archived" <?php selected( $season->status !== 'active', true ); ?>>Arhiva</option>
						</select>
					</label>
				</div>
				<label class="kurs-field-wrap">
					<span class="kurs-field-label">Max faulova po poluvremenu (akumulirani)</span>
					<input type="number" name="max_fouls_per_half" style="width:120px;" value="<?php echo (int) ( $season->max_fouls_per_half ?? 5 ); ?>" min="1" max="20" required>
					<span class="kurs-muted" style="font-size:11px;">Nakon ovog broja prekršaja po poluvremenu svaki je sljedeći akumulirani penal (zadano 5).</span>
				</label>
				<p class="kurs-muted" style="margin:0;font-size:12px;">
					Natjecanje (<?php echo esc_html( $comp['label'] ?? $season->league ); ?>) se ne mijenja — o njemu ovise lige i skupine.
					Na webu se kao aktualna prikazuje najnovija <em>aktivna</em> sezona natjecanja.
				</p>
				<div><button type="submit" class="kurs-btn kurs-btn--red">Spremi</button></div>
			</form>
		</div>
	</div>

	<div class="kurs-panel">
		<div class="kurs-panel__head">
			<h3 class="kurs-panel__title">Suci</h3>
			<span class="kurs-ref-count" id="kurs-ref-count"><?php echo count( $referees ); ?></span>
		</div>
		<div class="kurs-panel__body" style="padding:18px;">
			<p class="kurs-muted" style="margin:0 0 14px;font-size:12px;">
				U zapisniku utakmica ove sezone nude se kao prijedlozi, a sudac upisan u zapisniku sam se dodaje ovdje.
				Nova sezona automatski preuzima suce iz prethodne sezone natjecanja.
			</p>
			<form method="post" id="kurs-refs-editor">
				<?php wp_nonce_field( 'kurs_save_season_referees' ); ?>
				<input type="hidden" name="kurs_action" value="save_season_referees">
				<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">

				<div class="kurs-ref-rows" id="kurs-ref-rows">
					<?php foreach ( $referees as $r ) echo $ref_row( $r['ime'], $r['prezime'] ); ?>
				</div>
				<p class="kurs-ref-empty" id="kurs-ref-empty"<?php echo $referees ? ' hidden' : ''; ?>>Još nema sudaca — dodaj prvog.</p>
				<template id="kurs-ref-row-tpl"><?php echo $ref_row(); ?></template>

				<div class="kurs-ref-actions">
					<button type="button" class="kurs-btn kurs-btn--outline kurs-btn--sm" id="kurs-ref-add">+ Dodaj suca</button>
					<?php if ( $prev_refs ) : ?>
					<button type="button" class="kurs-btn kurs-btn--ghost kurs-btn--sm" id="kurs-ref-import"
					        data-refs="<?php echo esc_attr( wp_json_encode( Kurs_Season::get_referees( (int) $prev_refs->id ) ) ); ?>">
						&darr; Preuzmi iz sezone <?php echo esc_html( $prev_refs->name ); ?> <span class="kurs-ref-import__n"></span>
					</button>
					<?php endif; ?>
				</div>
				<div class="kurs-ref-save">
					<button type="submit" class="kurs-btn kurs-btn--red">Spremi suce</button>
					<span class="kurs-ref-dirty" id="kurs-ref-dirty" hidden>Nespremljene promjene</span>
				</div>
			</form>
		</div>
	</div>
	</div>

	<div class="kurs-form-col" style="gap:16px;">
		<div class="kurs-panel">
			<div class="kurs-panel__head"><h3 class="kurs-panel__title"><?php echo $is_locked ? '&#128274; Sezona je zaključana' : 'Zaključavanje'; ?></h3></div>
			<div class="kurs-panel__body" style="padding:18px;">
				<p style="margin:0 0 12px;font-size:13px;color:#555;">
					<?php echo $is_locked
						? 'Podaci sezone su konačni: ekipe i struktura se ne mogu mijenjati, a na webu piše „Završeno · konačni rezultati".'
						: 'Kad je sezona gotova, zaključaj je: podaci postaju konačni, a na webu se prikazuje „Završeno · konačni rezultati". Može se otključati.'; ?>
				</p>
				<form method="post"<?php echo $is_locked ? '' : ' onsubmit="return confirm(\'Zaključati sezonu? Podaci postaju konačni. Možeš je kasnije otključati.\')"'; ?>>
					<?php wp_nonce_field( 'kurs_toggle_lock' ); ?>
					<input type="hidden" name="kurs_action" value="toggle_season_lock">
					<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">
					<input type="hidden" name="lock"        value="<?php echo $is_locked ? '' : '1'; ?>">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $self ); ?>">
					<button type="submit" class="kurs-btn <?php echo $is_locked ? 'kurs-btn--outline' : 'kurs-btn--dark'; ?>">
						<?php echo $is_locked ? '&#128275; Otključaj sezonu' : '&#128274; Zaključaj sezonu'; ?>
					</button>
				</form>
			</div>
		</div>

		<?php if ( ! $is_locked ) : ?>
		<div class="kurs-panel kurs-panel--danger">
			<div class="kurs-panel__head"><h3 class="kurs-panel__title">Obriši sezonu</h3></div>
			<div class="kurs-panel__body" style="padding:18px;">
				<p style="margin:0 0 12px;font-size:13px;color:#555;">Briše lige, raspored, rezultate, zapisnike i rostere ove sezone. Ekipe i igrači ostaju u registru. <strong>Ne može se poništiti.</strong></p>
				<form method="post" onsubmit="return confirm('Sigurno obrisati sezonu &quot;<?php echo esc_js( $season->name ); ?>&quot;?\n\nBrišu se svi podaci ove sezone. Radnja se ne može poništiti!');">
					<?php wp_nonce_field( 'kurs_delete_season' ); ?>
					<input type="hidden" name="kurs_action" value="delete_season">
					<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">
					<button type="submit" class="kurs-btn kurs-btn--ghost" style="color:#c00 !important;border-color:#c00 !important;">&times; Obriši sezonu</button>
				</form>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>
