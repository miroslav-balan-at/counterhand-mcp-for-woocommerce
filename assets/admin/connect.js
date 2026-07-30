/*
 * Counterhand MCP — Connect AI apps tab.
 *
 * Three jobs, all aimed at removing round trips:
 *  - run the readiness check on load, so a store that cloud apps cannot reach
 *    says so before the admin spends a trip to the vendor's site;
 *  - copy the endpoint and open the vendor's connector page in one click;
 *  - watch for the connection landing, so the card confirms itself instead of
 *    sending the admin to the Connections tab to find out.
 */
( function () {
	'use strict';

	var config = window.ctrhConnect || {};
	var i18n = config.i18n || {};

	var chip = document.getElementById( 'ctrh-readiness' );
	var detail = document.getElementById( 'ctrh-readiness-detail' );
	var recheck = document.getElementById( 'ctrh-recheck' );

	function post( action, extra ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( '_ajax_nonce', config.nonce || '' );

		Object.keys( extra || {} ).forEach( function ( key ) {
			body.set( key, extra[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/* Readiness ---------------------------------------------------------- */

	function setChip( state, text ) {
		if ( ! chip ) {
			return;
		}

		chip.className = 'ctrh-chip' + ( state ? ' ctrh-chip--' + state : '' );
		chip.querySelector( '.ctrh-chip__text' ).textContent = text;
	}

	/**
	 * Cloud cards are dimmed rather than hidden when the store is local: the
	 * admin should see that Claude and ChatGPT exist and why they are out of
	 * reach, not wonder where they went.
	 */
	function markCloudUnavailable( reason ) {
		document.querySelectorAll( '[data-needs-public="1"]' ).forEach( function ( group ) {
			group.querySelectorAll( '.ctrh-card--collapsible' ).forEach( function ( card ) {
				if ( card.querySelector( '.ctrh-unavailable' ) ) {
					return;
				}

				var note = document.createElement( 'p' );
				note.className = 'ctrh-field__hint ctrh-unavailable';
				note.textContent = reason;

				var body = card.querySelector( '.ctrh-card__body' );
				if ( body ) {
					body.insertBefore( note, body.firstChild );
				}

				card.classList.add( 'ctrh-card--muted' );
			} );
		} );
	}

	function runReadiness() {
		if ( ! chip ) {
			return;
		}

		setChip( 'pending', i18n.checking || 'Checking the store…' );

		post( 'ctrh_preflight' )
			.then( function ( payload ) {
				var data = ( payload && payload.data ) || {};
				var status = data.status || 'error';

				setChip( status === 'ok' ? 'ok' : ( status === 'local' ? 'warn' : 'error' ), data.message || '' );

				if ( detail ) {
					detail.textContent = data.detail || '';
				}

				if ( status !== 'ok' ) {
					markCloudUnavailable( data.detail || data.message || '' );
				}
			} )
			.catch( function () {
				setChip( 'error', i18n.checkFailed || 'The check could not be run.' );
			} );
	}

	/* Copy-then-open and connection watching ------------------------------ */

	var watching = null;
	var watchUntil = 0;

	function cardFor( clientId ) {
		return document.querySelector( '.ctrh-card--collapsible[data-client="' + clientId + '"]' );
	}

	function showConnected( clientId, label ) {
		var card = cardFor( clientId );
		if ( ! card || card.querySelector( '.ctrh-connected' ) ) {
			return;
		}

		var badge = document.createElement( 'span' );
		badge.className = 'ctrh-connected';
		badge.textContent = '✓ ' + ( i18n.connected || 'Connected' );
		badge.title = label || '';

		var head = card.querySelector( '.ctrh-card__head' );
		if ( head ) {
			head.appendChild( badge );
		}
	}

	function setWaiting( clientId, waiting ) {
		var card = cardFor( clientId );
		if ( ! card ) {
			return;
		}

		var existing = card.querySelector( '.ctrh-waiting' );

		if ( ! waiting ) {
			if ( existing ) {
				existing.remove();
			}
			return;
		}

		if ( existing ) {
			return;
		}

		var note = document.createElement( 'span' );
		note.className = 'ctrh-chip ctrh-chip--pending ctrh-waiting';
		note.innerHTML = '<span class="ctrh-chip__dot" aria-hidden="true"></span>';

		var text = document.createElement( 'span' );
		text.className = 'ctrh-chip__text';
		text.setAttribute( 'role', 'status' );
		text.textContent = i18n.waiting || 'Waiting for the app to connect…';
		note.appendChild( text );

		var body = card.querySelector( '.ctrh-card__body' );
		if ( body ) {
			body.insertBefore( note, body.firstChild );
		}
	}

	function stopWatching() {
		if ( watching ) {
			window.clearInterval( watching );
			watching = null;
		}
	}

	/**
	 * Polls for a connection created after the click. Matching on "newer than
	 * the moment you pressed the button" is exact, unlike guessing the client
	 * from its metadata URL — several products authorise from the same vendor.
	 */
	function watchForConnection( clientId, since ) {
		stopWatching();
		setWaiting( clientId, true );
		watchUntil = Date.now() + 180000;

		watching = window.setInterval( function () {
			if ( Date.now() > watchUntil ) {
				stopWatching();
				setWaiting( clientId, false );
				return;
			}

			// No point polling a tab nobody is looking at.
			if ( document.hidden ) {
				return;
			}

			post( 'ctrh_connection_status', { since: String( since ) } )
				.then( function ( payload ) {
					var data = ( payload && payload.data ) || {};

					if ( ! data.connected ) {
						return;
					}

					stopWatching();
					setWaiting( clientId, false );
					showConnected( clientId, data.client || '' );
				} )
				.catch( stopWatching );
		}, 3000 );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '.ctrh-copy-open' );
		if ( ! trigger ) {
			return;
		}

		// The shared copy helper in tokens.js already handles the clipboard,
		// including the non-secure-context fallback; the link then follows
		// normally, so the endpoint is on the clipboard when the page lands.
		if ( window.ctrhCopyText ) {
			window.ctrhCopyText( trigger.dataset.copy || '' );
		}

		watchForConnection( trigger.dataset.client, Math.floor( Date.now() / 1000 ) );
	} );

	if ( recheck ) {
		recheck.addEventListener( 'click', runReadiness );
	}

	runReadiness();
}() );
