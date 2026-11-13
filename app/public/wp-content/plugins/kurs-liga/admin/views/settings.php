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

	<?php
	/* E-pošta (1.35.0): stanje slanja + probni mail (Kurs_Mail) */
	$mt   = get_transient( 'kurs_mail_test_' . get_current_user_id() );
	if ( $mt ) delete_transient( 'kurs_mail_test_' . get_current_user_id() );
	$merr = get_option( Kurs_Mail::ERR_OPT );
	$smtp = Kurs_Mail::smtp_on();
	?>
	<?php $patches = Kurs_Data_Patches::done(); if ( $patches ) : ?>
	<div class="kurs-panel" style="margin-top:18px;">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">Paketi podataka (izvršeni)</h3></div>
		<div class="kurs-panel__body" style="padding:14px 18px;">
			<?php foreach ( array_reverse( $patches, true ) as $pid => $pr ) : ?>
			<details style="margin:0 0 8px;">
				<summary style="cursor:pointer;"><strong><?php echo esc_html( $pid ); ?></strong> · <?php echo esc_html( $pr['at'] ); ?> · <?php echo ! empty( $pr['ok'] ) ? '<span style="color:#1F7A43;font-weight:700;">✓ izvršen</span>' : '<span style="color:#DA0F1E;font-weight:700;">greška</span>'; ?></summary>
				<pre style="max-height:320px;overflow:auto;background:#f6f6f1;padding:10px;border-radius:6px;font-size:12px;white-space:pre-wrap;margin:8px 0 0;"><?php echo esc_html( implode( "
", (array) $pr['log'] ) ); ?></pre>
			</details>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="kurs-panel" id="kurs-mail" style="margin-top:18px;">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">E-pošta s weba (nova lozinka, novi korisnik)</h3></div>
		<div class="kurs-panel__body" style="padding:18px;">
			<?php if ( $smtp ) : ?>
			<p style="margin:0 0 10px;"><span style="display:inline-block;padding:2px 9px;border-radius:10px;background:#e6f3ea;color:#1F7A43;font-weight:700;">✓ SMTP</span>
				Šalje se preko sandučića <strong><?php echo esc_html( KURS_SMTP_USER ); ?></strong> na <strong><?php echo esc_html( KURS_SMTP_HOST . ':' . ( defined( 'KURS_SMTP_PORT' ) ? (int) KURS_SMTP_PORT : 587 ) ); ?></strong>.</p>
			<?php else : ?>
			<p style="margin:0 0 10px;"><span style="display:inline-block;padding:2px 9px;border-radius:10px;background:#fdecec;color:#DA0F1E;font-weight:700;">PHP mail()</span>
				Mailovi idu bez prijave u sandučić — zbog strogih pravila domene (DMARC) često završe u spamu ili ne stignu. Rješenje: u Plesku napravi sandučić (npr. <code>web@kurskc.hr</code>) i u <code>wp-config.php</code> upiši <code>KURS_SMTP_HOST</code>, <code>KURS_SMTP_PORT</code>, <code>KURS_SMTP_SECURE</code>, <code>KURS_SMTP_USER</code> i <code>KURS_SMTP_PASS</code>.</p>
			<?php endif; ?>
			<?php if ( is_array( $merr ) ) : ?>
			<p style="margin:0 0 10px;color:#8a4300;font-size:13px;">Zadnja greška slanja (<?php echo esc_html( $merr['at'] ); ?>): <?php echo esc_html( $merr['msg'] ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
				<?php wp_nonce_field( 'kurs_mail_test' ); ?>
				<input type="hidden" name="action" value="kurs_mail_test">
				<input type="email" name="to" class="kurs-field-input" style="min-width:260px;" value="<?php echo esc_attr( $mt['to'] ?? wp_get_current_user()->user_email ); ?>" required>
				<button class="kurs-btn kurs-btn--red">Pošalji probni mail</button>
			</form>
			<?php if ( $mt ) : ?>
			<div class="notice <?php echo $mt['ok'] ? 'notice-success' : 'notice-error'; ?> inline" style="margin:12px 0 0;"><p><?php echo esc_html( $mt['msg'] ); ?></p></div>
			<?php if ( $mt['log'] ) : ?>
			<details style="margin-top:10px;"><summary style="cursor:pointer;">Razgovor sa SMTP serverom (za dijagnostiku)</summary>
				<pre style="max-height:300px;overflow:auto;background:#111317;color:#e6e6e0;padding:10px;border-radius:6px;font-size:11px;white-space:pre-wrap;"><?php echo esc_html( implode( "
", $mt['log'] ) ); ?></pre>
			</details>
			<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
