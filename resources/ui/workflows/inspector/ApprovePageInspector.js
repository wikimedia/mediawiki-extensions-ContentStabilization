window.ext.contentStabilization.ui.workflows = ext.contentStabilization.ui.workflows || {};
window.ext.contentStabilization.ui.workflows.inspector = ext.contentStabilization.ui.workflows.inspector || {};

ext.contentStabilization.ui.workflows.inspector.ApprovePageInspector = function ( element, dialog ) {
	ext.contentStabilization.ui.workflows.inspector.ApprovePageInspector.parent.call( this, element, dialog );
};

OO.inheritClass( ext.contentStabilization.ui.workflows.inspector.ApprovePageInspector, workflows.editor.inspector.ActivityInspector ); // eslint-disable-line no-undef

ext.contentStabilization.ui.workflows.inspector.ApprovePageInspector.prototype.getDialogTitle = function () {
	return mw.message( 'contentstabilization-ui-workflows-inspector-activity-approve-page-title' ).text();
};

ext.contentStabilization.ui.workflows.inspector.ApprovePageInspector.prototype.getItems = function () {
	return [
		{
			type: 'section_label',
			title: mw.message( 'workflows-ui-editor-inspector-properties' ).text()
		},
		{
			type: 'text',
			name: 'properties.user',
			label: mw.message( 'contentstabilization-ui-workflows-inspector-activity-approve-page-property-user' ).text(),
			help: mw.message( 'contentstabilization-ui-workflows-inspector-activity-approve-page-property-user-help' ).text(),
			required: true
		},
		{
			type: 'text',
			name: 'properties.revision',
			hidden: true
		},
		{
			type: 'text',
			name: 'properties.comment',
			label: mw.message( 'contentstabilization-ui-workflows-inspector-activity-approve-page-property-comment' ).text()
		}
	];
};

workflows.editor.inspector.Registry.register( 'approve_revision', ext.contentStabilization.ui.workflows.inspector.ApprovePageInspector ); // eslint-disable-line no-undef
