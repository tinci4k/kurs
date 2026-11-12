</main>
</div><!-- /.site-box -->

<?php if ( function_exists( 'kurskc_sponsors_strip' ) ) echo kurskc_sponsors_strip(); ?>

<footer class="site-footer">

	<!-- ── Gornji dio footera ────────────────────────────────────────────── -->
	<div class="site-footer__stripes" aria-hidden="true">
		<span style="background:var(--c-kc);"></span>
		<span style="background:var(--c-pan);"></span>
		<span style="background:var(--c-gold);"></span>
	</div>

	<div class="site-footer__inner">

		<!-- Stupac 1: Brend -->
		<div class="site-footer__brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__logo" aria-label="KURS početna">
				<?php
				$logo = get_template_directory_uri() . '/assets/img/KURS-clean.png';
				?>
				<img src="<?php echo esc_url( $logo ); ?>" alt="KURS" width="80" height="28">
			</a>
			<p class="site-footer__tagline">
				Portal rekreativnog malonogometnog sporta Koprivnice i Podravine. Lige, tablice i rezultati — cijelu godinu.
			</p>
			<ul class="site-footer__leagues">
				<li>
					<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( 'KC Liga', 22 ) : ''; ?>
					<strong>KC Liga</strong>
					<span class="site-footer__league-meta">· Zima · Dvorana</span>
				</li>
				<li>
					<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( 'Pan Ljetna', 22 ) : ''; ?>
					<strong>Pan Ljetna liga</strong>
					<span class="site-footer__league-meta">· Ljeto · Trava</span>
				</li>
			</ul>
		</div>

		<!-- Stupac 2: Portal -->
		<nav class="site-footer__nav" aria-label="Portal">
			<h4 class="site-footer__nav-title">Portal</h4>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/vijesti/' ) ); ?>">Vijesti</a></li>
				<li><a href="<?php echo esc_url( home_url( '/kc-liga/?tab=tablica#liga' ) ); ?>">Tablice</a></li>
				<li><a href="<?php echo esc_url( home_url( '/kc-liga/?tab=kola#kola' ) ); ?>">Rezultati po kolima</a></li>
				<li><a href="<?php echo esc_url( home_url( '/kc-liga/?tab=raspored#liga' ) ); ?>">Raspored</a></li>
				<li><a href="<?php echo esc_url( home_url( '/za-medije/' ) ); ?>">Za medije</a></li>
			</ul>
		</nav>

		<!-- Stupac 3: Natjecanja -->
		<nav class="site-footer__nav" aria-label="Natjecanja">
			<h4 class="site-footer__nav-title">Natjecanja</h4>
			<ul>
				<?php $kc_divs = function_exists( 'kurskc_league_divisions' ) ? kurskc_league_divisions( false ) : [];
				if ( $kc_divs ) : foreach ( $kc_divs as $kd ) : ?>
				<li>
					<a href="<?php echo esc_url( $kd['url'] ); ?>">
						<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( $kd['icon'], 18 ) : ''; ?>
						<?php echo esc_html( $kd['name'] ); ?>
					</a>
				</li>
				<?php endforeach; else : ?>
				<li>
					<a href="<?php echo esc_url( home_url( '/kc-liga/' ) ); ?>">
						<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( 'KC Liga', 18 ) : ''; ?>
						KC Liga
					</a>
				</li>
				<?php endif; ?>
				<li>
					<a href="<?php echo esc_url( home_url( '/pan-ljetna/' ) ); ?>">
						<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( 'Pan Ljetna', 18 ) : ''; ?>
						Pan Ljetna liga
					</a>
				</li>
			</ul>
		</nav>

		<!-- Stupac 4: Udruga -->
		<nav class="site-footer__nav" aria-label="Udruga">
			<h4 class="site-footer__nav-title">Udruga</h4>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/o-kurs-u/' ) ); ?>">O KURS-u</a></li>
				<li><a href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>">Kontakt</a></li>
			</ul>
		</nav>

		<!-- Stupac 5: Društvene mreže -->
		<div class="site-footer__social-col">
			<h4 class="site-footer__nav-title">Pratite nas</h4>
			<div class="site-footer__social">
				<?php foreach ( function_exists( 'kurskc_social_links' ) ? kurskc_social_links() : [] as $sk => $sn ) : ?>
				<a href="<?php echo esc_url( $sn['url'] ); ?>" class="site-footer__social-btn site-footer__social-btn--lbl" target="_blank" rel="noopener"><?php echo kurskc_icon( $sn['icon'], 15 ); ?><span><?php echo esc_html( $sn['label'] ); ?></span></a>
				<?php endforeach; ?>
			</div>
			<p class="site-footer__social-note">Rezultati i fotografije sa svakog kola.</p>
			</div>
		</div>

	</div><!-- /.site-footer__inner -->

	<!-- ── Donji bar ─────────────────────────────────────────────────────── -->
	<div class="site-footer__bottom">
		<span class="site-footer__copy">
			Koprivnička udruga za rekreativni sport &middot; &copy; <?php echo date( 'Y' ); ?> KURS
		</span>
		<a class="site-footer__credit" href="https://webdot.hr/" target="_blank" rel="noopener" title="Webdot — izrada, hosting i održavanje web stranica iz Koprivnice">
			<span class="site-footer__credit-pre">Web izradio</span>
			<img class="wd-mark" src="<?php echo esc_url( add_query_arg( 'ver', KURSKC_VERSION, get_template_directory_uri() . '/assets/img/webdot-o.png' ) ); ?>" alt="" width="18" height="19" loading="lazy" decoding="async"><b>webdot</b>
			<span class="site-footer__credit-tag">domaći web iz Koprivnice · asistencija za svaki gol</span>
		</a>
		<nav class="site-footer__legal" aria-label="Pravne informacije">
			<a href="<?php echo esc_url( home_url( '/uvjeti-koristenja/' ) ); ?>">Uvjeti</a>
			<a href="<?php echo esc_url( home_url( '/privatnost/' ) ); ?>">Privatnost</a>
			<a href="<?php echo esc_url( home_url( '/kolacici/' ) ); ?>">Kolačići</a>
		</nav>
	</div>

</footer>

<?php wp_footer(); ?>
</body>
</html>
