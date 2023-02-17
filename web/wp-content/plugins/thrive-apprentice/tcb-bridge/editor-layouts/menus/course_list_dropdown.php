<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}
?>
<div id="tve-course_list_dropdown-component" class="tve-component" data-view="CourseListDropdown">
	<div class="dropdown-header" data-prop="docked">
		<?php echo __( 'Main Options', TVA_Const::T ); ?>
		<i></i>
	</div>
	<div class="dropdown-content">
		<div class="tve-control tve-style-options palettes-v2 no-space preview" data-view="StyleChange"></div>
		<div class="tve-control" data-key="SelectStylePicker" data-initializer="stylePickerInitializer"></div>
		<div class="tve-control" data-view="PlaceholderInput"></div>
		<div class="tve-control" data-key="DropdownIcon" data-initializer="dropdownIconInitializer"></div>
		<div class="tve-control" data-view="DropdownAnimation"></div>
		<div class="tve-control" data-view="Width"></div>
		<div class="tve-control" data-view="RowsWhenOpen"></div>
		<div class="control-grid mt-10">
			<div class="label"><?php echo __( 'Filter options', TVA_Const::T ); ?></div>
		</div>
		<div class="tve-control tve-cbx-extra" data-view="FilterProgress"></div>
		<div class="tve-control tve-cbx-extra" data-view="FilterTopics"></div>
		<div class="tve-control tve-cbx-extra" data-view="FilterRestrictions"></div>
		<hr>
		<div class="control-grid mt-10">
			<div class="label">
				<?php echo __( 'Subheadings', TVA_Const::T ); ?>
				<span class="click" data-tooltip="<?php echo __( 'Leave empty to hide the subheading from the dropdown list', TVA_Const::T ); ?>" data-side="top"><?php tcb_icon( 'info-circle-solid' ); ?></span>
			</div>
		</div>
		<div class="tve-control tve-cbx-extra" data-view="FilterProgressSubheading"></div>
		<div class="tve-control tve-cbx-extra" data-view="FilterTopicsSubheading"></div>
		<div class="tve-control tve-cbx-extra" data-view="FilterRestrictionsSubheading"></div>
	</div>
</div>
