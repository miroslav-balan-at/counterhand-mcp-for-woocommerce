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

export async function listToolNames( client: APIRequestContext, endpoint: string, accessToken: string ): Promise< string[] > {
	const response = await mcpCall( client, endpoint, accessToken, 'tools/list' );
	const body = await response.json();

	return ( body.result?.tools ?? [] ).map( ( tool: { name: string } ) => tool.name );
}
