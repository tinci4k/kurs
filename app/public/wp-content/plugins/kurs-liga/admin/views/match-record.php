<?php
defined( 'ABSPATH' ) || exit;

$match_id = absint( $_GET['match_id'] ?? 0 );
if ( ! $match_id ) wp_die( 'Nevažeći ID utakmice.' );

$match = Kurs_Match::get( $match_id );
if ( ! $match ) wp_die( 'Utakmica ne postoji.' );

global $wpdb;
$p = $wpdb->prefix . KURS_PREFIX;

// Playoff meč ima group_id=0 — dohvati info iz playoff_matches tablice
$is_playoff = ( (int) $match->group_id === 0 );
$playoff_m  = $is_playoff ? Kurs_Playoff::find_by_match_id( $match_id ) : null;

$info = null;
if ( ! $is_playoff ) {
	$info = $wpdb->get_row( $wpdb->prepare(
		"SELECT g.name AS group_name, ph.name AS phase_name,
		        d.id AS division_id, d.name AS div_name,
		        s.id AS season_id, s.name AS season_name
		 FROM   {$p}groups    g
		 JOIN   {$p}phases   ph ON ph.id = g.phase_id
		 JOIN   {$p}divisions d  ON d.id  = ph.division_id
		 JOIN   {$p}seasons   s  ON s.id  = d.season_id
		 WHERE  g.id = %d",
		$match->group_id
	) );
}

$division_id = $info ? (int) $info->division_id : ( $playoff_m ? (int) $playoff_m->division_id : 0 );
$season_id   = $info ? (int) $info->season_id   : 0;
if ( ! $season_id && $division_id ) {
	$season_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT season_id FROM {$p}divisions WHERE id = %d", $division_id
	) );
}

$season_locked = Kurs_Season::is_locked( $season_id ); // konačni podaci — server odbija izmjene, sučelje ih skriva

// Sastav: spremljena postava utakmice (od završetka), a dotad trenutni roster lige.
$home_squad  = $division_id ? Kurs_Match::lineup( $match_id, (int) $match->home_team_id, $division_id ) : [];
$away_squad  = $division_id ? Kurs_Match::lineup( $match_id, (int) $match->away_team_id, $division_id ) : [];
$events      = Kurs_Match::get_events( $match_id );

$ev_home = 0; $ev_away = 0;
foreach ( $events as $ev ) {
	if ( $ev->event_type === 'goal' || $ev->event_type === 'penalty' ) {
		if ( (int) $ev->team_id === (int) $match->home_team_id ) $ev_home++; else $ev_away++;
	} elseif ( $ev->event_type === 'own_goal' ) {
		if ( (int) $ev->team_id === (int) $match->home_team_id ) $ev_away++; else $ev_home++;
	}
}

// Rezultat za prikaz: završena utakmica → spremljeni rezultat, inače živi rezultat
// iz unesenih golova. MORA biti definirano PRIJE PDF includea jer ga koristi i
// match-record-pdf.php (inače "Undefined variable $disp_home/$disp_away/$is_finished").
$is_finished = $match->status === 'finished';
$disp_home   = $is_finished ? (int) $match->home_score : $ev_home;
$disp_away   = $is_finished ? (int) $match->away_score : $ev_away;

// PDF format — standalone page
if ( isset( $_GET['format'] ) && $_GET['format'] === 'pdf' ) {
	include __DIR__ . '/match-record-pdf.php';
	exit;
}

// /zapisnik/ (Kurs_Zapisnik): isti zapisnik izvan wp-admina — povratak na popis utakmica, bez admin linkova.
$kurs_front = ! empty( $GLOBALS['kurs_record_front'] );
$can_edit_match = current_user_can( KURS_CAP ); // datum/sat/kolo mijenja samo administracija lige

$back_url = $kurs_front ? Kurs_Zapisnik::url() : Kurs_Admin::hub_url( $season_id, 'utakmice', $is_playoff
	? [ 'playoff_div' => $division_id ]
	: [ 'group_id' => (int) $match->group_id ] );
$back_season = $season_id ? Kurs_Season::get( $season_id ) : null;
$pdf_url  = $kurs_front
	? Kurs_Zapisnik::url( $match_id, [ 'format' => 'pdf' ] )
	: admin_url( "admin.php?page=kurs-match-record&match_id={$match_id}&format=pdf" );
$date_val = $match->match_date ? date( 'd.m.Y H:i', strtotime( $match->match_date ) ) : '';

$home_color = kurs_team_color( (int) $match->home_team_id );
// Dugi nazivi („AL Nemčić/Klesarstvo Habek”) lome se iza kose crte, ne usred riječi.
$wbr_name = fn( string $n ): string => str_replace( '/', '/<wbr>', esc_html( $n ) );
// Boja ekipe za traku/oznake; preblijeda (bijela) bi se izgubila na bijelom → siva.
$rc_tone = fn( string $c ): string => kurs_team_text_color( $c ) === '#ffffff' ? $c : '#c9c9c0';
$away_color = kurs_team_color( (int) $match->away_team_id );
// $is_finished / $disp_home / $disp_away su izračunati ranije (prije PDF includea).

