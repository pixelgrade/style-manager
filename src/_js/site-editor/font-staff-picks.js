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
  // Display faces with enough personality and structure to behave like a
  // wordmark. The list deliberately mixes Pixelgrade Cloud families with
  // broadly available Google families; the picker filters it against the
  // catalog enabled on the current site.
  wordmarks: [
    'Reforma1969',
    'Faune',
    'Sporting Grotesque',
    'Butler Stencil',
    'Psychedelic Cowboy',
    'FORTA',
    'Salome',
    'Mondia',
    'Restora',
    'Basteleur',
    'Cabinet Grotesk',
    'Murmure',
    'Prata',
    'Cormorant',
    'Young Serif',
    'Syne',
    'MuseoModerno',
    'Jaro',
    'League Gothic',
    'Big Shoulders',
    'Playfair Display',
    'DM Serif Display',
    'Fraunces',
    'Bodoni Moda',
    'Cinzel',
  ],
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

/**
 * Role-tagged fonts from the sheet's "Theme Font References" tab — ~70
 * families audited from Automattic / WordPress.com Premium themes, mapped by
 * their "Used as" roles (heading levels and post titles fold into `headings`,
 * meta/utility roles fold into `body`, Handwriting category into
 * `handwriting`). Names follow the sheet's own "Related:" normalizations
 * ("Playwrite US" → Playwrite US Modern, "Golos UI" → Golos Text, "Bitter
 * Pro" → Bitter); families the catalog lacks are filtered out at runtime, so
 * non-Google foundry fonts appear as soon as the cloud picks them up.
 */
