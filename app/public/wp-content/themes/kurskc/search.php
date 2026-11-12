<?php
/**
 * Rezultati pretrage (tema 1.0.56): ekipe · igrači · vijesti · stranice, filter ?tip=, istaknut pojam.
 * Glavni upit = vijesti (inc/search.php → pre_get_posts), ostale grupe dohvaćaju se ovdje.
 */
get_header();

$s     = get_search_query( false );
$tip   = isset( $_GET['tip'] ) && isset( KURSKC_SEARCH_TYPES[ $_GET['tip'] ] ) ? sanitize_key( $_GET['tip'] ) : '';
$short = mb_strlen( trim( $s ) ) < 2;

$groups = [];
if ( ! $short ) {
	foreach ( [ 'ekipe' => 12, 'igraci' => 24, 'stranice' => 6 ] as $key => $lim ) {
		$q = kurskc_search_query( KURSKC_SEARCH_TYPES[ $key ][0], $s, $tip === $key ? 60 : $lim );
		$groups[ $key ] = [ 'posts' => $q->posts, 'total' => (int) $q->found_posts ];
	}
	global $wp_query;
	$groups['vijesti'] = [ 'posts' => $wp_query->posts, 'total' => (int) $wp_query->found_posts ];
}
$total = array_sum( array_column( $groups, 'total' ) );
$url   = fn( string $t = '' ) => add_query_arg( array_filter( [ 's' => $s, 'tip' => $t ] ), home_url( '/' ) );
$show  = fn( string $k ) => ( ! $tip || $tip === $k ) && ! empty( $groups[ $k ]['posts'] );
?>

