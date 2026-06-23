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
		"SELECT m.round, s.name AS season_name, s.league AS league, s.id AS season_id, d.id AS division_id,
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
		'label'       => $row->league . ( $row->round ? ' · ' . (int) $row->round . '. kolo' : '' ),
		'url'         => add_query_arg(
			[ 'season_id' => (int) $row->season_id, 'division_id' => (int) $row->division_id, 'tab' => 'tablica' ],
			home_url( '/' . $slug . '/' )
		),
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
	$embed    = (string) get_post_meta( $post->ID, '_kurs_embed', true ); // "", "standings:ID", "bracket:ID"

	echo '<p><label><input type="checkbox" name="kurs_featured" value="1" ' . checked( $featured, 1, false ) . '> ';
	echo '<strong>Izdvoji na naslovnici</strong> (hero)</label></p>';
	echo '<p style="color:#666;font-size:12px;margin:0 0 12px;">Ako je više objava izdvojeno, prikazuje se najnovija.</p>';

	echo '<p><label for="kurs_embed"><strong>Ugradi tablicu / ljestvicu:</strong></label><br>';
	echo '<select name="kurs_embed" id="kurs_embed" style="width:100%;max-width:100%;">';
	echo '<option value="">— ništa —</option>';

	if ( class_exists( 'Kurs_Season' ) ) {
		// Grupe (ljestvice)
		echo '<optgroup label="Ljestvica grupe">';
		foreach ( Kurs_Season::all() as $s ) {
			foreach ( Kurs_Season::get_divisions( (int) $s->id ) as $d ) {
				foreach ( Kurs_Season::get_phases( (int) $d->id ) as $ph ) {
					foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
						$val = 'standings:' . (int) $g->id;
						printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $embed, $val, false ),
							esc_html( "{$s->name} · {$d->name} · {$g->name}" ) );
					}
				}
			}
		}
		echo '</optgroup>';
		// Divizije (playoff bracket)
		echo '<optgroup label="Playoff ljestvica (bracket)">';
		foreach ( Kurs_Season::all() as $s ) {
			foreach ( Kurs_Season::get_divisions( (int) $s->id ) as $d ) {
				$val = 'bracket:' . (int) $d->id;
				printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $embed, $val, false ),
					esc_html( "{$s->name} · {$d->name}" ) );
			}
		}
		echo '</optgroup>';
		// Divizije (raspored)
		echo '<optgroup label="Raspored divizije">';
		foreach ( Kurs_Season::all() as $s ) {
			foreach ( Kurs_Season::get_divisions( (int) $s->id ) as $d ) {
				$val = 'schedule:' . (int) $d->id;
				printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $embed, $val, false ),
					esc_html( "{$s->name} · {$d->name}" ) );
			}
		}
		echo '</optgroup>';
	}
	echo '</select></p>';
	echo '<p style="color:#666;font-size:12px;margin:6px 0 0;">Prikazuje se ispod teksta objave. Možeš i ručno ubaciti <code>[kurs_standings group_id="X"]</code> ili <code>[kurs_bracket division_id="Y"]</code>.</p>';
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
	if ( $embed && ! preg_match( '/^(standings|bracket|schedule):\d+$/', $embed ) ) $embed = '';
	update_post_meta( $post_id, '_kurs_embed', $embed );

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
});
JS;
}

/* ── Ispis ugrađene tablice/bracketa (ispod teksta objave) ────────────────── */
function kurskc_post_embed_html( int $post_id ): string {
	$embed = (string) get_post_meta( $post_id, '_kurs_embed', true );
	if ( ! $embed || ! preg_match( '/^(standings|bracket|schedule):(\d+)$/', $embed, $m ) ) return '';
	if ( $m[1] === 'standings' && function_exists( 'kurs_render_standings' ) ) {
		return '<div class="post-embed">' . kurs_render_standings( (int) $m[2], [ 'show_title' => true ] ) . '</div>';
	}
	if ( $m[1] === 'bracket' && function_exists( 'kurs_render_playoff_bracket' ) ) {
		return '<div class="post-embed">' . kurs_render_playoff_bracket( (int) $m[2] ) . '</div>';
	}
	if ( $m[1] === 'schedule' && function_exists( 'kurs_render_schedule' ) ) {
		return '<div class="post-embed">' . kurs_render_schedule( (int) $m[2] ) . '</div>';
	}
	return '';
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
		<span class="nw-league__cta">Tablica · raspored →</span>
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
			<a class="nw-st" href="<?php echo esc_url( get_post_permalink( $r['team_id'] ) ); ?>">
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
