ext.contentStabilization.ui.RemoveApprovalColumn = function ( cfg ) {
	ext.contentStabilization.ui.RemoveApprovalColumn.parent.call( this, cfg );
};

OO.inheritClass( ext.contentStabilization.ui.RemoveApprovalColumn, OOJSPlus.ui.data.column.Action );

ext.contentStabilization.ui.RemoveApprovalColumn.prototype.getViewControls = function( value, row ) {
	if ( !row.sp_approved ) {
		return '';
	}
	return ext.contentStabilization.ui.RemoveApprovalColumn.parent.prototype.getViewControls.call( this, value, row );
};

OOJSPlus.ui.data.registry.columnRegistry.register( 'remove-approval', ext.contentStabilization.ui.RemoveApprovalColumn );
