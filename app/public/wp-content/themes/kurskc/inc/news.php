<?php
/**
 * Vijesti / blog — backend logika za temu kurskc.
 *
 * Sadrži: veličine slika (oštrije sličice), oznake (post_tag) na CPT vijest,
 * 24h format vremena, bojanje/labela kategorije, brojač pregleda (+ "najčitanije"
 * 30 dana s fallbackom), "aktivna liga", te meta-okvire (izdvojeno / ugradnja
 * tablice-rasporeda / galerija) i njihov ispis na frontu.
 */

defined( 'ABSPATH' ) || exit;

/* ── Veličina slike za kartice (oštro na retini) ─────────────────────────── */
add_action( 'after_setup_theme', function () {
	// 800×450 (16:9) hard-crop — koristi se na naslovnoj/arhivi umjesto 'medium'.
	add_image_size( 'kurskc_card', 800, 450, true );
	add_image_size( 'kurskc_feat', 1280, 640, true ); // izdvojeni/hero
} );

/* ── Oznake (post_tag) i na CPT vijest ───────────────────────────────────── */
add_action( 'init', function () {
	register_taxonomy_for_object_type( 'post_tag', 'vijest' );
}, 11 );

/* ── 24-satni format vremena (npr. publish box u uredniku, frontend) ──────── */
add_filter( 'option_time_format', function ( $v ) {
	// HR konvencija: 24h. Ako je spremljeni format 12-satni (sadrži a/A/g/h), forsiraj H:i.
	return ( is_string( $v ) && ! preg_match( '/[aAgh]/', $v ) ) ? $v : 'H:i';
} );

/* ── Kategorija: boja + labela (centralizirano, dostupno svim templateima) ── */
function kurskc_badge_color( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'vijest_kategorija' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) return '#111317';
	$slug = $terms[0]->slug;
	if ( stripos( $slug, 'pan' ) !== false || stripos( $slug, 'ljet' ) !== false ) return '#F47B16';
	if ( stripos( $slug, 'briga' ) !== false || stripos( $slug, 'bez-briga' ) !== false ) return '#B07A1B';
	if ( stripos( $slug, 'kc' ) !== false ) return '#DA0F1E';
	return '#111317';
}
function kurskc_badge_label( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'vijest_kategorija' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) return 'Vijest';
	return $terms[0]->name;
}

/**
 * Zamjena za istaknutu sliku (vijest bez fotografije, tema 1.0.46): tamna podloga s plamenim
 * rubom u bojama lige (kao na grbu KC lige), grb lige (kurs_league_icon) i naziv lige —
 * naziv se pokaže samo u većim okvirima (container query u main.css). Za objave izvan liga
 * KURS monogram. Ide u okvir slike (position:relative).
 */
function kurskc_thumb_placeholder( int $post_id, int $size = 72 ): string {
	$color   = kurskc_badge_color( $post_id );
	$variant = [ '#DA0F1E' => 'kc', '#B07A1B' => 'lbb', '#F47B16' => 'pan' ][ $color ] ?? 'kurs';
	$label   = $variant === 'kurs' ? 'KURS' : kurskc_badge_label( $post_id );
	$icon    = $variant !== 'kurs' && function_exists( 'kurs_league_icon' )
		? kurs_league_icon( $label, $size )
		: '<img src="' . esc_url( get_template_directory_uri() . '/assets/img/KURS-monogram.png' ) . '" alt="" style="height:' . $size . 'px;width:auto">';
	return '<span class="kc-ph kc-ph--' . $variant . '" style="--ph:' . esc_attr( $color ) . '" aria-hidden="true">'
		. '<span class="kc-ph__fire"></span>'
		. '<span class="kc-ph__in">' . $icon . '<span class="kc-ph__lbl">' . esc_html( $label ) . '</span></span></span>';
}

/* ── Brojač pregleda + "najčitanije" (30 dana, fallback na ukupno) ─────────── */
add_action( 'template_redirect', function () {
	if ( ! is_singular( [ 'vijest', 'post' ] ) ) return;
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) return; // ne broji uređivače
	$id  = get_queried_object_id();
	if ( ! $id ) return;

	// Ukupno
	update_post_meta( $id, '_kurs_views', (int) get_post_meta( $id, '_kurs_views', true ) + 1 );

	// Dnevni log (za prozor od 30 dana), orezan na ~35 dana
	$log = json_decode( (string) get_post_meta( $id, '_kurs_views_log', true ), true );
	if ( ! is_array( $log ) ) $log = [];
	$today = current_time( 'Y-m-d' );
	$log[ $today ] = ( $log[ $today ] ?? 0 ) + 1;
	$cutoff = strtotime( '-35 days', strtotime( $today ) );
	foreach ( array_keys( $log ) as $d ) {
		if ( strtotime( $d ) < $cutoff ) unset( $log[ $d ] );
	}
	update_post_meta( $id, '_kurs_views_log', wp_json_encode( $log ) );
} );

/**
 * Najčitanije objave u zadnjih $days dana; ako u tom prozoru nema pregleda
 * (off-season), fallback na ukupne preglede. Vraća niz post objekata.
 */
