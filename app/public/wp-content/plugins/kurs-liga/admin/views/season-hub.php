<?php
defined( 'ABSPATH' ) || exit;

/*
 * Stranica natjecanja (KC Liga / Pan Ljetna liga) — „početna" lige u adminu.
 * Sve o jednoj sezoni na jednom mjestu, u karticama:
 *   pregled  → stanje sezone, što treba napraviti, sljedeće utakmice, rezultati
 *   ekipe    → koje ekipe igraju u kojoj ligi/skupini (+ igrači po ligi), raspored
 *   utakmice → raspored, unos rezultata/zapisnika, ljestvica, playoff
 *   postavke → naziv, status, faulovi, zaključavanje, brisanje
 * URL: admin.php?page=kurs-kc|kurs-pan &season_id= &tab=
 */

$hub_page   = sanitize_key( $_GET['page'] ?? 'kurs-kc' );
$league_key = Kurs_Admin::HUBS[ $hub_page ] ?? 'kc_liga';
$is_pan     = Kurs_Season::is_pan_league( $league_key );
$comp       = Kurs_Season::competition( $league_key );
$accent     = $is_pan ? '#F47B16' : '#DA0F1E';

/* Sezona: iz URL-a (mora biti ovog natjecanja), inače aktualna */
$hub_seasons = Kurs_Season::for_league( $is_pan );
$season_id   = absint( $_GET['season_id'] ?? 0 );
if ( ! in_array( $season_id, array_map( fn( $s ) => (int) $s->id, $hub_seasons ), true ) ) {
	$cur       = Kurs_Season::current_for_league( $is_pan );
	$season_id = (int) ( $cur->id ?? 0 );
}
$season    = $season_id ? Kurs_Season::get( $season_id ) : null;
$divisions = $season ? Kurs_Season::get_divisions( $season_id ) : [];
$is_locked = $season && ! empty( $season->locked );

$hub_tabs = [ 'pregled' => 'Pregled', 'ekipe' => 'Ekipe', 'utakmice' => 'Utakmice', 'postavke' => 'Postavke' ];
$hub_tab  = sanitize_key( $_GET['tab'] ?? '' );
if ( ! isset( $hub_tabs[ $hub_tab ] ) ) $hub_tab = 'pregled';
$hub_url  = fn( string $tab = '', array $args = [] ) => Kurs_Admin::hub_url( $season_id, $tab, $args );

