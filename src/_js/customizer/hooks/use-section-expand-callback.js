import { useEffect } from 'react';

const useSectionExpandCallback = ( sectionID, callback ) => {

  return useEffect( () => {
    wp.customize.section( sectionID, section => {
      section.expanded.bind( callback );
    } );

    return () => {
      wp.customize.section( sectionID, section => {
        section.expanded.unbind( callback );
      } );
    }

  }, [] );
};

export default useSectionExpandCallback;
