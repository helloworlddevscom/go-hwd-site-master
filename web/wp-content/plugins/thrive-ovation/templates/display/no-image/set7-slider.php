<div
		class="tvo-testimonials-display tvo-testimonials-display-slider tvo-default-template tve_green">
	<div id="<?php echo $unique_id; ?>">
		<div class="thrlider-slider">
			<?php foreach ( $testimonials as $testimonial ) : ?>
				<?php if ( ! empty( $testimonial ) ) : ?>
					<div class="thrlider-slide thrlider-slide-fix-height">
						<div class="tvo-testimonial-display-item tvo-apply-background custom-set7-slider">
							<div class="tvo-relative tvo-testimonial-content">
								<div class="quotes-holder">
									<?php if ( ! empty( $config['show_title'] ) && ! empty( $testimonial['title'] ) ) : ?>
										<div class="tvo-testimonial-quote"></div>
										<h4>
											<?php echo $testimonial['title'] ?>
										</h4>
									<?php endif; ?>
									<div class="tvo-testimonial-quote"></div>
								</div>
								<?php echo $testimonial['content'] ?>
								<div class="tvo-testimonial-info">
									<span class="tvo-testimonial-name">
										<?php echo $testimonial['name'] ?>
									</span>
									<?php if ( ! empty( $config['show_role'] ) ) : ?>
										<span class="tvo-testimonial-role">
											<?php if ( ! empty( $testimonial['role'] ) ) : ?>
												,
											<?php endif; ?>
											<?php $role_wrap_before = empty( $config['show_site'] ) || empty( $testimonial['website_url'] ) ? '' : '<a href="' . $testimonial['website_url'] . '">';
											$role_wrap_after        = empty( $config['show_site'] ) || empty( $testimonial['website_url'] ) ? '' : '</a>';
											echo $role_wrap_before . $testimonial['role'] . $role_wrap_after; ?>
										</span>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>
			<?php endforeach ?>
		</div>
		<div class="thrv-navigation-wrapper">
			<span class="thrlider-prev"></span>
			<span class="thrlider-next"></span>
		</div>
	</div>
</div>
<script type="text/javascript">
	jQuery( document ).ready( function () {
		setTimeout( function () {
			jQuery( '#<?php echo $unique_id; ?>' ).thrlider( {
				nav: true
			} );
		}, 100 );
	} );
</script>
