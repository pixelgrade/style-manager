import { useCallback, useEffect } from 'react';
import { getBackArray, pushToBackArray } from "../../global-service";
import usePopFromBackArray from "../../hooks/use-pop-from-back-array";

const CustomizerShortcut = ( props ) => {

  const {
    targetSectionID,
    currentSectionID,
    icon,
    label
  } = props;

  const onClick = useCallback( () => {

    if ( ! currentSectionID ) {
      return;
    }

    wp.customize.section( targetSectionID, ( section ) => {
      pushToBackArray( section, currentSectionID );
    } );

  }, [ currentSectionID ] );

  usePopFromBackArray( targetSectionID );

  return (
    <div className="sm-group">
      <div className="sm-panel-toggle" onClick={ onClick }>
        { icon && <div className="sm-panel-toggle__icon" dangerouslySetInnerHTML={ { __html: icon } } /> }
        { label && <div className="sm-panel-toggle__label">{ label }</div> }
      </div>
    </div>
  )
}

export default CustomizerShortcut;
