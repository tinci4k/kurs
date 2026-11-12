<?php
/**
 * Dodatne sekcije naslovnice (tema 1.0.55) — ispod „Najnovije":
 *   Liga u brojkama · Ekipe sezone (grbovi obje lige) · Zlatna kopačka (aktualna sezona, inače prošla)
 *   · Aktualni prvaci (zadnja zaključana sezona svakog natjecanja) · Pratite nas + Gdje igramo.
 * Sve iz baze lige; keš 10 min, ruši se na kurs_data_changed.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'kurs_data_changed', function () { delete_transient( 'kurskc_home_extra' ); } );

function kurskc_home_extra(): string {
	if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Match' ) ) return '';
	$cached = get_transient( 'kurskc_home_extra' );
	if ( is_string( $cached ) ) return $cached;
	$html = kurskc_home_extra_build();
	set_transient( 'kurskc_home_extra', $html, 10 * MINUTE_IN_SECONDS );
	return $html;
}

/** Ekipe lige (jedna ili više skupina) — [ team_id => naziv ]. */
function kurskc_he_teams( int $did ): array {
	$out = [];
	$ph  = Kurs_Season::group_phase( $did );
	foreach ( $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [] as $g ) {
		foreach ( Kurs_Season::get_group_teams( (int) $g->id ) as $t ) $out[ (int) $t->team_id ] = $t->team_name;
	}
	asort( $out, SORT_LOCALE_STRING );
	return $out;
}

/** Prvak lige: pobjednik finala playoffa ili prvi na ljestvici (liga s jednom skupinom). */
function kurskc_he_champion( int $did ): int {
	if ( class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( $did ) ) {
		$final = Kurs_Playoff::get_bracket( $did )['final'] ?? [];
		$f     = $final ? reset( $final ) : null;
		$side  = $f ? Kurs_Playoff::winner_side( $f ) : null;
		return $side ? (int) ( $side === 'home' ? $f->home_team_id : $f->away_team_id ) : 0;
	}
	$ph = Kurs_Season::group_phase( $did );
	$gs = $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [];
	return count( $gs ) === 1 ? (int) ( Kurs_Match::get_standings( (int) $gs[0]->id )[0]['team_id'] ?? 0 ) : 0;
}

