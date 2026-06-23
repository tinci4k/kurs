<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_template_directory_uri() . '/assets/img/favicon.svg' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
/* ── Globalni Live Ticker — status i sadržaj iz rasporeda (vidi Kurs_Ticker) ── */
if ( class_exists( 'Kurs_Ticker' ) ) :
	$tk = Kurs_Ticker::get_state();
	if ( Kurs_Ticker::is_visible( $tk ) ) :
		$tk_count  = count( $tk['items'] );
		$tk_static = $tk_count <= 3;                 // premalo stavki za vrtnju → statički
		$tk_dur    = max( 24, $tk_count * 5 );       // trajanje petlje (s) ~ broj stavki
		$tk_loops  = $tk_static ? 1 : 2;             // dupliciraj stavke za bešavni loop
?>
<div class="site-ticker site-ticker--<?php echo esc_attr( $tk['status'] ); ?><?php echo $tk_static ? ' is-static' : ''; ?>" data-status="<?php echo esc_attr( $tk['status'] ); ?>">
	<div class="site-ticker__badge"><?php echo esc_html( $tk['label'] ); ?></div>
	<div class="site-ticker__viewport">
		<div class="site-ticker__track"<?php echo $tk_static ? '' : ' style="animation-duration:' . (int) $tk_dur . 's"'; ?>>
			<?php for ( $L = 0; $L < $tk_loops; $L++ ) : foreach ( $tk['items'] as $it ) : ?>
			<a class="ticker-item ticker-item--<?php echo esc_attr( $it['kind'] ); ?>" href="<?php echo esc_url( $it['url'] ); ?>"<?php echo $L ? ' aria-hidden="true" tabindex="-1"' : ''; ?>>
				<?php if ( ! empty( $it['icon'] ) ) : ?><span class="ticker-item__ico"><?php echo $it['icon']; ?></span><?php endif; ?>
				<?php if ( ! empty( $it['league'] ) ) : ?><span class="ticker-item__lg lg-<?php echo esc_attr( strtolower( $it['league'] ) ); ?>"><?php echo esc_html( $it['league'] ); ?></span><?php endif; ?>
				<?php if ( $it['kind'] === 'champ' ) : ?>
					<span class="ticker-item__txt"><?php echo esc_html( $it['text'] ); ?></span>
				<?php elseif ( $it['kind'] === 'finished' ) : ?>
					<span class="ticker-item__tm"><?php echo esc_html( $it['home'] ); ?></span>
					<b class="ticker-item__sc"><?php echo (int) $it['home_score']; ?></b><span class="ticker-item__dash">&ndash;</span><b class="ticker-item__sc"><?php echo (int) $it['away_score']; ?></b>
					<span class="ticker-item__tm"><?php echo esc_html( $it['away'] ); ?></span>
				<?php elseif ( $it['kind'] === 'upcoming' ) : ?>
					<?php if ( ! empty( $it['time'] ) ) : ?><span class="ticker-item__time"><?php echo esc_html( $it['time'] ); ?></span><?php endif; ?>
					<span class="ticker-item__tm"><?php echo esc_html( $it['home'] ); ?></span><span class="ticker-item__vs">vs</span><span class="ticker-item__tm"><?php echo esc_html( $it['away'] ); ?></span>
				<?php else : /* live / u tijeku */ ?>
					<span class="ticker-item__tm"><?php echo esc_html( $it['home'] ); ?></span><span class="ticker-item__vs">vs</span><span class="ticker-item__tm"><?php echo esc_html( $it['away'] ); ?></span>
					<span class="ticker-item__note"><?php echo esc_html( $it['note'] ); ?></span>
				<?php endif; ?>
			</a>
			<?php endforeach; endfor; ?>
		</div>
	</div>
</div>
<?php endif; endif; ?>

<header class="site-header">
	<div class="container">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/KURS-clean-ink.png' ); ?>"
			     alt="<?php bloginfo( 'name' ); ?>"
			     width="120" height="26">
		</a>

		<nav class="site-nav" id="site-nav" aria-label="Glavni izbornik">
			<a href="<?php echo esc_url( home_url( '/vijesti/' ) ); ?>"
			   <?php if ( is_post_type_archive( 'vijest' ) || is_singular( 'vijest' ) ) echo 'aria-current="page"'; ?>>
				Vijesti
			</a>

			<span class="site-nav__divider" aria-hidden="true"></span>

			<span class="nav-league-wrap nav-kc-wrap">
				<a href="<?php echo esc_url( home_url( '/kc-liga/' ) ); ?>"
				   class="nav-league nav-kc"
				   <?php if ( is_page( 'kc-liga' ) ) echo 'aria-current="page"'; ?>>
					<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( 'KC Liga', 17 ) : '<span class="nav-league__dot"></span>'; ?>KC Liga
					<span class="nav-league__caret" aria-hidden="true">▾</span>
				</a>
				<div class="nav-dd" role="menu" aria-label="KC Liga — brzi pristup">
					<a href="<?php echo esc_url( home_url( '/kc-liga/?tab=tablica#liga' ) ); ?>" role="menuitem">Tablica</a>
					<a href="<?php echo esc_url( home_url( '/kc-liga/?tab=raspored#liga' ) ); ?>" role="menuitem">Raspored</a>
					<a href="<?php echo esc_url( home_url( '/kc-liga/?tab=statistika#liga' ) ); ?>" role="menuitem">Statistika</a>
				</div>
			</span>
			<span class="nav-league-wrap nav-pan-wrap">
				<a href="<?php echo esc_url( home_url( '/pan-ljetna/' ) ); ?>"
				   class="nav-league nav-pan"
				   <?php if ( is_page( 'pan-ljetna' ) ) echo 'aria-current="page"'; ?>>
					<?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( 'Pan Ljetna', 17 ) : '<span class="nav-league__dot"></span>'; ?>Pan Ljetna liga
					<span class="nav-league__caret" aria-hidden="true">▾</span>
				</a>
				<div class="nav-dd" role="menu" aria-label="Pan Ljetna — brzi pristup">
					<a href="<?php echo esc_url( home_url( '/pan-ljetna/?tab=tablica#liga' ) ); ?>" role="menuitem">Tablica</a>
					<a href="<?php echo esc_url( home_url( '/pan-ljetna/?tab=raspored#liga' ) ); ?>" role="menuitem">Raspored</a>
					<a href="<?php echo esc_url( home_url( '/pan-ljetna/?tab=statistika#liga' ) ); ?>" role="menuitem">Statistika</a>
				</div>
			</span>

			<span class="site-nav__divider" aria-hidden="true"></span>

			<a href="<?php echo esc_url( home_url( '/o-kurs-u/' ) ); ?>"
			   <?php if ( is_page( 'o-kurs-u' ) ) echo 'aria-current="page"'; ?>>
				O nama
			</a>
			<a href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>"
			   <?php if ( is_page( 'kontakt' ) ) echo 'aria-current="page"'; ?>>
				Kontakt
			</a>
		</nav>

		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="header-search">
			<input type="search" name="s" class="header-search__input"
			       placeholder="Pretraži…"
			       value="<?php echo esc_attr( get_search_query() ); ?>"
			       aria-label="Pretraži vijesti, ekipe, igrače" autocomplete="off">
			<button type="submit" class="header-search__btn" aria-label="Traži">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
			</button>
		</form>

		<button class="header-search-toggle" id="search-toggle" type="button" aria-label="Pretraži" aria-expanded="false">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
		</button>

		<button class="nav-toggle" id="nav-toggle" aria-label="Otvori izbornik" aria-expanded="false">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>

<div class="site-box">
<main id="main" class="site-main">
