<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$p = $wpdb->prefix . KURS_PREFIX;

/* ── Stat counts ─────────────────────────────────────────────────────────── */
$active_seasons = Kurs_Season::active();
$active_count   = count( $active_seasons );

$team_count = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'kurs_ekipa' AND post_status = 'publish'"
);
$player_count = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'kurs_igrac' AND post_status = 'publish'"
);
$matches_this_week = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$p}matches
	 WHERE match_date >= DATE(NOW()) AND match_date < DATE_ADD(DATE(NOW()), INTERVAL 7 DAY)"
);

/* ── Nadolazeće utakmice (bez rezultata, s datumom) ─────────────────────── */
$upcoming = $wpdb->get_results(
	"SELECT m.id, m.match_date, m.round,
	        h.post_title AS home_name, a.post_title AS away_name,
	        d.name AS div_name, m.home_team_id, m.away_team_id
	 FROM   {$p}matches m
	 JOIN   {$p}groups  g  ON g.id  = m.group_id
	 JOIN   {$p}phases  ph ON ph.id = g.phase_id
	 JOIN   {$p}divisions d ON d.id = ph.division_id
	 JOIN   {$wpdb->posts} h ON h.ID = m.home_team_id
	 JOIN   {$wpdb->posts} a ON a.ID = m.away_team_id
	 WHERE  m.status = 'scheduled'
	 ORDER  BY m.match_date ASC LIMIT 6"
);

