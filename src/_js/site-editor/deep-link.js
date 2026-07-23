export const SECTION_PREVIEW_MODES = {
  sm_color_palettes_section: { mode: 'colors' },
  sm_font_palettes_section: { mode: 'typography' },
  // The Layout section owns the page-anatomy board (internal overlay key
  // 'spacing', kept for continuity — it now covers container/inset/rails/rhythm).
  sm_layout_section: { mode: 'spacing' },
  sm_motion_section: { mode: 'site', context: 'motion' },
};

// The Spacing section merged into Layout (2026-07-23). Keep old deep links
// (e.g. Pixelgrade Assistant's Spacing shortcut, bookmarks) resolving to the
// new section so focus + preview still work.
export const SECTION_ALIASES = {
  sm_spacing_section: 'sm_layout_section',
};

const isPreviewRequested = value => [ '1', 'true', 'yes', 'open' ].includes( String( value || '' ).toLowerCase() );

export const parseSiteEditorDeepLink = search => {
  const params = search instanceof URLSearchParams
    ? search
    : new URLSearchParams( search || '' );
  const requestedSection = params.get( 'sm-section' ) || '';
  const targetSection = SECTION_ALIASES[ requestedSection ] || requestedSection;
  const shouldOpenSidebar = !! params.get( 'sm-sidebar' ) || !! targetSection;
  const previewEntry = targetSection && isPreviewRequested( params.get( 'sm-preview' ) )
    ? ( SECTION_PREVIEW_MODES[ targetSection ] || null )
    : null;

  return {
    shouldOpenSidebar,
    targetSection,
    previewEntry,
  };
};
