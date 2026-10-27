<?php
defined( 'ABSPATH' ) || exit;

/*
 * Igrači — zajednički registar (CPT kurs_igrac): igrač se upisuje jednom i igra više sezona i
 * natjecanja. Ovdje: popis s tražilicom, podaci igrača (pozicija, godina rođenja, fotografija),
 * karijera, spajanje dvostruko upisanih i brisanje igrača bez utakmica (Kurs_Players).
 * U ekipu se igrač upisuje u rosteru: stranica natjecanja → Ekipe → „N igr.".
 */

$tag_cls = fn( string $league, string $division ) => Kurs_Season::is_pan_league( $league ) ? 'pan' : ( stripos( $division, 'briga' ) !== false ? 'lbb' : 'kc' );
$edit_url = fn( int $id ) => admin_url( 'admin.php?page=kurs-players&action=edit&id=' . $id );

$action  = sanitize_key( $_GET['action'] ?? '' );
$edit_id = absint( $_GET['id'] ?? 0 );

// ── PODACI IGRAČA ───────────────────────────────────────────────────────────
if ( $action === 'edit' && $edit_id ) {
	$player = get_post( $edit_id );
	if ( ! $player || $player->post_type !== 'kurs_igrac' ) wp_die( 'Igrač ne postoji.' );

	$type      = kurs_player_type( $edit_id );
	$birthyear = (int) get_post_meta( $edit_id, '_kurs_player_birthyear', true );
	$photo     = get_the_post_thumbnail_url( $edit_id, 'medium' );
	$career    = Kurs_Match::player_career( $edit_id );
	$history   = Kurs_Players::has_history( $edit_id );
	$same_name = Kurs_Players::same_name( $edit_id );
	$others    = get_posts( [ 'post_type' => 'kurs_igrac', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish', 'exclude' => array_merge( [ $edit_id ], $same_name ) ] );
	$totals    = [ 'apps' => array_sum( array_column( $career, 'matches_played' ) ), 'goals' => array_sum( array_column( $career, 'goals' ) ) ];
	$year_max  = (int) current_time( 'Y' ) - 5;
	?>
	<div class="wrap kurs-wrap">

		<div class="kurs-breadcrumb">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga</a>
			<span class="kurs-breadcrumb__sep">/</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-players' ) ); ?>">Igrači</a>
			<span class="kurs-breadcrumb__sep">/</span>
			<?php echo esc_html( $player->post_title ); ?>
		</div>

		<div class="kurs-page-hd">
			<div class="kurs-player-hd">
				<span class="kurs-player-photo kurs-player-photo--sm"><?php if ( $photo ) : ?><img src="<?php echo esc_url( $photo ); ?>" alt=""><?php else : echo esc_html( mb_strtoupper( mb_substr( $player->post_title, 0, 1 ) ) ); endif; ?></span>
				<div>
					<h1 style="margin:0;"><?php echo esc_html( $player->post_title ); ?></h1>
					<div class="kurs-muted"><?php echo esc_html( kurs_player_type_label( $type ) . ( $birthyear ? ' · rođ. ' . $birthyear : '' ) . ' · ' . $totals['apps'] . ' nastupa · ' . $totals['goals'] . ' golova' ); ?></div>
				</div>
			</div>
			<div style="display:flex;gap:6px;">
				<a href="<?php echo esc_url( get_permalink( $edit_id ) ); ?>" class="kurs-btn kurs-btn--ghost kurs-btn--sm" target="_blank" rel="noopener">Javni profil &#8599;</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-players' ) ); ?>" class="kurs-btn kurs-btn--ghost kurs-btn--sm">&larr; Svi igrači</a>
			</div>
		</div>

		<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="kurs-notice kurs-notice--ok">&#10003; Podaci igrača su spremljeni.</div>
		<?php elseif ( ! empty( $_GET['created'] ) ) : ?>
		<div class="kurs-notice kurs-notice--ok">&#10003; Igrač je dodan. U ekipu ga upiši u rosteru ekipe (stranica natjecanja → Ekipe → „N igr.").</div>
		<?php elseif ( ! empty( $_GET['merged'] ) ) : ?>
		<div class="kurs-notice kurs-notice--ok">&#10003; Spojeno: „<?php echo esc_html( wp_unslash( $_GET['merged'] ) ); ?>" je obrisan, a njegove utakmice, golovi i rosteri sada su ovog igrača.</div>
		<?php elseif ( ! empty( $_GET['merge_error'] ) ) : ?>
		<div class="kurs-notice kurs-notice--err">Igrači nisu spojeni: <?php echo esc_html( wp_unslash( $_GET['merge_error'] ) ); ?></div>
		<?php elseif ( ! empty( $_GET['delete_error'] ) ) : ?>
		<div class="kurs-notice kurs-notice--err"><?php echo esc_html( wp_unslash( $_GET['delete_error'] ) ); ?></div>
		<?php endif; ?>

		<div class="kurs-team-edit-layout">

			<!-- ── Lijevo: podaci ─────────────────────────────────────────────── -->
			<div>
				<div class="kurs-panel">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Osnovno</h3></div>
					<div class="kurs-panel__body" style="padding:18px;">
						<form method="post" class="kurs-form-col" style="gap:12px;">
							<?php wp_nonce_field( 'kurs_edit_player_' . $edit_id ); ?>
							<input type="hidden" name="kurs_action" value="edit_player">
							<input type="hidden" name="player_id"   value="<?php echo (int) $edit_id; ?>">
							<label class="kurs-field-label">Ime i prezime
								<input type="text" name="player_name" class="regular-text" style="width:100%;" value="<?php echo esc_attr( $player->post_title ); ?>" required>
							</label>
							<div class="kurs-player-fields">
								<label class="kurs-field-label">Pozicija
									<select name="player_type" class="kurs-sel" style="width:100%;">
										<option value="player"<?php selected( $type, 'player' ); ?>>Igrač</option>
										<option value="goalkeeper"<?php selected( $type, 'goalkeeper' ); ?>>Golman</option>
									</select>
								</label>
								<label class="kurs-field-label">Godina rođenja
									<input type="number" name="birthyear" class="regular-text" style="width:100%;" min="1940" max="<?php echo (int) $year_max; ?>" placeholder="npr. 1994" value="<?php echo $birthyear ? (int) $birthyear : ''; ?>">
								</label>
							</div>
							<p class="kurs-muted" style="margin:0;font-size:11px;">Na javnom profilu prikazuje se dob (iz godine rođenja). Broj dresa se vodi u rosteru, po ligi.</p>
							<button type="submit" class="kurs-btn kurs-btn--red">Spremi</button>
						</form>
					</div>
				</div>

				<div class="kurs-panel" style="margin-top:16px;">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Fotografija</h3></div>
					<div class="kurs-panel__body" style="padding:18px;">
						<div style="display:flex;align-items:center;gap:16px;">
							<span class="kurs-player-photo"><?php if ( $photo ) : ?><img src="<?php echo esc_url( $photo ); ?>" alt=""><?php else : echo esc_html( mb_strtoupper( mb_substr( $player->post_title, 0, 1 ) ) ); endif; ?></span>
							<div>
								<button type="button" class="kurs-btn kurs-btn--outline kurs-btn--sm kurs-player-photo-btn" data-player-id="<?php echo (int) $edit_id; ?>">&#128247; <?php echo $photo ? 'Zamijeni' : 'Postavi fotografiju'; ?></button>
								<?php if ( $photo ) : ?>
								<form method="post" style="display:inline;margin-left:6px;">
									<?php wp_nonce_field( 'kurs_remove_player_photo_' . $edit_id ); ?>
									<input type="hidden" name="kurs_action" value="remove_player_photo">
									<input type="hidden" name="player_id"   value="<?php echo (int) $edit_id; ?>">
									<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm" onclick="return confirm('Ukloniti fotografiju?')">&#215; Ukloni</button>
								</form>
								<?php endif; ?>
								<p class="kurs-muted" style="margin:8px 0 0;font-size:12px;">Portret (uspravna ili kvadratna slika). Prikazuje se na javnom profilu igrača.</p>
							</div>
						</div>
					</div>
				</div>

				<div class="kurs-panel kurs-panel--danger" style="margin-top:16px;">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Brisanje</h3></div>
					<div class="kurs-panel__body" style="padding:14px 18px;">
						<?php if ( $history ) : ?>
						<p class="kurs-muted" style="margin:0;font-size:12px;">Igrač ima odigrane utakmice pa se ne briše — to bi promijenilo zapisnike i statistiku. Ako je upisan dvaput, spoji ga s drugim zapisom (desno).</p>
						<?php else : ?>
						<form method="post" onsubmit="return confirm('Obrisati igrača <?php echo esc_js( $player->post_title ); ?>? Briše se i iz svih rostera.');">
							<?php wp_nonce_field( 'kurs_delete_player_' . $edit_id ); ?>
							<input type="hidden" name="kurs_action" value="delete_player">
							<input type="hidden" name="player_id"   value="<?php echo (int) $edit_id; ?>">
							<p class="kurs-muted" style="margin:0 0 8px;font-size:12px;">Igrač još nije odigrao nijednu utakmicu i može se obrisati (npr. pogrešan unos).</p>
							<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm" style="color:#c00 !important;border-color:#c00 !important;">&times; Obriši igrača</button>
						</form>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- ── Desno: karijera + spajanje ────────────────────────────────── -->
			<div>
				<div class="kurs-panel">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Karijera</h3><span class="kurs-muted" style="font-size:11px;">iz zapisnika odigranih utakmica</span></div>
					<?php if ( ! $career ) : ?>
					<div class="kurs-panel__empty">Igrač nije ni u jednom rosteru i nema odigranih utakmica.</div>
					<?php else : ?>
					<table class="widefat kurs-career-table">
						<thead><tr><th>Sezona</th><th>Liga</th><th>Ekipa</th><th title="Broj dresa">#</th><th title="Nastupi">Nast.</th><th title="Golovi">Gol</th><th title="Žuti kartoni">Ž</th><th title="Crveni kartoni">C</th></tr></thead>
						<tbody>
						<?php foreach ( $career as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r->season_name ); ?></td>
							<td><span class="kurs-league-tag kurs-league-tag--<?php echo esc_attr( $tag_cls( (string) $r->league, (string) $r->division_name ) ); ?>"><?php echo esc_html( $r->division_name ); ?></span></td>
							<td>
								<?php echo kurs_team_badge_html( (int) $r->team_id, 18 ); ?>
								<?php if ( $r->in_roster ) : ?>
								<a href="<?php echo esc_url( Kurs_Admin::roster_url( (int) $r->team_id, (int) $r->division_id ) ); ?>" title="Roster ekipe u ovoj ligi"><?php echo esc_html( $r->team_name ); ?></a>
								<?php else : ?>
								<?php echo esc_html( $r->team_name ); ?> <span class="kurs-muted" title="Više nije u rosteru ove ekipe — odigrane utakmice ostaju">(otišao)</span>
								<?php endif; ?>
							</td>
							<td><?php echo $r->jersey_no ? (int) $r->jersey_no : '—'; ?></td>
							<td><?php echo (int) $r->matches_played; ?></td>
							<td><strong><?php echo (int) $r->goals; ?></strong></td>
							<td><?php echo (int) $r->yellows; ?></td>
							<td><?php echo (int) $r->reds; ?></td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>
				</div>

				<div class="kurs-panel" style="margin-top:16px;">
					<div class="kurs-panel__head"><h3 class="kurs-panel__title">Spoji dvostruki upis</h3></div>
					<div class="kurs-panel__body" style="padding:14px 18px;">
						<p class="kurs-muted" style="margin:0 0 10px;font-size:12px;">
							Ako je isti igrač upisan dvaput (npr. „Ivan Horvat" i „Ivan Horvat ml."), odaberi drugi zapis: njegove utakmice, golovi,
							kartoni i rosteri prelaze na <strong><?php echo esc_html( $player->post_title ); ?></strong>, a drugi zapis se briše.
							Igrači koji u istoj ligi igraju za različite ekipe ne mogu se spojiti.
						</p>
						<form method="post" class="kurs-merge-form" onsubmit="var s=this.merge_from;return s.value!=='' && confirm('Spojiti „'+s.options[s.selectedIndex].text+'“ u ovog igrača? Drugi zapis se briše — ovo se ne može poništiti.');">
							<?php wp_nonce_field( 'kurs_merge_players_' . $edit_id ); ?>
							<input type="hidden" name="kurs_action" value="merge_players">
							<input type="hidden" name="player_id"   value="<?php echo (int) $edit_id; ?>">
							<select name="merge_from" class="kurs-sel" required>
								<option value="">— Drugi zapis istog igrača —</option>
								<?php if ( $same_name ) : ?>
								<optgroup label="Isto ime">
									<?php foreach ( $same_name as $sid ) : ?>
									<option value="<?php echo (int) $sid; ?>"><?php echo esc_html( get_the_title( $sid ) . ' (#' . $sid . ')' ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php endif; ?>
								<optgroup label="Svi igrači">
									<?php foreach ( $others as $o ) : ?>
									<option value="<?php echo (int) $o->ID; ?>"><?php echo esc_html( $o->post_title ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							</select>
							<button type="submit" class="kurs-btn kurs-btn--outline kurs-btn--sm">Spoji u ovog igrača</button>
						</form>
					</div>
				</div>
			</div>

		</div>
	</div>
	<?php
	return;
}

// ── POPIS IGRAČA ────────────────────────────────────────────────────────────
global $wpdb;
$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$filter   = sanitize_key( $_GET['filter'] ?? '' );
$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 50;

$playing  = Kurs_Players::current_teams();
$dupes    = Kurs_Players::duplicate_groups();
$dupe_ids = $dupes ? array_merge( ...array_values( $dupes ) ) : [];
$all_n    = (int) wp_count_posts( 'kurs_igrac' )->publish;

$args = [ 'post_type' => 'kurs_igrac', 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC', 'posts_per_page' => $per_page, 'paged' => $paged ];
if ( $search !== '' ) $args['s'] = $search;
if ( $filter === 'sada' )      $args['post__in']     = array_keys( $playing ) ?: [ 0 ];
if ( $filter === 'bez' )       $args['post__not_in'] = array_keys( $playing );
if ( $filter === 'duplikati' ) $args['post__in']     = $dupe_ids ?: [ 0 ];
$q       = new WP_Query( $args );
$ids     = wp_list_pluck( $q->posts, 'ID' );
$totals  = Kurs_Players::totals( $ids );
$f_url   = fn( string $f ) => add_query_arg( array_filter( [ 'page' => 'kurs-players', 'filter' => $f, 's' => $search ] ), admin_url( 'admin.php' ) );
?>
<div class="wrap kurs-wrap">

	<div class="kurs-breadcrumb">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-dashboard' ) ); ?>">KURS Liga</a>
		<span class="kurs-breadcrumb__sep">/</span> Igrači
	</div>
	<div class="kurs-page-hd"><h1>Igrači</h1></div>

	<p class="kurs-hub-intro">
		<strong>Popis svih igrača.</strong> Igrač se upisuje jednom i igra više sezona i u oba natjecanja. U ekipu ga upisuješ u <strong>rosteru</strong>
		(stranica natjecanja → Ekipe → „N igr."), zajedno s brojem dresa. Ovdje uređuješ podatke igrača (pozicija, godina rođenja, fotografija)
		i spajaš igrače upisane dvaput.
	</p>

	<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="kurs-notice kurs-notice--ok">&#10003; Igrač je obrisan.</div>
	<?php elseif ( ! empty( $_GET['exists'] ) && get_post_type( absint( $_GET['exists'] ) ) === 'kurs_igrac' ) : ?>
	<div class="notice notice-warning is-dismissible"><p>Igrač „<?php echo esc_html( get_the_title( absint( $_GET['exists'] ) ) ); ?>" već postoji — nije dodan ponovno.
		<a href="<?php echo esc_url( $edit_url( absint( $_GET['exists'] ) ) ); ?>">Otvori ga &rarr;</a></p></div>
	<?php endif; ?>

	<div class="kurs-two-col">
		<div>
			<div class="kurs-panel">
				<div class="kurs-panel__head kurs-players-hd">
					<nav class="kurs-hub-sub kurs-hub-sub--inline" aria-label="Filter">
						<a href="<?php echo esc_url( $f_url( '' ) ); ?>" class="<?php echo $filter === '' ? 'is-active' : ''; ?>">Svi <span><?php echo (int) $all_n; ?></span></a>
						<a href="<?php echo esc_url( $f_url( 'sada' ) ); ?>" class="<?php echo $filter === 'sada' ? 'is-active' : ''; ?>">Igraju sada <span><?php echo count( $playing ); ?></span></a>
						<a href="<?php echo esc_url( $f_url( 'bez' ) ); ?>" class="<?php echo $filter === 'bez' ? 'is-active' : ''; ?>">Bez ekipe <span><?php echo max( 0, $all_n - count( $playing ) ); ?></span></a>
						<?php if ( $dupe_ids ) : ?>
						<a href="<?php echo esc_url( $f_url( 'duplikati' ) ); ?>" class="<?php echo $filter === 'duplikati' ? 'is-active' : ''; ?>" title="Isto ime upisano više puta">Isto ime <span><?php echo count( $dupe_ids ); ?></span></a>
						<?php endif; ?>
					</nav>
					<form method="get" class="kurs-players-search" role="search">
						<input type="hidden" name="page" value="kurs-players">
						<?php if ( $filter ) : ?><input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>"><?php endif; ?>
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Traži igrača…" aria-label="Traži igrača">
					</form>
				</div>

				<?php if ( ! $q->posts ) : ?>
				<div class="kurs-panel__empty"><?php echo $search !== '' ? 'Nema igrača za „' . esc_html( $search ) . '".' : 'Nema igrača za ovaj filter.'; ?></div>
				<?php else : ?>
				<table class="widefat kurs-players-table">
					<thead><tr><th>Igrač</th><th>Rođ.</th><th>Igra sada</th><th title="Nastupi (odigrane utakmice)">Nast.</th><th title="Golovi">Gol</th></tr></thead>
					<tbody>
					<?php foreach ( $q->posts as $pl ) :
						$pid = (int) $pl->ID;
						$by  = (int) get_post_meta( $pid, '_kurs_player_birthyear', true );
					?>
					<tr>
						<td>
							<a class="kurs-players-table__name" href="<?php echo esc_url( $edit_url( $pid ) ); ?>"><?php echo esc_html( $pl->post_title ); ?></a>
							<?php if ( kurs_player_type( $pid ) === 'goalkeeper' ) : ?><span class="kurs-pos-tag kurs-pos-tag--gk">GLM</span><?php endif; ?>
							<?php if ( in_array( $pid, $dupe_ids, true ) ) : ?><span class="kurs-dupe-tag" title="Postoji još igrač istog imena — provjeri je li isti i spoji ga">isto ime</span><?php endif; ?>
						</td>
						<td class="kurs-muted"><?php echo $by ?: '—'; ?></td>
						<td>
							<?php foreach ( $playing[ $pid ] ?? [] as $c ) : ?>
							<a class="kurs-league-tag kurs-league-tag--<?php echo esc_attr( $tag_cls( (string) $c->league, (string) $c->division_name ) ); ?>"
							   href="<?php echo esc_url( Kurs_Admin::roster_url( (int) $c->team_id, (int) $c->division_id ) ); ?>"
							   title="<?php echo esc_attr( $c->division_name . ( $c->jersey_no ? ' · #' . (int) $c->jersey_no : '' ) ); ?>"><?php echo esc_html( $c->team_name ); ?></a>
							<?php endforeach; ?>
							<?php if ( empty( $playing[ $pid ] ) ) : ?><span class="kurs-muted">—</span><?php endif; ?>
						</td>
						<td><?php echo (int) ( $totals[ $pid ]->apps ?? 0 ); ?></td>
						<td><strong><?php echo (int) ( $totals[ $pid ]->goals ?? 0 ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $q->max_num_pages > 1 ) : ?>
				<div class="kurs-pager">
					<?php echo paginate_links( [
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $paged,
						'total'     => (int) $q->max_num_pages,
						'prev_text' => '&larr;',
						'next_text' => '&rarr;',
					] ); ?>
				</div>
				<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<div>
			<div class="kurs-panel">
				<div class="kurs-panel__head"><h3 class="kurs-panel__title">Novi igrač</h3></div>
				<div class="kurs-panel__body" style="padding:18px;">
					<form method="post" class="kurs-form-col" style="gap:12px;">
						<?php wp_nonce_field( 'kurs_add_player' ); ?>
						<input type="hidden" name="kurs_action" value="add_player">
						<label class="kurs-field-label">Ime i prezime
							<input type="text" name="player_name" class="regular-text" style="width:100%;" required>
						</label>
						<div class="kurs-player-fields">
							<label class="kurs-field-label">Pozicija
								<select name="player_type" class="kurs-sel" style="width:100%;">
									<option value="player">Igrač</option>
									<option value="goalkeeper">Golman</option>
								</select>
							</label>
							<label class="kurs-field-label">Godina rođenja
								<input type="number" name="birthyear" class="regular-text" style="width:100%;" min="1940" max="<?php echo (int) current_time( 'Y' ) - 5; ?>" placeholder="neobavezno">
							</label>
						</div>
						<button type="submit" class="kurs-btn kurs-btn--red">+ Dodaj igrača</button>
					</form>
					<p class="kurs-muted" style="margin:12px 0 0;font-size:12px;">Igrače obično dodaješ ravno u roster ekipe; ovdje samo ako ga želiš upisati unaprijed.</p>
				</div>
			</div>
		</div>
	</div>
</div>
