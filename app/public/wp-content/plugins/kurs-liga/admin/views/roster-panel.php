<?php
defined( 'ABSPATH' ) || exit;

/*
 * Roster ekipe u jednoj ligi (igrač × ekipa × liga): popis s brojevima dresova, uređivanje,
 * dodavanje postojećeg ili novog igrača i prijelaz iz druge ekipe iste lige.
 * Ulaz: $roster_team_id, $roster_division_id, $roster_locked (zaključana sezona = samo pregled).
 * Prikazuje ga stranica natjecanja → Ekipe → „N igr." (hub-teams.php). JS: admin.js „Roster".
 */

$squad     = Kurs_Season::get_squad( $roster_team_id, $roster_division_id );
$squad_ids = array_map( 'intval', array_column( $squad, 'player_id' ) );

// Igrači koji u ovoj ligi već igraju za drugu ekipu — dodavanje je prijelaz (uz potvrdu).
global $wpdb;
$elsewhere = [];
foreach ( $wpdb->get_results( $wpdb->prepare(
	"SELECT sq.player_id, t.post_title AS team_name FROM {$wpdb->prefix}" . KURS_PREFIX . "squads sq
	 JOIN {$wpdb->posts} t ON t.ID = sq.team_id WHERE sq.division_id = %d AND sq.team_id <> %d",
	$roster_division_id, $roster_team_id
) ) as $r ) $elsewhere[ (int) $r->player_id ] = $r->team_name;

