import { buildVoiceTunerUpdatePlan } from '../../src/_js/customizer/font-palettes/voice-tuner-plan.js';

const makeCard = ( id ) => ( { id } );

export const runVoiceTunerPlanTests = async ( assert ) => {
  {
    const cardA = makeCard( 'alpha' );
    const cardB = makeCard( 'beta' );
    const cardC = makeCard( 'gamma' );

    const records = [
      { card: cardA, paletteID: 'alpha', originalIndex: 0 },
      { card: cardB, paletteID: 'beta', originalIndex: 1 },
      { card: cardC, paletteID: 'gamma', originalIndex: 2 },
    ];

    const plan = buildVoiceTunerUpdatePlan( {
      records,
      currentCards: [ cardB, cardA, cardC ],
      profile: {},
      hasBalancedProfile: true,
      personalityMap: {},
    } );

    assert.deepEqual(
      plan.orderedCards.map( card => card.id ),
      [ 'alpha', 'beta', 'gamma' ],
      'balanced profile should restore the original palette order'
    );
    assert.equal( plan.shouldReorder, true, 'balanced profile should request a reorder when current order drifted' );
    assert.deepEqual(
      plan.badgeStates,
      [
        { card: cardA, visible: false },
        { card: cardB, visible: false },
        { card: cardC, visible: false },
      ],
      'balanced profile should remove all fit badges'
    );
  }

  {
    const cardA = makeCard( 'alpha' );
    const cardB = makeCard( 'beta' );
    const cardC = makeCard( 'gamma' );

    const records = [
      { card: cardA, paletteID: 'alpha', originalIndex: 0 },
      { card: cardB, paletteID: 'beta', originalIndex: 1 },
      { card: cardC, paletteID: 'gamma', originalIndex: 2 },
    ];

    const plan = buildVoiceTunerUpdatePlan( {
      records,
      currentCards: [ cardA, cardB, cardC ],
      profile: {
        formality: 0.85,
        energy: 0.5,
        warmth: 0.5,
        tradition: 0.5,
      },
      hasBalancedProfile: false,
      personalityMap: {
        alpha: { formality: 0.85, energy: 0.5, warmth: 0.5, tradition: 0.5 },
        beta: { formality: 0.5, energy: 0.5, warmth: 0.5, tradition: 0.5 },
        gamma: { formality: 0.15, energy: 0.5, warmth: 0.5, tradition: 0.5 },
      },
    } );

    assert.deepEqual(
      plan.orderedCards.map( card => card.id ),
      [ 'alpha', 'beta', 'gamma' ],
      'cards should stay in place when the current DOM order already matches the desired sort'
    );
    assert.equal( plan.shouldReorder, false, 'no DOM reorder should be requested when order is already correct' );
    assert.deepEqual(
      plan.badgeStates.map( state => ( {
        id: state.card.id,
        visible: state.visible,
        fitClass: state.fitClass,
        label: state.label,
      } ) ),
      [
        { id: 'alpha', visible: true, fitClass: 'voice-tuner-fit--high', label: '100%' },
        { id: 'beta', visible: true, fitClass: 'voice-tuner-fit--high', label: '83%' },
        { id: 'gamma', visible: true, fitClass: 'voice-tuner-fit--mid', label: '65%' },
      ],
      'visible badge state should be deterministic from the computed fit score'
    );
  }
};
