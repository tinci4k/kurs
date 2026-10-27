<?php
defined( 'ABSPATH' ) || exit;

/*
 * KURS Liga → Sve sezone: arhiva svih sezona po natjecanju + kreiranje nove sezone.
 * Sve ostalo (ekipe, utakmice, postavke, zaključavanje) je na stranici natjecanja.
 */
$pre_league = sanitize_key( $_GET['league'] ?? '' );
if ( ! Kurs_Season::competition( $pre_league ) ) $pre_league = 'kc_liga';
?>
<div class="wrap kurs-wrap">

	<div class="kurs-breadcrumb">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span> Sve sezone
	</div>
	<div class="kurs-page-hd"><h1>Sve sezone</h1></div>

	<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je obrisana.</p></div>
	<?php elseif ( ! empty( $_GET['locked'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je zaključana — podaci su sada konačni.</p></div>
	<?php elseif ( ! empty( $_GET['unlocked'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Sezona je otključana.</p></div>
	<?php elseif ( ! empty( $_GET['invalid'] ) ) : ?>
	<div class="notice notice-error is-dismissible"><p>Odaberi natjecanje i ispravnu godinu.</p></div>
	<?php elseif ( ! empty( $_GET['exists'] ) && ( $dup = Kurs_Season::get( absint( $_GET['exists'] ) ) ) ) : ?>
	<div class="notice notice-warning is-dismissible"><p>
		Sezona <strong><?php echo esc_html( $dup->name ); ?></strong> već postoji.
		<a href="<?php echo esc_url( Kurs_Admin::hub_url( (int) $dup->id ) ); ?>">Otvori je &rarr;</a>
	</p></div>
	<?php endif; ?>

	<div class="kurs-two-col">

		<!-- ── Sezone po natjecanju ──────────────────────────────────────── -->
		<div class="kurs-form-col" style="gap:16px;">
			<?php foreach ( Kurs_Admin::HUBS as $slug => $league ) :
				$is_pan  = Kurs_Season::is_pan_league( $league );
				$comp    = Kurs_Season::competition( $league );
				$list    = Kurs_Season::for_league( $is_pan );
				$current = Kurs_Season::current_for_league( $is_pan );
			?>
			<div class="kurs-panel">
				<div class="kurs-panel__head">
					<h3 class="kurs-panel__title" style="display:flex;align-items:center;gap:8px;"><?php echo kurs_league_icon( $is_pan ? 'Pan Ljetna' : 'KC Liga', 22 ); ?> <?php echo esc_html( $comp['label'] ?? '' ); ?></h3>
					<a class="kurs-btn kurs-btn--ghost kurs-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>">Stranica natjecanja &rarr;</a>
				</div>
				<div class="kurs-panel__body" style="padding:0;">
					<?php if ( ! $list ) : ?>
					<div class="kurs-panel__empty">Nema sezona.</div>
					<?php endif; ?>
					<?php foreach ( $list as $s ) :
						$is_cur = $current && (int) $current->id === (int) $s->id;
					?>
					<a class="kurs-season-row" href="<?php echo esc_url( Kurs_Admin::hub_url( (int) $s->id ) ); ?>">
						<span class="kurs-season-row__name"><?php echo esc_html( $s->name ); ?></span>
						<span class="kurs-season-row__tags">
							<?php if ( $is_cur ) : ?><span class="kurs-badge kurs-badge--active">Aktualna na webu</span><?php endif; ?>
							<?php if ( ! empty( $s->locked ) ) : ?>
							<span class="kurs-badge" style="background:#111317;color:#FDE206;">&#128274; Zaključana</span>
							<?php elseif ( $s->status !== 'active' ) : ?>
							<span class="kurs-badge kurs-badge--archived">Arhiva</span>
							<?php elseif ( ! $is_cur ) : ?>
							<span class="kurs-badge kurs-badge--active">Aktivna</span>
							<?php endif; ?>
						</span>
						<span class="kurs-season-row__go">Otvori &rarr;</span>
					</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<!-- ── Nova sezona ───────────────────────────────────────────────── -->
		<div>
			<div class="kurs-panel" id="nova-sezona">
				<div class="kurs-panel__head"><h3 class="kurs-panel__title">Nova sezona</h3></div>
				<div class="kurs-panel__body" style="padding:18px;">
					<form method="post" id="kurs-new-season">
						<?php wp_nonce_field( 'kurs_create_season' ); ?>
						<input type="hidden" name="kurs_action" value="create_season">
						<div class="kurs-form-col" style="gap:12px;">
							<label class="kurs-field-wrap">
								<span class="kurs-field-label">Natjecanje</span>
								<select name="league" id="ns-league" style="width:100%;">
									<?php foreach ( Kurs_Season::COMPETITIONS as $slug => $c ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $pre_league ); ?>><?php echo esc_html( $c['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="kurs-field-wrap">
								<span class="kurs-field-label" id="ns-year-lbl">Godina početka sezone</span>
								<input type="number" name="year" id="ns-year" style="width:100%;" value="<?php echo (int) date( 'Y' ); ?>" min="2000" max="2100" required>
							</label>
							<label class="kurs-field-wrap" id="ns-groups-row" style="display:none;">
								<span class="kurs-field-label">Broj skupina</span>
								<select name="pan_groups" id="ns-groups" style="width:100%;">
									<option value="4">4 skupine — po 2 prolaze u četvrtfinale</option>
									<option value="2">2 skupine — po 2 prolaze u polufinale</option>
								</select>
								<span class="kurs-muted" style="font-size:11px;">Može se promijeniti i kasnije, dok skupine nemaju ekipa.</span>
							</label>
							<label class="kurs-field-wrap">
								<span class="kurs-field-label">Max faulova po poluvremenu (akumulirani)</span>
								<input type="number" name="max_fouls_per_half" style="width:120px;" value="5" min="1" max="20" required>
							</label>
							<div class="kurs-notice" id="ns-preview" style="margin:0;"></div>
							<button type="submit" class="kurs-btn kurs-btn--red">+ Kreiraj sezonu &rarr;</button>
						</div>
					</form>
					<p class="kurs-muted" style="margin-top:12px;font-size:12px;">
						Lige i skupine nastaju automatski. Nakon kreiranja otvara se stranica natjecanja,
						gdje uvrstiš ekipe i jednim klikom generiraš raspored.
					</p>
					<script>
					( function () {
						var C = <?php echo wp_json_encode( Kurs_Season::COMPETITIONS ); ?>;
						var lg = document.getElementById( 'ns-league' ), yr = document.getElementById( 'ns-year' ),
						    gr = document.getElementById( 'ns-groups' ), grRow = document.getElementById( 'ns-groups-row' ),
						    lbl = document.getElementById( 'ns-year-lbl' ), out = document.getElementById( 'ns-preview' );
						function esc( s ) { return String( s ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } ); }
						function render() {
							var c = C[ lg.value ], y = parseInt( yr.value, 10 ) || 0;
							if ( ! c ) return;
							var name = c.label + ' ' + ( c.span_year ? y + '/' + ( y + 1 ) : y );
							grRow.style.display = c.groups ? '' : 'none';
							lbl.textContent = c.span_year ? 'Godina početka sezone' : 'Godina';
							var what = c.groups
								? gr.value + ' skupine, po 2 najbolje u ' + ( gr.value === '4' ? 'četvrtfinale' : 'polufinale' )
								: 'lige ' + c.divisions.map( esc ).join( ' i ' ) + ' — svatko sa svakim jednom, bez playoffa';
							out.innerHTML = 'Kreirat će se <strong>' + esc( name ) + '</strong>: ' + what + '.';
						}
						[ lg, yr, gr ].forEach( function ( el ) { el.addEventListener( 'input', render ); el.addEventListener( 'change', render ); } );
						render();
					} )();
					</script>
				</div>
			</div>
		</div>

	</div>
</div>
