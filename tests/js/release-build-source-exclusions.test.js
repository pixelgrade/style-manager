import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { copyProductionFiles } = require( '../../tasks/build-folder.js' );

test( 'release staging excludes private worktrees and preserves runtime files', t => {
	const fixtureRoot = fs.mkdtempSync(
		path.join( os.tmpdir(), 'style-manager-release-staging-' )
	);
	const sourceDirectory = path.join( fixtureRoot, 'source' );
	const destinationDirectory = path.join( fixtureRoot, 'destination' );

	t.after( () => fs.rmSync( fixtureRoot, { recursive: true, force: true } ) );

	fs.mkdirSync( path.join( sourceDirectory, '.worktrees', 'private-feature' ), { recursive: true } );
	fs.mkdirSync( path.join( sourceDirectory, 'src' ), { recursive: true } );
	fs.writeFileSync(
		path.join( sourceDirectory, '.worktrees', 'private-feature', 'sentinel.txt' ),
		'private worktree data'
	);
	fs.writeFileSync( path.join( sourceDirectory, 'style-manager.php' ), 'runtime entrypoint' );
	fs.writeFileSync( path.join( sourceDirectory, 'src', 'Plugin.php' ), 'runtime source' );

	copyProductionFiles( sourceDirectory, destinationDirectory );

	assert.equal(
		fs.existsSync( path.join( destinationDirectory, '.worktrees' ) ),
		false,
		'private worktrees never enter the release staging directory'
	);
	assert.equal(
		fs.readFileSync( path.join( destinationDirectory, 'style-manager.php' ), 'utf8' ),
		'runtime entrypoint'
	);
	assert.equal(
		fs.readFileSync( path.join( destinationDirectory, 'src', 'Plugin.php' ), 'utf8' ),
		'runtime source'
	);
} );
