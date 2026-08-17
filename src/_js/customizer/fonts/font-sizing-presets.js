const FONT_SIZING_PRESETS = Object.freeze( {
  smallest: {
    sm_font_primary: [ 0, 34 ],
    sm_font_secondary: [ 5, 30 ],
    sm_font_body: [ 0, 10 ],
  },
  smaller: {
    sm_font_primary: [ 6, 40 ],
    sm_font_secondary: [ 16, 16 ],
    sm_font_body: [ 0, 45 ],
  },
  normal: {
    sm_font_primary: [ 0, 100 ],
    sm_font_secondary: [ 0, 100 ],
    sm_font_body: [ 0, 100 ],
  },
  larger: {
    sm_font_primary: [ 12, 100 ],
    sm_font_secondary: [ 20, 30 ],
    sm_font_body: [ 50, 30 ],
  },
  largest: {
    sm_font_primary: [ 18, 100 ],
    sm_font_secondary: [ 20, 45 ],
    sm_font_body: [ 70, 30 ],
  },
} );

export const getFontSizingPresetConfig = preset => FONT_SIZING_PRESETS[ preset ] || null;
