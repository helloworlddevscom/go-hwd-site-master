<?php
$timezone_offset = get_option( 'gmt_offset' );
$sign            = ( $timezone_offset < 0 ? '-' : '+' );
$min             = abs( $timezone_offset ) * 60;
$hour            = floor( $min / 60 );
$tzd             = $sign . str_pad( $hour, 2, '0', STR_PAD_LEFT ) . ':' . str_pad( $min % 60, 2, '0', STR_PAD_LEFT );
?>
<div class="thrv_ult_widget thrv_wrapper tve_no_drag tve_no_icons tve_element_hover tve_set_02 tvu_set_02 tve_red">
	<div class="tve-ult-widget-content tve_editor_main_content">
		<h5 class="rft tvu-heading tve_p_center" style="color: #fff; font-size: 48px;margin-top: 0;margin-bottom: 10px;">
			Beauty voucher expires in:
		</h5>
		<div class="thrv_wrapper thrv_countdown_timer tve_cd_timer_plain tve_clearfix init_done tve_red tve_countdown_2"
		     data-date="<?php echo gmdate( 'Y-m-d', time() + 3600 * $timezone_offset + ( 24 * 3600 ) ) ?>"
		     data-hour="<?php echo gmdate( 'H', time() + 3600 * $timezone_offset ) ?>"
		     data-min="<?php echo gmdate( 'i', time() + 3600 * $timezone_offset ) ?>"
		     data-timezone="<?php echo $tzd ?>">
			<div class="sc_timer_content tve_clearfix tve_block_center">
				<div class="tve_t_day tve_t_part">
					<div class="t-digits"></div>
					<div class="t-caption thrv-inline-text">days</div>
				</div>
				<div class="tve_t_hour tve_t_part">
					<div class="t-digits"></div>
					<div class="t-caption thrv-inline-text">hours</div>
				</div>
				<div class="tve_t_min tve_t_part">
					<div class="t-digits"></div>
					<div class="t-caption thrv-inline-text">minutes</div>
				</div>
				<div class="tve_t_sec tve_t_part">
					<div class="t-digits"></div>
					<div class="t-caption thrv-inline-text">seconds</div>
				</div>
				<div class="tve_t_text"></div>
			</div>
		</div>
		<div class="thrv_wrapper thrv-button" style="margin-top: 20px;">
			<a href="#" class="tcb-button-link">
				<span class="tcb-button-texts"><span class="tcb-button-text thrv-inline-text">Get it Now!</span></span>
			</a>
		</div>
	</div>
</div>