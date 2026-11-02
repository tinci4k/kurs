<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registar igrača (CPT kurs_igrac): pretraga, sažetak po igraču, mogući duplikati, spajanje
 * i brisanje. Igrač se upisuje jednom i igra više sezona; u ekipu ga stavlja roster (squads),
 * a odigrane utakmice čuva postava (match_players). Admin stranica „Igrači".
 */
class Kurs_Players {

	private static function t( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . $name;
	}

	/** Ime za usporedbu duplikata: bez dijakritika, malim slovima, jedan razmak. */
	public static function normalized_name( string $name ): string {
		return trim( preg_replace( '/\s+/', ' ', mb_strtolower( remove_accents( $name ) ) ) );
	}

	/** Grupe igrača istog (normaliziranog) imena: [ normalizirano ime => [ player_id, … ] ], samo grupe s 2+. */
	public static function duplicate_groups(): array {
		global $wpdb;
		$groups = [];
		foreach ( $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'kurs_igrac' AND post_status = 'publish'" ) as $p ) {
			$groups[ self::normalized_name( $p->post_title ) ][] = (int) $p->ID;
		}
		return array_filter( $groups, fn( $ids ) => count( $ids ) > 1 );
	}

	/** Igrači istog imena kao zadani (bez njega samog). */
	public static function same_name( int $player_id ): array {
		$key = self::normalized_name( get_the_title( $player_id ) );
		return array_values( array_diff( self::duplicate_groups()[ $key ] ?? [], [ $player_id ] ) );
	}

