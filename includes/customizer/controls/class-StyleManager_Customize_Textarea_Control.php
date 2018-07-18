<?php

/**
 * Class StyleManager_Customize_Textarea_Control.
 *
 * A simple textarea control.
 */
class StyleManager_Customize_Textarea_Control extends StyleManager_Customize_Control {
	public $type    = 'textarea';
	public $live    = false;

	/**
	 * Render the control's content.
	 *
	 * @since 3.4.0
	 */
	public function render_content() { ?>

		<label>
			<?php if ( ! empty( $this->label ) ) : ?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>
			<textarea id="<?php echo $this->id; ?>" rows="5" <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
			<?php if ( ! empty( $this->description ) ) : ?>
				<span class="description customize-control-description"><?php echo $this->description; ?></span>
			<?php endif; ?>
		</label>
	<?php

	}
}
