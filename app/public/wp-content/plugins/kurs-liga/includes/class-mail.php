<?php
/**
 * E-pošta s weba (1.35.0) — pouzdano slanje preko SMTP sandučića domene.
 *
 * WordPress po zadanom šalje PHP funkcijom mail(): pošiljatelj „wordpress@kurskc.hr”, a omotnica
 * (Return-Path) je sistemski korisnik servera. Uz strogi DMARC domene (p=quarantine, aspf=s, adkim=s)
 * takvi mailovi (novi korisnik, nova lozinka) često završe u spamu ili ih poslužitelji odbiju.
 *
 * Kad su u wp-config.php upisane konstante, svi wp_mail() idu preko SMTP-a s pravim sandučićem:
 *
 *   define( 'KURS_SMTP_HOST', 'mail.kurskc.hr' );   // ili ime servera ako certifikat ne odgovara
 *   define( 'KURS_SMTP_PORT', 587 );                // 587 = STARTTLS, 465 = SSL
 *   define( 'KURS_SMTP_SECURE', 'tls' );            // 'tls' | 'ssl' | ''
 *   define( 'KURS_SMTP_USER', 'web@kurskc.hr' );    // sandučić napravljen u Plesku
 *   define( 'KURS_SMTP_PASS', '…' );
 *   define( 'KURS_MAIL_FROM_NAME', 'KURS — kurskc.hr' ); // neobavezno
 *
 * Lozinka je samo u wp-config.php na serveru (nije u gitu ni u bazi). Pošiljatelj (From) je tada
 * KURS_SMTP_USER — isti sandučić kojim se prijavljujemo, pa SPF/DKIM/DMARC prolaze.
 *
 * KURS Liga → Postavke → „E-pošta”: stanje + probni mail s ispisom razgovora sa SMTP serverom.
 * Zadnja greška slanja: opcija `kurs_mail_last_error`.
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Mail {

	const ERR_OPT = 'kurs_mail_last_error';
	private static array $debug = [];
	private static bool $capture = false;

	public static function init(): void {
		add_action( 'phpmailer_init', [ __CLASS__, 'phpmailer' ] );
		add_filter( 'wp_mail_from', [ __CLASS__, 'from' ] );
		add_filter( 'wp_mail_from_name', [ __CLASS__, 'from_name' ] );
		add_action( 'wp_mail_failed', [ __CLASS__, 'failed' ] );
		add_action( 'admin_post_kurs_mail_test', [ __CLASS__, 'handle_test' ] );
	}

	public static function smtp_on(): bool {
		return defined( 'KURS_SMTP_HOST' ) && KURS_SMTP_HOST && defined( 'KURS_SMTP_USER' ) && KURS_SMTP_USER;
	}

	public static function phpmailer( $m ): void {
		if ( ! self::smtp_on() ) return;
		$m->isSMTP();
		$m->Host       = (string) KURS_SMTP_HOST;
		$m->Port       = defined( 'KURS_SMTP_PORT' ) ? (int) KURS_SMTP_PORT : 587;
		$secure        = defined( 'KURS_SMTP_SECURE' ) ? (string) KURS_SMTP_SECURE : 'tls';
		$m->SMTPSecure = in_array( $secure, [ 'tls', 'ssl' ], true ) ? $secure : '';
		$m->SMTPAutoTLS = $secure !== '';
		$m->SMTPAuth   = true;
		$m->Username   = (string) KURS_SMTP_USER;
		$m->Password   = defined( 'KURS_SMTP_PASS' ) ? (string) KURS_SMTP_PASS : '';
		$m->Sender     = (string) KURS_SMTP_USER; // omotnica = isti sandučić (SPF poravnanje)
		$m->Timeout    = 20;
		if ( self::$capture ) {
			$m->SMTPDebug   = 3;
			$m->Debugoutput = function ( $str ) {
				// prijava se nikad ne ispisuje: AUTH LOGIN/PLAIN šalje base64 korisnika i lozinke
				$str = preg_replace( '/(AUTH (?:PLAIN|LOGIN) )\S+/i', '$1********', (string) $str );
				$str = preg_replace( '/^(\s*CLIENT -> SERVER:\s*)[A-Za-z0-9+\/=]{6,}\s*$/m', '$1********', $str );
				self::$debug[] = rtrim( (string) $str );
			};
		}
	}

	public static function from( string $from ): string {
		if ( self::smtp_on() ) return (string) KURS_SMTP_USER;
		return $from; // bez SMTP-a ostaje WordPressov wordpress@domena
	}

	public static function from_name( string $name ): string {
		return defined( 'KURS_MAIL_FROM_NAME' ) ? (string) KURS_MAIL_FROM_NAME : 'KURS — kurskc.hr';
	}

	public static function failed( $err ): void {
		update_option( self::ERR_OPT, [ 'at' => current_time( 'mysql' ), 'msg' => is_wp_error( $err ) ? $err->get_error_message() : (string) $err ], false );
	}

	/** Probni mail: [ ok, poruka, dnevnik razgovora ]. */
	public static function send_test( string $to ): array {
		self::$debug   = [];
		self::$capture = true;
		$ok = wp_mail( $to, 'KURS — probni mail s kurskc.hr', "Ovo je probni mail s weba kurskc.hr.\n\nAko ga vidiš u primljenoj pošti (ne u spamu), slanje mailova s weba radi.\n\nKURS — Koprivnička udruga za rekreativni sport" );
		self::$capture = false;
		$err = get_option( self::ERR_OPT );
		$msg = $ok ? 'Mail je predan ' . ( self::smtp_on() ? 'SMTP serveru ' . KURS_SMTP_HOST : 'PHP funkciji mail()' ) . '. Provjeri primljenu poštu i spam.'
			: 'Slanje nije uspjelo: ' . ( is_array( $err ) ? $err['msg'] : 'nepoznata greška' );
		return [ $ok, $msg, self::$debug ];
	}

	public static function handle_test(): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemaš ovlasti.' );
		check_admin_referer( 'kurs_mail_test' );
		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		if ( ! is_email( $to ) ) $res = [ false, 'Upiši ispravnu e-mail adresu.', [] ];
		else $res = self::send_test( $to );
		set_transient( 'kurs_mail_test_' . get_current_user_id(), [ 'to' => $to, 'ok' => $res[0], 'msg' => $res[1], 'log' => array_slice( $res[2], -60 ) ], 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=kurs-settings#kurs-mail' ) );
		exit;
	}
}