$available = $roster_locked ? [] : array_filter(
	get_posts( [ 'post_type' => 'kurs_igrac', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] ),
	fn( $p ) => ! in_array( (int) $p->ID, $squad_ids, true )
);
$players_url = fn( int $pid ) => admin_url( 'admin.php?page=kurs-players&action=edit&id=' . $pid );
?>
<div class="kurs-panel kurs-roster">
	<div class="kurs-panel__head">
		<h3 class="kurs-panel__title">Igrači <span class="kurs-muted kurs-roster__n"><?php echo count( $squad ); ?></span></h3>
		<span class="kurs-muted" style="font-size:11px;">Odigrane utakmice čuvaju svoju postavu — prijelaz ili odlazak igrača ne mijenja ih.</span>
	</div>
	<div class="kurs-panel__body" style="padding:0 0 4px;">

		<div id="kurs-roster-ctx" data-team-id="<?php echo (int) $roster_team_id; ?>" data-division-id="<?php echo (int) $roster_division_id; ?>" style="display:none;"></div>

		<p class="kurs-muted" id="squad-empty-msg" style="padding:14px 18px;<?php echo $squad ? 'display:none;' : ''; ?>">Nema prijavljenih igrača za ovu ligu.</p>

		<table class="widefat kurs-roster-table" id="kurs-roster-table"<?php echo $squad ? '' : ' style="display:none;"'; ?>>
			<thead>
				<tr>
					<th style="width:52px;text-align:center;">Dres</th>
					<th>Igrač</th>
					<?php if ( ! $roster_locked ) : ?><th style="width:130px;text-align:center;">Akcije</th><?php endif; ?>
				</tr>
			</thead>
			<tbody id="kurs-squad-list">
				<?php foreach ( $squad as $sq ) :
					$type = kurs_player_type( (int) $sq->player_id ); ?>
				<tr id="squad-row-<?php echo (int) $sq->player_id; ?>"
				    data-player-id="<?php echo (int) $sq->player_id; ?>"
				    data-name="<?php echo esc_attr( $sq->player_name ); ?>"
				    data-jersey="<?php echo (int) $sq->jersey_no; ?>"
				    data-base="<?php echo (int) $sq->base_goals; ?>"
				    data-type="<?php echo esc_attr( $type ); ?>">
					<td class="squad-cell-jersey" style="text-align:center;">
						<span class="kurs-jersey"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : '&mdash;'; ?></span>
					</td>
					<td class="squad-cell-name">
						<strong><?php echo esc_html( $sq->player_name ); ?></strong><?php if ( $type === 'goalkeeper' ) : ?> <span class="kurs-pos-tag kurs-pos-tag--gk">GLM</span><?php endif; ?>
						<?php if ( (int) $sq->base_goals ) : ?><span class="kurs-base-tag" title="Golovi iz kola koja nisu u sustavu (preneseno stanje)">+<?php echo (int) $sq->base_goals; ?> gol.</span><?php endif; ?>
						<a class="kurs-roster__profile" href="<?php echo esc_url( $players_url( (int) $sq->player_id ) ); ?>" title="Podaci igrača (godina rođenja, fotografija, karijera)">&#8599;</a>
					</td>
					<?php if ( ! $roster_locked ) : ?>
					<td class="squad-actions" style="text-align:center;">
						<button class="button button-small kurs-btn-edit-squad" data-player-id="<?php echo (int) $sq->player_id; ?>">Uredi</button>
						<button class="button button-small kurs-btn-rm-squad" data-player-id="<?php echo (int) $sq->player_id; ?>" title="Makni iz rostera">&times;</button>
					</td>
					<?php endif; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! $roster_locked ) : ?>
		<div style="padding:14px 18px;border-top:1px solid var(--ka-border);">
			<div class="kurs-stack-mobile" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

				<!-- Postojeći igrač (iz druge ekipe iste lige = prijelaz) -->
				<div>
					<div class="kurs-field-label" style="margin-bottom:6px;">Dodaj postojećeg igrača</div>
					<p class="kurs-muted" id="available-empty-msg" style="font-size:12px;<?php echo $available ? 'display:none;' : ''; ?>">Svi igrači su već u rosteru.</p>
					<div id="add-existing-form"<?php echo $available ? '' : ' style="display:none;"'; ?>>
						<select id="squad-player-select" class="kurs-sel" style="width:100%;margin-bottom:6px;">
							<option value="">— Odaberi igrača —</option>
							<?php foreach ( $available as $pl ) : ?>
							<option value="<?php echo (int) $pl->ID; ?>"<?php echo isset( $elsewhere[ $pl->ID ] ) ? ' data-team="' . esc_attr( $elsewhere[ $pl->ID ] ) . '"' : ''; ?>><?php
								echo esc_html( $pl->post_title . ( isset( $elsewhere[ $pl->ID ] ) ? ' — sada: ' . $elsewhere[ $pl->ID ] : '' ) );
							?></option>
							<?php endforeach; ?>
						</select>
						<div style="display:flex;gap:8px;align-items:flex-end;">
							<div>
								<label class="kurs-field-label" for="squad-jersey" style="display:block;margin-bottom:4px;">Broj dresa</label>
								<div class="kurs-jersey-field" title="Broj na dresu (0–99)">
									<span class="kurs-jersey-field__hash">#</span>
									<input type="number" id="squad-jersey" placeholder="—" min="0" max="99">
								</div>
							</div>
							<button class="kurs-btn kurs-btn--red kurs-btn--sm" id="btn-add-squad">+ Dodaj</button>
						</div>
						<p class="kurs-muted" style="margin:6px 0 0;font-size:11px;">„sada: …" = igra za drugu ekipu ove lige — dodavanje ga prebacuje (prijelaz).</p>
					</div>
				</div>

				<!-- Novi igrač -->
				<div>
					<div class="kurs-field-label" style="margin-bottom:6px;">Kreiraj novog igrača</div>
					<div class="kurs-ac-wrap" style="margin-bottom:6px;">
						<input type="text" id="new-player-name" class="kurs-field-input" placeholder="Ime i prezime" autocomplete="off" style="width:100%;">
					</div>
					<select id="new-player-type" class="kurs-sel" style="width:100%;margin-bottom:6px;">
						<option value="player">Igrač</option>
						<option value="goalkeeper">Golman</option>
					</select>
					<div style="display:flex;gap:8px;align-items:flex-end;">
						<div>
							<label class="kurs-field-label" for="new-player-jersey" style="display:block;margin-bottom:4px;">Broj dresa</label>
							<div class="kurs-jersey-field" title="Broj na dresu (0–99)">
								<span class="kurs-jersey-field__hash">#</span>
								<input type="number" id="new-player-jersey" placeholder="—" min="0" max="99">
							</div>
						</div>
						<button class="kurs-btn kurs-btn--red kurs-btn--sm" id="btn-quick-add">+ Kreiraj i dodaj</button>
					</div>
					<p id="quick-add-msg" class="kurs-muted" style="margin-top:4px;font-size:11px;display:none;"></p>
				</div>

			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php if ( ! $roster_locked ) : ?>
<!-- Duplikat modal (igrač istog imena već postoji) -->
<div id="kurs-dup-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="kurs-dup-title">
	<div class="kurs-modal-backdrop" id="kurs-dup-backdrop"></div>
	<div class="kurs-modal-box">
		<h3 class="kurs-modal-title" id="kurs-dup-title">&#9888; Igrač već postoji</h3>
		<p id="kurs-dup-msg" class="kurs-modal-msg"></p>
		<p class="kurs-muted">Što želiš napraviti?</p>
		<div class="kurs-modal-actions">
			<button id="btn-dup-add-existing" class="button button-primary">Dodaj postojećeg u roster</button>
			<button id="btn-dup-create-new"   class="button">Kreiraj novog igrača</button>
			<button id="btn-dup-cancel"        class="button button-link">Odustani</button>
		</div>
	</div>
</div>
<?php endif; ?>
