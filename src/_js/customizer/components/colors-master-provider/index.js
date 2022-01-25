import { ConfigProvider } from "../config-context";
import { OptionsProvider } from "../options-context";
import { PalettesProvider } from "../palettes-context";

const ColorsMasterProvider = ( props ) => {

  return (
    <ConfigProvider { ...props }>
      <OptionsProvider>
        <PalettesProvider>
          { props.children }
        </PalettesProvider>
      </OptionsProvider>
    </ConfigProvider>
  )
};

export default ColorsMasterProvider;
