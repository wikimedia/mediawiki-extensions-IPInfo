( function () {
	var ipInfoEnabledCheck = OO.ui.infuse( $( '#mw-input-wpipinfo-enable' ) ),
		ipInfoUseAgreementCheck = OO.ui.infuse( $( '#mw-input-wpipinfo-use-agreement' ) );

	function setInputStates() {
		if ( !ipInfoEnabledCheck.isSelected() ) {
			ipInfoUseAgreementCheck.setDisabled( true );
			ipInfoUseAgreementCheck.setSelected( false );
		} else {
			ipInfoUseAgreementCheck.setDisabled( false );
		}
	}

	setInputStates();
	ipInfoEnabledCheck.on( 'change', setInputStates );
}() );