function kurskc_most_read( int $limit = 3, int $days = 30 ): array {
	$cache_key = "kurskc_most_read_{$limit}_{$days}";
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) return array_filter( array_map( 'get_post', $cached ) );

	$ids = get_posts( [
		'post_type'      => [ 'vijest', 'post' ],
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );

	$cutoff = strtotime( "-{$days} days", current_time( 'timestamp' ) );
	$scored = [];
	foreach ( $ids as $id ) {
		$log = json_decode( (string) get_post_meta( $id, '_kurs_views_log', true ), true );
		$sum = 0;
		if ( is_array( $log ) ) {
			foreach ( $log as $d => $n ) { if ( strtotime( $d ) >= $cutoff ) $sum += (int) $n; }
		}
		$scored[ $id ] = [ 'recent' => $sum, 'total' => (int) get_post_meta( $id, '_kurs_views', true ) ];
	}

	$recent_total = array_sum( array_column( $scored, 'recent' ) );
	$key = $recent_total > 0 ? 'recent' : 'total'; // fallback izvan sezone
	uasort( $scored, fn( $a, $b ) => $b[ $key ] <=> $a[ $key ] );

	$top = array_slice( array_keys( $scored ), 0, $limit );
	set_transient( $cache_key, $top, HOUR_IN_SECONDS );
	return array_filter( array_map( 'get_post', $top ) );
}

/**
 * "Aktivna" liga — divizija s vremenski najbližom utakmicom (nadolazeća ili
 * zadnja odigrana). Vraća [ 'label','round','url','league' ] ili null.
 */
function kurskc_active_league(): ?array {
	if ( ! class_exists( 'Kurs_Match' ) ) return null;
	$cached = get_transient( 'kurskc_active_league' );
	if ( is_array( $cached ) || $cached === 'none' ) return $cached === 'none' ? null : $cached;

	global $wpdb;
	$p   = $wpdb->prefix . 'kurs_';
	$now = current_time( 'mysql' );
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT m.round, s.name AS season_name, s.league AS league, s.id AS season_id, d.id AS division_id, d.name AS division_name,
		        ABS( TIMESTAMPDIFF( MINUTE, m.match_date, %s ) ) AS dist
		 FROM   {$p}matches m
		 JOIN   {$p}groups  g  ON g.id  = m.group_id
		 JOIN   {$p}phases  ph ON ph.id = g.phase_id
		 JOIN   {$p}divisions d ON d.id = ph.division_id
		 JOIN   {$p}seasons  s  ON s.id = d.season_id
		 WHERE  m.match_date IS NOT NULL
		 ORDER  BY dist ASC LIMIT 1",
		$now
	) );
	if ( ! $row ) { set_transient( 'kurskc_active_league', 'none', 30 * MINUTE_IN_SECONDS ); return null; }

	$is_pan = ( stripos( $row->league, 'pan' ) !== false || stripos( $row->league, 'ljet' ) !== false );
	$slug   = $is_pan ? 'pan-ljetna' : 'kc-liga';
	$data = [
		'league'      => $row->league,
		'round'       => (int) $row->round,
		'season_id'   => (int) $row->season_id,
		'division_id' => (int) $row->division_id,
		'short'       => $is_pan ? 'LJ' : 'KC',
		'label'       => $row->division_name . ( $row->round ? ' · ' . (int) $row->round . '. kolo' : '' ),
		// vodi na pregled tog kola (rezultati + ljestvica nakon kola); bez kola → tablica
		'url'         => add_query_arg(
			array_merge(
				[ 'season_id' => (int) $row->season_id, 'division_id' => (int) $row->division_id ],
				$row->round ? [ 'tab' => 'kola', 'kolo' => (int) $row->round ] : [ 'tab' => 'tablica' ]
			),
			home_url( '/' . $slug . '/' )
		) . ( $row->round ? '#kola' : '' ),
	];
	set_transient( 'kurskc_active_league', $data, 30 * MINUTE_IN_SECONDS );
	return $data;
}
// Osvježi keš aktivne lige kad se promijene podaci lige.
add_action( 'kurs_data_changed', function () { delete_transient( 'kurskc_active_league' ); } );

