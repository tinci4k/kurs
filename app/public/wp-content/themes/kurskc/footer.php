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
				<li><a href="<?php echo esc_url( home_url( '/kc-liga/?tab=tablica' ) ); ?>">Tablice</a></li>
				<li><a href="<?php echo esc_url( home_url( '/kc-liga/?tab=raspored' ) ); ?>">Raspored kola</a></li>
				<li><a href="<?php echo esc_url( home_url( '/kc-liga/?tab=raspored' ) ); ?>">Rezultati</a></li>
			</ul>
		</nav>

		<!-- Stupac 3: Natjecanja -->
		<nav class="site-footer__nav" aria-label="Natjecanja">
			<h4 class="site-footer__nav-title">Natjecanja</h4>
			<ul>
				<li>
					<a href="<?php echo esc_url( home_url( '/kc-liga/' ) ); ?>">
						<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( 'KC Liga', 18 ) : ''; ?>
						KC Liga
					</a>
				</li>
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
				<a href="#" class="site-footer__social-btn" aria-label="Facebook" rel="noopener noreferrer">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
						<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
					</svg>
				</a>
				<a href="#" class="site-footer__social-btn" aria-label="Instagram" rel="noopener noreferrer">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
						<circle cx="12" cy="12" r="4"/>
						<circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>
					</svg>
				</a>
				<a href="#" class="site-footer__social-btn" aria-label="YouTube" rel="noopener noreferrer">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
						<path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/>
						<polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#111317"/>
					</svg>
				</a>
			</div>
		</div>

	</div><!-- /.site-footer__inner -->

	<!-- ── Donji bar ─────────────────────────────────────────────────────── -->
	<div class="site-footer__bottom">
		<span class="site-footer__copy">
			Koprivnička udruga za rekreativni sport &middot; &copy; <?php echo date( 'Y' ); ?> KURS
		</span>
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
