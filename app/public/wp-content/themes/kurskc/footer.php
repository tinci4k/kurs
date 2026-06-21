</main>

<footer class="site-footer">
	<div class="container">
		<div class="footer-inner">
			<div class="footer-brand">
				<p class="footer-title"><?php bloginfo( 'name' ); ?></p>
				<p class="footer-desc">Organizacija malonogometnih turnira na području Koprivnice</p>
			</div>

			<nav class="footer-nav" aria-label="Footer izbornik">
				<?php
				wp_nav_menu( [
					'theme_location' => 'footer',
					'menu_class'     => 'footer-nav-list',
					'container'      => false,
					'fallback_cb'    => false,
					'depth'          => 1,
				] );
				?>
			</nav>
		</div>

		<div class="footer-bottom">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. Sva prava pridržana.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
