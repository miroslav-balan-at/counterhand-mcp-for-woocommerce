/* AgentGate MCP admin behavior: copy buttons + connection verifier. */
( function () {
	'use strict';

	function copyText( text, button ) {
		var restoreLabel = button.textContent;

		function done() {
			button.textContent = button.dataset.copiedLabel || 'Copied!';
			window.setTimeout( function () {
				button.textContent = restoreLabel;
			}, 1500 );
		}

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}

		// Fallback for non-secure contexts.
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
		done();
	}

	document.addEventListener( 'click', function ( event ) {
		var copyButton = event.target.closest( '.agmcp-copy' );
		if ( copyButton ) {
			copyText( copyButton.dataset.copy || '', copyButton );
			return;
		}

		if ( event.target.id === 'agmcp-copy-token' ) {
			var tokenValue = document.getElementById( 'agmcp-token-value' );
			if ( tokenValue ) {
				copyText( tokenValue.textContent.trim(), event.target );
			}
			return;
		}

		if ( event.target.id === 'agmcp-verify' ) {
			verifyConnection( event.target );
		}
	} );

	function verifyConnection( button ) {
		var result = document.getElementById( 'agmcp-verify-result' );
		result.className = '';
		result.textContent = '…';
		button.disabled = true;

		var body = new URLSearchParams();
		body.set( 'action', 'agmcp_verify_connection' );
		body.set( '_ajax_nonce', button.dataset.nonce );

		fetch( button.dataset.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				var ok = payload && payload.success;
				result.className = ok ? 'agmcp-ok' : 'agmcp-fail';
				result.textContent = ( ok ? '✓ ' : '✗ ' ) + ( payload.data && payload.data.message ? payload.data.message : 'Unknown response.' );
			} )
			.catch( function ( error ) {
				result.className = 'agmcp-fail';
				result.textContent = '✗ ' + error;
			} )
			.finally( function () {
				button.disabled = false;
			} );
	}
}() );