/* ── Meta-okviri na uredniku vijesti ─────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'kurskc_news_opts', 'KURS — opcije objave', 'kurskc_news_opts_box', 'vijest', 'side', 'high' );
	add_meta_box( 'kurskc_gallery', 'Galerija slika', 'kurskc_gallery_box', 'vijest', 'normal', 'low' );
} );

function kurskc_news_opts_box( $post ): void {
	wp_nonce_field( 'kurskc_news_meta', 'kurskc_news_nonce' );
	$featured = (int) get_post_meta( $post->ID, '_kurs_featured', true );
	$embed    = kurskc_embed_parse( (string) get_post_meta( $post->ID, '_kurs_embed', true ) );

	echo '<p><label><input type="checkbox" name="kurs_featured" value="1" ' . checked( $featured, 1, false ) . '> ';
	echo '<strong>Izdvoji na naslovnici</strong> (hero)</label></p>';
	echo '<p style="color:#666;font-size:12px;margin:0 0 12px;">Ako je više objava izdvojeno, prikazuje se najnovija.</p>';

	echo '<hr style="margin:12px 0"><p style="margin:0 0 6px;"><strong>Kolo, rezultati ili ljestvica u tekstu</strong></p>';
	echo '<p style="color:#555;font-size:12px;margin:0 0 8px;">Dodaj blok <b>KURS — iz lige</b> (gumb ispod ili <b>+</b> u tekstu). '
		. 'U bloku odabereš ligu i što prikazati, a izgled se odmah vidi — u uređivaču i u Pretpregledu. Blok može stajati bilo gdje u tekstu, i više njih.</p>';
	echo '<p style="margin:0 0 4px;"><button type="button" class="button button-primary" id="kurskc-add-embed" style="width:100%;justify-content:center">+ Dodaj blok „Iz lige”</button></p>';

	// Stara ugradnja „ispod teksta" (_kurs_embed) — i dalje se prikazuje; ponudi pretvaranje u blok.
	echo '<input type="hidden" name="kurs_embed" id="kurs_embed" value="' . esc_attr( $embed['value'] ?? '' ) . '">';
	if ( ! empty( $embed['value'] ) ) {
		$label = $embed['value'];
		if ( class_exists( 'Kurs_Season' ) && class_exists( 'Kurs_Rounds' ) ) {
			$o = kurskc_embed_options()[ (int) ( $embed['division_id'] ?? 0 ) ] ?? null;
			if ( $o ) {
				$label = $o['season'] . ' · ' . $o['division'];
				foreach ( $o['items'] as $it ) if ( $it[0] === $embed['value'] ) $label .= ' — ' . $it[1];
			}
		}
		echo '<div id="kurskc-legacy-embed" style="margin-top:10px;padding:8px 10px;background:#fff8e5;border:1px solid #f0d58c;border-radius:4px;font-size:12px">';
		echo '<b>Ispod teksta je ugrađeno:</b><br>' . esc_html( $label );
		echo '<p style="margin:6px 0 0;display:flex;gap:6px;flex-wrap:wrap"><button type="button" class="button button-small" id="kurskc-legacy-convert" data-value="' . esc_attr( $embed['value'] ) . '">Pretvori u blok</button>';
		echo '<button type="button" class="button button-small button-link-delete" id="kurskc-legacy-remove">Ukloni</button></p>';
		echo '<p style="margin:6px 0 0;color:#7a5b00">Pretvori u blok da se vidi u uređivaču i Pretpregledu; promjena vrijedi nakon spremanja.</p></div>';
	}
}

/** JS metaboxa: ubaci blok kurs/liga (prazan ili iz stare ugradnje) i makni stari zapis. */
function kurskc_embed_metabox_js(): string {
	return <<<'JS'
(function () {
	function insert(value) {
		if (!window.wp || !wp.blocks || !wp.data || !wp.data.dispatch('core/block-editor')) { alert('Blok se dodaje u uređivaču blokova: + → KURS — iz lige.'); return false; }
		var block = wp.blocks.createBlock('kurs/liga', value ? { value: value } : {});
		wp.data.dispatch('core/block-editor').insertBlocks(block);
		wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
		return true;
	}
	document.addEventListener('click', function (e) {
		var t = e.target;
		if (t.id === 'kurskc-add-embed') { insert(''); }
		if (t.id === 'kurskc-legacy-convert') {
			if (insert(t.getAttribute('data-value'))) { document.getElementById('kurs_embed').value = ''; document.getElementById('kurskc-legacy-embed').innerHTML = '<b>Pretvoreno u blok</b> — spremi vijest (Ažuriraj).'; }
		}
		if (t.id === 'kurskc-legacy-remove') {
			document.getElementById('kurs_embed').value = '';
			document.getElementById('kurskc-legacy-embed').innerHTML = '<b>Uklonjeno</b> — spremi vijest (Ažuriraj).';
		}
	});
})();
JS;
}

/**
 * Mogućnosti ugradnje po ligi, najnovije sezone prve:
 * [ division_id => [ 'season' => naziv, 'division' => naziv, 'items' => [ [ vrijednost, oznaka ], … ] ] ].
 * Vrijednosti: round|table|results:DIV:KOLO, live|schedule|bracket:DIV.
 */
