<?php get_header(); ?>

<div class="container" style="padding-block:26px;">
	<div class="sec-head">
		<h1 class="sec-head__title">
			Rezultati pretrage: &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;
		</h1>
	</div>

	<?php if ( have_posts() ) : ?>
	<div class="news-grid">
		<?php while ( have_posts() ) : the_post(); ?>
		<a href="<?php the_permalink(); ?>" class="news-card">
			<div class="news-card__thumb">
				<?php if ( has_post_thumbnail() ) : ?>
				<img src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'kurskc_card' ) ); ?>"
				     alt="<?php the_title_attribute(); ?>" loading="lazy">
				<?php else : echo kurskc_thumb_placeholder( get_the_ID() ); ?>
				<?php endif; ?>
				<div class="news-card__badge"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? 'Objava' ); ?></div>
			</div>
			<div class="news-card__body">
				<div class="news-card__title"><?php the_title(); ?></div>
				<div class="news-card__date"><?php echo esc_html( get_the_date( 'j.n.Y.' ) ); ?></div>
			</div>
		</a>
		<?php endwhile; ?>
	</div>
	<div style="margin-top:20px;"><?php the_posts_navigation(); ?></div>
	<?php else : ?>
	<div class="kurs-empty">
		Nema rezultata za &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;.<br>
		<span style="font-size:11px;margin-top:6px;display:block;">Pokušaj s drugačijim pojmom.</span>
	</div>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
