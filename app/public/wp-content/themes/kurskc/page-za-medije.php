<?php
/**
 * Template Name: Za medije
 *
 * Javna stranica s koje portali kopiraju rezultate kola s KURS brandingom:
 * HTML za WordPress (inline stilovi), obični tekst i slika (PNG).
 * URL params: ?liga=kc|pan &season_id=X &kolo=N|playoff
 */
if ( class_exists( 'Kurs_Season' ) ) {
	// PNG izvoz kartice — html-to-image (preglednik sam crta; html2canvas pomiče Barlow tekst)
	wp_enqueue_script( 'html-to-image', 'https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js', [], null, true );
}
get_header();

if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Rounds' ) ) {
	echo '<div class="container kurs-empty">KURS Liga plugin nije aktivan.</div>';
	get_footer();
	return;
}

/* Natjecanje — zadano ono koje je trenutno aktivno (najbliža utakmica) */
$req_liga = is_string( $_GET['liga'] ?? null ) ? sanitize_key( $_GET['liga'] ) : '';
if ( ! in_array( $req_liga, [ 'kc', 'pan' ], true ) ) {
	$al       = function_exists( 'kurskc_active_league' ) ? kurskc_active_league() : null;
	$req_liga = ( $al && Kurs_Season::is_pan_league( (string) $al['league'] ) ) ? 'pan' : 'kc';
}
$is_pan     = $req_liga === 'pan';
$comp_label = $is_pan ? 'Pan Ljetna liga' : 'KC Liga';

/* Sezona */
$seasons   = Kurs_Season::for_league( $is_pan );
$season_id = absint( $_GET['season_id'] ?? 0 );
if ( ! in_array( $season_id, array_map( fn( $s ) => (int) $s->id, $seasons ), true ) ) {
	$cur       = Kurs_Season::current_for_league( $is_pan );
	$season_id = (int) ( $cur->id ?? 0 );
}
$season    = $season_id ? Kurs_Season::get( $season_id ) : null;
$divisions = $season_id ? Kurs_Season::get_divisions( $season_id ) : [];

/* Kolo — zadano zadnje odigrano (ili playoff kad je počeo) */
$rounds   = $divisions ? Kurskc_Media_Kit::season_rounds( $divisions ) : [];
$po_div   = null;
foreach ( $divisions as $d ) {
	if ( class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( (int) $d->id ) ) { $po_div = $d; break; }
}
$req_kolo = is_string( $_GET['kolo'] ?? null ) ? sanitize_key( $_GET['kolo'] ) : '';
if ( $req_kolo === 'playoff' && $po_div ) {
	$kolo = 'playoff';
} elseif ( isset( $rounds[ absint( $req_kolo ) ] ) ) {
	$kolo = absint( $req_kolo );
} elseif ( $po_div && Kurskc_Media_Kit::playoff_started( (int) $po_div->id ) ) {
	$kolo = 'playoff';
} else {
	$played = array_keys( array_filter( $rounds, fn( $r ) => $r['finished'] > 0 ) );
	$kolo   = $played ? max( $played ) : ( array_key_first( $rounds ) ?? 0 );
}

/* Paket */
$datas = [];
if ( $kolo === 'playoff' ) {
	$datas[] = Kurskc_Media_Kit::division_data( $po_div, 'playoff', $is_pan );
} elseif ( $kolo ) {
	foreach ( $divisions as $d ) $datas[] = Kurskc_Media_Kit::division_data( $d, $kolo, $is_pan );
}
$season_name = $season->name ?? '';
$pkg_html    = $datas ? Kurskc_Media_Kit::html( $datas, $kolo, $comp_label, $season_name ) : '';
$pkg_text    = $datas ? Kurskc_Media_Kit::text( $datas, $kolo, $season_name ) : '';
$pkg_title   = $comp_label . ' · ' . ( $kolo === 'playoff' ? 'Playoff' : (int) $kolo . '. kolo' );
$file_base   = sanitize_title( $comp_label . '-' . ( $kolo === 'playoff' ? 'playoff' : $kolo . '-kolo' ) );
$any_played  = $kolo === 'playoff' ? ( $datas[0]['finished'] ?? 0 ) : ( $rounds[ $kolo ]['finished'] ?? 0 );

$page_url = get_permalink();
$pick_url = fn( array $a ) => add_query_arg( $a, $page_url ) . '#paket';
?>

