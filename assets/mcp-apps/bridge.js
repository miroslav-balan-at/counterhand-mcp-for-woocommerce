/*
 * The MCP Apps conversation with the host, written out because a view has no build
 * step and may load nothing from elsewhere. Text only ever reaches the page through
 * textContent: these results hold what customers wrote.
 */
( function () {
	var settings = window.counterhandSettings;
	var root = document.getElementById( 'counterhand-view' );
	var pending = {};
	var nextId = 1;
	var renderer = null;
	var lastResult = null;
	var initialized = false;

	function send( message ) {
		message.jsonrpc = '2.0';
		window.parent.postMessage( message, '*' );
	}

	function request( method, params, done ) {
		var id = nextId++;
		pending[ id ] = done;
		send( { id: id, method: method, params: params } );
	}

	function element( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text && null !== text ) {
			node.textContent = String( text );
		}

		return node;
	}

	function reportSize() {
		send( {
			method: 'ui/notifications/size-changed',
			params: { width: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight },
		} );
	}

	function applyContext( context ) {
		var variables = context && context.styles && context.styles.variables;
		var insets = context && context.safeAreaInsets;
		var name;

		if ( context && context.theme ) {
			document.documentElement.setAttribute( 'data-theme', context.theme );
		}
		if ( variables ) {
			for ( name in variables ) {
				if ( Object.prototype.hasOwnProperty.call( variables, name ) && variables[ name ] ) {
					document.documentElement.style.setProperty( name, variables[ name ] );
				}
			}
		}
		if ( insets ) {
			document.body.style.padding = [ insets.top, insets.right, insets.bottom, insets.left ].map( function ( inset ) {
				return ( Number( inset ) || 0 ) + 'px';
			} ).join( ' ' );
		}
	}

	function resultText( result ) {
		var first = result && result.content && result.content[ 0 ];

		return first && 'text' === first.type ? first.text : settings.text.failed;
	}

	function show( result ) {
		lastResult = result;
		root.textContent = '';
		root.className = '';
		root.removeAttribute( 'aria-busy' );

		if ( result.isError || ! result.structuredContent || ! renderer ) {
			root.appendChild( element( 'p', 'counterhand-failure', resultText( result ) ) );
			return;
		}

		renderer( root, result.structuredContent );
	}

	window.addEventListener( 'message', function ( event ) {
		var message = event.data;

		if ( event.source !== window.parent || ! message || '2.0' !== message.jsonrpc ) {
			return;
		}
		if ( undefined !== message.id && ! message.method ) {
			if ( pending[ message.id ] && ! message.error ) {
				pending[ message.id ]( message.result );
			}
			delete pending[ message.id ];
			return;
		}
		if ( 'ui/notifications/tool-result' === message.method ) {
			show( message.params );
			reportSize();
		} else if ( 'ui/notifications/host-context-changed' === message.method ) {
			applyContext( message.params );
			reportSize();
		} else if ( undefined !== message.id ) {
			// A teardown or ping asks for an answer, and this view has nothing to clean up.
			send( { id: message.id, result: {} } );
		}
	} );

	window.counterhandView = {
		text: settings.text,
		element: element,
		money: function ( value, currency ) {
			var amount = Number( value );

			if ( null === value || undefined === value || '' === value || isNaN( amount ) ) {
				return '–';
			}
			try {
				return new Intl.NumberFormat( document.documentElement.lang || undefined, { style: 'currency', currency: currency || settings.currency } ).format( amount );
			} catch ( error ) {
				return String( value );
			}
		},
		figures: function ( rows ) {
			var list = element( 'dl', 'counterhand-figures' );

			rows.forEach( function ( row ) {
				var group = element( 'div' );
				group.appendChild( element( 'dt', '', row[ 0 ] ) );
				group.appendChild( element( 'dd', '', row[ 1 ] ) );
				list.appendChild( group );
			} );

			return list;
		},
		render: function ( callback ) {
			renderer = callback;
			if ( lastResult ) {
				show( lastResult );
			}
		},
	};

	request(
		'ui/initialize',
		{
			appInfo: { name: 'counterhand-mcp-for-woocommerce', version: settings.version },
			appCapabilities: { availableDisplayModes: [ 'inline' ] },
			protocolVersion: '2026-01-26',
		},
		function ( result ) {
			applyContext( result && result.hostContext );
			initialized = true;
			send( { method: 'ui/notifications/initialized', params: {} } );
			reportSize();
		}
	);

	if ( window.ResizeObserver ) {
		new window.ResizeObserver( function () {
			if ( initialized ) {
				reportSize();
			}
		} ).observe( document.documentElement );
	}
}() );
