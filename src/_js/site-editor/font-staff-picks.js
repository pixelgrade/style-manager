/**
 * Staff Picks — curated font collections surfaced by the native font picker.
 *
 * Source of truth: the internal "Staff Picks" fonts sheet (Category column
 * flagged TRUE). Family names are normalized to the catalog families the
 * plugin actually ships (e.g. the sheet's "Comforta" is Google's "Comfortaa",
 * "Besley*" is "Besley", "Museo Moderno" is "MuseoModerno", and Google renamed
 * "Big Shoulders Display" to "Big Shoulders"). Cloud-hosted picks (Pixelgrade
 * CDN) are listed too — the picker filters every collection down to families
 * present in the site's styleManager.fonts catalog, so entries a site doesn't
 * have simply don't show.
 *
 * A server/cloud payload can override the bundled lists by localizing
 * `styleManager.fonts.staff_picks` with the same { key: [families] } shape.
 */

const BUNDLED_STAFF_PICKS = {
  headings: [
    'Cooper Hewitt',
    'League Spartan',
    'DM Sans',
    'Reforma1969',
    'Prata',
    'Cormorant',
    'Neuton',
    'Young Serif',
    'Eczar',
    'Comfortaa',
    'Faune',
    'Sporting Grotesque',
    'Butler Stencil',
    'Salome',
    'Syne',
    'League Gothic',
    'Big Shoulders',
    'Inknut Antiqua',
    'Montserrat',
    'Playfair Display',
    'DM Serif Display',
    'Arvo',
    'EB Garamond',
    'Fraunces',
    'Mondia',
    'Restora',
    'Literata',
    'Cabinet Grotesk',
    'Murmure',
    'Georama',
    'Besley',
    'Montagu Slab',
    'MuseoModerno',
    'Manrope',
  ],
  body: [
    'Reforma1969',
    'Quattrocento Sans',
    'Crimson Text',
    'HK Grotesk',
    'Poppins',
    'IBM Plex Mono',
    'iA Writer Quattro',
    'IBM Plex Sans',
    'Alegreya',
    'Lora',
    'EB Garamond',
    'DM Sans',
    'Gothic A1',
    'Space Grotesk',
  ],
  handwriting: [
    'Billy Ohio',
    'Jandys',
    'Nermola Script',
    'Mellony Dry Brush',
    'Quentin',
    'Prestige Signature Script',
    'Northwell',
    'Mrs Saint Delafield',
    'Hurricane',
  ],
};

const COLLECTION_ORDER = [ 'headings', 'body', 'handwriting' ];

export const getStaffPicksCollections = () => {
  const { __ } = wp.i18n;
  const labels = {
    headings: __( 'Staff Picks · Headings', '__plugin_txtd' ),
    body: __( 'Staff Picks · Body', '__plugin_txtd' ),
    handwriting: __( 'Staff Picks · Handwriting', '__plugin_txtd' ),
  };

  const lists = window.styleManager?.fonts?.staff_picks || BUNDLED_STAFF_PICKS;

  return COLLECTION_ORDER
    .filter( key => Array.isArray( lists[ key ] ) && lists[ key ].length )
    .map( key => ( {
      key,
      label: labels[ key ] || key,
      families: lists[ key ],
    } ) );
};
