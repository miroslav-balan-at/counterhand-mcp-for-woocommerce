/*
 * AgentGate MCP admin behaviour: the copy buttons shared by every screen.
 *
 * The copy helper is published on window because connect.js needs the same
 * clipboard handling — including the non-secure-context fallback — when it
 * copies the endpoint on the way to an app's connector page.
 */
( function () {
	'use strict';

	/** Writes text to the clipboard, falling back where the async API is absent. */
	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}

		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );

		return Promise.resolve();
	}

	window.agmcpCopyText = copyText;

	function copyFromButton( button ) {
		var restoreLabel = button.textContent;

		copyText( button.dataset.copy || '' ).then( function () {
			button.textContent = button.dataset.copiedLabel || 'Copied!';
			window.setTimeout( function () {
				button.textContent = restoreLabel;
			}, 1500 );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var copyButton = event.target.closest( '.agmcp-copy' );

		if ( copyButton ) {
			copyFromButton( copyButton );
		}
	} );
}() );
