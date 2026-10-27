<?php
defined( 'ABSPATH' ) || exit;

/* Opće postavke KURS Lige (vrijede za sva natjecanja): ticker izvan sezone. Suci se vode po sezoni (kartica Postavke natjecanja). */
$tk_off = class_exists( 'Kurs_Ticker' ) ? get_option( Kurs_Ticker::OPT_OFFSEASON, 'champions' ) : 'champions';
?>
<div class="wrap kurs-wrap">
	<div class="kurs-breadcrumb">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span> Postavke
	</div>
	<div class="kurs-page-hd"><h1>Postavke</h1></div>

	<?php if ( ! empty( $_GET['ticker_saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Postavke tickera spremljene.</p></div>
	<?php endif; ?>

	<div class="kurs-two-col">
		<div class="kurs-panel">
			<div class="kurs-panel__head"><h3 class="kurs-panel__title">Suci</h3></div>
			<div class="kurs-panel__body" style="padding:18px;">
				<p style="margin:0 0 12px;color:#555;font-size:13px;">
					Suci se vode po sezoni — na stranici natjecanja, kartica <strong>Postavke</strong>.
					Nova sezona automatski preuzima suce iz prethodne.
				</p>
				<p style="margin:0;display:flex;flex-wrap:wrap;gap:8px;">
					<?php foreach ( [ 'KC Liga' => false, 'Pan Ljetna liga' => true ] as $label => $is_pan ) :
						$cur = Kurs_Season::current_for_league( $is_pan );
						if ( ! $cur ) continue; ?>
					<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( Kurs_Admin::hub_url( (int) $cur->id, 'postavke' ) ); ?>"><?php echo esc_html( $label ); ?> &rarr;</a>
					<?php endforeach; ?>
				</p>
			</div>
		</div>

		<div class="kurs-panel">
			<div class="kurs-panel__head"><h3 class="kurs-panel__title">Ticker na vrhu weba — izvan sezone</h3></div>
			<div class="kurs-panel__body" style="padding:18px;">
				<form method="post">
					<?php wp_nonce_field( 'kurs_ticker_settings' ); ?>
					<input type="hidden" name="kurs_action" value="save_ticker_settings">
					<p style="margin:0 0 10px;color:#555;font-size:13px;">Kad nema utakmica u tijeku, nedavno odigranih ni nadolazećih, ticker:</p>
					<label style="display:block;margin-bottom:8px;">
						<input type="radio" name="kurs_ticker_offseason" value="champions" <?php checked( $tk_off, 'champions' ); ?>>
						prikazuje <strong>podij prvaka</strong> zadnje odigrane lige (🏆 čestitke)
					</label>
					<label style="display:block;margin-bottom:14px;">
						<input type="radio" name="kurs_ticker_offseason" value="hidden" <?php checked( $tk_off, 'hidden' ); ?>>
						se <strong>sakriva</strong>
					</label>
					<button type="submit" class="kurs-btn kurs-btn--red">Spremi</button>
				</form>
			</div>
		</div>
	</div>
</div>
