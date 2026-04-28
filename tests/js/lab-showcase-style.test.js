const LAB_SHOWCASE_SCSS_PATH = new URL( '../../src/_js/lab/showcase.scss', import.meta.url );

export const runLabShowcaseStyleTests = async ( assert ) => {
  const { readFile } = await import( 'node:fs/promises' );
  const source = await readFile( LAB_SHOWCASE_SCSS_PATH, 'utf8' );
  const chipBlockMatch = source.match( /\.sm-lab-cascade-chip\s*\{([\s\S]*?)\n\}/ );
  const chipsBlockMatch = source.match( /\.sm-lab-cascade-chips\s*\{([\s\S]*?)\n\}/ );
  const chipLabelBlockMatch = source.match( /\.sm-lab-cascade-chip > span\s*\{([\s\S]*?)\n\}/ );
  const chipCodeBlockMatch = source.match( /\.sm-lab-cascade-chip code\s*\{([\s\S]*?)\n\}/ );
  const cascadeNodeBlockMatch = source.match( /\.sm-lab-signal-cascade__node\s*\{([\s\S]*?)\n\}/ );
  const cascadeSignalBlockMatch = source.match( /\.sm-lab-signal-cascade__signal\s*\{([\s\S]*?)\n\}/ );
  const cascadeNodeStrongBlockMatch = source.match( /\.sm-lab-signal-cascade__node strong\s*\{([\s\S]*?)\n\}/ );
  const cascadeNodeSmallBlockMatch = source.match( /\.sm-lab-signal-cascade__node small\s*\{([\s\S]*?)\n\}/ );
  const cascadeRailBlockMatch = source.match( /\.sm-lab-cascade-rail\s*\{([\s\S]*?)\n\}/ );
  const cascadeRailMarkerBlockMatch = source.match( /\.sm-lab-cascade-rail__segment\[data-sm-lab-cascade-rail-marker="parent"\]::after,\n\.sm-lab-cascade-rail__segment\[data-sm-lab-cascade-rail-marker="resolved"\]::after\s*\{([\s\S]*?)\n\}/ );
  const cascadeRailResolvedMarkerBlockMatch = Array.from( source.matchAll( /\.sm-lab-cascade-rail__segment\[data-sm-lab-cascade-rail-marker="resolved"\]::after\s*\{([\s\S]*?)\n\}/g ) )
    .find( ( match ) => /background\s*:\s*var\(--sm-lab-cascade-dot-color\)\s*;/.test( match[1] ) );
  const signalSummaryBlockMatch = source.match( /\.sm-lab-signal-cascade__signal-summary\s*\{([\s\S]*?)\n\}/ );
  const signalIconBlockMatch = source.match( /\.sm-lab-signal-cascade__signal-summary \.sm-lab-signal-bars__icon\s*\{([\s\S]*?)\n\}/ );
  const signalStepBlockMatch = Array.from( source.matchAll( /\.sm-lab-signal-cascade__signal-step\s*\{([\s\S]*?)\n\}/g ) )
    .find( ( match ) => /place-items\s*:\s*center\s*;/.test( match[1] ) );
  const mobileCascadeBlockMatch = source.match( /@media\s*\(max-width:\s*850px\)\s*\{([\s\S]*?)\n\}/ );

  assert.ok( chipBlockMatch, 'expected the cascade chip style block to exist' );
  assert.ok( chipsBlockMatch, 'expected the cascade chips style block to exist' );
  assert.ok( chipLabelBlockMatch, 'expected the cascade chip label style block to exist' );
  assert.ok( chipCodeBlockMatch, 'expected the cascade chip code style block to exist' );
  assert.ok( cascadeNodeBlockMatch, 'expected the cascade node style block to exist' );
  assert.ok( cascadeSignalBlockMatch, 'expected the cascade signal style block to exist' );
  assert.ok( cascadeNodeStrongBlockMatch, 'expected the cascade title style block to exist' );
  assert.ok( cascadeNodeSmallBlockMatch, 'expected the cascade description style block to exist' );
  assert.ok( cascadeRailBlockMatch, 'expected the cascade rail style block to exist' );
  assert.ok( cascadeRailMarkerBlockMatch, 'expected the cascade rail marker style block to exist' );
  assert.ok( cascadeRailResolvedMarkerBlockMatch, 'expected the cascade resolved marker style block to exist' );
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
  assert.ok(
    /grid-template-columns\s*:\s*auto\s+minmax\(0,\s*1fr\)\s*;/.test( cascadeNodeBlockMatch[1] ),
    'cascade nodes should put the signal selector inline beside the node content'
  );
  assert.ok(
    /grid-row\s*:\s*1\s*\/\s*span\s*3\s*;/.test( cascadeSignalBlockMatch[1] ),
    'cascade signal selector should occupy the left column beside title, description, and chips'
  );
  assert.ok(
    /grid-column\s*:\s*2\s*;/.test( cascadeNodeStrongBlockMatch[1] ) && /grid-column\s*:\s*2\s*;/.test( cascadeNodeSmallBlockMatch[1] ),
    'cascade title and description should sit in the content column'
  );
  assert.ok(
    /grid-column\s*:\s*2\s*;/.test( chipsBlockMatch[1] ),
    'cascade chips should sit in the content column beside the signal selector'
  );
  assert.ok(
    /grid-column\s*:\s*1\s*\/\s*-1\s*;/.test( cascadeRailBlockMatch[1] ),
    'cascade rail should span the full node width below the header'
  );
  assert.ok(
    /border\s*:\s*1\.5px\s+solid\s+var\(--sm-lab-cascade-dot-color\)\s*;/.test( cascadeRailMarkerBlockMatch[1] ),
    'cascade rail marker border should use the High signal dot color'
  );
  assert.ok(
    /background\s*:\s*var\(--sm-lab-cascade-dot-color\)\s*;/.test( cascadeRailResolvedMarkerBlockMatch[1] ),
    'resolved cascade rail marker should fill with the High signal dot color'
  );
};
