(
	function( $, exports, wp ) {
		var api = wp.customize;
		var $window = $( window );

		wp.customize.bind( 'ready', function() {

			// Handle the Style Manager user feedback logic.
            var $smUserFeedbackModal = $('#style-manager-user-feedback-modal');
            if ( $smUserFeedbackModal.length ) {
                var $smUserFeedbackForm = $smUserFeedbackModal.find('form'),
                    $smUserFeedbackCloseBtn = $smUserFeedbackModal.find('.close'),
                    $smUserFeedbackFirstStep = $smUserFeedbackModal.find('.first-step'),
                    $smUserFeedbackSecondStep = $smUserFeedbackModal.find('.second-step'),
                    $smUserFeedbackThanksStep = $smUserFeedbackModal.find('.thanks-step'),
                    $smUserFeedbackErrorStep = $smUserFeedbackModal.find('.error-step'),
                    smUserFeedbackModalShown = false,
                    smColorPaletteChanged = false;

                // Handle when to open the modal.
                api.bind('saved', function () {
                    // We will only show the modal once per Customizer session.
                    if (!smUserFeedbackModalShown && smColorPaletteChanged) {
                        $('body').addClass('modal-open');
                        smUserFeedbackModalShown = true;
                    }
                });

                // Handle the color palette changed info update.
                const colorPaletteSetting = api( 'sm_color_palette' );
                if ( !_.isUndefined(colorPaletteSetting) ) {
                    colorPaletteSetting.bind( function( new_value, old_value ) {
                        if ( new_value != old_value ) {
                            smColorPaletteChanged = true;
                        }
                    } )
                }
                const colorPaletteVariationSetting = api( 'sm_color_palette_variation' );
                if ( !_.isUndefined(colorPaletteVariationSetting) ) {
                    colorPaletteVariationSetting.bind( function( new_value, old_value ) {
                        if ( new_value != old_value ) {
                            smColorPaletteChanged = true;
                        }
                    } )
                }

                // Handle the modal submit.
                $smUserFeedbackForm.on('submit', function (event) {
                    event.preventDefault();

                    let $form = $(event.target);

                    let data = {
                        action: 'style_manager_user_feedback',
                        nonce: sm_settings.style_manager_user_feedback_nonce,
                        type: $form.find('input[name=type]').val(),
                        rating: $form.find('input[name=rating]:checked').val(),
                        message: $form.find('textarea[name=message]').val()
                    };

                    $.post(
                        sm_settings.ajax_url,
                        data,
                        function (response) {
                            if (true === response.success) {
                                $smUserFeedbackFirstStep.hide();
                                $smUserFeedbackSecondStep.hide();
                                $smUserFeedbackThanksStep.show();
                                $smUserFeedbackErrorStep.hide();
                            } else {
                                $smUserFeedbackFirstStep.hide();
                                $smUserFeedbackSecondStep.hide();
                                $smUserFeedbackThanksStep.hide();
                                $smUserFeedbackErrorStep.show();
                            }
                        }
                    );
                });

                $smUserFeedbackForm.find('input[name=rating]').on('change', function (event) {
                    // Leave everything in working order
                    setTimeout(function () {
                        $smUserFeedbackSecondStep.show();
                    }, 300);

                    let rating = $smUserFeedbackForm.find('input[name=rating]:checked').val();

                    $smUserFeedbackForm.find('.rating-placeholder').text(rating);
                });

                $smUserFeedbackCloseBtn.on('click', function (event) {
                    event.preventDefault();

                    $('body').removeClass('modal-open');

                    // Leave everything in working order
                    setTimeout(function () {
                        $smUserFeedbackFirstStep.show();
                        $smUserFeedbackSecondStep.hide();
                        $smUserFeedbackThanksStep.hide();
                        $smUserFeedbackErrorStep.hide();
                    }, 300);
                });
            }
		} );
	}
)( jQuery, window, wp );


// Reverses a hex color to either black or white
function smInverseHexColorToBlackOrWhite( hex ) {
	return smInverseHexColor( hex, true );
}

// Taken from here: https://stackoverflow.com/a/35970186/6260836
function smInverseHexColor( hex, bw ) {
	if ( hex.indexOf( '#' ) === 0 ) {
		hex = hex.slice( 1 );
	}
	// convert 3-digit hex to 6-digits.
	if ( hex.length === 3 ) {
		hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
	}
	if ( hex.length !== 6 ) {
		throw new Error( 'Invalid HEX color.' );
	}
	var r = parseInt( hex.slice( 0, 2 ), 16 ),
		g = parseInt( hex.slice( 2, 4 ), 16 ),
		b = parseInt( hex.slice( 4, 6 ), 16 );
	if ( bw ) {
		// http://stackoverflow.com/a/3943023/112731
		return (
			       r * 0.299 + g * 0.587 + b * 0.114
		       ) > 186
			? '#000000'
			: '#FFFFFF';
	}
	// invert color components
	r = (
		255 - r
	).toString( 16 );
	g = (
		255 - g
	).toString( 16 );
	b = (
		255 - b
	).toString( 16 );
	// pad each with zeros and return
	return "#" + smPadZero( r ) + smPadZero( g ) + smPadZero( b );
}

function smPadZero( str, len ) {
	len = len || 2;
	var zeros = new Array( len ).join( '0' );
	return (
		zeros + str
	).slice( - len );
}
