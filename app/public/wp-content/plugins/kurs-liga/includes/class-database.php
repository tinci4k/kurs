<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Database {

	const DB_VERSION = '1.14';

	public static function install(): void {
		global $wpdb;

		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . KURS_PREFIX;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Sezone natjecanja
		dbDelta( "CREATE TABLE {$p}seasons (
			id                 INT UNSIGNED      NOT NULL AUTO_INCREMENT,
			league             VARCHAR(100)      NOT NULL COMMENT 'Slobodan unos naziva lige',
			name               VARCHAR(150)      NOT NULL,
			year               SMALLINT UNSIGNED NOT NULL,
			status             VARCHAR(20)       NOT NULL DEFAULT 'active',
			locked             TINYINT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'zaključana sezona = konačni podaci',
			max_fouls_per_half TINYINT UNSIGNED  NOT NULL DEFAULT 5 COMMENT 'akumulirani prekršaji prije penala',
			referees           TEXT              DEFAULT NULL COMMENT 'baza sudaca sezone, JSON [{ime, prezime}]',
			created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (status)
		) $c;" );

		// Divizije unutar sezone (KC Liga ima više: Glavna, Liga bez briga)
		dbDelta( "CREATE TABLE {$p}divisions (
			id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
			season_id         INT UNSIGNED NOT NULL,
			name              VARCHAR(100) NOT NULL,
			sort_order        TINYINT UNSIGNED NOT NULL DEFAULT 0,
			has_playoff       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 = samo skupina/liga, 1 = skupine + playoff',
			advance_per_group TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'koliko ekipa iz svake grupe prolazi u playoff',
			base_round        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'preneseni golovi igrača postignuti su do tog kola (0 = prije prvog kola u sustavu)',
			PRIMARY KEY (id),
			KEY idx_season (season_id)
		) $c;" );

		// Faze unutar divizije (Grupna faza, Playoff)
		dbDelta( "CREATE TABLE {$p}phases (
			id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
			division_id INT UNSIGNED NOT NULL,
			name        VARCHAR(100) NOT NULL,
			type        VARCHAR(20)  NOT NULL DEFAULT 'group',
			sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY idx_division (division_id)
		) $c;" );

		// Grupe unutar grupne faze (Grupa A, Grupa B...)
		dbDelta( "CREATE TABLE {$p}groups (
			id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
			phase_id       INT UNSIGNED    NOT NULL,
			name           VARCHAR(50)     NOT NULL,
			playoff_spots  TINYINT UNSIGNED NOT NULL DEFAULT 2,
			PRIMARY KEY (id),
			KEY idx_phase (phase_id)
		) $c;" );

		// Ekipe dodijeljene grupama. base_* = preneseno stanje: utakmice odigrane prije unosa u sustav
		// (npr. arhivska sezona s poznatom samo konačnom ljestvicom) — pribrajaju se ljestvici;
		// rank_hint = poredak kod jednakih bodova kad odlučuju međusobne utakmice kojih nema u sustavu.
		dbDelta( "CREATE TABLE {$p}group_teams (
			id        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
			group_id  INT UNSIGNED      NOT NULL,
			team_id   BIGINT UNSIGNED   NOT NULL,
			base_p    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			base_w    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			base_d    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			base_l    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			base_gf   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			base_ga   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			rank_hint TINYINT UNSIGNED  DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_team_group (group_id, team_id)
		) $c;" );

		// Roster: igrači po ekipi i diviziji (igrač može igrati u više divizija iste sezone)
		// base_goals / base_own_goals = golovi (i autogolovi) iz utakmica koje nisu u sustavu (preneseno stanje).
		dbDelta( "CREATE TABLE {$p}squads (
			id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
			player_id      BIGINT UNSIGNED   NOT NULL,
			team_id        BIGINT UNSIGNED   NOT NULL,
			division_id    INT UNSIGNED      NOT NULL,
			jersey_no      TINYINT UNSIGNED,
			base_goals     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			base_own_goals SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY uq_squad (player_id, team_id, division_id)
		) $c;" );

		// Utakmice
		dbDelta( "CREATE TABLE {$p}matches (
			id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
			group_id     INT UNSIGNED    NOT NULL,
			home_team_id BIGINT UNSIGNED NOT NULL,
			away_team_id BIGINT UNSIGNED NOT NULL,
			home_score   TINYINT UNSIGNED,
			away_score   TINYINT UNSIGNED,
			match_date   DATETIME,
			round        TINYINT UNSIGNED,
			status       VARCHAR(20) NOT NULL DEFAULT 'scheduled',
			referee1     VARCHAR(100) DEFAULT NULL,
			referee2     VARCHAR(100) DEFAULT NULL,
			created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_group  (group_id),
			KEY idx_status (status)
		) $c;" );

		// Događaji na utakmici (golovi, kartoni s minutama i igračima)
		dbDelta( "CREATE TABLE {$p}match_events (
			id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
			match_id   INT UNSIGNED    NOT NULL,
			team_id    BIGINT UNSIGNED NOT NULL,
			player_id  BIGINT UNSIGNED,
			event_type VARCHAR(20)     NOT NULL COMMENT 'goal|penalty|own_goal|yellow_card|second_yellow|red_card|foul',
			minute     TINYINT UNSIGNED,
			half       TINYINT UNSIGNED COMMENT 'poluvrijeme (1|2) — za akumulirane prekršaje',
			scored_for BIGINT UNSIGNED COMMENT 'za autogol: tim koji prima gol',
			PRIMARY KEY (id),
			KEY idx_match  (match_id),
			KEY idx_player (player_id)
		) $c;" );

		// Postava utakmice: sastav ekipe spremljen pri završetku utakmice (s tadašnjim brojem dresa),
		// da prijelaz/odlazak igrača iz rostera ne mijenja odigrane utakmice. Vidi Kurs_Match::lineup().
		dbDelta( "CREATE TABLE {$p}match_players (
			id        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
			match_id  INT UNSIGNED     NOT NULL,
			team_id   BIGINT UNSIGNED  NOT NULL,
			player_id BIGINT UNSIGNED  NOT NULL,
			jersey_no TINYINT UNSIGNED DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_match_player (match_id, player_id),
			KEY idx_player (player_id)
		) $c;" );

		// Playoff bracket utakmice
		dbDelta( "CREATE TABLE {$p}playoff_matches (
			id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
			division_id  INT UNSIGNED    NOT NULL,
			round        VARCHAR(20)     NOT NULL COMMENT 'qf|sf|final|3rd',
			slot         TINYINT UNSIGNED NOT NULL COMMENT 'pozicija unutar runde (1,2,3,4)',
			home_team_id BIGINT UNSIGNED DEFAULT NULL,
			away_team_id BIGINT UNSIGNED DEFAULT NULL,
			home_score   TINYINT UNSIGNED DEFAULT NULL,
			away_score   TINYINT UNSIGNED DEFAULT NULL,
			home_pen     TINYINT UNSIGNED DEFAULT NULL COMMENT 'penali — samo kod neriješenog',
			away_pen     TINYINT UNSIGNED DEFAULT NULL,
			status       VARCHAR(20)     NOT NULL DEFAULT 'scheduled',
			match_date   DATETIME        DEFAULT NULL,
			home_label   VARCHAR(20)     DEFAULT NULL COMMENT 'npr. A1, QF1-W',
			away_label   VARCHAR(20)     DEFAULT NULL,
			match_id     BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK → kurs_matches.id (zapisnik)',
			PRIMARY KEY (id),
			KEY idx_division (division_id),
			KEY idx_round    (division_id, round)
		) $c;" );

		update_option( 'kurs_db_version', self::DB_VERSION );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		$installed = get_option( 'kurs_db_version', '0' );
		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			global $wpdb;
			$p = $wpdb->prefix . KURS_PREFIX;

			// dbDelta (install) idempotentno kreira tablice i DODAJE nove stupce
			// koji nedostaju (npr. v1.2 groups.playoff_spots, v1.5 playoff_matches.match_id),
			// jer su već dio CREATE TABLE definicija. Time izbjegavamo "ADD COLUMN IF NOT EXISTS"
			// koji nije podržan u MySQL-u (samo MariaDB) i bacao je SQL grešku u debug.log.
			self::install();

			// v1.1 — promjena tipa postojećeg stupca (valjan MySQL; dbDelta ne mijenja
			// uvijek pouzdano duljinu VARCHAR-a).
			$wpdb->query( "ALTER TABLE {$p}seasons MODIFY COLUMN league VARCHAR(100) NOT NULL" );
		}

		// v1.10 — baza sudaca se vodi po sezoni (seasons.referees): dotadašnju zajedničku listu
		// (opcija kurs_referees) dobivaju sve postojeće sezone, a opcija se briše. Uvjet je stara
		// opcija, ne verzija baze — prekinuta migracija (npr. usred deploya) ponovi se sama.
		$old = get_option( 'kurs_referees' );
		if ( $old !== false && method_exists( 'Kurs_Season', 'set_referees' ) ) {
			foreach ( is_array( $old ) && $old ? Kurs_Season::all() : [] as $s ) {
				if ( ! Kurs_Season::get_referees( (int) $s->id ) ) Kurs_Season::set_referees( (int) $s->id, $old );
			}
			delete_option( 'kurs_referees' );
		}

		// v1.12 — postave utakmica (match_players): već završene utakmice dobivaju postavu iz
		// tadašnjeg rostera. Uvjet je opcija, ne verzija baze (isti razlog kao gore).
		if ( ! get_option( 'kurs_lineups_v1' ) && method_exists( 'Kurs_Match', 'backfill_lineups' )
			&& version_compare( get_option( 'kurs_db_version', '0' ), '1.12', '>=' ) ) {
			Kurs_Match::backfill_lineups();
			update_option( 'kurs_lineups_v1', 1 );
		}
	}
}
