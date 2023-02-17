<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

global $design;
if ( empty( $design ) ) {
	$design = tve_ult_get_design( $_REQUEST[ TVE_Ult_Const::DESIGN_QUERY_KEY_NAME ] );
}
$design_type_details = TVE_Ult_Const::design_details( $design['post_type'] );
?>
<a href="javascript:void(0)" class="click s-setting" data-fn="tve_ult_save_template">
	<span class="s-name"><?php echo __( 'Save Template', TVE_Ult_Const::T ); ?></span>
</a>

<?php if ( ! tve_ult_allow_cloud_templates() ): ?>
	<a href="javascript:void(0)" class="click s-setting" data-fn="tve_ult_reset_template">
		<span class="s-name"><?php echo __( 'Reset Template', TVE_Ult_Const::T ); ?></span>
	</a>
<?php endif; ?>

<a href="javascript:void(0)" class="click s-setting" data-fn="select_element" data-el="#tve_editor > .thrv_wrapper" style="order: -1">
	<span class="s-name"><?php echo sprintf( __( '%s Settings', TVE_Ult_Const::T ), $design_type_details['name'] ); ?></span><?php tcb_icon( 'cog-light' ); ?>
</a>

