<?php
/**
 * Template Name: Kontakt
 *
 * Tanki wrapper (hero + .o-nama-content) oko Gutenberg sadržaja — stranica
 * ostaje u potpunosti editabilna iz blok-editora. Koristi `.o-nama-*` stilove.
 */
get_header();
?>

<div class="o-nama-wrap">

	<!-- Hero -->
	<div class="o-nama-hero">
		<div class="o-nama-hero__inner container">
			<div class="o-nama-hero__label">Javite nam se</div>
			<h1 class="o-nama-hero__title">Kontakt</h1>
		</div>
	</div>

	<!-- Sadržaj — editabilan iz WP admina (Gutenberg) -->
	<div class="o-nama-content container">
		<?php
		if ( have_posts() ) :
			the_post();
			the_content();
		endif;
		?>
	</div>

</div>

<?php get_footer(); ?>
