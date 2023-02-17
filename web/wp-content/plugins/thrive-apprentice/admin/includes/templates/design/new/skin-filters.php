<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

//TODO once we have the final design rewrite this part to be more modular
?>
<div class="ttd-dropdown-w-count">
	<div class="ttd-dwc-active click" data-fn="toggleDropdown">
		<span class="templates-title"><#= this.model.get( 'title' ) #><?php echo ' ' . __( 'Templates', TVA_Const::T ); ?></span>
		<span class="templates-counter"><#= this.model.get('counter') #></span>
		<?php Thrive_Views::svg_icon( 'angle-down_light' ); ?>
	</div>
	<div class="ttd-dwc-drop">
		<div class="ttd-dwc-elem type-button click <# if ( this.model.get( 'template' ) === 'core' ) { #>active<# } #>" data-fn="mainFilter" data-template="core">
			<h3><?php echo __( 'Core Templates', TVA_Const::T ) ?></h3>
			<p><?php echo __( "Only show the most basic templates that control your website's look.", TVA_Const::T ); ?></p>
		</div>
		<div class="ttd-dwc-elem type-button click <# if ( this.model.get( 'template' ) === 'homepage' ) { #>active<# } #>" data-fn="mainFilter" data-template="homepage">
			<h3><?php echo __( 'Homepage Templates', TVA_Const::T ) ?></h3>
			<p><?php echo __( 'Show templates specifically for building your homepage.', TVA_Const::T ); ?></p>
		</div>
		<div class="ttd-dwc-elem type-button click <# if ( this.model.get( 'template' ) === 'lesson' ) { #>active<# } #>" data-fn="mainFilter" data-template="lesson">
			<h3><?php echo __( 'Lesson Templates', TVA_Const::T ) ?></h3>
			<p><?php echo __( 'Show templates specifically for building your lessons.', TVA_Const::T ); ?></p>
		</div>
		<div class="ttd-dwc-elem type-button click <# if ( this.model.get( 'template' ) === 'module' ) { #>active<# } #>" data-fn="mainFilter" data-template="module">
			<h3><?php echo __( 'Module Templates', TVA_Const::T ) ?></h3>
			<p><?php echo __( 'Show templates specifically for building your module.', TVA_Const::T ); ?></p>
		</div>
		<div class="ttd-dwc-elem type-button click <# if ( this.model.get( 'template' ) === 'course' ) { #>active<# } #>" data-fn="mainFilter" data-template="course">
			<h3><?php echo __( 'Course Overview Templates', TVA_Const::T ) ?></h3>
			<p><?php echo __( 'Show templates specifically for building your course overviews.', TVA_Const::T ); ?></p>
		</div>
		<div class="ttd-dwc-elem type-button click <# if ( this.model.get( 'template' ) === 'all' ) { #>active<# } #>" data-fn="mainFilter" data-template="all">
			<h3><?php echo __( 'All Templates', TVA_Const::T ) ?></h3>
			<p><?php echo __( 'All available templates for the active theme.', TVA_Const::T ); ?></p>
		</div>
	</div>
</div>
