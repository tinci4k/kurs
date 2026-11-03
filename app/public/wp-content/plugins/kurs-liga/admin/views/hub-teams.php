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

/* Stupci liga/skupina: naziv, boja lige, padeži za poruke potvrde („u KC Ligu" / „iz KC Lige") */
$case   = fn( string $s, array $to ) => preg_replace( [ '/\b([Ll])iga\b/u', '/\bGrupa\b/u', '/\bLjetna\b/u' ], $to, $s );
$acc    = fn( string $s ) => $case( $s, [ '${1}igu', 'Grupu', 'Ljetnu' ] );
$gen    = fn( string $s ) => $case( $s, [ '${1}ige', 'Grupe', 'Ljetne' ] );
$plural = fn( int $n, string $one, string $few, string $many ) => $n % 10 === 1 && $n % 100 !== 11 ? $one
	: ( $n % 10 >= 2 && $n % 10 <= 4 && ( $n % 100 < 12 || $n % 100 > 14 ) ? $few : $many );
$g_label = $g_color = [];
foreach ( $divisions as $d ) foreach ( $div_groups[ (int) $d->id ] as $g ) {
	$g_label[ (int) $g->id ] = count( $divisions ) > 1 && count( $div_groups[ (int) $d->id ] ) > 1 ? $d->name . ' · ' . $g->name : $g->label;
	$g_color[ (int) $g->id ] = stripos( $d->name, 'briga' ) !== false ? '#B07A1B' : $accent;
}
/* Gumbi „+ liga/skupina" uz ekipu bez lige (i u <template> za novo kreiranu ekipu) */
$add_buttons = '';
foreach ( $all_groups as $g ) {
	$add_buttons .= sprintf( '<button type="button" class="kurs-fteam__add" data-group-id="%d" style="--lg:%s;">+ %s</button>',
		(int) $g->id, esc_attr( $g_color[ (int) $g->id ] ), esc_html( $g_label[ (int) $g->id ] ) );
}
/* Ekipa s utakmicama u skupini ne može se maknuti (ajax_remove_team) — umjesto „Makni" lokot */
$team_matches = [];
if ( $all_groups ) {
	$gids = implode( ',', array_map( fn( $g ) => (int) $g->id, $all_groups ) );
	foreach ( $wpdb->get_results( "SELECT group_id, team_id, COUNT(*) AS n FROM (
		SELECT group_id, home_team_id AS team_id FROM {$p}matches WHERE group_id IN ($gids)
		UNION ALL SELECT group_id, away_team_id FROM {$p}matches WHERE group_id IN ($gids) ) x GROUP BY group_id, team_id" ) as $r ) {
		$team_matches[ (int) $r->group_id ][ (int) $r->team_id ] = (int) $r->n;
	}
}
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

<?php
/* ── Prijenos iz prethodne sezone: njezine ekipe koje još nisu u ovoj sezoni ── */
$prev_season = $is_locked ? null : Kurs_Season::previous_season( $season_id );
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

<?php if ( empty( $divisions ) ) : ?>
<div class="kurs-empty-state"><p>Ova sezona nema postavljenih liga. Obriši je u Postavkama i kreiraj ponovno.</p></div>
<?php return; endif; ?>

<?php if ( ! $is_locked ) : ?>
<p class="kurs-hub-intro">
	Ovdje biraš <strong>koje ekipe igraju u kojoj ligi</strong> ove sezone. Ekipu uvrštavaš iz popisa <em>„Ekipe koje nisu u ligi”</em>
	ispod, a mičeš gumbom <em>„Makni”</em> — sustav uvijek prvo pita, a zadnju promjenu možeš poništiti.
	Igrače ekipe za ligu uređuješ klikom na <em>„N igrača”</em>; logo, boju i trenera kluba u <a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-teams' ) ); ?>">Ekipe</a>.
</p>

