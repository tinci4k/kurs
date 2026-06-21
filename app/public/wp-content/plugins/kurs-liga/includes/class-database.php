<?php
defined( 'ABSPATH' ) || exit;

class Kurs_Database {

	const DB_VERSION = '1.1';

	public static function install(): void {
		global $wpdb;

		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . KURS_PREFIX;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Sezone natjecanja
		dbDelta( "CREATE TABLE {$p}seasons (
			id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
			league     VARCHAR(100)      NOT NULL COMMENT 'Slobodan unos naziva lige',
			name       VARCHAR(150)      NOT NULL,
			year       SMALLINT UNSIGNED NOT NULL,
			status     VARCHAR(20)       NOT NULL DEFAULT 'active',
			created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (status)
		) $c;" );

		// Divizije unutar sezone (KC Liga ima više: Glavna, Liga bez briga)
		dbDelta( "CREATE TABLE {$p}divisions (
			id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
			season_id  INT UNSIGNED NOT NULL,
			name       VARCHAR(100) NOT NULL,
			sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
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
			id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
			phase_id INT UNSIGNED NOT NULL,
			name     VARCHAR(50)  NOT NULL,
			PRIMARY KEY (id),
			KEY idx_phase (phase_id)
		) $c;" );

		// Ekipe dodijeljene grupama
		dbDelta( "CREATE TABLE {$p}group_teams (
			id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
			group_id INT UNSIGNED    NOT NULL,
			team_id  BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_team_group (group_id, team_id)
		) $c;" );

		// Roster: igrači po ekipi i diviziji (igrač može igrati u više divizija iste sezone)
		dbDelta( "CREATE TABLE {$p}squads (
			id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
			player_id   BIGINT UNSIGNED NOT NULL,
			team_id     BIGINT UNSIGNED NOT NULL,
			division_id INT UNSIGNED    NOT NULL,
			jersey_no   TINYINT UNSIGNED,
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
			event_type VARCHAR(20)     NOT NULL COMMENT 'goal|own_goal|yellow|red',
			minute     TINYINT UNSIGNED,
			scored_for BIGINT UNSIGNED COMMENT 'za autogol: tim koji prima gol',
			PRIMARY KEY (id),
			KEY idx_match  (match_id),
			KEY idx_player (player_id)
		) $c;" );

		update_option( 'kurs_db_version', self::DB_VERSION );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		$installed = get_option( 'kurs_db_version', '0' );
		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			global $wpdb;
			$p = $wpdb->prefix . KURS_PREFIX;
			// v1.1: proširenje league stupca na VARCHAR(100)
			$wpdb->query( "ALTER TABLE {$p}seasons MODIFY COLUMN league VARCHAR(100) NOT NULL" );
			self::install();
		}
	}
}
