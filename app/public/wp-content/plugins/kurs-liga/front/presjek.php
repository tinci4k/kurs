<?php
/**
 * /presjek/ — presjek ekipa i rostera za KURS (Kurs_Presjek::render). Podaci: presjek-data.json.
 */
defined( 'ABSPATH' ) || exit;

$d       = Kurs_Presjek::data();
$answers = Kurs_Presjek::answers();
$teams   = $d['teams'] ?? [];
$qs      = $d['questions'] ?? [];
$n_upd   = count( array_filter( $teams, fn( $t ) => $t['status'] === 'updated' ) );
$n_done  = count( array_filter( $qs, fn( $q ) => ! empty( $answers[ $q['id'] ] ) ) );
$q_by_team = [];
foreach ( $qs as $q ) $q_by_team[ (int) $q['team'] ][] = $q['id'];

$initial = [];
foreach ( $answers as $qid => $list ) $initial[ $qid ] = Kurs_Presjek::public_entries( (array) $list );

$chips = function ( array $names, string $cls = '' ) {
	echo '<ul class="kp-chips">';
	foreach ( $names as $n ) echo '<li class="kp-chip ' . esc_attr( $cls ) . '">' . esc_html( $n ) . '</li>';
	echo '</ul>';
};

$note_box = function ( string $qid, string $label, string $ph ) {
	?>
	<div class="kp-note" data-q="<?php echo esc_attr( $qid ); ?>">
		<label class="kp-note__lbl" for="kp-<?php echo esc_attr( $qid ); ?>"><?php echo esc_html( $label ); ?></label>
		<textarea id="kp-<?php echo esc_attr( $qid ); ?>" class="kp-text" rows="3" placeholder="<?php echo esc_attr( $ph ); ?>"></textarea>
		<div class="kp-row">
			<button type="button" class="kp-btn kp-save">Pošalji</button>
			<span class="kp-status" role="status" aria-live="polite"></span>
		</div>
		<ol class="kp-history"></ol>
	</div>
	<?php
};
?><!doctype html>
<html lang="hr">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Presjek ekipa — KURS</title>
<?php wp_print_styles( [ 'kurs-presjek' ] ); ?>
</head>
<body class="kp-body">

<header class="kp-bar">
	<span class="kp-bar__brand">
		<span class="kp-bar__stripes" aria-hidden="true"><i></i><i></i><i></i></span>
		<span class="kp-bar__name">KURS</span>
		<span class="kp-bar__app">Presjek ekipa</span>
	</span>
	<nav class="kp-bar__nav">
		<a href="#pitanja">Pitanja</a>
		<a href="#ekipe">Ekipe</a>
		<a href="#napravljeno">Napravljeno</a>
	</nav>
</header>

