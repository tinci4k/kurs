<?php
/**
 * /presjek/?k=KLJUČ — presjek ekipa i rostera nove sezone za KURS (1.27.0).
 *
 * Samostalna stranica (bez teme) s podacima iz `front/presjek-data.json` — snimka stanja
 * (ne čita bazu, pa radi i na produkciji prije migracije baze). Otvorena pitanja imaju gumbe
 * za odgovor; odgovori i napomene po ekipi spremaju se u opciju `kurs_presjek_odgovori`
 * (AJAX `kurs_presjek_odgovor`, bez prijave — štiti ga ključ iz linka).
 *
 * Link s ključem i svi odgovori: wp-admin → KURS Liga → Presjek ekipa.
 * Ključ ovisi o solima instalacije (lokalno ≠ produkcija) — link uvijek kopiraj iz admina.
 * PAZI: migracija lokalne baze na produkciju pregazi odgovore — prije toga ih prepiši.
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Presjek {

	const SLUG   = 'presjek';
	const OPTION = 'kurs_presjek_odgovori';
	const MAX    = 20;   // zadnjih N odgovora po pitanju

	public static function init(): void {
		add_action( 'init',              [ __CLASS__, 'rewrite' ] );
		add_action( 'init',              [ __CLASS__, 'heal_rewrite' ], 102 );
		add_filter( 'query_vars',        fn( $v ) => array_merge( $v, [ 'kurs_presjek' ] ) );
		add_action( 'template_redirect', [ __CLASS__, 'render' ], 0 );
		add_action( 'wp_ajax_kurs_presjek_odgovor',        [ __CLASS__, 'ajax_answer' ] );
		add_action( 'wp_ajax_nopriv_kurs_presjek_odgovor', [ __CLASS__, 'ajax_answer' ] );
		add_action( 'admin_menu',        [ __CLASS__, 'admin_menu' ], 20 );
	}

	public static function key(): string {
		return substr( hash_hmac( 'sha256', 'kurs-presjek', wp_salt( 'auth' ) ), 0, 12 );
	}

	public static function url(): string {
		return add_query_arg( 'k', self::key(), home_url( '/' . self::SLUG . '/' ) );
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?kurs_presjek=1', 'top' );
	}

	public static function heal_rewrite(): void {
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && get_option( 'permalink_structure' ) && ! isset( $rules[ '^' . self::SLUG . '/?$' ] ) ) {
			flush_rewrite_rules( false );
		}
	}

	public static function data(): array {
		static $d = null;
		if ( $d === null ) {
			$d = json_decode( (string) file_get_contents( KURS_DIR . 'front/presjek-data.json' ), true ) ?: [];
		}
		return $d;
	}

	/** Dopušteni ključevi odgovora: id pitanja, `team-<id>` (napomena uz ekipu), `opce`. */
	public static function valid_ids(): array {
		$d   = self::data();
		$ids = array_column( $d['questions'] ?? [], 'id' );
		foreach ( $d['teams'] ?? [] as $t ) $ids[] = 'team-' . (int) $t['id'];
		$ids[] = 'opce';
		return $ids;
	}

	public static function answers(): array {
		$a = get_option( self::OPTION, [] );
		return is_array( $a ) ? $a : [];
	}

	private static function has_key(): bool {
		$k = (string) ( $_REQUEST['k'] ?? '' );
		return $k !== '' && hash_equals( self::key(), $k );
	}

	public static function render(): void {
		if ( ! get_query_var( 'kurs_presjek' ) ) return;

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( ! self::has_key() && ! current_user_can( KURS_CAP ) ) {
			status_header( 404 );
			echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Stranica ne postoji</title><p style="font-family:sans-serif;padding:40px">Stranica ne postoji ili link nije potpun.</p>';
			exit;
		}

		require_once KURS_DIR . 'admin/class-admin.php';
		wp_enqueue_style( 'kurs-admin-fonts', Kurs_Admin::FONTS_URL, [], null );
		wp_enqueue_style( 'kurs-presjek', KURS_URL . 'front/presjek.css', [ 'kurs-admin-fonts' ], KURS_VERSION );

		status_header( 200 );
		require KURS_DIR . 'front/presjek.php';
		exit;
	}

	public static function ajax_answer(): void {
		if ( ! self::has_key() ) wp_send_json_error( [ 'msg' => 'Link nije ispravan.' ], 403 );
		if ( ! empty( $_POST['web'] ) ) wp_send_json_success( [ 'entries' => [] ] ); // zamka za botove

		$qid = sanitize_key( wp_unslash( $_POST['q'] ?? '' ) );
		if ( ! in_array( $qid, self::valid_ids(), true ) ) wp_send_json_error( [ 'msg' => 'Nepoznato pitanje.' ], 400 );

		$v   = mb_substr( sanitize_text_field( wp_unslash( $_POST['v'] ?? '' ) ), 0, 120 );
		$t   = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['t'] ?? '' ) ), 0, 2000 );
		$who = mb_substr( sanitize_text_field( wp_unslash( $_POST['who'] ?? '' ) ), 0, 80 );
		if ( $v === '' && $t === '' ) wp_send_json_error( [ 'msg' => 'Odaberi odgovor ili upiši napomenu.' ], 400 );

		$q = null;
		foreach ( self::data()['questions'] ?? [] as $qq ) if ( $qq['id'] === $qid ) $q = $qq;
		if ( $q && $v !== '' && $q['opts'] && ! in_array( $v, $q['opts'], true ) ) wp_send_json_error( [ 'msg' => 'Nepoznat odgovor.' ], 400 );

		$all           = self::answers();
		$list          = $all[ $qid ] ?? [];
		$list[]        = [ 'v' => $v, 't' => $t, 'who' => $who, 'at' => current_time( 'mysql' ) ];
		$all[ $qid ]   = array_slice( $list, -self::MAX );
		update_option( self::OPTION, $all, false );

		wp_send_json_success( [ 'entries' => self::public_entries( $all[ $qid ] ) ] );
	}

	/** Za prikaz na stranici: najnoviji prvi, s ljudskim datumom. */
	public static function public_entries( array $list ): array {
		return array_map( fn( $e ) => [
			'v'   => (string) ( $e['v'] ?? '' ),
			't'   => (string) ( $e['t'] ?? '' ),
			'who' => (string) ( $e['who'] ?? '' ),
			'at'  => self::when( (string) ( $e['at'] ?? '' ) ),
		], array_reverse( $list ) );
	}

	public static function when( string $mysql ): string {
		$ts = strtotime( $mysql );
		return $ts ? date( 'j. n. Y. \u H:i', $ts ) : '';
	}

	// ── Admin: link + odgovori ───────────────────────────────────────────────

	public static function admin_menu(): void {
		add_submenu_page( 'kurs-dashboard', 'Presjek ekipa', 'Presjek ekipa', KURS_CAP, 'kurs-presjek', [ __CLASS__, 'admin_page' ] );
	}

	public static function admin_page(): void {
		if ( ! current_user_can( KURS_CAP ) ) wp_die( 'Nemaš ovlasti.' );
		$d     = self::data();
		$ans   = self::answers();
		$label = [ 'opce' => 'Općenito' ];
		foreach ( $d['questions'] ?? [] as $q ) $label[ $q['id'] ] = $q['team_name'] . ' — ' . $q['q'];
		foreach ( $d['teams'] ?? [] as $t ) $label[ 'team-' . (int) $t['id'] ] = $t['name'] . ' — napomena / popis';
		$answered = count( array_intersect( array_column( $d['questions'] ?? [], 'id' ), array_keys( array_filter( $ans ) ) ) );
		?>
		<div class="wrap kurs-wrap">
			<h1>Presjek ekipa <?php echo esc_html( $d['season'] ?? '' ); ?></h1>
			<p>Stranica za KURS s pregledom ekipa, rostera i otvorenih pitanja (stanje <?php echo esc_html( $d['as_of'] ?? '' ); ?>). Link nije javan — pošalji ga onima koji trebaju odgovoriti.</p>
			<p><input type="text" readonly value="<?php echo esc_attr( self::url() ); ?>" style="width:100%;max-width:640px;font-family:monospace" onclick="this.select()">
				<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>" target="_blank" rel="noopener">Otvori stranicu</a></p>
			<p><b><?php echo (int) $answered; ?> / <?php echo count( $d['questions'] ?? [] ); ?></b> pitanja ima odgovor.
				<em>Odgovori se spremaju u bazu ove stranice — prije prijenosa lokalne baze na produkciju prepiši ih.</em></p>
			<?php if ( ! $ans ) : ?>
				<p>Još nema odgovora.</p>
			<?php else : ?>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th style="width:38%">Pitanje</th><th>Odgovor</th><th>Napomena</th><th>Tko</th><th>Kada</th></tr></thead>
				<tbody>
				<?php foreach ( $ans as $qid => $list ) : foreach ( self::public_entries( (array) $list ) as $i => $e ) : ?>
					<tr<?php echo $i ? ' style="opacity:.6"' : ''; ?>>
						<td><?php echo $i ? '<small>(raniji odgovor)</small>' : esc_html( $label[ $qid ] ?? $qid ); ?></td>
						<td><b><?php echo esc_html( $e['v'] ); ?></b></td>
						<td><?php echo nl2br( esc_html( $e['t'] ) ); ?></td>
						<td><?php echo esc_html( $e['who'] ); ?></td>
						<td><?php echo esc_html( $e['at'] ); ?></td>
					</tr>
				<?php endforeach; endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
