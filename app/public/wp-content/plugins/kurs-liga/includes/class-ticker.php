<?php
defined( 'ABSPATH' ) || exit;

/**
 * Globalni "Live Ticker" za header (kurskc.hr).
 *
 * Status i sadržaj se računaju ISKLJUČIVO iz rasporeda (match_date) i zaključanih
 * zapisnika (status='finished'). Nema "Start match" gumbe ni praćenja minute.
 *
 * Statusi:
 *   live      — danas je dan utakmica (prikaži današnje: u tijeku → odigrane → najava)
 *   results   — runda je odigrana; rezultati do ~2 dana prije sljedeće runde
 *   upcoming  — 1–2 dana prije sljedeće runde (najava fixtura)
 *   champions — off-season: nema budućih ni svježih utakmica → podij zadnje lige
 *
 * Rezultat se kešira u transientu s TTL-om koji ovisi o statusu (LIVE kratko,
 * off-season dugo). Cache se ruši na `kurs_data_changed` (rezultat/raspored).
 */
class Kurs_Ticker {

	const TRANSIENT     = 'kurs_ticker_state_v1';
	const OPT_OFFSEASON = 'kurs_ticker_offseason'; // 'champions' | 'hidden'

	/** TTL po statusu (sekunde) — adaptivno osvježavanje. */
	const TTL = [
		'live'      => 60,
		'upcoming'  => 600,
		'results'   => 1800,
		'champions' => 21600,
		'none'      => 1800,
	];

	public static function init(): void {
		// Promjena rezultata/rasporeda → očisti keš (vidi do_action u Kurs_Match/Kurs_Playoff).
		add_action( 'kurs_data_changed', [ __CLASS__, 'flush' ] );
	}

	public static function flush(): void {
		delete_transient( self::TRANSIENT );
	}

	private static function t( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . $name;
	}

	// ── Javni API ─────────────────────────────────────────────────────────────

