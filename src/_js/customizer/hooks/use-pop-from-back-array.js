import { useEffect } from 'react';
import { popFromBackArray } from "../global-service";

const usePopFromBackArray = ( sectionID ) => {

  useEffect( () => {

    const callback = ( isExpanded ) => {

      if ( ! isExpanded ) {
        popFromBackArray();
      }
    };

    wp.customize.section( sectionID, section => {
      section.expanded.bind( callback );
    } );

    return () => {
      wp.customize.section( sectionID, section => {
        section.expanded.unbind( callback );
      } )
    }
  }, [] );
};

export default usePopFromBackArray;
