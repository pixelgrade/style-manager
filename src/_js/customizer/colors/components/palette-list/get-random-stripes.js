const getRandomBetween = ( min, max ) => {
  const random = Math.max(0, Math.random() - Number.MIN_VALUE );
  return Math.floor( random * (max - min + 1) + min );
};

const getRandomStripes = ( palettes => {
  const widths = [1, 1, 2, 2, 4];

  if ( ! palettes.length ) {
    return [];
  }

  const stripes = Array.from( Array(5).keys() ).map( idx => {
    const stripe = document.createElement( 'div' );
    const widthPos = getRandomBetween( 0, widths.length - 1 );
    const width = widths[ widthPos ];

    widths.splice( widthPos, 1 );

    return {
      index: idx,
      element: stripe,
      width: width,
    }
  } );

  stripes.sort( ( a, b ) => ( a.width > b.width ) ? -1 : ( ( a.width < b.width ) ? 1 : 0 ) );

  const segments = [ Array.from( Array(10).keys() ) ];

  stripes.forEach( stripe => {
    const segmentsIndexes = Array.from( Array( segments.length ).keys() );
    const availSegmentsIndexes = segmentsIndexes.filter( index => segments[ index ].length >= stripe.width );
    const segmentRandom = getRandomBetween( 0, availSegmentsIndexes.length - 1 );
    const segmentIndex = availSegmentsIndexes[segmentRandom];
    const thisSegment = segments[ segmentIndex ];
    const positionRandom = getRandomBetween( 0, thisSegment.length - stripe.width );
    const position = thisSegment[ positionRandom ];

    segments.splice( segmentIndex, 1, thisSegment.slice( 0, positionRandom ), thisSegment.slice( positionRandom + stripe.width, thisSegment.length ) );

    stripe.pos = position;
  } );

  let sourceColors = [];
  let otherColors = [];

  palettes.forEach( palette => {
    const id = palette.id + '';

    if ( id.charAt( 0 ) === '_' ) {
      return;
    }

    const sourceIndex = palette.colors.findIndex( color => palette.source.includes( color.value ) );

    if ( sourceIndex > -1 ) {
      sourceColors.push( palette.colors[sourceIndex].value );
    }

    const remainingColors = palette.colors.slice();
    remainingColors.splice( sourceIndex, 1 );

    otherColors = otherColors.concat( remainingColors );
  } );

  // Randomize order of generated colors
  otherColors.sort( () => Math.random() > 0.5 ? -1 : 1 );

  // merge sources and other colors
  const colors = sourceColors.concat( otherColors ).slice(0, 5);

  stripes.sort( ( a, b ) => ( a.width > b.width ) ? -1 : ( ( a.width < b.width ) ? 1 : 0 ) );

  stripes.forEach( ( stripe, index ) => {
    stripe.color = colors[index];
  } );

  stripes.sort( ( a, b ) => ( a.index > b.index ) ? -1 : ( ( a.index < b.index ) ? 1 : 0 ) );

  return stripes;
} );

export default getRandomStripes;
