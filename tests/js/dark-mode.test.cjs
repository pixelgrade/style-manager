const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );
const babel = require( '@babel/core' );

const pluginRoot = path.resolve( __dirname, '../..' );
const sourcePath = path.join( pluginRoot, 'src/_js/dark-mode/index.js' );

function createClassList( initialClasses = [] ) {
	const classes = new Set( initialClasses );

	return {
		add: className => classes.add( className ),
		remove: className => classes.delete( className ),
		contains: className => classes.has( className ),
		toString: () => Array.from( classes ).join( ' ' ),
	};
}

function createStorage( initialStorage = {} ) {
	const values = new Map( Object.entries( initialStorage ) );

	return {
		getItem: key => values.has( key ) ? values.get( key ) : null,
		setItem: ( key, value ) => values.set( key, String( value ) ),
		removeItem: key => values.delete( key ),
	};
}

function loadDarkMode( {
	darkModeSetting = 'off',
	loggedIn = true,
	storage = {},
	systemDark = false,
} = {} ) {
	const documentElement = {
		classList: createClassList(),
		dataset: {
			darkModeAdvanced: darkModeSetting,
		},
		addEventListener: () => {},
	};

	const document = {
		addEventListener: () => {},
		body: {
			classList: createClassList( loggedIn ? [ 'logged-in' ] : [] ),
		},
		documentElement,
		querySelector: () => null,
		readyState: 'complete',
	};

	const localStorage = createStorage( storage );
	const window = {
		document,
		localStorage,
		matchMedia: () => ( {
			addEventListener: () => {},
			matches: systemDark,
		} ),
		parent: {},
	};
	window.self = window;
	window.top = window;

	const context = {
		MutationObserver: class {
			observe() {}
		},
		document,
		exports: {},
		localStorage,
		module: {
			exports: {},
		},
		require: specifier => {
			if ( specifier === './utils' ) {
				return {
					delegateEvent: () => {},
				};
			}

			throw new Error( `Unexpected require: ${ specifier }` );
		},
		window,
	};
	context.exports = context.module.exports;

	const transformed = babel.transformSync( fs.readFileSync( sourcePath, 'utf8' ), {
		filename: sourcePath,
		presets: [
			[
				'@babel/preset-env',
				{
					modules: 'commonjs',
					targets: {
						node: 'current',
					},
				},
			],
		],
	} );

	vm.runInNewContext( transformed.code, context, {
		filename: sourcePath,
	} );

	return {
		darkMode: context.module.exports.default,
		documentElement,
		localStorage,
	};
}

{
	const { darkMode, documentElement, localStorage } = loadDarkMode( {
		darkModeSetting: 'off',
		loggedIn: true,
	} );

	darkMode.onClick( {
		preventDefault: () => {},
	} );

	assert.equal(
		localStorage.getItem( 'color-scheme-dark-temp' ),
		'dark',
		'the menu switcher should store a logged-in visitor override'
	);
	assert.equal(
		darkMode.isCompiledDark(),
		true,
		'a stored visitor override should beat the Light Customizer default'
	);
	assert.equal(
		documentElement.classList.contains( 'is-dark' ),
		true,
		'the stored override should apply the dark document class'
	);
}
