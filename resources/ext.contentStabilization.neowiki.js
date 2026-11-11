const approvalNoticeKey = 'contentstabilization-edit-notice-approval-needed';
const deleteDialogTitleKey = 'contentstabilization-neowiki-delete-confirm-title';
const noticeClass = 'ext-contentstabilization-neowiki-delete-notice';

function getApprovalNoticeText() {
	const approvalNotice = mw.msg( approvalNoticeKey );
	return approvalNotice === approvalNoticeKey ? '' : approvalNotice;
}

function isDeleteDialog( dialog ) {
	const title = mw.msg( deleteDialogTitleKey );
	if ( title === deleteDialogTitleKey ) {
		return false;
	}

	const titleElement = dialog.querySelector( '.cdx-dialog__header__title' );
	if ( !titleElement || !titleElement.textContent ) {
		return false;
	}

	return titleElement.textContent.trim() === title.trim();
}

function ensureDeleteDialogNotice( dialog ) {
	if ( !isDeleteDialog( dialog ) || dialog.querySelector( `.${ noticeClass }` ) ) {
		return;
	}

	const noticeText = getApprovalNoticeText();
	if ( !noticeText ) {
		return;
	}

	const body = dialog.querySelector( '.cdx-dialog__body' );
	if ( !body ) {
		return;
	}

	const notice = document.createElement( 'p' );
	notice.className = noticeClass;
	notice.textContent = noticeText;
	body.appendChild( notice );
}

function applyDeleteDialogNotice() {
	Array.prototype.forEach.call( document.querySelectorAll( '.cdx-dialog.ext-neowiki-ui' ), ( dialog ) => {
		ensureDeleteDialogNotice( dialog );
	} );
}

function watchForDeleteDialog() {
	applyDeleteDialogNotice();

	const observer = new MutationObserver( () => {
		applyDeleteDialogNotice();
	} );

	observer.observe( document.body, {
		childList: true,
		subtree: true
	} );
}

const pageName = mw.config.get( 'wgPageName' );
if (
	pageName &&
	window.ext && window.ext.contentStabilization && window.ext.contentStabilization.isEnabledForCurrentPage()
) {
	watchForDeleteDialog();
}
