<?php
/**
 * Feed vijesti (arhiva CPT-a vijest + blog home) — dizajn „Naslovnica rubrike".
 */
get_header();

global $wp_query;
$total      = (int) $wp_query->found_posts;
$page       = max( 1, get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 );
$max_pages  = (int) $wp_query->max_num_pages;
$cur_kat  = isset( $_GET['kat'] ) ? sanitize_title( wp_unslash( $_GET['kat'] ) ) : '';
$kat_term = $cur_kat ? get_term_by( 'slug', $cur_kat, 'vijest_kategorija' ) : null;
if ( $cur_kat && ! $kat_term ) $cur_kat = ''; // nevažeća kategorija → prikaži sve

$arch_title = 'Vijesti';
if ( is_post_type_archive( 'kurs_ekipa' ) ) $arch_title = 'Ekipe';
elseif ( is_post_type_archive( 'kurs_igrac' ) ) $arch_title = 'Igrači';
elseif ( $kat_term ) $arch_title = $kat_term->name;

// Render helpera za karticu
$render_card = function () {
	$id    = get_the_ID();
	$color = kurskc_badge_color( $id );
	$rt    = max( 1, (int) round( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
	?>
	<a class="news-card" href="<?php the_permalink(); ?>">
		<div class="news-card__thumb">
			<?php if ( has_post_thumbnail() ) : ?>
			<img src="<?php echo esc_url( get_the_post_thumbnail_url( $id, 'kurskc_card' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
			<?php endif; ?>
			<span class="news-card__badge" style="border-left-color:<?php echo esc_attr( $color ); ?>;"><?php echo esc_html( kurskc_badge_label( $id ) ); ?></span>
		</div>
		<div class="news-card__body">
			<h3 class="news-card__title"><?php the_title(); ?></h3>
			<p class="news-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
			<span class="news-card__meta"><?php echo esc_html( kurs_time_ago( get_post_field( 'post_date', $id ) ) . ' · ' . $rt . ' min' ); ?></span>
		</div>
	</a>
	<?php
};
?>

<div class="container kc-feed">

	<!-- Naslovni red -->
	<div class="kc-feed__title">
		<div>
			<div class="kc-feed__kicker">Portal · rubrika</div>
			<h1 class="kc-feed__h"><span class="tick tick--red tick--lg"></span><?php echo esc_html( $arch_title ); ?></h1>
		</div>
		<div class="kc-feed__count"><?php echo $total; ?> članaka · str. <?php echo $page; ?>/<?php echo max( 1, $max_pages ); ?></div>
	</div>

	<!-- Filter chips (kategorije) -->
	<?php
	$cats      = get_terms( [ 'taxonomy' => 'vijest_kategorija', 'hide_empty' => true ] );
	$feed_base = get_post_type_archive_link( 'vijest' );
	?>
	<?php if ( ! is_wp_error( $cats ) && $cats ) : ?>
	<div class="kc-chips">
		<a class="kc-chip <?php echo ! $cur_kat ? 'is-active' : ''; ?>" href="<?php echo esc_url( $feed_base ); ?>">Sve</a>
		<?php foreach ( $cats as $c ) :
			$cc = ( stripos( $c->slug, 'pan' ) !== false || stripos( $c->slug, 'ljet' ) !== false ) ? '#F47B16'
				: ( stripos( $c->slug, 'briga' ) !== false ? '#B07A1B' : ( stripos( $c->slug, 'kc' ) !== false ? '#DA0F1E' : '' ) );
			$active = ( $cur_kat === $c->slug );
		?>
		<a class="kc-chip <?php echo $active ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'kat', $c->slug, $feed_base ) ); ?>"<?php echo $cc ? ' style="border-left:3px solid ' . esc_attr( $cc ) . ';"' : ''; ?>><?php echo esc_html( $c->name ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>

	<?php $show_featured = ( $page === 1 ); $featured_done = false; ?>

	<?php if ( $show_featured ) : ?>
	<!-- Izdvojeno + desni rail -->
	<div class="kc-lead-row">
		<?php
		the_post(); // prvi post = izdvojeni
		$featured_done = true;
		$fid    = get_the_ID();
		$fcolor = kurskc_badge_color( $fid );
		$frt    = max( 1, (int) round( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
		?>
		<a class="kc-feature" href="<?php the_permalink(); ?>">
			<div class="kc-feature__img">
				<?php if ( has_post_thumbnail() ) : ?>
				<img src="<?php echo esc_url( get_the_post_thumbnail_url( $fid, 'kurskc_feat' ) ?: get_the_post_thumbnail_url( $fid, 'large' ) ); ?>" alt="<?php the_title_attribute(); ?>">
				<?php endif; ?>
				<span class="kc-feature__stripe"></span>
				<span class="kc-feature__scrim"></span>
				<span class="kc-feature__chip" style="background:<?php echo esc_attr( $fcolor ); ?>;">Izdvojeno · <?php echo esc_html( kurskc_badge_label( $fid ) ); ?></span>
				<div class="kc-feature__text">
					<h2 class="kc-feature__title"><?php the_title(); ?></h2>
					<div class="kc-feature__meta"><?php echo esc_html( strtoupper( get_the_author() . ' · ' . kurs_time_ago( get_post_field( 'post_date', $fid ) ) . ' · ' . $frt . ' min čitanja' ) ); ?></div>
				</div>
			</div>
			<div class="kc-feature__excerpt"><p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 34 ) ); ?></p></div>
		</a>
		<div class="kc-rail">
			<?php echo kurskc_widget_most_read( true ); ?>
			<?php echo kurskc_widget_league_live(); ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Najnovije -->
	<div class="kc-section-head"><h2 class="kc-section-h2"><span class="tick tick--dark"></span>Najnovije</h2></div>

	<div class="news-grid kc-grid">
		<?php while ( have_posts() ) : the_post(); $render_card(); endwhile; ?>
	</div>

	<!-- Paginacija -->
	<?php
	$links = paginate_links( [
		'total'     => $max_pages,
		'current'   => $page,
		'prev_text' => '‹',
		'next_text' => '›',
		'type'      => 'array',
		'mid_size'  => 1,
		'end_size'  => 1,
	] );
	if ( $links ) : ?>
	<nav class="kc-pagination"><?php echo implode( '', $links ); ?></nav>
	<?php endif; ?>

	<?php else : ?>
	<p class="kurs-empty">Nema objava.</p>
	<?php endif; ?>

</div>

<?php get_footer(); ?>
