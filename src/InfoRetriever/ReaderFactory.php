<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use Exception;
use GeoIp2\Database\Reader;

class ReaderFactory {

	/** @var Reader[] Map of filename to the Reader object */
	private $readers = [];

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
	protected function getReader( string $filename ): Reader {
		return new Reader( $filename );
	}
}
