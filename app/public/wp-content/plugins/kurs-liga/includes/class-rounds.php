<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pregled po kolima — podaci za „N. kolo" jedne lige (divizije):
 *   - rezultati kola sa strijelcima po utakmici,
 *   - ljestvica nakon kola (+ pomak u odnosu na prethodno kolo),
 *   - strijelci nakon kola, grupirani po broju golova (+ autogolovi).
 * Isti podaci pune Liga Hub (tab „Po kolima") i stranicu „Za medije".
 * Kola su iz grupne faze; playoff (group_id = 0) nema numerirana kola.
 */
class Kurs_Rounds {

	private static function t( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . $name;
	}

	/** JOIN od utakmice do lige (divizije) — samo grupna faza. */
	private static function div_join(): string {
		return ' JOIN ' . self::t( 'groups' ) . ' g ON g.id = m.group_id'
		     . ' JOIN ' . self::t( 'phases' ) . ' ph ON ph.id = g.phase_id ';
	}

	/** Sva kola lige, uzlazno. */
	public static function rounds( int $division_id ): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT m.round FROM ' . self::t( 'matches' ) . ' m' . self::div_join() .
			'WHERE ph.division_id = %d AND m.round IS NOT NULL ORDER BY m.round',
			$division_id
		) ) );
	}

	/** Stanje svih kola: [ kolo => [ 'total' => n, 'finished' => n ] ] (za traku s kolima). */
	public static function round_status( int $division_id ): array {
		global $wpdb;
		$out = [];
		foreach ( $wpdb->get_results( $wpdb->prepare(
			"SELECT m.round, COUNT(*) AS total, SUM( m.status = 'finished' ) AS finished
			 FROM " . self::t( 'matches' ) . ' m' . self::div_join() .
			'WHERE ph.division_id = %d AND m.round IS NOT NULL GROUP BY m.round ORDER BY m.round',
			$division_id
		) ) as $r ) {
			$out[ (int) $r->round ] = [ 'total' => (int) $r->total, 'finished' => (int) $r->finished ];
		}
		return $out;
	}

	/**
	 * Ekipa koja u kolu ne igra (neparan broj ekipa → jedna slobodna po kolu).
	 * Vraća [ group_id => naziv ekipe ]; samo kad je izostala točno jedna ekipa,
	 * da djelomično unesen raspored ne bi izgledao kao „slobodni".
	 */
	public static function byes( array $matches ): array {
		$playing = [];
		foreach ( $matches as $m ) {
			$playing[ (int) $m->group_id ][ (int) $m->home_team_id ] = true;
			$playing[ (int) $m->group_id ][ (int) $m->away_team_id ] = true;
		}
		$out = [];
		foreach ( $playing as $gid => $ids ) {
			$teams = Kurs_Season::get_group_teams( $gid );
			if ( count( $teams ) % 2 === 0 ) continue;
			$idle = array_filter( $teams, fn( $t ) => ! isset( $ids[ (int) $t->team_id ] ) );
			if ( count( $idle ) === 1 ) $out[ $gid ] = reset( $idle )->team_name;
		}
		return $out;
	}

	/** Zadnje kolo s barem jednom odigranom utakmicom (inače prvo kolo, ili 0). */
	public static function default_round( int $division_id ): int {
		global $wpdb;
		$last = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT MAX(m.round) FROM ' . self::t( 'matches' ) . ' m' . self::div_join() .
			"WHERE ph.division_id = %d AND m.status = 'finished'",
			$division_id
		) );
		if ( $last ) return $last;
		return self::rounds( $division_id )[0] ?? 0;
	}

	/** Utakmice kola, poredane po skupini pa terminu. */
	public static function matches( int $division_id, int $round ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT m.id, m.group_id, g.name AS group_name, m.round, m.match_date, m.status,
			        m.home_score, m.away_score, m.home_team_id, m.away_team_id,
			        h.post_title AS home_name, a.post_title AS away_name
			 FROM ' . self::t( 'matches' ) . ' m' . self::div_join() .
			'JOIN ' . $wpdb->posts . ' h ON h.ID = m.home_team_id
			 JOIN ' . $wpdb->posts . ' a ON a.ID = m.away_team_id
			 WHERE ph.division_id = %d AND m.round = %d
			 ORDER BY g.name, ( m.match_date IS NULL ), m.match_date, m.id',
			$division_id, $round
		) ) ?: [];
	}

	/**
	 * Kratka imena strijelaca kao u medijima: prezime, a kad u ligi postoji više
	 * igrača istog prezimena, dodaje se početak imena („M. Kovaček", „Dan. Nemčić").
	 * @return array player_id => kratko ime
	 */
	public static function short_names( int $division_id ): array {
		static $cache = [];
		if ( isset( $cache[ $division_id ] ) ) return $cache[ $division_id ];
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT DISTINCT e.player_id, p.post_title AS name
			 FROM ' . self::t( 'match_events' ) . ' e
			 JOIN ' . self::t( 'matches' ) . ' m ON m.id = e.match_id' . self::div_join() .
			'JOIN ' . $wpdb->posts . " p ON p.ID = e.player_id
			 WHERE ph.division_id = %d AND e.player_id IS NOT NULL
			   AND e.event_type IN ('goal','penalty','own_goal')",
			$division_id
		) );

		$by_surname = [];
		foreach ( $rows as $r ) {
			$parts = preg_split( '/\s+/u', trim( (string) $r->name ) ) ?: [ '' ];
			$sur   = array_pop( $parts );
			$by_surname[ mb_strtolower( $sur ) ][] = [ 'id' => (int) $r->player_id, 'first' => $parts[0] ?? '', 'sur' => $sur ];
		}

		$out = [];
		foreach ( $by_surname as $list ) {
			if ( count( $list ) === 1 ) { $out[ $list[0]['id'] ] = $list[0]['sur']; continue; }
			// Najkraći početak imena koji razlikuje igrača od onih s DRUGIM imenom
			// (dva „Martin Kolarić" se ionako ne mogu razlikovati — oba „M. Kolarić").
			foreach ( $list as $x ) {
				$first = mb_strtolower( $x['first'] );
				$len   = 1;
				foreach ( $list as $y ) {
					$other = mb_strtolower( $y['first'] );
					if ( $other === $first ) continue;
					while ( $len < mb_strlen( $first ) && mb_substr( $first, 0, $len ) === mb_substr( $other, 0, $len ) ) $len++;
				}
				$p = mb_substr( $x['first'], 0, $len );
				$out[ $x['id'] ] = trim( ( $p === $x['first'] ? $p : $p . '.' ) . ' ' . $x['sur'] );
			}
		}
		return $cache[ $division_id ] = $out;
	}

	/**
	 * Strijelci po utakmici: [ match_id => [ 'home' => [...], 'away' => [...] ] ],
	 * stavka = [ 'name', 'goals', 'own' ]. Autogol ide na stranu ekipe kojoj je pripisan.
	 */
	public static function goals_by_match( int $division_id, array $matches ): array {
		if ( ! $matches ) return [];
		global $wpdb;
		$ids   = implode( ',', array_map( fn( $m ) => (int) $m->id, $matches ) );
		$names = self::short_names( $division_id );
		$evs   = $wpdb->get_results(
			'SELECT match_id, team_id, player_id, event_type FROM ' . self::t( 'match_events' ) .
			" WHERE match_id IN ($ids) AND event_type IN ('goal','penalty','own_goal') ORDER BY minute, id"
		);

		$by_id = [];
		foreach ( $matches as $m ) $by_id[ (int) $m->id ] = $m;

		$out = [];
		foreach ( $evs as $e ) {
			$m = $by_id[ (int) $e->match_id ] ?? null;
			if ( ! $m ) continue;
			$own      = $e->event_type === 'own_goal';
			$is_home  = (int) $e->team_id === (int) $m->home_team_id;
			$side     = ( $is_home xor $own ) ? 'home' : 'away'; // autogol domaćina = gol gosta
			$name     = $e->player_id ? ( $names[ (int) $e->player_id ] ?? get_post_field( 'post_title', (int) $e->player_id ) ) : 'nepoznat';
			$key      = ( $own ? 'og:' : '' ) . ( $e->player_id ?: $name );
			$out[ (int) $m->id ][ $side ][ $key ] ??= [ 'name' => $name, 'goals' => 0, 'own' => $own ];
			$out[ (int) $m->id ][ $side ][ $key ]['goals']++;
		}
		foreach ( $out as &$sides ) {
			foreach ( $sides as &$list ) {
				$list = array_values( $list );
				usort( $list, fn( $a, $b ) => $b['goals'] <=> $a['goals'] ); // stabilno: unutar istog broja redoslijed prvog gola
			}
		}
		return $out;
	}

	/** „Ranilović 3, Rac 2, Marić" — tekst jedne strane (prazno ako nema golova). */
	public static function scorers_text( array $list ): string {
		return implode( ', ', array_map(
			fn( $s ) => $s['name'] . ( $s['goals'] > 1 ? ' ' . $s['goals'] : '' ) . ( $s['own'] ? ' (ag)' : '' ),
			$list
		) );
	}

	/**
	 * Ljestvice lige nakon N. kola, po skupini:
	 * [ [ 'group' => obj, 'rows' => [...+ 'move' => int|null], 'unplayed' => int ], ... ]
	 * 'move' > 0 = skok prema gore u odnosu na stanje nakon prethodnog kola.
	 */
	public static function standings( int $division_id, int $round ): array {
		$ph = Kurs_Season::group_phase( $division_id );
		if ( ! $ph ) return [];

		$rounds = self::rounds( $division_id );
		$prev   = 0;
		foreach ( $rounds as $r ) if ( $r < $round ) $prev = $r;

		global $wpdb;
		$out = [];
		foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
			$rows = Kurs_Match::get_standings( (int) $g->id, $round );
			if ( ! $rows ) continue;

			$prev_pos = [];
			if ( $prev ) {
				$before = Kurs_Match::get_standings( (int) $g->id, $prev );
				if ( array_sum( array_column( $before, 'P' ) ) > 0 ) { // pomak samo ako se prije već igralo
					foreach ( $before as $i => $r ) $prev_pos[ $r['team_id'] ] = $i + 1;
				}
			}
			foreach ( $rows as $i => &$r ) {
				$r['move'] = isset( $prev_pos[ $r['team_id'] ] ) ? $prev_pos[ $r['team_id'] ] - ( $i + 1 ) : null;
			}
			unset( $r );

			$unplayed = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::t( 'matches' ) . " WHERE group_id = %d AND round <= %d AND status <> 'finished'",
				(int) $g->id, $round
			) );
			$out[] = [ 'group' => $g, 'rows' => $rows, 'unplayed' => $unplayed ];
		}
		return $out;
	}

	/**
	 * Strijelci lige nakon N. kola (null = svi), grupirani po broju golova:
	 * [ 'groups' => [ goals => [ ['name','team_id','team'] ... ] ], 'own' => [ ['name','team','goals'] ] ]
	 */
	public static function scorers( int $division_id, ?int $max_round = null ): array {
		global $wpdb;
		$names = self::short_names( $division_id );
		$round = $max_round ? $wpdb->prepare( ' AND m.round <= %d', $max_round ) : '';
		$sql   = fn( string $types ) =>
			'SELECT e.player_id, MAX(e.team_id) AS team_id, COUNT(*) AS goals
			 FROM ' . self::t( 'match_events' ) . ' e
			 JOIN ' . self::t( 'matches' ) . ' m ON m.id = e.match_id' . self::div_join() .
			$wpdb->prepare( "WHERE ph.division_id = %d AND m.status = 'finished' AND e.player_id IS NOT NULL AND e.event_type IN ($types)", $division_id ) .
			$round . ' GROUP BY e.player_id ORDER BY goals DESC';

		$groups = [];
		foreach ( $wpdb->get_results( $sql( "'goal','penalty'" ) ) as $r ) {
			$groups[ (int) $r->goals ][] = [
				'name'    => $names[ (int) $r->player_id ] ?? get_post_field( 'post_title', (int) $r->player_id ),
				'team_id' => (int) $r->team_id,
				'team'    => get_post_field( 'post_title', (int) $r->team_id ),
			];
		}
		krsort( $groups );
		foreach ( $groups as &$list ) usort( $list, fn( $a, $b ) => strcoll( $a['team'], $b['team'] ) ?: strcoll( $a['name'], $b['name'] ) );
		unset( $list );

		$own = [];
		foreach ( $wpdb->get_results( $sql( "'own_goal'" ) ) as $r ) {
			$own[] = [
				'name'  => $names[ (int) $r->player_id ] ?? get_post_field( 'post_title', (int) $r->player_id ),
				'team'  => get_post_field( 'post_title', (int) $r->team_id ),
				'goals' => (int) $r->goals,
			];
		}
		return [ 'groups' => $groups, 'own' => $own ];
	}

	/** Datum kola kao u medijima: „subota, 28. 2. 2026." ili raspon „21. – 24. 4. 2026.". */
	public static function date_label( array $matches ): string {
		$days = array_unique( array_filter( array_map( fn( $m ) => $m->match_date ? substr( $m->match_date, 0, 10 ) : '', $matches ) ) );
		if ( ! $days ) return '';
		sort( $days );
		$fmt = fn( string $d ) => (int) substr( $d, 8, 2 ) . '. ' . (int) substr( $d, 5, 2 ) . '. ' . substr( $d, 0, 4 ) . '.';
		if ( count( $days ) === 1 ) {
			$wd = [ 'nedjelja', 'ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota' ];
			return $wd[ (int) date( 'w', strtotime( $days[0] ) ) ] . ', ' . $fmt( $days[0] );
		}
		$a = $days[0]; $b = end( $days );
		return ( substr( $a, 0, 7 ) === substr( $b, 0, 7 )
			? (int) substr( $a, 8, 2 ) . '.'                                        // 21. – 24. 4. 2026.
			: (int) substr( $a, 8, 2 ) . '. ' . (int) substr( $a, 5, 2 ) . '.'     // 30. 4. – 2. 5. 2026.
		) . ' – ' . $fmt( $b );
	}
}
