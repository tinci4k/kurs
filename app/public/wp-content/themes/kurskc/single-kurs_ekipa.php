<?php
/**
 * Profil ekipe (E) — single-kurs_ekipa.php
 */
if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Match' ) ) {
	wp_redirect( home_url( '/' ) ); // nema arhive /ekipe/
	exit;
}

get_header();

global $wpdb;
$p       = $wpdb->prefix . KURS_PREFIX;
$team_id = (int) get_the_ID();
$team    = get_post( $team_id );
$thumb_l = get_the_post_thumbnail_url( $team_id, 'large' );
$thumb_s = get_the_post_thumbnail_url( $team_id, 'thumbnail' );

/* ── Konteksti (sezone/divizije u kojima ekipa sudjeluje) ── */
$contexts = Kurs_Season::get_team_contexts( $team_id );

/* Odredi aktivnu diviziju */
$division_id = isset( $_GET['division_id'] ) ? (int) $_GET['division_id'] : 0;
if ( $division_id && ! in_array( $division_id, array_column( $contexts, 'division_id' ), false ) ) {
	$division_id = 0;
}
if ( ! $division_id && $contexts ) {
	$division_id = (int) $contexts[0]->division_id;
}

/* ── Aktivni kontekst ───────────────────────────────────── */
$active_ctx = null;
foreach ( $contexts as $c ) {
	if ( (int) $c->division_id === $division_id ) { $active_ctx = $c; break; }
}

/* ── Pozicija u tablici ──────────────────────────────────── */
$team_pos = null;
if ( $division_id ) {
	foreach ( Kurs_Season::get_phases( $division_id ) as $ph ) {
		$ph_groups = Kurs_Season::get_groups( (int) $ph->id );
		foreach ( $ph_groups as $grp ) {
			$grp_rows = Kurs_Match::get_standings( (int) $grp->id );
			foreach ( $grp_rows as $i => $row ) {
				if ( (int) $row['team_id'] === $team_id ) {
					$team_pos = array_merge( $row, [
						'pos'     => $i + 1,
						'total'   => count( $grp_rows ),
						'group'   => (string) $grp->name,
						'ngroups' => count( $ph_groups ),
					] );
					break 3;
				}
			}
		}
	}
}

/* ── Doigravanje (1.0.61): konačni plasman + P/N/I i golovi playoff utakmica ──
   Ljestvica skupine broji samo grupnu fazu; na Pan ligi prvak se odlučuje u doigravanju,
   pa gornje kartice pokazuju plasman i zbroj svih utakmica (skupina + doigravanje). */
$po_place = null;   // [ vrijednost, opis, ton: champ|good|out|live ]
$po_tot   = null;   // W/D/L/GF/GA skupina + doigravanje
if ( $division_id && $team_pos && class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( $division_id ) ) {
	$br   = Kurs_Playoff::get_bracket( $division_id );
	$mine = [];
	foreach ( $br as $rnd => $slots ) foreach ( $slots as $pm ) {
		if ( (int) $pm->home_team_id === $team_id || (int) $pm->away_team_id === $team_id ) $mine[ $rnd ] = $pm;
	}
	$po_tot = [ 'W' => (int) $team_pos['W'], 'D' => (int) $team_pos['D'], 'L' => (int) $team_pos['L'], 'GF' => (int) $team_pos['GF'], 'GA' => (int) $team_pos['GA'] ];
	foreach ( $mine as $pm ) {
		if ( ( $pm->status ?? '' ) !== 'finished' ) continue;
		$h  = (int) $pm->home_team_id === $team_id;
		$gf = $h ? (int) $pm->home_score : (int) $pm->away_score;
		$ga = $h ? (int) $pm->away_score : (int) $pm->home_score;
		$po_tot['GF'] += $gf; $po_tot['GA'] += $ga;
		$po_tot[ $gf > $ga ? 'W' : ( $gf < $ga ? 'L' : 'D' ) ]++;
	}
	// true = ekipa prošla, false = ispala, null = još se nije igralo
	$won = function ( $pm ) use ( $team_id ) {
		$s = Kurs_Playoff::winner_side( $pm );
		return $s ? ( (int) ( $s === 'home' ? $pm->home_team_id : $pm->away_team_id ) === $team_id ) : null;
	};
	if ( isset( $mine['final'] ) ) {
		$r = $won( $mine['final'] );
		$po_place = $r === true ? [ 'Prvak', 'pobjednik finala', 'champ' ] : ( $r === false ? [ 'Finalist', 'izgubili u finalu', 'good' ] : [ 'Finale', 'igraju u finalu', 'live' ] );
	} elseif ( isset( $mine['3rd'] ) ) {
		$r = $won( $mine['3rd'] );
		$po_place = $r === true ? [ '3. mjesto', 'pobjeda za 3. mjesto', 'good' ] : ( $r === false ? [ '4. mjesto', 'poraz za 3. mjesto', 'out' ] : [ 'Za 3. mjesto', 'igraju za 3. mjesto', 'live' ] );
	} elseif ( isset( $mine['sf'] ) ) {
		$r = $won( $mine['sf'] );
		$po_place = $r === false ? [ 'Polufinale', 'ispali u polufinalu', 'out' ] : [ 'Polufinale', $r ? 'prošli u finale' : 'igraju u polufinalu', 'live' ];
	} elseif ( isset( $mine['qf'] ) ) {
		$r = $won( $mine['qf'] );
		$po_place = $r === false ? [ 'Četvrtfinale', 'ispali u četvrtfinalu', 'out' ] : [ 'Četvrtfinale', $r ? 'prošli u polufinale' : 'igraju u četvrtfinalu', 'live' ];
	} else {
		// nije u bracketu: ispali u skupini — samo ako su svi parovi prvog kola doigravanja popunjeni
		$first = reset( $br ) ?: [];
		$full  = $first && ! array_filter( $first, fn( $pm ) => ! (int) $pm->home_team_id || ! (int) $pm->away_team_id );
		if ( $full ) $po_place = [ 'Skupina', 'bez doigravanja', 'out' ];
	}
}

