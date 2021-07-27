const getTextColor = ( palette ) => {
  const { lightColorsCount, sourceIndex, textColors } = palette;
  return sourceIndex > lightColorsCount ? textColors[0].value : textColors[1].value;
}

export default getTextColor;