function kurskc_embed_options(): array {
	$out = [];
	foreach ( Kurs_Season::all() as $s ) {
		$sdivs = Kurs_Season::get_divisions( (int) $s->id );
		if ( count( $sdivs ) > 1 ) { // „Obje lige" (1.0.55): isti zapis s ID-evima spojenim „+" — kartica s obje lige, kao paket Za medije
			$key   = implode( '+', array_map( fn( $d ) => (int) $d->id, $sdivs ) );
			$items = $upcoming = [];
			foreach ( array_reverse( Kurs_Rounds::round_status( (int) $sdivs[0]->id ), true ) as $r => $st ) {
				if ( $st['finished'] ) {
					$items[] = [ "round:$key:$r",   "$r. kolo — rezultati, ljestvice i strijelci" ];
					$items[] = [ "results:$key:$r", "$r. kolo — samo rezultati" ];
					$items[] = [ "table:$key:$r",   "Ljestvice nakon $r. kola" ];
				} else {
					array_unshift( $upcoming, [ "results:$key:$r", "$r. kolo — najava utakmica" ] );
				}
			}
			$items   = array_merge( $items, $upcoming );
			$items[] = [ "live:$key", 'Ljestvice — uvijek aktualne' ];
			$items[] = [ "schedule:$key", 'Cijeli raspored (obje lige)' ];
			$out[ $key ] = [ 'season' => $s->name, 'division' => 'Obje lige', 'items' => $items ];
		}
		foreach ( $sdivs as $d ) {
			$did   = (int) $d->id;
			$items = $upcoming = [];
			foreach ( array_reverse( Kurs_Rounds::round_status( $did ), true ) as $r => $st ) {
				if ( $st['finished'] ) { // odigrana kola: zadnje prvo (zadani odabir)
					$items[] = [ "round:$did:$r",   "$r. kolo — rezultati, ljestvica i strijelci" ];
					$items[] = [ "table:$did:$r",   "Ljestvica nakon $r. kola" ];
					$items[] = [ "results:$did:$r", "$r. kolo — samo rezultati" ];
				} else {                 // nadolazeća: od sljedećeg kola nadalje
					array_unshift( $upcoming, [ "results:$did:$r", "$r. kolo — najava utakmica" ] );
				}
			}
			$items = array_merge( $items, $upcoming );
			$items[] = [ "live:$did", 'Ljestvica — uvijek aktualna' ];
			$items[] = [ "schedule:$did", 'Cijeli raspored' ];
			if ( ! empty( $d->has_playoff ) ) $items[] = [ "bracket:$did", 'Playoff (bracket)' ];
			$out[ $did ] = [ 'season' => $s->name, 'division' => $d->name, 'items' => $items ];
		}
	}
	return $out;
}

/** _kurs_embed → [ type, division_id, round, value(, group_id) ] ili []. Stari zapis „standings:SKUPINA" i dalje vrijedi. */
function kurskc_embed_parse( string $v ): array {
	if ( preg_match( '/^(round|table|results):(\d+(?:\+\d+)*):(\d+)$/', $v, $m ) ) {
		$ids = array_map( 'intval', explode( '+', $m[2] ) );
		return [ 'type' => $m[1], 'division_id' => $ids[0], 'division_ids' => $ids, 'round' => (int) $m[3], 'value' => $v ];
	}
	if ( preg_match( '/^(live|schedule|bracket):(\d+(?:\+\d+)*)$/', $v, $m ) ) {
		$ids = array_map( 'intval', explode( '+', $m[2] ) );
		return [ 'type' => $m[1], 'division_id' => $ids[0], 'division_ids' => $ids, 'round' => 0, 'value' => $v ];
	}
	if ( preg_match( '/^standings:(\d+)$/', $v, $m ) ) {
		global $wpdb;
		$p   = $wpdb->prefix . 'kurs_';
		$div = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ph.division_id FROM {$p}groups g JOIN {$p}phases ph ON ph.id = g.phase_id WHERE g.id = %d", (int) $m[1] ) );
		return [ 'type' => 'standings', 'group_id' => (int) $m[1], 'division_id' => $div, 'round' => 0, 'value' => $v ];
	}
	return [];
}

function kurskc_gallery_box( $post ): void {
	$ids = (string) get_post_meta( $post->ID, '_kurs_gallery', true );
	echo '<div class="kurs-gal-field" data-target="kurs_gallery_ids">';
	echo '<input type="hidden" id="kurs_gallery_ids" name="kurs_gallery" value="' . esc_attr( $ids ) . '">';
	echo '<div class="kurs-gal-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">';
	if ( $ids ) {
		foreach ( array_filter( array_map( 'intval', explode( ',', $ids ) ) ) as $aid ) {
			$thumb = wp_get_attachment_image_url( $aid, 'thumbnail' );
			if ( $thumb ) echo '<img src="' . esc_url( $thumb ) . '" style="width:72px;height:72px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">';
		}
	}
	echo '</div>';
	echo '<button type="button" class="button kurs-gal-add">Odaberi slike</button> ';
	echo '<button type="button" class="button kurs-gal-clear">Očisti</button>';
	echo '<p style="color:#666;font-size:12px;margin:8px 0 0;">Prikazuje se na dnu objave u rešetki 2 stupca s povećavanjem (lightbox).</p>';
	echo '</div>';
}

add_action( 'save_post_vijest', function ( $post_id ) {
	if ( ! isset( $_POST['kurskc_news_nonce'] ) || ! wp_verify_nonce( $_POST['kurskc_news_nonce'], 'kurskc_news_meta' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	update_post_meta( $post_id, '_kurs_featured', empty( $_POST['kurs_featured'] ) ? 0 : 1 );

	$embed = isset( $_POST['kurs_embed'] ) ? sanitize_text_field( wp_unslash( $_POST['kurs_embed'] ) ) : '';
	update_post_meta( $post_id, '_kurs_embed', kurskc_embed_parse( $embed ) ? $embed : '' );

	$gallery = isset( $_POST['kurs_gallery'] ) ? (string) wp_unslash( $_POST['kurs_gallery'] ) : '';
	$gallery = implode( ',', array_filter( array_map( 'intval', explode( ',', $gallery ) ) ) );
	update_post_meta( $post_id, '_kurs_gallery', $gallery );
} );

/* ── Asseti uređivača (wp.media za galeriju) ─────────────────────────────── */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
	if ( get_post_type() !== 'vijest' ) return;
	wp_enqueue_media();
	wp_add_inline_script( 'jquery-core', kurskc_gallery_admin_js() );
	wp_add_inline_script( 'jquery-core', kurskc_embed_metabox_js() );
} );