/* Utakmice lige: skupine + playoff (zapisnici s group_id=0) — podupit za „m.id IN ( … )" */
$in_div = $division_id ? Kurs_Match::division_match_ids_sql( $division_id ) : '';

/* ── Forma: zadnjih 5 utakmica ──────────────────────────── */
$form = [];
if ( $division_id ) {
	$form = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.id, m.home_team_id, m.away_team_id, m.home_score, m.away_score
		 FROM   {$p}matches m
		 WHERE  m.id IN ( {$in_div} ) AND m.status = 'finished'
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)
		 ORDER  BY m.match_date DESC, m.id DESC LIMIT 5",
		$team_id, $team_id
	) );
}

/* ── Sljedeća utakmica ──────────────────────────────────── */
$next = null;
if ( $division_id ) {
	$next = $wpdb->get_row( $wpdb->prepare(
		"SELECT m.id, m.match_date, m.round, pm.round AS po_round,
		        h.post_title AS home_name, a.post_title AS away_name,
		        m.home_team_id, m.away_team_id
		 FROM   {$p}matches m
		 LEFT JOIN {$p}playoff_matches pm ON pm.match_id = m.id
		 JOIN   {$wpdb->posts} h ON h.ID = m.home_team_id
		 JOIN   {$wpdb->posts} a ON a.ID = m.away_team_id
		 WHERE  m.id IN ( {$in_div} ) AND m.status = 'scheduled'
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)
		 ORDER  BY m.match_date IS NULL, m.match_date ASC, m.id ASC LIMIT 1",
		$team_id, $team_id
	) );
}

/* ── Roster ─────────────────────────────────────────────── */
$squad = $division_id ? Kurs_Season::get_squad( $team_id, $division_id ) : [];

/* ── Golovi po igraču u toj diviziji ───────────────────── */
$scorer_map = [];
if ( $division_id ) {
	$sc_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.player_id, COUNT(*) AS goals
		 FROM   {$p}match_events e
		 JOIN   {$p}matches      m  ON m.id  = e.match_id
		 WHERE  m.id IN ( {$in_div} ) AND e.team_id = %d
		   AND  e.event_type IN ('goal','penalty') AND m.status = 'finished'
		 GROUP  BY e.player_id",
		$team_id
	) );
	foreach ( $sc_rows as $s ) $scorer_map[ (int) $s->player_id ] = (int) $s->goals;
	// + preneseni golovi iz rostera (utakmice koje nisu u sustavu)
	foreach ( $squad as $sq ) {
		if ( (int) $sq->base_goals ) $scorer_map[ (int) $sq->player_id ] = ( $scorer_map[ (int) $sq->player_id ] ?? 0 ) + (int) $sq->base_goals;
	}
}

/* ── Sve utakmice ekipe u toj diviziji ─────────────────── */
$all_matches = [];
if ( $division_id ) {
	$all_matches = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.id, m.round, pm.round AS po_round, pm.home_pen, pm.away_pen, m.match_date, m.status, m.home_score, m.away_score,
		        h.post_title AS home_name, a.post_title AS away_name,
		        m.home_team_id, m.away_team_id
		 FROM   {$p}matches m
		 LEFT JOIN {$p}playoff_matches pm ON pm.match_id = m.id
		 JOIN   {$wpdb->posts} h ON h.ID = m.home_team_id
		 JOIN   {$wpdb->posts} a ON a.ID = m.away_team_id
		 WHERE  m.id IN ( {$in_div} )
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)
		 ORDER  BY m.group_id = 0, m.round ASC, m.match_date ASC",
		$team_id, $team_id
	) );
}

