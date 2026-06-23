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
		if ( $ok ) do_action( 'kurs_data_changed' );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function finish( int $id, int $home_score, int $away_score ): void {
		global $wpdb;
		$wpdb->update( self::t( 'matches' ), [
			'home_score' => $home_score,
			'away_score' => $away_score,
			'status'     => 'finished',
		], [ 'id' => $id ] );
		do_action( 'kurs_data_changed' );
	}

	public static function reopen( int $id ): void {
		global $wpdb;
		$wpdb->update( self::t( 'matches' ), [ 'status' => 'scheduled' ], [ 'id' => $id ] );
		do_action( 'kurs_data_changed' );
	}

	public static function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::t( 'match_events' ), [ 'match_id' => $id ] );
		$wpdb->delete( self::t( 'matches' ),      [ 'id' => $id ] );
		do_action( 'kurs_data_changed' );
	}

	// ── Događaji ──────────────────────────────────────────────────────────────

	public static function add_event( int $match_id, string $type, int $team_id, ?int $player_id = null, ?int $minute = null, ?int $half = null ): int|false {
		global $wpdb;
		$ok = $wpdb->insert( self::t( 'match_events' ), [
			'match_id'   => $match_id,
			'team_id'    => $team_id,
			'player_id'  => $player_id,
			'event_type' => $type,
			'minute'     => $minute,
			'half'       => $half,
		] );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Akumulirani prekršaji po ekipi i poluvremenu.
	 * Vraća [ team_id => [ 1 => broj_u_1_pol, 2 => broj_u_2_pol ] ].
	 */
	public static function get_foul_counts( int $match_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT team_id, half, COUNT(*) AS cnt
				 FROM '  . self::t( 'match_events' ) . '
				 WHERE match_id = %d AND event_type = %s
				 GROUP BY team_id, half',
				$match_id, 'foul'
			)
		);
		$out = [];
		foreach ( $rows as $r ) {
			$tid  = (int) $r->team_id;
			$half = (int) $r->half ?: 1;
			if ( ! isset( $out[ $tid ] ) ) $out[ $tid ] = [ 1 => 0, 2 => 0 ];
			$out[ $tid ][ $half ] = (int) $r->cnt;
		}
		return $out;
	}

	/**
	 * Ukloni zadnji upisani prekršaj za ekipu u danom poluvremenu (undo misklika).
	 * Vraća true ako je nešto obrisano.
	 */
	public static function delete_last_foul( int $match_id, int $team_id, int $half ): bool {
		global $wpdb;
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::t( 'match_events' ) . '
				 WHERE match_id = %d AND team_id = %d AND event_type = %s AND half = %d
				 ORDER BY id DESC LIMIT 1',
				$match_id, $team_id, 'foul', $half
			)
		);
		if ( ! $id ) return false;
		$wpdb->delete( self::t( 'match_events' ), [ 'id' => $id ] );
		return true;
	}

	// ── Kapetan i prisutnost (spremljeni kao inertni match_events) ────────────
	// event_type='captain' (team_id, player_id) = kapetan u ovoj utakmici.
	// event_type='absent'  (team_id, player_id) = igrač NIJE igrao.
	// Oba se ignoriraju u rezultatu/strijelcima/kartonima/timelineu.

	/** Kapetan tima u utakmici: per-match override → team default (postmeta) → 0. */
	public static function get_match_captain( int $match_id, int $team_id ): int {
		global $wpdb;
		$pid = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT player_id FROM ' . self::t( 'match_events' ) . "
			 WHERE match_id = %d AND team_id = %d AND event_type = 'captain'
			 ORDER BY id DESC LIMIT 1",
			$match_id, $team_id
		) );
		if ( $pid ) return $pid;
		return (int) get_post_meta( $team_id, '_kurs_team_captain', true );
	}

	public static function set_match_captain( int $match_id, int $team_id, int $player_id ): void {
		global $wpdb;
		$wpdb->delete( self::t( 'match_events' ), [ 'match_id' => $match_id, 'team_id' => $team_id, 'event_type' => 'captain' ] );
		if ( $player_id ) {
			$wpdb->insert( self::t( 'match_events' ), [
				'match_id' => $match_id, 'team_id' => $team_id, 'player_id' => $player_id, 'event_type' => 'captain',
			] );
		}
	}

	/** Player ID-evi koji NISU igrali u utakmici. */
	public static function get_absent( int $match_id ): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			'SELECT player_id FROM ' . self::t( 'match_events' ) . "
			 WHERE match_id = %d AND event_type = 'absent' AND player_id IS NOT NULL",
			$match_id
		) ) );
	}

	public static function set_absent( int $match_id, int $team_id, int $player_id, bool $absent ): void {
		global $wpdb;
		$wpdb->delete( self::t( 'match_events' ), [ 'match_id' => $match_id, 'player_id' => $player_id, 'event_type' => 'absent' ] );
		if ( $absent ) {
			$wpdb->insert( self::t( 'match_events' ), [
				'match_id' => $match_id, 'team_id' => $team_id, 'player_id' => $player_id, 'event_type' => 'absent',
			] );
		}
	}

	/** Ažuriraj postojeći događaj (uredi pogrešan unos). */
	public static function update_event( int $id, int $team_id, ?int $player_id, string $type, ?int $minute, ?int $half ): void {
		global $wpdb;
		$wpdb->update( self::t( 'match_events' ), [
			'team_id'    => $team_id,
			'player_id'  => $player_id,
			'event_type' => $type,
			'minute'     => $minute,
			'half'       => $half,
		], [ 'id' => $id ] );
	}

	/** Suci utakmice (uvijek 2 po utakmici) — spremaju se na matches.referee1/2. */
	public static function set_referees( int $match_id, string $r1, string $r2 ): void {
		global $wpdb;
		$wpdb->update( self::t( 'matches' ), [
			'referee1' => $r1 !== '' ? $r1 : null,
			'referee2' => $r2 !== '' ? $r2 : null,
		], [ 'id' => $match_id ] );
	}

	/**
	 * Igrači isključeni iz utakmice (direktni crveni, drugi žuti, ili 2× žuti).
	 * Takvima se kasnije ne smiju pripisivati golovi/kartoni.
	 */
	public static function get_sent_off( int $match_id ): array {
		global $wpdb;
		$t = self::t( 'match_events' );
		$direct = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT player_id FROM $t
				 WHERE match_id = %d AND player_id IS NOT NULL
				   AND event_type IN ('red_card','second_yellow')",
				$match_id
			)
		);
		$double = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT player_id FROM $t
				 WHERE match_id = %d AND player_id IS NOT NULL AND event_type = 'yellow_card'
				 GROUP BY player_id HAVING COUNT(*) >= 2",
				$match_id
			)
		);
		return array_values( array_unique( array_map( 'intval', array_merge( $direct, $double ) ) ) );
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

		// Kartoni po ekipi za fair-play (kriterij 7)
		$cards = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.team_id,
				        SUM( e.event_type = %s ) AS yellow,
				        SUM( e.event_type = %s ) AS red
				 FROM '  . self::t( 'match_events' ) . ' e
				 JOIN '  . self::t( 'matches' )      . ' m ON m.id = e.match_id
				 WHERE m.group_id = %d AND m.status = %s
				 GROUP BY e.team_id',
				'yellow_card', 'red_card', $group_id, 'finished'
			)
		);
		$card_pts = []; // niži = bolji (3 boda žuti, 10 crveni)
		foreach ( $cards as $c ) {
			$card_pts[ (int) $c->team_id ] = (int) $c->yellow * 3 + (int) $c->red * 10;
		}

		// Inicijalizacija redova
		$rows = [];
		foreach ( $teams as $t ) {
			$tid = (int) $t->team_id;
			$rows[ $tid ] = [
				'team_id'   => $tid,
				'team_name' => $t->team_name,
				'P' => 0, 'W' => 0, 'D' => 0, 'L' => 0,
				'GF' => 0, 'GA' => 0, 'GD' => 0, 'Pts' => 0,
				'FP' => $card_pts[ $tid ] ?? 0,
			];
		}

		// Popuni ukupne statistike
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

		// ── UEFA/HNS sortiranje ───────────────────────────────────────────────
		//
		// Grupe s jednakim bodovima razrješavaju se mini-tablicom međusobnih
		// utakmica. Ako su i tamo izjednačene, prelazi se na ukupne kriterije.

		$rows = array_values( $rows );

		// Grupiraj ekipe prema ukupnim bodovima
		$pt_groups = [];
		foreach ( $rows as $r ) {
			$pt_groups[ $r['Pts'] ][] = $r['team_id'];
		}

		// Za svaku grupu s 2+ ekipama izgradi mini-tablicu i dodijeli h2h rang
		$h2h_rank = []; // team_id => rang unutar grupe (niži = bolji)

		foreach ( $pt_groups as $pts => $tids ) {
			if ( count( $tids ) < 2 ) continue;

			$tid_set = array_flip( $tids );

			// Izgradi mini-red samo od međusobnih utakmica
			$mini = [];
			foreach ( $tids as $tid ) {
				$mini[ $tid ] = [ 'Pts' => 0, 'GD' => 0, 'GF' => 0 ];
			}
			foreach ( $matches as $m ) {
				$h  = (int) $m->home_team_id;
				$a  = (int) $m->away_team_id;
				if ( ! isset( $tid_set[ $h ], $tid_set[ $a ] ) ) continue;
				$hs = (int) $m->home_score;
				$as = (int) $m->away_score;
				$mini[ $h ]['GF'] += $hs; $mini[ $h ]['GD'] += $hs - $as;
				$mini[ $a ]['GF'] += $as; $mini[ $a ]['GD'] += $as - $hs;
				if ( $hs > $as )      { $mini[ $h ]['Pts'] += 3; }
				elseif ( $hs < $as )  { $mini[ $a ]['Pts'] += 3; }
				else                  { $mini[ $h ]['Pts']++; $mini[ $a ]['Pts']++; }
			}

			// Sortiraj mini-tablicu (h2h Pts → h2h GD → h2h GF)
			$mini_sorted = $tids;
			usort( $mini_sorted, function( $x, $y ) use ( $mini ) {
				return $mini[ $y ]['Pts'] <=> $mini[ $x ]['Pts']
					?: $mini[ $y ]['GD']  <=> $mini[ $x ]['GD']
					?: $mini[ $y ]['GF']  <=> $mini[ $x ]['GF'];
			} );

			// Provjeri ima li unutar mini-tablice još izjednačenih (cirkularni slučaj)
			// → ako da, za te ekipe mini-tablica ne pomaže, prepuštamo ukupnim kriterijima
			$seen_rank = 0;
			$prev_mini = null;
			foreach ( $mini_sorted as $i => $tid ) {
				$cur = $mini[ $tid ];
				if ( $prev_mini && $cur === $prev_mini ) {
					// Isti h2h rezultat kao prethodni — isti rang (ukupni kriteriji odlučuju)
					$h2h_rank[ $tid ] = $seen_rank;
				} else {
					$seen_rank = $i;
					$h2h_rank[ $tid ] = $i;
				}
				$prev_mini = $cur;
			}
		}

		// Konačno sortiranje
		usort( $rows, function( $a, $b ) use ( $h2h_rank ) {
			$aid = $a['team_id'];
			$bid = $b['team_id'];

			// 1. Ukupni bodovi
			$cmp = $b['Pts'] <=> $a['Pts'];
			if ( $cmp !== 0 ) return $cmp;

			// Samo ekipe s istim bodovima ulaze u h2h usporedbu
			// 2-4. H2H rang (pokriva h2h Pts, h2h GD, h2h GF)
			$cmp = ( $h2h_rank[ $aid ] ?? 999 ) <=> ( $h2h_rank[ $bid ] ?? 999 );
			if ( $cmp !== 0 ) return $cmp;

			// 5. Ukupna gol razlika u skupini
			$cmp = $b['GD'] <=> $a['GD'];
			if ( $cmp !== 0 ) return $cmp;

			// 6. Ukupni golovi za u skupini
			$cmp = $b['GF'] <=> $a['GF'];
			if ( $cmp !== 0 ) return $cmp;

			// 7. Fair-play (manji = bolji)
			return $a['FP'] <=> $b['FP'];
		} );

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
				   AND e.event_type IN (\'goal\', \'penalty\')
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