function kurskc_gallery_admin_js(): string {
	return <<<'JS'
jQuery(function($){
  var frame;
  $(document).on('click','.kurs-gal-add',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.kurs-gal-field'), $input=$('#'+$wrap.data('target'));
    if(frame){frame.open();return;}
    frame=wp.media({title:'Galerija objave',multiple:true,library:{type:'image'},button:{text:'Dodaj u galeriju'}});
    frame.on('select',function(){
      var ids=$input.val()?$input.val().split(',').filter(Boolean):[];
      var $prev=$wrap.find('.kurs-gal-preview');
      frame.state().get('selection').toJSON().forEach(function(a){
        if(ids.indexOf(String(a.id))===-1){
          ids.push(String(a.id));
          var u=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url;
          $prev.append('<img src="'+u+'" style="width:72px;height:72px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">');
        }
      });
      $input.val(ids.join(','));
    });
    frame.open();
  });
  $(document).on('click','.kurs-gal-clear',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.kurs-gal-field');
    $('#'+$wrap.data('target')).val('');
    $wrap.find('.kurs-gal-preview').empty();
  });
  // Ugradnja iz lige: liga → prikaz (kolo, ljestvica nakon kola…) → skriveno polje kurs_embed
  var $div=$('#kurs_embed_div'), $what=$('#kurs_embed_what'), $val=$('#kurs_embed');
  if($div.length){
    var opts=JSON.parse($('#kurs_embed_opts').text()||'{}');
    var fill=function(keep){
      var d=$div.val(); $what.empty();
      if(!d||!opts[d]){ $what.prop('hidden',true); $val.val(''); return; }
      opts[d].items.forEach(function(it){ $('<option>').val(it[0]).text(it[1]).appendTo($what); });
      if(keep&&$what.find('option').filter(function(){return this.value===keep;}).length) $what.val(keep);
      $what.prop('hidden',false); $val.val($what.val());
    };
    $div.on('change',function(){ fill(null); });
    $what.on('change',function(){ $val.val($what.val()); });
    fill($val.val());
  }
});
JS;
}

/* ── Ispis ugrađenog sadržaja lige (ispod teksta objave) ──────────────────── */
function kurskc_post_embed_html( int $post_id ): string {
	$html = kurskc_embed_render( kurskc_embed_parse( (string) get_post_meta( $post_id, '_kurs_embed', true ) ) );
	return $html ? '<div class="post-embed">' . $html . '</div>' : '';
}

/**
 * HTML ugradnje (meta okvir vijesti i [kurs_kolo]). Kolo / ljestvica nakon kola računaju se do tog
 * kola (Kurs_Rounds), kao na tabu „Po kolima" i u „Za medije" — ne mijenjaju se s kasnijim kolima.
 */
/**
 * Ugradnja kola / ljestvice / playoffa kao kartica paketa „Za medije" (tema 1.0.51) — izgled u vijesti
 * identičan onome što portali dijele (Kurskc_Media_Kit::html, inline stilovi). '' ako nema podataka.
 * Raspored (cijela sezona) i stari zapis „standings:SKUPINA" i dalje idu klasičnim prikazom.
 */
function kurskc_embed_media_card( array $e ): string {
	if ( ! class_exists( 'Kurskc_Media_Kit' ) ) return '';
	$did    = (int) $e['division_id'];
	$div    = $did ? Kurs_Season::get_division( $did ) : null;
	$season = $div ? Kurs_Season::get( (int) $div->season_id ) : null;
	if ( ! $div || ! $season ) return '';
	$is_pan = Kurs_Season::is_pan_league( (string) $season->league );
	$r      = (int) $e['round'];
	$ids    = $e['division_ids'] ?? [ $did ];
	if ( count( $ids ) > 1 ) { // obje lige u jednoj kartici (kao paket Za medije)
		$divs  = array_values( array_filter( array_map( fn( $i ) => Kurs_Season::get_division( (int) $i ), $ids ) ) );
		$comp  = $is_pan ? 'Pan Ljetna liga' : 'KC liga';
		if ( $e['type'] === 'live' ) {
			return Kurskc_Media_Kit::html( array_map( fn( $d ) => Kurskc_Media_Kit::live_data( $d, $is_pan ), $divs ), 'live', $comp, $season->name );
		}
		if ( in_array( $e['type'], [ 'round', 'results', 'table' ], true ) ) {
			$datas = array_values( array_filter( array_map( fn( $d ) => Kurskc_Media_Kit::division_data( $d, $r, $is_pan ), $divs ), fn( $d ) => $d['matches'] ) );
			if ( ! $datas ) return '';
			$parts = [ 'round' => [ 'results', 'table', 'scorers' ], 'results' => [ 'results' ], 'table' => [ 'table' ] ][ $e['type'] ];
			return Kurskc_Media_Kit::html( $datas, $r, $comp, $season->name, [ 'parts' => $parts ] );
		}
		return '';
	}
	switch ( $e['type'] ) {
		case 'bracket':
			if ( ! class_exists( 'Kurs_Playoff' ) || ! Kurs_Playoff::exists( $did ) ) return '';
			return Kurskc_Media_Kit::html( [ Kurskc_Media_Kit::division_data( $div, 'playoff', $is_pan ) ], 'playoff', $div->name, $season->name );
		case 'live':
			return Kurskc_Media_Kit::html( [ Kurskc_Media_Kit::live_data( $div, $is_pan ) ], 'live', $div->name, $season->name );
		case 'round': case 'results': case 'table':
			$d = Kurskc_Media_Kit::division_data( $div, $r, $is_pan );
			if ( ! $d['matches'] ) return '';
			$parts = [ 'round' => [ 'results', 'table', 'scorers' ], 'results' => [ 'results' ], 'table' => [ 'table' ] ][ $e['type'] ];
			return Kurskc_Media_Kit::html( [ $d ], $r, $div->name, $season->name, [ 'parts' => $parts ] );
	}
	return '';
}

