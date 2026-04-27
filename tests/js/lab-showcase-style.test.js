const LAB_SHOWCASE_SCSS_PATH = new URL( '../../src/_js/lab/showcase.scss', import.meta.url );

export const runLabShowcaseStyleTests = async ( assert ) => {
  const { readFile } = await import( 'node:fs/promises' );
  const source = await readFile( LAB_SHOWCASE_SCSS_PATH, 'utf8' );
  const chipBlockMatch = source.match( /\.sm-lab-cascade-chip\s*\{([\s\S]*?)\n\}/ );
  const chipLabelBlockMatch = source.match( /\.sm-lab-cascade-chip > span\s*\{([\s\S]*?)\n\}/ );
  const chipCodeBlockMatch = source.match( /\.sm-lab-cascade-chip code\s*\{([\s\S]*?)\n\}/ );
  const mobileCascadeBlockMatch = source.match( /@media\s*\(max-width:\s*850px\)\s*\{([\s\S]*?)\n\}/ );

  assert.ok( chipBlockMatch, 'expected the cascade chip style block to exist' );
  assert.ok( chipLabelBlockMatch, 'expected the cascade chip label style block to exist' );
  assert.ok( chipCodeBlockMatch, 'expected the cascade chip code style block to exist' );
  assert.ok(
    /font-size\s*:\s*11px\s*;/.test( chipBlockMatch[1] ),
    'cascade chips should use one compact font size'
  );
  assert.ok(
    /font-size\s*:\s*inherit\s*;/.test( chipLabelBlockMatch[1] ),
    'cascade chip labels should inherit the chip font size'
  );
  assert.ok(
    /font-size\s*:\s*inherit\s*;/.test( chipCodeBlockMatch[1] ),
    'cascade chip code should inherit the chip font size'
  );
  assert.ok(
    ! /(\.sm-lab-cascade-chip code\s*\{[\s\S]*?font-size\s*:)/.test( mobileCascadeBlockMatch?.[1] || '' ),
    'mobile cascade styles should not shrink only code values'
  );
};
