<?php if ( isset( $_GET['wrong_data'] ) ) : ?>
	<div>
		<p style="color: red;">
			<?php echo __( 'Invalid username or password', TVA_Const::T ) ?>
		</p>
	</div>
<?php endif; ?>