function kurskc_embed_render( array $e ): string {
	if ( ! $e || ! class_exists( 'Kurs_Rounds' ) || ! function_exists( 'kurs_render_standings' ) ) return '';
	if ( in_array( $e['type'], [ 'round', 'results', 'table', 'live', 'bracket' ], true ) ) {
		$card = kurskc_embed_media_card( $e );
		if ( $card !== '' ) return '<div class="post-embed__card">' . $card . '</div>';
	}
	$did = (int) $e['division_id'];
	switch ( $e['type'] ) {
		case 'standings': return kurs_render_standings( (int) $e['group_id'], [ 'show_title' => true ] );
		case 'bracket':   return kurs_render_playoff_bracket( $did );
		case 'schedule':  return kurs_render_schedule( $e['division_ids'] ?? $did );
	}
	$div    = $did ? Kurs_Season::get_division( $did ) : null;
	$season = $div ? Kurs_Season::get( (int) $div->season_id ) : null;
	if ( ! $div || ! $season ) return '';
	$spots  = empty( $div->has_playoff ) ? 0 : null; // lige bez playoffa — bez oznake prolaza
	$kicker = stripos( $season->name, $div->name ) !== false ? $season->name : $season->name . ' · ' . $div->name;
	$r      = (int) $e['round'];
	$type   = $e['type'];
	$st     = Kurs_Rounds::round_status( $did )[ $r ] ?? [ 'total' => 0, 'finished' => 0 ];
	$title  = $type === 'live' ? 'Ljestvica' : ( $type === 'table' ? "Ljestvica nakon $r. kola" : "$r. kolo" );
	$date   = $type === 'live' ? '' : Kurs_Rounds::date_label( Kurs_Rounds::matches( $did, $r ) );
	$more   = $type === 'live'
		? kurs_league_url( $did, [ 'tab' => 'tablica' ] )
		: kurs_league_url( $did, [ 'tab' => 'kola', 'kolo' => $r ] ) . '#kola';
	if ( $type !== 'live' && ! $st['total'] ) return '';
	ob_start(); ?>
	<div class="round-head">
		<div>
			<div class="round-head__kicker"><?php echo esc_html( $kicker ); ?></div>
			<h2 class="round-head__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $date ) : ?><div class="round-head__date"><?php echo esc_html( $date ); ?></div><?php endif; ?>
		</div>
	</div>
	<?php if ( $type === 'live' ) :
		foreach ( Kurs_Season::get_phases( $did ) as $ph ) {
			$groups = Kurs_Season::get_groups( (int) $ph->id );
			foreach ( $groups as $g ) {
				echo kurs_render_standings( (int) $g->id, [ 'title' => count( $groups ) > 1 ? $g->name : '', 'playoff_spots' => $spots ] );
			}
		}
	else :
		if ( $type !== 'table' ) : ?>
	<section class="round-sec">
		<h3 class="round-sec__title"><?php echo $st['finished'] ? 'Rezultati' : 'Utakmice'; ?></h3>
		<?php echo kurs_render_round_results( $did, $r ); ?>
	</section>
		<?php endif;
		if ( $type !== 'results' && $st['finished'] ) :
			$tables = Kurs_Rounds::standings( $did, $r ); ?>
	<section class="round-sec">
		<?php if ( $type === 'round' ) : ?><h3 class="round-sec__title">Ljestvica nakon <?php echo $r; ?>. kola</h3><?php endif; ?>
		<?php foreach ( $tables as $t ) :
			echo kurs_render_standings( (int) $t['group']->id, [
				'rows' => $t['rows'], 'show_move' => true, 'playoff_spots' => $spots,
				'title' => count( $tables ) > 1 ? $t['group']->name : '',
			] );
			if ( $t['unplayed'] ) : ?>
		<p class="round-note">Neodigranih utakmica zaključno s ovim kolom: <strong><?php echo (int) $t['unplayed']; ?></strong> — nisu uračunate u ljestvicu.</p>
			<?php endif;
		endforeach; ?>
	</section>
		<?php endif;
		if ( $type === 'round' && $st['finished'] ) : ?>
	<section class="round-sec">
		<h3 class="round-sec__title">Strijelci nakon <?php echo $r; ?>. kola</h3>
		<?php echo kurs_render_round_scorers( $did, $r ) ?: '<p class="kurs-empty">Nema evidentiranih golova.</p>'; ?>
	</section>
		<?php endif;
	endif; ?>
	<p class="post-embed__more"><a href="<?php echo esc_url( $more ); ?>">Sve na stranici lige &rarr;</a></p>
	<?php return ob_get_clean();
}

