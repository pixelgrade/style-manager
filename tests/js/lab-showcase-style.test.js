const LAB_SHOWCASE_SCSS_PATH = new URL( '../../src/_js/lab/showcase.scss', import.meta.url );

export const runLabShowcaseStyleTests = async ( assert ) => {
  const { readFile } = await import( 'node:fs/promises' );
  const source = await readFile( LAB_SHOWCASE_SCSS_PATH, 'utf8' );
  const chipBlockMatch = source.match( /\.sm-lab-cascade-chip\s*\{([\s\S]*?)\n\}/ );
  const chipLabelBlockMatch = source.match( /\.sm-lab-cascade-chip > span\s*\{([\s\S]*?)\n\}/ );
  const chipCodeBlockMatch = source.match( /\.sm-lab-cascade-chip code\s*\{([\s\S]*?)\n\}/ );
  const signalSummaryBlockMatch = source.match( /\.sm-lab-signal-cascade__signal-summary\s*\{([\s\S]*?)\n\}/ );
  const signalIconBlockMatch = source.match( /\.sm-lab-signal-cascade__signal-summary \.sm-lab-signal-bars__icon\s*\{([\s\S]*?)\n\}/ );
  const signalStepBlockMatch = Array.from( source.matchAll( /\.sm-lab-signal-cascade__signal-step\s*\{([\s\S]*?)\n\}/g ) )
    .find( ( match ) => /place-items\s*:\s*center\s*;/.test( match[1] ) );
  const mobileCascadeBlockMatch = source.match( /@media\s*\(max-width:\s*850px\)\s*\{([\s\S]*?)\n\}/ );

  assert.ok( chipBlockMatch, 'expected the cascade chip style block to exist' );
  assert.ok( chipLabelBlockMatch, 'expected the cascade chip label style block to exist' );
  assert.ok( chipCodeBlockMatch, 'expected the cascade chip code style block to exist' );
  assert.ok( signalSummaryBlockMatch, 'expected the signal summary style block to exist' );
  assert.ok( signalIconBlockMatch, 'expected the signal icon style block to exist' );
  assert.ok( signalStepBlockMatch, 'expected the signal step style block to exist' );
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
  assert.ok(
    /box-sizing\s*:\s*border-box\s*;/.test( signalSummaryBlockMatch[1] ) && /width\s*:\s*82px\s*;/.test( signalSummaryBlockMatch[1] ),
    'signal summary should have a fixed border-box width across all level labels'
  );
  assert.ok(
    /align-items\s*:\s*center\s*;/.test( signalSummaryBlockMatch[1] ),
    'signal summary should center the icon against the level/value stack'
  );
  assert.ok(
    /align-self\s*:\s*center\s*;/.test( signalIconBlockMatch[1] ),
    'signal bars icon should be vertically centered inside the summary control'
  );
  assert.ok(
    /width\s*:\s*40px\s*;/.test( signalStepBlockMatch[1] ) && /height\s*:\s*40px\s*;/.test( signalStepBlockMatch[1] ),
    'signal step controls should be square buttons'
  );
};
