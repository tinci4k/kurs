<?php get_header(); ?>

<?php while ( have_posts() ) : the_post();
	$pid       = get_the_ID();
	$cat_color = kurskc_badge_color( $pid );
	$cat_label = kurskc_badge_label( $pid );
	$read_min  = max( 1, (int) round( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
	$lede      = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 32 );
	$author_id = (int) get_the_author_meta( 'ID' );
	$share_url = urlencode( get_permalink() );
?>
<div class="container">
<article class="article">

	<!-- Hero (dark) -->
	<header class="article-hero">
		<div class="article-hero__stripe"></div>
		<div class="article-hero__inner">
			<span class="article-hero__badge" style="background:<?php echo esc_attr( $cat_color ); ?>;"><?php echo esc_html( $cat_label ); ?></span>
			<h1 class="article-hero__title"><?php the_title(); ?></h1>
			<?php if ( $lede ) : ?><p class="article-hero__lede"><?php echo esc_html( $lede ); ?></p><?php endif; ?>
			<div class="article-hero__meta">
				<div class="article-author">
					<?php echo get_avatar( $author_id, 36, '', '', [ 'class' => 'article-author__ava' ] ); ?>
					<span class="article-author__txt">
						<span class="article-author__name"><?php the_author(); ?></span>
						<span class="article-author__sub"><?php echo esc_html( get_the_date( 'd.m.Y.' ) . ' · ' . get_the_time( 'H:i' ) . ' · ' . $read_min . ' min čitanja' ); ?></span>
					</span>
				</div>
				<?php echo kurskc_share_html( $pid, 'compact' ); ?>
			</div>
		</div>
	</header>

	<!-- Glavna slika -->
	<?php if ( has_post_thumbnail() ) :
		$cap = get_the_post_thumbnail_caption(); ?>
	<figure class="article-lead">
		<img src="<?php echo esc_url( get_the_post_thumbnail_url( $pid, 'kurskc_feat' ) ?: get_the_post_thumbnail_url( $pid, 'large' ) ); ?>" alt="<?php the_title_attribute(); ?>">
		<?php if ( $cap ) : ?><figcaption class="article-lead__cap"><?php echo esc_html( $cap ); ?></figcaption><?php endif; ?>
	</figure>
	<?php endif; ?>

	<!-- Tijelo + sidebar -->
	<div class="article-layout">
		<div class="article-body">
			<?php the_content(); ?>

			<?php echo kurskc_post_embed_html( $pid ); ?>
			<?php echo kurskc_gallery_html( $pid ); ?>

			<?php $tags = get_the_tags(); if ( $tags && ! is_wp_error( $tags ) ) : ?>
			<div class="article-tags">
				<span class="article-tags__label">Oznake</span>
				<?php foreach ( $tags as $tag ) : ?>
				<a class="article-tag" href="<?php echo esc_url( get_tag_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php echo kurskc_share_html( $pid, 'full' ); ?>
			<?php echo kurskc_follow_box( 'inline' ); ?>

			<?php if ( get_the_author_meta( 'description', $author_id ) ) : ?>
			<div class="author-box">
				<?php echo get_avatar( $author_id, 54, '', '', [ 'class' => 'author-box__ava' ] ); ?>
				<div class="author-box__txt">
					<span class="author-box__kicker">Autor</span>
					<span class="author-box__name"><?php the_author(); ?></span>
					<p class="author-box__bio"><?php echo esc_html( get_the_author_meta( 'description', $author_id ) ); ?></p>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<aside class="article-side">
			<?php
			// „Iz lige" ovisi o fazi sezone (najava / danas / sljedeće kolo + vrh tablice / prvaci) — inc/league-widget.php.
			// Admin-konfigurirani widgeti (Izgled → Widgeti) dolaze ispod.
			echo kurskc_league_widget();
			if ( is_active_sidebar( 'kurskc-news-sidebar' ) ) dynamic_sidebar( 'kurskc-news-sidebar' );
			echo kurskc_widget_most_read( false );
			echo kurskc_follow_box( 'card' );
			?>
		</aside>
	</div>

	<!-- Povezano -->
	<?php
	$cats = wp_get_post_terms( $pid, 'vijest_kategorija', [ 'fields' => 'ids' ] );
	$rel  = new WP_Query( [
		'post_type'           => get_post_type( $pid ),
		'posts_per_page'      => 3,
		'post__not_in'        => [ $pid ],
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'tax_query'           => $cats ? [ [ 'taxonomy' => 'vijest_kategorija', 'field' => 'term_id', 'terms' => $cats ] ] : [],
	] );
	if ( $rel->have_posts() ) : ?>
	<section class="related">
		<h2 class="kc-section-h2"><span class="tick tick--red"></span>Povezano</h2>
		<div class="related__grid">
			<?php while ( $rel->have_posts() ) : $rel->the_post();
				$rc = kurskc_badge_color( get_the_ID() ); ?>
			<a class="related-card" href="<?php the_permalink(); ?>">
				<div class="related-card__thumb">
					<?php if ( has_post_thumbnail() ) : ?>
					<img src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'kurskc_card' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
					<?php else : echo kurskc_thumb_placeholder( get_the_ID(), 56 ); ?>
					<?php endif; ?>
					<span class="related-card__badge" style="border-left-color:<?php echo esc_attr( $rc ); ?>;"><?php echo esc_html( kurskc_badge_label( get_the_ID() ) ); ?></span>
				</div>
				<div class="related-card__body">
					<h3 class="related-card__title"><?php the_title(); ?></h3>
					<span class="related-card__date"><?php echo esc_html( kurs_time_ago( get_post_field( 'post_date', get_the_ID() ) ) ); ?></span>
				</div>
			</a>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</section>
	<?php endif; ?>

</article>
</div>
<?php endwhile; ?>

<?php get_footer(); ?>
