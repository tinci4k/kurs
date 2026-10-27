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

	// Lige u kojima ekipa igra; roster (igrač × ekipa × liga) se uređuje na stranici natjecanja → Ekipe.
	$division_id = absint( $_GET['division_id'] ?? 0 );
	$contexts    = Kurs_Season::get_team_contexts( $edit_id );
	$active_ctx  = null;
	foreach ( $contexts as $ctx ) {
		if ( (int) $ctx->division_id === $division_id ) { $active_ctx = $ctx; break; }
	}
	// Forme (osnovno, logo, baneri, slika) vraćaju na istu ligu — inače se gubi „Natrag na ligu".
	$keep_div  = $active_ctx ? "&division_id={$division_id}" : '';
	global $wpdb;
	$ctx_players = array_map( 'intval', array_column( $wpdb->get_results( $wpdb->prepare(
		"SELECT division_id, COUNT(*) AS n FROM {$wpdb->prefix}" . KURS_PREFIX . 'squads WHERE team_id = %d GROUP BY division_id', $edit_id
	) ), 'n', 'division_id' ) );

	$cur_color   = kurs_team_color( $edit_id );
	$presets     = [ '#DA0F1E','#1a3a6b','#1F7A43','#F47B16','#FDE206','#7c3aed','#0ea5e9','#111317','#ffffff','#6b7280' ];
	$coach       = kurs_team_coach( $edit_id );
	$rep         = kurs_team_rep( $edit_id );
	$banners     = kurs_team_banners( $edit_id, 'medium' );
	$photo       = kurs_team_photo( $edit_id, 'medium' );
	$cur_captain = (int) get_post_meta( $edit_id, '_kurs_team_captain', true );
	$cap_players = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT sq.player_id, p.post_title AS name
		 FROM {$wpdb->prefix}" . KURS_PREFIX . "squads sq
		 JOIN {$wpdb->posts} p ON p.ID = sq.player_id
		 WHERE sq.team_id = %d AND p.post_status = 'publish'
		 ORDER BY p.post_title ASC",
		$edit_id
	) );

	/* Ako je otvoreno iz lige (division_id), „natrag" vodi na kartu Ekipe te sezone */
	$ctx_season = $active_ctx ? (int) $active_ctx->season_id : 0;
	?>
	<div class="wrap kurs-wrap">

		<div class="kurs-breadcrumb">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga</a>
			<span class="kurs-breadcrumb__sep">/</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams' ) ); ?>">Ekipe</a>
			<span class="kurs-breadcrumb__sep">/</span>
			<?php echo esc_html( $edit_team->post_title ); ?>
		</div>

		<div class="kurs-page-hd">
			<div style="display:flex;align-items:center;gap:14px;">
				<?php echo kurs_team_badge_html( $edit_id, 42 ); ?>
				<div>
					<h1 style="margin:0;"><?php echo esc_html( $edit_team->post_title ); ?></h1>
					<div class="kurs-team-ctx">
						<?php if ( $contexts ) : foreach ( array_slice( $contexts, 0, 4 ) as $ctx ) : ?>
						<a class="kurs-league-tag kurs-league-tag--<?php echo Kurs_Season::is_pan_league( (string) $ctx->league ) ? 'pan' : ( stripos( $ctx->division_name, 'briga' ) !== false ? 'lbb' : 'kc' ); ?>"
						   href="<?php echo esc_url( Kurs_Admin::roster_url( $edit_id, (int) $ctx->division_id ) ); ?>" title="Igrači ekipe u ovoj ligi"><?php echo esc_html( $ctx->season_name . ' · ' . $ctx->division_name ); ?></a>
						<?php endforeach; else : ?>
						<span class="kurs-muted">Ne igra ni u jednoj ligi</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php if ( $ctx_season ) : ?>
			<a href="<?php echo esc_url( Kurs_Admin::roster_url( $edit_id, $division_id ) ); ?>" class="kurs-btn kurs-btn--ghost kurs-btn--sm">&larr; Natrag na roster</a>
			<?php else : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams' ) ); ?>" class="kurs-btn kurs-btn--ghost kurs-btn--sm">&larr; Sve ekipe</a>
			<?php endif; ?>
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
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}{$keep_div}&updated=1" ) ); ?>">
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
									<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}{$keep_div}" ) ); ?>">
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
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}{$keep_div}" ) ); ?>">

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
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( "admin.php?page=kurs-teams&action=edit&id={$edit_id}{$keep_div}" ) ); ?>">
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm" onclick="return confirm('Ukloniti sliku momčadi?')">&#215; Ukloni</button>
						</form>
						<?php endif; ?>
						<p class="kurs-muted" style="margin-top:8px;font-size:12px;">Skupna fotografija ekipe (npr. ispred gola). Prikazuje se na javnom profilu ekipe.</p>
					</div>
				</div>

			</div>

			<!-- ── Desno: igrači po ligama (roster se uređuje na stranici natjecanja) ── -->
			<div>
				<div class="kurs-panel">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Igrači po ligama</h3></div>
					<div class="kurs-panel__body" style="padding:0;">
					<?php if ( empty( $contexts ) ) : ?>
					<div style="padding:16px 18px;">
						<p class="kurs-muted">Igrači se upisuju za ligu u kojoj ekipa igra. Ekipa još nije ni u jednoj ligi — uvrsti je na stranici natjecanja → kartica Ekipe.</p>
						<div style="display:flex;gap:6px;flex-wrap:wrap;">
						<?php foreach ( Kurs_Admin::HUBS as $hslug => $hleague ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $hslug . '&tab=ekipe' ) ); ?>" class="kurs-btn kurs-btn--outline kurs-btn--sm">
							<?php echo esc_html( Kurs_Season::competition( $hleague )['label'] ?? '' ); ?> → Ekipe
						</a>
						<?php endforeach; ?>
						</div>
					</div>
					<?php else : foreach ( $contexts as $ctx ) :
						$n = (int) ( $ctx_players[ (int) $ctx->division_id ] ?? 0 ); ?>
					<a class="kurs-ctx-roster" href="<?php echo esc_url( Kurs_Admin::roster_url( $edit_id, (int) $ctx->division_id ) ); ?>">
						<span>
							<strong><?php echo esc_html( $ctx->division_name ); ?></strong>
							<span class="kurs-muted"><?php echo esc_html( $ctx->season_name ); ?></span>
						</span>
						<span class="kurs-ctx-roster__n<?php echo $n ? '' : ' is-empty'; ?>"><?php echo $n; ?> igr.</span>
						<span class="kurs-ctx-roster__go">Roster &rarr;</span>
					</a>
					<?php endforeach; ?>
					<p class="kurs-muted" style="padding:10px 18px 14px;margin:0;font-size:11px;">Roster (igrači s brojevima dresova) vodi se po ligi i sezoni, na stranici natjecanja → Ekipe. Podatke igrača (godina rođenja, fotografija, spajanje duplikata) uređuješ u <a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-players' ) ); ?>">Igrači</a>.</p>
					<?php endif; ?>
					</div>
				</div>
			</div>

		</div>
	</div>
	<?php
	return; // Izlaz — ne prikazuj listu ispod
}


