<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Match {

	private static function t( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . $name;
	}

	// ── Utakmice ──────────────────────────────────────────────────────────────

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT m.*, h.post_title AS home_name, a.post_title AS away_name
				 FROM '  . self::t( 'matches' ) . ' m
				 JOIN '  . $wpdb->posts         . ' h ON h.ID = m.home_team_id
				 JOIN '  . $wpdb->posts         . ' a ON a.ID = m.away_team_id
				 WHERE m.id = %d',
				$id
			)
		);
	}

	public static function get_by_group( int $group_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT m.*, h.post_title AS home_name, a.post_title AS away_name
				 FROM '  . self::t( 'matches' ) . ' m
				 JOIN '  . $wpdb->posts         . ' h ON h.ID = m.home_team_id
				 JOIN '  . $wpdb->posts         . ' a ON a.ID = m.away_team_id
				 WHERE m.group_id = %d
				 ORDER BY m.round, m.match_date, m.id',
				$group_id
			)
		) ?: [];
	}

	public static function create( int $group_id, int $home, int $away, ?string $date = null, int $round = 1 ): int|false {
		global $wpdb;
		$ok = $wpdb->insert( self::t( 'matches' ), [
			'group_id'     => $group_id,
			'home_team_id' => $home,
			'away_team_id' => $away,
			'match_date'   => $date,
			'round'        => $round,
			'status'       => 'scheduled',
		] );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function finish( int $id, int $home_score, int $away_score ): void {
		global $wpdb;
		$wpdb->update( self::t( 'matches' ), [
			'home_score' => $home_score,
			'away_score' => $away_score,
			'status'     => 'finished',
		], [ 'id' => $id ] );
	}

	public static function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::t( 'match_events' ), [ 'match_id' => $id ] );
		$wpdb->delete( self::t( 'matches' ),      [ 'id' => $id ] );
	}

	// ── Događaji ──────────────────────────────────────────────────────────────

	public static function add_event( int $match_id, string $type, int $team_id, ?int $player_id = null, ?int $minute = null ): int|false {
		global $wpdb;
		$ok = $wpdb->insert( self::t( 'match_events' ), [
			'match_id'   => $match_id,
			'team_id'    => $team_id,
			'player_id'  => $player_id,
			'event_type' => $type,
			'minute'     => $minute,
		] );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function get_events( int $match_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.*, p.post_title AS player_name
				 FROM '       . self::t( 'match_events' ) . ' e
				 LEFT JOIN '  . $wpdb->posts              . ' p ON p.ID = e.player_id
				 WHERE e.match_id = %d
				 ORDER BY e.minute, e.id',
				$match_id
			)
		) ?: [];
	}

	public static function delete_event( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::t( 'match_events' ), [ 'id' => $id ] );
	}

	// ── Tablica bodova ────────────────────────────────────────────────────────
	// Računamo dynamično iz završenih utakmica. Pobjeda = 3, Remi = 1, Poraz = 0.

	public static function get_standings( int $group_id ): array {
		global $wpdb;

		$teams = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT gt.team_id, p.post_title AS team_name
				 FROM '  . self::t( 'group_teams' ) . ' gt
				 JOIN '  . $wpdb->posts             . ' p ON p.ID = gt.team_id
				 WHERE gt.group_id = %d',
				$group_id
			)
		);
		if ( ! $teams ) return [];

		$matches = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT home_team_id, away_team_id, home_score, away_score
				 FROM '  . self::t( 'matches' ) . '
				 WHERE group_id = %d AND status = %s',
				$group_id, 'finished'
			)
		);

		$rows = [];
		foreach ( $teams as $t ) {
			$rows[ $t->team_id ] = [
				'team_id'   => (int) $t->team_id,
				'team_name' => $t->team_name,
				'P'   => 0, 'W'  => 0, 'D'  => 0, 'L'  => 0,
				'GF'  => 0, 'GA' => 0, 'GD' => 0, 'Pts' => 0,
			];
		}

		foreach ( $matches as $m ) {
			$h  = (int) $m->home_team_id;
			$a  = (int) $m->away_team_id;
			$hs = (int) $m->home_score;
			$as = (int) $m->away_score;

			if ( ! isset( $rows[ $h ], $rows[ $a ] ) ) continue;

			$rows[ $h ]['P']++; $rows[ $h ]['GF'] += $hs; $rows[ $h ]['GA'] += $as;
			$rows[ $a ]['P']++; $rows[ $a ]['GF'] += $as; $rows[ $a ]['GA'] += $hs;

			if ( $hs > $as ) {
				$rows[ $h ]['W']++; $rows[ $h ]['Pts'] += 3; $rows[ $a ]['L']++;
			} elseif ( $hs < $as ) {
				$rows[ $a ]['W']++; $rows[ $a ]['Pts'] += 3; $rows[ $h ]['L']++;
			} else {
				$rows[ $h ]['D']++; $rows[ $h ]['Pts']++;
				$rows[ $a ]['D']++; $rows[ $a ]['Pts']++;
			}
		}

		foreach ( $rows as &$r ) $r['GD'] = $r['GF'] - $r['GA'];
		unset( $r );

		// Sortiranje: Bodovi → Gol razlika → Golovi za
		usort( $rows, fn( $a, $b ) =>
			$b['Pts'] <=> $a['Pts'] ?: $b['GD'] <=> $a['GD'] ?: $b['GF'] <=> $a['GF']
		);

		return array_values( $rows );
	}

	// ── Statistika strijelaca ─────────────────────────────────────────────────

	public static function get_top_scorers( int $division_id, int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.player_id, p.post_title AS player_name,
				        t.post_title AS team_name, COUNT(*) AS goals
				 FROM '      . self::t( 'match_events' ) . ' e
				 JOIN '      . self::t( 'matches' )      . ' m  ON m.id  = e.match_id
				 JOIN '      . self::t( 'groups' )       . ' g  ON g.id  = m.group_id
				 JOIN '      . self::t( 'phases' )       . ' ph ON ph.id = g.phase_id
				 LEFT JOIN ' . $wpdb->posts               . ' p  ON p.ID  = e.player_id
				 LEFT JOIN ' . $wpdb->posts               . ' t  ON t.ID  = e.team_id
				 WHERE ph.division_id = %d
				   AND e.event_type IN (\'goal\', \'own_goal\')
				   AND m.status = \'finished\'
				   AND e.player_id IS NOT NULL
				 GROUP BY e.player_id
				 ORDER BY goals DESC
				 LIMIT %d',
				$division_id, $limit
			)
		) ?: [];
	}
}
