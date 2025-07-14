<?php

namespace MediaWiki\IPInfo\Maintenance;

use BackupReader;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\RequestContext;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Helper script to setup required test fixtures for WDIO browser tests.
 */
class PopulateTestData extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Import test data for use in WDIO E2E tests' );
		$this->addOption( 'ip', 'Test IP to associate with temp user edits', true, true );
		$this->addOption( 'temp-name', 'The temporary user name to use', true, true );
		$this->requireExtension( 'IPInfo' );
	}

	public function execute() {
		if ( !$this->getServiceContainer()->getTempUserConfig()->isEnabled() ) {
			$this->fatalError( 'Setting up test data requires temporary user support to be active.' );
		}

		$this->output( "Importing IP edit fixture..\n" );

		$importDump = $this->runChild( BackupReader::class );
		$importDump->setArg( 0, __DIR__ . '/../tests/fixtures/ip-edit-fixture.xml' );
		$importDump->execute();

		$this->output( "Creating test page as temporary user...\n" );

		$req = new FauxRequest();
		$req->setIP( $this->getOption( 'ip' ) );

		RequestContext::getMain()->setRequest( $req );

		$tempName = $this->getOption( 'temp-name' );

		if ( !$this->getServiceContainer()->getTempUserConfig()->isTempName( $tempName ) ) {
			$this->fatalError( "The given name \"$tempName\" is not a valid temporary user name.\n" );
		}

		$tempUser = $this->getServiceContainer()
			->getUserIdentityLookup()
			->getUserIdentityByName( $tempName );

		if ( $tempUser === null ) {
			$tempUser = $this->getServiceContainer()
				->getTempUserCreator()
				->create( $tempName, $req )
				->getUser();
		}

		$page = Title::makeTitle( NS_MAIN, 'IPInfo Temp User Test' );
		$content = $this->getServiceContainer()
			->getContentHandlerFactory()
			->getContentHandler( CONTENT_MODEL_WIKITEXT )
			->unserializeContent( 'test content' );

		$pageUpdater = $this->getServiceContainer()
			->getPageUpdaterFactory()
			->newPageUpdater( $page, $tempUser )
			->setContent( SlotRecord::MAIN, $content );

		$pageUpdater->saveRevision( CommentStoreComment::newUnsavedComment( 'test' ) );

		if ( !$pageUpdater->wasSuccessful() ) {
			$this->fatalError( $pageUpdater->getStatus() );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateTestData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