/* ── Statistika ekipe u diviziji (prosjeci + razdioba po poluvremenu) ───── */
$stats = null;
if ( $division_id ) {
	$ms = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.home_team_id, m.home_score, m.away_score
		 FROM   {$p}matches m
		 WHERE  m.id IN ( {$in_div} ) AND m.status = 'finished'
		   AND  (m.home_team_id = %d OR m.away_team_id = %d)",
		$team_id, $team_id
	) );
	$N = count( $ms );
	if ( $N ) {
		$gf = $ga = $w = $d = $l = $cs = $btts = $big_win = 0;
		foreach ( $ms as $m ) {
			$home = (int) $m->home_team_id === $team_id;
			$for  = $home ? (int) $m->home_score : (int) $m->away_score;
			$ag   = $home ? (int) $m->away_score : (int) $m->home_score;
			$gf += $for; $ga += $ag;
			if ( $for > $ag ) $w++; elseif ( $for < $ag ) $l++; else $d++;
			if ( $ag === 0 ) $cs++;
			if ( $for > 0 && $ag > 0 ) $btts++;
			if ( $for - $ag > $big_win ) $big_win = $for - $ag;
		}
		$ev = $wpdb->get_results( $wpdb->prepare(
			"SELECT (CASE WHEN e.half = 2 THEN 2 ELSE 1 END) AS h,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type IN ('goal','penalty')          THEN 1 ELSE 0 END) AS gf_own,
			   SUM(CASE WHEN e.team_id <> %d AND e.event_type IN ('goal','penalty')          THEN 1 ELSE 0 END) AS ga_opp,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type = 'own_goal'                    THEN 1 ELSE 0 END) AS og_team,
			   SUM(CASE WHEN e.team_id <> %d AND e.event_type = 'own_goal'                    THEN 1 ELSE 0 END) AS og_opp,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type = 'yellow_card'                 THEN 1 ELSE 0 END) AS yellow,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type IN ('red_card','second_yellow') THEN 1 ELSE 0 END) AS red,
			   SUM(CASE WHEN e.team_id =  %d AND e.event_type = 'foul'                        THEN 1 ELSE 0 END) AS fouls
			 FROM   {$p}match_events e
			 JOIN   {$p}matches m  ON m.id  = e.match_id
			 WHERE  m.id IN ( {$in_div} ) AND m.status = 'finished'
			   AND  (m.home_team_id = %d OR m.away_team_id = %d)
			 GROUP  BY h",
			$team_id, $team_id, $team_id, $team_id, $team_id, $team_id, $team_id, $team_id, $team_id
		) );
		$half = [ 1 => [ 'gf' => 0, 'ga' => 0, 'fouls' => 0 ], 2 => [ 'gf' => 0, 'ga' => 0, 'fouls' => 0 ] ];
		$yellow = $red = 0;
		foreach ( $ev as $r ) {
			$h = (int) $r->h === 2 ? 2 : 1;
			$half[ $h ]['gf']    += (int) $r->gf_own + (int) $r->og_opp;
			$half[ $h ]['ga']    += (int) $r->ga_opp + (int) $r->og_team;
			$half[ $h ]['fouls'] += (int) $r->fouls;
			$yellow += (int) $r->yellow; $red += (int) $r->red;
		}
		$stats = [
			'N' => $N, 'gf' => $gf, 'ga' => $ga, 'w' => $w, 'd' => $d, 'l' => $l,
			'cs' => $cs, 'btts' => $btts, 'big_win' => $big_win,
			'yellow' => $yellow, 'red' => $red,
			'fouls' => $half[1]['fouls'] + $half[2]['fouls'], 'half' => $half,
		];
	}
}

/* ── Helper: boja prema ligi ───────────────────────────── */
function ekipa_league_color( string $league ): string {
	return ( str_contains( strtolower( $league ), 'pan' ) ) ? 'var(--c-pan)' : 'var(--c-kc)';
}
/** „KC Liga 2026/2027" (bez ponavljanja lige) ili „Liga bez briga · KC Liga 2026/2027". */
function ekipa_ctx_label( object $c ): string {
	return stripos( $c->season_name, $c->division_name ) !== false ? $c->season_name : $c->division_name . ' · ' . $c->season_name;
}
/** 1 sezona · 2–4 sezone · 5+ sezona (11–14 sezona). */
function ekipa_sezona( int $n ): string {
	$m10 = $n % 10; $m100 = $n % 100;
	return $n . ' ' . ( $m10 === 1 && $m100 !== 11 ? 'sezona' : ( $m10 >= 2 && $m10 <= 4 && ( $m100 < 12 || $m100 > 14 ) ? 'sezone' : 'sezona' ) );
}
?>

