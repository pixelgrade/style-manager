/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { fetchLabConfig } from './admin-api.js';
import { postShowcaseState, subscribeToIframeReadback } from './iframe-bridge.js';
import { emptyReadbackColors, normalizeRuntimeReadback } from './runtime-readback.js';
import { buildAdminUrl, buildShowcaseUrl, normalizeLabState } from './state.js';
import { PalettePanel } from './sidebar/PalettePanel.jsx';
import { ContextualPanel } from './sidebar/ContextualPanel.jsx';
import { ColorSignalPanel } from './sidebar/ColorSignalPanel.jsx';
import { RuntimeInspectorPanel } from './sidebar/RuntimeInspectorPanel.jsx';

const paramsToObject = ( search ) => Object.fromEntries( new URLSearchParams( search ).entries() );

const getInitialState = ( config ) => normalizeLabState( {
  ...( config.defaultState || {} ),
  ...paramsToObject( window.location.search ),
} );

const emptyRuntimeReadback = normalizeRuntimeReadback();

export const App = ( { config } ) => {
  const [ labConfig, setLabConfig ] = useState( () => ( {
    ...config,
    palettes: Array.isArray( config.palettes ) ? config.palettes : [],
  } ) );
  const [ state, setState ] = useState( () => getInitialState( config ) );
  const [ runtimeReadback, setRuntimeReadback ] = useState( emptyRuntimeReadback );
  const [ readback, setReadback ] = useState( emptyReadbackColors );
  const [ contextualPalette, setContextualPalette ] = useState( null );
  const [ iframeReady, setIframeReady ] = useState( false );
  const [ isResetting, setIsResetting ] = useState( false );
  const iframeRef = useRef( null );
  const [ iframeUrl ] = useState( () => buildShowcaseUrl(
    config.showcaseBaseUrl || window.location.origin,
    config.showcaseNonce || '',
    getInitialState( config )
  ) );
  const palettes = Array.isArray( labConfig.palettes ) ? labConfig.palettes : [];

  useEffect( () => subscribeToIframeReadback( ( data ) => {
    const nextReadback = normalizeRuntimeReadback( data );

    setIframeReady( true );
    setRuntimeReadback( nextReadback );
    setReadback( nextReadback.colors );
    setContextualPalette( nextReadback.contextualPalette );
  } ), [] );

  useEffect( () => {
    window.history.replaceState( null, '', buildAdminUrl( labConfig.adminUrl || window.location.href, state ) );
  }, [ labConfig.adminUrl, state ] );

  useEffect( () => {
    if ( ! iframeReady || ! iframeRef.current ) {
      return;
    }

    postShowcaseState( iframeRef.current, {
      state,
      siteVariation: labConfig.siteVariation || labConfig.defaultState?.variation || 1,
    } );
  }, [ iframeReady, labConfig.defaultState, labConfig.siteVariation, state ] );

  const updateState = ( patch ) => setState( ( current ) => normalizeLabState( {
    ...current,
    ...patch,
  } ) );

  const resetState = async () => {
    if ( isResetting ) {
      return;
    }

    if ( ! labConfig.ajaxUrl || ! labConfig.ajaxNonce ) {
      setState( normalizeLabState( labConfig.defaultState || {} ) );
      return;
    }

    setIsResetting( true );

    try {
      const nextConfig = await fetchLabConfig( {
        ajaxUrl: labConfig.ajaxUrl,
        nonce: labConfig.ajaxNonce,
      } );

      setLabConfig( {
        ...labConfig,
        ...nextConfig,
        palettes: Array.isArray( nextConfig.palettes ) ? nextConfig.palettes : [],
      } );
      setState( normalizeLabState( nextConfig.defaultState || {} ) );
      setRuntimeReadback( emptyRuntimeReadback );
      setReadback( emptyReadbackColors );
      setContextualPalette( null );
    } catch ( error ) {
      setState( normalizeLabState( labConfig.defaultState || {} ) );
    } finally {
      setIsResetting( false );
    }
  };

  const copyLabUrl = () => {
    if ( window.navigator?.clipboard ) {
      window.navigator.clipboard.writeText( window.location.href );
    }
  };

  return (
    <div className="sm-lab-admin">
      <aside className="sm-lab-admin__sidebar">
        <div className="sm-lab-admin__title">
          <h1>Style Manager Lab</h1>
        </div>
        <PalettePanel palettes={ palettes } state={ state } contextualPalette={ contextualPalette } onChange={ updateState } />
        <ContextualPanel state={ state } readback={ readback } contextualReadout={ runtimeReadback.contextualReadout } contextualPalette={ contextualPalette } onChange={ updateState } />
        <ColorSignalPanel state={ state } onChange={ updateState } />
        <RuntimeInspectorPanel state={ state } readback={ runtimeReadback } />
        <div className="sm-lab-admin__footer">
          <Button variant="secondary" onClick={ resetState } disabled={ isResetting }>
            { isResetting ? 'Refreshing…' : 'Reset to live site state' }
          </Button>
          <Button variant="primary" onClick={ copyLabUrl }>Copy lab URL</Button>
        </div>
      </aside>
      <section className="sm-lab-admin__canvas" aria-label="Style Manager Lab canvas">
        <iframe
          ref={ iframeRef }
          title="Style Manager Lab showcase"
          src={ iframeUrl }
          className="sm-lab-admin__iframe"
        />
      </section>
    </div>
  );
};
