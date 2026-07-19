import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = path.dirname( fileURLToPath( import.meta.url ) );
const buildFolderSource = fs.readFileSync(
	path.resolve( testDirectory, '../../tasks/build-folder.js' ),
	'utf8'
);

test( 'release staging never traverses private Git worktrees', () => {
	assert.match(
		buildFolderSource,
		/'--exclude',\s*'\.worktrees'/,
		'the rsync copy boundary must exclude .worktrees before traversal'
	);
} );
