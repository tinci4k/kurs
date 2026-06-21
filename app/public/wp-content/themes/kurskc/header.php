<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="container">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-title">
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<nav class="site-nav" aria-label="Glavni izbornik">
			<?php
			wp_nav_menu( [
				'theme_location' => 'primary',
				'menu_class'     => 'nav-list',
				'container'      => false,
				'fallback_cb'    => false,
			] );
			?>
		</nav>

		<button class="nav-toggle" aria-label="Otvori izbornik" aria-expanded="false">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>

<main id="main" class="site-main">
