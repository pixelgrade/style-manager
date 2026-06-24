export const SECTION_PREVIEW_MODES = {
  sm_color_palettes_section: { mode: 'colors' },
  sm_font_palettes_section: { mode: 'typography' },
  sm_spacing_section: { mode: 'spacing' },
  sm_motion_section: { mode: 'site', context: 'motion' },
};

const isPreviewRequested = value => [ '1', 'true', 'yes', 'open' ].includes( String( value || '' ).toLowerCase() );

export const parseSiteEditorDeepLink = search => {
  const params = search instanceof URLSearchParams
    ? search
    : new URLSearchParams( search || '' );
  const targetSection = params.get( 'sm-section' ) || '';
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
