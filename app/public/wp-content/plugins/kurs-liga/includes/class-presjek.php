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
	const SPONS  = 'kurs_presjek_sponzori'; // [ team_id => [ {name, tier, link, att, who, at, removed?} ] ] (1.29.0)
	const TIERS  = [ 'glavni' => 'Glavni', 'srednji' => 'Srednji', 'mali' => 'Mali' ];
	const SPONS_MAX = 15;  // po ekipi
	/* Sponzori lige (1.30.1): KURS šalje sponzore natjecanja — ključ 0 u opciji SPONS, uz natjecanje. */
	const LEAGUE     = 0;
	const COMPS      = [ 'kc' => 'KC liga', 'pan' => 'Pan Ljetna liga', 'obje' => 'Obje lige' ];
	const LEAGUE_MAX = 40;
	/* Sponzori koji su već na webu (CPT `sponzor`): KURS potvrđuje vrijede li i dalje — odgovor `websp-<ID>`. */
	const WEBSP_OPTS = [ 'KC liga i Pan Ljetna liga', 'Samo KC liga', 'Samo Pan Ljetna liga', 'Više nije sponzor' ];

	public static function init(): void {
		add_action( 'init',              [ __CLASS__, 'rewrite' ] );
		add_action( 'init',              [ __CLASS__, 'heal_rewrite' ], 102 );
		add_filter( 'query_vars',        fn( $v ) => array_merge( $v, [ 'kurs_presjek' ] ) );
		add_action( 'template_redirect', [ __CLASS__, 'render' ], 0 );
		add_action( 'wp_ajax_kurs_presjek_odgovor',        [ __CLASS__, 'ajax_answer' ] );
		add_action( 'wp_ajax_nopriv_kurs_presjek_odgovor', [ __CLASS__, 'ajax_answer' ] );
		add_action( 'wp_ajax_kurs_presjek_sponzor',        [ __CLASS__, 'ajax_sponsor' ] );
		add_action( 'wp_ajax_nopriv_kurs_presjek_sponzor', [ __CLASS__, 'ajax_sponsor' ] );
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
		foreach ( self::web_sponsors() as $s ) $ids[] = 'websp-' . (int) $s['id'];
		$ids[] = 'opce';
		$ids[] = 'sponzori';
		return $ids;
	}

	/** Sponzori koji su trenutno na webu (CPT `sponzor` iz teme): id, naziv, razina, link, logo. */
	public static function web_sponsors(): array {
		if ( ! post_type_exists( 'sponzor' ) ) return [];
		$out  = [];
		$tier = [ 1 => 'Glavni', 2 => 'Srednji', 3 => 'Mali' ];
		foreach ( get_posts( [ 'post_type' => 'sponzor', 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => [ 'menu_order' => 'ASC', 'title' => 'ASC' ] ] ) as $p ) {
			$t     = (int) get_post_meta( $p->ID, '_kurs_sponsor_tier', true ) ?: 2;
			$out[] = [
				'id'    => (int) $p->ID,
				'name'  => get_the_title( $p ),
				'tierL' => $tier[ $t ] ?? '',
				'link'  => (string) get_post_meta( $p->ID, '_kurs_sponsor_link', true ),
				'logo'  => (string) get_the_post_thumbnail_url( $p->ID, 'medium' ),
			];
		}
		return $out;
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
		if ( str_starts_with( $qid, 'websp-' ) && $v !== '' && ! in_array( $v, self::WEBSP_OPTS, true ) ) wp_send_json_error( [ 'msg' => 'Nepoznat odgovor.' ], 400 );

		$all           = self::answers();
		$list          = $all[ $qid ] ?? [];
		$list[]        = [ 'v' => $v, 't' => $t, 'who' => $who, 'at' => current_time( 'mysql' ) ];
		$all[ $qid ]   = array_slice( $list, -self::MAX );
		update_option( self::OPTION, $all, false );

		wp_send_json_success( [ 'entries' => self::public_entries( $all[ $qid ] ) ] );
	}

	// ── Sponzori ekipa (1.29.0): naziv, razina (glavni/srednji/mali), link, logo ─────────

	public static function sponsors_all(): array {
		$a = get_option( self::SPONS, [] );
		return is_array( $a ) ? $a : [];
	}

	/** Sponzori jedne ekipe za prikaz (bez uklonjenih), redom glavni → srednji → mali. */
	public static function sponsors( int $team_id ): array {
		$out = [];
		foreach ( (array) ( self::sponsors_all()[ $team_id ] ?? [] ) as $i => $s ) {
			if ( ! empty( $s['removed'] ) ) continue;
			$att   = (int) ( $s['att'] ?? 0 );
			$out[] = [
				'i'     => $i,
				'name'  => (string) $s['name'],
				'tier'  => (string) $s['tier'],
				'tierL' => self::TIERS[ $s['tier'] ] ?? '',
				'comp'  => (string) ( $s['comp'] ?? '' ),
				'compL' => self::COMPS[ $s['comp'] ?? '' ] ?? '',
				'link'  => (string) ( $s['link'] ?? '' ),
				'logo'  => $att ? (string) wp_get_attachment_image_url( $att, 'medium' ) : '',
				'att'   => $att,
				'who'   => (string) ( $s['who'] ?? '' ),
				'at'    => self::when( (string) ( $s['at'] ?? '' ) ),
			];
		}
		$rank = array_flip( array_keys( self::TIERS ) );
		usort( $out, fn( $a, $b ) => ( ( $rank[ $a['tier'] ] ?? 9 ) <=> ( $rank[ $b['tier'] ] ?? 9 ) ) ?: ( $a['i'] <=> $b['i'] ) );
		return $out;
	}

	public static function ajax_sponsor(): void {
		if ( ! self::has_key() ) wp_send_json_error( [ 'msg' => 'Link nije ispravan.' ], 403 );
		if ( ! empty( $_POST['web'] ) ) wp_send_json_success( [ 'list' => [] ] );

		$team = absint( $_POST['team'] ?? 0 );
		if ( $team !== self::LEAGUE && ! in_array( $team, array_map( 'intval', array_column( self::data()['teams'] ?? [], 'id' ) ), true ) ) {
			wp_send_json_error( [ 'msg' => 'Nepoznata ekipa.' ], 400 );
		}
		$all  = self::sponsors_all();
		$list = (array) ( $all[ $team ] ?? [] );

		// uklanjanje (samo oznaka — logo ostaje u Medijima dok ga admin ne obriše)
		if ( isset( $_POST['remove'] ) ) {
			$i = absint( $_POST['remove'] );
			if ( isset( $list[ $i ] ) ) {
				$list[ $i ]['removed'] = current_time( 'mysql' );
				$all[ $team ]          = $list;
				update_option( self::SPONS, $all, false );
			}
			wp_send_json_success( [ 'list' => self::sponsors( $team ) ] );
		}

		$name = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 120 );
		$tier = sanitize_key( $_POST['tier'] ?? '' );
		$link = trim( (string) wp_unslash( $_POST['link'] ?? '' ) );
		$who  = mb_substr( sanitize_text_field( wp_unslash( $_POST['who'] ?? '' ) ), 0, 80 );
		if ( $name === '' ) wp_send_json_error( [ 'msg' => 'Upišite naziv sponzora.' ], 400 );
		if ( ! isset( self::TIERS[ $tier ] ) ) wp_send_json_error( [ 'msg' => 'Odaberite razinu sponzora.' ], 400 );
		if ( $link !== '' ) {
			if ( ! preg_match( '#^https?://#i', $link ) ) $link = 'https://' . $link;
			$link = esc_url_raw( $link, [ 'http', 'https' ] );
			$host = (string) wp_parse_url( $link, PHP_URL_HOST );
			if ( ! $link || ! filter_var( $link, FILTER_VALIDATE_URL ) || ! str_contains( $host, '.' ) ) {
				wp_send_json_error( [ 'msg' => 'Link nije ispravan (npr. https://sponzor.hr).' ], 400 );
			}
		}
		$comp = sanitize_key( $_POST['comp'] ?? '' );
		if ( $team === self::LEAGUE && ! isset( self::COMPS[ $comp ] ) ) wp_send_json_error( [ 'msg' => 'Odaberite natjecanje.' ], 400 );
		$max = $team === self::LEAGUE ? self::LEAGUE_MAX : self::SPONS_MAX;
		if ( count( array_filter( $list, fn( $s ) => empty( $s['removed'] ) ) ) >= $max ) {
			wp_send_json_error( [ 'msg' => 'Najviše ' . $max . ' sponzora — za više nam se javite.' ], 400 );
		}

		$att = 0;
		if ( ! empty( $_FILES['logo']['name'] ) ) {
			$f = $_FILES['logo'];
			if ( (int) $f['size'] > 5 * MB_IN_BYTES ) wp_send_json_error( [ 'msg' => 'Slika je veća od 5 MB.' ], 400 );
			$mimes = [ 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' ];
			$chk   = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $mimes );
			if ( empty( $chk['type'] ) || ! @getimagesize( $f['tmp_name'] ) ) {
				wp_send_json_error( [ 'msg' => 'Logo mora biti slika (PNG, JPG, WEBP ili GIF).' ], 400 );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$att = media_handle_upload( 'logo', 0, [ 'post_title' => 'Sponzor: ' . $name . ' (' . ( $team === self::LEAGUE ? self::COMPS[ $comp ] : get_the_title( $team ) ) . ')' ], [ 'test_form' => false, 'mimes' => $mimes ] );
			if ( is_wp_error( $att ) ) wp_send_json_error( [ 'msg' => 'Spremanje slike nije uspjelo: ' . $att->get_error_message() ], 400 );
			update_post_meta( $att, '_kurs_presjek_team', $team );
		}

		$list[]       = [ 'name' => $name, 'tier' => $tier, 'comp' => $team === self::LEAGUE ? $comp : '', 'link' => $link, 'att' => (int) $att, 'who' => $who, 'at' => current_time( 'mysql' ) ];
		$all[ $team ] = $list;
		update_option( self::SPONS, $all, false );
		wp_send_json_success( [ 'list' => self::sponsors( $team ) ] );
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
		foreach ( self::web_sponsors() as $s ) $label[ 'websp-' . (int) $s['id'] ] = 'Sponzor na webu: ' . $s['name'] . ' — vrijedi li i dalje?';
		$label['sponzori'] = 'Sponzori lige — napomena';
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

			<h2 style="margin-top:32px">Sponzori lige (poslao KURS)</h2>
			<?php $lrows = self::sponsors( self::LEAGUE ); ?>
			<p>Logotipi su u Medijima (naslov „Sponzor: …”). Na web ih stavljaš ručno: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=sponzor' ) ); ?>">Sponzori</a> (logo = istaknuta slika, razina 1–3, link). Odgovori o sponzorima koji su već na webu su u tablici odgovora iznad.</p>
			<?php if ( $lrows ) : ?>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>Natjecanje</th><th>Logo</th><th>Sponzor</th><th>Razina</th><th>Link</th><th>Tko</th><th>Kada</th></tr></thead>
				<tbody>
				<?php foreach ( $lrows as $sp ) : ?>
					<tr>
						<td><?php echo esc_html( $sp['compL'] ); ?></td>
						<td><?php if ( $sp['logo'] ) : ?><a href="<?php echo esc_url( (string) get_edit_post_link( $sp['att'] ) ); ?>"><img src="<?php echo esc_url( $sp['logo'] ); ?>" alt="" style="max-width:90px;max-height:44px;object-fit:contain;background:#f4f4ee"></a><?php else : ?>—<?php endif; ?></td>
						<td><b><?php echo esc_html( $sp['name'] ); ?></b></td>
						<td><?php echo esc_html( $sp['tierL'] ); ?></td>
						<td><?php echo $sp['link'] ? '<a href="' . esc_url( $sp['link'] ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', $sp['link'] ) ) . '</a>' : '—'; ?></td>
						<td><?php echo esc_html( $sp['who'] ); ?></td>
						<td><?php echo esc_html( $sp['at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
				<p>KURS još nije poslao sponzore lige.</p>
			<?php endif; ?>

			<?php
			$names = array_column( $d['teams'] ?? [], 'name', 'id' );
			$rows  = [];
			foreach ( $names as $tid => $tn ) foreach ( self::sponsors( (int) $tid ) as $sp ) $rows[] = [ $tn, $sp ];
			$with  = count( array_filter( array_keys( $names ), fn( $tid ) => (bool) self::sponsors( (int) $tid ) ) );
			?>
			<?php if ( $rows ) : // stari unos po ekipama (1.29.0) — prikaz samo ako postoji ?>
			<h2 style="margin-top:32px">Sponzori ekipa</h2>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>Ekipa</th><th>Logo</th><th>Sponzor</th><th>Razina</th><th>Link</th><th>Tko</th><th>Kada</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as [ $tn, $sp ] ) : ?>
					<tr>
						<td><?php echo esc_html( $tn ); ?></td>
						<td><?php if ( $sp['logo'] ) : ?><a href="<?php echo esc_url( (string) get_edit_post_link( $sp['att'] ) ); ?>"><img src="<?php echo esc_url( $sp['logo'] ); ?>" alt="" style="max-width:90px;max-height:44px;object-fit:contain;background:#f4f4ee"></a><?php else : ?>—<?php endif; ?></td>
						<td><b><?php echo esc_html( $sp['name'] ); ?></b></td>
						<td><?php echo esc_html( $sp['tierL'] ); ?></td>
						<td><?php echo $sp['link'] ? '<a href="' . esc_url( $sp['link'] ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', $sp['link'] ) ) . '</a>' : '—'; ?></td>
						<td><?php echo esc_html( $sp['who'] ); ?></td>
						<td><?php echo esc_html( $sp['at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