const THEME_FONT_REFERENCES = [
  { family: 'Playwrite US Modern', roles: [ 'handwriting', 'body', 'headings' ] },
  { family: 'Basteleur', roles: [ 'body', 'headings' ] },
  { family: 'Gabarito', roles: [ 'body', 'headings' ] },
  { family: 'Leluja', roles: [ 'body', 'headings' ] },
  { family: 'Special Elite', roles: [ 'body', 'headings' ] },
  { family: 'Utara', roles: [ 'headings' ] },
  { family: 'PlymouthPress', roles: [ 'headings' ] },
  { family: 'Psychedelic Cowboy', roles: [ 'headings' ] },
  { family: 'FORTA', roles: [ 'headings' ] },
  { family: 'Antique Série', roles: [ 'headings' ] },
  { family: 'Bitter', roles: [ 'body' ] },
  { family: 'Crimson Pro', roles: [ 'body', 'headings' ] },
  { family: 'Gabriela', roles: [ 'headings' ] },
  { family: 'Gentium Plus', roles: [ 'body', 'headings' ] },
  { family: 'Literata', roles: [ 'body', 'headings' ] },
  { family: 'Lucette', roles: [ 'headings' ] },
  { family: 'Newsreader', roles: [ 'body', 'headings' ] },
  { family: 'Pitagon Serif', roles: [ 'body' ] },
  { family: 'Playfair', roles: [ 'body', 'headings' ] },
  { family: 'Spectral', roles: [ 'body', 'headings' ] },
  { family: 'Formera', roles: [ 'headings' ] },
  { family: 'Google Sans Display', roles: [ 'headings' ] },
  { family: 'Inter Display', roles: [ 'headings' ] },
  { family: 'Rena', roles: [ 'headings' ] },
  { family: 'D-DIN Condensed', roles: [ 'headings' ] },
  { family: 'Routed Gothic Narrow', roles: [ 'headings' ] },
  { family: 'Routed Gothic Wide', roles: [ 'headings' ] },
  { family: 'Open Runde', roles: [ 'headings' ] },
  { family: 'Routed Gothic', roles: [ 'body' ] },
  { family: 'Albert Sans', roles: [ 'body', 'headings' ] },
  { family: 'Apfel Grotezk', roles: [ 'body' ] },
  { family: 'Archivo', roles: [ 'headings' ] },
  { family: 'Barlow', roles: [ 'body', 'headings' ] },
  { family: 'Barlow Condensed', roles: [ 'headings' ] },
  { family: 'Barlow Semi Condensed', roles: [ 'headings' ] },
  { family: 'Bricolage Grotesque', roles: [ 'body', 'headings' ] },
  { family: 'Chakra Petch', roles: [ 'body' ] },
  { family: 'Chivo', roles: [ 'headings' ] },
  { family: 'Faculty Glyphic', roles: [ 'headings' ] },
  { family: 'Figtree', roles: [ 'body' ] },
  { family: 'Funnel Sans', roles: [ 'body' ] },
  { family: 'Golos Text', roles: [ 'body' ] },
  { family: 'Google Sans Text', roles: [ 'body' ] },
  { family: 'Hanken Grotesk', roles: [ 'body' ] },
  { family: 'Haskoy', roles: [ 'body', 'headings' ] },
  { family: 'Hezaedrus', roles: [ 'body', 'headings' ] },
  { family: 'Inter', roles: [ 'body', 'headings' ] },
  { family: 'Jaro', roles: [ 'headings' ] },
  { family: 'Jost', roles: [ 'body', 'headings' ] },
  { family: 'Manrope', roles: [ 'body', 'headings' ] },
  { family: 'Metropolis', roles: [ 'body', 'headings' ] },
  { family: 'Neutral Sans', roles: [ 'body', 'headings' ] },
  { family: 'Nunito', roles: [ 'body', 'headings' ] },
  { family: 'Outfit', roles: [ 'headings' ] },
  { family: 'Overused Grotesk', roles: [ 'body' ] },
  { family: 'Plus Jakarta Sans', roles: [ 'body', 'headings' ] },
  { family: 'PT Root UI', roles: [ 'body' ] },
  { family: 'Public Sans', roles: [ 'body', 'headings' ] },
  { family: 'SUSE Mono', roles: [ 'body', 'headings' ] },
  { family: 'TeX Gyre Adventor', roles: [ 'headings' ] },
  { family: 'Uncut Sans', roles: [ 'body', 'headings' ] },
  { family: 'URW Gothic', roles: [ 'body' ] },
  { family: 'Zalando Sans', roles: [ 'body' ] },
  { family: 'Chivo Mono', roles: [ 'body' ] },
  { family: 'Cutive Mono', roles: [ 'body' ] },
  { family: 'DM Mono', roles: [ 'body' ] },
  { family: 'Martian Mono', roles: [ 'body' ] },
  { family: 'Amiamie', roles: [ 'body', 'headings' ] },
  { family: 'St. Martin', roles: [ 'headings' ] },
];

const COLLECTION_ORDER = [ 'wordmarks', 'headings', 'body', 'handwriting' ];

export const getStaffPicksCollections = () => {
  const { __ } = wp.i18n;
  const labels = {
    wordmarks: __( 'Staff Picks · Wordmarks', '__plugin_txtd' ),
    headings: __( 'Staff Picks · Headings', '__plugin_txtd' ),
    body: __( 'Staff Picks · Body', '__plugin_txtd' ),
    handwriting: __( 'Staff Picks · Handwriting', '__plugin_txtd' ),
  };

  // A cloud/server payload replaces the bundled data wholesale.
  const override = window.styleManager?.fonts?.staff_picks;

  const lists = {};
  COLLECTION_ORDER.forEach( key => {
    if ( override ) {
      lists[ key ] = Array.isArray( override[ key ] ) ? override[ key ] : [];
      return;
    }

    // Curated picks first, then the theme-reference fonts for the same role.
    const families = [ ...( BUNDLED_STAFF_PICKS[ key ] || [] ) ];
    THEME_FONT_REFERENCES.forEach( reference => {
      if ( reference.roles.includes( key ) && ! families.includes( reference.family ) ) {
        families.push( reference.family );
      }
    } );
    lists[ key ] = families;
  } );

  return COLLECTION_ORDER
    .filter( key => Array.isArray( lists[ key ] ) && lists[ key ].length )
    .map( key => ( {
      key,
      label: labels[ key ] || key,
      families: lists[ key ],
    } ) );
};