/* ── Zadnji uneseni rezultati ────────────────────────────────────────────── */
$recent = $wpdb->get_results(
	"SELECT m.id, m.home_score, m.away_score,
	        h.post_title AS home_name, a.post_title AS away_name,
	        m.match_date
	 FROM   {$p}matches m
	 JOIN   {$wpdb->posts} h ON h.ID = m.home_team_id
	 JOIN   {$wpdb->posts} a ON a.ID = m.away_team_id
	 WHERE  m.status = 'finished'
	 ORDER  BY m.match_date DESC, m.id DESC LIMIT 6"
);
?>
<div class="wrap kurs-wrap">

	<!-- ── Naslov ─────────────────────────────────────────────────────────── -->
	<div class="kurs-breadcrumb">
		KURS Liga <span class="kurs-breadcrumb__sep">/</span> Nadzorna ploča
	</div>
	<div class="kurs-page-hd">
		<h1>Pregled</h1>
	</div>

	<!-- ── Stat kartice ───────────────────────────────────────────────────── -->
	<div class="kurs-stats-grid">
		<div class="kurs-stat-card" style="border-bottom-color:#DA0F1E;">
			<div class="kurs-stat-card__val"><?php echo $active_count; ?></div>
			<div class="kurs-stat-card__lbl">Aktivne lige</div>
		</div>
		<div class="kurs-stat-card" style="border-bottom-color:#F47B16;">
			<div class="kurs-stat-card__val"><?php echo $team_count; ?></div>
			<div class="kurs-stat-card__lbl">Prijavljene ekipe</div>
		</div>
		<div class="kurs-stat-card" style="border-bottom-color:#FDE206;">
			<div class="kurs-stat-card__val"><?php echo $player_count; ?></div>
			<div class="kurs-stat-card__lbl">Registrirani igrači</div>
		</div>
		<div class="kurs-stat-card" style="border-bottom-color:#1F7A43;">
			<div class="kurs-stat-card__val"><?php echo $matches_this_week; ?></div>
			<div class="kurs-stat-card__lbl">Utakmice / tjedan</div>
		</div>
	</div>

	<!-- ── CTA gumbi ──────────────────────────────────────────────────────── -->
	<div class="kurs-cta-row">
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>"
		   class="kurs-btn kurs-btn--red">
			+ Kreiraj ligu / turnir
		</a>
		<a href="<?php echo admin_url( 'post-new.php?post_type=kurs_ekipa' ); ?>"
		   class="kurs-btn kurs-btn--dark">
			+ Unesi ekipu
		</a>
		<a href="<?php echo admin_url( 'post-new.php?post_type=kurs_igrac' ); ?>"
		   class="kurs-btn kurs-btn--outline">
			+ Dodaj igrača
		</a>
	</div>

	<!-- ── Aktivne sezone (pregled) ───────────────────────────────────────── -->
	<?php if ( $active_seasons ) : ?>
	<div class="kurs-cards" style="margin-bottom:24px;">
		<?php foreach ( $active_seasons as $s ) :
			$divs = Kurs_Season::get_divisions( (int) $s->id );
			$bar_color = Kurs_Season::is_pan_league( $s->league ) ? '#F47B16' : '#DA0F1E';
		?>
		<div class="kurs-card" style="border-top: 4px solid <?php echo $bar_color; ?>; padding-top:16px;">
			<div class="kurs-card__head">
				<div>
					<h3 style="margin:0;font-family:var(--font-cond);font-weight:800;font-style:italic;font-size:18px;text-transform:uppercase;">
						<?php echo esc_html( $s->name ); ?>
					</h3>
					<div style="display:flex;align-items:center;gap:7px;margin-top:4px;">
						<?php echo kurs_league_icon( $s->league, 18 ); ?>
						<span class="kurs-tag kurs-tag--<?php echo esc_attr( $s->league ); ?>" style="display:inline-block;">
							<?php echo Kurs_Season::is_pan_league( $s->league ) ? 'Pan Ljetna Liga' : 'KC Liga'; ?>
						</span>
					</div>
				</div>
				<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
					<span class="kurs-badge kurs-badge--active">Aktivna</span>
					<?php if ( ! empty( $s->locked ) ) : ?>
					<span class="kurs-badge" style="background:#111317;color:#FDE206;">&#128274; Zaključano</span>
					<?php endif; ?>
				</div>
			</div>

			<div style="display:flex;gap:24px;margin:.4rem 0 .75rem;">
				<?php
				$total_teams = 0;
				foreach ( $divs as $d ) {
					foreach ( Kurs_Season::get_phases( (int) $d->id ) as $ph ) {
						foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
							$total_teams += count( Kurs_Season::get_group_teams( (int) $g->id ) );
						}
					}
				}
				?>
				<div>
					<div style="font-family:var(--font-cond);font-weight:800;font-style:italic;font-size:30px;line-height:1;color:var(--ka-dark);">
						<?php echo count( $divs ); ?>
					</div>
					<div style="font-family:var(--font-mono);font-size:9px;text-transform:uppercase;color:var(--ka-muted);">
						<?php echo count( $divs ) === 1 ? 'Divizija' : 'Divizije'; ?>
					</div>
				</div>
				<div>
					<div style="font-family:var(--font-cond);font-weight:800;font-style:italic;font-size:30px;line-height:1;color:var(--ka-dark);">
						<?php echo $total_teams; ?>
					</div>
					<div style="font-family:var(--font-mono);font-size:9px;text-transform:uppercase;color:var(--ka-muted);">Ekipa</div>
				</div>
			</div>

			<?php foreach ( $divs as $d ) :
				$phases = Kurs_Season::get_phases( (int) $d->id );
			?>
			<div style="font-size:13px;margin:.2rem 0;color:#555;display:flex;align-items:center;gap:6px;">
				<strong style="font-family:var(--font-cond);font-weight:700;"><?php echo esc_html( $d->name ); ?></strong>
				<?php foreach ( $phases as $ph ) : ?>
				<span class="kurs-phase-pill kurs-phase-pill--<?php echo esc_attr( $ph->type ); ?>">
					<?php echo esc_html( $ph->name ); ?>
				</span>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>

			<div class="kurs-card__foot">
				<?php if ( empty( $s->locked ) ) : ?>
				<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $s->id . '&step=2' ); ?>"
				   class="kurs-btn kurs-btn--red kurs-btn--sm">Postavi ligu</a>
				<?php endif; ?>
				<a href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $s->id ); ?>"
				   class="kurs-btn kurs-btn--outline kurs-btn--sm">Utakmice</a>
				<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>"
				   class="kurs-btn kurs-btn--ghost kurs-btn--sm">Sve sezone</a>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php else : ?>
	<div class="kurs-empty-state">
		<p style="font-size:14px;">Nema aktivnih sezona. Kreiraj prvu sezonu.</p>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>"
		   class="kurs-btn kurs-btn--red" style="margin-top:12px;">+ Kreiraj sezonu</a>
	</div>
	<?php endif; ?>

	<!-- ── Dva panela: Nadolazeće + Rezultati ─────────────────────────────── -->
	<div class="kurs-dash-panels">

		<!-- Nadolazeće -->
		<div class="kurs-panel">
			<div class="kurs-panel__head">
				<h3 class="kurs-panel__title">Nadolazeće utakmice</h3>
				<a href="<?php echo admin_url( 'admin.php?page=kurs-matches' ); ?>"
				   style="font-family:var(--font-mono);font-size:9px;text-transform:uppercase;letter-spacing:.04em;color:var(--ka-muted);text-decoration:none;">
					Sve &rarr;
				</a>
			</div>
			<div class="kurs-panel__body">
			<?php if ( $upcoming ) :
				foreach ( $upcoming as $m ) :
					$dt = $m->match_date ? date_i18n( 'D · H:i', strtotime( $m->match_date ) ) : 'TBD';
					$url = admin_url( 'admin.php?page=kurs-match-record&match_id=' . $m->id );
			?>
			<div class="kurs-upcoming-row">
				<div class="kurs-upcoming-row__time"><?php echo esc_html( strtoupper( $dt ) ); ?></div>
				<div>
					<div class="kurs-upcoming-row__match">
						<?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?>
					</div>
					<div class="kurs-upcoming-row__div"><?php echo esc_html( $m->div_name ); ?> · <?php echo (int) $m->round; ?>. kolo</div>
				</div>
				<a href="<?php echo esc_url( $url ); ?>" class="kurs-btn kurs-btn--red kurs-btn--sm">
					Zapisnik
				</a>
			</div>
			<?php endforeach;
			else : ?>
			<div class="kurs-panel__empty">Nema zakazanih utakmica.</div>
			<?php endif; ?>
			</div>
		</div>

		<!-- Zadnji rezultati -->
		<div class="kurs-panel">
			<div class="kurs-panel__head">
				<h3 class="kurs-panel__title">Zadnji rezultati</h3>
			</div>
			<div class="kurs-panel__body">
			<?php if ( $recent ) :
				foreach ( $recent as $m ) :
					$url = admin_url( 'admin.php?page=kurs-match-record&match_id=' . $m->id );
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="kurs-result-row" style="text-decoration:none;display:grid;grid-template-columns:1fr 70px auto;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid #f5f5ef;">
				<div class="kurs-result-row__match">
					<?php echo esc_html( $m->home_name . ' — ' . $m->away_name ); ?>
				</div>
				<div class="kurs-result-row__score">
					<?php echo (int) $m->home_score . ' : ' . (int) $m->away_score; ?>
				</div>
				<div class="kurs-result-row__status kurs-status--ok">
					✓ objavljeno
				</div>
			</a>
			<?php endforeach;
			else : ?>
			<div class="kurs-panel__empty">Nema unesenih rezultata.</div>
			<?php endif; ?>
			</div>
		</div>

	</div>

	<?php if ( ! empty( $_GET['ticker_saved'] ) ) : ?>
	<div class="notice notice-success" style="margin:16px 0;"><p>Postavke tickera spremljene.</p></div>
	<?php endif; ?>
	<div class="kurs-panel" style="margin-top:24px;max-width:560px;">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">Live ticker — izvan sezone</h3></div>
		<div class="kurs-panel__body" style="padding:18px;">
			<?php $tk_off = get_option( Kurs_Ticker::OPT_OFFSEASON, 'champions' ); ?>
			<form method="post">
				<?php wp_nonce_field( 'kurs_ticker_settings' ); ?>
				<input type="hidden" name="kurs_action" value="save_ticker_settings">
				<p style="margin:0 0 10px;color:#555;font-size:13px;">Kad nema aktivnih, odigranih ni nadolazećih utakmica, ticker:</p>
				<label style="display:block;margin-bottom:8px;">
					<input type="radio" name="kurs_ticker_offseason" value="champions" <?php checked( $tk_off, 'champions' ); ?>>
					prikazuje <strong>podij prvaka</strong> zadnje odigrane lige (🏆 čestitke)
				</label>
				<label style="display:block;margin-bottom:14px;">
					<input type="radio" name="kurs_ticker_offseason" value="hidden" <?php checked( $tk_off, 'hidden' ); ?>>
					se <strong>sakriva</strong>
				</label>
				<button type="submit" class="button button-primary">Spremi</button>
			</form>
		</div>
	</div>

	<?php if ( ! empty( $_GET['refs_saved'] ) ) : ?>
	<div class="notice notice-success" style="margin:16px 0;"><p>Lista sudaca spremljena.</p></div>
	<?php endif; ?>
	<div class="kurs-panel" style="margin-top:24px;max-width:560px;">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">Suci (baza)</h3></div>
		<div class="kurs-panel__body" style="padding:18px;">
			<form method="post">
				<?php wp_nonce_field( 'kurs_save_referees' ); ?>
				<input type="hidden" name="kurs_action" value="save_referees">
				<p style="margin:0 0 8px;color:#555;font-size:13px;">Jedan sudac po retku. U zapisniku se nude kao prijedlozi (autocomplete), a novi se dodaju automatski kad ih upišeš.</p>
				<textarea name="referees" rows="6" style="width:100%;font-family:var(--font-mono),monospace;font-size:13px;" placeholder="Ime Prezime"><?php echo esc_textarea( implode( "\n", function_exists( 'kurs_get_referees' ) ? kurs_get_referees() : [] ) ); ?></textarea>
				<p style="margin:10px 0 0;"><button type="submit" class="button button-primary">Spremi suce</button></p>
			</form>
		</div>
	</div>

</div>
