<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use Exception;
use GeoIp2\Database\Reader;
use MediaWiki\Languages\LanguageFallback;
use RequestContext;

class ReaderFactory {

	/** @var Reader[] Map of filename to Reader object */
	private $readers = [];

	/** @var LanguageFallback */
	private $languageFallback;

	/**
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		LanguageFallback $languageFallback
	) {
		$this->languageFallback = $languageFallback;
	}

	/**
	 * @param string $path
	 * @param string $filename
	 * @return Reader|null null if the file path or file is invalid
	 */
	public function get( string $path, string $filename ): ?Reader {
		if ( isset( $this->readers[$filename] ) ) {
			return $this->readers[$filename];
		}
		try {
			$reader = $this->getReader( $path . $filename );
		} catch ( Exception $e ) {
			return null;
		}

		$this->readers[$filename] = $reader;

		return $this->readers[$filename];
	}

	/**
	 * @param string $filename
	 * @return Reader
	 * @throws Exception
	 */
	protected function getReader( $filename ) {
		// TODO: Pending a resolution to T318726?
		$userLang = RequestContext::getMain()->getLanguage()->getCode();
		$langCodes = array_merge(
			[ $userLang ],
			$this->languageFallback->getAll( $userLang )
		);
		return new Reader( $filename, $langCodes );
	}
}
