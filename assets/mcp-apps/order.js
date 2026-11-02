( function () {
	var view = window.counterhandView;

	view.render( function ( root, data ) {
		var order = data.order || {};
		var text = view.text;
		var billing = order.billing || {};
		var customer = [ billing.first_name, billing.last_name ].filter( Boolean ).join( ' ' );
		var rows = [
			[ text.status, order.status ],
			[ text.total, view.money( order.total, order.currency ) ],
		];
		var items;

		if ( customer ) {
			rows.push( [ text.customer, customer ] );
		}

		root.appendChild( view.element( 'h1', '', text.orderTitle + ' #' + ( order.number || order.id ) ) );
		root.appendChild( view.figures( rows ) );

		if ( order.line_items && order.line_items.length ) {
			items = view.element( 'ul' );
			order.line_items.forEach( function ( item ) {
				var row = view.element( 'li' );
				row.appendChild( view.element( 'span', '', item.quantity + ' × ' + item.name ) );
				row.appendChild( view.element( 'span', '', view.money( item.total, order.currency ) ) );
				items.appendChild( row );
			} );
			root.appendChild( items );
		}

		if ( order.customer_note ) {
			root.appendChild( view.element( 'p', 'counterhand-note', text.customerNote + ': ' + order.customer_note ) );
		}
	} );
}() );
