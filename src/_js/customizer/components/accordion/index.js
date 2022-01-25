import './style.scss';
import React, { useCallback, useEffect, useState } from 'react';
import { useSectionExpandCallback } from '../../hooks';

export const Accordion = ( props ) => {
  const sections = React.Children.toArray( props.children ).filter( child => child.type === AccordionSection );
  const open = sections.findIndex( section => !! section?.props?.open );
  const [ active, setActive ] = useState( open );

  // hide children when leaving panel to avoid useless re-renders
  const callback = useCallback( isExpanded => {
    if ( ! isExpanded ) {
      setActive( null );
    }
  }, [] );

  useSectionExpandCallback( 'sm_color_palettes_section', callback );

  return sections.map( ( section, index ) => {
    const { title, children } = section.props;
    const isOpen = active === index;

    return (
      <div className={ `sm-blinds sm-blinds--${ isOpen ? 'open' : 'closed' }` } key={ index }>
        <div className="sm-blinds__header" onClick={ () => {
          setActive( active !== index ? index : null );
        } }>
          <div className="sm-blinds__title">{ title }</div>
          <div className="sm-blinds__toggle" />
        </div>
        <div className="sm-blinds__body">
          { isOpen && children }
        </div>
      </div>
    )
  } )
};

export const AccordionSection = ( props ) => {
  return null;
};
