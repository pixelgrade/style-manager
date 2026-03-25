const VOICE_TUNER_TOGGLE_CONTROL_ID = 'customize-control-sm_voice_tuner_toggle_control';
const VOICE_TUNER_CONTROL_IDS = [
  'customize-control-sm_voice_tuner_label_control',
  'customize-control-sm_voice_formality_control',
  'customize-control-sm_voice_energy_control',
  'customize-control-sm_voice_warmth_control',
  'customize-control-sm_voice_tradition_control',
];
const FALLBACK_TITLE = 'Tune your project\'s voice';
const TOGGLE_ICON_MARKUP = `
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
    <path d="M3 17V19H9V17H3ZM3 5V7H13V5H3ZM13 21V19H21V17H13V15H11V21H13ZM7 9V11H3V13H7V15H9V9H7ZM21 13V11H11V13H21ZM15 9H17V7H21V5H17V3H15V9Z" fill="currentColor"/>
  </svg>
`;

export const initializeVoiceTunerAccordion = ( { document: documentRef = document } = {} ) => {
  if ( documentRef.getElementById( VOICE_TUNER_TOGGLE_CONTROL_ID ) ) {
    return;
  }

  const controls = VOICE_TUNER_CONTROL_IDS.map( id => documentRef.getElementById( id ) );

  if ( controls.some( control => ! control ) ) {
    return;
  }

  const [ titleControl ] = controls;
  const parentElement = titleControl.parentElement;

  if ( ! parentElement ) {
    return;
  }

  const toggleTitle = getToggleTitle( titleControl );
  const { control: toggleControl, button: toggleButton } = createToggleControl( documentRef, toggleTitle );

  titleControl.classList.add( 'sm-voice-tuner-accordion__intro' );
  controls.forEach( control => control.classList.add( 'sm-voice-tuner-accordion__content' ) );

  toggleButton.addEventListener( 'click', () => {
    const isExpanded = toggleButton.getAttribute( 'aria-expanded' ) !== 'true';
    syncExpandedState( controls, toggleControl, toggleButton, isExpanded );
  } );

  parentElement.insertBefore( toggleControl, titleControl );

  syncExpandedState( controls, toggleControl, toggleButton, false );
};

const getToggleTitle = ( titleControl ) => {
  const titleElement = titleControl.querySelector( '.customize-control-title' );
  const rawTitle = titleElement?.textContent?.trim() || FALLBACK_TITLE;

  return rawTitle.replace( /:\s*$/, '' );
};

const createToggleControl = ( documentRef, title ) => {
  const control = documentRef.createElement( 'li' );
  control.id = VOICE_TUNER_TOGGLE_CONTROL_ID;
  control.className = 'customize-control customize-control-html sm-voice-tuner-toggle-control';
  control.setAttribute( 'style', 'padding: 0' );

  const button = documentRef.createElement( 'button' );
  button.type = 'button';
  button.className = 'sm-panel-toggle sm-voice-tuner-toggle__button';
  button.setAttribute( 'aria-expanded', 'false' );
  button.setAttribute( 'aria-label', title );

  const icon = documentRef.createElement( 'span' );
  icon.className = 'sm-panel-toggle__icon sm-voice-tuner-toggle__icon';
  icon.innerHTML = TOGGLE_ICON_MARKUP;

  const label = documentRef.createElement( 'span' );
  label.className = 'sm-panel-toggle__label';
  label.textContent = title;

  button.append( icon, label );
  control.appendChild( button );

  return { control, button };
};

const syncExpandedState = ( controls, toggleControl, toggleButton, isExpanded ) => {
  toggleControl.classList.toggle( 'is-open', isExpanded );
  toggleButton.setAttribute( 'aria-expanded', String( isExpanded ) );

  controls.forEach( control => {
    control.hidden = ! isExpanded;
  } );
};
