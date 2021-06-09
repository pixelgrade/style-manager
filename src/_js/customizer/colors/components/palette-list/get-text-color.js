const getTextColor = ( palette ) => {
  const { lightColorsCount, sourceIndex, textColors } = palette;
  return sourceIndex > lightColorsCount ? '#FFFFFF' : textColors[0].value;
}

export default getTextColor;
