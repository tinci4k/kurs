<?php
/**
 * KURS Liga → Zahtjevi ekipa (1.31.0) — odobravanje promjena s portala ekipa + tajni linkovi ekipa.
 */
defined( 'ABSPATH' ) || exit;

$tab    = sanitize_key( $_GET['tab'] ?? 'cekanje' );
$base   = admin_url( 'admin.php?page=kurs-requests' );
$notice = get_transient( 'kurs_req_notice_' . get_current_user_id() );
if ( $notice ) delete_transient( 'kurs_req_notice_' . get_current_user_id() );
$pending = Kurs_Portal::requests( 0, 'pending', 500 );
$by_team = [];
foreach ( $pending as $r ) $by_team[ (int) $r->team_id ][] = $r;
$icons = [ 'player_add' => '➕', 'player_edit' => '✏️', 'player_remove' => '➖', 'logo' => '🖼️', 'colors' => '🎨', 'sponsor_add' => '🤝', 'sponsor_remove' => '✖', 'info' => '📇', 'note' => '💬' ];

/** Dodatna napomena za odobravanje (postojeći igrač, prijelaz, trenutna vrijednost). */
$hint = function ( object $r ): string {
	$d = $r->data;
	if ( $r->type === 'player_add' ) {
		$pid = Kurs_Portal::find_player( (string) ( $d['name'] ?? '' ) );
		if ( ! $pid ) return 'Novi igrač u registru.';
		$other = Kurs_Season::player_team_in_division( $pid, (int) $r->division_id );
		if ( $other === (int) $r->team_id ) return 'Već je na popisu ove ekipe — odobrenje samo postavi broj.';
		if ( $other ) return '⚠ Igrač istog imena igra za „' . get_the_title( $other ) . '” u istoj ligi — odobrenjem prelazi u ovu ekipu.';
		return 'Postoji u registru igrača (#' . $pid . ') — dodat će se postojeći, s njegovom poviješću.';
	}
	if ( $r->type === 'info' ) {
		$map = [ 'rep' => '_kurs_team_rep', 'rep_phone' => '_kurs_team_rep_phone', 'coach' => '_kurs_team_coach', 'coach_phone' => '_kurs_team_coach_phone' ];
		$was = [];
		foreach ( $map as $f => $m ) if ( array_key_exists( $f, $d ) ) $was[] = ( get_post_meta( (int) $r->team_id, $m, true ) ?: '(prazno)' );
		return 'Sada: ' . implode( ' · ', $was );
	}
	return '';
};
?>
<div class="wrap kurs-wrap kreq">
	<style>
		.kreq-tabs { display:flex; gap:6px; margin:0 0 18px; flex-wrap:wrap; }
		.kreq-tabs a { padding:8px 14px; border-radius:6px; background:#fff; border:1.5px solid var(--ka-border); text-decoration:none; color:var(--ka-dark); font-family:var(--font-cond); font-weight:800; font-style:italic; text-transform:uppercase; font-size:14px; }
		.kreq-tabs a.is-on { background:var(--ka-dark); border-color:var(--ka-dark); color:#fff; }
		.kreq-tabs b { display:inline-block; min-width:20px; padding:0 6px; margin-left:4px; border-radius:10px; background:var(--ka-red); color:#fff; font-style:normal; text-align:center; }
		.kreq-team { border-left:5px solid var(--tc, var(--ka-dark)); }
		.kreq-team .kurs-card__head { align-items:center; gap:12px; }
		.kreq-team__name { display:flex; align-items:center; gap:10px; }
		.kreq-team__name small { font-family:var(--font-body); font-style:normal; text-transform:none; font-weight:500; color:var(--ka-muted); font-size:13px; }
		.kreq-row { display:grid; grid-template-columns:34px minmax(0, 1fr) auto; gap:12px; align-items:center; padding:10px 0; border-bottom:1px solid #efefe8; }
		.kreq-row:last-child { border-bottom:0; }
		.kreq-ico { font-size:20px; text-align:center; }
		.kreq-body b { font-family:var(--font-cond); font-weight:800; text-transform:uppercase; font-size:13px; letter-spacing:.02em; color:var(--ka-muted); display:block; }
		.kreq-desc { font-size:15px; font-weight:600; color:var(--ka-dark); }
		.kreq-desc img { vertical-align:middle; max-height:54px; max-width:140px; object-fit:contain; background:#f4f4ee; border-radius:4px; margin-right:8px; }
		.kreq-hint { font-size:12.5px; color:#7a5700; margin-top:2px; }
		.kreq-meta { font-size:12px; color:var(--ka-muted); margin-top:2px; }
		.kreq-act { display:flex; gap:6px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
		.kreq-act input[type=text] { width:200px; height:32px; border:1.5px solid var(--ka-border); border-radius:5px; padding:0 8px; }
		.kreq-ok { background:var(--ka-green) !important; color:#fff !important; }
		.kreq-st { font-size:12px; font-weight:700; padding:2px 8px; border-radius:10px; background:#ececE4; }
		.kreq-st--approved { background:#e6f3ea; color:var(--ka-green); }
		.kreq-st--rejected { background:#fde6e7; color:var(--ka-red); }
		.kreq-links td, .kreq-links th { padding:9px 8px; vertical-align:middle; }
		.kreq-links input { width:100%; font-family:monospace; font-size:12px; }
		.kreq-links tr.is-hl { background:#fffbe0; }
		@media (max-width:900px) { .kreq-row { grid-template-columns:28px minmax(0, 1fr); } .kreq-act { grid-column:1 / -1; justify-content:flex-start; } }
	</style>

	<div class="kurs-page-hd"><div><div class="kurs-breadcrumb">KURS Liga</div><h1>Zahtjevi ekipa</h1></div></div>
	<p style="max-width:860px;margin:-10px 0 16px;color:#55585f;">Ekipe na svom tajnom linku (portal ekipe) šalju ispravke popisa igrača, logo, sponzore i kontakt. <strong>Ništa se ne mijenja na webu dok ne odobriš.</strong> Odbijeni zahtjev ekipa vidi s tvojom napomenom.</p>

	<?php if ( $notice ) : ?>
	<div class="notice <?php echo $notice['errors'] ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p>
		<?php echo $notice['ok'] ? esc_html( ( $notice['action'] === 'approve' ? 'Odobreno: ' : 'Odbijeno: ' ) . $notice['ok'] ) : ''; ?>
		<?php foreach ( $notice['errors'] as $e ) echo '<br>Nije primijenjeno — ' . esc_html( $e ); ?>
	</p></div>
	<?php endif; ?>
	<?php if ( ( $_GET['msg'] ?? '' ) === 'newlink' ) : ?>
	<div class="notice notice-success is-dismissible"><p>Novi link je napravljen — stari više ne radi. Pošalji ekipi novi link.</p></div>
	<?php endif; ?>

	<nav class="kreq-tabs">
		<a href="<?php echo esc_url( $base ); ?>" class="<?php echo $tab === 'cekanje' ? 'is-on' : ''; ?>">Na čekanju<?php echo $pending ? '<b>' . count( $pending ) . '</b>' : ''; ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'rijeseno', $base ) ); ?>" class="<?php echo $tab === 'rijeseno' ? 'is-on' : ''; ?>">Riješeno</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'linkovi', $base ) ); ?>" class="<?php echo $tab === 'linkovi' ? 'is-on' : ''; ?>">Linkovi za ekipe</a>
	</nav>

	<?php if ( $tab === 'linkovi' ) : $hl = absint( $_GET['tm'] ?? 0 ); ?>
	<div class="kurs-card">
		<div class="kurs-card__head"><h2>Tajni linkovi ekipa</h2></div>
		<p style="margin-top:0;color:#55585f;">Svakoj ekipi pošalji njezin link (npr. voditelju na WhatsApp). Tko ima link, može slati zahtjeve za tu ekipu — ako link dođe u krive ruke, klikni „Novi link”.</p>
		<table class="widefat striped kreq-links">
			<thead><tr><th>Ekipa</th><th>Liga</th><th style="width:46%">Link</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( Kurs_Portal::current_teams() as $t ) : $u = Kurs_Portal::url( $t['id'] ); ?>
				<tr id="tm-<?php echo (int) $t['id']; ?>" class="<?php echo $hl === $t['id'] ? 'is-hl' : ''; ?>">
					<td><strong><?php echo esc_html( $t['name'] ); ?></strong></td>
					<td><?php echo esc_html( $t['league'] ); ?><?php echo $t['locked'] ? ' <em>(zaključana)</em>' : ''; ?></td>
					<td><input type="text" readonly value="<?php echo esc_attr( $u ); ?>" onclick="this.select()"></td>
					<td style="white-space:nowrap;">
						<button type="button" class="kurs-btn kurs-btn--dark kurs-btn--sm" data-copy="<?php echo esc_attr( $u ); ?>">Kopiraj</button>
						<a class="kurs-btn kurs-btn--outline kurs-btn--sm" href="<?php echo esc_url( $u ); ?>" target="_blank" rel="noopener">Otvori</a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Napraviti novi link za <?php echo esc_js( $t['name'] ); ?>? Stari link odmah prestaje raditi.');">
							<?php wp_nonce_field( 'kurs_portal_newlink' ); ?>
							<input type="hidden" name="action" value="kurs_portal_newlink"><input type="hidden" name="team_id" value="<?php echo (int) $t['id']; ?>">
							<button class="kurs-btn kurs-btn--ghost kurs-btn--sm">Novi link</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<script>
	document.querySelectorAll('[data-copy]').forEach(function (b) {
		b.addEventListener('click', function () {
			var t = b.getAttribute('data-copy');
			(navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).catch(function () { var i = b.closest('tr').querySelector('input'); i.select(); document.execCommand('copy'); });
			b.textContent = 'Kopirano ✓'; setTimeout(function () { b.textContent = 'Kopiraj'; }, 1800);
		});
	});
	</script>

	<?php elseif ( $tab === 'rijeseno' ) : $done = Kurs_Portal::requests( 0, 'done', 150 ); ?>
	<div class="kurs-card">
		<?php if ( ! $done ) : ?><p>Još nema riješenih zahtjeva.</p><?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>Ekipa</th><th>Zahtjev</th><th>Status</th><th>Napomena</th><th>Poslao</th><th>Riješeno</th></tr></thead>
			<tbody>
			<?php foreach ( $done as $r ) : ?>
				<tr>
					<td><?php echo esc_html( get_the_title( (int) $r->team_id ) ); ?></td>
					<td><strong><?php echo esc_html( Kurs_Portal::TYPES[ $r->type ] ?? $r->type ); ?></strong> — <?php echo esc_html( Kurs_Portal::describe( $r ) ); ?></td>
					<td><span class="kreq-st kreq-st--<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( Kurs_Portal::STATUS[ $r->status ] ?? $r->status ); ?></span></td>
					<td><?php echo esc_html( $r->admin_note ); ?></td>
					<td><?php echo esc_html( $r->who ); ?><br><small><?php echo esc_html( Kurs_Portal::when( (string) $r->created ) ); ?></small></td>
					<td><?php echo esc_html( Kurs_Portal::when( (string) $r->decided ) ); ?><?php echo $r->decided_by ? '<br><small>' . esc_html( get_the_author_meta( 'display_name', (int) $r->decided_by ) ) . '</small>' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<?php else : ?>
		<?php if ( ! $by_team ) : ?>
		<div class="kurs-card"><p style="margin:0;">Nema zahtjeva na čekanju. 👍 Linkove za ekipe nađeš pod <a href="<?php echo esc_url( add_query_arg( 'tab', 'linkovi', $base ) ); ?>">Linkovi za ekipe</a>.</p></div>
		<?php endif; ?>
		<?php foreach ( $by_team as $tid => $list ) :
			$ctx = Kurs_Portal::context( $tid ); ?>
		<div class="kurs-card kreq-team" style="--tc:<?php echo esc_attr( kurs_team_color( $tid ) ); ?>">
			<div class="kurs-card__head">
				<h2 class="kreq-team__name"><?php echo kurs_team_badge_html( $tid, 30 ); ?><?php echo esc_html( get_the_title( $tid ) ); ?> <small><?php echo $ctx ? esc_html( $ctx->division_name . ' · ' . $ctx->season_name ) : ''; ?> · <?php echo count( $list ); ?> na čekanju</small></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Odobriti sve zahtjeve ekipe <?php echo esc_js( get_the_title( $tid ) ); ?>?');">
					<?php wp_nonce_field( 'kurs_request_decide' ); ?>
					<input type="hidden" name="action" value="kurs_request_decide"><input type="hidden" name="decision" value="approve">
					<?php foreach ( $list as $r ) : ?><input type="hidden" name="ids[]" value="<?php echo (int) $r->id; ?>"><?php endforeach; ?>
					<button class="kurs-btn kurs-btn--sm kreq-ok">✓ Odobri sve (<?php echo count( $list ); ?>)</button>
				</form>
			</div>
			<?php foreach ( $list as $r ) :
				$img = ! empty( $r->data['att'] ) && in_array( $r->type, [ 'logo', 'sponsor_add' ], true ) ? wp_get_attachment_image_url( (int) $r->data['att'], 'medium' ) : '';
				$h   = $hint( $r ); ?>
			<form class="kreq-row" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'kurs_request_decide' ); ?>
				<input type="hidden" name="action" value="kurs_request_decide"><input type="hidden" name="ids[]" value="<?php echo (int) $r->id; ?>">
				<span class="kreq-ico" aria-hidden="true"><?php echo $icons[ $r->type ] ?? '•'; ?></span>
				<div class="kreq-body">
					<b><?php echo esc_html( Kurs_Portal::TYPES[ $r->type ] ?? $r->type ); ?></b>
					<div class="kreq-desc"><?php if ( $img ) : ?><a href="<?php echo esc_url( (string) wp_get_attachment_url( (int) $r->data['att'] ) ); ?>" target="_blank"><img src="<?php echo esc_url( $img ); ?>" alt=""></a><?php endif; ?><?php if ( $r->type === 'colors' ) : ?><span style="display:inline-flex;align-items:center;gap:10px;"><?php echo kurs_kit_svg( (string) $r->data['color'], (string) $r->data['color2'], 40 ); ?> <span style="color:#8a8a84;">→ sada:</span> <?php echo kurs_kit_svg( kurs_team_color( (int) $r->team_id ), kurs_team_color2( (int) $r->team_id ), 30 ); ?></span> <?php endif; ?><?php echo nl2br( esc_html( Kurs_Portal::describe( $r ) ) ); ?></div>
					<?php if ( $h ) : ?><div class="kreq-hint"><?php echo esc_html( $h ); ?></div><?php endif; ?>
					<div class="kreq-meta"><?php echo esc_html( ( $r->who ?: 'bez imena' ) . ' · ' . Kurs_Portal::when( (string) $r->created ) ); ?></div>
				</div>
				<div class="kreq-act">
					<input type="text" name="admin_note" maxlength="255" placeholder="Napomena ekipi (neobavezno)">
					<button class="kurs-btn kurs-btn--sm kreq-ok" name="decision" value="approve"><?php echo $r->type === 'note' ? '✓ Pročitano' : '✓ Odobri'; ?></button>
					<?php if ( $r->type !== 'note' ) : ?><button class="kurs-btn kurs-btn--ghost kurs-btn--sm" name="decision" value="reject">Odbij</button><?php endif; ?>
				</div>
			</form>
			<?php endforeach; ?>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