// ── LISTA EKIPA ─────────────────────────────────────────────────────────────
// Ekipa (klub) je zajednički registar: upisuje se jednom i može igrati u više
// sezona i natjecanja. Uz svaku piše gdje igra u AKTUALNIM sezonama.
global $wpdb;
$p     = $wpdb->prefix . KURS_PREFIX;
$teams = get_posts( [
	'post_type'      => 'kurs_ekipa',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'post_status'    => 'publish',
] );

$current_ids = array_filter( array_map( fn( $l ) => (int) ( Kurs_Season::current_for_league( Kurs_Season::is_pan_league( $l ) )->id ?? 0 ), Kurs_Admin::HUBS ) );
$where_now   = []; // team_id => [ row … ] (aktualne sezone)
$seasons_ago = []; // team_id => broj ranijih sezona
foreach ( $wpdb->get_results(
	"SELECT DISTINCT gt.team_id, s.id AS season_id, s.name AS season_name, s.league, d.name AS div_name, g.name AS group_name,
	        ( SELECT COUNT(*) FROM {$p}groups g2 WHERE g2.phase_id = g.phase_id ) AS group_count
	 FROM {$p}group_teams gt
	 JOIN {$p}groups g ON g.id = gt.group_id JOIN {$p}phases ph ON ph.id = g.phase_id
	 JOIN {$p}divisions d ON d.id = ph.division_id JOIN {$p}seasons s ON s.id = d.season_id
	 ORDER BY s.year DESC, s.id DESC"
) as $r ) {
	if ( in_array( (int) $r->season_id, $current_ids, true ) ) $where_now[ (int) $r->team_id ][] = $r;
	else $seasons_ago[ (int) $r->team_id ][ (int) $r->season_id ] = true;
}
$players = [];
// Igrači u aktivnim sezonama (ne zbroj kroz sve godine)
foreach ( $wpdb->get_results( "SELECT sq.team_id, COUNT(DISTINCT sq.player_id) AS n FROM {$p}squads sq
	JOIN {$p}divisions d ON d.id = sq.division_id JOIN {$p}seasons s ON s.id = d.season_id
	WHERE s.status = 'active' GROUP BY sq.team_id" ) as $r ) $players[ (int) $r->team_id ] = (int) $r->n;

$filter  = sanitize_key( $_GET['filter'] ?? '' );
$n_now   = count( array_filter( $teams, fn( $t ) => ! empty( $where_now[ $t->ID ] ) ) );
$n_free  = count( $teams ) - $n_now;
$shown   = array_filter( $teams, fn( $t ) => $filter === 'aktualne' ? ! empty( $where_now[ $t->ID ] ) : ( $filter === 'bez' ? empty( $where_now[ $t->ID ] ) : true ) );
$f_url   = fn( string $f ) => admin_url( 'admin.php?page=kurs-teams' . ( $f ? '&filter=' . $f : '' ) );
?>
<div class="wrap kurs-wrap">

	<div class="kurs-breadcrumb">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span> Ekipe
	</div>
	<div class="kurs-page-hd"><h1>Ekipe</h1></div>

	<p class="kurs-hub-intro">
		Ovo je <strong>popis svih klubova</strong>. Ekipa se upisuje jednom i može igrati više sezona i u oba natjecanja.
		U ligu je uvrštavaš na stranici natjecanja (<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-kc&tab=ekipe' ) ); ?>">KC Liga → Ekipe</a>,
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-pan&tab=ekipe' ) ); ?>">Pan Ljetna liga → Ekipe</a>); tamo, klikom na „N igr.", uređuješ i igrače ekipe za svaku ligu (roster). Sve igrače vidiš u <a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-players' ) ); ?>">Igrači</a>.
	</p>

	<?php if ( ! empty( $_GET['added'] ) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Ekipa je dodana.</div>
	<?php elseif ( ! empty( $_GET['updated'] ) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Ekipa je ažurirana.</div>
	<?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Ekipa je obrisana.</div>
	<?php elseif ( ! empty( $_GET['exists'] ) && get_post_type( absint( $_GET['exists'] ) ) === 'kurs_ekipa' ) : ?>
	<div class="notice notice-warning is-dismissible"><p>Ekipa „<?php echo esc_html( get_the_title( absint( $_GET['exists'] ) ) ); ?>" već postoji — nije dodana ponovno.
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams&action=edit&id=' . absint( $_GET['exists'] ) ) ); ?>">Otvori je &rarr;</a></p></div>
	<?php elseif ( ! empty( $_GET['in_use'] ) ) : ?>
	<div class="notice notice-error is-dismissible"><p>Ekipa ima <?php echo (int) $_GET['in_use']; ?> utakmica i ne može se obrisati — to bi pokvarilo ljestvice i zapisnike. Možeš joj promijeniti naziv.</p></div>
	<?php endif; ?>

	<div class="kurs-two-col">

		<!-- ── Lista ekipa ───────────────────────────────────────────────── -->
		<div>
			<div class="kurs-panel">
				<div class="kurs-panel__head">
					<nav class="kurs-hub-sub kurs-hub-sub--inline" aria-label="Filter">
						<a href="<?php echo esc_url( $f_url( '' ) ); ?>" class="<?php echo $filter === '' ? 'is-active' : ''; ?>">Sve <span><?php echo count( $teams ); ?></span></a>
						<a href="<?php echo esc_url( $f_url( 'aktualne' ) ); ?>" class="<?php echo $filter === 'aktualne' ? 'is-active' : ''; ?>">Igraju sada <span><?php echo (int) $n_now; ?></span></a>
						<a href="<?php echo esc_url( $f_url( 'bez' ) ); ?>" class="<?php echo $filter === 'bez' ? 'is-active' : ''; ?>">Bez lige <span><?php echo (int) $n_free; ?></span></a>
					</nav>
				</div>

				<?php if ( empty( $shown ) ) : ?>
				<div class="kurs-panel__empty"><?php echo $teams ? 'Nema ekipa za ovaj filter.' : 'Nema ekipa. Dodaj prvu ekipu →'; ?></div>
				<?php else : ?>
				<div class="kurs-panel__body" style="padding:0;">
				<?php foreach ( $shown as $t ) :
					$now_rows = $where_now[ $t->ID ] ?? [];
					$ago      = count( $seasons_ago[ $t->ID ] ?? [] );
				?>
				<div class="kurs-team-row">
					<div class="kurs-logo-cell" id="logo-cell-<?php echo $t->ID; ?>"><?php echo kurs_team_badge_html( $t->ID, 40 ); ?></div>

					<div class="kurs-team-row__main">
						<a class="kurs-team-row__name" href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams&action=edit&id=' . $t->ID ) ); ?>"><?php echo esc_html( $t->post_title ); ?></a>
						<div class="kurs-team-row__where">
							<?php foreach ( $now_rows as $r ) :
								$cls = Kurs_Season::is_pan_league( (string) $r->league ) ? 'pan' : ( stripos( $r->div_name, 'briga' ) !== false ? 'lbb' : 'kc' );
							?>
							<a class="kurs-league-tag kurs-league-tag--<?php echo $cls; ?>" href="<?php echo esc_url( Kurs_Admin::hub_url( (int) $r->season_id, 'ekipe' ) ); ?>"
							   title="<?php echo esc_attr( $r->season_name ); ?>"><?php echo esc_html( $r->div_name . ( (int) $r->group_count > 1 ? ' · ' . $r->group_name : '' ) ); ?></a>
							<?php endforeach; ?>
							<?php if ( ! $now_rows ) : ?><span class="kurs-team-row__free">Ne igra u aktualnim sezonama</span><?php endif; ?>
							<?php if ( $ago ) : ?><span class="kurs-muted">· ranije <?php echo (int) $ago; ?> <?php echo $ago === 1 ? 'sezona' : ( $ago < 5 ? 'sezone' : 'sezona' ); ?></span><?php endif; ?>
						</div>
					</div>

					<div class="kurs-team-row__players" title="Igrači u rosterima aktivnih sezona"><?php echo (int) ( $players[ $t->ID ] ?? 0 ); ?> <span>igrača</span></div>

					<div style="display:flex;gap:5px;align-items:center;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams&action=edit&id=' . $t->ID ) ); ?>"
						   class="kurs-btn kurs-btn--outline kurs-btn--sm" title="Uredi ekipu (logo, boja, trener, sponzori)">&#9998; Uredi</a>
						<form method="post" style="display:inline;"
						      onsubmit="return confirm('Sigurno želiš obrisati ekipu &quot;<?php echo esc_js( $t->post_title ); ?>&quot;?');">
							<?php wp_nonce_field( 'kurs_delete_team' ); ?>
							<input type="hidden" name="kurs_action" value="delete_team">
							<input type="hidden" name="team_id"     value="<?php echo (int) $t->ID; ?>">
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm" title="Obriši ekipu"
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
					<h3 class="kurs-panel__title">Nova ekipa</h3>
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
					<p class="kurs-muted" style="margin:12px 0 0;font-size:12px;">Nakon dodavanja uvrsti je u ligu na stranici natjecanja → Ekipe. Logo, boju i trenera uređuješ klikom na „Uredi", a igrače u rosteru ekipe na stranici natjecanja.</p>
				</div>
			</div>
		</div>

	</div>
</div>
