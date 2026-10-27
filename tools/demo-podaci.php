<?php
/**
 * DEMO PODACI — samo za lokalni razvoj (kurshr.local), NE ide na produkciju.
 *
 * Puni aktualnu KC Ligu (sezona league=kc_liga, najnovija) izmišljenim, ali
 * realističnim podacima da se vidi kako izgledaju „Po kolima", „Za medije",
 * ticker i naslovnica:
 *   - KC Liga 10 ekipa, Liga bez briga 9 ekipa (→ svako kolo jedna slobodna),
 *   - raspored svatko sa svakim (9 kola), subotama od 12. 9. 2026.,
 *   - kola 1–5 odigrana s golovima/autogolovima/kartonima, jedna utakmica
 *     4. kola KC Lige odgođena, 6. kolo = sutrašnji termin,
 *   - 3 nove demo ekipe + igrači (meta _kurs_demo = 1),
 *   - strijelci u odigranim Pan 2026 utakmicama koje nemaju golove.
 * Sve što skripta doda pamti se u opciji kurs_demo_seed, pa se može obrisati.
 *
 * Pokretanje (Git Bash, iz korijena repozitorija):
 *   PHPRC="C:/Users/Tinci/AppData/Roaming/Local/run/df7bZ9tGl/conf/php" \
 *   "/c/Users/Tinci/AppData/Roaming/Local/lightning-services/php-8.2.30+1/bin/win64/php.exe" tools/demo-podaci.php [obrisi]
 */
if ( PHP_SAPI !== 'cli' ) exit( 'Samo CLI.' );
define( 'WP_USE_THEMES', false );
require __DIR__ . '/../app/public/wp-load.php';
if ( ! class_exists( 'Kurs_Season' ) ) exit( "Plugin kurs-liga nije aktivan.\n" );

global $wpdb;
$p   = $wpdb->prefix . KURS_PREFIX;
$opt = 'kurs_demo_seed';

/* ── Brisanje prethodnog demo seta ─────────────────────────────────────── */
function kurs_demo_clean( string $p, string $opt ): void {
	global $wpdb;
	$seed = get_option( $opt );
	if ( ! $seed ) { echo "Nema demo podataka za brisanje.\n"; return; }

	foreach ( $seed['divisions'] ?? [] as $did ) {
		$ph = Kurs_Season::group_phase( (int) $did );
		foreach ( $ph ? Kurs_Season::get_groups( (int) $ph->id ) : [] as $g ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p}matches WHERE group_id = %d", $g->id ) );
			foreach ( $ids ? [ 'match_events', 'match_players' ] : [] as $tbl ) $wpdb->query( "DELETE FROM {$p}{$tbl} WHERE match_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' );
			$wpdb->delete( "{$p}matches", [ 'group_id' => $g->id ] );
			$wpdb->delete( "{$p}group_teams", [ 'group_id' => $g->id ] );
		}
		$wpdb->delete( "{$p}squads", [ 'division_id' => (int) $did ] );
	}
	if ( ! empty( $seed['events'] ) ) {
		$wpdb->query( "DELETE FROM {$p}match_events WHERE id IN (" . implode( ',', array_map( 'intval', $seed['events'] ) ) . ')' );
	}
	foreach ( $seed['posts'] ?? [] as $pid ) wp_delete_post( (int) $pid, true );
	delete_option( $opt );
	do_action( 'kurs_data_changed' );
	echo 'Obrisano: ' . count( $seed['divisions'] ?? [] ) . ' lige ispražnjene, ' . count( $seed['events'] ?? [] ) . ' Pan golova, ' . count( $seed['posts'] ?? [] ) . " demo ekipa/igrača.\n";
}

kurs_demo_clean( $p, $opt );
if ( ( $argv[1] ?? '' ) === 'obrisi' ) exit;

mt_srand( 2026 );
$seed = [ 'divisions' => [], 'events' => [], 'posts' => [] ];

/** Poissonova razdioba — realni broj golova. */
$poisson = function ( float $l ): int { $L = exp( -$l ); $k = 0; $q = 1.0; do { $k++; $q *= mt_rand() / mt_getrandmax(); } while ( $q > $L ); return $k - 1; };
/** Izbor s težinama (prvi igrač ekipe je „topscorer"). */
$weighted = function ( array $items, array $w ) { $sum = array_sum( $w ); $r = mt_rand( 1, $sum ); foreach ( $items as $i => $it ) { $r -= $w[ $i ]; if ( $r <= 0 ) return $it; } return end( $items ); };
$player_w = [ 9, 6, 4, 3, 2, 2, 1, 1, 1, 1, 1, 1 ];

