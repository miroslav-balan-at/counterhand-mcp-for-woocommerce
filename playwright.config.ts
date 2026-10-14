import { join } from 'node:path';
import { defineConfig, devices } from '@playwright/test';
import { adminStatePath, baseURL, stateDirectory } from './tests/e2e/support/store';

/*
 * The suite runs against the disposable devbox store in tests/e2e/store (see
 * tests/e2e/README.md). Start it with `npm run e2e:store:start`.
 */
export default defineConfig( {
	testDir: './tests/e2e/specs',
	globalSetup: './tests/e2e/global-setup.ts',
	// Traces carry bearer tokens, so they stay with the rest of the store's state, outside the repo.
	outputDir: join( stateDirectory, 'results' ),
	// One store, shared state (settings, connections): specs must not interleave.
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	timeout: 60_000,
	reporter: [ [ 'list' ], [ 'html', { open: 'never', outputFolder: join( stateDirectory, 'report' ) } ] ],
	use: {
		baseURL,
		storageState: adminStatePath,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
