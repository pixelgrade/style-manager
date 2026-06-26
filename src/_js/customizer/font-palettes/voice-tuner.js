import $ from 'jquery';
import { buildVoiceTunerUpdatePlan } from './voice-tuner-plan';
import { createVoiceTunerUpdateScheduler } from './voice-tuner-scheduler';

const VALUE_MAP = Object.freeze( {
  low: 0.15,
  balanced: 0.5,
  high: 0.85,
} );

const DIMENSIONS = Object.freeze( [ 'formality', 'energy', 'warmth', 'tradition' ] );

let hasBoundSettings = false;

export const initializeVoiceTuner = () => {
  if ( typeof wp === 'undefined' || ! wp.customize ) {
    return;
  }

  if ( ! hasBoundSettings ) {
    bindVoiceTunerSettings();
    hasBoundSettings = true;
  }

  updateVoiceTuner();
};

const bindVoiceTunerSettings = () => {
  DIMENSIONS.forEach( dimension => {
    const settingID = `sm_voice_${ dimension }`;

    wp.customize( settingID, setting => {
      setting.bind( scheduleVoiceTunerUpdate );
    } );
  } );
};

const updateVoiceTuner = () => {
  const profile = getCurrentVoiceProfile();
  const hasBalancedProfile = DIMENSIONS.every( dimension => profile[ dimension ] === VALUE_MAP.balanced );
  const personalityMap = window.styleManager?.fontPalettes?.personalityMap || {};

  $( '.js-font-palette' ).each( function( index, paletteSet ) {
    updatePaletteSet( $( paletteSet ), profile, hasBalancedProfile, personalityMap );
  } );
};

const voiceTunerUpdateScheduler = createVoiceTunerUpdateScheduler( {
  flush: updateVoiceTuner,
} );

const scheduleVoiceTunerUpdate = () => {
  voiceTunerUpdateScheduler.schedule();
};

const updatePaletteSet = ( $paletteSet, profile, hasBalancedProfile, personalityMap ) => {
  const records = getPaletteRecords( $paletteSet );

  if ( ! records.length ) {
    return;
  }

  const currentCards = $paletteSet.children( '.customize-inside-control-row' ).toArray();
  const plan = buildVoiceTunerUpdatePlan( {
    records,
    currentCards,
    profile,
    hasBalancedProfile,
    personalityMap,
  } );

  if ( plan.shouldReorder ) {
    reorderPaletteCards( $paletteSet[0], plan.orderedCards );
  }

  plan.badgeStates.forEach( syncPaletteBadge );
};

const getPaletteRecords = ( $paletteSet ) => {
  const cachedRecords = $paletteSet.data( 'voiceTunerRecords' );

  if ( cachedRecords ) {
    return cachedRecords;
  }

  const records = $paletteSet.children( '.customize-inside-control-row' ).toArray().map( ( card, originalIndex ) => ( {
    card,
    paletteID: getPaletteID( card ),
    badge: getOrCreateBadge( card ),
    originalIndex,
  } ) );

  $paletteSet.data( 'voiceTunerRecords', records );

  return records;
};

const reorderPaletteCards = ( paletteSet, orderedCards ) => {
  paletteSet.append( ...orderedCards );

  // Voice-fit reordering only moves the free direct-child cards. The pro
  // palettes live in a Try & Play group (with its banner inserted just before
  // it); re-append both so the gated group always stays pinned after the free
  // palettes, no matter how the free set gets sorted.
  const banner = paletteSet.querySelector( ':scope > .sm-tap-intro' );
  const proGroup = paletteSet.querySelector( ':scope > .sm-plus-palette-group' );
  if ( banner ) {
    paletteSet.appendChild( banner );
  }
  if ( proGroup ) {
    paletteSet.appendChild( proGroup );
  }
};

const syncPaletteBadge = ( badgeState ) => {
  const { card, visible, fitClass, label } = badgeState;
  const badge = getOrCreateBadge( card );

  if ( ! visible ) {
    if ( ! badge.hidden ) {
      badge.hidden = true;
    }

    return;
  }

  const desiredClassName = `voice-tuner-fit ${ fitClass }`;

  if ( badge.hidden ) {
    badge.hidden = false;
  }

  if ( badge.className !== desiredClassName ) {
    badge.className = desiredClassName;
  }

  if ( badge.textContent !== label ) {
    badge.textContent = label;
  }
};

const getOrCreateBadge = card => {
  const existingBadge = card.querySelector( '.voice-tuner-fit' );

  if ( existingBadge ) {
    return existingBadge;
  }

  const badge = document.createElement( 'span' );
  badge.className = 'voice-tuner-fit';
  badge.hidden = true;
  card.appendChild( badge );

  return badge;
};

const getCurrentVoiceProfile = () => {
  return DIMENSIONS.reduce( ( profile, dimension ) => {
    profile[ dimension ] = getSettingNumericValue( `sm_voice_${ dimension }` );
    return profile;
  }, {} );
};

const getSettingNumericValue = settingID => {
  const setting = wp.customize( settingID );

  if ( ! setting ) {
    return VALUE_MAP.balanced;
  }

  return VALUE_MAP[ setting() ] ?? VALUE_MAP.balanced;
};

const getPaletteID = card => {
  const input = card.querySelector( 'input[type="radio"]' );

  return input ? input.value : '';
};
