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

/* ── Iz lige: zadnje odigrano kolo svake lige aktualnog natjecanja ─── */
$home_rounds = [];
$al          = function_exists( 'kurskc_active_league' ) ? kurskc_active_league() : null;
if ( $al && class_exists( 'Kurs_Rounds' ) ) {
	$hr_pan = Kurs_Season::is_pan_league( (string) $al['league'] );
	foreach ( Kurs_Season::get_divisions( (int) $al['season_id'] ) as $d ) {
		$did    = (int) $d->id;
		$status = Kurs_Rounds::round_status( $did );
		$r      = Kurs_Rounds::default_round( $did );
		if ( ! $r ) continue;
		$ms     = Kurs_Rounds::matches( $did, $r );
		$played = ( $status[ $r ]['finished'] ?? 0 ) > 0;

		// Vodeći (samo liga s jednom skupinom — Pan ima više skupina)
		$leader = null;
		$ph     = Kurs_Season::group_phase( $did );
		$grps   = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
		if ( $played && count( $grps ) === 1 ) {
			$st     = Kurs_Match::get_standings( (int) $grps[0]->id );
			$leader = $st[0] ?? null;
		}
		// Sljedeće kolo s neodigranim utakmicama
		$next = null;
		foreach ( $status as $nr => $nst ) {
			if ( $nr > $r && $nst['finished'] < $nst['total'] ) { $next = $nr; break; }
		}
		$home_rounds[] = [
			'name'    => $d->name,
			'tone'    => $hr_pan ? 'pan' : ( stripos( $d->name, 'briga' ) !== false ? 'lbb' : 'kc' ),
			'icon'    => $hr_pan ? 'Pan Ljetna' : $d->name,
			'round'   => $r,
			'played'  => $played,
			'date'    => Kurs_Rounds::date_label( $ms ),
			'matches' => $ms,
			'leader'  => $leader,
			'next'    => $next ? [ 'round' => $next, 'date' => Kurs_Rounds::date_label( Kurs_Rounds::matches( $did, $next ) ) ] : null,
			'url'     => kurs_league_url( $did, [ 'tab' => 'kola', 'kolo' => $r ] ) . '#kola',
		];
	}
}
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
			<?php elseif ( $hero_post ) : echo kurskc_thumb_placeholder( $hero_post->ID, 120 ); ?>
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
					<div class="league-card__cta">Rezultati &middot; Tablica &rarr;</div>
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
					<div class="league-card__cta">Rezultati &middot; Tablica &rarr;</div>
				</div>
			</a>
		</div>
	</div>

	<?php if ( $home_rounds ) : ?>
	<!-- ── IZ LIGE: zadnje kolo ───────────────────────────────────── -->
	<div class="sec-head">
		<h2 class="sec-head__title">Iz lige</h2>
		<a href="<?php echo esc_url( home_url( '/za-medije/' ) ); ?>" class="sec-head__link">Za medije &rarr;</a>
	</div>
	<div class="home-rounds<?php echo count( $home_rounds ) === 1 ? ' home-rounds--single' : ''; ?>">
		<?php foreach ( $home_rounds as $hb ) : ?>
		<section class="home-round home-round--<?php echo esc_attr( $hb['tone'] ); ?>">
			<a class="home-round__head" href="<?php echo esc_url( $hb['url'] ); ?>">
				<span class="home-round__icon"><?php echo kurs_league_icon( $hb['icon'], 34 ); ?></span>
				<span class="home-round__title">
					<span class="home-round__league"><?php echo esc_html( $hb['name'] ); ?></span>
					<span class="home-round__meta"><?php echo esc_html( $hb['round'] . '. kolo' . ( $hb['date'] ? ' · ' . $hb['date'] : '' ) ); ?></span>
				</span>
				<span class="home-round__badge"><?php echo $hb['played'] ? 'Rezultati' : 'Najava'; ?></span>
			</a>
			<div class="home-round__list">
				<?php foreach ( $hb['matches'] as $m ) :
					$done = $m->status === 'finished';
					$hw   = $done && (int) $m->home_score > (int) $m->away_score;
					$aw   = $done && (int) $m->away_score > (int) $m->home_score;
				?>
				<a class="hr-match" href="<?php echo esc_url( add_query_arg( 'match_id', (int) $m->id, home_url( '/utakmica/' ) ) ); ?>">
					<span class="hr-match__team<?php echo $hw ? ' is-win' : ''; ?>"><?php echo esc_html( $m->home_name ); ?></span>
					<?php if ( $done ) : ?>
					<span class="hr-match__score"><?php echo (int) $m->home_score; ?>:<?php echo (int) $m->away_score; ?></span>
					<?php else : ?>
					<span class="hr-match__score hr-match__score--tbd"><?php echo $m->match_date ? esc_html( date( 'H:i', strtotime( $m->match_date ) ) ) : '–'; ?></span>
					<?php endif; ?>
					<span class="hr-match__team hr-match__team--away<?php echo $aw ? ' is-win' : ''; ?>"><?php echo esc_html( $m->away_name ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<div class="home-round__foot">
				<?php if ( $hb['leader'] ) : ?>
				<span>Vodi: <b><?php echo esc_html( $hb['leader']['team_name'] ); ?></b> · <?php echo (int) $hb['leader']['Pts']; ?> bod.</span>
				<?php elseif ( $hb['next'] ) : ?>
				<span>Sljedeće: <b><?php echo (int) $hb['next']['round']; ?>. kolo</b><?php echo $hb['next']['date'] ? ' · ' . esc_html( $hb['next']['date'] ) : ''; ?></span>
				<?php else : ?>
				<span></span>
				<?php endif; ?>
				<a href="<?php echo esc_url( $hb['url'] ); ?>">Ljestvica i strijelci &rarr;</a>
			</div>
			<?php if ( $hb['leader'] && $hb['next'] ) : ?>
			<div class="home-round__next">Sljedeće: <b><?php echo (int) $hb['next']['round']; ?>. kolo</b><?php echo $hb['next']['date'] ? ' · ' . esc_html( $hb['next']['date'] ) : ''; ?></div>
			<?php endif; ?>
		</section>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

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
				<?php else : echo kurskc_thumb_placeholder( $p->ID ); ?>
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