<div class="media-kit<?php echo $is_pan ? ' league-hub--pan' : ''; ?>">

<!-- Hero -->
<section class="media-hero">
	<div class="league-banner__hatch"></div>
	<div class="container media-hero__inner">
		<div>
			<div class="media-hero__kicker"><span class="tick tick--gold"></span>Za medije</div>
			<h1 class="media-hero__title">Rezultati i tablice<br>za vaš portal</h1>
			<p class="media-hero__lede">Odaberite kolo, kopirajte paket i zalijepite ga u svoju objavu — rezultati sa strijelcima, ljestvica i lista strijelaca, uredno složeni i s oznakom izvora.</p>
		</div>
		<ol class="media-hero__steps">
			<li><b>1</b><span>Odaberite natjecanje i kolo</span></li>
			<li><b>2</b><span>Kopirajte HTML, tekst ili preuzmite sliku</span></li>
			<li><b>3</b><span>Zalijepite u objavu — izvor je već naveden</span></li>
		</ol>
	</div>
</section>

<div class="container media-body" id="paket">

	<!-- Odabir: natjecanje · sezona · kolo -->
	<form class="media-picker" method="get" action="<?php echo esc_url( $page_url ); ?>#paket">
		<div class="div-toggle media-picker__comp">
			<a href="<?php echo esc_url( $pick_url( [ 'liga' => 'kc' ] ) ); ?>" class="<?php echo $is_pan ? '' : 'is-active'; ?>"><?php echo kurs_league_icon( 'KC Liga', 18 ); ?> KC Liga</a>
			<a href="<?php echo esc_url( $pick_url( [ 'liga' => 'pan' ] ) ); ?>" class="<?php echo $is_pan ? 'is-active' : ''; ?>"><?php echo kurs_league_icon( 'Pan Ljetna', 18 ); ?> Pan Ljetna</a>
		</div>
		<input type="hidden" name="liga" value="<?php echo esc_attr( $req_liga ); ?>">
		<?php if ( $seasons ) : ?>
		<label class="media-picker__field">
			<span>Sezona</span>
			<select name="season_id" data-autosubmit>
				<?php foreach ( $seasons as $s ) : ?>
				<option value="<?php echo (int) $s->id; ?>" <?php selected( (int) $s->id, $season_id ); ?>><?php echo esc_html( $s->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php endif; ?>
		<?php if ( $rounds || $po_div ) : ?>
		<label class="media-picker__field media-picker__field--round">
			<span>Kolo</span>
			<select name="kolo" data-autosubmit>
				<?php foreach ( $rounds as $r => $st ) :
					$state = $st['finished'] >= $st['total'] ? '' : ( $st['finished'] ? ' — djelomično' : ' — najava' );
				?>
				<option value="<?php echo (int) $r; ?>" <?php selected( $kolo, $r ); ?>><?php echo esc_html( $r . '. kolo' . ( $st['date'] ? ' · ' . $st['date'] : '' ) . $state ); ?></option>
				<?php endforeach; ?>
				<?php if ( $po_div ) : ?>
				<option value="playoff" <?php selected( $kolo, 'playoff' ); ?>>Playoff</option>
				<?php endif; ?>
			</select>
		</label>
		<?php endif; ?>
		<noscript><button type="submit" class="media-btn">Prikaži</button></noscript>
	</form>

	<?php if ( ! $pkg_html ) : ?>
	<p class="kurs-empty">Za odabrano natjecanje još nema rasporeda. Paket će se pojaviti čim se objavi raspored kola.</p>
	<?php else : ?>

	<div class="media-layout">
		<!-- Pregled / tekst / kod -->
		<div class="media-main">
			<div class="media-tabs" role="tablist">
				<button type="button" class="is-active" role="tab" aria-selected="true" data-media-tab="preview">Pregled</button>
				<button type="button" role="tab" aria-selected="false" data-media-tab="text">Tekst</button>
				<button type="button" role="tab" aria-selected="false" data-media-tab="code">HTML kod</button>
			</div>
			<div class="media-panel is-active" data-media-panel="preview">
				<?php if ( ! $any_played ) : ?>
				<p class="media-flag">Ovo kolo još nije odigrano — paket je <b>najava</b> s terminima utakmica.</p>
				<?php endif; ?>
				<div class="media-paper"><?php echo $pkg_html; // generirano u Kurskc_Media_Kit, sve vrijednosti escapane ?></div>
			</div>
			<div class="media-panel" data-media-panel="text" hidden>
				<pre class="media-pre"><?php echo esc_html( $pkg_text ); ?></pre>
			</div>
			<div class="media-panel" data-media-panel="code" hidden>
				<textarea id="media-src-html" class="media-code" readonly rows="14" aria-label="HTML kod paketa"><?php echo esc_textarea( $pkg_html ); ?></textarea>
			</div>
		</div>

		<!-- Akcije -->
		<aside class="media-actions">
			<div class="media-card">
				<div class="media-card__kicker">Paket</div>
				<div class="media-card__title"><?php echo esc_html( $pkg_title ); ?></div>
				<div class="media-card__meta"><?php echo esc_html( $season_name ); ?></div>

				<button type="button" class="media-btn media-btn--primary" data-media-copy="html">
					<span class="media-btn__label">Kopiraj HTML za WordPress</span><span class="media-btn__done">Kopirano ✓</span>
				</button>
				<p class="media-hint">Blok <b>Prilagođeni HTML</b> (Gutenberg) ili kartica <b>Tekst</b> u klasičnom uređivaču.</p>

				<button type="button" class="media-btn" data-media-copy="text">
					<span class="media-btn__label">Kopiraj kao običan tekst</span><span class="media-btn__done">Kopirano ✓</span>
				</button>
				<p class="media-hint">Za portale bez HTML-a, društvene mreže i e-mail.</p>

				<button type="button" class="media-btn" data-media-png="media-png-all" data-media-name="<?php echo esc_attr( 'kurs-' . $file_base ); ?>">
					<span class="media-btn__label">Preuzmi sliku (PNG)</span><span class="media-btn__done">Preuzeto ✓</span>
				</button>
				<?php if ( count( $datas ) > 1 ) : ?>
				<div class="media-btn-row">
					<?php foreach ( $datas as $i => $d ) : ?>
					<button type="button" class="media-btn media-btn--small" data-media-png="media-png-<?php echo (int) $i; ?>" data-media-name="<?php echo esc_attr( 'kurs-' . sanitize_title( $d['name'] . '-' . $kolo . '-kolo' ) ); ?>">
						<span class="media-btn__label">PNG · <?php echo esc_html( $d['name'] ); ?></span><span class="media-btn__done">Preuzeto ✓</span>
					</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<p class="media-hint">Slika za Facebook, Instagram ili portal bez HTML-a.</p>
			</div>

			<div class="media-card">
				<h2 class="media-card__h">Kako objaviti na WordPressu</h2>
				<ol class="media-howto">
					<li>U objavi dodajte blok <b>Prilagođeni HTML</b> (tipka „+" → pretraži „HTML").</li>
					<li>Zalijepite kopirani kod (Ctrl + V) i spremite objavu.</li>
					<li>U klasičnom uređivaču prebacite se na karticu <b>Tekst</b> i zalijepite ondje.</li>
				</ol>
				<p class="media-hint">Paket nema skripti ni vanjskih stilova — samo tablice i slike s <?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>, pa se uklapa u svaku temu.</p>
			</div>

			<div class="media-card media-card--note">
				<p><b>Podaci su uvijek svježi.</b> Paket se slaže iz službenih zapisnika — kopirajte ga nakon što su odigrane sve utakmice kola.</p>
				<p>Pri preuzimanju molimo navedite izvor: <b>KURS · <?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></b>.</p>
			</div>
		</aside>
	</div>

	<!-- Izvori za kopiranje (HTML = textarea u kartici „HTML kod") i PNG (fiksne širine, izvan ekrana) -->
	<textarea id="media-src-text" hidden><?php echo esc_textarea( $pkg_text ); ?></textarea>
	<div class="media-png-src" aria-hidden="true">
		<div id="media-png-all" class="media-png-card"><?php echo $pkg_html; ?></div>
		<?php if ( count( $datas ) > 1 ) :
			foreach ( $datas as $i => $d ) : ?>
		<div id="media-png-<?php echo (int) $i; ?>" class="media-png-card"><?php echo Kurskc_Media_Kit::html( [ $d ], $kolo, $d['name'], $season_name ); ?></div>
		<?php endforeach;
		endif; ?>
	</div>
	<?php endif; ?>

</div>
</div>

<?php get_footer(); ?>
