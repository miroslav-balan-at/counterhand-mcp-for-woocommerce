/* AgentGate MCP — in-admin chat: message bubbles + inline tool-call cards. */
( function () {
	'use strict';

	var i18n = ( window.agmcpChat && window.agmcpChat.i18n ) || {};

	// Provider install: intercept the chooser's install form so the button can
	// show progress; the form itself stays as the no-JS fallback.
	var installForm = document.getElementById( 'agmcp-install-form' );

	if ( installForm ) {
		installForm.addEventListener( 'submit', function ( event ) {
			var clicked = installForm.querySelector( 'button[data-clicked]' );
			if ( ! clicked ) {
				return;
			}

			event.preventDefault();

			installForm.querySelectorAll( 'button' ).forEach( function ( button ) {
				button.disabled = true;
			} );
			clicked.textContent = i18n.installing || 'Installing…';

			var body = new URLSearchParams( new FormData( installForm ) );
			body.set( 'action', 'agmcp_install_provider' );
			body.set( 'agmcp_provider_slug', clicked.value );

			fetch( installForm.dataset.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( payload ) {
					if ( ! payload.success ) {
						throw new Error( ( payload.data && payload.data.message ) || '' );
					}

					clicked.textContent = '✓ ' + ( i18n.installed || 'Installed' );
					window.location.reload();
				} )
				.catch( function ( error ) {
					installForm.querySelectorAll( 'button' ).forEach( function ( button ) {
						button.disabled = false;
						delete button.dataset.clicked;
					} );

					var note = document.getElementById( 'agmcp-install-error' );
					if ( note ) {
						note.textContent = '✕ ' + ( error.message || i18n.installFailed || 'The install failed.' );
					}
				} );
		} );

		installForm.querySelectorAll( 'button[type="submit"]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				button.dataset.clicked = '1';
			} );
		} );
	}

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

	function escapeHtml( text ) {
		return String( text ).replace( /[&<>"']/g, function ( character ) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;'
			}[ character ];
		} );
	}

	/**
	 * Renders an assistant answer as light markup.
	 *
	 * Models answer with lists, code and emphasis; as plain text those arrive
	 * as a wall of characters. The whole string is escaped first and only then
	 * are our own tags inserted, so no model output can become live markup —
	 * which is what lets us do this without a markdown dependency.
	 */
	function formatAnswer( text ) {
		var blocks = [];

		var out = escapeHtml( text )
			// Fenced blocks are parked first so the inline rules below can't
			// reach inside them. The sentinel uses angle brackets, which the
			// escaping above has already turned into entities everywhere else,
			// so it cannot collide with the message text.
			.replace( /```[^\n]*\n?([\s\S]*?)```/g, function ( match, code ) {
				blocks.push( code.replace( /\n$/, '' ) );
				return '\n<<' + ( blocks.length - 1 ) + '>>\n';
			} )
			.replace( /`([^`\n]+)`/g, '<code>$1</code>' )
			.replace( /\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>' );

		var html = '';
		var listTag = null;
		var paragraph = [];

		function closeParagraph() {
			if ( paragraph.length ) {
				html += '<p>' + paragraph.join( '<br>' ) + '</p>';
				paragraph = [];
			}
		}

		function closeList() {
			if ( listTag ) {
				html += '</' + listTag + '>';
				listTag = null;
			}
		}

		out.split( '\n' ).forEach( function ( line ) {
			var block = line.match( /^<<(\d+)>>$/ );

			if ( block ) {
				closeParagraph();
				closeList();
				html += '<pre><code>' + blocks[ Number( block[ 1 ] ) ] + '</code></pre>';
				return;
			}

			var bullet = line.match( /^\s*[-*]\s+(.*)$/ );
			var numbered = line.match( /^\s*\d+[.)]\s+(.*)$/ );
			var wanted = bullet ? 'ul' : ( numbered ? 'ol' : null );

			if ( wanted ) {
				closeParagraph();
				if ( listTag !== wanted ) {
					closeList();
					html += '<' + wanted + '>';
					listTag = wanted;
				}
				html += '<li>' + ( bullet ? bullet[ 1 ] : numbered[ 1 ] ) + '</li>';
				return;
			}

			closeList();

			if ( line.trim() === '' ) {
				closeParagraph();
				return;
			}

			paragraph.push( line );
		} );

		closeParagraph();
		closeList();

		return html;
	}

	/** The store's own mark, matching the avatar on the OAuth consent screen. */
	function avatar() {
		var mark = ( window.agmcpChat && window.agmcpChat.avatar ) || {};
		var node = el( 'span', 'agmcp-msg__avatar' );
		node.setAttribute( 'aria-hidden', 'true' );

		if ( mark.url ) {
			var image = document.createElement( 'img' );
			image.src = mark.url;
			image.alt = '';
			node.appendChild( image );
		} else {
			node.textContent = mark.letter || '?';
		}

		return node;
	}

	/** A message row; assistant rows carry the store mark, user rows do not. */
	function messageRow( role ) {
		var row = el( 'div', 'agmcp-msg agmcp-msg--' + role );

		if ( role !== 'user' ) {
			row.appendChild( avatar() );
		}

		var main = el( 'div', 'agmcp-msg__main' );
		main.appendChild( el(
			'div',
			'agmcp-msg__role',
			role === 'user' ? ( i18n.you || 'You' ) : ( i18n.assistant || 'Assistant' )
		) );
		row.appendChild( main );

		return { row: row, main: main };
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

	/**
	 * Adds a bubble. Assistant answers are formatted; the user's own text is
	 * inserted as text, since there is nothing to render and no reason to parse
	 * what they typed.
	 */
	function addBubble( role, text ) {
		hideEmptyState();

		var parts = messageRow( role );
		var body = el( 'div', 'agmcp-msg__body' );

		if ( role === 'user' ) {
			body.textContent = text;
		} else {
			body.innerHTML = formatAnswer( text );
		}

		parts.main.appendChild( body );
		log.appendChild( parts.row );
		scrollToEnd();

		return parts.row;
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
		input.style.height = Math.min( input.scrollHeight, 240 ) + 'px';
	}

	function send( text ) {
		addBubble( 'user', text );

		var parts = messageRow( 'assistant' );
		parts.row.classList.add( 'agmcp-msg--pending' );

		var typing = el( 'div', 'agmcp-typing' );
		for ( var dot = 0; dot < 3; dot++ ) {
			typing.appendChild( el( 'span' ) );
		}
		parts.main.appendChild( typing );

		log.appendChild( parts.row );
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
				parts.row.remove();

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
				parts.row.remove();
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
	autoGrow();

	document.addEventListener( 'click', function ( event ) {
		if ( event.target.classList.contains( 'agmcp-chat__suggestion' ) ) {
			input.value = event.target.textContent.trim();
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
