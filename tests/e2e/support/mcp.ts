import type { APIRequestContext, APIResponse } from '@playwright/test';

export const PROTOCOL_VERSION = '2025-11-25';

let nextId = 1;

/** One JSON-RPC call over Streamable HTTP, the way an MCP client POSTs it. */
export function mcpCall(
	client: APIRequestContext,
	endpoint: string,
	accessToken: string | null,
	method: string,
	params: Record< string, unknown > = {}
): Promise< APIResponse > {
	const headers: Record< string, string > = {
		'Content-Type': 'application/json',
		Accept: 'application/json, text/event-stream',
		'MCP-Protocol-Version': PROTOCOL_VERSION,
	};
	if ( accessToken ) {
		headers.Authorization = `Bearer ${ accessToken }`;
	}

	return client.post( endpoint, {
		headers,
		data: { jsonrpc: '2.0', id: nextId++, method, params },
	} );
}

export const STATELESS_VERSION = '2026-07-28';

/**
 * One stateless (2026-07-28) request: the version and capabilities in
 * params._meta on every call, mirrored into the standard headers. `headers`
 * overrides or removes (undefined) a mirrored header, to test the refusals.
 */
export function statelessCall(
	client: APIRequestContext,
	endpoint: string,
	accessToken: string,
	method: string,
	params: Record< string, unknown > = {},
	options: { version?: string; headers?: Record< string, string | undefined > } = {}
): Promise< APIResponse > {
	const version = options.version ?? STATELESS_VERSION;
	const name = ( params.name ?? params.uri ) as string | undefined;
	const headers: Record< string, string | undefined > = {
		'Content-Type': 'application/json',
		Accept: 'application/json, text/event-stream',
		Authorization: `Bearer ${ accessToken }`,
		'MCP-Protocol-Version': version,
		'Mcp-Method': method,
		...( name ? { 'Mcp-Name': name } : {} ),
		...options.headers,
	};

	return client.post( endpoint, {
		headers: Object.fromEntries( Object.entries( headers ).filter( ( entry ): entry is [ string, string ] => undefined !== entry[ 1 ] ) ),
		data: {
			jsonrpc: '2.0',
			id: nextId++,
			method,
			params: {
				...params,
				_meta: {
					'io.modelcontextprotocol/protocolVersion': version,
					'io.modelcontextprotocol/clientCapabilities': {},
					'io.modelcontextprotocol/clientInfo': { name: 'counterhand-e2e', version: '0' },
				},
			},
		},
	} );
}

export async function listToolNames( client: APIRequestContext, endpoint: string, accessToken: string ): Promise< string[] > {
	const response = await mcpCall( client, endpoint, accessToken, 'tools/list' );
	const body = await response.json();

	return ( body.result?.tools ?? [] ).map( ( tool: { name: string } ) => tool.name );
}