<?php if ( ! empty( $comp['groups'] ) ) : ?>
<!-- ── Format lige sa skupinama (broj skupina) ─────────────────────────── -->
<div class="kurs-structure">
	<?php foreach ( $divisions as $div ) :
		$groups  = $div_groups[ (int) $div->id ];
		$n       = count( $groups );
		$bracket = class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( (int) $div->id );
		$teams_n = array_sum( array_map( fn( $g ) => count( $g->teams ), $groups ) );
	?>
	<div class="kurs-div-card">
		<div class="kurs-div-card__bar" style="background:<?php echo esc_attr( $accent ); ?>;"></div>
		<div class="kurs-div-card__body">
			<div class="kurs-div-card__name"><?php echo kurs_league_icon( $season->league . ' ' . $div->name, 20 ); ?> <?php echo esc_html( $div->name ); ?></div>
			<div class="kurs-div-card__meta">
				<?php echo (int) $teams_n; ?> ekipa · <?php echo (int) $n; ?> skupine · po <?php echo (int) $div->advance_per_group; ?> najbolje u <?php echo $n >= 4 ? 'četvrtfinale' : 'polufinale'; ?>
			</div>
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
		</div>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Lige / skupine (stupci jedan do drugog) + ekipe bez lige ───────────────
     Uvrštavanje i micanje: potvrda u dijalogu + „Poništi" (admin.js → #kurs-assign). -->
<div class="kurs-assign" id="kurs-assign">
	<div class="kurs-lcols">
		<?php foreach ( $divisions as $div ) : foreach ( $div_groups[ (int) $div->id ] as $g ) :
			$gid = (int) $g->id;
			$nt  = count( $g->teams );
		?>
		<section class="kurs-lcol" data-group-id="<?php echo $gid; ?>" data-acc="<?php echo esc_attr( $acc( $g_label[ $gid ] ) ); ?>" data-gen="<?php echo esc_attr( $gen( $g_label[ $gid ] ) ); ?>" style="--lg:<?php echo esc_attr( $g_color[ $gid ] ); ?>;">
			<header class="kurs-lcol__hd">
				<?php echo kurs_league_icon( $season->league . ' ' . $div->name, 30 ); ?>
				<div>
					<h3><?php echo esc_html( $g_label[ $gid ] ); ?></h3>
					<span class="kurs-lcol__meta"><b class="kurs-lcol__count"><?php echo $nt; ?></b> <span class="kurs-lcol__word"><?php echo $plural( $nt, 'ekipa', 'ekipe', 'ekipa' ); ?></span><?php echo empty( $div->has_playoff ) ? ' · svatko sa svakim jednom' : ''; ?></span>
				</div>
			</header>
			<ul class="kurs-lcol__teams">
				<?php foreach ( $g->teams as $gt ) :
					$tid = (int) $gt->team_id;
					$sq  = (int) ( $squad_counts[ (int) $div->id ][ $tid ] ?? 0 );
					$nm  = (int) ( $team_matches[ $gid ][ $tid ] ?? 0 );
				?>
				<li class="kurs-lteam" data-team-id="<?php echo $tid; ?>" data-name="<?php echo esc_attr( $gt->team_name ); ?>"<?php echo ( (int) $gt->base_p || $gt->rank_hint !== null ) ? ' data-base="1"' : ''; ?>>
					<?php echo kurs_team_badge_html( $tid, 30 ); ?>
					<span class="kurs-lteam__name"><?php echo esc_html( $gt->team_name ); ?></span>
					<a class="kurs-lteam__roster<?php echo $sq ? '' : ' is-empty'; ?>" href="<?php echo esc_url( $roster_url( $tid, (int) $div->id ) ); ?>" title="Igrači ekipe u ovoj ligi (s brojevima dresova)"><?php echo $sq . ' ' . $plural( $sq, 'igrač', 'igrača', 'igrača' ); ?></a>
					<?php if ( $is_locked ) : ?>
					<?php elseif ( $nm ) : ?>
					<span class="kurs-lteam__lock" title="Ekipa ima <?php echo $nm; ?> utakmica u ovoj ligi — ne može se maknuti dok se one ne obrišu (kartica Utakmice).">&#128274;</span>
					<?php else : ?>
					<button type="button" class="kurs-lteam__rm" title="Makni ekipu iz ove lige">Makni</button>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
			<p class="kurs-lcol__empty"<?php echo $nt ? ' hidden' : ''; ?>>Još nema ekipa<?php echo $is_locked ? '' : ' — dodaj ih iz popisa ispod'; ?>.</p>
		</section>
		<?php endforeach; endforeach; ?>
	</div>

	<?php if ( ! $is_locked ) : ?>
	<section class="kurs-free">
		<header class="kurs-free__hd">
			<div>
				<h3>Ekipe koje nisu u ligi</h3>
				<p>Ekipe iz registra koje ove sezone još ne igraju: <b id="kurs-free-count"><?php echo (int) $free_count; ?></b>. Klikni ligu<?php echo count( $all_groups ) > 2 ? ' ili skupinu' : ''; ?> uz ekipu da je uvrstiš.</p>
			</div>
			<input type="search" id="kurs-free-search" class="kurs-free__search" placeholder="Traži ekipu…" aria-label="Traži ekipu" autocomplete="off">
		</header>
		<div class="kurs-free__grid" id="kurs-free-grid">
			<?php foreach ( $all_teams as $t ) : ?>
			<div class="kurs-fteam" data-team-id="<?php echo (int) $t->ID; ?>" data-name="<?php echo esc_attr( $t->post_title ); ?>"<?php echo empty( $assigned[ $t->ID ] ) ? '' : ' hidden'; ?>>
				<?php echo kurs_team_badge_html( $t->ID, 30 ); ?>
				<span class="kurs-fteam__name"><?php echo esc_html( $t->post_title ); ?></span>
				<span class="kurs-fteam__go"><?php echo $add_buttons; ?></span>
			</div>
			<?php endforeach; ?>
		</div>
		<p class="kurs-free__none" id="kurs-free-none"<?php echo $free_count ? ' hidden' : ''; ?>>Sve ekipe iz registra već igraju ove sezone. Nova ekipa? Upiši je ispod.</p>
		<p class="kurs-free__none" id="kurs-free-nomatch" hidden>Nijedna ekipa bez lige ne odgovara pretrazi.</p>
		<div class="kurs-free__new">
			<label for="new-team-name-setup">Nova ekipa (prvi put igra u KURS-u):</label>
			<input type="text" id="new-team-name-setup" placeholder="Naziv ekipe" autocomplete="off">
			<button type="button" class="kurs-btn kurs-btn--dark kurs-btn--sm" id="btn-create-team-setup">+ Kreiraj ekipu</button>
			<span id="new-team-setup-msg" class="kurs-free__msg" hidden></span>
		</div>
	</section>
	<?php endif; ?>
