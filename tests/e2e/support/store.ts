import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

/** The devbox project that runs the disposable store (tests/e2e/store). */
const storeProject = join( __dirname, '..', 'store' );

/** Mirrors scripts/setup-env.sh, which owns these defaults for the store itself. */
export const stateDirectory = process.env.COUNTERHAND_E2E_STATE_DIR ?? join( homedir(), '.cache', 'counterhand-e2e' );
export const baseURL = `http://localhost:80${ process.env.COUNTERHAND_E2E_PORT_OFFSET ?? '07' }`;
export const adminStatePath = join( stateDirectory, 'admin.json' );
export const shopManagerStatePath = join( stateDirectory, 'shop-manager.json' );

export type Account = { username: string; password: string };

/** Written by install-store.sh on every run; never committed. */
export function accounts(): { admin: Account; shopManager: Account } {
	return JSON.parse( readFileSync( join( stateDirectory, 'credentials.json' ), 'utf-8' ) );
}

export function installStore(): void {
	execFileSync( 'devbox', [ 'run', '-c', storeProject, 'install-store' ], { stdio: 'inherit' } );
}

/**
 * Runs WP-CLI in the store. For state the UI cannot set quickly, such as a
 * tool group switch, never for anything a spec is meant to prove.
 */
export function wp( ...args: string[] ): string {
	return execFileSync( 'devbox', [ 'run', '-c', storeProject, '--', 'wp', `--path=${ join( stateDirectory, 'wordpress' ) }`, ...args ], {
		encoding: 'utf-8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} )
		.split( '\n' )
		.filter( ( line ) => ! line.startsWith( 'Info:' ) )
		.join( '\n' )
		.trim();
}

/** Sets keys of an array option for the duration of a spec; returns the undo. */
export function patchOption( name: string, values: Record< string, unknown > ): () => void {
	// WP-CLI exits non-zero for an option that was never saved; restoring then means deleting it.
	let before: Record< string, unknown > | null = null;
	try {
		before = JSON.parse( wp( 'option', 'get', name, '--format=json' ) );
	} catch {
		before = null;
	}
	wp( 'option', 'update', name, JSON.stringify( { ...( before ?? {} ), ...values } ), '--format=json' );

	let restored = false;
	return () => {
		if ( restored ) {
			return;
		}
		restored = true;
		if ( null === before ) {
			wp( 'option', 'delete', name );
			return;
		}
		wp( 'option', 'update', name, JSON.stringify( before ), '--format=json' );
	};
}

export const patchSettings = ( values: Record< string, boolean | number > ) => patchOption( 'counterhand_settings', values );
