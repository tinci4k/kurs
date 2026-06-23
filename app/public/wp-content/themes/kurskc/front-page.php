<?php
/**
 * Naslovnica portala (A)
 */
get_header();

$logo_uri = get_template_directory_uri() . '/assets/img/';

/* ── Vijesti: izdvojeni (hero) + grid (vijest CPT + WP post) ─── */
$hero_post  = null;
$grid_posts = [];

// Hero = ručno izdvojena objava (_kurs_featured); inače najnovija.
$featured = get_posts( [
	'post_type'   => [ 'vijest', 'post' ],
	'numberposts' => 1,
	'post_status' => 'publish',
	'meta_key'    => '_kurs_featured',
	'meta_value'  => '1',
] );

$news_raw = get_posts( [
	'post_type'   => [ 'vijest', 'post' ],
	'numberposts' => 7,
	'post_status' => 'publish',
	'orderby'     => 'date',
	'order'       => 'DESC',
] );

if ( $featured ) {
	$hero_post  = $featured[0];
	$grid_posts = array_values( array_filter( $news_raw, fn( $p ) => $p->ID !== $hero_post->ID ) );
} elseif ( $news_raw ) {
	$hero_post  = $news_raw[0];
	$grid_posts = array_slice( $news_raw, 1 );
}
$grid_posts = array_slice( $grid_posts, 0, 3 );
?>

<div class="container kurs-home">

	<!-- ── HERO + LIGA KARTICE ────────────────────────────────────── -->
	<div class="kurs-hero">

		<!-- Hero vijest -->
		<a href="<?php echo $hero_post ? esc_url( get_permalink( $hero_post ) ) : home_url( '/vijesti/' ); ?>"
		   class="kurs-hero__main">
			<div class="hero-stripe"></div>
			<?php if ( $hero_post && has_post_thumbnail( $hero_post ) ) : ?>
			<img class="hero-img"
			     src="<?php echo esc_url( get_the_post_thumbnail_url( $hero_post, 'large' ) ); ?>"
			     alt="<?php echo esc_attr( $hero_post->post_title ); ?>">
			<?php endif; ?>
			<div class="hero-gradient"></div>
			<div class="kurs-hero__content">
				<?php if ( $hero_post ) : ?>
				<div class="hero-cat" style="background:<?php echo esc_attr( kurskc_badge_color( $hero_post->ID ) ); ?>;">
					<?php echo esc_html( kurskc_badge_label( $hero_post->ID ) ); ?>
				</div>
				<h2 class="hero-title"><?php echo esc_html( $hero_post->post_title ); ?></h2>
				<div class="hero-meta"><?php echo esc_html( date_i18n( 'j.n.Y.', strtotime( $hero_post->post_date ) ) ); ?></div>
				<?php else : ?>
				<div class="hero-cat">KURS</div>
				<h2 class="hero-title">Dobrodošli na portal KC i Pan Ljetna lige</h2>
				<?php endif; ?>
			</div>
		</a>

		<!-- Liga kartice -->
		<div class="kurs-hero__side">
			<a href="<?php echo esc_url( home_url( '/kc-liga/' ) ); ?>" class="league-card">
				<div class="league-card__top">
					<div class="league-card__icon">
						<?php echo kurs_league_icon( 'KC Liga', 44 ); ?>
					</div>
					<div class="league-card__type">Zima &middot; Dvorana</div>
				</div>
				<div>
					<div class="league-card__name">KC Liga</div>
					<div class="league-card__cta">Tablica &middot; Raspored &rarr;</div>
				</div>
			</a>
			<a href="<?php echo esc_url( home_url( '/pan-ljetna/' ) ); ?>" class="league-card league-card--pan">
				<div class="league-card__top">
					<div class="league-card__icon">
						<?php echo kurs_league_icon( 'Pan Ljetna', 44 ); ?>
					</div>
					<div class="league-card__type">Ljeto &middot; Trava</div>
				</div>
				<div>
					<div class="league-card__name">Pan Ljetna</div>
					<div class="league-card__cta">Tablica &middot; Raspored &rarr;</div>
				</div>
			</a>
		</div>
	</div>

	<!-- ── NAJNOVIJE VIJESTI ──────────────────────────────────────── -->
	<div class="sec-head">
		<h2 class="sec-head__title">Najnovije</h2>
		<a href="<?php echo esc_url( home_url( '/vijesti/' ) ); ?>" class="sec-head__link">Sve vijesti &rarr;</a>
	</div>

	<div class="news-grid">
	<?php if ( $grid_posts ) :
		foreach ( $grid_posts as $p ) :
			$badge_color = kurskc_badge_color( $p->ID );
			$badge_label = kurskc_badge_label( $p->ID );
	?>
		<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" class="news-card">
			<div class="news-card__thumb">
				<?php if ( has_post_thumbnail( $p ) ) : ?>
				<img src="<?php echo esc_url( get_the_post_thumbnail_url( $p, 'kurskc_card' ) ); ?>"
				     alt="<?php echo esc_attr( $p->post_title ); ?>" loading="lazy">
				<?php endif; ?>
				<div class="news-card__badge" style="border-left-color:<?php echo esc_attr( $badge_color ); ?>">
					<?php echo esc_html( $badge_label ); ?>
				</div>
			</div>
			<div class="news-card__body">
				<h3 class="news-card__title"><?php echo esc_html( $p->post_title ); ?></h3>
				<p class="news-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $p ), 18 ) ); ?></p>
				<span class="news-card__meta"><?php echo esc_html( kurs_time_ago( $p->post_date ) ); ?></span>
			</div>
		</a>
	<?php endforeach;
	else : /* Placeholderi ako nema vijesti */ ?>
		<?php for ( $i = 0; $i < 3; $i++ ) : ?>
		<div class="news-card news-card--placeholder">
			<div class="news-card__thumb"></div>
			<div class="news-card__body">
				<div class="news-card__title" style="color:var(--c-muted);font-size:13px;">Vijesti će se prikazivati ovdje</div>
			</div>
		</div>
		<?php endfor; ?>
	<?php endif; ?>
	</div>

</div>

<?php get_footer(); ?>
