import $ from 'jquery';

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
      setting.bind( updateVoiceTuner );
    } );
  } );
};

const updateVoiceTuner = () => {
  const profile = getCurrentVoiceProfile();
  const hasBalancedProfile = DIMENSIONS.every( dimension => profile[ dimension ] === VALUE_MAP.balanced );

  $( '.js-font-palette' ).each( function( index, paletteSet ) {
    updatePaletteSet( $( paletteSet ), profile, hasBalancedProfile );
  } );
};

const updatePaletteSet = ( $paletteSet, profile, hasBalancedProfile ) => {
  const cards = $paletteSet.children( '.customize-inside-control-row' ).toArray();

  if ( ! cards.length ) {
    return;
  }

  const scoredCards = cards.map( ( card, index ) => {
    const paletteID = getPaletteID( card );

    return {
      card,
      index,
      score: getPaletteFitScore( profile, getPalettePersonality( paletteID ) ),
    };
  } );

  scoredCards
    .sort( ( left, right ) => {
      if ( right.score !== left.score ) {
        return right.score - left.score;
      }

      return left.index - right.index;
    } )
    .forEach( ({ card }) => {
      $paletteSet.append( card );
    } );

  scoredCards.forEach( ({ card, score }) => {
    updatePaletteBadge( $( card ), score, hasBalancedProfile );
  } );
};

const updatePaletteBadge = ( $card, score, hasBalancedProfile ) => {
  const $badge = $card.children( '.voice-tuner-fit' ).first();

  if ( hasBalancedProfile ) {
    $badge.remove();
    return;
  }

  const fit = clampScore( score );
  const percentage = Math.round( fit * 100 );
  const $targetBadge = $badge.length ? $badge : $( '<span />', {
    class: 'voice-tuner-fit',
  } ).appendTo( $card );

  $targetBadge.removeClass( 'voice-tuner-fit--high voice-tuner-fit--mid voice-tuner-fit--low' );
  $targetBadge.addClass( getFitStateClass( fit ) );
  $targetBadge.text( `${ percentage }%` );
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

const getPalettePersonality = paletteID => {
  const personalityMap = window.styleManager?.fontPalettes?.personalityMap || {};

  if ( ! Object.prototype.hasOwnProperty.call( personalityMap, paletteID ) ) {
    return null;
  }

  const personality = personalityMap[ paletteID ] || {};

  return DIMENSIONS.reduce( ( vector, dimension ) => {
    const value = Number.parseFloat( personality[ dimension ] );
    vector[ dimension ] = Number.isFinite( value ) ? value : VALUE_MAP.balanced;
    return vector;
  }, {} );
};

const getPaletteFitScore = ( profile, personality ) => {
  if ( ! personality ) {
    return 0.5;
  }

  const distance = DIMENSIONS.reduce( ( total, dimension ) => {
    const difference = profile[ dimension ] - personality[ dimension ];

    return total + Math.pow( difference, 2 );
  }, 0 );

  const dist = Math.sqrt( distance ) / 2;

  return clampScore( 1 - dist );
};

const clampScore = score => {
  return Math.max( 0, Math.min( 1, score ) );
};

const getFitStateClass = fit => {
  if ( fit >= 0.75 ) {
    return 'voice-tuner-fit--high';
  }

  if ( fit >= 0.5 ) {
    return 'voice-tuner-fit--mid';
  }

  return 'voice-tuner-fit--low';
};