function kurskc_home_extra_build(): string {
	global $wpdb;
	$p   = $wpdb->prefix . 'kurs_';
	$kc  = Kurs_Season::current_for_league( false );
	$kcd = $kc ? Kurs_Season::get_divisions( (int) $kc->id ) : [];

	// ── Brojke ──
	$team_n   = 0;
	foreach ( $kcd as $d ) $team_n += count( kurskc_he_teams( (int) $d->id ) );
	$ids      = implode( ',', array_map( fn( $d ) => (int) $d->id, $kcd ) ) ?: '0';
	$player_n = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT player_id) FROM {$p}squads WHERE division_id IN ($ids)" );
	$tot      = $wpdb->get_row( "SELECT COUNT(*) AS n, COALESCE(SUM(home_score + away_score),0) AS g FROM {$p}matches WHERE status = 'finished'" );
	$season_no = $kc ? max( 1, (int) $kc->year - 2011 ) : 0; // 2025/26 = 14. sezona (izvještaji KC lige)

	// ── Zlatna kopačka: aktualna sezona, a dok nema golova — prošla ──
	$sc_season = $kc;
	$sc_divs   = $kcd;
	$has_goals = (bool) array_filter( $kcd, fn( $d ) => Kurs_Match::get_top_scorers( (int) $d->id, 1 ) );
	if ( ! $has_goals ) {
		foreach ( Kurs_Season::for_league( false ) as $s ) {
			if ( $kc && (int) $s->id === (int) $kc->id ) continue;
			$sdivs = Kurs_Season::get_divisions( (int) $s->id );
			if ( array_filter( $sdivs, fn( $d ) => Kurs_Match::get_top_scorers( (int) $d->id, 1 ) ) ) { $sc_season = $s; $sc_divs = $sdivs; break; }
		}
	}

	// ── Prvaci: zadnja zaključana sezona svakog natjecanja ──
	$champs = [];
	foreach ( [ false, true ] as $is_pan ) {
		foreach ( Kurs_Season::for_league( $is_pan ) as $s ) {
			if ( empty( $s->locked ) ) continue;
			foreach ( Kurs_Season::get_divisions( (int) $s->id ) as $d ) {
				$c = kurskc_he_champion( (int) $d->id );
				if ( $c ) $champs[] = [ 'team' => $c, 'div' => $d, 'season' => $s, 'pan' => $is_pan ];
			}
			break;
		}
	}

	ob_start(); ?>

	<!-- ── LIGA U BROJKAMA ── -->
	<section class="he-stats" aria-label="Liga u brojkama">
		<?php if ( $season_no ) : ?><div class="he-stat"><b><?php echo (int) $season_no; ?>.</b><span>sezona KC lige</span></div><?php endif; ?>
		<?php if ( $team_n ) : ?><div class="he-stat"><b><?php echo (int) $team_n; ?></b><span>ekipa u <?php echo count( $kcd ) > 1 ? 'dvije lige' : 'ligi'; ?></span></div><?php endif; ?>
		<?php if ( $player_n ) : ?><div class="he-stat"><b><?php echo (int) $player_n; ?></b><span>prijavljenih igrača</span></div><?php endif; ?>
		<div class="he-stat"><b><?php echo number_format_i18n( (int) $tot->n ); ?></b><span>odigranih utakmica u arhivi</span></div>
		<div class="he-stat"><b><?php echo number_format_i18n( (int) $tot->g ); ?></b><span>golova u arhivi</span></div>
	</section>

	<?php if ( $kcd ) : ?>
	<!-- ── EKIPE SEZONE ── -->
	<div class="sec-head">
		<h2 class="sec-head__title">Ekipe sezone <?php echo esc_html( $kc->name ? '· ' . preg_replace( '/^KC Liga\s*/i', '', $kc->name ) : '' ); ?></h2>
		<a href="<?php echo esc_url( kurs_league_url( (int) $kcd[0]->id, [ 'tab' => 'tablica' ] ) . '#liga' ); ?>" class="sec-head__link">Tablice &rarr;</a>
	</div>
	<div class="he-teams">
		<?php foreach ( $kcd as $d ) : $tone = stripos( $d->name, 'briga' ) !== false ? 'lbb' : 'kc'; ?>
		<div class="he-teams__col he-teams__col--<?php echo $tone; ?>">
			<h3 class="he-teams__title"><?php echo kurs_league_icon( $d->name, 22 ); ?><?php echo esc_html( $d->name ); ?></h3>
			<div class="he-teams__grid">
				<?php foreach ( kurskc_he_teams( (int) $d->id ) as $tid => $tname ) : ?>
				<a class="he-team" href="<?php echo esc_url( kurs_team_url( $tid, (int) $d->id ) ); ?>">
					<?php echo kurs_team_badge_html( $tid, 40 ); ?>
					<span><?php echo esc_html( $tname ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="he-duo">
		<?php if ( $sc_divs ) : ?>
		<!-- ── ZLATNA KOPAČKA ── -->
		<section class="he-box">
			<div class="he-box__hd">
				<h2 class="sec-head__title">Zlatna kopačka</h2>
				<span class="he-box__sub"><?php echo $sc_season && $kc && (int) $sc_season->id !== (int) $kc->id ? 'prošla sezona · ' . esc_html( $sc_season->name ) : esc_html( $sc_season->name ?? '' ); ?></span>
			</div>
			<div class="he-scorers">
				<?php foreach ( $sc_divs as $d ) :
					$list = Kurs_Match::get_top_scorers( (int) $d->id, 5 );
					if ( ! $list ) continue; ?>
				<div class="he-scorers__col">
					<div class="he-scorers__lg"><?php echo function_exists( 'kurs_division_chip' ) ? kurs_division_chip( (int) $d->id ) : ''; ?> <?php echo esc_html( $d->name ); ?></div>
					<?php foreach ( $list as $i => $sc ) : ?>
					<div class="he-sc<?php echo $i === 0 ? ' is-first' : ''; ?>">
						<span class="he-sc__pos"><?php echo $i + 1; ?></span>
						<?php echo kurs_team_badge_html( (int) ( $sc->team_id ?? 0 ), 26 ); ?>
						<span class="he-sc__name"><?php echo kurs_player_link( (int) $sc->player_id, $sc->player_name ?: '—' ); ?><small><?php echo esc_html( $sc->team_name ?? '' ); ?></small></span>
						<b class="he-sc__g"><?php echo (int) $sc->goals; ?></b>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( $champs ) : ?>
		<!-- ── AKTUALNI PRVACI ── -->
		<section class="he-box he-box--gold">
			<div class="he-box__hd"><h2 class="sec-head__title">Aktualni prvaci</h2></div>
			<div class="he-champs">
				<?php foreach ( $champs as $c ) : ?>
				<a class="he-champ" href="<?php echo esc_url( kurs_team_url( $c['team'], (int) $c['div']->id ) ); ?>">
					<span class="he-champ__cup" aria-hidden="true">🏆</span>
					<?php echo kurs_team_badge_html( $c['team'], 46 ); ?>
					<span class="he-champ__txt">
						<small><?php echo esc_html( $c['pan'] ? $c['season']->name : $c['div']->name . ' · ' . preg_replace( '/^KC Liga\s*/i', '', $c['season']->name ) ); ?></small>
						<b><?php echo esc_html( get_the_title( $c['team'] ) ); ?></b>
					</span>
				</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>
	</div>

	<!-- ── PRATITE NAS + GDJE IGRAMO ── -->
	<div class="he-duo he-duo--bottom">
		<?php if ( function_exists( 'kurskc_follow_box' ) ) echo kurskc_follow_box( 'card' ); ?>
		<section class="he-venue">
			<div class="he-venue__txt">
				<b>Gdje igramo</b>
				<span><strong>KC liga</strong> · Sportska dvorana Srednje škole Koprivnica, Trg slobode 7 — subotom od 16.30</span>
				<span><strong>Pan Ljetna liga</strong> · ljeto, na travi — Koprivnica</span>
			</div>
			<a class="he-venue__btn" href="https://maps.app.goo.gl/6j3qPHXP2tzxmGFJ7" target="_blank" rel="noopener">Karta &rarr;</a>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