<div class="container srch">
	<section class="srch-hero">
		<p class="srch-hero__kicker">Pretraga</p>
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="srch-form">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
			<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Ekipa, igrač ili vijest…" aria-label="Pretraži" autocomplete="off">
			<?php if ( $tip ) : ?><input type="hidden" name="tip" value="<?php echo esc_attr( $tip ); ?>"><?php endif; ?>
			<button type="submit">Traži</button>
		</form>
		<?php if ( ! $short ) : ?>
		<p class="srch-hero__count"><?php echo $total ? '<b>' . (int) $total . '</b> ' . ( $total % 10 === 1 && $total % 100 !== 11 ? 'rezultat' : 'rezultata' ) . ' za' : 'Nema rezultata za'; ?> „<?php echo esc_html( $s ); ?>”</p>
		<nav class="srch-tabs" aria-label="Vrsta rezultata">
			<a href="<?php echo esc_url( $url() ); ?>" class="<?php echo $tip ? '' : 'is-on'; ?>">Sve <span><?php echo (int) $total; ?></span></a>
			<?php foreach ( [ 'ekipe', 'igraci', 'vijesti', 'stranice' ] as $k ) : if ( empty( $groups[ $k ]['total'] ) ) continue; ?>
			<a href="<?php echo esc_url( $url( $k ) ); ?>" class="<?php echo $tip === $k ? 'is-on' : ''; ?>"><?php echo esc_html( KURSKC_SEARCH_TYPES[ $k ][1] ); ?> <span><?php echo (int) $groups[ $k ]['total']; ?></span></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</section>

	<?php if ( $short ) : ?>
	<div class="srch-empty"><b>Upiši barem dva slova.</b><span>Npr. naziv ekipe, prezime igrača ili pojam iz vijesti.</span></div>

	<?php elseif ( ! $total ) : ?>
	<div class="srch-empty">
		<b>Ništa nismo pronašli.</b>
		<span>Provjeri pravopis ili probaj kraći pojam — kvačice nisu bitne („cekicari” nalazi „Čekićari”).</span>
		<div class="srch-empty__links">
			<a href="<?php echo esc_url( home_url( '/kc-liga/' ) ); ?>">KC liga</a>
			<a href="<?php echo esc_url( home_url( '/kc-liga/?tab=raspored#liga' ) ); ?>">Raspored</a>
			<a href="<?php echo esc_url( home_url( '/pan-ljetna/' ) ); ?>">Pan Ljetna liga</a>
			<a href="<?php echo esc_url( home_url( '/vijesti/' ) ); ?>">Sve vijesti</a>
		</div>
	</div>

	<?php else : ?>

	<?php if ( $show( 'ekipe' ) ) : ?>
	<section class="srch-sec">
		<h2 class="srch-sec__title">Ekipe <span><?php echo (int) $groups['ekipe']['total']; ?></span></h2>
		<div class="srch-teams">
			<?php foreach ( $groups['ekipe']['posts'] as $t ) : ?>
			<a class="srch-team" href="<?php echo esc_url( get_permalink( $t ) ); ?>">
				<?php echo kurs_team_badge_html( $t->ID, 44 ); ?>
				<span><?php echo kurskc_search_mark( $t->post_title, $s ); ?></span>
			</a>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $tip && $groups['ekipe']['total'] > count( $groups['ekipe']['posts'] ) ) : ?><a class="srch-more" href="<?php echo esc_url( $url( 'ekipe' ) ); ?>">Sve ekipe &rarr;</a><?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( $show( 'igraci' ) ) : ?>
	<section class="srch-sec">
		<h2 class="srch-sec__title">Igrači <span><?php echo (int) $groups['igraci']['total']; ?></span></h2>
		<div class="srch-players">
			<?php foreach ( $groups['igraci']['posts'] as $pl ) : $lt = kurskc_player_last_team( $pl->ID ); ?>
			<a class="srch-player" href="<?php echo esc_url( get_permalink( $pl ) ); ?>">
				<?php echo $lt ? kurs_team_badge_html( $lt[0], 30 ) : '<span class="srch-player__ph"></span>'; ?>
				<span class="srch-player__txt"><b><?php echo kurskc_search_mark( $pl->post_title, $s ); ?></b><small><?php echo esc_html( $lt[1] ?? 'Igrač' ); ?></small></span>
			</a>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $tip && $groups['igraci']['total'] > count( $groups['igraci']['posts'] ) ) : ?><a class="srch-more" href="<?php echo esc_url( $url( 'igraci' ) ); ?>">Svi igrači &rarr;</a><?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( $show( 'vijesti' ) ) : ?>
	<section class="srch-sec">
		<h2 class="srch-sec__title">Vijesti <span><?php echo (int) $groups['vijesti']['total']; ?></span></h2>
		<div class="srch-news">
			<?php foreach ( $groups['vijesti']['posts'] as $n ) : ?>
			<a class="srch-n" href="<?php echo esc_url( get_permalink( $n ) ); ?>">
				<div class="srch-n__img">
					<?php if ( has_post_thumbnail( $n ) ) : ?><img src="<?php echo esc_url( get_the_post_thumbnail_url( $n, 'kurskc_card' ) ); ?>" alt="" loading="lazy">
					<?php else : echo kurskc_thumb_placeholder( $n->ID, 40 ); endif; ?>
				</div>
				<div class="srch-n__body">
					<span class="srch-n__meta"><i style="background:<?php echo esc_attr( kurskc_badge_color( $n->ID ) ); ?>"></i><?php echo esc_html( kurskc_badge_label( $n->ID ) . ' · ' . get_the_date( 'j. n. Y.', $n ) ); ?></span>
					<h3 class="srch-n__title"><?php echo kurskc_search_mark( get_the_title( $n ), $s ); ?></h3>
					<p class="srch-n__snip"><?php echo kurskc_search_mark( kurskc_search_snippet( $n->post_excerpt . ' ' . $n->post_content, $s ), $s ); ?></p>
				</div>
			</a>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $tip && $groups['vijesti']['total'] > count( $groups['vijesti']['posts'] ) ) : ?><a class="srch-more" href="<?php echo esc_url( $url( 'vijesti' ) ); ?>">Sve vijesti (<?php echo (int) $groups['vijesti']['total']; ?>) &rarr;</a><?php endif; ?>
		<?php if ( $tip === 'vijesti' ) : ?><div class="srch-pages"><?php the_posts_pagination( [ 'mid_size' => 1, 'prev_text' => '&larr;', 'next_text' => '&rarr;' ] ); ?></div><?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( $show( 'stranice' ) ) : ?>
	<section class="srch-sec">
		<h2 class="srch-sec__title">Stranice <span><?php echo (int) $groups['stranice']['total']; ?></span></h2>
		<div class="srch-players">
			<?php foreach ( $groups['stranice']['posts'] as $pg ) : ?>
			<a class="srch-player" href="<?php echo esc_url( get_permalink( $pg ) ); ?>"><span class="srch-player__ph srch-player__ph--page"></span><span class="srch-player__txt"><b><?php echo kurskc_search_mark( $pg->post_title, $s ); ?></b><small>Stranica</small></span></a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php endif; ?>
</div>

<?php get_footer(); ?>
