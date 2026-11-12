<?php
/**
 * KURS Liga → Sponzori (1.31.0) — sponzori lige (CPT `sponzor`) u KURS sučelju.
 */
defined( 'ABSPATH' ) || exit;

$all     = Kurs_Sponsors::all();
$edit_id = absint( $_GET['edit'] ?? 0 );
$is_new  = ! empty( $_GET['new'] );
$hl      = absint( $_GET['hl'] ?? 0 );
$msg     = sanitize_key( $_GET['msg'] ?? '' );
$err     = sanitize_key( $_GET['err'] ?? '' );
$edit    = $edit_id && get_post_type( $edit_id ) === 'sponzor' ? get_post( $edit_id ) : null;
$form    = $edit || $is_new;

$presjek  = class_exists( 'Kurs_Presjek' ) ? Kurs_Presjek::sponsors( Kurs_Presjek::LEAGUE ) : [];
$imported = Kurs_Sponsors::imported_refs();
$waiting  = array_values( array_filter( $presjek, fn( $s ) => ! in_array( (int) $s['i'], $imported, true ) ) );

$post_form = function ( string $act, int $id, string $label, string $cls = 'kurs-btn--ghost', array $extra = [], string $confirm = '' ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"<?php echo $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : ''; ?>>
		<?php wp_nonce_field( 'kurs_sponsor_' . $act ); ?>
		<input type="hidden" name="action" value="kurs_sponsor_<?php echo esc_attr( $act ); ?>"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<?php foreach ( $extra as $k => $v ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>"><?php endforeach; ?>
		<button class="kurs-btn kurs-btn--sm <?php echo esc_attr( $cls ); ?>"><?php echo $label; ?></button>
	</form>
	<?php
};
?>
<div class="wrap kurs-wrap ksp">
	<style>
		.ksp-intro { max-width:880px; margin:-10px 0 18px; color:#55585f; }
		.ksp-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:14px; }
		.ksp-card { background:#fff; border:1px solid var(--ka-border); border-radius:10px; overflow:hidden; display:flex; flex-direction:column; transition:box-shadow .2s; }
		.ksp-card.is-hl { box-shadow:0 0 0 3px var(--ka-gold); }
		.ksp-card.is-off { opacity:.62; }
		.ksp-card__logo { height:120px; display:flex; align-items:center; justify-content:center; background:#f6f6f1; border-bottom:1px solid var(--ka-border); position:relative; }
		.ksp-card__logo img { max-width:78%; max-height:86px; object-fit:contain; }
		.ksp-card__logo .ksp-nologo { font-family:var(--font-mono); font-size:11px; color:var(--ka-muted); text-transform:uppercase; }
		.ksp-card__pos { position:absolute; top:8px; left:8px; font-family:var(--font-cond); font-weight:800; font-style:italic; font-size:15px; background:var(--ka-dark); color:#fff; border-radius:4px; padding:1px 7px; }
		.ksp-card__body { padding:12px 14px; flex:1; }
		.ksp-card__name { font-family:var(--font-cond); font-weight:800; font-style:italic; text-transform:uppercase; font-size:20px; line-height:1.1; margin:0 0 6px; }
		.ksp-chips { display:flex; gap:5px; flex-wrap:wrap; margin-bottom:6px; }
		.ksp-chip { font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:10px; background:#ececE4; color:#44474e; }
		.ksp-chip--t1 { background:var(--ka-gold); color:var(--ka-dark); }
		.ksp-chip--t2 { background:#dfe3e8; }
		.ksp-chip--off { background:#fde6e7; color:var(--ka-red); }
		.ksp-chip--kc { background:#fde6e7; color:var(--ka-red); } .ksp-chip--pan { background:#fff0e0; color:#a24f00; }
		.ksp-card__link { font-size:13px; color:var(--ka-blue); word-break:break-all; }
		.ksp-card__foot { display:flex; gap:5px; flex-wrap:wrap; padding:10px 14px; border-top:1px solid var(--ka-border); background:#fcfcfa; }
		.ksp-form { display:grid; grid-template-columns:200px minmax(0, 1fr); gap:18px; }
		.ksp-logo-pick { width:200px; height:140px; border:2px dashed #c9c9c0; border-radius:10px; display:flex; align-items:center; justify-content:center; background:#f6f6f1; cursor:pointer; overflow:hidden; text-align:center; font-size:13px; color:var(--ka-muted); padding:8px; }
		.ksp-logo-pick img { max-width:100%; max-height:120px; object-fit:contain; }
		.ksp-fields { display:grid; gap:14px; }
		.ksp-fields .kurs-field-input { height:38px; font-size:14px; width:100%; max-width:520px; }
		.ksp-opts { display:flex; gap:6px; flex-wrap:wrap; }
		.ksp-opt { position:relative; }
		.ksp-opt input { position:absolute; opacity:0; inset:0; cursor:pointer; }
		.ksp-opt span { display:inline-block; padding:7px 14px; border-radius:18px; border:1.5px solid var(--ka-border); background:#fff; font-weight:600; font-size:13.5px; }
		.ksp-opt input:checked + span { background:var(--ka-dark); border-color:var(--ka-dark); color:#fff; }
		.ksp-opt input:focus-visible + span { outline:2px solid var(--ka-red); outline-offset:2px; }
		.ksp-help { font-size:12.5px; color:var(--ka-muted); margin-top:3px; }
		.ksp-wait { border-left:5px solid var(--ka-gold); }
		.ksp-wait__row { display:flex; align-items:center; gap:12px; padding:9px 0; border-bottom:1px solid #efefe8; }
		.ksp-wait__row:last-child { border-bottom:0; }
		.ksp-wait__row img { width:84px; height:40px; object-fit:contain; background:#f6f6f1; border-radius:4px; }
		.ksp-wait__row .ksp-nologo { width:84px; text-align:center; font-size:11px; color:var(--ka-muted); }
		.ksp-wait__txt { flex:1; min-width:0; }
		@media (max-width:782px) { .ksp-form { grid-template-columns:1fr; } }
	</style>

	<div class="kurs-page-hd">
		<div><div class="kurs-breadcrumb">KURS Liga</div><h1>Sponzori lige</h1></div>
		<?php if ( ! $form ) : ?><a class="kurs-btn kurs-btn--red" href="<?php echo esc_url( Kurs_Sponsors::url( [ 'new' => 1 ] ) ); ?>">+ Novi sponzor</a><?php endif; ?>
	</div>
	<p class="ksp-intro">Sponzori se prikazuju u traci <strong>„Partneri turnira i liga”</strong> na dnu svake stranice weba, redom kao ovdje. Sponzor bez loga ili skriven ne prikazuje se. Sponzore ekipa (na profilu ekipe) ekipe šalju same — vidi <a href="<?php echo esc_url( admin_url( 'admin.php?page=kurs-requests' ) ); ?>">Zahtjevi ekipa</a>.</p>

	<?php if ( ! post_type_exists( 'sponzor' ) ) : ?>
	<div class="notice notice-error"><p>Tema kurskc nije aktivna — sponzori lige dolaze iz teme.</p></div>
	<?php endif; ?>
	<?php if ( $msg === 'saved' ) : ?><div class="notice notice-success is-dismissible"><p>Sponzor je spremljen.</p></div><?php endif; ?>
	<?php if ( $msg === 'imported' ) : ?><div class="notice notice-success is-dismissible"><p>Sponzor je dodan na web — provjeri razinu i redoslijed.</p></div><?php endif; ?>
	<?php if ( $msg === 'restored' ) : ?><div class="notice notice-success is-dismissible"><p>Sponzor je vraćen.</p></div><?php endif; ?>
	<?php if ( $msg === 'trashed' && ( $undo = absint( $_GET['undo'] ?? 0 ) ) ) : ?>
	<div class="notice notice-warning"><p style="display:flex;align-items:center;gap:10px;">Sponzor „<?php echo esc_html( get_the_title( $undo ) ); ?>” je uklonjen. <?php $post_form( 'untrash', $undo, 'Poništi', 'kurs-btn--dark' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $err === 'name' ) : ?><div class="notice notice-error"><p>Upiši naziv sponzora.</p></div><?php endif; ?>

	<?php if ( $form ) :
		$tier = $edit ? ( (int) get_post_meta( $edit->ID, '_kurs_sponsor_tier', true ) ?: 2 ) : 2;
		$comp = $edit ? ( (string) get_post_meta( $edit->ID, '_kurs_sponsor_comp', true ) ?: 'obje' ) : 'obje';
		$logo = $edit ? (int) get_post_thumbnail_id( $edit->ID ) : 0; ?>
	<div class="kurs-card">
		<div class="kurs-card__head"><h2><?php echo $edit ? 'Uredi sponzora' : 'Novi sponzor'; ?></h2></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ksp-form">
			<?php wp_nonce_field( 'kurs_sponsor_save' ); ?>
			<input type="hidden" name="action" value="kurs_sponsor_save"><input type="hidden" name="id" value="<?php echo $edit ? (int) $edit->ID : 0; ?>">
			<input type="hidden" name="logo" id="ksp-logo" value="<?php echo (int) $logo; ?>">
			<div>
				<div class="ksp-logo-pick" id="ksp-logo-pick" role="button" tabindex="0" aria-label="Odaberi logo">
					<?php echo $logo ? '<img src="' . esc_url( (string) wp_get_attachment_image_url( $logo, 'medium' ) ) . '" alt="">' : 'Klikni za odabir loga<br>(PNG, prozirna pozadina)'; ?>
				</div>
				<p class="ksp-help"><button type="button" class="button-link" id="ksp-logo-x"<?php echo $logo ? '' : ' hidden'; ?>>Ukloni logo</button></p>
			</div>
			<div class="ksp-fields">
				<label class="kurs-field-wrap"><span class="kurs-field-label">Naziv *</span><input class="kurs-field-input" type="text" name="name" maxlength="120" required value="<?php echo esc_attr( $edit ? $edit->post_title : '' ); ?>" placeholder="npr. Pan"></label>
				<div class="kurs-field-wrap"><span class="kurs-field-label">Razina</span>
					<div class="ksp-opts"><?php foreach ( Kurs_Sponsors::TIERS as $v => $l ) : ?><label class="ksp-opt"><input type="radio" name="tier" value="<?php echo $v; ?>"<?php checked( $tier, $v ); ?>><span><?php echo esc_html( $l ); ?></span></label><?php endforeach; ?></div>
					<div class="ksp-help" id="ksp-tier-help"><?php echo esc_html( Kurs_Sponsors::TIER_FRONT[ $tier ] ); ?></div>
				</div>
				<div class="kurs-field-wrap"><span class="kurs-field-label">Natjecanje</span>
					<div class="ksp-opts"><?php foreach ( Kurs_Sponsors::COMPS as $v => $l ) : ?><label class="ksp-opt"><input type="radio" name="comp" value="<?php echo esc_attr( $v ); ?>"<?php checked( $comp, $v ); ?>><span><?php echo esc_html( $l ); ?></span></label><?php endforeach; ?></div>
					<div class="ksp-help">Zasad se svi prikazuju u istoj traci; natjecanje će služiti za „sponzora kola” uz tablicu.</div>
				</div>
				<label class="kurs-field-wrap"><span class="kurs-field-label">Link (web ili Facebook)</span><input class="kurs-field-input" type="text" name="link" maxlength="300" inputmode="url" value="<?php echo esc_attr( $edit ? (string) get_post_meta( $edit->ID, '_kurs_sponsor_link', true ) : '' ); ?>" placeholder="https://…"></label>
				<label style="display:flex;gap:8px;align-items:center;font-weight:600;"><input type="checkbox" name="visible" value="1"<?php checked( ! $edit || $edit->post_status === 'publish' ); ?>> Prikaži na webu</label>
				<div style="display:flex;gap:8px;">
					<button class="kurs-btn kurs-btn--red">Spremi</button>
					<a class="kurs-btn kurs-btn--outline" href="<?php echo esc_url( Kurs_Sponsors::url() ); ?>">Odustani</a>
				</div>
			</div>
		</form>
	</div>
	<script>
	(function ($) {
		var help = <?php echo wp_json_encode( Kurs_Sponsors::TIER_FRONT ); ?>;
		document.querySelectorAll('input[name=tier]').forEach(function (r) { r.addEventListener('change', function () { document.getElementById('ksp-tier-help').textContent = help[r.value]; }); });
		var pick = document.getElementById('ksp-logo-pick'), inp = document.getElementById('ksp-logo'), x = document.getElementById('ksp-logo-x'), frame;
		function open() {
			if (!window.wp || !wp.media) return;
			frame = frame || wp.media({ title: 'Logo sponzora', button: { text: 'Odaberi' }, library: { type: 'image' }, multiple: false });
			frame.off('select').on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				inp.value = a.id;
				pick.innerHTML = '<img src="' + ((a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url) + '" alt="">';
				x.hidden = false;
			});
			frame.open();
		}
		pick.addEventListener('click', open);
		pick.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
		x.addEventListener('click', function () { inp.value = 0; pick.innerHTML = 'Klikni za odabir loga<br>(PNG, prozirna pozadina)'; x.hidden = true; });
	})(window.jQuery);
	</script>
	<?php endif; ?>

	<?php if ( $waiting ) : ?>
	<div class="kurs-card ksp-wait">
		<div class="kurs-card__head"><h2>Poslao KURS — još nisu na webu</h2></div>
		<?php foreach ( $waiting as $s ) : ?>
		<div class="ksp-wait__row">
			<?php echo $s['logo'] ? '<img src="' . esc_url( $s['logo'] ) . '" alt="">' : '<span class="ksp-nologo">bez loga</span>'; ?>
			<div class="ksp-wait__txt"><strong><?php echo esc_html( $s['name'] ); ?></strong> · <?php echo esc_html( $s['tierL'] . ( $s['compL'] ? ' · ' . $s['compL'] : '' ) ); ?><?php echo $s['link'] ? ' · <a href="' . esc_url( $s['link'] ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', $s['link'] ) ) . '</a>' : ''; ?><br><small style="color:#8a8a84;"><?php echo esc_html( trim( $s['who'] . ' · ' . $s['at'], ' ·' ) ); ?></small></div>
			<?php $post_form( 'import', 0, '+ Dodaj na web', 'kurs-btn--dark', [ 'ref' => (int) $s['i'] ] ); ?>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ( ! $all ) : ?>
	<div class="kurs-card"><p style="margin:0;">Još nema sponzora. Dok ih nema, na webu se prikazuju primjeri. Klikni „+ Novi sponzor”.</p></div>
	<?php else : ?>
	<div class="ksp-grid">
		<?php foreach ( $all as $i => $sp ) :
			$t    = (int) get_post_meta( $sp->ID, '_kurs_sponsor_tier', true ) ?: 2;
			$c    = (string) get_post_meta( $sp->ID, '_kurs_sponsor_comp', true ) ?: 'obje';
			$lnk  = (string) get_post_meta( $sp->ID, '_kurs_sponsor_link', true );
			$img  = get_the_post_thumbnail_url( $sp->ID, 'medium' );
			$on   = $sp->post_status === 'publish';
			$shown = $on && $img; ?>
		<div class="ksp-card<?php echo $hl === $sp->ID ? ' is-hl' : ''; ?><?php echo $shown ? '' : ' is-off'; ?>">
			<div class="ksp-card__logo">
				<span class="ksp-card__pos"><?php echo $i + 1; ?>.</span>
				<?php echo $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '<span class="ksp-nologo">bez loga</span>'; ?>
			</div>
			<div class="ksp-card__body">
				<h3 class="ksp-card__name"><?php echo esc_html( $sp->post_title ); ?></h3>
				<div class="ksp-chips">
					<span class="ksp-chip ksp-chip--t<?php echo $t; ?>"><?php echo esc_html( Kurs_Sponsors::TIERS[ $t ] ); ?></span>
					<span class="ksp-chip ksp-chip--<?php echo esc_attr( $c ); ?>"><?php echo esc_html( Kurs_Sponsors::COMPS[ $c ] ?? '' ); ?></span>
					<?php if ( ! $on ) : ?><span class="ksp-chip ksp-chip--off">Skriveno</span><?php elseif ( ! $img ) : ?><span class="ksp-chip ksp-chip--off">Bez loga — ne prikazuje se</span><?php endif; ?>
				</div>
				<?php if ( $lnk ) : ?><a class="ksp-card__link" href="<?php echo esc_url( $lnk ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', rtrim( $lnk, '/' ) ) ); ?></a><?php endif; ?>
			</div>
			<div class="ksp-card__foot">
				<a class="kurs-btn kurs-btn--dark kurs-btn--sm" href="<?php echo esc_url( Kurs_Sponsors::url( [ 'edit' => $sp->ID ] ) ); ?>">Uredi</a>
				<?php if ( $i > 0 ) $post_form( 'move', $sp->ID, '↑', 'kurs-btn--outline', [ 'dir' => 'up' ] ); ?>
				<?php if ( $i < count( $all ) - 1 ) $post_form( 'move', $sp->ID, '↓', 'kurs-btn--outline', [ 'dir' => 'down' ] ); ?>
				<?php $post_form( 'toggle', $sp->ID, $on ? 'Sakrij' : 'Prikaži', 'kurs-btn--ghost' ); ?>
				<?php $post_form( 'trash', $sp->ID, 'Ukloni', 'kurs-btn--ghost', [], 'Ukloniti sponzora „' . $sp->post_title . '” s weba?' ); ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>
