/**
 * JS twin of Utils\Fonts::resolveFallbackStack() (src/Utils/Fonts.php) — keep
 * in sync; both run tests/js/support/font-fallback-stack-cases.json.
 *
 * Each family gets the fallback stack of its own category (style-manager#219):
 * - a display font tagged with a classification (e.g. `serif`) gets that
 *   category's stack: display is a use, not a letterform;
 * - otherwise the font's own stack wins, unless it is a bare CSS generic
 *   (`serif`), which the category stack extends;
 * - then the stack of the font's category, or of `catalogCategory` (the Google
 *   Fonts category of the same family) when the font has none;
 * - a known category missing from the list falls back to its CSS generic;
 * - anything else gets the neutral stack.
 */
export const NEUTRAL_FALLBACK_STACK = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

const CATEGORY_ALIASES = {
  sans: 'sans-serif',
  'sans serif': 'sans-serif',
  'system-ui': 'sans-serif',
  mono: 'monospace',
  monospaced: 'monospace',
  script: 'handwriting',
  cursive: 'handwriting',
};

const CATEGORY_GENERICS = {
  serif: 'serif',
  'sans-serif': 'sans-serif',
  monospace: 'monospace',
  handwriting: 'cursive',
};

const CLASSIFICATION_TAGS = {
  serif: 'serif',
  sans: 'sans-serif',
  'sans-serif': 'sans-serif',
  mono: 'monospace',
  monospace: 'monospace',
  script: 'handwriting',
  handwriting: 'handwriting',
};

const KNOWN_CATEGORIES = [ 'serif', 'sans-serif', 'display', 'monospace', 'handwriting' ];
const BARE_GENERICS = [ 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui' ];

const toList = value => {
  if ( Array.isArray( value ) ) {
    return value;
  }
  if ( 'string' === typeof value ) {
    return value.split( ',' );
  }
  return [];
};

const has = ( object, key ) => Object.prototype.hasOwnProperty.call( object, key );

export const fallbackCategoryKey = ( category, categories = {} ) => {
  const name = 'string' === typeof category ? category.trim().toLowerCase() : '';
  if ( '' === name ) {
    return '';
  }

  const ids = Object.keys( categories || {} );
  const byKey = ids.find( id => id.toLowerCase() === name );
  if ( undefined !== byKey ) {
    return byKey;
  }

  const byAlias = ids.find( id => toList( categories[ id ]?.aliases ).some( alias => String( alias ).trim().toLowerCase() === name ) );
  if ( undefined !== byAlias ) {
    return byAlias;
  }

  if ( has( CATEGORY_ALIASES, name ) ) {
    return CATEGORY_ALIASES[ name ];
  }

  return KNOWN_CATEGORIES.includes( name ) ? name : '';
};

const tagCategory = tags => {
  if ( ! Array.isArray( tags ) ) {
    return '';
  }

  for ( const tag of tags ) {
    const raw = tag && 'object' === typeof tag ? ( tag.slug ?? tag.name ?? '' ) : tag;
    const slug = 'string' === typeof raw ? raw.trim().toLowerCase() : '';
    if ( has( CLASSIFICATION_TAGS, slug ) ) {
      return CLASSIFICATION_TAGS[ slug ];
    }
  }

  return '';
};

const categoryStack = ( category, categories = {} ) => {
  const stack = categories?.[ category ]?.fallback_stack;
  if ( 'string' === typeof stack && '' !== stack.trim() ) {
    return stack.trim();
  }

  return CATEGORY_GENERICS[ category ] || '';
};

export const resolveFontFallbackStack = ( details, categories = {}, catalogCategory = '' ) => {
  if ( ! details || 'object' !== typeof details || ! Object.keys( details ).length ) {
    return '';
  }

  const own = 'string' === typeof details.fallback_stack ? details.fallback_stack.trim() : '';
  const declared = fallbackCategoryKey( details.category, categories );

  const tagged = tagCategory( details.tags );
  if ( '' !== tagged && tagged !== declared && ( '' === declared || 'display' === declared ) ) {
    const stack = categoryStack( tagged, categories );
    if ( '' !== stack ) {
      return stack;
    }
  }

  if ( '' !== own && ! BARE_GENERICS.includes( own.toLowerCase() ) ) {
    return own;
  }

  const category = '' !== declared ? declared : fallbackCategoryKey( catalogCategory, categories );
  const stack = '' !== category ? categoryStack( category, categories ) : '';
  if ( '' !== stack ) {
    return stack;
  }

  return '' !== own ? own : NEUTRAL_FALLBACK_STACK;
};
