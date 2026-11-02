( function () {
	var view = window.counterhandView;

	function shown( value ) {
		if ( null === value || undefined === value || '' === value ) {
			return view.text.empty;
		}

		return 'object' === typeof value ? JSON.stringify( value ) : String( value );
	}

	view.render( function ( root, data ) {
		var text = view.text;
		var table;
		var head;

		if ( ! data.preview ) {
			root.appendChild( view.element( 'h1', '', text.savedTitle ) );
			root.appendChild( view.figures( [ [ ( data.product && data.product.name ) || '', view.money( data.product && data.product.price ) ] ] ) );
			return;
		}

		root.appendChild( view.element( 'h1', '', text.previewTitle ) );
		table = view.element( 'table' );
		head = view.element( 'tr' );
		[ text.field, text.before, text.after ].forEach( function ( label ) {
			var cell = view.element( 'th', '', label );
			cell.scope = 'col';
			head.appendChild( cell );
		} );
		table.appendChild( head );

		( data.changes || [] ).forEach( function ( change ) {
			var row = view.element( 'tr' );
			row.appendChild( view.element( 'td', '', change.field ) );
			row.appendChild( view.element( 'td', '', shown( change.before ) ) );
			row.appendChild( view.element( 'td', '', shown( change.after ) ) );
			table.appendChild( row );
		} );
		root.appendChild( table );
	} );
}() );
