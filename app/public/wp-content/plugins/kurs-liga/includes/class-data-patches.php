<?php
/**
 * Jednokratni paketi podataka (1.36.0) — promjene podataka koje stignu nakon prijenosa baze na live.
 *
 * Live je izvor istine (korisnici, odgovori iz presjeka, statistika), pa se baza više ne prenosi.
 * Nove podatke (rosteri iz mailova, odgovori iz presjeka, sponzori) pišemo kao paket u kodu:
 * nakon deploya se izvrši SAM, JEDNOM, na prvom zahtjevu (zaključavanje opcijom), i traži ekipe,
 * igrače i sponzore po IMENU — ne po ID-u — pa radi jednako lokalno i na liveu.
 *
 * Paket = datoteka includes/patches/<id>.php koja vraća callable( Kurs_Data_Patches $p ): void.
 * Izvršeni paketi i njihov dnevnik: opcija `kurs_data_patches` (prikaz: KURS Liga → Postavke).
 */

defined( 'ABSPATH' ) || exit;

class Kurs_Data_Patches {

	const OPT = 'kurs_data_patches';
	/** Redoslijed izvršavanja. */
	const PATCHES = [ '2026-11-13-rosteri-sponzori' ];

	public array $log = [];

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'run' ], 120 );
	}

	public static function done(): array {
		$d = get_option( self::OPT, [] );
		return is_array( $d ) ? $d : [];
	}

	public static function run(): void {
		if ( wp_installing() || ! class_exists( 'Kurs_Season' ) ) return;
		$done = self::done();
		foreach ( self::PATCHES as $id ) {
			if ( isset( $done[ $id ] ) ) continue;
			$file = KURS_DIR . 'includes/patches/' . $id . '.php';
			if ( ! is_file( $file ) ) continue;
			$lock = 'kurs_patch_lock_' . md5( $id );
			if ( ! add_option( $lock, time(), '', false ) ) {          // drugi zahtjev ga već izvršava
				if ( time() - (int) get_option( $lock ) < 300 ) continue;
				update_option( $lock, time(), false );                 // zapeli lock (stariji od 5 min)
			}
			$p  = new self();
			$cb = require $file;
			try {
				$cb( $p );
				$ok = true;
			} catch ( \Throwable $e ) {
				$p->log[] = 'GREŠKA: ' . $e->getMessage();
				$ok = false;
			}
			$done = self::done();
			$done[ $id ] = [ 'at' => current_time( 'mysql' ), 'ok' => $ok, 'log' => $p->log ];
			update_option( self::OPT, $done, false );
			delete_option( $lock );
			wp_cache_flush();
			do_action( 'kurs_data_changed' );
		}
	}

	/* ── Pomoćnici za pakete ─────────────────────────────────────────────── */

	public function team( string $name ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'kurs_ekipa' AND post_status = 'publish' AND post_title = %s ORDER BY ID LIMIT 1", $name ) );
	}

	/** Liga ekipe u aktualnoj sezoni natjecanja (0 ako nije uvrštena). */
	public function current_division( int $team_id, bool $pan = false ): int {
		$cur = Kurs_Season::current_for_league( $pan );
		if ( ! $cur ) return 0;
		foreach ( Kurs_Season::get_team_contexts( $team_id ) as $c ) if ( (int) $c->season_id === (int) $cur->id ) return (int) $c->division_id;
		return 0;
	}

	public function rename( int $pid, string $name ): void {
		$old = get_the_title( $pid );
		if ( $old === $name ) return;
		wp_update_post( [ 'ID' => $pid, 'post_title' => $name, 'post_name' => wp_unique_post_slug( sanitize_title( $name ), $pid, 'publish', 'kurs_igrac', 0 ) ] );
		$this->log[] = "  preimenovan: {$old} → {$name}";
	}

	private static function norm( string $s ): string {
		return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $s ) ) );
	}

	/**
	 * Odgovara li stari upis („Fulir”, „M. Kovaček”, „Dan. Nemčić” ili puno ime) novom punom imenu?
	 * Prezime-samo pristaje samo ako na novom popisu postoji točno jedno takvo prezime ($uniq).
	 */
	private static function matches( string $old, string $new, array $surname_count ): bool {
		$o = self::norm( $old ); $n = self::norm( $new );
		if ( $o === $n ) return true;
		$ot = explode( ' ', $o ); $nt = explode( ' ', $n );
		$sur = end( $nt );
		if ( end( $ot ) !== $sur ) return false;
		if ( count( $ot ) === 1 ) return ( $surname_count[ $sur ] ?? 0 ) === 1;                 // „Fulir”
		$pre = rtrim( $ot[0], '.' );                                                              // „m.” / „dan.”
		return count( $ot ) === 2 && str_ends_with( $ot[0], '.' ) && $pre !== '' && str_starts_with( $nt[0], $pre );
	}

	/**
	 * Postavi roster ekipe u ligi točno prema popisu (puna imena). Postojeći igrači ekipe (ova ili prošla
	 * sezona) se prepoznaju i po potrebi preimenuju — povijest im ostaje; ostali se dodaju kao novi.
	 * Tko nije na popisu, makne se iz rostera ove lige (odigrane utakmice i povijest ostaju).
	 */
	public function set_roster( string $team_name, array $names, bool $pan = false ): void {
		$tid = $this->team( $team_name );
		if ( ! $tid ) { $this->log[] = "PRESKOČENO: ekipa „{$team_name}” ne postoji"; return; }
		$div = $this->current_division( $tid, $pan );
		if ( ! $div ) { $this->log[] = "PRESKOČENO: „{$team_name}” nije u ligi aktualne sezone"; return; }
		$season = Kurs_Season::get_division( $div )->season_id ?? 0;
		if ( $season && Kurs_Season::is_locked( (int) $season ) ) { $this->log[] = "PRESKOČENO: sezona za „{$team_name}” je zaključana"; return; }

		$this->log[] = "{$team_name} — roster (" . count( $names ) . ' igrača):';
		$current = [];
		foreach ( Kurs_Season::get_squad( $tid, $div ) as $s ) $current[ (int) $s->player_id ] = (string) $s->player_name;
		$history = [];
		foreach ( Kurs_Season::get_team_contexts( $tid ) as $c ) {
			if ( (int) $c->division_id === $div ) continue;
			foreach ( Kurs_Season::get_squad( $tid, (int) $c->division_id ) as $s ) $history[ (int) $s->player_id ] ??= (string) $s->player_name;
		}
		$surname_count = [];
		foreach ( $names as $n ) { $t = explode( ' ', self::norm( $n ) ); $surname_count[ end( $t ) ] = ( $surname_count[ end( $t ) ] ?? 0 ) + 1; }

		$keep = [];
		foreach ( $names as $name ) {
			$name = trim( preg_replace( '/\s+/u', ' ', $name ) );
			$pid  = 0;
			foreach ( [ $current, $history ] as $pool ) {
				foreach ( $pool as $id => $old ) {
					if ( isset( $keep[ $id ] ) ) continue;
					if ( self::matches( $old, $name, $surname_count ) ) { $pid = $id; break 2; }
				}
			}
			if ( $pid ) {
				$this->rename( $pid, $name );
				if ( ! isset( $current[ $pid ] ) ) $this->log[] = "  vraćen iz prošle sezone: {$name}";
			} else {
				$pid = $this->free_player( $name, $div );
				if ( $pid ) $this->log[] = "  postojeći igrač iz registra: {$name}";
				else {
					$pid = (int) wp_insert_post( [ 'post_title' => $name, 'post_type' => 'kurs_igrac', 'post_status' => 'publish' ] );
					update_post_meta( $pid, '_kurs_player_type', 'player' );
					$this->log[] = "  novi igrač: {$name}";
				}
			}
			$other = Kurs_Season::player_team_in_division( $pid, $div );
			if ( $other && $other !== $tid ) {
				$this->log[] = "  UPOZORENJE: {$name} je u istoj ligi i za „" . get_the_title( $other ) . "” — nije premješten";
				continue;
			}
			if ( ! isset( $current[ $pid ] ) ) Kurs_Season::add_to_squad( $pid, $tid, $div, 0 );
			$keep[ $pid ] = true;
		}
		foreach ( $current as $pid => $old ) {
			if ( isset( $keep[ $pid ] ) ) continue;
			Kurs_Season::remove_from_squad( $pid, $tid, $div );
			$this->log[] = "  maknut s popisa (povijest ostaje): {$old}";
		}
	}

	/** Igrač točno tog imena koji ne igra ni za koga u aktualnim sezonama (inače 0 → novi zapis). */
	private function free_player( string $name, int $div ): int {
		global $wpdb;
		$p   = $wpdb->prefix . KURS_PREFIX;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'kurs_igrac' AND post_status = 'publish' AND post_title = %s", $name ) );
		if ( count( $ids ) !== 1 ) return 0;
		$season = (int) ( Kurs_Season::get_division( $div )->season_id ?? 0 );
		$busy   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}squads sq JOIN {$p}divisions d ON d.id = sq.division_id WHERE sq.player_id = %d AND d.season_id = %d", (int) $ids[0], $season ) );
		return $busy ? 0 : (int) $ids[0];
	}

	public function set_meta( string $team_name, string $key, string $value, string $label ): void {
		$tid = $this->team( $team_name );
		if ( ! $tid ) { $this->log[] = "PRESKOČENO: ekipa „{$team_name}” ne postoji"; return; }
		$old = (string) get_post_meta( $tid, $key, true );
		if ( $old === $value ) return;
		update_post_meta( $tid, $key, $value );
		$this->log[] = "{$team_name} — {$label}: " . ( $old !== '' ? "{$old} → " : '' ) . $value;
	}

	public function rename_player_in_team( string $team_name, string $from, string $to ): void {
		$tid = $this->team( $team_name );
		$div = $tid ? $this->current_division( $tid ) : 0;
		foreach ( $div ? Kurs_Season::get_squad( $tid, $div ) : [] as $s ) {
			if ( self::norm( (string) $s->player_name ) === self::norm( $from ) ) { $this->log[] = "{$team_name}:"; $this->rename( (int) $s->player_id, $to ); return; }
		}
	}

	/** Preimenuj igrača iz bilo koje sezone ekipe (npr. prezime-samo iz prošle sezone u puno ime). */
	public function rename_player_any( string $team_name, string $from, string $to ): void {
		$tid = $this->team( $team_name );
		foreach ( $tid ? Kurs_Season::get_team_contexts( $tid ) : [] as $c ) {
			foreach ( Kurs_Season::get_squad( $tid, (int) $c->division_id ) as $s ) {
				if ( self::norm( (string) $s->player_name ) === self::norm( $from ) ) {
					$this->log[] = "{$team_name} ({$c->season_name}):";
					$this->rename( (int) $s->player_id, $to );
					return;
				}
			}
		}
	}

	/** Sponzor lige (CPT `sponzor`) po nazivu. */
	public function sponsor( string $name ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sponzor' AND post_status IN ('publish','draft') AND post_title = %s ORDER BY ID LIMIT 1", $name ) );
	}
}
