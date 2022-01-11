import { createContext, useContext, useMemo } from 'react';

import { ConfigContext, OptionsContext } from "../../components";
import { getPalettesFromColors } from "../../utils";

const PalettesContext = createContext();

export const PalettesProvider = ( props ) => {
  const options = useContext( OptionsContext );
  const { config } = useContext( ConfigContext );
  const palettes = useMemo( () => {
    return getPalettesFromColors( config, options );
  }, [ config, options ] );

  return (
    <PalettesContext.Provider value={ palettes }>
      { props.children }
    </PalettesContext.Provider>
  )
}

export default PalettesContext;
