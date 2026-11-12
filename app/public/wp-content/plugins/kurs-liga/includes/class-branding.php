<?php
/**
 * KURS izgled prijave (1.31.0): wp-login.php (prijava, zaboravljena lozinka, nova lozinka)
 * u bojama i fontovima KURS-a, s logom KURS-a umjesto WordPressovog. Zapisnik (/zapisnik/) ima
 * svoju prijavu u istom stilu (front/zapisnik.php + zapisnik.css).
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Branding {

	public static function init(): void {
		add_action( 'login_enqueue_scripts', [ __CLASS__, 'login_styles' ] );
		add_filter( 'login_headerurl',  fn() => home_url( '/' ) );
		add_filter( 'login_headertext', fn() => 'KURS — Koprivnička udruga za rekreativni sport' );
		add_filter( 'login_display_language_dropdown', '__return_false' );
		add_action( 'login_init', function () {
			add_filter( 'gettext', [ __CLASS__, 'login_hr' ], 10, 2 );
			add_filter( 'gettext_with_context', fn( $tr, $text ) => self::login_hr( $tr, $text ), 10, 2 );
		} );
	}

	/** Hrvatski tekstovi na ekranu prijave (sučelje stranice je na engleskom). */
	public static function login_hr( string $tr, string $text ): string {
		static $hr = [
			'Username or Email Address' => 'Korisničko ime ili e-mail',
			'Password'                  => 'Lozinka',
			'Remember Me'               => 'Zapamti me',
			'Log In'                    => 'Prijava',
			'Lost your password?'       => 'Zaboravljena lozinka?',
			'&larr; Go to %s'           => '&larr; Natrag na %s',
			'Get New Password'          => 'Pošalji novu lozinku',
			'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.' => 'Upišite korisničko ime ili e-mail. Dobit ćete poruku s uputama za novu lozinku.',
			'Show password'             => 'Prikaži lozinku',
			'Hide password'             => 'Sakrij lozinku',
			'You are now logged out.'   => 'Odjavljeni ste.',
			'Check your email for the confirmation link, then visit the <a href="%s">login page</a>.' => 'Provjerite e-mail (poveznica za potvrdu), zatim otvorite <a href="%s">prijavu</a>.',
			'Save Password'             => 'Spremi lozinku',
			'New password'              => 'Nova lozinka',
			'Generate Password'         => 'Generiraj lozinku',
		];
		return $hr[ $text ] ?? $tr;
	}

	public static function login_styles(): void {
		require_once KURS_DIR . 'admin/class-admin.php';
		wp_enqueue_style( 'kurs-admin-fonts', Kurs_Admin::FONTS_URL, [], null );
		$logo = esc_url( KURS_URL . 'assets/kurs-logo-white.png' );
		$css  = <<<CSS
body.login { background:#111317 radial-gradient(1200px 600px at 50% -10%, #23262d 0%, #111317 60%) no-repeat; font-family:'Barlow', -apple-system, 'Segoe UI', sans-serif; color:#111317; }
body.login::before { content:''; position:fixed; top:0; left:0; right:0; height:6px; background:linear-gradient(90deg, #DA0F1E 0 33.3%, #F47B16 33.3% 66.6%, #FDE206 66.6%); z-index:5; }
#login { width:360px; padding:7vh 0 24px; }
#login h1 { margin-bottom:22px; }
#login h1 a { background-image:url('{$logo}'); background-size:contain; background-position:center; width:250px; height:98px; margin:0 auto; }
#login h1::after { content:'Administracija KURS lige'; display:block; margin-top:12px; text-align:center; font-family:'Space Mono', monospace; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:#a9a9a2; }
.login form { border:0; border-radius:14px; padding:26px 26px 22px; box-shadow:0 18px 50px rgba(0,0,0,.45); border-top:5px solid #DA0F1E; }
.login label { font-weight:600; font-size:14px; color:#111317; }
.login form .input, .login input[type=text], .login input[type=password], .login input[type=email] { border:1.5px solid #e2e2da; border-radius:8px; font-size:17px; padding:6px 10px; min-height:44px; box-shadow:none; background:#fff; }
.login form .input:focus, .login input:focus { border-color:#DA0F1E; box-shadow:0 0 0 1px #DA0F1E; outline:0; }
.login .button.wp-hide-pw { color:#111317; height:44px; }
.login .button.wp-hide-pw:focus { border-color:#DA0F1E; box-shadow:none; }
.login .forgetmenot label { font-weight:500; }
.login input[type=checkbox]:checked::before { filter:hue-rotate(140deg) saturate(3); }
.wp-core-ui .button-primary { background:#DA0F1E; border:0; border-radius:6px; text-shadow:none; box-shadow:0 2px 10px rgba(218,15,30,.35); font-family:'Barlow Semi Condensed', 'Barlow', sans-serif; font-weight:800; font-style:italic; text-transform:uppercase; letter-spacing:.04em; font-size:16px; min-height:44px; padding:0 22px; }
.wp-core-ui .button-primary:hover, .wp-core-ui .button-primary:focus { background:#c00d1a; box-shadow:0 2px 12px rgba(218,15,30,.45); }
.wp-core-ui .button-primary:focus { box-shadow:0 0 0 2px #fff, 0 0 0 4px #DA0F1E; }
.login #nav, .login #backtoblog { text-align:center; padding:0; margin:16px 0 0; }
.login #nav a, .login #backtoblog a, .privacy-policy-page-link a { color:#c9c9c0; font-size:14px; }
.login #nav a:hover, .login #backtoblog a:hover { color:#fff; }
.login .message, .login .notice, .login .success { border-radius:10px; border-left:4px solid #FDE206; box-shadow:none; }
.login #login_error, .login .notice-error { border-left-color:#DA0F1E; }
.login .privacy-policy-page-link { margin-top:20px; }
@media (max-width:420px) { #login { width:auto; padding:5vh 16px 20px; } }
CSS;
		wp_add_inline_style( 'kurs-admin-fonts', $css );
	}
}
