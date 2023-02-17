<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}
$allow_skin_reset = \TVA\TTB\Check::is_end_user_site();
?>

<style>
    .ttb-container {
        margin: 24px auto;
        width: 680px;
        box-sizing: border-box;
        padding: 25px 90px 35px;
        background: white;
        border: 1px solid #e5e5e5;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        position: relative;
    }

    .ttb-container h1 {
        margin: 0 0 30px;
    }

    .wp-core-ui .ttb-container .button {
        color: #fff;
        background-color: orange;
        border: none;
    }

    .wp-core-ui .ttb-container .button.delete-skin {
        background-color: red;
    }

    .ttb-center {
        text-align: center;
    }

    .ttb-mb30 {
        margin-bottom: 30px;
    }
</style>

<div class="ttb-container theme-overlay">
	<h1 class="ttb-center"><?php echo __( 'Reset user learned lessons', TVA_Const::T ); ?></h1>
	<p><?php echo __( 'Use the button below to reset the logged in user learned lessons.', TVA_Const::T ); ?></p>
	<p class="ttb-mb30"><strong><?php echo __( "Warning: Resetting the learned lessons will remove all progress the currently logged user has made for content controlled by Thrive Apprentice and cannot be undone!", TVA_Const::T ); ?></strong></p>

	<p class="ttb-center ttb-mb30"><strong><?php echo __( 'Are you sure you want to reset the progress?', TVA_Const::T ); ?></strong></p>

	<div class="ttb-center">
		<button data-action="tva_progress_reset" class="button ttb-action-button delete-theme">
			<?php echo __( 'Remove logged in user data from Apprentice', TVA_Const::T ); ?>
		</button>
	</div>
</div>
<div class="ttb-container theme-overlay">
	<h1 class="ttb-center"><?php echo __( 'Toggle demo content', TVA_Const::T ); ?></h1>
	<p><?php echo __( 'Use the options bellow to toggle demo content data.', TVA_Const::T ); ?></p>
	<p class="ttb-mb30"><strong><?php echo __( "Warning: By removing demo content from this website the wizard might not work as expected.", TVA_Const::T ); ?></strong></p>
	<div class="ttb-center">
		<button data-action="tva_remove_demo_content" class="button ttb-action-button delete-theme">
			<?php echo __( 'Remove demo content', TVA_Const::T ); ?>
		</button>
		<button data-action="tva_create_demo_content" class="button ttb-action-button delete-theme">
			<?php echo __( 'Re-create demo content', TVA_Const::T ); ?>
		</button>
	</div>
</div>
<?php if ( $allow_skin_reset ): ?>
	<div class="ttb-container theme-overlay">
		<h1 class="ttb-center"><?php echo __( 'Reset apprentice skins', TVA_Const::T ); ?></h1>
		<p><?php echo __( 'Use the button below to remove all apprentice skins.', TVA_Const::T ); ?></p>
		<p class="ttb-mb30"><strong><?php echo __( "Warning: This option will remove all cloud apprentice skins and will activate the Legacy Skin. It cannot be undone!", TVA_Const::T ); ?></strong></p>

		<p class="ttb-center ttb-mb30"><strong><?php echo __( 'Are you sure you want to reset the skin data?', TVA_Const::T ); ?></strong></p>

		<div class="ttb-center">
			<button data-action="tva_skin_reset" class="button ttb-action-button delete-theme delete-skin">
				<?php echo __( 'Remove all skin data from Thrive Apprentice', TVA_Const::T ); ?>
			</button>
		</div>
	</div>
<?php endif; ?>
<div class="ttb-container theme-overlay">
	<h1 class="ttb-center"><?php echo __( 'Reset products created from migration', TVA_Const::T ); ?></h1>
	<p><?php echo __( 'Use the button below to reset all products created from migration.', TVA_Const::T ); ?></p>
	<p class="ttb-mb30"><strong><?php echo __( "Warning: This option will remove all products created from migration and try to re-create new ones from the existing protected courses", TVA_Const::T ); ?></strong></p>

	<p class="ttb-center ttb-mb30"><strong><?php echo __( 'Are you sure you want to reset the products?', TVA_Const::T ); ?></strong></p>

	<div class="ttb-center">
		<button data-action="tva_products_reset" class="button ttb-action-button delete-theme delete-theme">
			<?php echo __( 'Remove all migrated products from Thrive Apprentice', TVA_Const::T ); ?>
		</button>
	</div>
</div>
<script type="text/javascript">
	( function ( $ ) {
		$( '.ttb-action-button' ).click( function () {
			$( this ).css( 'opacity', 0.3 );

			$.ajax( {
					url: ajaxurl,
					type: 'post',
					data: {
						action: this.dataset.action
					}
				}
			).success( () => $( this ).css( { 'opacity': 1, 'background-color': 'green' } ).text( 'Done - do it again?' )
			).always( response => console.warn( response ) )
		} );
	} )( jQuery )
</script>