/* ── KC sezona ─────────────────────────────────────────────────────────── */
$season = null;
foreach ( Kurs_Season::for_league( false ) as $s ) { if ( $s->league === 'kc_liga' ) { $season = $s; break; } }
if ( ! $season ) exit( "Nema KC sezone — kreiraj je u adminu (KURS Liga → Sezone).\n" );
Kurs_Season::scaffold( (int) $season->id, 'kc_liga' ); // ako lige još ne postoje
$divs = Kurs_Season::get_divisions( (int) $season->id );
if ( count( $divs ) < 2 ) exit( "KC sezona nema obje lige.\n" );

/* Nove demo ekipe (do 19 = 10 + 9) */
$demo_teams = [
	'MNK Drava'         => [ '#1d4f91', [ 'Dario Horvatin', 'Ivan Golub', 'Matej Ištvan', 'Kristijan Šafran', 'Luka Fuček', 'Marin Tomašić' ] ],
	'Veterani Bilogora' => [ '#2f6b2f', [ 'Zoran Kuzmić', 'Željko Pintarić', 'Dražen Bešenić', 'Ante Lacković', 'Siniša Jagić', 'Goran Peharda' ] ],
	'NK Stari grad'     => [ '#7a1f2b', [ 'Hrvoje Bunić', 'Tin Matić', 'Petar Sokač', 'Josip Kos', 'Filip Antolić', 'Mateo Vidović' ] ],
];
$team_ids = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'kurs_ekipa' AND post_status = 'publish' ORDER BY ID" ) );
$roster   = [];
foreach ( $wpdb->get_results( "SELECT DISTINCT team_id, player_id FROM {$p}squads ORDER BY id" ) as $r ) $roster[ (int) $r->team_id ][] = (int) $r->player_id;

foreach ( $demo_teams as $name => [ $color, $players ] ) {
	$tid = wp_insert_post( [ 'post_type' => 'kurs_ekipa', 'post_status' => 'publish', 'post_title' => $name ] );
	update_post_meta( $tid, '_kurs_team_color', $color );
	update_post_meta( $tid, '_kurs_demo', 1 );
	$seed['posts'][] = $tid; $team_ids[] = $tid; $roster[ $tid ] = [];
	foreach ( $players as $pl ) {
		$pid = wp_insert_post( [ 'post_type' => 'kurs_igrac', 'post_status' => 'publish', 'post_title' => $pl ] );
		update_post_meta( $pid, '_kurs_demo', 1 );
		$seed['posts'][] = $pid; $roster[ $tid ][] = $pid;
	}
}

$split = [ array_slice( $team_ids, 0, 10 ), array_slice( $team_ids, 10, 9 ) ];
$today = current_time( 'Y-m-d' );

