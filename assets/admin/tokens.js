/*
 * Counterhand MCP admin behaviour: the copy buttons shared by every screen.
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

	window.ctrhCopyText = copyText;

	/*
	 * Swapping the button's own text says nothing to a screen reader: the label
	 * changes but nothing announces it, and reverting it after 1500ms would race
	 * the announcement anyway. A separate polite region is what gets spoken, and
	 * it is not reverted.
	 */
	function announce( message ) {
		var region = document.getElementById( 'ctrh-copy-status' );

		if ( region ) {
			region.textContent = message;
		}
	}

	function copyFromButton( button ) {
		var restoreLabel = button.textContent;
		var copiedLabel = button.dataset.copiedLabel || 'Copied!';

		copyText( button.dataset.copy || '' ).then( function () {
			button.textContent = copiedLabel;
			announce( copiedLabel );
			window.setTimeout( function () {
				button.textContent = restoreLabel;
			}, 1500 );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var copyButton = event.target.closest( '.ctrh-copy' );

		if ( copyButton ) {
			copyFromButton( copyButton );
		}
	} );
}() );