/* ── Galerija (2 stupca + lightbox) ──────────────────────────────────────── */
function kurskc_gallery_html( int $post_id ): string {
	$ids = (string) get_post_meta( $post_id, '_kurs_gallery', true );
	$ids = array_filter( array_map( 'intval', explode( ',', $ids ) ) );
	if ( ! $ids ) return '';

	$out  = '<section class="post-gallery"><h2 class="article-h2"><span class="h2-tick"></span>Galerija</h2>';
	$out .= '<div class="post-gallery__grid">';
	foreach ( $ids as $aid ) {
		$full  = wp_get_attachment_image_url( $aid, 'large' );
		$thumb = wp_get_attachment_image_url( $aid, 'kurskc_card' ) ?: wp_get_attachment_image_url( $aid, 'medium_large' ) ?: $full;
		$cap   = wp_get_attachment_caption( $aid );
		if ( ! $full ) continue;
		$out .= '<a class="post-gallery__item" href="' . esc_url( $full ) . '" data-lightbox'
			. ( $cap ? ' data-caption="' . esc_attr( $cap ) . '"' : '' ) . '>';
		$out .= '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $cap ) . '" loading="lazy">';
		$out .= '</a>';
	}
	$out .= '</div></section>';
	return $out;
}

/* ── Sidebar widgeti (dijeljeni: single + feed) ──────────────────────────── */

/** Najčitanije (3) — $dark = tamna kartica (feed rail) ili svijetla (single). */
function kurskc_widget_most_read( bool $dark = false ): string {
	$posts = kurskc_most_read( 3, 30 );
	if ( ! $posts ) return '';
	ob_start(); ?>
	<div class="news-widget news-widget--<?php echo $dark ? 'dark' : 'card'; ?> news-widget--mostread">
		<div class="news-widget__hd"><span class="tick tick--gold"></span>Najčitanije</div>
		<div class="news-widget__rows">
			<?php foreach ( $posts as $i => $pp ) : ?>
			<a class="nw-mr" href="<?php echo esc_url( get_permalink( $pp ) ); ?>">
				<span class="nw-mr__rank<?php echo $i === 0 ? ' is-first' : ''; ?>"><?php echo $i + 1; ?></span>
				<span class="nw-mr__title"><?php echo esc_html( get_the_title( $pp ) ); ?></span>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php return ob_get_clean();
}

/** "Iz lige · uživo" — promo kartica aktivne lige. */
function kurskc_widget_league_live(): string {
	$al = kurskc_active_league();
	if ( ! $al ) return '';
	ob_start(); ?>
	<a class="news-widget news-widget--card news-widget--league" href="<?php echo esc_url( $al['url'] ); ?>">
		<span class="nw-league__kicker">Iz lige · uživo</span>
		<span class="nw-league__title"><?php echo esc_html( $al['label'] ); ?></span>
		<span class="nw-league__cta"><?php echo $al['round'] ? 'Rezultati kola · tablica →' : 'Tablica · raspored →'; ?></span>
	</a>
	<?php return ob_get_clean();
}

