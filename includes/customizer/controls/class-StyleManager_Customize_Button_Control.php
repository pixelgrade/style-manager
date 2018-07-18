<?php

/**
 * Class Pix_Customize_Color_Control
 * A simple Color Control
 */
class StyleManager_Customize_Button_Control extends StyleManager_Customize_Control {
	public $type    = 'button';
	public $action  = null;

	/**
	 * Render the control's content.
	 *
	 * @since 1.0.0
	 */
	public function render_content() { ?>
		<button type="button" class="customify_button button" <?php $this->input_attrs(); ?> data-action="<?php echo esc_html( $this->action ); ?>" ><?php echo esc_html( $this->label ); ?></button>
	<?php

	}
}