const DIMENSIONS = Object.freeze( [ 'formality', 'energy', 'warmth', 'tradition' ] );

export const buildVoiceTunerUpdatePlan = ( {
  records,
  currentCards,
  profile,
  hasBalancedProfile,
  personalityMap,
} ) => {
  // Only the free, direct-child palette cards are records here — the pro group is
  // wrapped in its Try & Play host and pinned last by the voice tuner itself, so
  // it never reaches this plan. Sorting is therefore plain fit (then original).
  const orderedRecords = hasBalancedProfile
    ? records.slice().sort( ( left, right ) => left.originalIndex - right.originalIndex )
    : records
      .map( record => ( {
        ...record,
        score: getPaletteFitScore( profile, getPalettePersonality( record.paletteID, personalityMap ) ),
      } ) )
      .sort( ( left, right ) => {
        if ( right.score !== left.score ) {
          return right.score - left.score;
        }

        return left.originalIndex - right.originalIndex;
      } );

  const orderedCards = orderedRecords.map( record => record.card );

  return {
    orderedCards,
    shouldReorder: ! haveSameCardOrder( currentCards, orderedCards ),
    badgeStates: orderedRecords.map( record => {
      return getBadgeState( record.card, record.score ?? 0.5, hasBalancedProfile );
    } ),
  };
};

const haveSameCardOrder = ( currentCards, orderedCards ) => {
  if ( currentCards.length !== orderedCards.length ) {
    return false;
  }

  return currentCards.every( ( card, index ) => card === orderedCards[ index ] );
};

const getBadgeState = ( card, score, hasBalancedProfile ) => {
  if ( hasBalancedProfile ) {
    return {
      card,
      visible: false,
    };
  }

  const fit = clampScore( score );

  return {
    card,
    visible: true,
    fitClass: getFitStateClass( fit ),
    label: `${ Math.round( fit * 100 ) }%`,
  };
};

const getPalettePersonality = ( paletteID, personalityMap ) => {
  if ( ! Object.prototype.hasOwnProperty.call( personalityMap, paletteID ) ) {
    return null;
  }

  const personality = personalityMap[ paletteID ] || {};

  return DIMENSIONS.reduce( ( vector, dimension ) => {
    const value = Number.parseFloat( personality[ dimension ] );
    vector[ dimension ] = Number.isFinite( value ) ? value : 0.5;
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