<main class="kp-main">

	<section class="kp-hero">
		<p class="kp-eyebrow"><?php echo esc_html( $d['season'] ?? '' ); ?> · stanje <?php echo esc_html( $d['as_of'] ?? '' ); ?></p>
		<h1 class="kp-h1">Ekipe i rosteri nove sezone</h1>
		<p class="kp-lead">Ovdje je sve što smo ove jeseni upisali na kurskc.hr: koje su ekipe poslale popis, tko je nov, tko više nije u rosteru i kome smo dopunili ime. Na dnu svake ekipe možete poslati ispravak ili popis, a na <a href="#pitanja">otvorena pitanja</a> odgovoriti jednim klikom.</p>
		<div class="kp-stats">
			<div class="kp-stat"><b><?php echo count( $teams ); ?></b><span>ekipa u dvije lige</span></div>
			<div class="kp-stat kp-stat--ok"><b><?php echo (int) $n_upd; ?></b><span>ažurirano prema popisu</span></div>
			<div class="kp-stat kp-stat--wait"><b><?php echo count( $teams ) - $n_upd; ?></b><span>čeka novi popis</span></div>
			<div class="kp-stat kp-stat--q"><b><span id="kp-done"><?php echo (int) $n_done; ?></span> / <?php echo count( $qs ); ?></b><span>pitanja odgovoreno</span></div>
		</div>
	</section>

	<!-- ── Otvorena pitanja ─────────────────────────────────────────────── -->
	<section class="kp-section" id="pitanja">
		<div class="kp-section__hd">
			<h2 class="kp-h2">Otvorena pitanja</h2>
			<label class="kp-who">Tko odgovara? <input type="text" id="kp-who" maxlength="80" placeholder="Ime i prezime" autocomplete="name"></label>
		</div>
		<p class="kp-muted">Kliknite odgovor pa „Pošalji”. Odgovor možete i promijeniti — vrijedi zadnji poslani.</p>

		<div class="kp-qs">
		<?php foreach ( $qs as $i => $q ) : ?>
			<article class="kp-q" data-q="<?php echo esc_attr( $q['id'] ); ?>" id="q-<?php echo esc_attr( $q['id'] ); ?>">
				<div class="kp-q__top">
					<span class="kp-q__n"><?php echo $i + 1; ?></span>
					<span class="kp-tag"><?php echo esc_html( $q['team_name'] ); ?></span>
					<span class="kp-q__state">Čeka odgovor</span>
				</div>
				<h3 class="kp-q__title"><?php echo esc_html( $q['q'] ); ?></h3>
				<?php if ( ! empty( $q['why'] ) ) : ?><p class="kp-q__why"><?php echo esc_html( $q['why'] ); ?></p><?php endif; ?>
				<?php if ( $q['opts'] ) : ?>
				<div class="kp-opts" role="radiogroup" aria-label="Odgovor">
					<?php foreach ( $q['opts'] as $o ) : ?>
					<button type="button" class="kp-opt" role="radio" aria-checked="false" data-v="<?php echo esc_attr( $o ); ?>"><?php echo esc_html( $o ); ?></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<input type="text" class="kp-text kp-text--line" maxlength="500" placeholder="<?php echo esc_attr( $q['text'] ?? 'Napomena (neobavezno)' ); ?>" aria-label="<?php echo esc_attr( $q['text'] ?? 'Napomena' ); ?>">
				<div class="kp-row">
					<button type="button" class="kp-btn kp-save">Pošalji</button>
					<span class="kp-status" role="status" aria-live="polite"></span>
				</div>
				<ol class="kp-history"></ol>
			</article>
		<?php endforeach; ?>
		</div>
	</section>

	<!-- ── Promjene u ligama ─────────────────────────────────────────────── -->
	<section class="kp-section">
		<h2 class="kp-h2">Promjene u ligama</h2>
		<ul class="kp-changes">
			<?php foreach ( $d['changes'] ?? [] as [ $h, $txt ] ) : ?>
			<li><b><?php echo esc_html( $h ); ?></b><span><?php echo esc_html( $txt ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	</section>

	<!-- ── Ekipe ─────────────────────────────────────────────────────────── -->
	<section class="kp-section" id="ekipe">
		<div class="kp-section__hd">
			<h2 class="kp-h2">Ekipe</h2>
			<div class="kp-filter" role="group" aria-label="Prikaži">
				<button type="button" class="kp-filter__btn is-on" data-f="all">Sve</button>
				<button type="button" class="kp-filter__btn" data-f="kc">KC Liga</button>
				<button type="button" class="kp-filter__btn" data-f="lbb">Liga bez briga</button>
				<button type="button" class="kp-filter__btn" data-f="carried">Čeka popis</button>
			</div>
		</div>

		<?php foreach ( [ 'kc' => 'KC Liga', 'lbb' => 'Liga bez briga' ] as $lg => $lg_name ) :
			$list = array_values( array_filter( $teams, fn( $t ) => $t['lg'] === $lg ) ); ?>
		<div class="kp-league kp-league--<?php echo $lg; ?>" data-lg="<?php echo $lg; ?>">
			<h3 class="kp-league__title"><?php echo esc_html( $lg_name ); ?> <small><?php echo count( $list ); ?> ekipa</small></h3>
			<div class="kp-teams">
			<?php foreach ( $list as $t ) :
				$upd = $t['status'] === 'updated';
				$tq  = $q_by_team[ (int) $t['id'] ] ?? []; ?>
				<article class="kp-team kp-team--<?php echo esc_attr( $t['status'] ); ?>" data-lg="<?php echo $lg; ?>" data-status="<?php echo esc_attr( $t['status'] ); ?>" id="ekipa-<?php echo (int) $t['id']; ?>">
					<header class="kp-team__hd">
						<h4 class="kp-team__name"><?php echo esc_html( $t['name'] ); ?></h4>
						<span class="kp-badge <?php echo $upd ? 'kp-badge--ok' : 'kp-badge--wait'; ?>">
							<?php echo $upd ? 'Ažurirano prema popisu · ' . esc_html( $t['updated'] ) : 'Čeka popis'; ?>
						</span>
					</header>
					<p class="kp-team__meta">
						<span><b><?php echo count( $t['roster'] ); ?></b> igrača</span>
						<?php if ( $t['prev'] ) : ?><span>umjesto: <b><?php echo esc_html( $t['prev'] ); ?></b></span><?php endif; ?>
						<?php if ( $t['rep'] ) : ?><span>voditelj: <b><?php echo esc_html( $t['rep'] ); ?></b></span><?php endif; ?>
						<?php if ( $t['coach'] && $t['coach'] !== $t['rep'] ) : ?><span>trener: <b><?php echo esc_html( $t['coach'] ); ?></b></span><?php endif; ?>
					</p>
					<?php if ( $t['note'] ) : ?><p class="kp-team__note"><?php echo esc_html( $t['note'] ); ?></p><?php endif; ?>
					<?php if ( ! $upd ) : ?><p class="kp-team__note kp-team__note--wait">Roster je prenesen iz prošle sezone. Pošaljite popis igrača za 2026/27 (polje na dnu kartice) — dovoljna su imena i prezimena.</p><?php endif; ?>

					<?php if ( $t['new'] ) : ?>
					<div class="kp-block"><h5>Novi u ekipi <small><?php echo count( $t['new'] ); ?></small></h5><?php $chips( $t['new'], 'kp-chip--new' ); ?></div>
					<?php endif; ?>
					<?php if ( $t['from'] ) : ?>
					<div class="kp-block"><h5>Došli iz druge ekipe</h5>
						<ul class="kp-chips"><?php foreach ( $t['from'] as [ $n, $from ] ) : ?><li class="kp-chip kp-chip--new"><?php echo esc_html( $n ); ?> <em>← <?php echo esc_html( $from ); ?></em></li><?php endforeach; ?></ul>
					</div>
					<?php endif; ?>
					<?php if ( $t['renamed'] ) : ?>
					<div class="kp-block"><h5>Dopunjeno ime <small><?php echo count( $t['renamed'] ); ?></small></h5>
						<ul class="kp-renames"><?php foreach ( $t['renamed'] as [ $old, $new ] ) : ?><li><s><?php echo esc_html( $old ); ?></s> → <b><?php echo esc_html( $new ); ?></b></li><?php endforeach; ?></ul>
					</div>
					<?php endif; ?>
					<?php if ( ! empty( $t['moved'] ) ) : ?>
					<div class="kp-block"><h5>Prešli u drugu ekipu</h5>
						<ul class="kp-chips"><?php foreach ( $t['moved'] as [ $n, $to ] ) : ?><li class="kp-chip"><?php echo esc_html( $n ); ?> <em>→ <?php echo esc_html( $to ); ?></em></li><?php endforeach; ?></ul>
					</div>
					<?php endif; ?>
					<?php if ( $t['gone'] ) : ?>
					<div class="kp-block"><h5>Nisu na novom popisu <small><?php echo count( $t['gone'] ); ?></small></h5><?php $chips( $t['gone'], 'kp-chip--gone' ); ?></div>
					<?php endif; ?>

					<details class="kp-roster"<?php echo $upd ? '' : ' open'; ?>>
						<summary>Cijeli roster (<?php echo count( $t['roster'] ); ?>)</summary>
						<?php $chips( $t['roster'] ); ?>
					</details>

					<?php if ( $tq ) : ?>
					<p class="kp-team__qs">Otvorena pitanja za ovu ekipu:
						<?php foreach ( $tq as $qid ) : $qi = array_search( $qid, array_column( $qs, 'id' ), true ); ?>
						<a href="#q-<?php echo esc_attr( $qid ); ?>" data-qlink="<?php echo esc_attr( $qid ); ?>"><?php echo (int) $qi + 1; ?></a>
						<?php endforeach; ?>
					</p>
					<?php endif; ?>

					<details class="kp-fix"<?php echo empty( $answers[ 'team-' . (int) $t['id'] ] ) ? '' : ' open'; ?>>
						<summary><?php echo $upd ? 'Ispravak za ovu ekipu' : 'Pošalji popis ili ispravak'; ?></summary>
						<?php $note_box( 'team-' . (int) $t['id'], $upd ? 'Što treba ispraviti?' : 'Popis igrača 2026/27 ili napomena', $upd ? 'npr. krivo prezime, igrač koji nedostaje, broj dresa…' : "Ime Prezime\nIme Prezime\n…" ); ?>
					</details>
				</article>
			<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</section>

	<!-- ── Napravljeno ───────────────────────────────────────────────────── -->
	<section class="kp-section" id="napravljeno">
		<h2 class="kp-h2">Što smo još napravili</h2>
		<div class="kp-done">
			<?php foreach ( $d['done'] ?? [] as [ $h, $txt ] ) : ?>
			<div class="kp-done__item"><b><?php echo esc_html( $h ); ?></b><p><?php echo esc_html( $txt ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="kp-section">
		<h2 class="kp-h2">Nešto drugo?</h2>
		<?php $note_box( 'opce', 'Napomena, pitanje ili ispravak koji se ne odnosi na jednu ekipu', 'Napišite što treba…' ); ?>
	</section>

	<input type="text" id="kp-web" name="web" tabindex="-1" autocomplete="off" aria-hidden="true" class="kp-hp">
</main>

<footer class="kp-foot">KURS — Koprivnička udruga za rekreativni sport · kurskc.hr · stranica nije javna, otvara se samo preko ovog linka</footer>

<script>
window.KP = <?php echo wp_json_encode( [
	'ajax'    => admin_url( 'admin-ajax.php' ),
	'k'       => Kurs_Presjek::key(), // stranica se otvara samo s ispravnim ključem (ili adminu)
	'answers' => (object) $initial,
	'qids'    => array_column( $qs, 'id' ),
] ); ?>;
</script>
<script src="<?php echo esc_url( KURS_URL . 'front/presjek.js?ver=' . KURS_VERSION ); ?>"></script>
</body>
</html>
