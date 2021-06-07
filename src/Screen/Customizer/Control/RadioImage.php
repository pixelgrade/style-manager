<?php
/**
 * Customizer radio image control.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Screen\Customizer\Control;

/**
 * Customizer radio image control class.
 *
 * This handles the 'radio_image' control type.
 *
 * @since 2.0.0
 */
class RadioImage extends BaseControl {
	/**
	 * Type.
	 *
	 * @var string
	 */
	public $type = 'radio_image';

	public string $choices_type = 'radio';

	/**
	 * Render the control's content.
	 */
	public function render_content() {

		switch ( $this->choices_type ) {

			case 'radio' :
			{ ?>
				<label>
					<?php if ( ! empty( $this->label ) ) { ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php } ?>

					<div class="style-manager_radio_image">
						<?php
						foreach ( $this->choices as $value => $image_url ) {

							if ( empty( $image_url ) ) {
								$image_url = plugins_url() . '/style-manager/images/default_radio_image.png';
							} ?>
							<label>
								<input
									<?php $this->link(); ?>
									name="<?php echo esc_attr( $this->setting->id ); ?>"
									type="radio"
									value="<?php echo esc_attr( $value ); ?>"
									<?php selected( $this->value(), $value ); ?>
								></input>
								<img src="<?php echo esc_url( $image_url ); ?>"
								     style="width: 50px; display: block; height: auto;"></span>
							</label>
						<?php } ?>
					</div>

					<?php if ( ! empty( $this->description ) ) { ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php } ?>
				</label>
				<?php break;
			}

			case 'buttons' :
			{ ?>
				<label>
					<?php if ( ! empty( $this->label ) ) { ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php } ?>

					<div class="style-manager_radio_image radio_buttons">
						<?php
						foreach ( $this->choices as $value => $setts ) {
							if ( ! isset( $setts['options'] ) || ! isset( $setts['label'] ) ) {
								continue;
							}
							$color = '';
							if ( isset( $setts['color'] ) ) {
								$color .= ' style="border-left-color: ' . $setts['color'] . '; color: ' . $setts['color'] . ';"';
							}

							$label   = $setts['label'];
							$options = $setts['options'];
							$data    = ' data-options=\'' . json_encode( $options ) . '\''; ?>

							<fieldset class="style-manager_radio_button">
								<input
									<?php $this->link(); ?>
									name="<?php echo esc_attr( $this->setting->id ); ?>"
									type="radio"
									value="<?php echo esc_attr( $value ); ?>"
									<?php selected( $this->value(), $value ); ?>
									<?php echo $data; ?>
								/>
								<label class="button" for="<?php echo esc_attr( $this->setting->id ); ?>" <?php echo $color; ?>>
									<?php echo $label; ?>
								</label>
							</fieldset>
						<?php } ?>
					</div>

					<?php if ( ! empty( $this->description ) ) { ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php } ?>
				</label>
				<?php break;
			}

			default:
				break;
		}
	}
}
