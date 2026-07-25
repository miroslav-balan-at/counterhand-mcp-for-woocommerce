/* AgentGate MCP — in-admin chat: message bubbles + inline tool-call cards. */
( function () {
	'use strict';

	var i18n = ( window.agmcpChat && window.agmcpChat.i18n ) || {};

	var form = document.getElementById( 'agmcp-chat-form' );
	var input = document.getElementById( 'agmcp-chat-input' );
	var log = document.getElementById( 'agmcp-chat-log' );
	var empty = document.getElementById( 'agmcp-chat-empty' );
	var status = document.getElementById( 'agmcp-chat-status' );
	var sendButton = document.getElementById( 'agmcp-chat-send' );
	var resetButton = document.getElementById( 'agmcp-chat-reset' );

	if ( ! form || ! input || ! log ) {
		return;
	}

	// Provider-format message history, replaced wholesale by each response.
	var history = [];

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	function scrollToEnd() {
		log.scrollTop = log.scrollHeight;
	}

	function hideEmptyState() {
		if ( empty && empty.parentNode ) {
			empty.parentNode.removeChild( empty );
			empty = null;
		}
	}

	function addBubble( role, text ) {
		hideEmptyState();
		var row = el( 'div', 'agmcp-msg agmcp-msg--' + role );
		row.appendChild( el( 'div', 'agmcp-msg__role', role === 'user' ? ( i18n.you || 'You' ) : ( i18n.assistant || 'Assistant' ) ) );
		row.appendChild( el( 'div', 'agmcp-msg__body', text ) );
		log.appendChild( row );
		scrollToEnd();
		return row;
	}

	/** A collapsible card showing one tool call and its result. */
	function addToolCard( entry ) {
		hideEmptyState();

		var details = el( 'details', 'agmcp-tool' + ( entry.is_error ? ' agmcp-tool--error' : '' ) );
		var summary = el( 'summary', 'agmcp-tool__summary' );

		summary.appendChild( el( 'span', 'agmcp-tool__icon', entry.is_error ? '✕' : '✓' ) );
		summary.appendChild( el( 'code', 'agmcp-tool__name', entry.name ) );
		summary.appendChild( el(
			'span',
			'agmcp-tool__hint',
			entry.is_error ? ( i18n.toolFailed || 'failed' ) : ( i18n.toolRan || 'ran' )
		) );
		details.appendChild( summary );

		var body = el( 'div', 'agmcp-tool__body' );

		if ( entry.arguments && Object.keys( entry.arguments ).length ) {
			body.appendChild( el( 'div', 'agmcp-tool__label', i18n.arguments || 'Arguments' ) );
			body.appendChild( el( 'pre', 'agmcp-tool__code', JSON.stringify( entry.arguments, null, 2 ) ) );
		}

		body.appendChild( el( 'div', 'agmcp-tool__label', i18n.result || 'Result' ) );
		body.appendChild( el( 'pre', 'agmcp-tool__code', JSON.stringify( entry.result, null, 2 ) ) );

		details.appendChild( body );
		log.appendChild( details );
		scrollToEnd();
	}

	function setBusy( busy ) {
		sendButton.disabled = busy;
		input.disabled = busy;
		status.className = 'agmcp-chat__status';
		status.textContent = busy ? ( i18n.thinking || 'Thinking…' ) : '';
	}

	function autoGrow() {
		input.style.height = 'auto';
		input.style.height = Math.min( input.scrollHeight, 200 ) + 'px';
	}

	function send( text ) {
		addBubble( 'user', text );

		var pending = el( 'div', 'agmcp-msg agmcp-msg--assistant agmcp-msg--pending' );
		pending.appendChild( el( 'div', 'agmcp-msg__role', i18n.assistant || 'Assistant' ) );

		var typing = el( 'div', 'agmcp-typing' );
		for ( var dot = 0; dot < 3; dot++ ) {
			typing.appendChild( el( 'span' ) );
		}
		pending.appendChild( typing );

		log.appendChild( pending );
		scrollToEnd();

		setBusy( true );

		var body = new URLSearchParams();
		body.set( 'action', 'agmcp_chat_send' );
		body.set( '_ajax_nonce', form.dataset.nonce );
		body.set( 'message', text );
		body.set( 'history', JSON.stringify( history ) );

		fetch( form.dataset.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				pending.remove();

				var data = payload.data || {};

				if ( ! payload.success ) {
					var failure = addBubble( 'assistant', data.message || ( i18n.failed || 'The request failed.' ) );
					failure.classList.add( 'agmcp-msg--error' );
					return;
				}

				history = data.history || history;

				( data.transcript || [] ).forEach( function ( entry ) {
					if ( entry.type === 'tool' ) {
						addToolCard( entry );
					} else if ( entry.type === 'text' && entry.text ) {
						addBubble( 'assistant', entry.text );
					}
				} );

				if ( data.usage ) {
					status.textContent = ( i18n.tokens || 'Tokens' ) + ': ' +
						data.usage.input + ' → ' + data.usage.output;
				}
			} )
			.catch( function ( error ) {
				pending.remove();
				addBubble( 'assistant', String( error ) ).classList.add( 'agmcp-msg--error' );
			} )
			.finally( function () {
				setBusy( false );
				input.focus();
			} );
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		var text = input.value.trim();
		if ( ! text ) {
			return;
		}
		input.value = '';
		autoGrow();
		send( text );
	} );

	// Enter sends, Shift+Enter makes a new line.
	input.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Enter' && ! event.shiftKey ) {
			event.preventDefault();
			form.dispatchEvent( new Event( 'submit' ) );
		}
	} );

	input.addEventListener( 'input', autoGrow );

	document.addEventListener( 'click', function ( event ) {
		if ( event.target.classList.contains( 'agmcp-chat__suggestion' ) ) {
			input.value = event.target.textContent;
			autoGrow();
			form.dispatchEvent( new Event( 'submit' ) );
		}
	} );

	if ( resetButton ) {
		resetButton.addEventListener( 'click', function () {
			history = [];
			log.innerHTML = '';
			status.textContent = '';
			input.focus();
		} );
	}
}() );