</div>
<?php if ( $is_locked ) return; ?>

<!-- Potvrda uvrštavanja / micanja i traka „Poništi" (admin.js) -->
<div class="kurs-rec-modal kurs-confirm" id="kurs-assign-confirm" role="dialog" aria-modal="true" aria-labelledby="kurs-confirm-title" hidden>
	<div class="kurs-modal-backdrop" data-close></div>
	<div class="kurs-modal-box">
		<h3 class="kurs-modal-title" id="kurs-confirm-title"></h3>
		<div class="kurs-confirm__team"></div>
		<p class="kurs-modal-msg"></p>
		<div class="kurs-modal-actions">
			<button type="button" class="kurs-btn" data-ok></button>
			<button type="button" class="kurs-btn kurs-btn--outline" data-close>Odustani</button>
		</div>
	</div>
</div>
<div class="kurs-undo" id="kurs-undo" role="status" aria-live="polite" hidden>
	<span class="kurs-undo__msg"></span>
	<button type="button" class="kurs-undo__btn">Poništi</button>
	<button type="button" class="kurs-undo__x" aria-label="Zatvori">&times;</button>
</div>
<template id="kurs-fteam-go-tpl"><?php echo $add_buttons; ?></template>

<?php
/* ── Preneseno stanje: kola odigrana prije unosa u sustav (arhivska sezona, prelazak usred sezone) ── */
$base_used = (bool) array_filter( $all_groups, fn( $g ) => array_filter( $g->teams, fn( $t ) => (int) $t->base_p || $t->rank_hint !== null ) )
	|| array_filter( $divisions, fn( $d ) => (int) ( $d->base_round ?? 0 ) );
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
		<p class="kurs-base__round">
			<span>Preneseni golovi igrača postignuti su do kola:</span>
			<?php foreach ( $divisions as $d ) : ?>
			<label><?php echo esc_html( $d->name ); ?> <input type="number" min="0" max="99" name="base_round[<?php echo (int) $d->id; ?>]" value="<?php echo (int) ( $d->base_round ?? 0 ); ?>"></label>
			<?php endforeach; ?>
			<span class="kurs-muted">0 = prije prvog kola u sustavu. Liste strijelaca nakon ranijih kola (Po kolima, Za medije) ih ne pribrajaju — npr. 3 kad su golovi 2. i 3. kola poznati samo ukupno.</span>
		</p>
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
