<?php
defined( 'ABSPATH' ) || exit;

$season_id = absint( $_GET['season_id'] ?? 0 );
$step      = absint( $_GET['step'] ?? 2 );

/* Bez season_id → preusmjeri na prvu aktivnu sezonu */
if ( ! $season_id ) {
	$all_seasons = Kurs_Season::all();
	if ( empty( $all_seasons ) ) {
		wp_die( 'Nema kreiranih sezona. <a href="' . admin_url( 'admin.php?page=kurs-seasons' ) . '">Kreiraj sezonu &rarr;</a>' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=kurs-season-setup&season_id=' . (int) $all_seasons[0]->id . '&step=' . $step ) );
	exit;
}

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

/* Odredi status svakog koraka */
$step2_done = ! empty( $divisions );
$step3_done = false;
if ( $step2_done ) {
	foreach ( $divisions as $d ) {
		foreach ( Kurs_Season::get_phases( (int) $d->id ) as $ph ) {
			foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
				if ( count( Kurs_Season::get_group_teams( (int) $g->id ) ) > 0 ) {
					$step3_done = true; break 3;
				}
			}
		}
	}
}
$step4_done = false; // "pregled" je uvijek dostupan

$bar_color = Kurs_Season::is_pan_league( $season->league ) ? '#F47B16' : '#DA0F1E';
?>
<div class="wrap kurs-wrap kurs-wizard-wrap">

	<!-- ── Breadcrumb + naslov ───────────────────────────────────────────── -->
	<div class="kurs-breadcrumb">
		<a href="<?php echo admin_url( 'admin.php?page=kurs-dashboard' ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>">Sezone</a>
		<span class="kurs-breadcrumb__sep">/</span>
		<?php echo esc_html( $season->name ); ?>
	</div>
	<div class="kurs-page-hd">
		<h1><?php echo esc_html( $season->name ); ?>
			<span style="display:inline-block;vertical-align:middle;margin-left:10px;"><?php echo kurs_league_icon( $season->league, 26 ); ?></span>
			<span class="kurs-tag kurs-tag--<?php echo esc_attr( $season->league ); ?>" style="font-size:.55em;vertical-align:middle;margin-left:6px;">
				<?php echo Kurs_Season::is_pan_league( $season->league ) ? 'Pan Ljetna Liga' : 'KC Liga'; ?>
			</span>
		</h1>
	</div>

	<!-- ── Stepper ───────────────────────────────────────────────────────── -->
	<?php if ( ! empty( $season->locked ) ) : ?>
	<div class="kurs-card" style="text-align:center;padding:36px;">
		<div style="font-size:34px;line-height:1;margin-bottom:10px;">&#128274;</div>
		<h2 style="font-family:var(--font-cond);font-weight:800;font-style:italic;text-transform:uppercase;margin:0 0 6px;">Sezona je zaključana</h2>
		<p style="color:var(--ka-muted);margin:0 auto 18px;max-width:460px;">Podaci ove sezone su konačni (zaključani). Za izmjenu postave lige prvo otključaj sezonu.</p>
		<form method="post" style="display:inline;margin-right:6px;">
			<?php wp_nonce_field( 'kurs_toggle_lock' ); ?>
			<input type="hidden" name="kurs_action" value="toggle_season_lock">
			<input type="hidden" name="season_id" value="<?php echo (int) $season->id; ?>">
			<input type="hidden" name="lock" value="">
			<button type="submit" class="kurs-btn kurs-btn--dark">&#128275; Otključaj sezonu</button>
		</form>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>" class="kurs-btn kurs-btn--outline">Natrag na sezone</a>
	</div>
	</div><!-- /.kurs-wrap (zaključano) -->
	<?php return; endif; ?>

	<div class="kurs-stepper">
		<?php
		/* Korak 1 — uvijek "done" jer sezona postoji */
		?>
		<span class="kurs-step kurs-step--done">
			<span class="kurs-step__num">✓</span>
			<span>
				<div class="kurs-step__label">Osnovno</div>
				<div class="kurs-step__sub">Sezona kreirana</div>
			</span>
		</span>

		<?php
		$s2_class = $step === 2 ? 'kurs-step--active' : ( $step2_done ? 'kurs-step--done' : 'kurs-step--locked' );
		?>
		<a href="<?php echo kurs_setup_url( $season_id, 2 ); ?>"
		   class="kurs-step <?php echo $s2_class; ?>">
			<span class="kurs-step__num"><?php echo $step2_done && $step !== 2 ? '✓' : '2'; ?></span>
			<span>
				<div class="kurs-step__label">Format & Divizije</div>
				<div class="kurs-step__sub">Grupe i faze</div>
			</span>
		</a>

		<?php
		$s3_can  = $step2_done;
		$s3_class = $step === 3 ? 'kurs-step--active' : ( $step3_done ? 'kurs-step--done' : ( $s3_can ? '' : 'kurs-step--locked' ) );
		?>
		<a href="<?php echo $s3_can ? kurs_setup_url( $season_id, 3 ) : '#'; ?>"
		   class="kurs-step <?php echo $s3_class; ?>"
		   <?php if ( ! $s3_can ) echo 'tabindex="-1" aria-disabled="true"'; ?>>
			<span class="kurs-step__num"><?php echo $step3_done && $step !== 3 ? '✓' : '3'; ?></span>
			<span>
				<div class="kurs-step__label">Dodjela ekipa</div>
				<div class="kurs-step__sub">Rasporedi ekipe u grupe</div>
			</span>
		</a>

		<a href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $season_id ); ?>"
		   class="kurs-step <?php echo $step === 4 ? 'kurs-step--active' : ( $step3_done ? '' : 'kurs-step--locked' ); ?>"
		   <?php if ( ! $step3_done ) echo 'tabindex="-1" aria-disabled="true"'; ?>>
			<span class="kurs-step__num">4</span>
			<span>
				<div class="kurs-step__label">Utakmice</div>
				<div class="kurs-step__sub">Generiraj raspored</div>
			</span>
		</a>
	</div>

	<?php if ( $step === 2 ) :
	/* ══════════════════════════════════════════════════════════════════════════
	   KORAK 2 — Format & Divizije
	   ══════════════════════════════════════════════════════════════════════════ */
	?>
	<div class="kurs-setup-layout">

		<!-- ── Sidebar: Dodaj diviziju ───────────────────────────────────── -->
		<div class="kurs-setup-sidebar">
			<div class="kurs-card">
				<div class="kurs-card__head">
					<h3>Dodaj diviziju</h3>
				</div>
				<p class="kurs-muted" style="margin-bottom:10px;">Npr. "Glavna liga", "Liga bez briga" ili ostavi jednu za ligaški format.</p>
				<form method="post">
					<?php wp_nonce_field( 'kurs_season_setup' ); ?>
					<input type="hidden" name="kurs_action" value="add_division">
					<input type="hidden" name="season_id"  value="<?php echo $season_id; ?>">
					<div class="kurs-form-col">
						<input type="text" name="div_name" class="regular-text" placeholder="Naziv divizije" required>
						<label style="font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);margin-top:6px;">&#127942; Format natjecanja</label>
						<select name="has_playoff" onchange="this.closest('form').querySelector('.kurs-div-advance-row').style.display=this.value==='1'?'flex':'none';">
							<option value="1">Skupine + playoff</option>
							<option value="0">Samo skupina/liga (bez playoffa)</option>
						</select>
						<label class="kurs-div-advance-row" style="display:flex;align-items:center;gap:8px;font-size:13px;margin-top:2px;">
							Prolazi iz svake grupe:
							<input type="number" name="advance_per_group" value="2" min="1" max="8" step="1" style="width:60px;">
						</label>
						<button type="submit" class="kurs-btn kurs-btn--red" style="margin-top:6px;">+ Dodaj diviziju</button>
					</div>
				</form>

				<?php if ( $divisions ) : ?>
				<div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--ka-border);">
					<p class="kurs-muted" style="margin-bottom:8px;">
						Kada dodaš sve divizije, nastavi na dodjelu ekipa.
					</p>
					<a href="<?php echo kurs_setup_url( $season_id, 3 ); ?>"
					   class="kurs-btn kurs-btn--dark" style="width:100%;justify-content:center;">
						Nastavi: Dodjela ekipa &rarr;
					</a>
				</div>
				<?php endif; ?>
			</div>

			<!-- Info box -->
			<div class="kurs-notice" style="margin-top:0;">
				<strong>ℹ Napomena</strong><br>
				Ekipe i igrači vežu se za diviziju pri prijavi — odvojene tablice i statistike po divizijama.
			</div>
		</div>

		<!-- ── Glavno: prikaz divizija ───────────────────────────────────── -->
		<div class="kurs-setup-main">
			<?php if ( empty( $divisions ) ) : ?>
			<div class="kurs-empty-state">
				<p>Dodaj prvu diviziju s lijeve strane da nastaviš postavljanje.</p>
			</div>
			<?php endif; ?>

			<?php foreach ( $divisions as $div ) :
				$phases = Kurs_Season::get_phases( (int) $div->id );
			?>
			<div class="kurs-div-card">
				<div class="kurs-div-card__bar" style="background:<?php echo esc_attr( $bar_color ); ?>;"></div>
				<div class="kurs-div-card__body">
					<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;">
						<div>
							<div class="kurs-div-card__name"><?php echo esc_html( $div->name ); ?></div>
							<div class="kurs-div-card__meta"><?php echo count( $phases ); ?> faza</div>
						</div>
						<form method="post" onsubmit="return confirm('Obriši diviziju <?php echo esc_js( $div->name ); ?> i sve njene faze, grupe i utakmice?');">
							<?php wp_nonce_field( 'kurs_season_setup' ); ?>
							<input type="hidden" name="kurs_action"  value="delete_division">
							<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
							<input type="hidden" name="division_id"  value="<?php echo (int) $div->id; ?>">
							<button type="submit" class="kurs-del-btn" title="Obriši diviziju">&times;</button>
						</form>
					</div>

					<!-- Format natjecanja: playoff da/ne + koliko prolazi iz svake grupe (jednom, na razini divizije) -->
					<form method="post" class="kurs-div-format">
						<?php wp_nonce_field( 'kurs_season_setup' ); ?>
						<input type="hidden" name="kurs_action" value="edit_division">
						<input type="hidden" name="season_id"   value="<?php echo $season_id; ?>">
						<input type="hidden" name="division_id" value="<?php echo (int) $div->id; ?>">
						<span class="kurs-div-format__lbl">&#127942; Format</span>
						<select name="has_playoff" onchange="this.closest('form').querySelector('.kurs-div-advance').style.display=this.value==='1'?'inline-flex':'none';">
							<option value="1" <?php selected( ! empty( $div->has_playoff ) ); ?>>Skupine + playoff</option>
							<option value="0" <?php selected( empty( $div->has_playoff ) ); ?>>Samo skupina (bez playoffa)</option>
						</select>
						<label class="kurs-div-advance" style="display:<?php echo ! empty( $div->has_playoff ) ? 'inline-flex' : 'none'; ?>;align-items:center;gap:6px;font-size:12px;">
							prolazi iz grupe:
							<input type="number" name="advance_per_group" value="<?php echo (int) ( $div->advance_per_group ?? 2 ); ?>" min="1" max="8" step="1" style="width:54px;">
						</label>
						<button type="submit" class="kurs-btn kurs-btn--outline kurs-btn--sm">Spremi</button>
					</form>

					<!-- Faze -->
					<?php foreach ( $phases as $ph ) :
						$groups = Kurs_Season::get_groups( (int) $ph->id );
					?>
					<div class="kurs-phase-block">
						<div class="kurs-phase-block__head">
							<h4><?php echo esc_html( $ph->name ); ?></h4>
							<span class="kurs-phase-pill kurs-phase-pill--<?php echo esc_attr( $ph->type ); ?>">
								<?php echo $ph->type === 'group' ? 'Grupna' : 'Playoff'; ?>
							</span>
							<form method="post" style="margin-left:auto;" onsubmit="return confirm('Obriši fazu \'<?php echo esc_js( $ph->name ); ?>\' i sve njene grupe i utakmice?');">
								<?php wp_nonce_field( 'kurs_season_setup' ); ?>
								<input type="hidden" name="kurs_action"  value="delete_phase">
								<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
								<input type="hidden" name="phase_id"     value="<?php echo (int) $ph->id; ?>">
								<button type="submit" class="kurs-del-btn" title="Obriši fazu" style="font-size:14px;">&times;</button>
							</form>
						</div>

						<?php if ( $ph->type === 'group' ) : ?>
						<div class="kurs-groups-mgr" data-phase-id="<?php echo (int) $ph->id; ?>">
							<div class="kurs-chips">
								<?php foreach ( $groups as $g ) : ?>
								<span class="kurs-chip">
									<?php echo esc_html( $g->name ); ?>
									<button class="kurs-chip__del"
									        data-group-id="<?php echo (int) $g->id; ?>"
									        title="Obriši grupu">&times;</button>
								</span>
								<?php endforeach; ?>
							</div>
							<div class="kurs-form-row" style="margin-top:8px;">
								<input type="text" class="js-group-name small-text" placeholder="Npr. Grupa A">
								<button class="button js-add-group">+ Dodaj grupu</button>
							</div>

							<?php if ( $groups && ! empty( $div->has_playoff ) ) : ?>
							<div class="kurs-muted" style="margin-top:8px;font-size:11px;">
								&#127942; U playoff prolazi <strong><?php echo (int) ( $div->advance_per_group ?? 2 ); ?></strong> iz svake grupe <span style="opacity:.7;">(mijenja se gore, na razini divizije)</span>.
							</div>
							<?php endif; ?>
						</div>
						<?php else : ?>
						<div style="margin-top:8px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-matches&season_id=' . $season_id . '&playoff_div=' . $div->id ) ); ?>"
							   class="kurs-btn kurs-btn--outline kurs-btn--sm">
								&#127942; Upravljaj playoff bracketom &rarr;
							</a>
						</div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>

					<!-- Dodaj fazu -->
					<details class="kurs-add-phase" style="margin-top:10px;">
						<summary>+ Dodaj fazu natjecanja</summary>
						<form method="post" style="margin-top:10px;">
							<?php wp_nonce_field( 'kurs_season_setup' ); ?>
							<input type="hidden" name="kurs_action"  value="add_phase">
							<input type="hidden" name="season_id"    value="<?php echo $season_id; ?>">
							<input type="hidden" name="division_id"  value="<?php echo $div->id; ?>">
							<div class="kurs-form-row">
								<input type="text" name="phase_name" class="regular-text"
								       placeholder="Npr. Grupna faza" required>
								<input type="hidden" name="phase_type" value="group">
								<button type="submit" class="button button-primary">Dodaj fazu</button>
							</div>
						</form>
					</details>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Wizard nav gumbi -->
	<div class="kurs-wizard-nav">
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>"
		   class="kurs-btn kurs-btn--ghost">&larr; Natrag na sezone</a>
		<?php if ( $divisions ) : ?>
		<a href="<?php echo kurs_setup_url( $season_id, 3 ); ?>"
		   class="kurs-btn kurs-btn--red">Dalje: Dodjela ekipa &rarr;</a>
		<?php endif; ?>
	</div>

	<?php elseif ( $step === 3 ) :
	/* ══════════════════════════════════════════════════════════════════════════
	   KORAK 3 — Dodjela ekipa grupama
	   ══════════════════════════════════════════════════════════════════════════ */
	?>
	<script>
	window.KursTeams = <?php echo wp_json_encode( array_map( fn( $t ) => [
		'id'   => $t->ID,
		'name' => $t->post_title,
	], $all_teams ) ); ?>;
	</script>

	<?php if ( empty( $divisions ) ) : ?>
	<div class="kurs-empty-state">
		<p>Prvo postavi divizije i grupe u
		   <a href="<?php echo kurs_setup_url( $season_id, 2 ); ?>">koraku 2</a>.
		</p>
	</div>
	<?php else : ?>

	<?php if ( empty( $all_teams ) ) : ?>
	<div class="kurs-notice">
		<strong>Nema ekipa u sustavu.</strong>
		<a href="<?php echo admin_url( 'post-new.php?post_type=kurs_ekipa' ); ?>">Dodaj ekipe &rarr;</a>
	</div>
	<?php endif; ?>

	<div class="kurs-assign-layout">

		<!-- ── Pool sidebar ──────────────────────────────────────────────── -->
		<div class="kurs-team-pool">
			<h3>Dostupne ekipe</h3>
			<p class="kurs-muted">Odaberi ekipu iz padajućeg izbornika unutar grupe i klikni "Dodaj".</p>

			<?php if ( $all_teams ) : ?>
			<div class="kurs-team-list" id="kurs-team-pool-list">
				<?php foreach ( $all_teams as $t ) : ?>
				<div class="kurs-team-pill" data-team-id="<?php echo $t->ID; ?>">
					<?php echo kurs_team_badge_html( $t->ID, 24 ); ?>
					<span><?php echo esc_html( $t->post_title ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
			<div id="kurs-team-pool-list"></div>
			<?php endif; ?>

			<!-- Nova ekipa -->
			<div class="kurs-new-team-form">
				<h4>+ Nova ekipa</h4>
				<input type="text" id="new-team-name-setup" class="regular-text" placeholder="Naziv ekipe">
				<button class="button button-primary" id="btn-create-team-setup">Kreiraj ekipu</button>
				<p id="new-team-setup-msg" class="kurs-muted" style="margin-top:4px;display:none;"></p>
			</div>
		</div>

		<!-- ── Canvas: grupe po divizijama ──────────────────────────────── -->
		<div class="kurs-groups-canvas">
			<?php foreach ( $divisions as $div ) :
				$phases = Kurs_Season::get_phases( (int) $div->id );
			?>
			<h2><?php echo esc_html( $div->name ); ?></h2>

			<?php foreach ( $phases as $ph ) :
				if ( $ph->type !== 'group' ) continue;
				$groups = Kurs_Season::get_groups( (int) $ph->id );
				if ( empty( $groups ) ) : ?>
				<p class="kurs-muted" style="margin-bottom:10px;">
					Nema grupa u fazi "<?php echo esc_html( $ph->name ); ?>".
					<a href="<?php echo kurs_setup_url( $season_id, 2 ); ?>">Dodaj grupe &rarr;</a>
				</p>
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
							<?php echo kurs_team_badge_html( (int) $gt->team_id, 22 ); ?>
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
		</div>

	</div><!-- /.kurs-assign-layout -->
	<?php endif; ?>

	<!-- Wizard nav gumbi -->
	<div class="kurs-wizard-nav">
		<a href="<?php echo kurs_setup_url( $season_id, 2 ); ?>"
		   class="kurs-btn kurs-btn--ghost">&larr; Natrag: Format</a>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $season_id ); ?>"
		   class="kurs-btn kurs-btn--red">Dalje: Utakmice &rarr;</a>
	</div>

	<?php endif; ?>

</div>
