ext.contentStabilization.ui.ApproveDialog = function ( config ) {
	ext.contentStabilization.ui.ApproveDialog.parent.call( this, config );
	this.page = config.page;
};

OO.inheritClass( ext.contentStabilization.ui.ApproveDialog, OO.ui.ProcessDialog );

ext.contentStabilization.ui.ApproveDialog.static.name = 'contentStabilizationApproveDialog';
ext.contentStabilization.ui.ApproveDialog.static.title = mw.msg( 'contentstabilization-ui-approve-title' );
ext.contentStabilization.ui.ApproveDialog.static.actions = [
	{ action: 'approve', label: mw.msg( 'contentstabilization-ui-approve-approve' ), flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: mw.msg( 'contentstabilization-ui-approve-cancel' ), flags: 'safe' }
];

ext.contentStabilization.ui.ApproveDialog.prototype.initialize = function () {
	ext.contentStabilization.ui.ApproveDialog.parent.prototype.initialize.call( this );
	var panel = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.$body.append( panel.$element );

	this.comment = new OO.ui.MultilineTextInputWidget( { rows: 3 } );

	panel.$element.append(
		new OO.ui.LabelWidget( { label: mw.msg( 'contentstabilization-ui-approve-notice' ) } ).$element,
		new OO.ui.FieldLayout( this.comment, { label: mw.msg( 'contentstabilization-ui-approve-comment' ) } ).$element
	);
};

ext.contentStabilization.ui.ApproveDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'approve' ) {
		return new OO.ui.Process( function () {
			var dfd = $.Deferred();
			this.pushPending();
			ext.contentStabilization.setStablePoint(
				this.page,
				this.comment.getValue()
			)
			.done( function () {
				this.close( { action: action } );
			}.bind( this ) )
			.fail( function () {
				this.popPending();
				dfd.reject( new OO.ui.Error(
					mw.msg( 'contentstabilization-ui-approve-error' ) ), { recoverable: false }
				);
			}.bind( this ) );
			return dfd.promise();
		}, this );
	}
	if ( action === 'cancel' ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	}
	return ext.contentStabilization.ui
		.ApproveDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.contentStabilization.ui.ApproveDialog.prototype.showErrors = function ( errors ) {
	ext.contentStabilization.ui.ApproveDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};

ext.contentStabilization.ui.ApproveDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight;
};
