const RADIO_GROUP_SCSS_PATH = new URL( '../../src/_js/customizer/scss/controls/_radio-group.scss', import.meta.url );

export const runRadioGroupStyleTests = async ( assert ) => {
  const { readFile } = await import( 'node:fs/promises' );
  const source = await readFile( RADIO_GROUP_SCSS_PATH, 'utf8' );
  const labelBlockMatch = source.match( /label\s*\{([\s\S]*?)&:nth-of-type\(2\)/ );

  assert.ok( labelBlockMatch, 'expected to find the shared sm-radio-group label rule' );
  assert.ok(
    /transition\s*:\s*all\s+\.3s\s+ease\s*;/.test( labelBlockMatch[1] ),
    'shared sm-radio-group labels should restore the original all .3s ease transition'
  );
};
