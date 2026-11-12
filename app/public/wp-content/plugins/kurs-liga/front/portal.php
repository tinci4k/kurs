<?php
/**
 * /portal-ekipe/ — portal jedne ekipe (Kurs_Portal::render). Roster, sponzori i zahtjevi crtaju se
 * iz stanja (KPT.state) u portal.js; svaka promjena je zahtjev koji KURS odobrava.
 */
defined( 'ABSPATH' ) || exit;

/** @var int $team_id */
$team  = get_post( $team_id );
$ctx   = Kurs_Portal::context( $team_id );
$state = Kurs_Portal::state( $team_id );
$color = function_exists( 'kurs_team_color' ) ? kurs_team_color( $team_id ) : '#1a3a6b';
$league = $ctx ? ( stripos( $ctx->season_name, $ctx->division_name ) !== false ? $ctx->season_name : $ctx->division_name . ' · ' . $ctx->season_name ) : '';
$locked = $ctx && Kurs_Season::is_locked( (int) $ctx->season_id );
?><!doctype html>
<html lang="hr">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo function_exists( 'kurs_favicon_links' ) ? kurs_favicon_links() : ''; ?>
<title><?php echo esc_html( $team->post_title ); ?> — portal ekipe · KURS</title>
<?php wp_print_styles( [ 'kurs-portal' ] ); ?>
</head>
<body class="kp-body kpt">

<header class="kp-bar">
	<span class="kp-bar__brand">
		<img class="kpt-bar__logo" src="<?php echo esc_url( KURS_URL . 'assets/kurs-logo-white.png' ); ?>" alt="KURS">
		<span class="kp-bar__app">Portal ekipe</span>
	</span>
	<nav class="kp-bar__nav">
		<a href="#igraci">Igrači</a>
		<a href="#sponzori">Sponzori</a>
		<a href="#kontakt">Kontakt</a>
		<a href="#zahtjevi">Zahtjevi</a>
	</nav>
</header>

