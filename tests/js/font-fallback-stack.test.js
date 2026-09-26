import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

import { resolveFontFallbackStack, NEUTRAL_FALLBACK_STACK } from '../../src/_js/utils/resolve-font-fallback-stack.js';

// style-manager#219: each family gets the fallback stack of its own category.
// The same cases drive the PHP resolver (tests/phpunit/Unit/FontFallbackStackTest.php).
const fixture = JSON.parse( readFileSync( new URL( './support/font-fallback-stack-cases.json', import.meta.url ), 'utf8' ) );

test( 'the neutral stack matches the shared fixture', () => {
  assert.equal( NEUTRAL_FALLBACK_STACK, fixture.neutral );
} );

for ( const c of fixture.cases ) {
  test( c.name, () => {
    const categories = c.categories ?? fixture.categories;
    assert.equal( resolveFontFallbackStack( c.details, categories, c.catalogCategory || '' ), c.expected );
  } );
}
