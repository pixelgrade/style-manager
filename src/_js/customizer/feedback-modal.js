import $ from 'jquery';

export const initializeFeedbackModal = () => {

  const $userFeedbackModal = $( '#style-manager-user-feedback-modal' );

  if ( ! $userFeedbackModal.length ) {
    return;
  }

  const $userFeedbackForm = $userFeedbackModal.find( 'form' ),
    $userFeedbackCloseBtn = $userFeedbackModal.find( '.close' ),
    $userFeedbackFirstStep = $userFeedbackModal.find( '.first-step' ),
    $userFeedbackSecondStep = $userFeedbackModal.find( '.second-step' ),
    $userFeedbackThanksStep = $userFeedbackModal.find( '.thanks-step' ),
    $userFeedbackErrorStep = $userFeedbackModal.find( '.error-step' )

  let userFeedbackModalShown = false,
    colorPaletteChanged = false,
    fontPaletteChanged = false

  // Handle when to open the modal.
  wp.customize.bind( 'saved', function() {
    // We will only show the modal once per Customizer session.
    if ( ! userFeedbackModalShown && ( colorPaletteChanged || fontPaletteChanged ) ) {
      $( 'body' ).addClass( 'feedback-modal-open modal-open' );
      userFeedbackModalShown = true;
    }
  } )

  // Handle the color palette changed info update.
  wp.customize( 'sm_advanced_palette_output', setting => {
    setting.bind( function( new_value, old_value ) {
      // Intentional loose comparison.
      if ( new_value != old_value ) {
        colorPaletteChanged = true;
      }
    } );
  } );

  // Handle the font palette changed info update.
  wp.customize( 'sm_font_palette', setting => {
    setting.bind( function( new_value, old_value ) {
      // Intentional loose comparison.
      if ( new_value != old_value ) {
        fontPaletteChanged = true;
      }
    } );
  } );

  // Handle the modal submit.
  $userFeedbackForm.on( 'submit', function( event ) {
    event.preventDefault()

    let $form = $( event.target )

    let data = {
      action: 'customify_style_manager_user_feedback',
      nonce: styleManager.userFeedback.nonce,
      type: $form.find( 'input[name=type]' ).val(),
      rating: $form.find( 'input[name=rating]:checked' ).val(),
      message: $form.find( 'textarea[name=message]' ).val()
    }

    $.post(
      customify.config.ajax_url,
      data,
      function( response ) {
        if ( true === response.success ) {
          $userFeedbackFirstStep.hide()
          $userFeedbackSecondStep.hide()
          $userFeedbackThanksStep.show()
          $userFeedbackErrorStep.hide()
        } else {
          $userFeedbackFirstStep.hide()
          $userFeedbackSecondStep.hide()
          $userFeedbackThanksStep.hide()
          $userFeedbackErrorStep.show()
        }
      }
    )
  } )

  $userFeedbackForm.find( 'input[name=rating]' ).on( 'change', function( event ) {
    // Leave everything in working order
    setTimeout( function() {
      $userFeedbackSecondStep.show()
    }, 300 )

    let rating = $userFeedbackForm.find( 'input[name=rating]:checked' ).val()

    $userFeedbackForm.find( '.rating-placeholder' ).text( rating )
  } )

  $userFeedbackCloseBtn.on( 'click', function( event ) {
    event.preventDefault()

    $( 'body' ).removeClass( 'feedback-modal-open modal-open' )

    // Leave everything in working order
    setTimeout( function() {
      $userFeedbackFirstStep.show()
      $userFeedbackSecondStep.hide()
      $userFeedbackThanksStep.hide()
      $userFeedbackErrorStep.hide()
    }, 300 )
  } )
}