foreach ( array_slice( $divs, 0, 2 ) as $di => $d ) {
	$did = (int) $d->id;
	$seed['divisions'][] = $did;
	$ph  = Kurs_Season::group_phase( $did );
	$grp = Kurs_Season::get_groups( (int) $ph->id )[0];

	// isprazni ligu (sezona nema stvarnih podataka) pa posloži demo
	$old = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p}matches WHERE group_id = %d", $grp->id ) );
	foreach ( $old ? [ 'match_events', 'match_players' ] : [] as $tbl ) $wpdb->query( "DELETE FROM {$p}{$tbl} WHERE match_id IN (" . implode( ',', array_map( 'intval', $old ) ) . ')' );
	$wpdb->delete( "{$p}matches", [ 'group_id' => $grp->id ] );
	$wpdb->delete( "{$p}group_teams", [ 'group_id' => $grp->id ] );
	$wpdb->delete( "{$p}squads", [ 'division_id' => $did ] );

	$strength = [];
	foreach ( $split[ $di ] as $t ) {
		Kurs_Season::assign_team( (int) $grp->id, $t );
		$strength[ $t ] = mt_rand( 10, 30 ) / 10; // 1.0 – 3.0
		foreach ( $roster[ $t ] ?? [] as $n => $pid ) {
			$wpdb->insert( "{$p}squads", [ 'player_id' => $pid, 'team_id' => $t, 'division_id' => $did, 'jersey_no' => $n + 2 ] );
		}
	}
	Kurs_Match::generate_round_robin( (int) $grp->id );

	$matches = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$p}matches WHERE group_id = %d ORDER BY round, id", $grp->id ) );
	$slot = [];
	$played = 0;
	foreach ( $matches as $m ) {
		$r     = (int) $m->round;
		$k     = $slot[ $r ] = ( $slot[ $r ] ?? -1 ) + 1;
		$start = $di === 0 ? 15 : 10; // KC Liga popodne, Liga bez briga prijepodne
		$date  = date( 'Y-m-d H:i:s', strtotime( "2026-09-12 {$start}:00" ) + ( $r - 1 ) * 7 * DAY_IN_SECONDS + $k * HOUR_IN_SECONDS );
		$upd   = [ 'match_date' => $date, 'referee1' => [ 'Kralj', 'Hajdinjak', 'Mikulić' ][ ( $r + $k ) % 3 ] ];

		$postponed = $di === 0 && $r === 4 && $k === 2;
		if ( substr( $date, 0, 10 ) < $today && ! $postponed ) {
			$h = (int) $m->home_team_id; $a = (int) $m->away_team_id;
			$hs = min( 12, $poisson( 0.6 + $strength[ $h ] * 1.3 - $strength[ $a ] * 0.35 ) );
			$as = min( 12, $poisson( 0.4 + $strength[ $a ] * 1.3 - $strength[ $h ] * 0.35 ) );
			$upd += [ 'home_score' => $hs, 'away_score' => $as, 'status' => 'finished' ];
			$played++;

			foreach ( [ [ $h, $a, $hs ], [ $a, $h, $as ] ] as [ $team, $opp, $goals ] ) {
				for ( $j = 0; $j < $goals; $j++ ) {
					$min = mt_rand( 1, 40 );
					if ( mt_rand( 1, 30 ) === 1 && ! empty( $roster[ $opp ] ) ) { // autogol protivnika
						$wpdb->insert( "{$p}match_events", [ 'match_id' => $m->id, 'team_id' => $opp, 'player_id' => $roster[ $opp ][ array_rand( $roster[ $opp ] ) ], 'event_type' => 'own_goal', 'minute' => $min, 'half' => $min > 20 ? 2 : 1 ] );
						continue;
					}
					if ( empty( $roster[ $team ] ) ) continue;
					$scorer = $weighted( $roster[ $team ], array_slice( $player_w, 0, count( $roster[ $team ] ) ) );
					$wpdb->insert( "{$p}match_events", [ 'match_id' => $m->id, 'team_id' => $team, 'player_id' => $scorer, 'event_type' => mt_rand( 1, 12 ) === 1 ? 'penalty' : 'goal', 'minute' => $min, 'half' => $min > 20 ? 2 : 1 ] );
				}
				// kartoni
				foreach ( [ 'yellow_card' => 3, 'red_card' => 40 ] as $type => $chance ) {
					if ( ! empty( $roster[ $team ] ) && mt_rand( 1, $chance ) === 1 ) {
						$min = mt_rand( 1, 40 );
						$wpdb->insert( "{$p}match_events", [ 'match_id' => $m->id, 'team_id' => $team, 'player_id' => $roster[ $team ][ array_rand( $roster[ $team ] ) ], 'event_type' => $type, 'minute' => $min, 'half' => $min > 20 ? 2 : 1 ] );
					}
				}
			}
		}
		$wpdb->update( "{$p}matches", $upd, [ 'id' => $m->id ] );
	}
	printf( "%s: %d ekipa, %d utakmica, odigrano %d, zadnje kolo %d\n", $d->name, count( $split[ $di ] ), count( $matches ), $played, Kurs_Rounds::default_round( $did ) );
}

/* ── Pan 2026: strijelci u odigranim utakmicama bez golova ─────────────── */
$pan = $wpdb->get_results(
	"SELECT m.*, ph.division_id FROM {$p}matches m
	 JOIN {$p}groups g ON g.id = m.group_id JOIN {$p}phases ph ON ph.id = g.phase_id
	 WHERE m.status = 'finished' AND NOT EXISTS (
	   SELECT 1 FROM {$p}match_events e WHERE e.match_id = m.id AND e.event_type IN ('goal','penalty','own_goal') )"
);
foreach ( $pan as $m ) {
	$sq = [];
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT team_id, player_id FROM {$p}squads WHERE division_id = %d ORDER BY id", $m->division_id ) ) as $r ) $sq[ (int) $r->team_id ][] = (int) $r->player_id;
	foreach ( [ [ (int) $m->home_team_id, (int) $m->home_score ], [ (int) $m->away_team_id, (int) $m->away_score ] ] as [ $team, $goals ] ) {
		if ( empty( $sq[ $team ] ) ) continue;
		for ( $j = 0; $j < $goals; $j++ ) {
			$min = mt_rand( 1, 40 );
			$wpdb->insert( "{$p}match_events", [ 'match_id' => $m->id, 'team_id' => $team, 'player_id' => $weighted( $sq[ $team ], array_slice( $player_w, 0, count( $sq[ $team ] ) ) ), 'event_type' => 'goal', 'minute' => $min, 'half' => $min > 20 ? 2 : 1 ] );
			$seed['events'][] = (int) $wpdb->insert_id;
		}
	}
}
echo 'Pan: golovi dodani u ' . count( $pan ) . ' utakmica (' . count( $seed['events'] ) . " golova).\n";

// Odigrane demo utakmice dobivaju postavu (match_players) iz demo rostera — kao da su završene u zapisniku.
Kurs_Match::backfill_lineups();
update_option( $opt, $seed, false );
do_action( 'kurs_data_changed' );
echo "Gotovo. Brisanje: tools/demo-podaci.php obrisi\n";
