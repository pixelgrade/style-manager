import { Fragment } from "react";
import { Overlay } from "../index";
import './style.scss';

const elements = [ {
  category: 'primary',
  children: 'Display Heading',
  size: 20,
}, {
  category: 'primary',
  children: 'Main Heading One',
  size: 20,
}, {
  category: 'primary',
  children: 'Secondary Heading',
  size: 20,
}, {
  category: 'primary',
  children: 'Heading Three',
  size: 20,
}, {
  category: 'secondary',
  children: 'Heading Four',
  size: 20,
}, {
  category: 'secondary',
  children: 'Heading Five & Six',
  size: 20,
}, {
  category: 'secondary',
  children: 'Primary Button',
  size: 20,
}, {
  category: 'body',
  children: 'Opening paragraphs often deserve some form of decorative type treatment to help draw the reader in. These special type treatments serve to mark a clear beginning to an article.',
  size: 20,
}, {
  category: 'body',
  children: (
    <Fragment>
      <p>Paragraphs only need enough space below them to let the reader know they are starting on a new paragraph. Any more space than that is distracting and breaks up the flow of reading. White space is important, but you don’t want huge gaps all down your page.</p>
      <p>Typography is more than just what fonts you use. Typography is everything that has to do with how the text looks—such as font size, line length, color, and even more subtle things like the whitespace around a text. Good typography sets the tone of your written message and helps to reinforce its meaning and context.</p>
    </Fragment>
  ),
  size: 20,
}, {
  category: 'accent',
  children: (
    <p>Discover our story</p>
  ),
} ]

const Cell = ( props ) => {
  const { isHead, name, children } = props;
  const classNameBase = 'sm-typography-preview__cell';
  const classNames = [
    classNameBase,
    `${ classNameBase }--${ name }`,
  ]

  if ( isHead ) {
    classNames.push( `${ classNameBase }--head` );
  }

  return (
    <div className={ classNames.join( '  ' ) }>{ children }</div>
  )
}

const TypographyPreview = ( props ) => {
  const { show } = props;
  return (
    <Overlay show={ show }>
      <div className="sm-typography-preview">
        <Cell name="category" isHead>
          { styleManager.l10n.colorPalettes.typographyPreviewHeadCategoryLabel }
        </Cell>
        <Cell name="preview" isHead>
          { styleManager.l10n.colorPalettes.typographyPreviewHeadPreviewLabel }
        </Cell>
        <Cell name="size" isHead>
          { styleManager.l10n.colorPalettes.typographyPreviewHeadSizeLabel }
        </Cell>
        { elements.map( ( element, index ) => {
          const classNameBase = 'sm-typography-preview__separator';
          const classNames = [ classNameBase ];

          if ( index === 0 ) {
            classNames.push( `${ classNameBase }--head` );
          }

          return (
            <Fragment>
              <div className={ classNames.join( '  ' ) } />
              <Element { ...element } />
            </Fragment>
          )
        } ) }
      </div>
    </Overlay>
  )
}

const Element = ( props ) => {
  const { category, children, size } = props;

  return (
    <Fragment>
      <Cell name="category">
        <Category id={ category } />
      </Cell>
      <Cell name="preview">
        { children }
      </Cell>
      <Cell name="size">
        { size }
      </Cell>
    </Fragment>
  )
}

const Category = ( props ) => {
  const { id } = props;

  const categories = [ {
    id: 'primary',
    label: styleManager.l10n.colorPalettes.typographyPreviewPrimaryShortLabel,
  }, {
    id: 'secondary',
    label: styleManager.l10n.colorPalettes.typographyPreviewSecondaryShortLabel,
  }, {
    id: 'body',
    label: styleManager.l10n.colorPalettes.typographyPreviewBodyShortLabel,
  }, {
    id: 'accent',
    label: styleManager.l10n.colorPalettes.typographyPreviewAccentShortLabel,
  } ];

  const current = categories.find( category => category.id === id );

  if ( ! current ) {
    return null;
  }

  return (
    <span className={ id }>{ current.label }</span>
  )
}

export default TypographyPreview;
