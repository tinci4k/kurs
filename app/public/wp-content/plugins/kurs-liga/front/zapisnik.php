<?php
/**
 * /zapisnik/ — samostalna stranica za zapisničare (Kurs_Zapisnik::render).
 * Stanja: prijava · račun bez ovlasti · popis utakmica · zapisnik utakmice (admin/views/match-record.php).
 */
defined( 'ABSPATH' ) || exit;

$user     = wp_get_current_user();
$logged   = $user->exists();
$can      = current_user_can( KURS_RECORD_CAP );
$match_id = absint( $_GET['match_id'] ?? 0 );
$here     = Kurs_Zapisnik::url( $match_id );

$match_row = function ( object $m, string $mode ) {
	$finished = $mode === 'done';
	$time     = $mode === 'day' ? date( 'H:i', strtotime( $m->match_date ) ) : Kurs_Admin::match_when( $m );
	?>
	<a class="kz-match kz-match--<?php echo esc_attr( $mode ); ?>" href="<?php echo esc_url( Kurs_Zapisnik::url( (int) $m->id ) ); ?>">
		<span class="kz-match__time"><?php echo esc_html( $time ); ?></span>
		<span class="kz-match__body">
			<span class="kz-match__teams">
				<span class="kz-match__team"><?php echo esc_html( $m->home_name ); ?></span>
				<span class="kz-match__vs"><?php echo $finished ? (int) $m->home_score . ' : ' . (int) $m->away_score : '–'; ?></span>
				<span class="kz-match__team"><?php echo esc_html( $m->away_name ); ?></span>
			</span>
			<span class="kz-match__meta"><?php echo Kurs_Admin::league_tag( $m ); ?> <?php echo esc_html( Kurs_Admin::match_stage( $m ) ); ?></span>
		</span>
		<span class="kz-match__go"><?php echo $mode === 'late' ? 'Unesi zapisnik' : ( $finished ? 'Pogledaj' : 'Zapisnik' ); ?> &rarr;</span>
	</a>
	<?php
};
?><!doctype html>
<html lang="hr">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo function_exists( 'kurs_favicon_links' ) ? kurs_favicon_links() : ''; ?>
<title><?php echo $can && $match_id ? 'Zapisnik utakmice' : 'Zapisnik'; ?> — KURS</title>
<?php wp_print_styles( [ 'kurs-zapisnik' ] ); // samo naši stilovi (+ ovisnosti), bez teme ?>
</head>
<body class="kz-body<?php echo $can && $match_id ? ' kz-body--record' : ''; ?>">

<header class="kz-bar no-print">
	<a class="kz-bar__brand" href="<?php echo esc_url( Kurs_Zapisnik::url() ); ?>">
		<span class="kz-bar__stripes" aria-hidden="true"><i></i><i></i><i></i></span>
		<span class="kz-bar__name">KURS</span>
		<span class="kz-bar__app">Zapisnik</span>
	</a>
	<?php if ( $logged ) : ?>
	<nav class="kz-bar__user">
		<span class="kz-bar__who"><?php echo esc_html( $user->display_name ); ?></span>
		<?php if ( current_user_can( KURS_CAP ) ) : ?>
		<a href="<?php echo esc_url( $match_id ? Kurs_Admin::record_url( $match_id ) : admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">Administracija</a>
		<?php endif; ?>
		<a href="<?php echo esc_url( wp_logout_url( Kurs_Zapisnik::url() ) ); ?>">Odjava</a>
	</nav>
	<?php endif; ?>
</header>

<main class="kz-main">
<?php if ( ! $logged ) : ?>

	<section class="kz-card kz-login">
		<h1 class="kz-h1">Prijava zapisničara</h1>
		<p class="kz-lead">Prijavi se korisničkim imenom i lozinkom koje si dobio od KURS-a.</p>
		<?php wp_login_form( [
			'redirect'       => $here,
			'label_username' => 'Korisničko ime ili e-mail',
			'label_password' => 'Lozinka',
			'label_remember' => 'Zapamti me na ovom uređaju',
			'label_log_in'   => 'Prijava',
			'remember'       => true,
		] ); ?>
		<p class="kz-small"><a href="<?php echo esc_url( wp_lostpassword_url( $here ) ); ?>">Zaboravljena lozinka?</a></p>
	</section>

<?php elseif ( ! $can ) : ?>

	<section class="kz-card">
		<h1 class="kz-h1">Nema pristupa</h1>
		<p class="kz-lead">Račun <strong><?php echo esc_html( $user->user_login ); ?></strong> nema ovlast za vođenje zapisnika. Ako bi trebao, javi se KURS-u.</p>
		<p><a class="kz-btn" href="<?php echo esc_url( wp_logout_url( Kurs_Zapisnik::url() ) ); ?>">Odjava</a></p>
	</section>

<?php elseif ( $match_id ) : ?>

	<?php
	$GLOBALS['kurs_record_front'] = true;
	require KURS_DIR . 'admin/views/match-record.php';
	?>

<?php else :
	$l = Kurs_Zapisnik::lists(); ?>

	<h1 class="kz-h1">Utakmice</h1>
	<p class="kz-lead">Otvori utakmicu, upiši tko je igrao, golove i kartone, pa na dnu klikni <strong>„Završi i spremi rezultat”</strong>.</p>

	<?php if ( $l['late'] ) : ?>
	<section class="kz-sec kz-sec--late">
		<h2 class="kz-h2">Čeka rezultat <span class="kz-count"><?php echo count( $l['late'] ); ?></span></h2>
		<p class="kz-hint">Ove su utakmice odigrane, a zapisnik još nije završen.</p>
		<?php foreach ( $l['late'] as $m ) $match_row( $m, 'late' ); ?>
	</section>
	<?php endif; ?>

	<section class="kz-sec">
		<h2 class="kz-h2">Nadolazeće utakmice</h2>
		<?php if ( $l['days'] ) : ?>
			<?php foreach ( $l['days'] as $d => $ms ) : ?>
			<h3 class="kz-day<?php echo $d === $l['today'] ? ' is-today' : ''; ?>"><?php echo esc_html( Kurs_Zapisnik::day_label( $d, $l['today'] ) ); ?></h3>
			<?php foreach ( $ms as $m ) $match_row( $m, 'day' ); ?>
			<?php endforeach; ?>
			<?php if ( $l['more'] ) : ?>
			<p class="kz-hint">Još <?php echo (int) $l['more']; ?> utakmica kasnije — pojavit će se ovdje kako se budu približavale.</p>
			<?php endif; ?>
		<?php else : ?>
			<p class="kz-empty">Nema zakazanih utakmica. Kad KURS upiše raspored, ovdje će se pojaviti.</p>
		<?php endif; ?>
	</section>

	<?php if ( $l['done'] ) : ?>
	<section class="kz-sec kz-sec--done">
		<h2 class="kz-h2">Nedavno završene</h2>
		<p class="kz-hint">Za pregled i ispis. Ako treba ispraviti grešku, u zapisniku klikni „Otvori za ispravak”.</p>
		<?php foreach ( $l['done'] as $m ) $match_row( $m, 'done' ); ?>
	</section>
	<?php endif; ?>

<?php endif; ?>
</main>

<?php if ( $can ) wp_print_scripts( [ 'kurs-admin' ] ); ?>
</body>
</html>
