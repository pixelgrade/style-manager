/**
 * External dependencies
 */
const TerserPlugin = require( 'terser-webpack-plugin' );
const MiniCssExtractPlugin = require( "mini-css-extract-plugin" );
const path = require( 'path' );

const files = [
  'customizer',
  'customizer-preview',
  'customizer-search',
  'dark-mode',
  'design-hub',
  'editor-launcher',
  'lab',
  'lab-showcase',
  'settings',
  'site-editor',
];

function camelize( str ) {
  const arr = str.split( '-' );

  return arr.slice(1).reduce( ( acc, curr ) => {
    return acc + curr.charAt(0).toUpperCase() + curr.slice(1).toLowerCase();
  }, arr[0] );
}

function kebabize( str ) {
  return str.replace( /([a-z0-9]|(?=[A-Z]))([A-Z])/g, '$1-$2' ).toLowerCase();
}

const entries = files.reduce( ( acc, curr ) => {
  acc[ camelize( curr ) ] = `./src/_js/${ curr }/index.js`;
  return acc;
}, {} );

const browserConfig = {
  mode: 'production',
  entry: entries,
  output: {
    path: path.join( __dirname, "dist/js" ),
    filename: pathData => {
      return `${ kebabize( pathData.chunk.name ) }.js`;
    },
    chunkFilename: '[name].js',
    library: {
      name: [ 'sm', '[name]' ],
      type: 'window'
    },
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /(node_modules|bower_components)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              [
                '@babel/preset-env',
                {
                  modules: false
                }
              ],
              '@babel/preset-react',
            ],
          }
        },
        sideEffects: false
      },
      {
        test: /\.s[ac]ss$/i,
        use: [
          MiniCssExtractPlugin.loader,
          // Translates CSS into CommonJS
          {
            loader: "css-loader",
            options: {
              url: false,
            },
          },
          // Compiles Sass to CSS
          "sass-loader",
        ],
        sideEffects: true
      },
      {
        test: /\.svg$/,
        loader: 'svg-sprite-loader'
      }
    ],
  },
  externals: {
    jquery: 'jQuery',
    lodash: 'lodash',
    react: 'React',
    'chroma-js': 'chroma',
    'react-dom': 'ReactDOM',
    '@wordpress/components': [ 'wp', 'components' ],
    '@wordpress/element': [ 'wp', 'element' ],
    '@wordpress/i18n': [ 'wp', 'i18n' ],
    '@wordpress/plugins': [ 'wp', 'plugins' ],
    '@wordpress/editor': [ 'wp', 'editor' ],
    '@wordpress/data': [ 'wp', 'data' ],
    '@wordpress/api-fetch': [ 'wp', 'apiFetch' ],
  },
  optimization: {
    minimize: true,
    minimizer: [
      new TerserPlugin( {
        include: /\.js$/,
        extractComments: {
          condition: true,
          filename: ( fileData ) => {
            // The "fileData" argument contains object with "filename", "basename", "query" and "hash"
            return `${ fileData.filename }.LICENSE.txt${ fileData.query }`;
          },
        },
      } )
    ],
  },
  'plugins': [
    new MiniCssExtractPlugin( {
      filename: pathData => {
        return `${ kebabize( pathData.chunk.name ) }.css`;
      },
      chunkFilename: "[id].css",
    } ),
  ]
};

/**
 * The Node build of the shared color generator (agent-surface contract §3.11 / F13).
 *
 * Same `src/_js/shared/color-generator/` module the Customizer bundle consumes — that
 * identity is the drift guard, and it is why `wp pixelgrade sm apply-color-palette` can
 * reproduce browser-produced `sm_advanced_palette_output` byte for byte.
 *
 * Two deliberate differences from the browser config:
 *
 * - **No externals.** The browser gets `chroma-js` from the `pixelgrade_style_manager-chroma`
 *   handle (`vendor_js/chroma.js`) and therefore lists it in `externals`. There is no such
 *   global in Node, so chroma-js and hsluv are bundled in — they are devDependencies and
 *   would otherwise be absent from a distributed plugin zip.
 * - **No minification.** The artifact is committed to the repo, so a readable diff is worth
 *   more than the bytes, and a reviewer can see exactly what ships.
 *
 * `target: 'node'` also disposes of the CJS/ESM interop shim the spike needed: hsluv@0.1.0
 * has no named ESM exports, and webpack resolves that at build time.
 */
const nodeConfig = {
  name: 'palette-generator',
  mode: 'production',
  target: 'node',
  entry: {
    paletteGenerator: './src/_js/shared/color-generator/cli.js',
  },
  output: {
    path: path.join( __dirname, 'dist/node' ),
    filename: 'palette-generator.js',
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /(node_modules|bower_components)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              [
                '@babel/preset-env',
                {
                  modules: false,
                  targets: { node: '22' },
                }
              ],
            ],
          }
        },
        sideEffects: false
      },
    ],
  },
  optimization: {
    minimize: false,
  },
  performance: {
    hints: false,
  },
};

module.exports = [ browserConfig, nodeConfig ];
