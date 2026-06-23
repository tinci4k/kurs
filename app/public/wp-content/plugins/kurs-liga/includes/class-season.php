<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Season {

	private static function t( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . $name;
	}

	// ── Sezone ────────────────────────────────────────────────────────────────

	public static function all(): array {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT * FROM ' . self::t( 'seasons' ) . ' ORDER BY year DESC, id DESC'
		) ?: [];
	}

	public static function active(): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::t( 'seasons' ) . ' WHERE status = %s ORDER BY year DESC',
				'active'
			)
		) ?: [];
	}

	/** Je li liga (slug ili slobodan naziv) "Pan ljetna"? Sve ostalo = KC. */
	public static function is_pan_league( string $league ): bool {
		return stripos( $league, 'pan' ) !== false;
	}

	/** Sve sezone jedne lige (KC ili Pan), najnovije prvo (year DESC, id DESC). */
	public static function for_league( bool $is_pan ): array {
		return array_values( array_filter(
			self::all(),
			fn( $s ) => self::is_pan_league( (string) $s->league ) === $is_pan
		) );
	}

	/**
	 * Aktualna sezona lige za prikaz u izborniku: prva AKTIVNA s najvišom godinom,
	 * a ako nijedna nije aktivna — najnovija. Tako nova sezona (kad je označiš
	 * "Aktivna") automatski postaje aktualna pod tim izbornikom.
	 */
	public static function current_for_league( bool $is_pan ): ?object {
		$seasons = self::for_league( $is_pan ); // već sortirano year DESC
		foreach ( $seasons as $s ) {
			if ( $s->status === 'active' ) return $s;
		}
		return $seasons[0] ?? null;
	}

	/** Je li sezona zaključana (konačni podaci)? */
	public static function is_locked( int $id ): bool {
		$s = self::get( $id );
		return $s && ! empty( $s->locked );
	}

	/** Zaključaj/otključaj sezonu. */
	public static function set_locked( int $id, bool $locked ): void {
		global $wpdb;
		$wpdb->update( self::t( 'seasons' ), [ 'locked' => $locked ? 1 : 0 ], [ 'id' => $id ] );
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::t( 'seasons' ) . ' WHERE id = %d', $id )
		);
	}

	public static function create( array $data ): int|false {
		global $wpdb;
		$ok = $wpdb->insert( self::t( 'seasons' ), [
			'league'             => sanitize_text_field( $data['league'] ),
			'name'               => sanitize_text_field( $data['name'] ),
			'year'               => absint( $data['year'] ),
			'status'             => 'active',
			'max_fouls_per_half' => isset( $data['max_fouls_per_half'] ) ? max( 1, absint( $data['max_fouls_per_half'] ) ) : 5,
		] );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update( int $id, array $data ): void {
		global $wpdb;
		$clean = [];
		foreach ( [ 'name', 'year', 'status', 'league' ] as $k ) {
			if ( isset( $data[ $k ] ) ) $clean[ $k ] = sanitize_text_field( $data[ $k ] );
		}
		if ( isset( $data['max_fouls_per_half'] ) ) {
			$clean['max_fouls_per_half'] = max( 1, absint( $data['max_fouls_per_half'] ) );
		}
		if ( $clean ) $wpdb->update( self::t( 'seasons' ), $clean, [ 'id' => $id ] );
	}

	public static function delete( int $id ): void {
		global $wpdb;
		foreach ( self::get_divisions( $id ) as $div ) {
			self::delete_division( (int) $div->id );
		}
		$wpdb->delete( self::t( 'seasons' ), [ 'id' => $id ] );
	}

	// ── Divizije ───────────────────────────────────────────────────────────────

	public static function get_divisions( int $season_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::t( 'divisions' ) . ' WHERE season_id = %d ORDER BY sort_order, id',
				$season_id
			)
		) ?: [];
	}

	public static function get_division( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::t( 'divisions' ) . ' WHERE id = %d', $id )
		);
	}

	public static function create_division( int $season_id, string $name, int $order = 0, int $has_playoff = 1, int $advance = 2 ): int|false {
		global $wpdb;
		$ok = $wpdb->insert( self::t( 'divisions' ), [
			'season_id'         => $season_id,
			'name'              => sanitize_text_field( $name ),
			'sort_order'        => $order,
			'has_playoff'       => $has_playoff ? 1 : 0,
			'advance_per_group' => max( 0, $advance ),
		] );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Postavi format divizije: ima li playoff i koliko ekipa prolazi iz svake grupe.
	 * Vrijednost se propagira na sve grupe (groups.playoff_spots) — 0 kad nema playoffa —
	 * pa postojeća logika generiranja bracketa i isticanja u ljestvici radi nepromijenjena.
	 */
	public static function update_division_format( int $div_id, int $has_playoff, int $advance ): void {
		global $wpdb;
		$has_playoff = $has_playoff ? 1 : 0;
		$advance     = max( 0, $advance );
		$wpdb->update( self::t( 'divisions' ), [
			'has_playoff'       => $has_playoff,
			'advance_per_group' => $advance,
		], [ 'id' => $div_id ] );

		$spots = $has_playoff ? $advance : 0;
		$g  = self::t( 'groups' );
		$ph = self::t( 'phases' );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $g g JOIN $ph ph ON ph.id = g.phase_id SET g.playoff_spots = %d WHERE ph.division_id = %d",
			$spots, $div_id
		) );
	}

	public static function delete_division( int $id ): void {
		global $wpdb;
		foreach ( self::get_phases( $id ) as $ph ) self::delete_phase( (int) $ph->id );
		$wpdb->delete( self::t( 'squads' ),    [ 'division_id' => $id ] );
		$wpdb->delete( self::t( 'divisions' ), [ 'id' => $id ] );
	}

	// ── Faze ──────────────────────────────────────────────────────────────────

	public static function get_phases( int $division_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::t( 'phases' ) . ' WHERE division_id = %d ORDER BY sort_order, id',
				$division_id
			)
		) ?: [];
	}

	public static function create_phase( int $division_id, string $name, string $type = 'group', int $order = 0 ): int|false {
		global $wpdb;
		$ok = $wpdb->insert( self::t( 'phases' ), [
			'division_id' => $division_id,
			'name'        => sanitize_text_field( $name ),
			'type'        => in_array( $type, [ 'group', 'playoff' ], true ) ? $type : 'group',
			'sort_order'  => $order,
		] );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function delete_phase( int $id ): void {
		global $wpdb;
		foreach ( self::get_groups( $id ) as $g ) self::delete_group( (int) $g->id );
		$wpdb->delete( self::t( 'phases' ), [ 'id' => $id ] );
	}

	// ── Grupe ─────────────────────────────────────────────────────────────────

	public static function get_groups( int $phase_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::t( 'groups' ) . ' WHERE phase_id = %d ORDER BY name',
				$phase_id
			)
		) ?: [];
	}

	public static function create_group( int $phase_id, string $name ): int|false {
		global $wpdb;
		// Naslijedi broj prolaznika iz divizije (0 ako se ne igra playoff)
		$ph  = self::t( 'phases' );
		$dv  = self::t( 'divisions' );
		$div = $wpdb->get_row( $wpdb->prepare(
			"SELECT dv.has_playoff, dv.advance_per_group FROM $ph ph JOIN $dv dv ON dv.id = ph.division_id WHERE ph.id = %d",
			$phase_id
		) );
		$spots = ( $div && $div->has_playoff ) ? (int) $div->advance_per_group : 0;
		$ok = $wpdb->insert( self::t( 'groups' ), [
			'phase_id'      => $phase_id,
			'name'          => sanitize_text_field( $name ),
			'playoff_spots' => $spots,
		] );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update_group_playoff_spots( int $group_id, int $spots ): void {
		global $wpdb;
		$wpdb->update(
			self::t( 'groups' ),
			[ 'playoff_spots' => max( 0, $spots ) ],
			[ 'id' => $group_id ]
		);
	}

	public static function delete_group( int $id ): void {
		global $wpdb;
		// Brisanje utakmica u grupi briše i match_events u kasnijim fazama
		$match_ids = array_column(
			$GLOBALS['wpdb']->get_results(
				$GLOBALS['wpdb']->prepare( 'SELECT id FROM ' . self::t( 'matches' ) . ' WHERE group_id = %d', $id )
			),
			'id'
		);
		if ( $match_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $match_ids ), '%d' ) );
			$GLOBALS['wpdb']->query(
				$GLOBALS['wpdb']->prepare(
					"DELETE FROM " . self::t( 'match_events' ) . " WHERE match_id IN ($placeholders)",
					...$match_ids
				)
			);
		}
		$GLOBALS['wpdb']->delete( self::t( 'group_teams' ), [ 'group_id' => $id ] );
		$GLOBALS['wpdb']->delete( self::t( 'matches' ),     [ 'group_id' => $id ] );
		$GLOBALS['wpdb']->delete( self::t( 'groups' ),      [ 'id' => $id ] );
	}

	// ── Ekipe u grupama ───────────────────────────────────────────────────────

	public static function get_group_teams( int $group_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT gt.group_id, gt.team_id, p.post_title AS team_name
				 FROM '  . self::t( 'group_teams' ) . ' gt
				 JOIN '  . $wpdb->posts             . ' p ON p.ID = gt.team_id
				 WHERE gt.group_id = %d
				 ORDER BY p.post_title',
				$group_id
			)
		) ?: [];
	}

	public static function assign_team( int $group_id, int $team_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::t( 'group_teams' ) . ' (group_id, team_id) VALUES (%d, %d)',
				$group_id, $team_id
			)
		);
	}

	public static function remove_team( int $group_id, int $team_id ): void {
		global $wpdb;
		$wpdb->delete( self::t( 'group_teams' ), [ 'group_id' => $group_id, 'team_id' => $team_id ] );
	}

	// ── Kontekst ekipe (sve sezone/divizije u kojima igra) ───────────────────

	public static function get_team_contexts( int $team_id ): array {
		global $wpdb;
		$p = $wpdb->prefix . KURS_PREFIX;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT s.id  AS season_id,   s.name AS season_name, s.league,
				                d.id  AS division_id, d.name AS division_name
				 FROM   {$p}group_teams gt
				 JOIN   {$p}groups     g  ON g.id  = gt.group_id
				 JOIN   {$p}phases     ph ON ph.id = g.phase_id
				 JOIN   {$p}divisions  d  ON d.id  = ph.division_id
				 JOIN   {$p}seasons    s  ON s.id  = d.season_id
				 WHERE  gt.team_id = %d
				 ORDER  BY s.year DESC, s.id DESC, d.sort_order, d.id",
				$team_id
			)
		) ?: [];
	}

	// ── Roster ────────────────────────────────────────────────────────────────

	public static function get_squad( int $team_id, int $division_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT sq.*, p.post_title AS player_name
				 FROM '  . self::t( 'squads' ) . ' sq
				 JOIN '  . $wpdb->posts        . ' p ON p.ID = sq.player_id
				 WHERE sq.team_id = %d AND sq.division_id = %d
				 ORDER BY sq.jersey_no, p.post_title',
				$team_id, $division_id
			)
		) ?: [];
	}

	public static function add_to_squad( int $player_id, int $team_id, int $division_id, int $jersey = 0 ): void {
		global $wpdb;
		$wpdb->replace( self::t( 'squads' ), [
			'player_id'   => $player_id,
			'team_id'     => $team_id,
			'division_id' => $division_id,
			'jersey_no'   => $jersey ?: null,
		] );
	}

	public static function remove_from_squad( int $player_id, int $team_id, int $division_id ): void {
		global $wpdb;
		$wpdb->delete( self::t( 'squads' ), [
			'player_id'   => $player_id,
			'team_id'     => $team_id,
			'division_id' => $division_id,
		] );
	}
}
