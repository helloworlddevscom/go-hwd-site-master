<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}
?>

<div class="tva-resources-panel">
	<select class="tva-resource-courses change" data-fn="selectCourse">
		<option class="tva-option-placeholder" value="" disabled selected><?php echo __( 'Please select a course', TVA_Const::T ); ?></option>
	</select>
	<select class="tva-resource-lessons change" data-fn="selectLesson">
		<option class="tva-option-placeholder" value="" disabled selected><?php echo __( 'Please select a lesson', TVA_Const::T ); ?></option>
	</select>
</div>
