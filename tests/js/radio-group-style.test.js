const RADIO_GROUP_SCSS_PATH = new URL( '../../src/_js/customizer/scss/controls/_radio-group.scss', import.meta.url );

export const runRadioGroupStyleTests = async ( assert ) => {
  const { readFile } = await import( 'node:fs/promises' );
  const source = await readFile( RADIO_GROUP_SCSS_PATH, 'utf8' );
  const labelBlockMatch = source.match( /label\s*\{([\s\S]*?)&:nth-of-type\(2\)/ );
  const checkedLabelBlockMatch = source.match( /&:checked\s*\+\s*label\s*\{([\s\S]*?)\n\s*\}\n\s*\}/ );

  assert.ok( labelBlockMatch, 'expected to find the shared sm-radio-group label rule' );
  assert.ok( checkedLabelBlockMatch, 'expected to find the shared checked label rule' );
  assert.ok(
    /transition\s*:\s*all\s+0\.4s\s+ease\s*;/.test( labelBlockMatch[1] ),
    'shared sm-radio-group labels should use the 0.4s transition timing'
  );
  assert.ok(
    ! /&:before\s*\{/.test( checkedLabelBlockMatch[1] ),
    'shared sm-radio-group active labels should not render a checkmark pseudo-element'
  );
};