	/** Nastupi i golovi po igraču (završene utakmice iz postava): [ player_id => { apps, goals } ]. */
	public static function totals( array $ids ): array {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) return [];
		$in  = implode( ',', $ids );
		$out = array_fill_keys( $ids, null );
		foreach ( $out as $id => $_ ) $out[ $id ] = (object) [ 'apps' => 0, 'goals' => 0 ];
		foreach ( $wpdb->get_results(
			'SELECT mp.player_id, COUNT(*) AS n FROM ' . self::t( 'match_players' ) . ' mp
			 JOIN ' . self::t( 'matches' ) . " m ON m.id = mp.match_id AND m.status = 'finished'
			 WHERE mp.player_id IN ($in) AND NOT EXISTS ( SELECT 1 FROM " . self::t( 'match_events' ) . " e
			   WHERE e.match_id = mp.match_id AND e.player_id = mp.player_id AND e.event_type = 'absent' )
			 GROUP BY mp.player_id"
		) as $r ) $out[ (int) $r->player_id ]->apps = (int) $r->n;
		foreach ( $wpdb->get_results(
			'SELECT e.player_id, COUNT(*) AS n FROM ' . self::t( 'match_events' ) . ' e
			 JOIN ' . self::t( 'matches' ) . " m ON m.id = e.match_id AND m.status = 'finished'
			 WHERE e.player_id IN ($in) AND e.event_type IN ('goal','penalty') GROUP BY e.player_id"
		) as $r ) $out[ (int) $r->player_id ]->goals = (int) $r->n;
		// + preneseni golovi (utakmice koje nisu u sustavu)
		foreach ( $wpdb->get_results( 'SELECT player_id, SUM( base_goals ) AS n FROM ' . self::t( 'squads' ) . " WHERE player_id IN ($in) GROUP BY player_id" ) as $r ) {
			$out[ (int) $r->player_id ]->goals += (int) $r->n;
		}
		return $out;
	}

	/**
	 * Gdje igrači igraju u aktivnim sezonama (roster ekipe uvrštene u ligu):
	 * [ player_id => [ { team_id, team_name, division_id, division_name, season_id, league, jersey_no }, … ] ].
	 */
	public static function current_teams( array $ids = [] ): array {
		global $wpdb;
		$ids   = array_filter( array_map( 'intval', $ids ) );
		$where = $ids ? 'AND sq.player_id IN (' . implode( ',', $ids ) . ')' : '';
		$out   = [];
		foreach ( $wpdb->get_results(
			'SELECT sq.player_id, sq.team_id, t.post_title AS team_name, sq.jersey_no, d.id AS division_id, d.name AS division_name,
			        s.id AS season_id, s.league
			 FROM ' . self::t( 'squads' ) . ' sq
			 JOIN ' . self::t( 'divisions' ) . ' d ON d.id = sq.division_id
			 JOIN ' . self::t( 'seasons' ) . " s ON s.id = d.season_id AND s.status = 'active'
			 JOIN {$wpdb->posts} t ON t.ID = sq.team_id
			 WHERE EXISTS ( SELECT 1 FROM " . self::t( 'group_teams' ) . ' gt JOIN ' . self::t( 'groups' ) . ' g ON g.id = gt.group_id
			                JOIN ' . self::t( 'phases' ) . " ph ON ph.id = g.phase_id WHERE gt.team_id = sq.team_id AND ph.division_id = sq.division_id )
			 $where
			 ORDER BY s.year DESC, d.sort_order, d.id"
		) as $r ) $out[ (int) $r->player_id ][] = $r;
		return $out;
	}

	/** Ima li igrač odigranih utakmica ili događaja (tada se ne briše, nego spaja). */
	public static function has_history( int $player_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT ( SELECT COUNT(*) FROM ' . self::t( 'match_players' ) . ' WHERE player_id = %d )
			      + ( SELECT COUNT(*) FROM ' . self::t( 'match_events' ) . " WHERE player_id = %d AND event_type <> 'absent' )",
			$player_id, $player_id
		) );
	}

	/** Briše igrača bez povijesti (s rosterima i kapetanskim oznakama). Igrač s utakmicama → WP_Error. */
	public static function delete( int $player_id ): bool|\WP_Error {
		global $wpdb;
		if ( get_post_type( $player_id ) !== 'kurs_igrac' ) return new \WP_Error( 'not_player', 'Igrač ne postoji.' );
		if ( self::has_history( $player_id ) ) {
			return new \WP_Error( 'has_history', 'Igrač ima odigrane utakmice — ne briše se. Ako je upisan dvaput, spoji ga s drugim zapisom.' );
		}
		$wpdb->delete( self::t( 'squads' ),       [ 'player_id' => $player_id ] );
		$wpdb->delete( self::t( 'match_events' ), [ 'player_id' => $player_id ] ); // samo 'absent' (ostalo je povijest)
		self::replace_captain( $player_id, 0 );
		wp_delete_post( $player_id, true );
		do_action( 'kurs_data_changed' );
		return true;
	}

	/**
	 * Spoji dvostruko upisanog igrača: sve od $drop (rosteri, postave, golovi, kartoni, kapetan)
	 * prelazi na $keep, a $drop se briše. Ako su u istoj ligi u različitim ekipama, to nisu isti
	 * igrač (ili je roster pogrešan) — WP_Error, ništa se ne mijenja.
	 */
	public static function merge( int $keep, int $drop ): bool|\WP_Error {
		global $wpdb;
		if ( $keep === $drop || get_post_type( $keep ) !== 'kurs_igrac' || get_post_type( $drop ) !== 'kurs_igrac' ) {
			return new \WP_Error( 'merge_invalid', 'Odaberi dva različita igrača.' );
		}
		$sq      = self::t( 'squads' );
		$keep_sq = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $sq WHERE player_id = %d", $keep ) );
		$drop_sq = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $sq WHERE player_id = %d", $drop ) );
		foreach ( $drop_sq as $d ) {
			foreach ( $keep_sq as $k ) {
				if ( (int) $k->division_id === (int) $d->division_id && (int) $k->team_id !== (int) $d->team_id ) {
					$div = Kurs_Season::get_division( (int) $d->division_id );
					return new \WP_Error( 'merge_conflict', sprintf(
						'U ligi %s jedan igra za „%s", a drugi za „%s" — to nisu isti igrač. Ako jesu, najprije jednog makni iz rostera.',
						$div->name ?? '', get_the_title( (int) $k->team_id ), get_the_title( (int) $d->team_id )
					) );
				}
			}
		}

		// Roster: isti redak (ekipa × liga) ostaje igraču koji ostaje (broj dresa dopuni), ostali prelaze.
		foreach ( $drop_sq as $d ) {
			$same = array_filter( $keep_sq, fn( $k ) => (int) $k->team_id === (int) $d->team_id && (int) $k->division_id === (int) $d->division_id );
			if ( $same ) {
				$k = reset( $same );
				if ( ! $k->jersey_no && $d->jersey_no ) $wpdb->update( $sq, [ 'jersey_no' => (int) $d->jersey_no ], [ 'id' => (int) $k->id ] );
				$wpdb->delete( $sq, [ 'id' => (int) $d->id ] );
			} else {
				$wpdb->update( $sq, [ 'player_id' => $keep ], [ 'id' => (int) $d->id ] );
			}
		}

		// Postave: utakmica u kojoj su obojica — igrao je ako je igrao bilo koji zapis.
		$mp  = self::t( 'match_players' );
		$ev  = self::t( 'match_events' );
		foreach ( array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT a.match_id FROM $mp a JOIN $mp b ON b.match_id = a.match_id AND b.player_id = %d WHERE a.player_id = %d", $drop, $keep
		) ) ) as $mid ) {
			$abs = fn( int $pid ) => (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ev WHERE match_id = %d AND player_id = %d AND event_type = 'absent'", $mid, $pid ) );
			if ( ! ( $abs( $keep ) && $abs( $drop ) ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM $ev WHERE match_id = %d AND player_id IN (%d, %d) AND event_type = 'absent'", $mid, $keep, $drop ) );
			}
			$wpdb->query( $wpdb->prepare( "DELETE FROM $ev WHERE match_id = %d AND player_id = %d AND event_type = 'absent'", $mid, $drop ) );
			$wpdb->delete( $mp, [ 'match_id' => $mid, 'player_id' => $drop ] );
		}
		$wpdb->update( $mp, [ 'player_id' => $keep ], [ 'player_id' => $drop ] );
		$wpdb->update( $ev, [ 'player_id' => $keep ], [ 'player_id' => $drop ] );

		// Podaci igrača: što ostaje prazno, dopuni iz duplikata.
		if ( ! has_post_thumbnail( $keep ) && ( $thumb = get_post_thumbnail_id( $drop ) ) ) set_post_thumbnail( $keep, $thumb );
		if ( ! get_post_meta( $keep, '_kurs_player_birthyear', true ) && ( $by = get_post_meta( $drop, '_kurs_player_birthyear', true ) ) ) {
			update_post_meta( $keep, '_kurs_player_birthyear', $by );
		}
		self::replace_captain( $drop, $keep );
		wp_delete_post( $drop, true );
		do_action( 'kurs_data_changed' );
		return true;
	}

	/** Zadani kapetan ekipa (postmeta _kurs_team_captain): $from → $to (0 = bez kapetana). */
	private static function replace_captain( int $from, int $to ): void {
		global $wpdb;
		foreach ( $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_kurs_team_captain' AND meta_value = %d", $from
		) ) as $team_id ) {
			$to ? update_post_meta( (int) $team_id, '_kurs_team_captain', $to ) : delete_post_meta( (int) $team_id, '_kurs_team_captain' );
		}
	}
}
