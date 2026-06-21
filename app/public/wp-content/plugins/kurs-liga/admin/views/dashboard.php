<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap kurs-wrap">
	<h1 class="kurs-page-title">
		<span class="dashicons dashicons-awards"></span> KURS Liga — Dashboard
	</h1>

	<?php $active = Kurs_Season::active(); ?>

	<?php if ( empty( $active ) ) : ?>
	<div class="kurs-empty-state">
		<span class="dashicons dashicons-plus-alt" style="font-size:3rem;height:auto;width:auto;color:#ccc;"></span>
		<p>Nema aktivnih sezona. Kreiraj prvu sezonu da počneš.</p>
		<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>" class="button button-primary button-large">
			+ Kreiraj sezonu
		</a>
	</div>
	<?php else : ?>

	<div class="kurs-cards">
		<?php foreach ( $active as $s ) :
			$divs       = Kurs_Season::get_divisions( (int) $s->id );
			$team_count = 0;
			foreach ( $divs as $d ) {
				foreach ( Kurs_Season::get_phases( (int) $d->id ) as $ph ) {
					foreach ( Kurs_Season::get_groups( (int) $ph->id ) as $g ) {
						$team_count += count( Kurs_Season::get_group_teams( (int) $g->id ) );
					}
				}
			}
		?>
		<div class="kurs-card">
			<div class="kurs-card__head">
				<div>
					<h2><?php echo esc_html( $s->name ); ?></h2>
					<span class="kurs-tag kurs-tag--league"><?php echo esc_html( $s->league ); ?></span>
				</div>
				<span class="kurs-badge kurs-badge--active">Aktivna</span>
			</div>

			<div class="kurs-card__stats">
				<div class="kurs-stat">
					<span class="kurs-stat__val"><?php echo count( $divs ); ?></span>
					<span class="kurs-stat__lbl"><?php echo count( $divs ) === 1 ? 'Divizija' : 'Divizije'; ?></span>
				</div>
				<div class="kurs-stat">
					<span class="kurs-stat__val"><?php echo $team_count; ?></span>
					<span class="kurs-stat__lbl">Ekipa</span>
				</div>
			</div>

			<?php foreach ( $divs as $d ) :
				$phases = Kurs_Season::get_phases( (int) $d->id );
			?>
			<div class="kurs-division-row">
				<strong><?php echo esc_html( $d->name ); ?></strong>
				<?php foreach ( $phases as $ph ) : ?>
					<span class="kurs-phase-pill kurs-phase-pill--<?php echo esc_attr( $ph->type ); ?>">
						<?php echo esc_html( $ph->name ); ?>
					</span>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>

			<div class="kurs-card__foot">
				<a href="<?php echo admin_url( 'admin.php?page=kurs-season-setup&season_id=' . $s->id . '&step=2' ); ?>"
				   class="button button-primary">Postavi ligu</a>
				<a href="<?php echo admin_url( 'admin.php?page=kurs-matches&season_id=' . $s->id ); ?>"
				   class="button">Utakmice &amp; tablice</a>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<?php endif; ?>

	<div class="kurs-quick">
		<h3>Brze radnje</h3>
		<div class="kurs-quick__list">
			<a href="<?php echo admin_url( 'admin.php?page=kurs-seasons' ); ?>"       class="kurs-quick__btn">+ Nova sezona</a>
			<a href="<?php echo admin_url( 'post-new.php?post_type=kurs_ekipa' ); ?>" class="kurs-quick__btn">+ Nova ekipa</a>
			<a href="<?php echo admin_url( 'post-new.php?post_type=kurs_igrac' ); ?>" class="kurs-quick__btn">+ Novi igrač</a>
			<a href="<?php echo admin_url( 'edit.php?post_type=kurs_ekipa' ); ?>"     class="kurs-quick__btn secondary">Sve ekipe</a>
			<a href="<?php echo admin_url( 'edit.php?post_type=kurs_igrac' ); ?>"     class="kurs-quick__btn secondary">Svi igrači</a>
		</div>
	</div>
</div>