	/** Keširano stanje: [ 'status', 'label', 'items' => [ … ] ]. */
	public static function get_state(): array {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['status'] ) ) return $cached;

		$state = self::compute();
		set_transient( self::TRANSIENT, $state, self::TTL[ $state['status'] ] ?? 600 );
		return $state;
	}

	/** Treba li prikazati ticker (poštuje off-season postavku 'hidden'). */
	public static function is_visible( array $state ): bool {
		if ( empty( $state['items'] ) ) return false;
		if ( $state['status'] === 'champions'
			&& get_option( self::OPT_OFFSEASON, 'champions' ) === 'hidden' ) {
			return false;
		}
		return true;
	}

	// ── Računanje stanja ────────────────────────────────────────────────────

	/** SELECT za utakmicu + nazivi ekipa + liga (samo grupne utakmice). */
	private static function match_select(): string {
		global $wpdb;
		$posts = $wpdb->posts;
		return "SELECT m.id, m.match_date, m.status, m.home_score, m.away_score, m.round,
		               h.post_title AS home_name, a.post_title AS away_name, s.league AS league
		        FROM " . self::t( 'matches' ) . " m
		        JOIN $posts h ON h.ID = m.home_team_id
		        JOIN $posts a ON a.ID = m.away_team_id
		        JOIN " . self::t( 'groups' )    . " g  ON g.id  = m.group_id
		        JOIN " . self::t( 'phases' )    . " ph ON ph.id = g.phase_id
		        JOIN " . self::t( 'divisions' ) . " d  ON d.id  = ph.division_id
		        JOIN " . self::t( 'seasons' )   . " s  ON s.id  = d.season_id ";
	}

	private static function compute(): array {
		global $wpdb;

		$now        = current_time( 'mysql' );    // lokalno WP vrijeme
		$today      = current_time( 'Y-m-d' );
		$now_ts     = strtotime( $now );

		// Današnje utakmice (matchday)?
		$today_matches = $wpdb->get_results( $wpdb->prepare(
			self::match_select() . " WHERE m.match_date >= %s AND m.match_date <= %s
			 ORDER BY m.match_date, m.id",
			$today . ' 00:00:00', $today . ' 23:59:59'
		) );
		if ( ! empty( $today_matches ) ) {
			return self::state_live( $today_matches, $now_ts );
		}

		$next_date = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(match_date) FROM " . self::t( 'matches' ) . "
			 WHERE match_date > %s AND status = 'scheduled'",
			$now
		) );
		$last_finished = $wpdb->get_var(
			"SELECT MAX(match_date) FROM " . self::t( 'matches' ) . " WHERE status = 'finished'"
		);

		if ( $next_date && ( strtotime( $next_date ) - $now_ts ) <= 2 * DAY_IN_SECONDS ) {
			return self::state_upcoming( $now );
		}
		if ( $last_finished && ( $now_ts - strtotime( $last_finished ) ) <= 10 * DAY_IN_SECONDS ) {
			return self::state_results();
		}
		if ( $next_date ) {
			return self::state_upcoming( $now );
		}
		return self::state_champions();
	}

	// ── Status builderi ───────────────────────────────────────────────────────

	private static function state_live( array $today, int $now_ts ): array {
		$in_progress = $finished = $upcoming = [];
		foreach ( $today as $m ) {
			if ( $m->status === 'finished' ) {
				$finished[] = self::item_finished( $m );
			} elseif ( strtotime( $m->match_date ) <= $now_ts ) {
				$in_progress[] = self::item_live( $m );
			} else {
				$upcoming[] = self::item_upcoming( $m, true );
			}
		}
		$items = array_merge( $in_progress, $finished, $upcoming );
		return [ 'status' => 'live', 'label' => '● Uživo', 'items' => $items ];
	}

	private static function state_results(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			self::match_select() . " WHERE m.status = 'finished'
			 ORDER BY m.match_date DESC, m.id DESC LIMIT 8"
		);
		$rows  = array_reverse( $rows ); // kronološki za prikaz
		$items = array_map( [ __CLASS__, 'item_finished' ], $rows );
		return [ 'status' => 'results', 'label' => 'Rezultati', 'items' => $items ];
	}

	private static function state_upcoming( string $now ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			self::match_select() . " WHERE m.match_date > %s AND m.status = 'scheduled'
			 ORDER BY m.match_date, m.id LIMIT 8",
			$now
		) );
		$items = array_map( fn( $m ) => self::item_upcoming( $m, false ), $rows );
		return [ 'status' => 'upcoming', 'label' => 'Najava', 'items' => $items ];
	}

	private static function state_champions(): array {
		$p     = self::compute_podium();
		$items = [];
		if ( $p && ! empty( $p['champion'] ) ) {
			$items[] = [ 'kind' => 'champ', 'icon' => '🏆', 'url' => $p['url'],
				'text' => sprintf( 'Čestitke %s — pobjednik natjecanja %s!', $p['champion'], $p['label'] ) ];
			if ( ! empty( $p['second'] ) ) $items[] = [ 'kind' => 'champ', 'icon' => '🥈', 'url' => $p['url'], 'text' => '2. mjesto: ' . $p['second'] ];
			if ( ! empty( $p['third'] ) )  $items[] = [ 'kind' => 'champ', 'icon' => '🥉', 'url' => $p['url'], 'text' => '3. mjesto: ' . $p['third'] ];
		}
		return [ 'status' => 'champions', 'label' => 'Prvaci', 'items' => $items ];
	}

	// ── Item helperi (struktura podataka; render je u temi/header.php) ─────────

	private static function league_short( ?string $league ): string {
		$l = mb_strtolower( (string) $league );
		if ( $l === '' ) return '';
		if ( str_contains( $l, 'pan' ) || str_contains( $l, 'ljet' ) ) return 'LJ';
		if ( str_contains( $l, 'kc' ) ) return 'KC';
		return mb_strtoupper( mb_substr( $league, 0, 2 ) );
	}

	private static function match_url( int $id ): string {
		return add_query_arg( 'match_id', $id, home_url( '/utakmica/' ) );
	}

	private static function schedule_url( ?string $league ): string {
		$l = mb_strtolower( (string) $league );
		$slug = ( str_contains( $l, 'pan' ) || str_contains( $l, 'ljet' ) ) ? 'pan-ljetna' : 'kc-liga';
		return home_url( '/' . $slug . '/?tab=raspored' );
	}

	private static function item_finished( object $m ): array {
		return [
			'kind' => 'finished', 'league' => self::league_short( $m->league ),
			'home' => $m->home_name, 'away' => $m->away_name,
			'home_score' => (int) $m->home_score, 'away_score' => (int) $m->away_score,
			'url' => self::match_url( (int) $m->id ),
		];
	}

	private static function item_live( object $m ): array {
		return [
			'kind' => 'live', 'league' => self::league_short( $m->league ),
			'home' => $m->home_name, 'away' => $m->away_name,
			'note' => 'U TIJEKU', 'url' => self::match_url( (int) $m->id ),
		];
	}

	private static function item_upcoming( object $m, bool $today ): array {
		$time = '';
		if ( $m->match_date ) {
			$ts   = strtotime( $m->match_date );
			$days = [ 'ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub' ]; // neovisno o jeziku WP-a
			$time = $today ? date( 'H:i', $ts ) : $days[ (int) date( 'w', $ts ) ] . ' ' . date( 'j.n. H:i', $ts );
		}
		return [
			'kind' => 'upcoming', 'league' => self::league_short( $m->league ),
			'home' => $m->home_name, 'away' => $m->away_name, 'time' => $time,
			'url' => self::schedule_url( $m->league ),
		];
	}

	// ── Podij zadnje odigrane lige (off-season) ────────────────────────────────

	private static function compute_podium(): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT s.id AS season_id, s.name AS season_name, d.id AS division_id
			 FROM " . self::t( 'seasons' )   . " s
			 JOIN " . self::t( 'divisions' ) . " d  ON d.season_id  = s.id
			 JOIN " . self::t( 'phases' )    . " ph ON ph.division_id = d.id
			 JOIN " . self::t( 'groups' )    . " g  ON g.phase_id    = ph.id
			 JOIN " . self::t( 'matches' )   . " m  ON m.group_id    = g.id
			 WHERE m.status = 'finished'
			 ORDER BY m.match_date DESC LIMIT 1"
		);
		if ( ! $row ) return null;

		$division_id = (int) $row->division_id;
		$champion = $second = $third = null;

		// 1) Playoff finale (ako postoji zaključano)
		if ( class_exists( 'Kurs_Playoff' ) ) {
			$bracket = Kurs_Playoff::get_bracket( $division_id );
			$final   = $bracket['final'][1] ?? null;
			$side    = $final ? Kurs_Playoff::winner_side( $final ) : null;
			if ( $side && $final->home_name && $final->away_name ) {
				$home_wins = $side === 'home';
				$champion  = $home_wins ? $final->home_name : $final->away_name;
				$second    = $home_wins ? $final->away_name : $final->home_name;
			}
		}

		// 2) Tablica (fallback za prvaka i uvijek za 3. mjesto)
		$flat = self::division_standings_flat( $division_id );
		if ( ! $champion ) {
			$champion = $flat[0]['name'] ?? null;
			$second   = $flat[1]['name'] ?? null;
			$third    = $flat[2]['name'] ?? null;
		} else {
			foreach ( $flat as $r ) {
				if ( $r['name'] !== $champion && $r['name'] !== $second ) { $third = $r['name']; break; }
			}
		}
		if ( ! $champion ) return null;

		return [
			'champion' => $champion,
			'second'   => $second,
			'third'    => $third,
			'label'    => $row->season_name,
			'url'      => function_exists( 'kurs_league_url' ) ? kurs_league_url( $division_id ) : home_url( '/kc-liga/' ),
		];
	}

	/** Spljošti sve grupne tablice divizije i sortiraj (Pts → GD → GF). */
	private static function division_standings_flat( int $division_id ): array {
		if ( ! class_exists( 'Kurs_Season' ) || ! class_exists( 'Kurs_Match' ) ) return [];
		$out = [];
		foreach ( Kurs_Season::get_phases( $division_id ) as $ph ) {
			foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
				foreach ( Kurs_Match::get_standings( (int) $g->id ) as $r ) {
					$out[] = [ 'name' => $r['team_name'], 'Pts' => (int) $r['Pts'], 'GD' => (int) $r['GD'], 'GF' => (int) $r['GF'] ];
				}
			}
		}
		usort( $out, fn( $a, $b ) => $b['Pts'] <=> $a['Pts'] ?: $b['GD'] <=> $a['GD'] ?: $b['GF'] <=> $a['GF'] );
		return $out;
	}
}