<div class="team-profile">

	<!-- ── HERO ─────────────────────────────────────────────── -->
	<?php $team_color = kurs_team_color( $team_id ); ?>
	<div class="team-hero" style="border-bottom:4px solid <?php echo esc_attr( $team_color ); ?>;<?php if ( $thumb_l ) echo 'background-image:url(' . esc_url( $thumb_l ) . ');'; ?>">
		<div class="team-hero__overlay"></div>
		<div class="container team-hero__inner">
			<div class="team-hero__badge">
				<?php if ( $thumb_s ) : ?>
				<img src="<?php echo esc_url( $thumb_s ); ?>" alt="">
				<?php else : ?>
				<span style="background:<?php echo esc_attr( $team_color ); ?>;color:<?php echo esc_attr( kurs_team_text_color( $team_color ) ); ?>;"><?php echo esc_html( mb_strtoupper( mb_substr( $team->post_title, 0, 2 ) ) ); ?></span>
				<?php endif; ?>
			</div>
			<div>
				<?php
				/* Jedna oznaka po natjecanju (1.0.54): „KC liga · 2 sezone · od 2025." — s godinama se ne nižu nove oznake;
				   sve sezone su u izborniku „Liga / natjecanje" i u statističkom kartonu. */
				$comp = [];
				foreach ( $contexts as $c ) {
					$k = Kurs_Season::is_pan_league( (string) $c->league ) ? 'pan' : 'kc';
					$comp[ $k ]['seasons'][ (int) $c->season_id ] = $c->season_name;
					$comp[ $k ]['league'] = $c->league;
				}
				?>
				<div class="team-hero__comps">
					<?php foreach ( $comp as $k => $cp ) :
						$years = array_filter( array_map( fn( $n ) => preg_match( '/(\d{4})/', $n, $mm ) ? (int) $mm[1] : 0, $cp['seasons'] ) ); ?>
					<span class="team-hero__comp" style="--cc:<?php echo esc_attr( ekipa_league_color( $cp['league'] ) ); ?>">
						<?php echo kurs_league_icon( $k === 'pan' ? 'Pan Ljetna' : 'KC Liga', 18 ); ?>
						<b><?php echo $k === 'pan' ? 'Pan Ljetna liga' : 'KC liga'; ?></b>
						<span><?php echo esc_html( ekipa_sezona( count( $cp['seasons'] ) ) . ( $years ? ' · od ' . min( $years ) . '.' : '' ) ); ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<h1 class="team-hero__name"><?php echo esc_html( $team->post_title ); ?></h1>
			</div>
		</div>
	</div>

	<!-- ── LIGA / NATJECANJE — uvijek vidljivo što se prikazuje (1.0.59): izbornik ili fiksna oznaka + status sezone ── -->
	<?php if ( $contexts ) :
		$active_color = $active_ctx ? ekipa_league_color( $active_ctx->league ) : 'var(--c-kc)';
		// Je li prikazana sezona aktualna sezona tog natjecanja? Igra li ekipa u aktualnoj sezoni?
		$ctx_pan    = $active_ctx ? Kurs_Season::is_pan_league( (string) $active_ctx->league ) : false;
		$ctx_cur    = Kurs_Season::current_for_league( $ctx_pan );
		$ctx_is_cur = $active_ctx && $ctx_cur && (int) $active_ctx->season_id === (int) $ctx_cur->id;
		$in_cur     = $ctx_cur && array_filter( $contexts, fn( $c ) => (int) $c->season_id === (int) $ctx_cur->id );
	?>
	<div class="hub-select-bar">
		<div class="container hub-select-inner">
			<span class="hub-select__label">Prikazano</span>
			<?php if ( count( $contexts ) === 1 ) : ?>
			<span class="hub-dd__static"><?php echo kurs_league_icon( ( $active_ctx->league ?? '' ) . ' ' . ( $active_ctx->division_name ?? '' ), 22 ); ?><?php echo esc_html( $active_ctx ? ekipa_ctx_label( $active_ctx ) : '' ); ?></span>
			<?php else : ?>
			<div class="hub-dd" id="team-league-dd">
				<button type="button" class="hub-dd__toggle" aria-haspopup="listbox" aria-expanded="false">
					<?php echo kurs_league_icon( ( $active_ctx->league ?? '' ) . ' ' . ( $active_ctx->division_name ?? '' ), 22 ); ?>
					<span class="hub-dd__current"><?php echo esc_html( $active_ctx ? ekipa_ctx_label( $active_ctx ) : '' ); ?></span>
					<span class="hub-dd__caret" aria-hidden="true">▾</span>
				</button>
				<ul class="hub-dd__menu" role="listbox">
					<?php $grp_prev = null; foreach ( $contexts as $c ) :
						$grp = Kurs_Season::is_pan_league( (string) $c->league ) ? 'Pan Ljetna liga' : 'KC liga';
						if ( $grp !== $grp_prev && count( array_unique( array_map( fn( $x ) => Kurs_Season::is_pan_league( (string) $x->league ), $contexts ) ) ) > 1 ) : $grp_prev = $grp; ?>
					<li class="hub-dd__grp" role="presentation"><?php echo esc_html( $grp ); ?></li>
						<?php endif;
						$url    = add_query_arg( 'division_id', $c->division_id, get_permalink() );
						$active = (int) $c->division_id === $division_id;
						$dotc   = ekipa_league_color( $c->league );
					?>
					<li role="option" aria-selected="<?php echo $active ? 'true' : 'false'; ?>">
						<a href="<?php echo esc_url( $url ); ?>" class="hub-dd__opt<?php echo $active ? ' is-active' : ''; ?>">
							<?php echo kurs_league_icon( $c->league . ' ' . $c->division_name, 20 ); ?>
							<?php echo esc_html( ekipa_ctx_label( $c ) ); ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
			<?php if ( $ctx_is_cur ) : ?>
			<span class="ctx-badge ctx-badge--cur">Aktualna sezona</span>
			<?php else : ?>
			<span class="ctx-badge">Arhiva · završena sezona</span>
			<?php if ( ! $in_cur && $ctx_cur ) : ?><span class="ctx-note">Ekipa ne nastupa u sezoni <?php echo esc_html( $ctx_cur->name ); ?>.</span><?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="container" style="padding-block:28px 44px;">
		<div class="team-body">

			<!-- ── LIJEVО: stats + forma + raspored ─────────────── -->
			<div class="team-main">

				<!-- Slika momčadi -->
				<?php $team_photo = kurs_team_photo( $team_id, 'large' ); ?>
				<?php if ( $team_photo ) : ?>
				<div class="team-photo">
					<img src="<?php echo esc_url( $team_photo ); ?>" alt="<?php echo esc_attr( $team->post_title ); ?>">
				</div>
				<?php endif; ?>

				<!-- Brze stats -->
				<?php if ( $team_pos && $po_tot ) :
					$grp_lbl = $team_pos['ngroups'] > 1 ? 'u skupini ' . preg_replace( '/^(skupina|grupa)\s+/iu', '', $team_pos['group'] ) : 'u skupini'; ?>
				<div class="stat-strip stat-strip--po">
					<?php if ( $po_place ) : ?>
					<div class="stat-box stat-box--place stat-box--<?php echo esc_attr( $po_place[2] ); ?>">
						<div class="stat-box__val"><?php if ( $po_place[2] === 'champ' ) : ?><span aria-hidden="true">🏆</span> <?php endif; ?><?php echo esc_html( $po_place[0] ); ?></div>
						<div class="stat-box__lbl">Plasman · <?php echo esc_html( $po_place[1] ); ?></div>
					</div>
					<?php endif; ?>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo (int) $team_pos['pos']; ?>.<span class="stat-box__sub"><?php echo (int) $team_pos['Pts']; ?> bod.</span></div>
						<div class="stat-box__lbl"><?php echo esc_html( $grp_lbl ); ?></div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $po_tot['W']; ?>&thinsp;/&thinsp;<?php echo $po_tot['D']; ?>&thinsp;/&thinsp;<?php echo $po_tot['L']; ?></div>
						<div class="stat-box__lbl">P / N / I · sve utakmice</div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $po_tot['GF']; ?><span style="font-weight:400;opacity:.4;font-size:.7em;">:<?php echo $po_tot['GA']; ?></span></div>
						<div class="stat-box__lbl">Golovi · sve utakmice</div>
					</div>
				</div>
				<?php elseif ( $team_pos ) : ?>
				<div class="stat-strip">
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['pos']; ?>.</div>
						<div class="stat-box__lbl">Pozicija</div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['Pts']; ?></div>
						<div class="stat-box__lbl">Bodova</div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['W']; ?>&thinsp;/&thinsp;<?php echo $team_pos['D']; ?>&thinsp;/&thinsp;<?php echo $team_pos['L']; ?></div>
						<div class="stat-box__lbl">Pobjede / Remiji / Porazi</div>
					</div>
					<div class="stat-box">
						<div class="stat-box__val"><?php echo $team_pos['GF']; ?><span style="font-weight:400;opacity:.4;font-size:.7em;">:<?php echo $team_pos['GA']; ?></span></div>
						<div class="stat-box__lbl">Golovi</div>
					</div>
				</div>
				<?php elseif ( $division_id ) : ?>
				<div class="kurs-empty" style="margin-bottom:24px;">Nema odigranih utakmica u ovoj sezoni.</div>
				<?php endif; ?>

				<!-- Statistika (prosjeci + razdioba po poluvremenu) -->
				<?php if ( $stats ) :
					$N   = $stats['N'];
					$avg = function ( $x ) use ( $N ) { return $N ? rtrim( rtrim( number_format( $x / $N, 1 ), '0' ), '.' ) : '0'; };
				?>
				<div class="widget team-stats">
					<div class="widget__title"><?php echo $po_tot ? 'Statistika sezone' : 'Statistika lige'; ?> <span class="team-stats__sub"><?php echo (int) $N; ?> odigranih<?php echo $po_tot ? ' · skupina + doigravanje' : ''; ?></span></div>

					<div class="ts-grid">
						<div class="ts-box ts-box--gf"><div class="ts-box__val"><?php echo $avg( $stats['gf'] ); ?></div><div class="ts-box__lbl">Golova zabije</div></div>
						<div class="ts-box ts-box--ga"><div class="ts-box__val"><?php echo $avg( $stats['ga'] ); ?></div><div class="ts-box__lbl">Golova primi</div></div>
						<div class="ts-box ts-box--fl"><div class="ts-box__val"><?php echo $avg( $stats['fouls'] ); ?></div><div class="ts-box__lbl">Prekršaja</div></div>
						<div class="ts-box ts-box--yc"><div class="ts-box__val"><?php echo $avg( $stats['yellow'] ); ?></div><div class="ts-box__lbl">Žutih</div></div>
					</div>
					<div class="ts-note">prosjek po utakmici</div>

					<div class="ts-halves">
						<div class="ts-halves__head">
							<span class="ts-halves__title">Po poluvremenu</span>
							<span class="ts-halves__leg"><i class="ts-leg ts-leg--1"></i>1. pol. <i class="ts-leg ts-leg--2"></i>2. pol.</span>
						</div>
						<?php
						$hrows = [
							[ 'Golovi zabijeni',  $stats['half'][1]['gf'],    $stats['half'][2]['gf'] ],
							[ 'Golovi primljeni', $stats['half'][1]['ga'],    $stats['half'][2]['ga'] ],
							[ 'Prekršaji',        $stats['half'][1]['fouls'], $stats['half'][2]['fouls'] ],
						];
						foreach ( $hrows as $hr ) :
							$tot = $hr[1] + $hr[2];
							$p1  = $tot ? round( $hr[1] / $tot * 100 ) : 50;
						?>
						<div class="ts-hrow">
							<div class="ts-hrow__lbl"><?php echo esc_html( $hr[0] ); ?></div>
							<div class="ts-hbar">
								<div class="ts-hbar__1" style="width:<?php echo (int) $p1; ?>%;"><?php if ( $hr[1] ) echo (int) $hr[1]; ?></div>
								<div class="ts-hbar__2" style="width:<?php echo 100 - (int) $p1; ?>%;"><?php if ( $hr[2] ) echo (int) $hr[2]; ?></div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="ts-extra">
						<span><strong><?php echo (int) $stats['cs']; ?></strong> čistih mreža</span>
						<span><strong><?php echo (int) $stats['btts']; ?></strong>× obje zabile</span>
						<span><strong><?php echo (int) $stats['red']; ?></strong> crvenih</span>
						<?php if ( $stats['big_win'] > 0 ) : ?><span>najveća pobjeda <strong>+<?php echo (int) $stats['big_win']; ?></strong></span><?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Forma -->
				<?php if ( $form ) : ?>
				<div class="widget" style="margin-bottom:20px;">
					<?php $fn = count( $form ); ?>
					<div class="widget__title">Forma (<?php echo $fn === 1 ? 'zadnja utakmica' : ( $fn < 5 ? "zadnje $fn utakmice" : "zadnjih $fn utakmica" ); ?>)</div>
					<div style="display:flex;gap:6px;padding:12px 0 4px;flex-wrap:wrap;">
					<?php foreach ( array_reverse( $form ) as $fm ) :
						$home = (int) $fm->home_team_id === $team_id;
						$gf   = $home ? (int) $fm->home_score : (int) $fm->away_score;
						$ga   = $home ? (int) $fm->away_score : (int) $fm->home_score;
						if ( $gf > $ga )      { $r = 'P'; $bg = 'var(--c-win)'; }
						elseif ( $gf < $ga )  { $r = 'I'; $bg = 'var(--c-loss)'; }
						else                  { $r = 'N'; $bg = 'var(--c-draw)'; }
					?>
					<a href="<?php echo esc_url( add_query_arg( 'match_id', $fm->id, home_url( '/utakmica/' ) ) ); ?>"
					   title="<?php echo "$gf : $ga"; ?>"
					   style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:5px;background:<?php echo $bg; ?>;color:#fff;font-family:var(--font-mono);font-size:13px;font-weight:700;text-decoration:none;">
						<?php echo $r; ?>
					</a>
					<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Sljedeća utakmica -->
				<?php if ( $next ) : ?>
				<div class="next-match-card">
					<div class="next-match-card__label">Sljedeća &middot; <?php echo esc_html( kurs_round_label( $next->round, $next->po_round ) ); ?></div>
					<div class="next-match-card__teams">
						<span><?php echo kurs_team_link( (int) $next->home_team_id, $next->home_name, (int) $division_id ); ?></span>
						<span class="next-match-card__vs">vs</span>
						<span><?php echo kurs_team_link( (int) $next->away_team_id, $next->away_name, (int) $division_id ); ?></span>
					</div>
					<?php if ( $next->match_date ) : ?>
					<div class="next-match-card__date">
						<?php echo esc_html( date_i18n( 'j.n.Y. · H:i', strtotime( $next->match_date ) ) ); ?>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<!-- Utakmice sezone (1.0.56): kolo · termin · protivnik (doma/gost) · rezultat P/N/I -->
				<?php if ( $all_matches ) :
					$tm_dani = [ 'ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub' ];
					$tm_sum  = [ 'P' => 0, 'N' => 0, 'I' => 0 ];
					foreach ( $all_matches as $m ) {
						if ( $m->status !== 'finished' ) continue;
						$h = (int) $m->home_team_id === $team_id; $gf = $h ? (int) $m->home_score : (int) $m->away_score; $ga = $h ? (int) $m->away_score : (int) $m->home_score;
						$tm_sum[ $gf > $ga ? 'P' : ( $gf < $ga ? 'I' : 'N' ) ]++;
					}
				?>
				<section class="tm-card" style="margin-top:24px;">
					<div class="tm-card__hd">
						<span>Utakmice · <?php echo esc_html( $active_ctx ? ekipa_ctx_label( $active_ctx ) : 'sezona' ); ?></span>
						<em><?php echo array_sum( $tm_sum ) ? esc_html( $tm_sum['P'] . ' P · ' . $tm_sum['N'] . ' N · ' . $tm_sum['I'] . ' I' ) : esc_html( count( $all_matches ) . ' utakmica' ); ?></em>
					</div>
					<div class="tm-list">
					<?php $po_shown = false; foreach ( $all_matches as $m ) :
						if ( $m->po_round && ! $po_shown ) : $po_shown = true; ?>
						<div class="tm-sep"><span>Doigravanje</span></div>
						<?php endif;
						$home = (int) $m->home_team_id === $team_id;
						$oid  = $home ? (int) $m->away_team_id : (int) $m->home_team_id;
						$onm  = $home ? $m->away_name : $m->home_name;
						$fin  = $m->status === 'finished';
						$gf   = $home ? (int) $m->home_score : (int) $m->away_score;
						$ga   = $home ? (int) $m->away_score : (int) $m->home_score;
						$res  = $fin ? ( $gf > $ga ? 'P' : ( $gf < $ga ? 'I' : 'N' ) ) : '';
						$ts   = $m->match_date ? strtotime( $m->match_date ) : 0;
						$hm   = $ts && date( 'H:i', $ts ) !== '00:00' ? date( 'H:i', $ts ) : '';
						[ $rl_big, $rl_small ] = function_exists( 'kurs_round_short' ) ? kurs_round_short( $m->round, $m->po_round ) : [ esc_html( (int) $m->round . '.' ), 'kolo' ];
					?>
					<div class="tm-row has-row-link<?php echo $res ? ' tm-row--' . strtolower( $res ) : ' tm-row--up'; ?>">
						<a class="kc-row-link" href="<?php echo esc_url( add_query_arg( 'match_id', $m->id, home_url( '/utakmica/' ) ) ); ?>" aria-label="<?php echo esc_attr( $m->home_name . ' – ' . $m->away_name ); ?>"></a>
						<span class="tm-row__rd<?php echo $m->po_round ? ' tm-row__rd--po tm-row__rd--' . esc_attr( $m->po_round ) : ''; ?>" title="<?php echo esc_attr( kurs_round_label( $m->round, $m->po_round ) ); ?>"><b><?php echo $rl_big; ?></b><small><?php echo esc_html( $rl_small ); ?></small></span>
						<span class="tm-row__date"><?php echo $ts ? esc_html( $tm_dani[ (int) date( 'w', $ts ) ] . ' ' . date( 'j.n.', $ts ) ) : 'termin naknadno'; ?><?php if ( $hm ) : ?><small><?php echo esc_html( $hm ); ?></small><?php endif; ?></span>
						<span class="tm-row__opp">
							<?php echo kurs_team_badge_html( $oid, 30 ); ?>
							<span class="tm-row__opp-txt"><small><?php echo $home ? 'doma · protiv' : 'u gostima · protiv'; ?></small><b><?php echo kurs_team_link( $oid, $onm, (int) $division_id ); ?></b></span>
						</span>
						<span class="tm-row__res">
							<?php if ( $fin ) : ?><i class="tm-res tm-res--<?php echo strtolower( $res ); ?>" title="<?php echo [ 'P' => 'Pobjeda', 'N' => 'Neriješeno', 'I' => 'Poraz' ][ $res ]; ?>"><?php echo $res; ?></i><b><?php echo $gf; ?>:<?php echo $ga; ?><?php if ( $m->po_round && $m->home_pen !== null && $m->away_pen !== null ) : $pf = $home ? (int) $m->home_pen : (int) $m->away_pen; $pa = $home ? (int) $m->away_pen : (int) $m->home_pen; ?><small class="tm-pen<?php echo $pf > $pa ? ' is-won' : ''; ?>" title="Izvođenje jedanaesteraca"><?php echo $pf > $pa ? 'prošli' : 'ispali'; ?> · pen. <?php echo $pf . ':' . $pa; ?></small><?php endif; ?></b>
							<?php else : ?><em><?php echo $hm ? esc_html( $hm ) : '–'; ?></em><?php endif; ?>
						</span>
					</div>
					<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

			</div><!-- /.team-main -->

			<!-- ── DESNO: roster + pozicija ─────────────────────── -->
			<aside class="team-side">

				<!-- Roster (1.0.56): kompaktna lista — broj ili inicijali u boji ekipe, oznake GLM / C, golovi -->
				<?php $rs_cap = (int) get_post_meta( $team_id, '_kurs_team_captain', true ); $rs_col = kurs_team_color( $team_id ); $rs_txt = kurs_team_text_color( $rs_col ); ?>
				<section class="tm-card">
					<div class="tm-card__hd"><span>Roster</span><em><?php echo esc_html( ( $active_ctx ? ekipa_ctx_label( $active_ctx ) : '' ) . ( $squad ? ' · ' . count( $squad ) . ' igrača' : '' ) ); ?></em></div>
					<?php if ( $squad ) : ?>
					<div class="tm-roster">
					<?php foreach ( $squad as $pl ) :
						$pid   = (int) $pl->player_id;
						$goals = $scorer_map[ $pid ] ?? 0;
						$nm    = preg_split( '/\s+/u', trim( $pl->player_name ) );
						$ini   = mb_strtoupper( count( $nm ) > 1 ? mb_substr( $nm[0], 0, 1 ) . mb_substr( end( $nm ), 0, 1 ) : mb_substr( $nm[0], 0, 2 ) );
					?>
					<a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" class="tm-pl">
						<span class="tm-pl__no" style="background:<?php echo esc_attr( $rs_col ); ?>;color:<?php echo esc_attr( $rs_txt ); ?>"><?php echo $pl->jersey_no ? (int) $pl->jersey_no : esc_html( $ini ); ?></span>
						<span class="tm-pl__name"><?php echo esc_html( $pl->player_name ); ?>
							<?php if ( kurs_player_type( $pid ) === 'goalkeeper' ) : ?><em class="tm-tag">GLM</em><?php endif; ?>
							<?php if ( $rs_cap === $pid ) : ?><em class="tm-tag tm-tag--c">C</em><?php endif; ?>
						</span>
						<?php if ( $goals ) : ?><span class="tm-pl__g" title="Golovi u ovoj sezoni"><i aria-hidden="true"></i><?php echo (int) $goals; ?></span><?php endif; ?>
					</a>
					<?php endforeach; ?>
					</div>
					<?php else : ?>
					<p class="tm-empty">Roster još nije unesen za ovu sezonu.</p>
					<?php endif; ?>
				</section>

				<!-- Pozicija -->
				<?php if ( $team_pos && $active_ctx ) :
					$liga_slug = str_contains( strtolower( $active_ctx->league ), 'pan' ) ? 'pan-ljetna' : 'kc-liga';
				?>
				<div class="widget" style="margin-top:16px;">
					<div class="widget__title">Tablica</div>
					<div style="display:flex;align-items:baseline;gap:6px;padding:12px 0 4px;">
						<span style="font-family:var(--font-cond);font-weight:800;font-style:italic;font-size:52px;line-height:1;color:<?php echo esc_attr( ekipa_league_color( $active_ctx->league ) ); ?>;">
							<?php echo $team_pos['pos']; ?>.
						</span>
						<span style="font-size:13px;color:var(--c-muted);">od <?php echo $team_pos['total']; ?></span>
					</div>
					<div style="font-size:11px;color:var(--c-muted);font-family:var(--font-mono);margin-bottom:12px;">
						<?php echo $team_pos['Pts']; ?> bod · GR <?php echo ( $team_pos['GD'] >= 0 ? '+' : '' ) . $team_pos['GD']; ?>
					</div>
					<a href="<?php echo esc_url( kurs_league_url( (int) $division_id, [ 'tab' => 'tablica' ] ) . '#liga' ); /* sezona odabrane lige, ne aktualna */ ?>"
					   style="font-family:var(--font-cond);font-weight:700;font-size:12px;text-transform:uppercase;color:var(--c-muted);">
						Puna tablica &rarr;
					</a>
				</div>
				<?php endif; ?>

				<!-- Stožer (trener / predstavnik) -->
				<?php $t_coach = kurs_team_coach( $team_id ); $t_rep = kurs_team_rep( $team_id ); ?>
				<?php if ( $t_coach || $t_rep ) : ?>
				<div class="widget" style="margin-top:16px;">
					<div class="widget__title">Stožer</div>
					<div class="team-officials">
						<?php if ( $t_coach ) : ?>
						<div class="team-officials__row">
							<span class="team-officials__role">Trener</span>
							<span class="team-officials__name"><?php echo esc_html( $t_coach['name'] ); ?></span>
							<?php if ( $t_coach['phone'] ) : ?><span class="team-officials__phone"><?php echo esc_html( $t_coach['phone'] ); ?></span><?php endif; ?>
						</div>
						<?php endif; ?>
						<?php if ( $t_rep ) : ?>
						<div class="team-officials__row">
							<span class="team-officials__role">Predstavnik</span>
							<span class="team-officials__name"><?php echo esc_html( $t_rep['name'] ); ?></span>
							<?php if ( $t_rep['phone'] ) : ?><span class="team-officials__phone"><?php echo esc_html( $t_rep['phone'] ); ?></span><?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

			</aside><!-- /.team-side -->

		</div><!-- /.team-body -->
	</div>

	<!-- ── Sponzorski baneri (može ih biti više) ─────────────── -->
	<?php $team_banners = kurs_team_banners( $team_id, 'large' ); ?>
	<?php if ( $team_banners ) : ?>
	<div class="container team-banner-wrap">
		<div class="team-banner__label"><?php echo count( $team_banners ) > 1 ? 'Sponzori ekipe' : 'Sponzor ekipe'; ?></div>
		<?php foreach ( $team_banners as $bn ) : ?>
			<?php if ( $bn['link'] ) : ?>
			<a href="<?php echo esc_url( $bn['link'] ); ?>" target="_blank" rel="noopener nofollow sponsored" class="team-banner">
				<img src="<?php echo esc_url( $bn['url'] ); ?>" alt="<?php echo esc_attr( $team->post_title ); ?> — sponzor">
			</a>
			<?php else : ?>
			<div class="team-banner">
				<img src="<?php echo esc_url( $bn['url'] ); ?>" alt="<?php echo esc_attr( $team->post_title ); ?> — sponzor">
			</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

</div><!-- /.team-profile -->

<?php get_footer(); ?>
