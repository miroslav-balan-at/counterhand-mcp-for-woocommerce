( function () {
	var view = window.counterhandView;

	view.render( function ( root, data ) {
		var report = ( data.sales && data.sales[ 0 ] ) || {};
		var text = view.text;

		root.appendChild( view.element( 'h1', '', text.salesTitle ) );
		root.appendChild( view.figures( [
			[ text.totalSales, view.money( report.total_sales ) ],
			[ text.netSales, view.money( report.net_sales ) ],
			[ text.orders, report.total_orders ],
			[ text.itemsSold, report.total_items ],
			[ text.refunds, view.money( report.total_refunds ) ],
		] ) );
	} );
}() );
