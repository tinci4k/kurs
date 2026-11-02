<?php
defined( 'ABSPATH' ) || exit;

/*
 * Stranica natjecanja → kartica „Ekipe": koje ekipe igraju u kojoj ligi (skupini)
 * ove sezone + igrači svake ekipe za tu ligu + generiranje rasporeda.
 * Ekipe (klubovi) su zajednički registar u „Ekipe" — ovdje ih se samo uvrštava; „N igr." otvara
 * roster ekipe u ligi (?roster=&division_id=, roster-panel.php).
 * Varijable iz season-hub.php: $season, $season_id, $divisions, $comp, $is_locked, $hub_url.
 */

$all_teams = get_posts( [ 'post_type' => 'kurs_ekipa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );

/* Skupine po ligi + broj igrača svake ekipe u toj ligi */
global $wpdb;
$p            = $wpdb->prefix . KURS_PREFIX;
$div_groups   = [];
$assigned     = []; // team_id => [ 'Liga · Skupina', ... ]
$ready_groups = 0;
$match_total  = 0;
foreach ( $divisions as $d ) {
	$ph = Kurs_Season::group_phase( (int) $d->id );
	$div_groups[ (int) $d->id ] = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
	foreach ( $div_groups[ (int) $d->id ] as $g ) {
		$g->teams = Kurs_Season::get_group_teams( (int) $g->id );
		$g->label = ( count( $div_groups[ (int) $d->id ] ) === 1 ) ? $d->name : $g->name;
		if ( count( $g->teams ) >= 2 ) $ready_groups++;
		$match_total += Kurs_Season::group_match_count( (int) $g->id );
		foreach ( $g->teams as $gt ) $assigned[ (int) $gt->team_id ][] = $g->label;
	}
}
$squad_counts = [];
if ( $divisions ) {
	$ids = implode( ',', array_map( fn( $d ) => (int) $d->id, $divisions ) );
	foreach ( $wpdb->get_results( "SELECT team_id, division_id, COUNT(*) AS n FROM {$p}squads WHERE division_id IN ($ids) GROUP BY team_id, division_id" ) as $r ) {
		$squad_counts[ (int) $r->division_id ][ (int) $r->team_id ] = (int) $r->n;
	}
}
$roster_url = Kurs_Admin::roster_url( ... );

/* ── Roster jedne ekipe u ligi (?roster=EKIPA&division_id=LIGA) ─────────────────────────── */
$roster_team_id     = absint( $_GET['roster'] ?? 0 );
$roster_division_id = absint( $_GET['division_id'] ?? 0 );
if ( $roster_team_id && isset( $div_groups[ $roster_division_id ] ) && Kurs_Season::team_group_in_division( $roster_division_id, $roster_team_id ) ) :
	$roster_locked = $is_locked;
	$roster_div    = Kurs_Season::get_division( $roster_division_id );
	$captain       = (int) get_post_meta( $roster_team_id, '_kurs_team_captain', true );
	$coach         = kurs_team_coach( $roster_team_id );
	?>
	<div class="kurs-roster-hd">
		<a href="<?php echo esc_url( $hub_url( 'ekipe' ) ); ?>" class="kurs-back">&larr; Sve ekipe sezone</a>
		<label class="kurs-roster-hd__switch">
			<span class="kurs-muted">Ekipa:</span>
			<select onchange="if(this.value)location.href=this.value" aria-label="Prijeđi na roster druge ekipe">
				<?php foreach ( $divisions as $d ) : ?>
				<optgroup label="<?php echo esc_attr( $d->name ); ?>">
					<?php foreach ( $div_groups[ (int) $d->id ] as $g ) : foreach ( $g->teams as $gt ) : ?>
					<option value="<?php echo esc_url( $roster_url( (int) $gt->team_id, (int) $d->id ) ); ?>" <?php selected( (int) $gt->team_id === $roster_team_id && (int) $d->id === $roster_division_id ); ?>>
						<?php echo esc_html( $gt->team_name . ' (' . (int) ( $squad_counts[ (int) $d->id ][ (int) $gt->team_id ] ?? 0 ) . ')' ); ?>
					</option>
					<?php endforeach; endforeach; ?>
				</optgroup>
				<?php endforeach; ?>
			</select>
		</label>
	</div>

	<div class="kurs-roster-title">
		<?php echo kurs_team_badge_html( $roster_team_id, 44 ); ?>
		<div>
			<h2><?php echo esc_html( get_the_title( $roster_team_id ) ); ?></h2>
			<span class="kurs-muted"><?php echo esc_html( $season->name . ' · ' . ( $roster_div->name ?? '' ) ); ?></span>
		</div>
	</div>

	<div class="kurs-roster-layout">
		<div><?php require KURS_DIR . 'admin/views/roster-panel.php'; ?></div>
		<aside class="kurs-panel">
			<div class="kurs-panel__head"><h3 class="kurs-panel__title">Ekipa</h3></div>
			<div class="kurs-panel__body kurs-roster-side">
				<p><span class="kurs-muted">Kapetan:</span> <?php echo $captain ? esc_html( get_the_title( $captain ) ) : '<span class="kurs-muted">—</span>'; ?></p>
				<p><span class="kurs-muted">Trener:</span> <?php echo $coach ? esc_html( $coach['name'] ) : '<span class="kurs-muted">—</span>'; ?></p>
				<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams&action=edit&id=' . $roster_team_id . '&division_id=' . $roster_division_id ) ); ?>">&#9998; Podaci ekipe</a>
				<a class="kurs-btn kurs-btn--ghost kurs-btn--sm" href="<?php echo esc_url( kurs_team_url( $roster_team_id, $roster_division_id ) ); ?>" target="_blank" rel="noopener">Javni profil &#8599;</a>
				<p class="kurs-muted" style="font-size:11px;margin:4px 0 0;">Logo, boja, trener, predstavnik, kapetan i sponzori su u podacima ekipe (vrijede za sve sezone).</p>
			</div>
		</aside>
	</div>
	<?php
	return;
endif;

$has_teams   = $ready_groups > 0;
$has_matches = $match_total > 0;
$all_groups  = array_merge( ...array_values( $div_groups ?: [ [] ] ) );
$free_count  = count( array_filter( $all_teams, fn( $t ) => empty( $assigned[ $t->ID ] ) ) );
/* Kratka oznaka na gumbu „uvrsti u…": skupina → slovo (A, B…), Liga bez briga → „Bez briga" */
$short = fn( string $label ) => stripos( $label, 'briga' ) !== false ? 'Bez briga'
	: ( preg_match( '/^Grupa\s+(\S+)$/u', $label, $m ) ? $m[1] : $label );
?>

<?php if ( ! empty( $_GET['created'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>
	Sezona je kreirana<?php echo $divisions ? ' s ' . esc_html( implode( ' i ', array_map( fn( $d ) => $d->name, $divisions ) ) ) : ''; ?>.
	Sad uvrsti ekipe u lige (ili ih preuzmi iz prošle sezone, zajedno s igračima), pa generiraj raspored.
	<?php
	$arch = array_filter( array_map( fn( $sid ) => Kurs_Season::get( absint( $sid ) ), explode( ',', (string) ( $_GET['archived'] ?? '' ) ) ) );
	if ( $arch ) : ?>
	<br><?php echo esc_html( implode( ', ', array_map( fn( $s ) => $s->name, $arch ) ) ); ?> <?php echo count( $arch ) > 1 ? 'su prebačene' : 'je prebačena'; ?> u arhivu (vraća se u njezinim Postavkama).
	<?php endif; ?>
</p></div>
<?php elseif ( isset( $_GET['carried'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>
	Preuzeto ekipa: <strong><?php echo absint( $_GET['carried'] ); ?></strong>, igrača: <strong><?php echo absint( $_GET['carried_players'] ?? 0 ); ?></strong>.
	<?php if ( absint( $_GET['carried_skipped'] ?? 0 ) ) : ?>Preskočeno igrača: <?php echo absint( $_GET['carried_skipped'] ); ?> (u toj ligi već igraju za drugu ekipu).<?php endif; ?>
	Provjeri rostere („N igr."), pa generiraj raspored.
</p></div>
<?php elseif ( ! empty( $_GET['base_saved'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Preneseno stanje je spremljeno — ljestvica ga uključuje.</p></div>
<?php elseif ( ! empty( $_GET['groups_saved'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Broj skupina je spremljen.</p></div>
<?php elseif ( ! empty( $_GET['groups_error'] ) ) : ?>
<div class="notice notice-error is-dismissible"><p><?php echo esc_html( wp_unslash( $_GET['groups_error'] ) ); ?></p></div>
<?php endif; ?>

<?php if ( $is_locked ) : ?>
<!-- Zaključana sezona: samo pregled -->
<div class="kurs-structure">
	<?php foreach ( $divisions as $div ) : foreach ( $div_groups[ (int) $div->id ] as $g ) : ?>
	<div class="kurs-panel">
		<div class="kurs-panel__head"><h3 class="kurs-panel__title"><?php echo esc_html( $g->label ); ?></h3><span class="kurs-muted"><?php echo count( $g->teams ); ?> ekipa</span></div>
		<div class="kurs-panel__body" style="padding:6px 0;">
			<?php foreach ( $g->teams as $gt ) : ?>
			<a class="kurs-hub-team-row" href="<?php echo esc_url( $roster_url( (int) $gt->team_id, (int) $div->id ) ); ?>">
				<?php echo kurs_team_badge_html( (int) $gt->team_id, 22 ); ?><span><?php echo esc_html( $gt->team_name ); ?></span>
				<em><?php echo (int) ( $squad_counts[ (int) $div->id ][ (int) $gt->team_id ] ?? 0 ); ?> igr.</em>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endforeach; endforeach; ?>
</div>
<?php return; endif; ?>

<?php
/* ── Prijenos iz prethodne sezone: njezine ekipe koje još nisu u ovoj sezoni ── */
$prev_season = Kurs_Season::previous_season( $season_id );
$carry_rows  = $prev_season ? array_filter( Kurs_Season::season_teams( (int) $prev_season->id ), fn( $t ) => empty( $assigned[ (int) $t->team_id ] ) ) : [];
if ( $carry_rows ) :
	$carry_def = Kurs_Season::carry_over_defaults( (int) $prev_season->id, $season_id );
	$carry_grp = Kurs_Season::season_groups( $season_id );
	$grp_label = fn( $g ) => $g->groups_in_division > 1 ? ( count( $divisions ) > 1 ? $g->division_name . ' · ' : '' ) . $g->name : $g->division_name;
	$carry_pl  = array_sum( array_map( fn( $t ) => (int) $t->players, $carry_rows ) );
?>
<details class="kurs-panel kurs-carry"<?php echo $assigned ? '' : ' open'; ?>>
	<summary class="kurs-panel__head">
		<h3 class="kurs-panel__title">&#8634; Preuzmi ekipe iz sezone <?php echo esc_html( $prev_season->name ); ?></h3>
		<span class="kurs-muted"><?php echo count( $carry_rows ); ?> ekipa · <?php echo (int) $carry_pl; ?> igrača</span>
	</summary>
	<form method="post" class="kurs-carry__body">
		<?php wp_nonce_field( 'kurs_carry_over_teams' ); ?>
		<input type="hidden" name="kurs_action" value="carry_over_teams">
		<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">
		<p class="kurs-muted kurs-carry__intro">
			Za svaku ekipu koja ponovno igra odaberi ligu<?php echo ! empty( $comp['groups'] ) ? ' i skupinu' : ''; ?>; „— ne igra —" je preskače.
			Predloženo je kao prošle sezone<?php echo ! empty( $comp['groups'] ) ? ' — skupine promijeni prema ždrijebu' : ''; ?>.
		</p>
		<div class="kurs-carry__rows">
			<?php foreach ( $carry_rows as $key => $t ) : ?>
			<div class="kurs-carry__row">
				<?php echo kurs_team_badge_html( (int) $t->team_id, 22 ); ?>
				<span class="kurs-carry__name" title="<?php echo esc_attr( $t->team_name ); ?>"><?php echo esc_html( $t->team_name ); ?></span>
				<span class="kurs-carry__was" title="Prošle sezone: <?php echo esc_attr( $t->division_name . ( ! empty( $comp['groups'] ) ? ' · ' . $t->group_name : '' ) ); ?>"><?php echo (int) $t->players; ?> igr.</span>
				<select name="carry[<?php echo esc_attr( $key ); ?>]" class="kurs-carry__sel" data-default="<?php echo (int) ( $carry_def[ $key ] ?? 0 ); ?>" aria-label="Liga za <?php echo esc_attr( $t->team_name ); ?>">
					<option value="0">— ne igra —</option>
					<?php foreach ( $carry_grp as $gid => $g ) : ?>
					<option value="<?php echo (int) $gid; ?>" <?php selected( (int) ( $carry_def[ $key ] ?? 0 ), (int) $gid ); ?>><?php echo esc_html( $grp_label( $g ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endforeach; ?>
		</div>
		<div class="kurs-carry__foot">
			<label><input type="checkbox" name="with_roster" value="1" checked> Prenesi i igrače s brojevima dresova</label>
			<span class="kurs-carry__quick">
				<button type="button" class="kurs-btn kurs-btn--ghost kurs-btn--xs" data-carry="default">Sve kao prošle sezone</button>
				<button type="button" class="kurs-btn kurs-btn--ghost kurs-btn--xs" data-carry="none">Nijedna</button>
			</span>
			<button type="submit" class="kurs-btn kurs-btn--red">Preuzmi odabrane ekipe</button>
		</div>
		<p class="kurs-muted kurs-carry__note">Igrače koji više ne igraju makni iz rostera ekipe („N igr."). Igrač koji u ligi već igra za drugu ekipu se preskače.</p>
	</form>
</details>
<?php endif; ?>

<p class="kurs-hub-intro">
	Ovdje biraš <strong>koje ekipe igraju u kojoj ligi</strong> ove sezone. Ekipa (klub) se u sustav upisuje jednom
	— u <a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams' ) ); ?>">Ekipe</a> — i može igrati više sezona.
	Igrače ekipe za ovu ligu (roster s brojevima dresova) uređuješ klikom na <em>„N igr."</em> uz ekipu.
</p>

<!-- ── Lige sezone (struktura je automatska) ───────────────────────────── -->
<div class="kurs-structure">
	<?php foreach ( $divisions as $div ) :
		$groups  = $div_groups[ (int) $div->id ];
		$n       = count( $groups );
		$bracket = class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( (int) $div->id );
		$color   = stripos( $div->name, 'briga' ) !== false ? '#B07A1B' : $accent;
		$teams_n = array_sum( array_map( fn( $g ) => count( $g->teams ), $groups ) );
	?>
	<div class="kurs-div-card">
		<div class="kurs-div-card__bar" style="background:<?php echo esc_attr( $color ); ?>;"></div>
		<div class="kurs-div-card__body">
			<div class="kurs-div-card__name"><?php echo kurs_league_icon( $season->league . ' ' . $div->name, 20 ); ?> <?php echo esc_html( $div->name ); ?></div>
			<div class="kurs-div-card__meta">
				<?php echo (int) $teams_n; ?> ekipa ·
				<?php if ( ! empty( $div->has_playoff ) ) : ?>
				<?php echo (int) $n; ?> skupine · po <?php echo (int) $div->advance_per_group; ?> najbolje u <?php echo $n >= 4 ? 'četvrtfinale' : 'polufinale'; ?>
				<?php else : ?>
				svatko sa svakim jednom · bez playoffa
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $comp['groups'] ) ) : ?>
				<?php if ( $bracket ) : ?>
				<p class="kurs-muted" style="margin:8px 0 0;font-size:11px;">Playoff je generiran — broj skupina je zaključan.</p>
				<?php else : ?>
				<form method="post" class="kurs-div-format" style="margin-top:8px;">
					<?php wp_nonce_field( 'kurs_set_group_count' ); ?>
					<input type="hidden" name="kurs_action" value="set_group_count">
					<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">
					<input type="hidden" name="division_id" value="<?php echo (int) $div->id; ?>">
					<span class="kurs-div-format__lbl">Broj skupina</span>
					<select name="count">
						<?php foreach ( Kurs_Season::PAN_GROUP_OPTIONS as $opt ) : ?>
						<option value="<?php echo (int) $opt; ?>" <?php selected( $n, $opt ); ?>><?php echo (int) $opt; ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="kurs-btn kurs-btn--outline kurs-btn--sm">Spremi</button>
				</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<?php if ( empty( $divisions ) ) : ?>
<div class="kurs-empty-state"><p>Ova sezona nema postavljenih liga. Obriši je u Postavkama i kreiraj ponovno.</p></div>
<?php return; endif; ?>

<script>
window.KursTeams = <?php echo wp_json_encode( array_map( fn( $t ) => [ 'id' => $t->ID, 'name' => $t->post_title ], $all_teams ) ); ?>;
</script>
<!-- Gumbi „u ligu/skupinu" za novo kreiranu ekipu (JS kopira u popis) -->
<template id="kurs-pool-assign-tpl"><?php foreach ( $all_groups as $g ) : ?><button type="button" class="kurs-pool-assign" data-group-id="<?php echo (int) $g->id; ?>" title="Uvrsti u: <?php echo esc_attr( $g->label ); ?>"><?php echo esc_html( $short( $g->label ) ); ?></button><?php endforeach; ?></template>

<div class="kurs-assign-layout">

	<!-- ── Sve ekipe (registar) ────────────────────────────────────────── -->
	<div class="kurs-team-pool">
		<h3>Sve ekipe</h3>
		<p class="kurs-muted">Bez lige u ovoj sezoni: <strong id="kurs-pool-free-count"><?php echo (int) $free_count; ?></strong>. Klikni ligu<?php echo count( $all_groups ) > 2 ? ' / skupinu' : ''; ?> uz ekipu da je uvrstiš.</p>

		<div class="kurs-team-list" id="kurs-team-pool-list">
			<?php
			$pool = $all_teams; // ekipe bez lige na vrh
			usort( $pool, fn( $a, $b ) => ( empty( $assigned[ $b->ID ] ) <=> empty( $assigned[ $a->ID ] ) ) ?: strcoll( $a->post_title, $b->post_title ) );
			foreach ( $pool as $t ) :
				$in = $assigned[ $t->ID ] ?? [];
			?>
			<div class="kurs-team-pill<?php echo $in ? ' is-assigned' : ''; ?>" data-team-id="<?php echo (int) $t->ID; ?>" title="<?php echo esc_attr( $in ? 'Igra u: ' . implode( ', ', $in ) : 'Nije u ligi ove sezone' ); ?>">
				<?php echo kurs_team_badge_html( $t->ID, 24 ); ?>
				<span class="kurs-team-pill__name"><?php echo esc_html( $t->post_title ); ?></span>
				<span class="kurs-team-pill__go"><?php foreach ( $all_groups as $g ) : ?><button type="button" class="kurs-pool-assign" data-group-id="<?php echo (int) $g->id; ?>" title="Uvrsti u: <?php echo esc_attr( $g->label ); ?>"><?php echo esc_html( $short( $g->label ) ); ?></button><?php endforeach; ?></span>
			</div>
			<?php endforeach; ?>
		</div>

		<div class="kurs-new-team-form">
			<h4>+ Nova ekipa</h4>
			<input type="text" id="new-team-name-setup" class="regular-text" placeholder="Naziv ekipe">
			<button type="button" class="kurs-btn kurs-btn--dark kurs-btn--sm" id="btn-create-team-setup" style="width:100%;justify-content:center;">+ Kreiraj ekipu</button>
			<p id="new-team-setup-msg" class="kurs-muted" style="margin-top:4px;display:none;"></p>
		</div>
	</div>

	<!-- ── Lige i skupine ──────────────────────────────────────────────── -->
	<div class="kurs-groups-canvas">
		<?php foreach ( $divisions as $div ) :
			$groups = $div_groups[ (int) $div->id ];
		?>
		<?php if ( count( $divisions ) > 1 || count( $groups ) > 1 ) : ?><h2><?php echo esc_html( $div->name ); ?></h2><?php endif; ?>

		<div class="kurs-group-cols">
			<?php foreach ( $groups as $g ) : ?>
			<div class="kurs-group-col" data-group-id="<?php echo (int) $g->id; ?>">
				<div class="kurs-group-col__head">
					<span><?php echo esc_html( count( $groups ) === 1 ? 'Ekipe u ligi' : $g->name ); ?></span>
					<span class="kurs-muted kurs-cnt"><?php echo count( $g->teams ); ?> ekipa</span>
				</div>
				<div class="kurs-group-col__teams">
					<?php foreach ( $g->teams as $gt ) :
						$sq = (int) ( $squad_counts[ (int) $div->id ][ (int) $gt->team_id ] ?? 0 );
					?>
					<div class="kurs-group-team" data-team-id="<?php echo (int) $gt->team_id; ?>">
						<?php echo kurs_team_badge_html( (int) $gt->team_id, 22 ); ?>
						<span><?php echo esc_html( $gt->team_name ); ?></span>
						<a class="kurs-group-team__roster<?php echo $sq ? '' : ' is-empty'; ?>" href="<?php echo esc_url( $roster_url( (int) $gt->team_id, (int) $div->id ) ); ?>"
						   title="Igrači ekipe u ovoj ligi"><?php echo $sq; ?> igr.</a>
						<button class="kurs-btn-rm" data-group-id="<?php echo (int) $g->id; ?>" data-team-id="<?php echo (int) $gt->team_id; ?>" title="Makni iz lige">&times;</button>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="kurs-group-col__add">
					<div class="kurs-team-ac-wrap">
						<input type="text" class="kurs-team-search small-text" placeholder="Traži ekipu..." autocomplete="off">
						<input type="hidden" class="kurs-team-selected-id" value="">
					</div>
					<button class="button button-small kurs-btn-add-team" data-group-id="<?php echo (int) $g->id; ?>">Dodaj</button>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endforeach; ?>
	</div>
</div>

<?php
/* ── Preneseno stanje: kola odigrana prije unosa u sustav (arhivska sezona, prelazak usred sezone) ── */
$base_used = (bool) array_filter( $all_groups, fn( $g ) => array_filter( $g->teams, fn( $t ) => (int) $t->base_p || $t->rank_hint !== null ) );
if ( $all_groups && array_filter( $all_groups, fn( $g ) => $g->teams ) ) : ?>
<details class="kurs-panel kurs-base"<?php echo $base_used ? ' open' : ''; ?>>
	<summary class="kurs-panel__head">
		<h3 class="kurs-panel__title">Preneseno stanje</h3>
		<span class="kurs-muted"><?php echo $base_used ? 'uneseno — pribraja se ljestvici' : 'samo ako dio kola nije u sustavu'; ?></span>
	</summary>
	<form method="post" class="kurs-base__body">
		<?php wp_nonce_field( 'kurs_save_base_standings' ); ?>
		<input type="hidden" name="kurs_action" value="save_base_standings">
		<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">
		<p class="kurs-muted kurs-base__intro">
			Ako su neka kola odigrana prije nego što se liga vodi ovdje (npr. stara sezona za koju postoji samo konačna ljestvica),
			upiši njihov zbroj po ekipi: pobjede, neriješene, porazi i golovi. Ljestvica = ovo + utakmice u sustavu.
			<strong>Redoslijed</strong> popuni samo kod jednakih bodova kad je odlučila međusobna utakmica koje nema u sustavu (1 = viši).
			Golove igrača iz tih kola upisuješ u rosteru ekipe (Uredi → „Preneseni golovi”).
		</p>
		<?php foreach ( $all_groups as $g ) : if ( ! $g->teams ) continue; ?>
		<table class="widefat kurs-base__table">
			<thead><tr><th><?php echo esc_html( $g->label ); ?></th><th title="Pobjede">P</th><th title="Neriješeno">N</th><th title="Porazi">I</th><th title="Dani golovi">G+</th><th title="Primljeni golovi">G−</th><th title="Utakmice / bodovi iz prenesenog stanja">Ut. / Bod</th><th title="Redoslijed kod jednakih bodova">Red.</th></tr></thead>
			<tbody>
			<?php foreach ( $g->teams as $t ) :
				$k = 'base[' . (int) $g->id . '][' . (int) $t->team_id . ']'; ?>
			<tr>
				<td><?php echo kurs_team_badge_html( (int) $t->team_id, 18 ); ?> <?php echo esc_html( $t->team_name ); ?></td>
				<?php foreach ( [ 'w' => $t->base_w, 'd' => $t->base_d, 'l' => $t->base_l, 'gf' => $t->base_gf, 'ga' => $t->base_ga ] as $f => $v ) : ?>
				<td><input type="number" min="0" max="999" name="<?php echo esc_attr( $k . '[' . $f . ']' ); ?>" value="<?php echo (int) $v; ?>"></td>
				<?php endforeach; ?>
				<td class="kurs-muted"><?php echo (int) $t->base_p; ?> / <?php echo 3 * (int) $t->base_w + (int) $t->base_d; ?></td>
				<td><input type="number" min="1" max="99" name="<?php echo esc_attr( $k . '[hint]' ); ?>" value="<?php echo $t->rank_hint !== null ? (int) $t->rank_hint : ''; ?>" placeholder="—"></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endforeach; ?>
		<button type="submit" class="kurs-btn kurs-btn--outline kurs-btn--sm">Spremi preneseno stanje</button>
	</form>
</details>
<?php endif; ?>

<!-- ── Raspored ──────────────────────────────────────────────────────────── -->
<div class="kurs-hub-next">
	<div>
		<strong><?php echo $has_matches ? 'Raspored je generiran' : 'Sljedeći korak: raspored'; ?></strong>
		<span class="kurs-muted"><?php echo $has_matches
			? sprintf( '%d utakmica. Ako si naknadno dodao ekipu, „Dopuni raspored" doda samo utakmice koje nedostaju.', $match_total )
			: 'Kad su ekipe u ligama, jednim klikom napravi raspored — svatko sa svakim jednom.'; ?></span>
	</div>
	<div style="display:flex;gap:8px;flex-wrap:wrap;">
		<?php if ( $has_teams ) : ?>
		<form method="post" style="margin:0;"
		      onsubmit="return confirm('<?php echo $has_matches
		          ? esc_js( 'Dodati utakmice koje nedostaju (npr. za naknadno dodane ekipe)? Postojeće utakmice ostaju netaknute.' )
		          : esc_js( 'Generirati raspored za sve lige/skupine? Svaka ekipa igra sa svakom jednom.' ); ?>');">
			<?php wp_nonce_field( 'kurs_generate_season_fixtures' ); ?>
			<input type="hidden" name="kurs_action" value="generate_season_fixtures">
			<input type="hidden" name="season_id"   value="<?php echo (int) $season_id; ?>">
			<button type="submit" class="kurs-btn <?php echo $has_matches ? 'kurs-btn--outline' : 'kurs-btn--red'; ?>">
				<?php echo $has_matches ? '&#8635; Dopuni raspored' : 'Generiraj raspored &rarr;'; ?>
			</button>
		</form>
		<?php endif; ?>
		<?php if ( $has_matches ) : ?>
		<a href="<?php echo esc_url( $hub_url( 'utakmice' ) ); ?>" class="kurs-btn kurs-btn--red">Utakmice &rarr;</a>
		<?php endif; ?>
	</div>
</div>
