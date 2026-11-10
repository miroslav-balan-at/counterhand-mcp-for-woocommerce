/* Counterhand MCP — in-admin chat: message bubbles + inline tool-call cards. */
( function () {
	'use strict';

	var i18n = ( window.counterhandChat && window.counterhandChat.i18n ) || {};

	// Provider install: intercept the chooser's install form so the button can
	// show progress; the form itself stays as the no-JS fallback.
	var installForm = document.getElementById( 'counterhand-install-form' );

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
			body.set( 'action', 'counterhand_install_provider' );
			body.set( 'counterhand_provider_slug', clicked.value );

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

					var note = document.getElementById( 'counterhand-install-error' );
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

	var form = document.getElementById( 'counterhand-chat-form' );
	var input = document.getElementById( 'counterhand-chat-input' );
	var log = document.getElementById( 'counterhand-chat-log' );
	var empty = document.getElementById( 'counterhand-chat-empty' );
	var status = document.getElementById( 'counterhand-chat-status' );
	var sendButton = document.getElementById( 'counterhand-chat-send' );
	var resetButton = document.getElementById( 'counterhand-chat-reset' );

	if ( ! form || ! input || ! log ) {
		return;
	}

	// Provider-format message history, replaced wholesale by each response.
	var history = [];

	// What the log shows, as data, so a reload can draw it again through the same escaping.
	var entries = [];
	var totals = { input: 0, output: 0 };
	var storageKey = form.dataset.storageKey || '';
	var savedList = document.getElementById( 'counterhand-chat-saved' );
	var saveButton = document.getElementById( 'counterhand-chat-save-prompt' );

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
		var mark = ( window.counterhandChat && window.counterhandChat.avatar ) || {};
		var node = el( 'span', 'counterhand-msg__avatar' );
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
		var row = el( 'div', 'counterhand-msg counterhand-msg--' + role );

		if ( role !== 'user' ) {
			row.appendChild( avatar() );
		}

		var main = el( 'div', 'counterhand-msg__main' );
		main.appendChild( el(
			'div',
			'counterhand-msg__role',
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
	function addBubble( role, text, kept ) {
		hideEmptyState();

		if ( kept !== false ) {
			entries.push( { kind: 'bubble', role: role, text: text } );
		}

		var parts = messageRow( role );
		var body = el( 'div', 'counterhand-msg__body' );

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
		entries.push( { kind: 'tool', entry: entry } );

		var state = entry.declined ? 'declined' : ( entry.is_error ? 'error' : 'ok' );
		var details = el( 'details', 'counterhand-tool' + ( state === 'ok' ? '' : ' counterhand-tool--' + state ) );
		var summary = el( 'summary', 'counterhand-tool__summary' );

		summary.appendChild( el( 'span', 'counterhand-tool__icon', state === 'ok' ? '✓' : ( state === 'declined' ? '–' : '✕' ) ) );
		summary.appendChild( el( 'code', 'counterhand-tool__name', entry.name ) );
		summary.appendChild( el(
			'span',
			'counterhand-tool__hint',
			state === 'declined' ? ( i18n.toolDeclined || 'declined' ) : ( state === 'error' ? ( i18n.toolFailed || 'failed' ) : ( i18n.toolRan || 'ran' ) )
		) );
		details.appendChild( summary );

		var body = el( 'div', 'counterhand-tool__body' );

		if ( entry.arguments && Object.keys( entry.arguments ).length ) {
			body.appendChild( el( 'div', 'counterhand-tool__label', i18n.arguments || 'Arguments' ) );
			body.appendChild( el( 'pre', 'counterhand-tool__code', JSON.stringify( entry.arguments, null, 2 ) ) );
		}

		body.appendChild( el( 'div', 'counterhand-tool__label', i18n.result || 'Result' ) );
		body.appendChild( el( 'pre', 'counterhand-tool__code', JSON.stringify( entry.result, null, 2 ) ) );

		details.appendChild( body );
		log.appendChild( details );
		scrollToEnd();
	}

	function setBusy( busy ) {
		sendButton.disabled = busy;
		input.disabled = busy;
		status.className = 'counterhand-chat__status';
		status.textContent = busy ? ( i18n.thinking || 'Thinking…' ) : '';
	}

	function autoGrow() {
		input.style.height = 'auto';
		input.style.height = Math.min( input.scrollHeight, 240 ) + 'px';
	}

	/** A pending row with the typing indicator, removed when the answer lands. */
	function typingRow() {
		var parts = messageRow( 'assistant' );
		parts.row.classList.add( 'counterhand-msg--pending' );

		var typing = el( 'div', 'counterhand-typing' );
		for ( var dot = 0; dot < 3; dot++ ) {
			typing.appendChild( el( 'span' ) );
		}
		parts.main.appendChild( typing );

		log.appendChild( parts.row );
		scrollToEnd();

		return parts.row;
	}

	function post( body ) {
		body.set( '_ajax_nonce', form.dataset.nonce );
		body.set( 'history', JSON.stringify( history ) );

		return fetch( form.dataset.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Renders one exchange. A turn parked for approval leaves the composer
	 * locked: the conversation cannot move on until the person has decided.
	 */
	function render( payload ) {
		var data = payload.data || {};

		if ( ! payload.success ) {
			addBubble( 'assistant', data.message || ( i18n.failed || 'The request failed.' ), false ).classList.add( 'counterhand-msg--error' );
			return false;
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
			totals.input += data.usage.input || 0;
			totals.output += data.usage.output || 0;
		}
		showTotals();

		if ( data.pending ) {
			askApproval( data.pending, data.previews || [] );
			return true;
		}

		keep();
		return false;
	}

	function showTotals() {
		if ( ! totals.input && ! totals.output ) {
			return;
		}

		status.textContent = ( i18n.conversation || 'This conversation: %1$s tokens in, %2$s out' )
			.replace( '%1$s', totals.input.toLocaleString() )
			.replace( '%2$s', totals.output.toLocaleString() );
	}

	/*
	 * The tab's own storage, keyed by site, user and model: logging out does not clear it,
	 * and a history in one provider's format breaks another's. A turn waiting for approval
	 * is never kept, because its approval cannot survive the reload.
	 */
	function keep() {
		try {
			window.sessionStorage.setItem( storageKey, JSON.stringify( { history: history, entries: entries, totals: totals } ) );
		} catch ( error ) {
			// Storage can be full or blocked; the conversation just will not survive a reload.
		}
	}

	function forgetKept() {
		try {
			window.sessionStorage.removeItem( storageKey );
		} catch ( error ) {
			// Nothing was kept.
		}
	}

	function restore() {
		var kept = null;

		try {
			kept = JSON.parse( window.sessionStorage.getItem( storageKey ) || 'null' );
		} catch ( error ) {
			kept = null;
		}

		if ( ! kept || ! Array.isArray( kept.history ) || ! Array.isArray( kept.entries ) ) {
			return;
		}

		history = kept.history;
		totals = kept.totals || totals;
		kept.entries.forEach( function ( item ) {
			if ( item.kind === 'tool' ) {
				addToolCard( item.entry );
			} else if ( item.kind === 'bubble' ) {
				addBubble( item.role, String( item.text ) );
			}
		} );
		showTotals();
	}

	/** Values can be HTML or objects; they go in as text, and a long one is cut. */
	function previewValue( value ) {
		var text = typeof value === 'string' ? value : JSON.stringify( value );

		if ( text === '' || text === undefined || text === 'null' ) {
			return i18n.empty || '(empty)';
		}

		return text.length > 300 ? text.slice( 0, 300 ) + '…' : text;
	}

	function previewTable( preview ) {
		if ( preview.refusal ) {
			var refused = el( 'p', 'counterhand-confirm__refusal' );
			refused.appendChild( el( 'strong', '', ( i18n.wouldRefuse || 'The store would refuse this:' ) + ' ' ) );
			refused.appendChild( document.createTextNode( preview.refusal ) );
			return refused;
		}

		var table = el( 'table', 'counterhand-confirm__diff' );
		var head = el( 'tr' );
		[ i18n.field || 'Field', i18n.before || 'Now', i18n.after || 'After' ].forEach( function ( label ) {
			head.appendChild( el( 'th', '', label ) );
		} );
		table.appendChild( head );

		preview.changes.forEach( function ( change ) {
			var row = el( 'tr' );
			row.appendChild( el( 'th', '', change.field ) );
			row.appendChild( el( 'td', '', previewValue( change.before ) ) );
			row.appendChild( el( 'td', '', previewValue( change.after ) ) );
			table.appendChild( row );
		} );

		return table;
	}

	/** One card per call that needs the person; the turn resumes once all are decided. */
	function askApproval( pending, previews ) {
		hideEmptyState();

		var previewFor = {};
		previews.forEach( function ( preview ) {
			previewFor[ preview.id ] = preview;
		} );

		var decisions = {};
		var remaining = pending.gated.length;

		pending.calls.forEach( function ( call ) {
			if ( pending.gated.indexOf( call.id ) === -1 ) {
				return;
			}

			var card = el( 'div', 'counterhand-confirm' );
			card.appendChild( el( 'p', 'counterhand-confirm__title', i18n.approvalTitle || 'Approve this change?' ) );
			card.appendChild( el( 'p', 'counterhand-confirm__hint', i18n.approvalHint || 'The assistant wants to run this. Nothing happens until you decide.' ) );
			card.appendChild( el( 'code', 'counterhand-tool__name', call.name ) );

			if ( previewFor[ call.id ] ) {
				card.appendChild( previewTable( previewFor[ call.id ] ) );
			} else if ( call.arguments && Object.keys( call.arguments ).length ) {
				card.appendChild( el( 'pre', 'counterhand-tool__code', JSON.stringify( call.arguments, null, 2 ) ) );
			}

			var actions = el( 'div', 'counterhand-confirm__actions' );
			var approve = el( 'button', 'button button-primary', i18n.approve || 'Approve' );
			var decline = el( 'button', 'button', i18n.decline || 'Decline' );
			approve.type = 'button';
			decline.type = 'button';
			actions.appendChild( approve );
			actions.appendChild( decline );
			card.appendChild( actions );

			function decide( approved ) {
				decisions[ call.id ] = approved;
				remaining -= 1;

				card.classList.add( 'counterhand-confirm--decided' );
				actions.replaceWith( el(
					'p',
					'counterhand-confirm__decision',
					approved ? ( i18n.approved || 'Approved' ) : ( i18n.declined || 'Declined' )
				) );

				if ( remaining === 0 ) {
					resume( pending.key, decisions );
				}
			}

			approve.addEventListener( 'click', function () { decide( true ); } );
			decline.addEventListener( 'click', function () { decide( false ); } );

			log.appendChild( card );
		} );

		status.textContent = i18n.awaiting || 'Waiting for your approval…';
		scrollToEnd();
	}

	function exchange( body ) {
		var row = typingRow();
		setBusy( true );

		post( body )
			.then( function ( payload ) {
				row.remove();
				return render( payload );
			} )
			.catch( function ( error ) {
				row.remove();
				addBubble( 'assistant', String( error ), false ).classList.add( 'counterhand-msg--error' );
				return false;
			} )
			.then( function ( awaiting ) {
				if ( ! awaiting ) {
					setBusy( false );
					showTotals();
					input.focus();
				}
			} );
	}

	/** A playbook shows its title and sends its instructions, which the model reads and the person need not. */
	function send( text, shown ) {
		addBubble( 'user', shown || text );

		var body = new URLSearchParams();
		body.set( 'action', 'counterhand_chat_send' );
		body.set( 'message', text );

		exchange( body );
	}

	function resume( key, decisions ) {
		var body = new URLSearchParams();
		body.set( 'action', 'counterhand_chat_resume' );
		body.set( 'pending_key', key );
		body.set( 'decisions', JSON.stringify( decisions ) );

		exchange( body );
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
		var target = event.target;

		if ( target.classList.contains( 'counterhand-chat__suggestion' ) && ! sendButton.disabled ) {
			send( target.dataset.message || target.textContent.trim(), target.textContent.trim() );
		}

		if ( target.classList.contains( 'counterhand-chat__saved-ask' ) && ! sendButton.disabled ) {
			send( target.textContent.trim() );
		}

		if ( target.classList.contains( 'counterhand-chat__saved-forget' ) ) {
			savePrompt( 'counterhand_chat_forget_prompt', target.parentNode.querySelector( '.counterhand-chat__saved-ask' ).textContent.trim() );
		}
	} );

	function drawSaved( prompts ) {
		if ( ! savedList ) {
			return;
		}

		savedList.textContent = '';
		prompts.forEach( function ( prompt ) {
			var item = el( 'li' );
			var ask = el( 'button', 'counterhand-chat__saved-ask', prompt );
			var forget = el( 'button', 'counterhand-chat__saved-forget', '×' );
			ask.type = 'button';
			forget.type = 'button';
			forget.setAttribute( 'aria-label', i18n.forget || 'Forget this question' );
			item.appendChild( ask );
			item.appendChild( forget );
			savedList.appendChild( item );
		} );
	}

	function savePrompt( action, prompt ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'prompt', prompt );
		body.set( '_ajax_nonce', form.dataset.promptsNonce );

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
				if ( payload.success ) {
					drawSaved( payload.data.prompts || [] );
				}
			} );
	}

	if ( saveButton ) {
		saveButton.addEventListener( 'click', function () {
			var text = input.value.trim();

			if ( ! text ) {
				status.textContent = i18n.nothingToSave || 'Type a question first, then save it.';
				return;
			}

			savePrompt( 'counterhand_chat_save_prompt', text );
		} );
	}

	if ( resetButton ) {
		resetButton.addEventListener( 'click', function () {
			history = [];
			entries = [];
			totals = { input: 0, output: 0 };
			forgetKept();
			log.innerHTML = '';
			status.textContent = '';
			input.focus();
		} );
	}

	// Before a model is connected the log holds the setup form, not a conversation.
	if ( storageKey && ! input.disabled ) {
		restore();
	}
}() );
