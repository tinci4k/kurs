<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php $kc_img = get_template_directory_uri() . '/assets/img/'; // favicon = grb KC lige (tema 1.0.46) ?>
	<link rel="icon" href="<?php echo esc_url( $kc_img . 'favicon.ico' ); ?>" sizes="any">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $kc_img . 'favicon-32.png' ); ?>">
	<link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url( $kc_img . 'favicon-192.png' ); ?>">
	<link rel="apple-touch-icon" href="<?php echo esc_url( $kc_img . 'apple-touch-icon.png' ); ?>">
	<meta name="theme-color" content="#111317">
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
		$tk_dur    = max( 40, $tk_count * 8 );       // trajanje petlje (s) ~ broj stavki (1.0.50: sporije, da se stigne pročitati)
		// Sve najave isti dan → dan jednom u oznaci („Najava · sub 14.11."), uz utakmicu samo sat
		$tk_days   = array_unique( array_filter( array_map( fn( $it ) => $it['kind'] === 'upcoming' ? ( $it['day'] ?? '' ) : null, $tk['items'] ) ) );
		$tk_oneday = count( $tk_days ) === 1 && ! array_filter( $tk['items'], fn( $it ) => $it['kind'] !== 'upcoming' ) ? reset( $tk_days ) : '';
		$tk_loops  = $tk_static ? 1 : 2;             // dupliciraj stavke za bešavni loop
?>
<div class="site-ticker site-ticker--<?php echo esc_attr( $tk['status'] ); ?><?php echo $tk_static ? ' is-static' : ''; ?>" data-status="<?php echo esc_attr( $tk['status'] ); ?>">
	<div class="site-ticker__badge"><?php echo esc_html( $tk['label'] ); ?><?php if ( $tk_oneday ) : ?><span class="site-ticker__day"><?php echo esc_html( $tk_oneday ); ?></span><?php endif; ?></div>
	<div class="site-ticker__viewport">
		<div class="site-ticker__track"<?php echo $tk_static ? '' : ' style="animation-duration:' . (int) $tk_dur . 's"'; ?>>
			<?php for ( $L = 0; $L < $tk_loops; $L++ ) : foreach ( $tk['items'] as $it ) : ?>
			<a class="ticker-item ticker-item--<?php echo esc_attr( $it['kind'] ); ?>" href="<?php echo esc_url( $it['url'] ); ?>"<?php echo $L ? ' aria-hidden="true" tabindex="-1"' : ''; ?>>
				<?php if ( ! empty( $it['icon'] ) ) : ?><span class="ticker-item__ico"><?php echo $it['icon']; ?></span><?php endif; ?>
				<?php if ( ! empty( $it['league'] ) ) : ?><span class="ticker-item__lg ticker-item__lg--<?php echo esc_attr( $it['tone'] ?? 'kc' ); ?>"><?php echo esc_html( $it['league'] ); ?></span><?php endif; ?>
				<?php if ( $it['kind'] === 'champ' ) : ?>
					<span class="ticker-item__txt"><?php echo esc_html( $it['text'] ); ?></span>
				<?php elseif ( $it['kind'] === 'finished' ) : ?>
					<span class="ticker-item__tm"><?php echo esc_html( $it['home'] ); ?></span>
					<span class="ticker-item__score"><b><?php echo (int) $it['home_score']; ?></b><i>:</i><b><?php echo (int) $it['away_score']; ?></b></span>
					<span class="ticker-item__tm"><?php echo esc_html( $it['away'] ); ?></span>
				<?php elseif ( $it['kind'] === 'upcoming' ) : ?>
					<?php if ( ! empty( $it['time'] ) ) : ?><span class="ticker-item__time"><?php if ( ! $tk_oneday && ! empty( $it['day'] ) ) : ?><small><?php echo esc_html( $it['day'] ); ?></small><?php endif; ?><?php echo esc_html( $it['time'] ); ?></span><?php endif; ?>
					<span class="ticker-item__tm"><?php echo esc_html( $it['home'] ); ?></span><span class="ticker-item__vs">–</span><span class="ticker-item__tm"><?php echo esc_html( $it['away'] ); ?></span>
				<?php else : /* live / u tijeku */ ?>
					<span class="ticker-item__tm"><?php echo esc_html( $it['home'] ); ?></span><span class="ticker-item__vs">–</span><span class="ticker-item__tm"><?php echo esc_html( $it['away'] ); ?></span>
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
			<div class="site-nav__mob site-nav__mobhead" aria-hidden="true">
				<span class="site-nav__mobstripes"><i></i><i></i><i></i></span>
				<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/KURS-clean.png' ); ?>" alt="" width="96" height="28">
			</div>
			<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-nav__mob site-nav__search" data-suggest="<?php echo esc_url( rest_url( 'kurskc/v1/search' ) ); ?>">
				<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
				<input type="search" name="s" placeholder="Traži ekipu, igrača, vijest…" aria-label="Pretraži" autocomplete="off">
			</form>
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
					<?php $kc_divs = function_exists( 'kurskc_league_divisions' ) ? kurskc_league_divisions( false ) : [];
					if ( count( $kc_divs ) > 1 ) : ?>
					<span class="nav-dd__lbl">Lige</span>
					<?php foreach ( $kc_divs as $kd ) : ?>
					<a href="<?php echo esc_url( $kd['url'] ); ?>" role="menuitem" class="nav-dd__league"><?php echo function_exists( 'kurs_league_icon' ) ? kurs_league_icon( $kd['icon'], 18 ) : ''; ?><?php echo esc_html( $kd['name'] ); ?></a>
					<?php endforeach; ?>
					<span class="nav-dd__sep" aria-hidden="true"></span>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( '/kc-liga/?tab=tablica#liga' ) ); ?>" role="menuitem">Tablica</a>
					<a href="<?php echo esc_url( home_url( '/kc-liga/?tab=kola#kola' ) ); ?>" role="menuitem">Po kolima</a>
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
					<a href="<?php echo esc_url( home_url( '/pan-ljetna/?tab=kola#kola' ) ); ?>" role="menuitem">Po kolima</a>
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
			<div class="site-nav__mob site-nav__mobfoot">
				<?php echo function_exists( 'kurskc_social_icons' ) ? kurskc_social_icons( 'site-nav__social' ) : ''; ?>
				<span>Koprivnička udruga za rekreativni sport</span>
			</div>
		</nav>

		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="header-search" data-suggest="<?php echo esc_url( rest_url( 'kurskc/v1/search' ) ); ?>">
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