<main class="kp-main">

	<section class="kpt-hero" style="--tc:<?php echo esc_attr( $color ); ?>">
		<div class="kpt-hero__logo" id="kpt-logo">
			<?php if ( $state['logo'] ) : ?><img src="<?php echo esc_url( $state['logo'] ); ?>" alt=""><?php else : ?><span><?php echo esc_html( mb_strtoupper( mb_substr( $team->post_title, 0, 2 ) ) ); ?></span><?php endif; ?>
		</div>
		<div class="kpt-hero__txt">
			<p class="kp-eyebrow"><?php echo esc_html( $league ?: 'Ekipa nije u aktualnoj ligi' ); ?></p>
			<h1 class="kp-h1"><?php echo esc_html( $team->post_title ); ?></h1>
			<p class="kp-lead">Ovdje provjerite popis igrača, pošaljite ispravke, logo ekipe, sponzore i kontakt. <b>Ništa se ne mijenja odmah</b> — svaku promjenu KURS pregleda i odobri, a status vidite dolje pod „Vaši zahtjevi”.</p>
			<p class="kp-muted kpt-private">🔒 Link je samo za vašu ekipu. Ne objavljujte ga javno — ako ga netko neovlašten dobije, KURS može izdati novi.</p>
		</div>
	</section>

	<div class="kpt-who">
		<label>Tko šalje? <input type="text" id="kp-who" maxlength="80" placeholder="Ime i prezime (voditelj, kapetan…)" autocomplete="name"></label>
	</div>

	<?php if ( $locked ) : ?>
	<p class="kpt-warn">Sezona je zaključana — popis igrača se više ne mijenja. Logo, sponzore i kontakt i dalje možete poslati.</p>
	<?php endif; ?>

	<!-- ── Igrači ─────────────────────────────────────────────────────── -->
	<section class="kp-section kpt-card" id="igraci">
		<div class="kp-section__hd">
			<h2 class="kp-h2">Igrači <small class="kpt-count" id="kpt-n"></small></h2>
		</div>
		<p class="kp-muted">Provjerite imena i brojeve. Kliknite „Ispravi” uz igrača ili „Nije više u ekipi”. Novog igrača dodajte ispod popisa.</p>
		<ul class="kpt-roster" id="kpt-roster"></ul>
		<?php if ( ! $locked && $ctx ) : ?>
		<form class="kpt-form kpt-form--row" data-do="player_add" novalidate>
			<p class="kpt-form__h">Dodaj igrača</p>
			<label class="kp-field kpt-grow"><span>Ime i prezime *</span><input type="text" name="name" maxlength="80" required placeholder="npr. Ivan Horvat" autocomplete="off"></label>
			<label class="kp-field kpt-num"><span>Broj</span><input type="number" name="number" min="0" max="99" inputmode="numeric" placeholder="–"></label>
			<label class="kpt-check"><input type="checkbox" name="gk" value="1"> golman</label>
			<div class="kp-row"><button type="submit" class="kp-btn">Pošalji</button><span class="kp-status" role="status" aria-live="polite"></span></div>
		</form>
		<?php endif; ?>
	</section>

	<div class="kpt-grid">
		<!-- ── Logo ekipe (jedan) ───────────────────────────────────────── -->
		<section class="kp-section kpt-card" id="logo">
			<h2 class="kp-h2">Logo ekipe</h2>
			<p class="kp-muted">Ekipa ima <b>jedan logo</b> — prikazuje se uz ime u tablicama, rasporedu i na profilu. Novi logo zamjenjuje postojeći. Najbolje PNG s prozirnom pozadinom, kvadratni, do 5 MB.</p>
			<div class="kpt-logo-now"><span class="kpt-logo-now__img" id="kpt-logo-now"></span><span class="kpt-logo-now__txt" id="kpt-logo-txt"></span></div>
			<form class="kpt-form" data-do="logo" enctype="multipart/form-data" novalidate>
				<label class="kp-field"><span>Nova slika loga *</span><input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required></label>
				<div class="kp-row"><button type="submit" class="kp-btn">Pošalji logo</button><span class="kp-status" role="status" aria-live="polite"></span></div>
			</form>
		</section>

		<!-- ── Boje dresa ───────────────────────────────────────────────── -->
		<section class="kp-section kpt-card" id="dres">
			<h2 class="kp-h2">Boje dresa</h2>
			<p class="kp-muted">Glavna boja je boja ekipe na webu (oznake, tablice). Ako je dres dvobojni, odaberite i drugu boju.</p>
			<form class="kpt-form kpt-kit" data-do="colors" novalidate>
				<div class="kpt-kit__preview" id="kpt-kit"></div>
				<div class="kpt-kit__pick">
					<div class="kp-field"><span>Glavna boja *</span>
						<div class="kpt-sw" data-for="color"><?php foreach ( Kurs_Portal::COLORS as $c ) : ?><button type="button" class="kpt-sw__b" data-c="<?php echo esc_attr( strtolower( $c ) ); ?>" style="background:<?php echo esc_attr( $c ); ?>" aria-label="<?php echo esc_attr( $c ); ?>"></button><?php endforeach; ?><label class="kpt-sw__own" title="Vlastita boja"><input type="color" data-own="color" aria-label="Vlastita glavna boja"><span>+</span></label></div>
					</div>
					<div class="kp-field"><span>Druga boja</span>
						<div class="kpt-sw" data-for="color2"><button type="button" class="kpt-sw__b kpt-sw__none" data-c="" aria-label="Jednobojni dres">Nema</button><?php foreach ( Kurs_Portal::COLORS as $c ) : ?><button type="button" class="kpt-sw__b" data-c="<?php echo esc_attr( strtolower( $c ) ); ?>" style="background:<?php echo esc_attr( $c ); ?>" aria-label="<?php echo esc_attr( $c ); ?>"></button><?php endforeach; ?><label class="kpt-sw__own" title="Vlastita boja"><input type="color" data-own="color2" aria-label="Vlastita druga boja"><span>+</span></label></div>
					</div>
					<input type="hidden" name="color"><input type="hidden" name="color2">
					<div class="kp-row"><button type="submit" class="kp-btn">Pošalji boje</button><span class="kp-status" role="status" aria-live="polite"></span></div>
				</div>
			</form>
		</section>
	</div>

	<div class="kpt-solo">

		<!-- ── Kontakt ──────────────────────────────────────────────────── -->
		<section class="kp-section kpt-card" id="kontakt">
			<h2 class="kp-h2">Kontakt ekipe</h2>
			<p class="kp-muted">Ime i telefon voditelja i trenera prikazuju se na profilu ekipe na kurskc.hr. Ako telefon ne želite objaviti, ostavite ga prazno.</p>
			<form class="kpt-form kpt-form--2" data-do="info" novalidate>
				<label class="kp-field"><span>Voditelj ekipe</span><input type="text" name="rep" maxlength="80" value="<?php echo esc_attr( $state['info']['rep'] ); ?>"></label>
				<label class="kp-field"><span>Telefon voditelja</span><input type="tel" name="rep_phone" maxlength="40" value="<?php echo esc_attr( $state['info']['rep_phone'] ); ?>"></label>
				<label class="kp-field"><span>Trener</span><input type="text" name="coach" maxlength="80" value="<?php echo esc_attr( $state['info']['coach'] ); ?>"></label>
				<label class="kp-field"><span>Telefon trenera</span><input type="tel" name="coach_phone" maxlength="40" value="<?php echo esc_attr( $state['info']['coach_phone'] ); ?>"></label>
				<div class="kp-row"><button type="submit" class="kp-btn">Pošalji promjene</button><span class="kp-status" role="status" aria-live="polite"></span></div>
			</form>
		</section>
	</div>

	<!-- ── Sponzori ekipe ─────────────────────────────────────────────── -->
	<section class="kp-section kpt-card" id="sponzori">
		<h2 class="kp-h2">Sponzori ekipe</h2>
		<p class="kp-muted">Prikazuju se na vrhu profila vaše ekipe na kurskc.hr. Najviše <?php echo (int) Kurs_Portal::MAX_SPONS; ?> sponzora — pošaljite naziv, logo (ili baner) i link.</p>
		<ul class="kpt-spons" id="kpt-spons"></ul>
		<form class="kpt-form kpt-form--2" data-do="sponsor_add" enctype="multipart/form-data" novalidate>
			<p class="kpt-form__h">Dodaj sponzora</p>
			<label class="kp-field"><span>Naziv sponzora *</span><input type="text" name="name" maxlength="120" required placeholder="npr. Pekara Kruh"></label>
			<label class="kp-field"><span>Link (web ili Facebook)</span><input type="url" name="link" maxlength="300" placeholder="https://…" inputmode="url"></label>
			<label class="kp-field"><span>Logo ili baner *</span><input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required></label>
			<div class="kp-row"><button type="submit" class="kp-btn">Pošalji sponzora</button><span class="kp-status" role="status" aria-live="polite"></span></div>
		</form>
	</section>

	<!-- ── Poruka ──────────────────────────────────────────────────────── -->
	<section class="kp-section kpt-card">
		<h2 class="kp-h2">Poruka KURS-u</h2>
		<form class="kpt-form" data-do="note" novalidate>
			<label class="kp-field"><span>Nešto drugo? Napišite.</span><textarea name="text" rows="3" maxlength="2000" class="kp-text" placeholder="npr. promjena boje dresa, termin, pitanje…"></textarea></label>
			<div class="kp-row"><button type="submit" class="kp-btn">Pošalji poruku</button><span class="kp-status" role="status" aria-live="polite"></span></div>
		</form>
	</section>

	<!-- ── Zahtjevi ────────────────────────────────────────────────────── -->
	<section class="kp-section kpt-card" id="zahtjevi">
		<h2 class="kp-h2">Vaši zahtjevi</h2>
		<ol class="kpt-reqs" id="kpt-reqs"></ol>
	</section>

	<input type="text" id="kp-web" name="web" tabindex="-1" autocomplete="off" aria-hidden="true" class="kp-hp">
</main>

<footer class="kp-foot">KURS — Koprivnička udruga za rekreativni sport · kurskc.hr · portal ekipe <?php echo esc_html( $team->post_title ); ?></footer>

<script>
window.KPT = <?php echo wp_json_encode( [
	'ajax'   => admin_url( 'admin-ajax.php' ),
	'e'      => $team_id,
	'k'      => Kurs_Portal::key( $team_id ),
	'locked' => $locked || ! $ctx,
	'state'  => $state,
] ); ?>;
</script>
<script src="<?php echo esc_url( KURS_URL . 'front/portal.js?ver=' . KURS_VERSION ); ?>"></script>
</body>
</html>
