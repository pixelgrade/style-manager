export const inPreviewIframe = () => {
  try {
    return window.self !== window.top;
  } catch ( e ) {
    return true;
  }
};
