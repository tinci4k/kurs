<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Playoff {

	private static function t( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . $table;
	}

	// ── Generiranje bracketa iz grupnih ljestvica ─────────────────────────────

	/**
	 * Generira playoff bracket za zadanu diviziju.
	 * Briše stari bracket (ako postoji) i kreira novi na temelju grupnih ljestvica.
	 *
	 * Seeding za 4 grupe (A,B,C,D) s 2 mjesta:
	 *   QF1: A1 vs B2  │ SF1: QF1-W vs QF2-W
	 *   QF2: C1 vs D2  │                      →  Final
	 *   QF3: B1 vs A2  │ SF2: QF3-W vs QF4-W
	 *   QF4: D1 vs C2  │
	 *
	 * Za generički N grupa / M prolaza sustav koristi "balanced seeding" matricu.
	 */
	public static function generate( int $division_id ): bool {
		global $wpdb;

		// Dohvati grupe sortirane po imenu
		$groups = $wpdb->get_results( $wpdb->prepare(
			'SELECT g.id, g.name, g.playoff_spots
			 FROM '   . self::t('groups')  . ' g
			 JOIN '   . self::t('phases')  . ' ph ON ph.id = g.phase_id
			 WHERE ph.division_id = %d
			 ORDER BY g.name ASC',
			$division_id
		) );
		if ( ! $groups ) return false;

		// Skupi kvalificirane ekipe po grupi
		$qualified = []; // [ [ team_id, label ], ... ] po grupi
		foreach ( $groups as $g ) {
			$standings = Kurs_Match::get_standings( (int) $g->id );
			$spots     = (int) $g->playoff_spots;
			$label     = strtoupper( $g->name ); // "A", "B"...
			$qualified[] = array_map(
				fn( $i, $r ) => [ 'team_id' => $r['team_id'], 'label' => "{$label}" . ($i+1) ],
				array_keys( array_slice( $standings, 0, $spots ) ),
				array_slice( $standings, 0, $spots )
			);
		}

		// Izgradi parove koristeći balanced seeding matricu
		// Matrica [g1_rank, g2_rank] parova za N grupe s M spots
		$pairs = self::build_seeding_pairs( count( $groups ), (int) $groups[0]->playoff_spots );
		if ( empty( $pairs ) ) return false;

		$bracket_matches = self::bracket_rounds( count( $pairs ) );

		// Obriši stari bracket
		$wpdb->delete( self::t('playoff_matches'), [ 'division_id' => $division_id ] );

		// Umetni QF utakmice
		foreach ( $pairs as $slot => $pair ) {
			[ $gi, $ri, $gj, $rj ] = $pair; // group index, rank index (0-based)
			$home = $qualified[ $gi ][ $ri ] ?? null;
			$away = $qualified[ $gj ][ $rj ] ?? null;
			$wpdb->insert( self::t('playoff_matches'), [
				'division_id'  => $division_id,
				'round'        => $bracket_matches['qf'],
				'slot'         => $slot + 1,
				'home_team_id' => $home ? $home['team_id'] : null,
				'away_team_id' => $away ? $away['team_id'] : null,
				'home_label'   => $home ? $home['label']  : ( "G" . ($gi+1) . ($ri+1) ),
				'away_label'   => $away ? $away['label']  : ( "G" . ($gj+1) . ($rj+1) ),
				'status'       => 'scheduled',
			] );
		}

		// Umetni prazne SF i Final slotove
		foreach ( $bracket_matches['later'] as $round => $count ) {
			for ( $slot = 1; $slot <= $count; $slot++ ) {
				$prev_round  = $bracket_matches['prev'][ $round ];
				$home_src    = self::source_label( $prev_round, $slot * 2 - 1 );
				$away_src    = self::source_label( $prev_round, $slot * 2 );
				$wpdb->insert( self::t('playoff_matches'), [
					'division_id' => $division_id,
					'round'       => $round,
					'slot'        => $slot,
					'home_label'  => $home_src,
					'away_label'  => $away_src,
					'status'      => 'scheduled',
				] );
			}
		}

		return true;
	}

	/** Vraća round string temeljem broja utakmica u QF. */
	private static function bracket_rounds( int $qf_count ): array {
		if ( $qf_count === 4 ) {
			return [
				'qf'    => 'qf',
				'later' => [ 'sf' => 2, 'final' => 1 ],
				'prev'  => [ 'sf' => 'qf', 'final' => 'sf' ],
			];
		}
		if ( $qf_count === 2 ) {
			return [
				'qf'    => 'sf',
				'later' => [ 'final' => 1 ],
				'prev'  => [ 'final' => 'sf' ],
			];
		}
		// Fallback: samo final
		return [
			'qf'    => 'final',
			'later' => [],
			'prev'  => [],
		];
	}

	/**
	 * Balanced seeding matrica za N grupe × M mjesta.
	 * Vraća niz [ [gi, ri, gj, rj], ... ] za svaki QF par.
	 * Pazi da pobjednici različitih strana bracketa ne dolaze iz iste grupe.
	 */
	private static function build_seeding_pairs( int $n_groups, int $spots ): array {
		// Predefinirana matrica za najčešće slučajeve
		if ( $n_groups === 4 && $spots === 2 ) {
			// QF1: A1-B2, QF2: C1-D2, QF3: B1-A2, QF4: D1-C2
			return [
				[0,0, 1,1],
				[2,0, 3,1],
				[1,0, 0,1],
				[3,0, 2,1],
			];
		}
		if ( $n_groups === 2 && $spots === 2 ) {
			// SF: A1-B2, A2-B1 → odmah final
			return [
				[0,0, 1,1],
				[1,0, 0,1],
			];
		}
		if ( $n_groups === 4 && $spots === 1 ) {
			return [
				[0,0, 3,0],
				[1,0, 2,0],
			];
		}
		// Generic: 1. vs zadnji, 2. vs predzadnji itd.
		$pairs = [];
		$all   = [];
		for ( $g = 0; $g < $n_groups; $g++ )
			for ( $r = 0; $r < $spots; $r++ )
				$all[] = [$g, $r];
		// Spoji po seeding principu (1.vs.zadnji)
		$left  = array_slice( $all, 0, intdiv( count($all), 2 ) );
		$right = array_reverse( array_slice( $all, intdiv( count($all), 2 ) ) );
		foreach ( $left as $i => $l ) {
			$r = $right[ $i ] ?? null;
			if ( $r ) $pairs[] = [ $l[0], $l[1], $r[0], $r[1] ];
		}
		return $pairs;
	}

	private static function source_label( string $round, int $slot ): string {
		$map = [ 'qf' => 'QF', 'sf' => 'SF', 'final' => 'F' ];
		return ( $map[$round] ?? strtoupper($round) ) . $slot . '-W';
	}

	// ── CRUD ─────────────────────────────────────────────────────────────────

	public static function get_bracket( int $division_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT pm.*,
			        hp.post_title AS home_name,
			        ap.post_title AS away_name
			 FROM '      . self::t('playoff_matches') . ' pm
			 LEFT JOIN ' . $wpdb->posts . ' hp ON hp.ID = pm.home_team_id
			 LEFT JOIN ' . $wpdb->posts . ' ap ON ap.ID = pm.away_team_id
			 WHERE pm.division_id = %d
			 ORDER BY FIELD(pm.round,"qf","sf","final","3rd"), pm.slot ASC',
			$division_id
		) );

		$bracket = [];
		foreach ( $rows as $r ) {
			$bracket[ $r->round ][ $r->slot ] = $r;
		}
		return $bracket;
	}

	public static function get_match( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT pm.*,
			        hp.post_title AS home_name,
			        ap.post_title AS away_name
			 FROM '      . self::t('playoff_matches') . ' pm
			 LEFT JOIN ' . $wpdb->posts . ' hp ON hp.ID = pm.home_team_id
			 LEFT JOIN ' . $wpdb->posts . ' ap ON ap.ID = pm.away_team_id
			 WHERE pm.id = %d',
			$id
		) );
	}

	public static function finish_match( int $id, int $home_score, int $away_score ): void {
		global $wpdb;
		$wpdb->update( self::t('playoff_matches'), [
			'home_score' => $home_score,
			'away_score' => $away_score,
			'status'     => 'finished',
		], [ 'id' => $id ] );
		// Sinkroniziraj s wp_kurs_matches zapisnikom ako postoji
		$mid = self::get_linked_match_id( $id );
		if ( $mid ) {
			Kurs_Match::finish( $mid, $home_score, $away_score );
		}
		self::advance_winner( $id );
		do_action( 'kurs_data_changed' );
	}

	public static function reopen_match( int $id ): void {
		global $wpdb;
		$m = self::get_match( $id );
		if ( $m ) self::retract_winner( $m );
		$wpdb->update( self::t('playoff_matches'), [
			'home_score' => null,
			'away_score' => null,
			'status'     => 'scheduled',
		], [ 'id' => $id ] );
		// Sinkroniziraj s wp_kurs_matches zapisnikom ako postoji
		$mid = self::get_linked_match_id( $id );
		if ( $mid ) Kurs_Match::reopen( $mid );
		do_action( 'kurs_data_changed' );
	}

	public static function update_teams( int $id, ?int $home_id, ?int $away_id, ?string $home_label, ?string $away_label ): void {
		global $wpdb;
		$wpdb->update( self::t('playoff_matches'), [
			'home_team_id' => $home_id ?: null,
			'away_team_id' => $away_id ?: null,
			'home_label'   => sanitize_text_field( $home_label ?? '' ) ?: null,
			'away_label'   => sanitize_text_field( $away_label ?? '' ) ?: null,
		], [ 'id' => $id ] );
	}

	/**
	 * Vraća match_id iz wp_kurs_matches za ovaj playoff meč.
	 * Kreira ga (lazy) ako još ne postoji, pod uvjetom da meč ima obje ekipe.
	 */
	public static function ensure_match_record( int $playoff_match_id ): ?int {
		global $wpdb;
		$pm = self::get_match( $playoff_match_id );
		if ( ! $pm || ! $pm->home_team_id || ! $pm->away_team_id ) return null;

		if ( $pm->match_id ) return (int) $pm->match_id;

		// Kreiraj novi wp_kurs_matches zapis s group_id=0 (marker za playoff)
		$wpdb->insert( $wpdb->prefix . KURS_PREFIX . 'matches', [
			'group_id'     => 0,
			'home_team_id' => $pm->home_team_id,
			'away_team_id' => $pm->away_team_id,
			'match_date'   => $pm->match_date,
			'round'        => null,
			'status'       => $pm->status,
			'home_score'   => $pm->home_score,
			'away_score'   => $pm->away_score,
		] );
		$mid = (int) $wpdb->insert_id;
		if ( ! $mid ) return null;

		$wpdb->update( self::t('playoff_matches'), [ 'match_id' => $mid ], [ 'id' => $playoff_match_id ] );
		return $mid;
	}

	public static function get_linked_match_id( int $playoff_match_id ): ?int {
		global $wpdb;
		$mid = $wpdb->get_var( $wpdb->prepare(
			'SELECT match_id FROM ' . self::t('playoff_matches') . ' WHERE id = %d',
			$playoff_match_id
		) );
		return $mid ? (int) $mid : null;
	}

	/** Dohvati playoff_match_id za dani wp_kurs_matches.id (reverse lookup). */
	public static function find_by_match_id( int $match_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::t('playoff_matches') . ' WHERE match_id = %d',
			$match_id
		) ) ?: null;
	}

	public static function update_date( int $id, ?string $date ): void {
		global $wpdb;
		$wpdb->update( self::t('playoff_matches'), [ 'match_date' => $date ], [ 'id' => $id ] );
	}

	// ── Auto-advance ─────────────────────────────────────────────────────────

	/**
	 * Nakon zaključene utakmice, upiše pobjednika u sljedeću rundu.
	 * Mapping: QF slot → SF slot/pozicija; SF slot → Final slot/pozicija.
	 */
	private static function advance_winner( int $match_id ): void {
		global $wpdb;
		$m = self::get_match( $match_id );
		if ( ! $m || $m->status !== 'finished' ) return;

		$winner_id   = (int) $m->home_score > (int) $m->away_score ? $m->home_team_id : $m->away_team_id;
		$winner_name = (int) $m->home_score > (int) $m->away_score ? $m->home_name    : $m->away_name;

		if ( ! $winner_id ) return; // Neriješeno — ne advance automatski (potrebno produžetke/penale)

		// Odredi sljedeću rundu i slot
		[ $next_round, $next_pos ] = self::next_slot( $m->round, (int) $m->slot );
		if ( ! $next_round ) return;

		// Nađi utakmicu u sljedećoj rundi
		$next = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::t('playoff_matches') . '
			 WHERE division_id = %d AND round = %s AND slot = %d',
			$m->division_id, $next_round, $next_pos['slot']
		) );
		if ( ! $next ) return;

		$col       = $next_pos['pos'] === 'home' ? 'home_team_id' : 'away_team_id';
		$label_col = $next_pos['pos'] === 'home' ? 'home_label'   : 'away_label';
		$label     = strtoupper( $m->round ) . $m->slot . '-W';

		$wpdb->update( self::t('playoff_matches'), [
			$col       => $winner_id,
			$label_col => $label,
		], [ 'id' => $next->id ] );
	}

	private static function retract_winner( object $m ): void {
		global $wpdb;
		[ $next_round, $next_pos ] = self::next_slot( $m->round, (int) $m->slot );
		if ( ! $next_round ) return;

		$next = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::t('playoff_matches') . '
			 WHERE division_id = %d AND round = %s AND slot = %d',
			$m->division_id, $next_round, $next_pos['slot']
		) );
		if ( ! $next ) return;

		$col       = $next_pos['pos'] === 'home' ? 'home_team_id' : 'away_team_id';
		$label_col = $next_pos['pos'] === 'home' ? 'home_label'   : 'away_label';
		$label     = strtoupper( $m->round ) . $m->slot . '-W';

		$wpdb->update( self::t('playoff_matches'), [
			$col       => null,
			$label_col => $label,
		], [ 'id' => $next->id ] );
	}

	/**
	 * Mapping QF/SF slot → sljedeća runda + slot + pozicija (home/away).
	 * QF1→SF slot1/home, QF2→SF slot1/away, QF3→SF slot2/home, QF4→SF slot2/away
	 * SF1→Final slot1/home, SF2→Final slot1/away
	 */
	private static function next_slot( string $round, int $slot ): array {
		$map = [
			'qf' => [
				1 => [ 'sf',    [ 'slot' => 1, 'pos' => 'home' ] ],
				2 => [ 'sf',    [ 'slot' => 1, 'pos' => 'away' ] ],
				3 => [ 'sf',    [ 'slot' => 2, 'pos' => 'home' ] ],
				4 => [ 'sf',    [ 'slot' => 2, 'pos' => 'away' ] ],
			],
			'sf' => [
				1 => [ 'final', [ 'slot' => 1, 'pos' => 'home' ] ],
				2 => [ 'final', [ 'slot' => 1, 'pos' => 'away' ] ],
			],
		];
		return $map[ $round ][ $slot ] ?? [ null, null ];
	}

	// ── Provjeri je li bracket već generiran ─────────────────────────────────

	public static function exists( int $division_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::t('playoff_matches') . ' WHERE division_id = %d',
			$division_id
		) );
	}

	public static function reset( int $division_id ): void {
		global $wpdb;
		$wpdb->delete( self::t('playoff_matches'), [ 'division_id' => $division_id ] );
	}
}
