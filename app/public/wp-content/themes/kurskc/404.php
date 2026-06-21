<?php get_header(); ?>

<div class="container">
	<section class="error-404">
		<h1>404 &mdash; Stranica nije pronađena</h1>
		<p>Tražena stranica ne postoji. Možda je premještena ili izbrisana.</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">Povratak na početnu</a>
	</section>
</div>

<?php get_footer(); ?>
