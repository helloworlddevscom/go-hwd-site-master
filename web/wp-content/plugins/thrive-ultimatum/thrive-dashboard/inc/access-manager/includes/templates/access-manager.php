<?php

/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-dashboard
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}
require_once( TVE_DASH_PATH . '/templates/header.phtml' ); ?>
<div class="tvd-am-breadcrumbs">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tve_dash_section' ) ); ?>">
		<?php echo esc_html__( 'Thrive Dashboard', TVE_DASH_TRANSLATE_DOMAIN ); ?>
	</a>

	<span class="tvd-breadcrumb"><?php echo esc_html__( 'User Access Manager', TVE_DASH_TRANSLATE_DOMAIN ); ?></span>
</div>
<div class="tvd-access-manager-setting"></div>