/** Vrh tablice (top 3) aktivne lige — kompaktni redovi. */
function kurskc_widget_standings(): string {
	$al = kurskc_active_league();
	if ( ! $al || empty( $al['division_id'] ) || ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Match' ) ) return '';
	$gid = 0;
	foreach ( Kurs_Season::get_phases( (int) $al['division_id'] ) as $ph ) {
		$gs = Kurs_Season::get_groups( (int) $ph->id );
		if ( $gs ) { $gid = (int) $gs[0]->id; break; }
	}
	if ( ! $gid ) return '';
	$rows = array_slice( Kurs_Match::get_standings( $gid ), 0, 3 );
	if ( ! $rows ) return '';
	ob_start(); ?>
	<div class="news-widget news-widget--card news-widget--topstand">
		<div class="news-widget__hd news-widget__hd--bar"><span class="tick tick--red"></span>Vrh tablice · <?php echo esc_html( $al['short'] ); ?></div>
		<div class="news-widget__rows">
			<?php foreach ( $rows as $i => $r ) : ?>
			<a class="nw-st" href="<?php echo esc_url( kurs_team_url( (int) $r['team_id'], (int) $al['division_id'] ) ); ?>">
				<span class="nw-st__rank"><?php echo $i + 1; ?></span>
				<span class="nw-st__crest"><?php echo kurs_team_badge_html( (int) $r['team_id'], 20 ); ?></span>
				<span class="nw-st__name"><?php echo esc_html( $r['team_name'] ); ?></span>
				<span class="nw-st__pts"><?php echo (int) $r['Pts']; ?></span>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php return ob_get_clean();
}

/* ── Shortcodes za ručnu ugradnju ─────────────────────────────────────────── */
/* [kurs_kolo division_id="X" kolo="N" prikaz="kolo|tablica|rezultati"] — isto što i „Ugradi iz lige" */
add_shortcode( 'kurs_kolo', function ( $atts ) {
	$a    = shortcode_atts( [ 'division_id' => 0, 'kolo' => 0, 'prikaz' => 'kolo' ], $atts );
	$type = [ 'kolo' => 'round', 'tablica' => 'table', 'rezultati' => 'results' ][ $a['prikaz'] ] ?? 'round';
	if ( ! (int) $a['division_id'] || ! (int) $a['kolo'] ) return '';
	return kurskc_embed_render( [ 'type' => $type, 'division_id' => (int) $a['division_id'], 'round' => (int) $a['kolo'], 'value' => '' ] );
} );
add_shortcode( 'kurs_standings', function ( $atts ) {
	$a = shortcode_atts( [ 'group_id' => 0, 'limit' => 0, 'title' => '1' ], $atts );
	if ( ! $a['group_id'] || ! function_exists( 'kurs_render_standings' ) ) return '';
	return kurs_render_standings( (int) $a['group_id'], [
		'limit'      => (int) $a['limit'] ?: null,
		'show_title' => $a['title'] !== '0',
	] );
} );
add_shortcode( 'kurs_bracket', function ( $atts ) {
	$a = shortcode_atts( [ 'division_id' => 0 ], $atts );
	if ( ! $a['division_id'] || ! function_exists( 'kurs_render_playoff_bracket' ) ) return '';
	return kurs_render_playoff_bracket( (int) $a['division_id'] );
} );
add_shortcode( 'kurs_schedule', function ( $atts ) {
	$a = shortcode_atts( [ 'division_id' => 0, 'limit' => 0, 'status' => '' ], $atts );
	if ( ! $a['division_id'] || ! function_exists( 'kurs_render_schedule' ) ) return '';
	return kurs_render_schedule( (int) $a['division_id'], [
		'limit'  => (int) $a['limit'] ?: null,
		'status' => in_array( $a['status'], [ 'finished', 'scheduled' ], true ) ? $a['status'] : null,
	] );
} );

/* ── Bočni stupac vijesti (widget area) + „Vrh tablice" widget ───────────── */
add_action( 'widgets_init', function () {
	register_sidebar( [
		'name'          => 'Vijesti — bočni stupac',
		'id'            => 'kurskc-news-sidebar',
		'description'   => 'Widgeti uz pojedinačnu objavu (npr. „Vrh tablice"). Zamjenjuje automatsku ljestvicu ako je popunjen.',
		'before_widget' => '<div class="news-widget news-widget--card news-widget--topstand %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<div class="news-widget__hd news-widget__hd--bar"><span class="tick tick--red"></span>',
		'after_title'   => '</div>',
	] );
	register_widget( 'KURSKC_Top_Standings_Widget' );
} );

class KURSKC_Top_Standings_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct( 'kurskc_top_standings', 'KURS — Vrh tablice', [
			'description' => 'Ljestvica(e) odabranih grupa. Podržava više grupa istovremeno.',
		] );
	}

	private function all_groups(): array {
		$out = [];
		if ( ! class_exists( 'Kurs_Season' ) ) return $out;
		foreach ( Kurs_Season::all() as $s ) {
			foreach ( Kurs_Season::get_divisions( (int) $s->id ) as $d ) {
				foreach ( Kurs_Season::get_phases( (int) $d->id ) as $ph ) {
					foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
						$out[ (int) $g->id ] = "{$s->name} · {$d->name} · {$g->name}";
					}
				}
			}
		}
		return $out;
	}

	public function widget( $args, $instance ): void {
		if ( ! function_exists( 'kurs_render_standings' ) ) return;
		$gids  = array_filter( array_map( 'intval', (array) ( $instance['groups'] ?? [] ) ) );
		$limit = (int) ( $instance['limit'] ?? 5 );
		if ( ! $gids ) return;

		$tables = '';
		foreach ( $gids as $gid ) {
			$tables .= kurs_render_standings( $gid, [
				'limit'       => $limit ?: null,
				'show_title'  => count( $gids ) > 1,
				'show_legend' => false,
				'link_teams'  => true,
			] );
		}
		if ( $tables === '' ) return;

		$title = $instance['title'] ?? 'Vrh tablice';
		echo $args['before_widget'];
		if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		echo '<div class="news-widget__embed">' . $tables . '</div>';
		echo $args['after_widget'];
	}

	public function form( $instance ): void {
		$title    = $instance['title'] ?? 'Vrh tablice';
		$limit    = (int) ( $instance['limit'] ?? 5 );
		$selected = array_map( 'intval', (array) ( $instance['groups'] ?? [] ) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Naslov:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'groups' ) ); ?>">Grupe (Ctrl/Cmd za više):</label>
			<select multiple size="8" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'groups' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'groups' ) ); ?>[]">
				<?php foreach ( $this->all_groups() as $gid => $label ) : ?>
				<option value="<?php echo (int) $gid; ?>" <?php selected( in_array( $gid, $selected, true ) ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>">Broj redova (0 = cijela tablica):</label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="0" max="50" value="<?php echo (int) $limit; ?>">
		</p>
		<?php
	}

	public function update( $new, $old ): array {
		return [
			'title'  => sanitize_text_field( $new['title'] ?? '' ),
			'groups' => array_values( array_filter( array_map( 'intval', (array) ( $new['groups'] ?? [] ) ) ) ),
			'limit'  => max( 0, (int) ( $new['limit'] ?? 5 ) ),
		];
	}
}
