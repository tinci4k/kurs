<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Season {

	/**
	 * Natjecanja KURS-a — fiksna struktura (sustav služi isključivo udruzi).
	 * Nova sezona = odabir natjecanja + godine; lige, faze i grupe kreiraju se same.
	 *   KC Liga  → uvijek 2 lige (seniori + veterani), svaka jedna skupina, jednokružno, bez playoffa.
	 *   Pan      → jedna liga, 2 ili 4 skupine, 2 najbolja iz svake idu u playoff.
	 */
	public const COMPETITIONS = [
		'kc_liga'    => [
			'label'     => 'KC Liga',
			'divisions' => [ 'KC Liga', 'Liga bez briga' ],
			'groups'    => false,
			'playoff'   => false,
			'advance'   => 0,
			'span_year' => true,  // zimska sezona: 2026/2027
		],
		'pan_ljetna' => [
			'label'     => 'Pan Ljetna liga',
			'divisions' => [ 'Pan Ljetna liga' ],
			'groups'    => true,
			'playoff'   => true,
			'advance'   => 2,
			'span_year' => false,
		],
	];

	/** Broj skupina koje playoff motor podržava (4 → četvrtfinale, 2 → polufinale). */
	public const PAN_GROUP_OPTIONS = [ 2, 4 ];

	private static function t( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . KURS_PREFIX . $name;
	}

	public static function competition( string $league ): ?array {
		return self::COMPETITIONS[ $league ] ?? null;
	}

	/** Naziv sezone iz natjecanja i godine: „KC Liga 2026/2027", „Pan Ljetna liga 2026". */
	public static function default_name( string $league, int $year ): string {
		$c = self::competition( $league );
		if ( ! $c ) return (string) $year;
		return $c['label'] . ' ' . ( $c['span_year'] ? $year . '/' . ( $year + 1 ) : $year );
	}

	/** Postoji li već sezona tog natjecanja za tu godinu? Vraća njen ID ili 0. */
	public static function find_for( string $league, int $year ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . self::t( 'seasons' ) . ' WHERE league = %s AND year = %d LIMIT 1',
			$league, $year
		) );
	}

	/**
	 * Kreira lige, fazu i skupine za novu sezonu prema COMPETITIONS.
	 * Ne radi ništa ako sezona već ima lige (postojeće sezone se ne diraju).
	 */
	public static function scaffold( int $season_id, string $league, int $pan_groups = 4 ): void {
		if ( self::get_divisions( $season_id ) ) return;
		$c = self::competition( $league );
		if ( ! $c ) return;

		foreach ( $c['divisions'] as $order => $div_name ) {
			$did = self::create_division( $season_id, $div_name, $order, $c['playoff'] ? 1 : 0, $c['advance'] );
			if ( ! $did ) continue;
			$pid = self::create_phase( $did, 'Grupna faza', 'group' );
			if ( ! $pid ) continue;

			if ( $c['groups'] ) {
				$n = in_array( $pan_groups, self::PAN_GROUP_OPTIONS, true ) ? $pan_groups : 4;
				for ( $i = 0; $i < $n; $i++ ) self::create_group( $pid, 'Grupa ' . chr( 65 + $i ) );
			} else {
				self::create_group( $pid, $div_name ); // liga = jedna skupina
			}
		}
	}

	/** Grupna faza lige (svaka liga ima točno jednu). */
	public static function group_phase( int $division_id ): ?object {
		foreach ( self::get_phases( $division_id ) as $ph ) {
			if ( $ph->type === 'group' ) return $ph;
		}
		return null;
	}

	public static function group_match_count( int $group_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::t( 'matches' ) . ' WHERE group_id = %d',
			$group_id
		) );
	}

	/**
	 * Promijeni broj skupina Pan lige (2 ↔ 4). Dodaje prazne skupine ili briše
	 * zadnje — ali samo ako su prazne (bez ekipa i utakmica) i playoff još nije generiran.
	 */
	public static function set_group_count( int $division_id, int $count ): bool|\WP_Error {
		if ( ! in_array( $count, self::PAN_GROUP_OPTIONS, true ) ) {
			return new \WP_Error( 'kurs_groups', 'Broj skupina mora biti 2 ili 4.' );
		}
		if ( class_exists( 'Kurs_Playoff' ) && Kurs_Playoff::exists( $division_id ) ) {
			return new \WP_Error( 'kurs_groups', 'Playoff je već generiran — broj skupina se više ne može mijenjati.' );
		}
		$phase = self::group_phase( $division_id );
		if ( ! $phase ) return new \WP_Error( 'kurs_groups', 'Liga nema grupnu fazu.' );

		$groups = self::get_groups( (int) $phase->id ); // poredano po nazivu
		$have   = count( $groups );

		if ( $count > $have ) {
			$names = array_map( fn( $g ) => $g->name, $groups );
			for ( $i = 0; count( $names ) < $count && $i < 26; $i++ ) {
				$name = 'Grupa ' . chr( 65 + $i );
				if ( in_array( $name, $names, true ) ) continue;
				self::create_group( (int) $phase->id, $name );
				$names[] = $name;
			}
		} elseif ( $count < $have ) {
			$remove = array_slice( $groups, $count );
			foreach ( $remove as $g ) { // provjeri SVE prije brisanja ičega
				if ( self::get_group_teams( (int) $g->id ) || self::group_match_count( (int) $g->id ) ) {
					return new \WP_Error( 'kurs_groups', sprintf( '%s već ima ekipe ili utakmice — najprije ih ukloni.', $g->name ) );
				}
			}
			foreach ( $remove as $g ) self::delete_group( (int) $g->id );
		}
		return true;
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

	// ── Suci sezone (u zapisniku se nude kao prijedlozi) ──────────────────────

	/** Suci sezone: [ [ 'ime' => …, 'prezime' => … ], … ], sortirano po prezimenu (seasons.referees, JSON). */
	public static function get_referees( int $season_id ): array {
		$s    = $season_id ? self::get( $season_id ) : null;
		$rows = json_decode( (string) ( $s->referees ?? '' ), true );
		return is_array( $rows ) ? $rows : [];
	}

	/** Puna imena sudaca sezone („Ime Prezime"). */
	public static function referee_names( int $season_id ): array {
		return array_map( fn( $r ) => trim( $r['ime'] . ' ' . $r['prezime'] ), self::get_referees( $season_id ) );
	}

	/**
	 * Spremi suce sezone. Prima retke [ 'ime', 'prezime' ] ili puna imena („Ivan Horvat" →
	 * ime Ivan, prezime Horvat; jedna riječ = prezime). Prazni i duplikati se izbacuju.
	 */
	public static function set_referees( int $season_id, array $refs ): void {
		global $wpdb;
		$rows = [];
		foreach ( $refs as $r ) {
			if ( is_string( $r ) ) {
				$parts = preg_split( '/\s+/', trim( $r ), 2 );
				$r     = count( $parts ) === 2 ? [ 'ime' => $parts[0], 'prezime' => $parts[1] ] : [ 'prezime' => $parts[0] ];
			}
			$ime     = sanitize_text_field( (string) ( $r['ime'] ?? '' ) );
			$prezime = sanitize_text_field( (string) ( $r['prezime'] ?? '' ) );
			if ( $ime === '' && $prezime === '' ) continue;
			$rows[ mb_strtolower( "$ime $prezime" ) ] ??= [ 'ime' => $ime, 'prezime' => $prezime ]; // duplikat: vrijedi prvi
		}
		uasort( $rows, fn( $a, $b ) => strnatcasecmp( remove_accents( "{$a['prezime']} {$a['ime']}" ), remove_accents( "{$b['prezime']} {$b['ime']}" ) ) );
		$wpdb->update(
			self::t( 'seasons' ),
			[ 'referees' => $rows ? wp_json_encode( array_values( $rows ), JSON_UNESCAPED_UNICODE ) : null ],
			[ 'id' => $season_id ]
		);
	}

	/** Dodaj nove suce sezoni (puna imena, npr. upisana u zapisniku); postojeći se preskaču. */
	public static function add_referees( int $season_id, array $names ): void {
		if ( ! $season_id ) return;
		$known = array_map( 'mb_strtolower', self::referee_names( $season_id ) );
		$new   = array_filter( array_map( 'trim', $names ), fn( $n ) => $n !== '' && ! in_array( mb_strtolower( $n ), $known, true ) );
		if ( $new ) self::set_referees( $season_id, array_merge( self::get_referees( $season_id ), $new ) );
	}

	/** Ranije sezone istog natjecanja, najnovija prva (manja godina, ili ista godina i manji id). */
	private static function earlier_seasons( int $season_id ): array {
		$s = self::get( $season_id );
		if ( ! $s ) return [];
		return array_values( array_filter(
			self::for_league( self::is_pan_league( (string) $s->league ) ), // year DESC, id DESC
			fn( $o ) => (int) $o->year < (int) $s->year || ( (int) $o->year === (int) $s->year && (int) $o->id < $season_id )
		) );
	}

	/** Prethodna sezona istog natjecanja (izvor za prijenos ekipa i rostera) ili null. */
	public static function previous_season( int $season_id ): ?object {
		return self::earlier_seasons( $season_id )[0] ?? null;
	}

	/** Najnovija ranija sezona istog natjecanja koja ima suce (izvor za prijenos) ili null. */
	public static function previous_with_referees( int $season_id ): ?object {
		foreach ( self::earlier_seasons( $season_id ) as $o ) {
			if ( trim( (string) $o->referees ) !== '' ) return $o;
		}
		return null;
	}

	// ── Prijenos ekipa i rostera iz prethodne sezone ──────────────────────────

	/** Skupine sezone [ group_id => { id, name, division_id, division_name, groups_in_division } ], redom liga pa skupina. */
	public static function season_groups( int $season_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT g.id, g.name, d.id AS division_id, d.name AS division_name
			 FROM '  . self::t( 'groups' )    . ' g
			 JOIN '  . self::t( 'phases' )    . ' ph ON ph.id = g.phase_id
			 JOIN '  . self::t( 'divisions' ) . ' d  ON d.id  = ph.division_id
			 WHERE d.season_id = %d
			 ORDER BY d.sort_order, d.id, g.name',
			$season_id
		) ) ?: [];
		$per_div = array_count_values( array_map( fn( $r ) => (int) $r->division_id, $rows ) );
		$out     = [];
		foreach ( $rows as $r ) {
			$r->groups_in_division = $per_div[ (int) $r->division_id ];
			$out[ (int) $r->id ]   = $r;
		}
		return $out;
	}

	/**
	 * Ekipe sezone po ligama, ključ „division_id:team_id" (ekipa može igrati u više liga):
	 * { team_id, team_name, division_id, division_name, group_id, group_name, players }.
	 */
	public static function season_teams( int $season_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT gt.team_id, p.post_title AS team_name, d.id AS division_id, d.name AS division_name,
			        g.id AS group_id, g.name AS group_name,
			        ( SELECT COUNT(*) FROM ' . self::t( 'squads' ) . ' sq WHERE sq.team_id = gt.team_id AND sq.division_id = d.id ) AS players
			 FROM '  . self::t( 'group_teams' ) . ' gt
			 JOIN '  . self::t( 'groups' )      . ' g  ON g.id  = gt.group_id
			 JOIN '  . self::t( 'phases' )      . ' ph ON ph.id = g.phase_id
			 JOIN '  . self::t( 'divisions' )   . ' d  ON d.id  = ph.division_id
			 JOIN '  . $wpdb->posts             . ' p  ON p.ID  = gt.team_id
			 WHERE d.season_id = %d AND p.post_status = \'publish\'
			 ORDER BY d.sort_order, d.id, g.name, p.post_title',
			$season_id
		) ) ?: [];
		$out = [];
		foreach ( $rows as $r ) $out[ (int) $r->division_id . ':' . (int) $r->team_id ] = $r;
		return $out;
	}

	/**
	 * Prijedlog skupine u novoj sezoni za svaku ekipu ranije sezone: ista liga (po nazivu), u njoj
	 * skupina istog imena, a ako je nema (npr. Pan 4 → 2 skupine) — skupina s najmanje ekipa.
	 * Vraća [ „division_id:team_id" => group_id | 0 ].
	 */
	public static function carry_over_defaults( int $from_season, int $to_season ): array {
		$groups = self::season_groups( $to_season );
		$fill   = [];
		foreach ( $groups as $gid => $g ) $fill[ $gid ] = count( self::get_group_teams( $gid ) );
		$out = [];
		foreach ( self::season_teams( $from_season ) as $key => $t ) {
			$same_div = array_filter( $groups, fn( $g ) => mb_strtolower( $g->division_name ) === mb_strtolower( $t->division_name ) );
			$same_grp = array_filter( $same_div, fn( $g ) => $g->name === $t->group_name );
			if ( $same_grp ) {
				$gid = (int) array_key_first( $same_grp );
			} elseif ( $same_div ) {
				$cand = array_intersect_key( $fill, $same_div );
				$gid  = (int) array_keys( $cand, min( $cand ), true )[0];
			} else {
				$gid = 0;
			}
			if ( $gid ) $fill[ $gid ]++;
			$out[ $key ] = $gid;
		}
		return $out;
	}

	/**
	 * Prijenos iz ranije sezone: $carry = [ „division_id:team_id" (izvor) => group_id u ovoj sezoni ].
	 * Ekipa ide u zadanu skupinu, a uz $rosters i njezini igrači iz lige u kojoj je igrala, s brojevima
	 * dresova. Ekipa koja je već u ovoj sezoni (prenesena ili ručno uvrštena) se preskače cijela, kao
	 * i igrač koji u ciljnoj ligi već igra za drugu ekipu. Vraća [ 'teams' => N, 'players' => M, 'skipped' => K ].
	 */
	public static function carry_over_teams( int $from_season, int $to_season, array $carry, bool $rosters = true ): array {
		$src       = self::season_teams( $from_season );
		$groups    = self::season_groups( $to_season );
		$in_season = array_flip( array_map( fn( $t ) => (int) $t->team_id, self::season_teams( $to_season ) ) );
		$n         = [ 'teams' => 0, 'players' => 0, 'skipped' => 0 ];
		foreach ( $carry as $key => $gid ) {
			$t = $src[ $key ] ?? null;
			$g = $groups[ (int) $gid ] ?? null;
			if ( ! $t || ! $g || isset( $in_season[ (int) $t->team_id ] ) ) continue;
			$tid = (int) $t->team_id;
			$div = (int) $g->division_id;
			self::assign_team( (int) $g->id, $tid );
			$in_season[ $tid ] = true;
			$n['teams']++;
			if ( ! $rosters ) continue;
			foreach ( self::get_squad( $tid, (int) $t->division_id ) as $pl ) {
				$other = self::player_team_in_division( (int) $pl->player_id, $div );
				if ( $other === $tid ) continue;                // već je u rosteru
				if ( $other ) { $n['skipped']++; continue; }    // u toj ligi već igra za drugu ekipu
				self::add_to_squad( (int) $pl->player_id, $tid, $div, (int) $pl->jersey_no );
				$n['players']++;
			}
		}
		return $n;
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

	public static function delete_division( int $id ): void {
		global $wpdb;
		// Playoff (bracket + njegovi zapisnici s group_id=0 i eventi) — inače ostaju siročad
		if ( class_exists( 'Kurs_Playoff' ) ) Kurs_Playoff::reset( $id );
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

	/** Ekipa (team_id) u čijem je rosteru igrač u toj ligi, ili 0. Pravilo: u jednoj ligi igrač igra za jednu ekipu. */
	public static function player_team_in_division( int $player_id, int $division_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT team_id FROM ' . self::t( 'squads' ) . ' WHERE player_id = %d AND division_id = %d ORDER BY id LIMIT 1',
			$player_id, $division_id
		) );
	}

	/** Skupina iste lige (divizije) u kojoj ekipa već igra — ekipa smije biti samo u jednoj. */
	public static function team_group_in_division( int $division_id, int $team_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT g.id, g.name FROM ' . self::t( 'group_teams' ) . ' gt
			 JOIN ' . self::t( 'groups' ) . ' g ON g.id = gt.group_id
			 JOIN ' . self::t( 'phases' ) . ' ph ON ph.id = g.phase_id
			 WHERE ph.division_id = %d AND gt.team_id = %d LIMIT 1',
			$division_id, $team_id
		) ) ?: null;
	}

	/** Broj utakmica ekipe u skupini (ne smije se maknuti iz skupine dok ih ima). */
	public static function team_match_count( int $group_id, int $team_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::t( 'matches' ) . ' WHERE group_id = %d AND ( home_team_id = %d OR away_team_id = %d )',
			$group_id, $team_id, $team_id
		) );
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
