<?php
/**
 * Template Name: O nama
 */
get_header();
?>

<div class="o-nama-wrap">

	<!-- Hero -->
	<div class="o-nama-hero">
		<div class="o-nama-hero__inner container">
			<div class="o-nama-hero__label">Udruga</div>
			<h1 class="o-nama-hero__title">O nama</h1>
		</div>
	</div>

	<!-- Sadržaj — editabilan iz WP admina -->
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
