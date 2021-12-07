import './style.scss';

import chroma from "chroma-js";
import React, { useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

import uploadIcon from "../../svg/upload.svg";

import { ConfigContext } from '../../components';
import { getPalettesFromColors } from '../../utils';
import { useUpdateSourceSetting } from '../../hooks';

import Worker from "worker-loader!./worker.js";

const canInterpolate = ( color1, color2 ) => {
  const luminance1 = chroma( color1 ).luminance();
  const luminance2 = chroma( color2 ).luminance();

  return Math.abs( luminance1 - luminance2 ) > 0.3;
}

const maybeInterpolateColors = ( colors ) => {

  if ( colors.length >= 3 &&
       canInterpolate( colors[0], colors[1] ) &&
       canInterpolate( colors[0], colors[2] ) &&
       canInterpolate( colors[1], colors[2] ) ) {
    return [ colors ];
  }

  if ( colors.length >= 2 && canInterpolate( colors[0], colors[1] ) ) {
    return [ [ colors[0], colors[1] ], [ colors[2] ] ];
  }

  if ( colors.length >= 3 && canInterpolate( colors[0], colors[2] ) ) {
    return [ [ colors[0], colors[2] ], [ colors[1] ] ];
  }

  if ( colors.length >= 3 && canInterpolate( colors[0], colors[2] ) ) {
    return [ [ colors[0] ], [ colors[1], colors[2] ] ];
  }

  return [ [ colors[0] ], [ colors[1] ], [ colors[2] ] ];
}

const DropZone = ( props ) => {
  const updateSourceSetting = useUpdateSourceSetting();

  const [ files, setFiles ] = useState( null );
  const [ stripes, setStripes ] = useState( [] );

  const imgSourceRef = useRef( null );
  const imgPreviewRef = useRef( null );
  const canvasRef = useRef( null );
  const inputFile = useRef( null );

  const myWorker = useMemo( () => {
    let worker = null;

    try {
      worker = new Worker();
    } catch (e) {

    }

    return worker;
  }, [] );


  if ( ! myWorker ) {
    return null;
  }

  const dragOver = ( e ) => {
    e.preventDefault();
  }

  const dragEnter = ( e ) => {
    e.preventDefault();
  }

  const dragLeave = ( e ) => {
    e.preventDefault();
  }

  const fileDrop = ( e ) => {
    e.preventDefault();
    const files = e.dataTransfer.files;
    setFiles( files );
  }

  const onClick = () => {
    inputFile.current.click();
  }

  const onFileChange = e => {
    setFiles( e.target.files );
  };

  useEffect( () => {

    myWorker.onmessage = function( event ) {

      const order = [
        "primary",
        "secondary",
        "tertiary",
        "quinary",
        "senary",
        "septenary",
        "octonary",
        "nonary",
        "denary"
      ];

      const type = event.data.type;

      if ( 'palette' === type ) {
        const groups = maybeInterpolateColors( event.data.colors );

        const config = groups.map( ( colors, groupIndex ) => {
          let label = `Brand ${ order[ groupIndex ] }`;

          if ( groupIndex === 0 ) {
            label = label.charAt( 0 ).toUpperCase() + label.slice( 1 );
          }

          const time = new Date().getTime();

          return {
            uid: `color_group_${ time }${ groupIndex }`,
            sources: colors.map( ( color, colorIndex ) => {

              if ( colorIndex !== 0 ) {
                label = styleManager.l10n.colorPalettes.dropzoneInterpolatedColorLabel;
              }

              return {
                uid: `color_${ time }${ groupIndex }${ colorIndex }`,
                label: label,
                value: chroma( color ).hex()
              }
            } )
          }
        } );

        updateSourceSetting( config );

        const preset = {};
        preset.palettes = getPalettesFromColors( config );
        setStripes( getRandomStripes( preset ) );
      }
    };

    return () => {
      delete myWorker.onmessage;
    };

  }, [] );

  useEffect( () => {
    const imgSource = imgSourceRef.current;
    const imgPreview = imgPreviewRef.current;

    // FileReader support
    if ( FileReader && files && files.length ) {
      var fr = new FileReader();
      fr.onload = function() {
        imgSource.src = fr.result;
        imgPreview.src = fr.result;
      }
      fr.readAsDataURL( files[0] );
    }
  }, [ files ] );

  if ( ! myWorker ) {
    return null;
  }

  const onImageLoad = useCallback( () => {
    const imgSource = imgSourceRef.current;

    const canvas = canvasRef.current;
    const context = canvas.getContext( '2d' );

    canvas.width = Math.min( imgSource.width, 100 );
    canvas.height = canvas.width * imgSource.height / imgSource.width;
    context.drawImage( imgSource, 0, 0, canvas.width, canvas.height );

    const imageData = context.getImageData( 0, 0, canvas.width, canvas.height ).data;

    if ( !! myWorker ) {
      myWorker.postMessage( {
        type: 'image',
        imageData: imageData,
        width: canvas.width,
        height: canvas.height
      } );
    }
  }, [ imgSourceRef.current, canvasRef.current ] );

  return (
    <div className="dropzone">
      <div className="customize-control-description">
        { styleManager.l10n.colorPalettes.dropzoneDesc }
      </div>
      <div className="dropzone-container" onDragOver={dragOver}
           onDragEnter={dragEnter}
           onDragLeave={dragLeave}
           onDrop={fileDrop}
           onClick={onClick}>
        <div className="dropzone-placeholder">
          <div className="dropzone-info">
            <div className="dropzone-info-icon" dangerouslySetInnerHTML={{
              __html: `
                <svg viewBox="${ uploadIcon.viewBox }">
                  <use xlink:href="#${ uploadIcon.id }" />
                </svg>`
            } } />
            <div className="dropzone-info-title">{ styleManager.l10n.colorPalettes.dropzoneTitle }</div>
            <div className="dropzone-info-text" dangerouslySetInnerHTML={{ __html: styleManager.l10n.colorPalettes.dropzoneSubtitle }} />
          </div>
        </div>
        <img alt="Preview" className="dropzone-image-preview" ref={ imgPreviewRef } />
        <input type='file' id='file' ref={ inputFile } style={ { display: 'none' } } onChange={ onFileChange } />
      </div>
      <img alt="Source" className="dropzone-image-source" ref={ imgSourceRef } onLoad={ onImageLoad } />
      <canvas className="dropzone-canvas" ref={ canvasRef } />
    </div>
  )
}

export default DropZone;