// ── Akumulirani prekršaji + isključeni igrači + max faulova (postavka sezone) ──
$season      = $season_id ? Kurs_Season::get( $season_id ) : null;
$max_fouls   = $season ? max( 1, (int) ( $season->max_fouls_per_half ?? 5 ) ) : 5;
$foul_counts = Kurs_Match::get_foul_counts( $match_id );
$sent_off    = array_map( 'intval', Kurs_Match::get_sent_off( $match_id ) );
$home_captain = Kurs_Match::get_match_captain( $match_id, (int) $match->home_team_id );
$away_captain = Kurs_Match::get_match_captain( $match_id, (int) $match->away_team_id );
$absent_ids   = Kurs_Match::get_absent( $match_id );
$fc_home     = $foul_counts[ (int) $match->home_team_id ] ?? [ 1 => 0, 2 => 0 ];
$fc_away     = $foul_counts[ (int) $match->away_team_id ] ?? [ 1 => 0, 2 => 0 ];
$cur_half    = 1; // zadnje korišteno poluvrijeme = poluvrijeme zadnjeg upisanog događaja
$last_ev_id  = 0;
foreach ( $events as $ev ) {
	if ( (int) $ev->id > $last_ev_id ) { $last_ev_id = (int) $ev->id; $cur_half = ( (int) $ev->half === 2 ) ? 2 : 1; }
}
// Utakmica otvorena za ispravak čuva postavu spremljenu pri završetku (promjene rostera je ne mijenjaju);
// igrača iz rostera koji je izostavljen operater može dodati u postavu.
$lineup_add_html = function ( int $team_id, array $lineup ) use ( $match_id, $division_id, $is_finished, $season_locked ): string {
	if ( $is_finished || $season_locked || ! $division_id || ! Kurs_Match::has_lineup( $match_id, $team_id ) ) return '';
	$in   = array_map( fn( $r ) => (int) $r->player_id, $lineup );
	$miss = array_filter( Kurs_Season::get_squad( $team_id, $division_id ), fn( $s ) => ! in_array( (int) $s->player_id, $in, true ) );
	$html = '<p class="kurs-lineup-note no-print">Postava je spremljena pri završetku utakmice — promjene rostera na nju ne utječu.</p>';
	if ( ! $miss ) return $html;
	$html .= '<div class="kurs-lineup-add no-print"><select class="kurs-lineup-add__sel" aria-label="Igrač iz rostera"><option value="">Igrač iz rostera…</option>';
	foreach ( $miss as $s ) {
		$html .= '<option value="' . (int) $s->player_id . '">' . ( $s->jersey_no ? (int) $s->jersey_no . ' · ' : '' ) . esc_html( $s->player_name ) . '</option>';
	}
	return $html . '</select><button type="button" class="kurs-btn kurs-btn--outline kurs-btn--xs kurs-lineup-add__btn" data-team-id="' . $team_id . '">+ Dodaj u postavu</button></div>';
};
// Prekršaji se NE prikazuju u tijeku utakmice (samo kao brojač) — filtriraj ih.
$timeline_events = array_values( array_filter( $events, fn( $e ) => ! in_array( $e->event_type, [ 'foul', 'captain', 'absent' ], true ) ) );
?>
<div class="wrap kurs-wrap kurs-record-wrap" id="kurs-match-record"
     data-match-id="<?php echo $match_id; ?>"
     data-home-team="<?php echo (int) $match->home_team_id; ?>"
     data-away-team="<?php echo (int) $match->away_team_id; ?>">

	<!-- Gornja traka -->
	<div class="kurs-record-topbar no-print">
		<a href="<?php echo esc_url( $back_url ); ?>" class="kurs-back">&larr; <?php echo esc_html( $kurs_front ? 'Sve utakmice' : ( $back_season ? $back_season->name . ' · Utakmice' : 'Utakmice' ) ); ?></a>
		<div class="kurs-record-topbar__actions">
			<button class="kurs-btn kurs-btn--outline kurs-btn--sm" onclick="window.print()">
				&#128424; Ispiši
			</button>
			<a class="kurs-btn kurs-btn--dark kurs-btn--sm" id="btn-download-pdf" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">
				&#11015; Preuzmi PDF
			</a>
		</div>
	</div>

	<?php if ( $season_locked ) : ?>
	<div class="kurs-hub-locked no-print">&#128274; Sezona je <strong>zaključana</strong> — zapisnik je konačan i ne može se mijenjati.<?php if ( ! $kurs_front ) : ?> Otključati je možeš u <a href="<?php echo esc_url( Kurs_Admin::hub_url( $season_id, 'postavke' ) ); ?>">Postavkama natjecanja</a>.<?php endif; ?></div>
	<?php endif; ?>
	<?php if ( ( $_GET['pm_error'] ?? '' ) === 'draw' ) : ?>
	<div class="kurs-notice kurs-notice--err no-print">Utakmica nije zaključena: neriješeno je, a u playoffu mora biti pobjednik — upiši i rezultat penala.</div>
	<?php endif; ?>

	<!-- ── DOKUMENT ZAPISNIKA ─────────────────────────────────────────────── -->
	<div class="kurs-record-doc">

		<!-- Hero scoreboard -->
		<div class="kurs-record-hero">
			<div class="kurs-record-hero__stripes">
				<span style="background:#DA0F1E;"></span>
				<span style="background:#F47B16;"></span>
				<span style="background:#FDE206;"></span>
			</div>

			<?php
			$round_labels_rec = [ 'qf' => 'Četvrtfinale', 'sf' => 'Polufinale', 'final' => 'Finale', '3rd' => '3. mjesto' ];
			if ( $info ) : ?>
			<div class="kurs-record-hero__league">
				<?php echo esc_html( $info->season_name ); ?>
				<span class="kurs-record-hero__league-sep">/</span>
				<?php echo esc_html( $info->div_name ); ?>
				<span class="kurs-record-hero__league-sep">/</span>
				<?php echo esc_html( $info->group_name ); ?>
			</div>
			<?php elseif ( $is_playoff && $playoff_m ) :
				$div_info = $wpdb->get_row( $wpdb->prepare(
					"SELECT d.name AS div_name, s.name AS season_name FROM {$p}divisions d JOIN {$p}seasons s ON s.id=d.season_id WHERE d.id=%d",
					$playoff_m->division_id
				) ); ?>
			<div class="kurs-record-hero__league">
				<?php echo $div_info ? esc_html( $div_info->season_name . ' / ' . $div_info->div_name . ' / ' ) : ''; ?>
				<?php echo esc_html( $round_labels_rec[ $playoff_m->round ] ?? ucfirst( $playoff_m->round ) ); ?>
				#<?php echo (int) $playoff_m->slot; ?>
			</div>
			<?php endif; ?>

			<div class="kurs-record-hero__body">
				<!-- Domaćin -->
				<div class="kurs-record-hero__team kurs-record-hero__team--home">
					<?php echo kurs_team_badge_html( (int) $match->home_team_id, 72 ); ?>
					<div class="kurs-record-hero__team-name"><?php echo $wbr_name( $match->home_name ); ?></div>
					<div class="kurs-record-hero__team-role">Domaćin</div>
				</div>

				<!-- Score -->
				<div class="kurs-record-hero__score">
					<div class="kurs-record-hero__score-nums">
						<span class="kurs-record-hero__score-num" id="live-home"><?php echo $disp_home; ?></span>
						<span class="kurs-record-hero__score-sep">:</span>
						<span class="kurs-record-hero__score-num" id="live-away"><?php echo $disp_away; ?></span>
					</div>
					<div class="kurs-record-hero__status <?php echo $is_finished ? 'is-finished' : 'is-live'; ?>">
						<?php echo $is_finished ? '&#10003; ZAVRŠENO' : '&#9679; U TIJEKU'; ?>
					</div>
					<div class="kurs-record-hero__meta" id="record-date-display">
						<?php if ( $match->match_date ) :
							$ts = strtotime( $match->match_date );
						?>
						<span class="kurs-record-hero__date">
							<span class="kurs-match__dt-day"><?php echo date( 'j.n.Y', $ts ); ?></span>
							<span style="opacity:.5;margin:0 4px;">·</span>
							<span class="kurs-match__dt-time"><?php echo date( 'H:i', $ts ); ?></span>
						</span>
						<?php else : ?>
						<span class="kurs-match__dt-none">Datum nije postavljen</span>
						<?php endif; ?>
						<span class="kurs-record-hero__meta-sep">·</span>
						<span class="kurs-record-hero__kolo"><?php echo $is_playoff ? esc_html( kurs_round_label( null, $playoff_m->round ?? '' ) ) : 'Kolo <strong>' . (int) $match->round . '</strong>'; ?></span>
					</div>
				</div>

				<!-- Gost -->
				<div class="kurs-record-hero__team kurs-record-hero__team--away">
					<?php echo kurs_team_badge_html( (int) $match->away_team_id, 72 ); ?>
					<div class="kurs-record-hero__team-name"><?php echo $wbr_name( $match->away_name ); ?></div>
					<div class="kurs-record-hero__team-role">Gost</div>
				</div>
			</div>
		</div>

		<!-- Uredi podatke utakmice -->
		<?php if ( ! $season_locked && $can_edit_match ) : ?>
		<details class="kurs-record-edit-details no-print">
			<summary>&#9998; Uredi datum, sat<?php echo $is_playoff ? '' : ' i kolo'; ?></summary>
			<div class="kurs-record-edit-form">
				<label class="kurs-field-wrap"<?php echo $is_playoff ? ' style="display:none"' : ''; ?>><!-- playoff nema kola -->
					<span class="kurs-field-label">Kolo</span>
					<input type="number" id="rec-round" class="kurs-field-input kurs-field-input--xs"
					       value="<?php echo (int) $match->round; ?>" min="1" max="99">
				</label>
				<label class="kurs-field-wrap">
					<span class="kurs-field-label">Datum i sat</span>
					<input type="text" id="rec-date" class="kurs-datepicker kurs-field-input"
					       value="<?php echo esc_attr( $date_val ); ?>"
					       placeholder="dd.mm.yyyy hh:mm" autocomplete="off">
				</label>
				<button class="kurs-btn kurs-btn--red kurs-btn--sm" id="btn-save-match-info">Spremi</button>
				<span id="save-match-msg" class="kurs-muted" style="display:none;"></span>
			</div>
		</details>
		<?php endif; ?>

		<!-- ── Unos: svaka ekipa svoja strana (1.25.0) ───────────────────────── -->
		<?php if ( ! $is_finished && ! $season_locked ) : ?>
		<div class="kurs-record-section kurs-rc no-print" id="kurs-rc">
			<div class="kurs-rc-half" role="group" aria-label="Poluvrijeme">
				<button type="button" class="kurs-rc-half__opt<?php echo $cur_half === 1 ? ' is-active' : ''; ?>" data-half="1">1. poluvrijeme</button>
				<button type="button" class="kurs-rc-half__opt<?php echo $cur_half === 2 ? ' is-active' : ''; ?>" data-half="2">2. poluvrijeme</button>
			</div>
			<div class="kurs-rc-teams">
				<?php foreach ( [ 'home' => [ (int) $match->home_team_id, $match->home_name, $home_color, 'Domaćin' ], 'away' => [ (int) $match->away_team_id, $match->away_name, $away_color, 'Gost' ] ] as $who => [ $tid, $tname, $tcolor, $trole ] ) : ?>
				<div class="kurs-rc-team" data-who="<?php echo $who; ?>" data-team="<?php echo $tid; ?>" style="--tc:<?php echo esc_attr( $rc_tone( $tcolor ) ); ?>">
					<div class="kurs-rc-team__hd">
						<?php echo kurs_team_badge_html( $tid, 30 ); ?>
						<span class="kurs-rc-team__name"><?php echo $wbr_name( $tname ); ?></span>
						<span class="kurs-rc-team__role"><?php echo $trole; ?></span>
					</div>
					<button type="button" class="kurs-rc-btn kurs-rc-btn--goal" data-act="goal"><span class="kurs-rc-btn__ico">&#9917;</span>Gol</button>
					<div class="kurs-rc-team__cards">
						<button type="button" class="kurs-rc-btn kurs-rc-btn--yellow" data-act="yellow"><span class="kurs-rc-card kurs-rc-card--y"></span>Žuti</button>
						<button type="button" class="kurs-rc-btn kurs-rc-btn--red" data-act="red"><span class="kurs-rc-card kurs-rc-card--r"></span>Crveni</button>
					</div>
					<div class="kurs-rc-foul">
						<button type="button" class="kurs-rc-btn kurs-rc-btn--foul" data-act="foul">&#9995; Prekršaj</button>
						<span class="kurs-rc-foul__n" title="Akumulirani prekršaji u ovom poluvremenu"><b>0</b><small>/ <?php echo (int) $max_fouls; ?></small></span>
						<button type="button" class="kurs-rc-foul__minus" data-act="foul-minus" title="Ukloni zadnji prekršaj">&minus;</button>
					</div>
					<p class="kurs-rc-foul__hint" hidden></p>
				</div>
				<?php endforeach; ?>
			</div>
			<p class="kurs-rc-help">Klikni <b>Gol</b>, <b>Žuti</b> ili <b>Crveni</b> na strani ekipe pa igrača — sprema se odmah. Pogrešan unos ispravi s „Poništi” ili u popisu ispod (&#9998; / &times;).</p>
		</div>
		<?php endif; ?>

		<!-- Akumulirani prekršaji po poluvremenima (pregled završene utakmice + ispis) -->
		<div class="kurs-record-section kurs-record-actions<?php echo $is_finished ? '' : ' kurs-fouls-printonly'; ?>">
			<div class="kurs-fouls" id="kurs-fouls" data-max="<?php echo (int) $max_fouls; ?>">
				<div class="kurs-fouls__hd">
					<span class="kurs-fouls__title">Akumulirani prekršaji <span class="kurs-fouls__max">limit <?php echo (int) $max_fouls; ?> / poluvr.</span></span>
				</div>
				<div class="kurs-fouls__grid">
					<?php foreach ( [ [ (int) $match->home_team_id, $match->home_name, $fc_home ], [ (int) $match->away_team_id, $match->away_name, $fc_away ] ] as $ft ) :
						list( $tid, $tname, $fc ) = $ft; ?>
					<div class="kurs-foul-team" data-team="<?php echo $tid; ?>">
						<span class="kurs-foul-team__name"><?php echo esc_html( $tname ); ?></span>
						<?php foreach ( [ 1, 2 ] as $hh ) :
							$cnt = (int) ( $fc[ $hh ] ?? 0 );
							$cls = $cnt > $max_fouls ? ' is-penal' : ( $cnt >= $max_fouls ? ' is-warn' : '' );
						?>
						<span class="kurs-foul-cell<?php echo $cls; ?>" data-half="<?php echo $hh; ?>" data-count="<?php echo $cnt; ?>">
							<span class="kurs-foul-cell__h"><?php echo $hh; ?>. pol</span>
							<span class="kurs-foul-cell__n"><?php echo $cnt; ?></span>
						</span>
						<?php endforeach; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<p class="kurs-fouls__legend">Do <?php echo (int) $max_fouls; ?>. prekršaja igra se sa zidom; <?php echo (int) $max_fouls + 1; ?>. i svaki sljedeći = akumulirani penal (10 m). Brojač se resetira na poluvremenu.</p>
			</div>
		</div>
		<!-- ── Tijek utakmice (golovi i kartoni) ────────────────────────────── -->
		<div class="kurs-record-section">
			<h3 class="kurs-record-section__title">Golovi i kartoni</h3>
			<div id="kurs-events-list">
				<?php
				$ev_icons = [ 'goal' => '⚽', 'penalty' => '⚽', 'own_goal' => '⚽', 'yellow_card' => '🟨', 'second_yellow' => '🟨🟥', 'red_card' => '🟥' ];
				$ev_tags  = [ 'penalty' => '(P)', 'own_goal' => '(AG)' ];
				// Grupiraj golove i kartone po poluvremenu (prazno/0 = 1. poluvrijeme)
				$events_by_half = [ 1 => [], 2 => [] ];
				foreach ( $timeline_events as $tev ) {
					$events_by_half[ ( (int) $tev->half === 2 ) ? 2 : 1 ][] = $tev;
				}
				?>
				<p class="kurs-muted" id="no-events-msg"<?php echo $timeline_events ? ' style="display:none;"' : ''; ?>>Nema evidentiranih događaja.</p>
				<?php foreach ( [ 1 => '1. poluvrijeme', 2 => '2. poluvrijeme' ] as $hh => $hlabel ) : ?>
				<div class="kurs-half-group" id="kurs-half-group-<?php echo $hh; ?>" data-half="<?php echo $hh; ?>"<?php echo empty( $events_by_half[ $hh ] ) ? ' hidden' : ''; ?>>
					<h4 class="kurs-half-group__title"><?php echo $hlabel; ?></h4>
					<div class="kurs-half-group__events" id="kurs-events-half-<?php echo $hh; ?>">
						<?php foreach ( $events_by_half[ $hh ] as $ev ) :
							$is_home = (int) $ev->team_id === (int) $match->home_team_id;
							$icon    = $ev_icons[ $ev->event_type ] ?? '⚽';
							$tag     = $ev_tags[ $ev->event_type ] ?? '';
						?>
				<div class="kurs-event-row <?php echo $is_home ? 'kurs-event--home' : 'kurs-event--away'; ?>"
				     id="event-<?php echo (int) $ev->id; ?>" data-event-id="<?php echo (int) $ev->id; ?>" data-half="<?php echo $hh; ?>">
					<span class="kurs-event__min"><?php echo $ev->minute !== null ? (int) $ev->minute . "'" : '–'; ?></span>
					<span class="kurs-event__icon"><?php echo $icon; ?></span>
					<span class="kurs-event__name">
						<?php echo esc_html( $ev->player_name ?: '—' ); ?>
						<?php if ( $tag ) : ?><span class="kurs-event__og"><?php echo $tag; ?></span><?php endif; ?>
					</span>
					<span class="kurs-event__team"><?php echo esc_html( $is_home ? $match->home_name : $match->away_name ); ?></span>
					<?php if ( ! $is_finished ) : ?>
					<button class="kurs-edit-event no-print" title="Uredi"
					        data-event-id="<?php echo (int) $ev->id; ?>" data-type="<?php echo esc_attr( $ev->event_type ); ?>"
					        data-team-id="<?php echo (int) $ev->team_id; ?>" data-player-id="<?php echo (int) $ev->player_id; ?>"
					        data-minute="<?php echo (int) $ev->minute; ?>" data-half="<?php echo $hh; ?>"><span aria-hidden="true">&#9998;</span> Uredi</button>
					<button class="kurs-del-event no-print" data-event-id="<?php echo (int) $ev->id; ?>" title="Obriši"><span aria-hidden="true">&times;</span> Obriši</button>
					<?php endif; ?>
				</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

		</div>

		<!-- ── Postave ──────────────────────────────────────────────────────── -->
		<div class="kurs-record-section kurs-record-squads">
			<div class="kurs-record-squad">
				<h3 class="kurs-record-section__title">
					<?php echo kurs_team_badge_html( (int) $match->home_team_id, 20 ); ?>
					<?php echo esc_html( $match->home_name ); ?>
				</h3>
				<?php $h_coach = kurs_team_coach( (int) $match->home_team_id ); $h_rep = kurs_team_rep( (int) $match->home_team_id ); ?>
				<?php if ( $h_coach || $h_rep ) : ?>
				<div class="kurs-officials">
					<?php if ( $h_coach ) : ?><span><strong>Trener:</strong> <?php echo esc_html( $h_coach['name'] ); ?><?php if ( $h_coach['phone'] ) echo ' · ' . esc_html( $h_coach['phone'] ); ?></span><?php endif; ?>
					<?php if ( $h_rep ) : ?><span><strong>Predstavnik:</strong> <?php echo esc_html( $h_rep['name'] ); ?><?php if ( $h_rep['phone'] ) echo ' · ' . esc_html( $h_rep['phone'] ); ?></span><?php endif; ?>
				</div>
				<?php endif; ?>
				<?php if ( empty( $home_squad ) ) : ?>
				<p class="kurs-muted">Nema prijavljenih igrača.</p>
				<?php else : ?>
				<?php if ( ! $is_finished ) : $cap_now = $home_captain; ?>
				<div class="kurs-sq-tools no-print">
					<label class="kurs-sq-capsel"><span class="kurs-sq-capsel__c" aria-hidden="true">C</span> Kapetan
						<select class="kurs-cap-select" data-team-id="<?php echo (int) $match->home_team_id; ?>">
							<option value="0">— nije odabran —</option>
							<?php foreach ( $home_squad as $sq ) : ?>
							<option value="<?php echo (int) $sq->player_id; ?>"<?php selected( (int) $sq->player_id, $cap_now ); ?><?php disabled( in_array( (int) $sq->player_id, $absent_ids, true ) ); ?>><?php echo esc_html( ( $sq->jersey_no ? (int) $sq->jersey_no . ' · ' : '' ) . $sq->player_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<span class="kurs-sq-legend"><i>&#10003;</i> svi su u postavi · klik na kvačicu = igrač nije došao (&#8634; vraća)</span>
				</div>
				<?php endif; ?>
				<table class="kurs-squad-table" data-team-id="<?php echo (int) $match->home_team_id; ?>">
					<thead class="no-print"><tr><?php if ( ! $is_finished ) : ?><th class="kurs-sq-th">Igrao</th><?php endif; ?><th class="kurs-sq-th">Br.</th><th class="kurs-sq-th kurs-sq-th--name">Igrač</th></tr></thead>
					<?php foreach ( $home_squad as $sq ) :
						$pid = (int) $sq->player_id;
						$gk  = kurs_player_type( $pid ) === 'goalkeeper';
						$cap = $pid === $home_captain;
						$abs = in_array( $pid, $absent_ids, true );
					?>
					<tr class="kurs-sq-row<?php echo $abs ? ' is-absent' : ''; ?>"
					    data-player-id="<?php echo $pid; ?>" data-team-id="<?php echo (int) $match->home_team_id; ?>"
					    data-jersey="<?php echo (int) $sq->jersey_no; ?>" data-name="<?php echo esc_attr( $sq->player_name ); ?>" data-gk="<?php echo $gk ? 1 : 0; ?>">
						<?php if ( ! $is_finished ) : ?>
						<td class="kurs-sq-check no-print"><button type="button" class="kurs-sq-absent kurs-sq-tick" title="Igrao — klikni ako nije došao" aria-label="Igrao">&#10003;</button></td>
						<?php endif; ?>
						<td class="kurs-sq-jersey<?php echo $sq->jersey_no ? '' : ' is-empty'; ?>" title="Broj dresa"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : ''; ?></td>
						<td class="kurs-sq-name">
							<?php echo esc_html( $sq->player_name ); ?>
							<?php if ( $gk ) : ?><span class="kurs-sq-badge kurs-sq-badge--gk" title="Golman">G</span><?php endif; ?>
							<span class="kurs-sq-badge kurs-sq-badge--cap" title="Kapetan"<?php echo $cap ? '' : ' hidden'; ?>>C</span>
						</td>
					</tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>
				<?php echo $lineup_add_html( (int) $match->home_team_id, $home_squad ); ?>
			</div>
			<div class="kurs-record-squad">
				<h3 class="kurs-record-section__title">
					<?php echo kurs_team_badge_html( (int) $match->away_team_id, 20 ); ?>
					<?php echo esc_html( $match->away_name ); ?>
				</h3>
				<?php $a_coach = kurs_team_coach( (int) $match->away_team_id ); $a_rep = kurs_team_rep( (int) $match->away_team_id ); ?>
				<?php if ( $a_coach || $a_rep ) : ?>
				<div class="kurs-officials">
					<?php if ( $a_coach ) : ?><span><strong>Trener:</strong> <?php echo esc_html( $a_coach['name'] ); ?><?php if ( $a_coach['phone'] ) echo ' · ' . esc_html( $a_coach['phone'] ); ?></span><?php endif; ?>
					<?php if ( $a_rep ) : ?><span><strong>Predstavnik:</strong> <?php echo esc_html( $a_rep['name'] ); ?><?php if ( $a_rep['phone'] ) echo ' · ' . esc_html( $a_rep['phone'] ); ?></span><?php endif; ?>
				</div>
				<?php endif; ?>
				<?php if ( empty( $away_squad ) ) : ?>
				<p class="kurs-muted">Nema prijavljenih igrača.</p>
				<?php else : ?>
				<?php if ( ! $is_finished ) : $cap_now = $away_captain; ?>
				<div class="kurs-sq-tools no-print">
					<label class="kurs-sq-capsel"><span class="kurs-sq-capsel__c" aria-hidden="true">C</span> Kapetan
						<select class="kurs-cap-select" data-team-id="<?php echo (int) $match->away_team_id; ?>">
							<option value="0">— nije odabran —</option>
							<?php foreach ( $away_squad as $sq ) : ?>
							<option value="<?php echo (int) $sq->player_id; ?>"<?php selected( (int) $sq->player_id, $cap_now ); ?><?php disabled( in_array( (int) $sq->player_id, $absent_ids, true ) ); ?>><?php echo esc_html( ( $sq->jersey_no ? (int) $sq->jersey_no . ' · ' : '' ) . $sq->player_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<span class="kurs-sq-legend"><i>&#10003;</i> svi su u postavi · klik na kvačicu = igrač nije došao (&#8634; vraća)</span>
				</div>
				<?php endif; ?>
				<table class="kurs-squad-table" data-team-id="<?php echo (int) $match->away_team_id; ?>">
					<thead class="no-print"><tr><?php if ( ! $is_finished ) : ?><th class="kurs-sq-th">Igrao</th><?php endif; ?><th class="kurs-sq-th">Br.</th><th class="kurs-sq-th kurs-sq-th--name">Igrač</th></tr></thead>
					<?php foreach ( $away_squad as $sq ) :
						$pid = (int) $sq->player_id;
						$gk  = kurs_player_type( $pid ) === 'goalkeeper';
						$cap = $pid === $away_captain;
						$abs = in_array( $pid, $absent_ids, true );
					?>
					<tr class="kurs-sq-row<?php echo $abs ? ' is-absent' : ''; ?>"
					    data-player-id="<?php echo $pid; ?>" data-team-id="<?php echo (int) $match->away_team_id; ?>"
					    data-jersey="<?php echo (int) $sq->jersey_no; ?>" data-name="<?php echo esc_attr( $sq->player_name ); ?>" data-gk="<?php echo $gk ? 1 : 0; ?>">
						<?php if ( ! $is_finished ) : ?>
						<td class="kurs-sq-check no-print"><button type="button" class="kurs-sq-absent kurs-sq-tick" title="Igrao — klikni ako nije došao" aria-label="Igrao">&#10003;</button></td>
						<?php endif; ?>
						<td class="kurs-sq-jersey<?php echo $sq->jersey_no ? '' : ' is-empty'; ?>" title="Broj dresa"><?php echo $sq->jersey_no ? (int) $sq->jersey_no : ''; ?></td>
						<td class="kurs-sq-name">
							<?php echo esc_html( $sq->player_name ); ?>
							<?php if ( $gk ) : ?><span class="kurs-sq-badge kurs-sq-badge--gk" title="Golman">G</span><?php endif; ?>
							<span class="kurs-sq-badge kurs-sq-badge--cap" title="Kapetan"<?php echo $cap ? '' : ' hidden'; ?>>C</span>
						</td>
					</tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>
				<?php echo $lineup_add_html( (int) $match->away_team_id, $away_squad ); ?>
			</div>
		</div>

		<!-- ── Suci ──────────────────────────────────────────────────────────── -->
		<?php
		$saved_referees = Kurs_Season::referee_names( $season_id );
		$refs_locked    = $season_locked || ( ( $match->referee1 ?? '' ) !== '' ) || ( ( $match->referee2 ?? '' ) !== '' );
		?>
		<div class="kurs-record-section kurs-record-refs">
			<h3 class="kurs-record-section__title">Suci <span class="kurs-muted" style="font-weight:400;font-size:11px;text-transform:none;letter-spacing:0;">(2 po utakmici)</span></h3>
			<div class="kurs-refs-form" id="kurs-refs">
				<div class="kurs-refs-inputs">
					<input type="text" id="ref-1" class="kurs-ref-input" placeholder="Sudac 1" value="<?php echo esc_attr( $match->referee1 ?? '' ); ?>" maxlength="100" list="kurs-ref-list"<?php echo $refs_locked ? ' readonly' : ''; ?>>
					<input type="text" id="ref-2" class="kurs-ref-input" placeholder="Sudac 2" value="<?php echo esc_attr( $match->referee2 ?? '' ); ?>" maxlength="100" list="kurs-ref-list"<?php echo $refs_locked ? ' readonly' : ''; ?>>
				</div>
				<div class="kurs-refs-actions no-print">
					<button type="button" class="kurs-btn kurs-btn--dark kurs-btn--sm" id="ref-save"<?php echo $refs_locked ? ' hidden' : ''; ?>>Spremi suce</button>
					<button type="button" class="kurs-btn kurs-btn--outline kurs-btn--sm" id="ref-unlock"<?php echo $refs_locked && ! $season_locked ? '' : ' hidden'; ?>>&#9998; Promijeni</button>
					<span class="kurs-ref-msg" id="ref-msg" hidden>&#10003; Spremljeno</span>
				</div>
				<datalist id="kurs-ref-list">
					<?php foreach ( $saved_referees as $rname ) : ?>
					<option value="<?php echo esc_attr( $rname ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
			</div>
		</div>

		<!-- ── Završi utakmicu ────────────────────────────────────────────── -->
		<?php if ( ! $is_finished ) : ?>
		<div class="kurs-record-section kurs-record-section--finish-wrap no-print">
		<div class="kurs-record-section--finish">
			<h3 class="kurs-record-section__title">Završi utakmicu</h3>
			<form method="post">
				<?php wp_nonce_field( 'kurs_finish_match' ); ?>
				<input type="hidden" name="kurs_action" value="finish_match">
				<input type="hidden" name="match_id"    value="<?php echo $match_id; ?>">
				<input type="hidden" name="season_id"   value="<?php echo $season_id; ?>">
				<input type="hidden" name="from_record"  value="1">
				<?php if ( $kurs_front ) : ?><input type="hidden" name="kurs_front" value="1"><?php endif; ?>
				<div class="kurs-finish-row">
					<span class="kurs-finish-row__home"><?php echo esc_html( $match->home_name ); ?></span>
					<div class="kurs-finish-score">
						<input type="number" name="home_score" id="finish-home" class="kurs-si" value="<?php echo $ev_home; ?>" min="0" max="99" required readonly>
						<span class="kurs-finish-score__sep">:</span>
						<input type="number" name="away_score" id="finish-away" class="kurs-si" value="<?php echo $ev_away; ?>" min="0" max="99" required readonly>
					</div>
					<span class="kurs-finish-row__away"><?php echo esc_html( $match->away_name ); ?></span>
				</div>
				<?php if ( $is_playoff ) : ?>
				<div class="kurs-finish-row kurs-finish-row--pen" title="Playoff mora imati pobjednika — upiši samo ako je utakmica završila neriješeno">
					<span class="kurs-finish-row__home kurs-muted">Penali</span>
					<div class="kurs-finish-score">
						<input type="number" name="home_pen" class="kurs-si" min="0" max="99" placeholder="–">
						<span class="kurs-finish-score__sep">:</span>
						<input type="number" name="away_pen" class="kurs-si" min="0" max="99" placeholder="–">
					</div>
					<span class="kurs-finish-row__away kurs-muted">samo kod neriješenog</span>
				</div>
				<?php endif; ?>
				<div class="kurs-finish-meta">
					<span class="kurs-finish-auto-label" id="finish-auto-label">
						&#9679; Rezultat se automatski računa iz unesenih golova
					</span>
					<button type="button" class="kurs-btn kurs-btn--ghost kurs-btn--sm" id="finish-manual-toggle">
						Ručni unos
					</button>
				</div>
				<div class="kurs-finish-submit">
					<button type="submit" class="kurs-btn kurs-btn--red">&#10003; Završi i spremi rezultat</button>
					<span class="kurs-muted" style="font-size:11px;">Završavanjem se rezultat bilježi u tablicu bodova.</span>
				</div>
			</form>
		</div>
		</div><!-- /.kurs-record-section--finish -->
		</div><!-- /.kurs-record-section--finish-wrap -->
		<?php else : ?>
		<div class="kurs-record-section kurs-record-section--finish-wrap no-print">
			<div class="kurs-record-section--finish kurs-record-section--finished">
				<div class="kurs-finished-status">
					<span class="kurs-finished-status__badge">&#10003; Završeno</span>
					<span class="kurs-finished-status__score">
						<?php echo esc_html( $match->home_name ); ?>
						<strong><?php echo (int) $match->home_score; ?> : <?php echo (int) $match->away_score; ?></strong>
						<?php echo esc_html( $match->away_name ); ?>
						<?php if ( $playoff_m && ( $pen = Kurs_Playoff::pen_label( $playoff_m ) ) ) : ?>
						<span class="kurs-muted">(<?php echo esc_html( $pen ); ?>)</span>
						<?php endif; ?>
					</span>
				</div>
				<?php if ( ! $season_locked ) : ?>
				<form method="post" class="kurs-reopen-form"
				      onsubmit="return confirm('Otvoriti utakmicu za ispravak? Dok je otvorena, ne računa se u ljestvicu — nakon ispravka je ponovno završi.');">
					<?php wp_nonce_field( 'kurs_reopen_match' ); ?>
					<input type="hidden" name="kurs_action" value="reopen_match">
					<?php if ( $kurs_front ) : ?><input type="hidden" name="kurs_front" value="1"><?php endif; ?>
					<input type="hidden" name="match_id"    value="<?php echo $match_id; ?>">
					<button type="submit" class="kurs-btn kurs-btn--ghost kurs-btn--sm">
						&#9998; Otvori za ispravak
					</button>
				</form>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Potpisi (samo za ispis) -->
		<div class="kurs-record-sigs print-only">
			<div><div>Potpis domaćin:</div><div class="kurs-sig-line"></div></div>
			<div><div>Sudac:</div><div class="kurs-sig-line"></div></div>
			<div><div>Potpis gost:</div><div class="kurs-sig-line"></div></div>
		</div>

	</div><!-- /.kurs-record-doc -->
</div><!-- /.kurs-record-wrap -->

<?php if ( ! $is_finished ) : ?>
<!-- ── Modali zapisnika (GOL / KARTON / PREKRŠAJ) ────────────────────────── -->
<div class="kurs-rec-modal" id="kurs-modal-gol" hidden>
	<div class="kurs-modal-backdrop" data-close></div>
	<div class="kurs-modal-box">
		<h3 class="kurs-modal-title">&#9998; Uredi gol</h3>
		<div class="kurs-modal-field">
			<label>Momčad</label>
			<div class="kurs-team-toggle">
				<button type="button" class="kurs-team-opt is-active" data-team="home"><?php echo esc_html( $match->home_name ); ?></button>
				<button type="button" class="kurs-team-opt" data-team="away"><?php echo esc_html( $match->away_name ); ?></button>
			</div>
		</div>
		<div class="kurs-modal-field">
			<label>Igrač</label>
			<select id="gol-player"></select>
		</div>
		<div class="kurs-modal-field">
			<label>Poluvrijeme</label>
			<div class="kurs-half-toggle-modal" id="gol-half">
				<button type="button" class="kurs-half-opt is-active" data-half="1">1. poluvrijeme</button>
				<button type="button" class="kurs-half-opt" data-half="2">2. poluvrijeme</button>
			</div>
		</div>
		<div class="kurs-modal-row">
			<div class="kurs-modal-field">
				<label>Tip</label>
				<select id="gol-type">
					<option value="goal">Regularan</option>
					<option value="penalty">Penal</option>
					<option value="own_goal">Autogol</option>
				</select>
			</div>
			<div class="kurs-modal-field kurs-modal-field--min">
				<label>Minuta</label>
				<input type="number" id="gol-minute" min="0" max="120" placeholder="—">
			</div>
		</div>
		<p class="kurs-modal-err" id="gol-err" hidden></p>
		<div class="kurs-modal-actions">
			<button type="button" class="kurs-btn kurs-btn--ghost" data-close>Odustani</button>
			<button type="button" class="kurs-btn kurs-btn--red" id="gol-submit">Spremi</button>
		</div>
	</div>
</div>

<div class="kurs-rec-modal" id="kurs-modal-karton" hidden>
	<div class="kurs-modal-backdrop" data-close></div>
	<div class="kurs-modal-box">
		<h3 class="kurs-modal-title">&#9998; Uredi karton</h3>
		<div class="kurs-modal-field">
			<label>Momčad</label>
			<div class="kurs-team-toggle">
				<button type="button" class="kurs-team-opt is-active" data-team="home"><?php echo esc_html( $match->home_name ); ?></button>
				<button type="button" class="kurs-team-opt" data-team="away"><?php echo esc_html( $match->away_name ); ?></button>
			</div>
		</div>
		<div class="kurs-modal-field">
			<label>Igrač</label>
			<select id="karton-player"></select>
		</div>
		<div class="kurs-modal-field">
			<label>Poluvrijeme</label>
			<div class="kurs-half-toggle-modal" id="karton-half">
				<button type="button" class="kurs-half-opt is-active" data-half="1">1. poluvrijeme</button>
				<button type="button" class="kurs-half-opt" data-half="2">2. poluvrijeme</button>
			</div>
		</div>
		<div class="kurs-modal-row">
			<div class="kurs-modal-field">
				<label>Tip</label>
				<select id="karton-type">
					<option value="yellow_card">&#129000; Žuti karton</option>
					<option value="second_yellow">&#129000;&#128997; Drugi žuti (&rarr; crveni)</option>
					<option value="red_card">&#128997; Direktni crveni</option>
				</select>
			</div>
			<div class="kurs-modal-field kurs-modal-field--min">
				<label>Minuta</label>
				<input type="number" id="karton-minute" min="0" max="120" placeholder="—">
			</div>
		</div>
		<p class="kurs-modal-err" id="karton-err" hidden></p>
		<div class="kurs-modal-actions">
			<button type="button" class="kurs-btn kurs-btn--ghost" data-close>Odustani</button>
			<button type="button" class="kurs-btn kurs-btn--dark" id="karton-submit">Spremi</button>
		</div>
	</div>
</div>

<!-- Izbor igrača: GOL / ŽUTI / CRVENI jedne ekipe (#kurs-rc) — klik na igrača odmah sprema -->
<div class="kurs-rec-modal kurs-pick" id="kurs-pick" hidden>
	<div class="kurs-modal-backdrop" data-close></div>
	<div class="kurs-modal-box kurs-pick__box" role="dialog" aria-modal="true" aria-labelledby="kurs-pick-title">
		<div class="kurs-pick__hd">
			<h3 class="kurs-pick__title" id="kurs-pick-title"></h3>
			<button type="button" class="kurs-pick__x" data-close aria-label="Zatvori">&times;</button>
		</div>
		<div class="kurs-pick__opts">
			<label class="kurs-pick__min">Minuta <input type="number" id="kurs-pick-min" min="0" max="120" inputmode="numeric" placeholder="—"></label>
			<span class="kurs-pick__half" id="kurs-pick-half"></span>
			<button type="button" class="kurs-pick__chip" id="kurs-pick-pen" aria-pressed="false">Penal</button>
		</div>
		<p class="kurs-pick__sub" id="kurs-pick-sub"></p>
		<div class="kurs-pick__grid" id="kurs-pick-grid"></div>
		<div class="kurs-pick__extra">
			<button type="button" class="kurs-pick__chip" id="kurs-pick-og">Autogol protivnika</button>
			<button type="button" class="kurs-pick__chip" id="kurs-pick-back" hidden>&larr; Natrag na igrače</button>
		</div>
		<p class="kurs-modal-err" id="kurs-pick-err" hidden></p>
	</div>
</div>

<div class="kurs-undo" id="kurs-rc-undo" hidden role="status" aria-live="polite">
	<span class="kurs-undo__msg"></span>
	<button type="button" class="kurs-undo__btn">Poništi</button>
	<button type="button" class="kurs-undo__x" aria-label="Zatvori">&times;</button>
</div>
<?php endif; ?>

<script>
window.KursHomeSquad = <?php echo wp_json_encode( array_values( array_map( fn( $s ) => [
	'id'     => (int) $s->player_id,
	'name'   => $s->player_name,
	'jersey' => (int) $s->jersey_no,
	'gk'     => kurs_player_type( (int) $s->player_id ) === 'goalkeeper',
	'cap'    => (int) $s->player_id === $home_captain,
], array_filter( $home_squad, fn( $s ) => ! in_array( (int) $s->player_id, $absent_ids, true ) ) ) ) ); ?>;
window.KursAwaySquad = <?php echo wp_json_encode( array_values( array_map( fn( $s ) => [
	'id'     => (int) $s->player_id,
	'name'   => $s->player_name,
	'jersey' => (int) $s->jersey_no,
	'gk'     => kurs_player_type( (int) $s->player_id ) === 'goalkeeper',
	'cap'    => (int) $s->player_id === $away_captain,
], array_filter( $away_squad, fn( $s ) => ! in_array( (int) $s->player_id, $absent_ids, true ) ) ) ) ); ?>;
window.KursHomeTeamId = <?php echo (int) $match->home_team_id; ?>;
window.KursAwayTeamId = <?php echo (int) $match->away_team_id; ?>;
window.KursMatchId    = <?php echo $match_id; ?>;
window.KursHomeName   = <?php echo wp_json_encode( $match->home_name ); ?>;
window.KursAwayName   = <?php echo wp_json_encode( $match->away_name ); ?>;
window.KursPdfUrl     = <?php echo wp_json_encode( $pdf_url ); ?>;
window.KursMaxFouls   = <?php echo (int) $max_fouls; ?>;
window.KursFoulCounts = <?php echo wp_json_encode( (object) $foul_counts ); ?>;
window.KursSentOff    = <?php echo wp_json_encode( array_values( $sent_off ) ); ?>;
window.KursCurHalf    = <?php echo (int) $cur_half; ?>;
window.KursStatus     = <?php echo wp_json_encode( $match->status ); ?>;
</script>
