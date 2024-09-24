workflows.editor.element.registry.register( 'approve_revision', { // eslint-disable-line no-undef
	isUserActivity: false,
	class: 'activity-approve-page activity-bootstrap-icon',
	label: mw.message( 'contentstabilization-ui-workflows-inspector-activity-approve-page-title' ).text(),
	defaultData: {
		properties: {
			user: '',
			revision: '',
			comment: ''
		}
	}
} );