/* Brojke za kartice */
global $wpdb;
$p          = $wpdb->prefix . KURS_PREFIX;
$hub_counts = [ 'teams' => 0, 'matches' => 0, 'finished' => 0 ];
if ( $season ) {
	$hub_counts['teams'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT gt.team_id) FROM {$p}group_teams gt JOIN {$p}groups g ON g.id = gt.group_id
		 JOIN {$p}phases ph ON ph.id = g.phase_id JOIN {$p}divisions d ON d.id = ph.division_id WHERE d.season_id = %d", $season_id ) );
	$mc = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS total, SUM(m.status = 'finished') AS fin FROM {$p}matches m JOIN {$p}groups g ON g.id = m.group_id
		 JOIN {$p}phases ph ON ph.id = g.phase_id JOIN {$p}divisions d ON d.id = ph.division_id WHERE d.season_id = %d", $season_id ) );
	$hub_counts['matches']  = (int) ( $mc->total ?? 0 );
	$hub_counts['finished'] = (int) ( $mc->fin ?? 0 );
}
$public_url = home_url( $is_pan ? '/pan-ljetna/' : '/kc-liga/' ) . ( $season ? '?season_id=' . $season_id : '' );
?>
<div class="wrap kurs-wrap kurs-hub" style="--hub-accent:<?php echo esc_attr( $accent ); ?>;">

	<div class="kurs-breadcrumb">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $hub_page ) ); ?>"><?php echo esc_html( $comp['label'] ?? '' ); ?></a>
		<?php if ( $season ) : ?>
		<span class="kurs-breadcrumb__sep">/</span><?php echo esc_html( $season->name ); ?>
		<span class="kurs-breadcrumb__sep">/</span><?php echo esc_html( $hub_tabs[ $hub_tab ] ); ?>
		<?php endif; ?>
	</div>

	<!-- ── Zaglavlje natjecanja ─────────────────────────────────────────── -->
	<div class="kurs-hub-hd">
		<div class="kurs-hub-hd__id">
			<?php echo kurs_league_icon( $is_pan ? 'Pan Ljetna' : 'KC Liga', 52 ); ?>
			<div>
				<h1><?php echo esc_html( $comp['label'] ?? '' ); ?></h1>
				<div class="kurs-hub-hd__meta">
					<?php if ( $season ) : ?>
					<span class="kurs-hub-hd__season"><?php echo esc_html( $season->name ); ?></span>
					<?php if ( $is_locked ) : ?>
					<span class="kurs-badge" style="background:#111317;color:#FDE206;">&#128274; Zaključana</span>
					<?php elseif ( $season->status === 'active' ) : ?>
					<span class="kurs-badge kurs-badge--active">Aktivna</span>
					<?php else : ?>
					<span class="kurs-badge kurs-badge--archived">Arhiva</span>
					<?php endif; ?>
					<?php if ( $divisions ) : ?>
					<span class="kurs-hub-hd__divs"><?php echo esc_html( implode( ' · ', array_map( fn( $d ) => $d->name, $divisions ) ) ); ?></span>
					<?php endif; ?>
					<?php else : ?>
					<span class="kurs-hub-hd__season">Još nema sezone</span>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="kurs-hub-hd__actions">
			<?php if ( count( $hub_seasons ) > 1 ) : ?>
			<form method="get" class="kurs-hub-season-pick">
				<input type="hidden" name="page" value="<?php echo esc_attr( $hub_page ); ?>">
				<?php if ( $hub_tab !== 'pregled' ) : ?><input type="hidden" name="tab" value="<?php echo esc_attr( $hub_tab ); ?>"><?php endif; ?>
				<label class="screen-reader-text" for="kurs-hub-season">Sezona</label>
				<select id="kurs-hub-season" name="season_id" onchange="this.form.submit()">
					<?php foreach ( $hub_seasons as $s ) : ?>
					<option value="<?php echo (int) $s->id; ?>" <?php selected( (int) $s->id, $season_id ); ?>>
						<?php echo esc_html( $s->name . ( $s->status === 'active' ? '' : ' (arhiva)' ) ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</form>
			<?php endif; ?>
			<?php if ( $season ) : ?>
			<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( $public_url ); ?>" target="_blank" rel="noopener">Javna stranica &#8599;</a>
			<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( add_query_arg( [ 'liga' => $is_pan ? 'pan' : 'kc', 'season_id' => $season_id ], home_url( '/za-medije/' ) ) ); ?>" target="_blank" rel="noopener">Za medije &#8599;</a>
			<?php endif; ?>
			<a class="kurs-btn kurs-btn--ghost kurs-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-seasons&league=' . $league_key . '#nova-sezona' ) ); ?>">+ Nova sezona</a>
		</div>
	</div>

	<?php if ( ! $season ) : ?>
	<!-- ── Natjecanje još nema sezone ───────────────────────────────────── -->
	<div class="kurs-panel" style="max-width:560px;">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title">Kreiraj prvu sezonu</h3></div>
		<div class="kurs-panel__body" style="padding:18px;">
			<form method="post" class="kurs-form-col" style="gap:12px;">
				<?php wp_nonce_field( 'kurs_create_season' ); ?>
				<input type="hidden" name="kurs_action" value="create_season">
				<input type="hidden" name="league" value="<?php echo esc_attr( $league_key ); ?>">
				<label class="kurs-field-wrap"><span class="kurs-field-label"><?php echo ! empty( $comp['span_year'] ) ? 'Godina početka sezone' : 'Godina'; ?></span>
					<input type="number" name="year" class="kurs-field-input" value="<?php echo (int) date( 'Y' ); ?>" min="2000" max="2100" required></label>
				<?php if ( ! empty( $comp['groups'] ) ) : ?>
				<label class="kurs-field-wrap"><span class="kurs-field-label">Broj skupina</span>
					<select name="pan_groups"><option value="4">4 skupine — po 2 u četvrtfinale</option><option value="2">2 skupine — po 2 u polufinale</option></select></label>
				<?php endif; ?>
				<p class="kurs-muted" style="margin:0;font-size:12px;">Lige i skupine nastaju automatski<?php echo ! empty( $comp['divisions'] ) ? ' (' . esc_html( implode( ' i ', $comp['divisions'] ) ) . ')' : ''; ?>.</p>
				<button type="submit" class="kurs-btn kurs-btn--red">+ Kreiraj sezonu</button>
			</form>
		</div>
	</div>
	</div>
	<?php return; endif; ?>

	<!-- ── Kartice ───────────────────────────────────────────────────────── -->
	<nav class="kurs-hub-tabs" aria-label="Sezona">
		<?php foreach ( $hub_tabs as $key => $label ) :
			$count = [ 'ekipe' => $hub_counts['teams'], 'utakmice' => $hub_counts['matches'] ? $hub_counts['finished'] . '/' . $hub_counts['matches'] : '' ][ $key ] ?? '';
		?>
		<a href="<?php echo esc_url( $hub_url( $key ) ); ?>" class="kurs-hub-tab<?php echo $hub_tab === $key ? ' is-active' : ''; ?>"<?php echo $hub_tab === $key ? ' aria-current="page"' : ''; ?>>
			<?php echo esc_html( $label ); ?><?php if ( $count !== '' && $count !== 0 ) : ?><span class="kurs-hub-tab__count"><?php echo esc_html( (string) $count ); ?></span><?php endif; ?>
		</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $is_locked && $hub_tab !== 'postavke' ) : ?>
	<div class="kurs-hub-locked">&#128274; Sezona je <strong>zaključana</strong> — podaci su konačni. Otključati je možeš u <a href="<?php echo esc_url( $hub_url( 'postavke' ) ); ?>">Postavkama</a>.</div>
	<?php endif; ?>

	<div class="kurs-hub-body">
	<?php
	// Varijable dostupne karticama: $season, $season_id, $divisions, $is_pan, $comp, $accent, $is_locked, $hub_url, $hub_page
	require KURS_DIR . 'admin/views/hub-' . [ 'pregled' => 'overview', 'ekipe' => 'teams', 'utakmice' => 'matches', 'postavke' => 'settings' ][ $hub_tab ] . '.php';
	?>
	</div>
</div>
