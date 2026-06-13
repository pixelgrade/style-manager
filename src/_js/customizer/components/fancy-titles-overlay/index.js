import React, { useState } from 'react';

import { Overlay } from '../index';
import useCustomizeSettingCallback from '../../hooks/use-customize-setting-callback';

import './style.scss';

/**
 * The Fancy Titles board. Fancy Titles auto-styles headings: simple
 * punctuation rules emphasize parts of each title (no manual formatting).
 * The frontend does the real work with PHP regex (anima/inc/title-styles.php);
 * the board previews the *full rule set* (principle 1) as fixed illustrative
 * specimens in the live heading font, so there is nothing to port and no
 * markup coupling. It reflects the on/off toggle.
 */

const getSettingValue = ( settingID, fallback ) => {
  if ( ! window.wp?.customize ) {
    return fallback;
  }

  const setting = window.wp.customize( settingID );
  return setting ? setting() : fallback;
};

// Anima stores the toggle as a boolean and migrates the legacy `hive` /
// underline / blocky values; treat the truthy spellings as "on".
const isEnabled = raw => true === raw || 1 === raw || '1' === raw || 'on' === raw || 'hive' === raw;

const FancyTitlesOverlay = props => {
  const { show } = props;

  return (
    <Overlay show={ show }>
      <FancyTitlesPreview key={ 'overlay_fancy_titles_preview' } />
    </Overlay>
  );
};

const FancyTitlesPreview = () => {
  const { __ } = wp.i18n;

  const [ enabled, setEnabled ] = useState(
    () => isEnabled( getSettingValue( 'sm_decorative_titles_style', false ) )
  );

  useCustomizeSettingCallback( 'sm_decorative_titles_style', newValue => setEnabled( isEnabled( newValue ) ) );

  // Each rule cluster is shown both ways: `plain` is the heading as it renders
  // with Fancy Titles OFF; `styled` is the same text with the emphasis the
  // frontend regex would produce. The board renders the one that matches the
  // live toggle, so flipping Enabled is a true before/after.
  const rules = [
    {
      plain: 'I’m UPPERCASE and bold!',
      styled: <>I&rsquo;m <b>UPPERCASE</b> and <b>bold!</b></>,
      caption: __( 'UPPERCASE words, and words ending in “!”, become bold.', '__plugin_txtd' ),
    },
    {
      plain: '“Quotes” or (parentheses) are italicized',
      styled: <><i>&ldquo;Quotes&rdquo;</i> or <i>(parentheses)</i> are italicized</>,
      caption: __( 'Anything inside quotes or parentheses becomes italic.', '__plugin_txtd' ),
    },
    {
      plain: 'Before Colon: is always bold',
      styled: <><b>Before Colon:</b> is always bold</>,
      caption: __( 'Everything before a colon becomes bold.', '__plugin_txtd' ),
    },
    {
      plain: 'Is this a Question Mark? Yes, my final answer!',
      styled: <><b>Is this a Question Mark?</b> <i>Yes, my final <b>answer!</b></i></>,
      caption: __( 'In a question, the part before “?” is bold and the answer is italic.', '__plugin_txtd' ),
    },
  ];

  return (
    <div className={ `sm-fancy-titles-preview ${ enabled ? 'is-on' : 'is-off' }` }>
      <div className="sm-fancy-titles-preview__header">
        <h1>{ __( 'Fancy Titles', '__plugin_txtd' ) }</h1>
        <p>
          { enabled
            ? __( 'Fancy Titles is on — your headings are auto-styled by simple punctuation rules, with no manual formatting. Here is what each rule does, in your heading font.', '__plugin_txtd' )
            : __( 'Fancy Titles is off — your headings render plain, exactly as shown below. Turn it on and watch the same titles pick up emphasis from their punctuation.', '__plugin_txtd' ) }
        </p>
      </div>

      <div className="sm-fancy-titles-preview__specimens">
        { rules.map( ( rule, index ) => (
          <div className="sm-fancy-titles-preview__row" key={ index }>
            <p className="sm-fancy-titles-preview__title">{ enabled ? rule.styled : rule.plain }</p>
            <p className="sm-fancy-titles-preview__caption">{ rule.caption }</p>
          </div>
        ) ) }
      </div>

      { ! enabled && (
        <div className="sm-fancy-titles-preview__note">
          { __( 'Enable Fancy Titles to apply this to your post titles.', '__plugin_txtd' ) }
        </div>
      ) }
    </div>
  );
};

export default FancyTitlesOverlay;
