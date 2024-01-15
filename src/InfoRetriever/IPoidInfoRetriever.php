<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\IPInfo\Info\IPoidInfo;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;

/**
 * Manager for getting information from the IPoid service.
 */
class IPoidInfoRetriever implements InfoRetriever {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoIpoidUrl',
	];

	/** @var ServiceOptions */
	private $options;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ServiceOptions $options
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ServiceOptions $options,
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'ipinfo-source-ipoid';
	}

	/**
	 * @inheritDoc
	 * @return IPoidInfo
	 */
	public function retrieveFromIP( string $ip ): IPoidInfo {
		$info = array_fill_keys(
			[
				'behaviors',
				'risks',
				'connectionTypes',
				'tunnelOperators',
				'proxies',
				'numUsersOnThisIP',
			],
			null
		);

		if ( $this->options->get( 'IPInfoIpoidUrl' ) ) {
			$data = $this->getData( $ip );

			$info['behaviors'] = $data['behaviors'] ?? null;
			$info['risks'] = $data['risks'] ?? null;
			$info['connectionTypes'] = $data['types'] ?? null;
			$info['tunnelOperators'] = $data['tunnels'] ?? null;
			$info['proxies'] = $data['proxies'] ?? null;
			$info['numUsersOnThisIP'] = $data['client_count'] ?? null;
		}

		return new IPoidInfo(
			$info['behaviors'],
			$info['risks'],
			$info['connectionTypes'],
			$info['tunnelOperators'],
			$info['proxies'],
			$info['numUsersOnThisIP'],
		);
	}

	/**
	 * Call the ipoid API to get data for an IP address.
	 *
	 * @param string $ip
	 * @return mixed[] Data returned by ipoid
	 */
	private function getData( string $ip ) {
		$data = [];

		$baseUrl = $this->options->get( 'IPInfoIpoidUrl' );
		$url = $baseUrl . '/feed/v1/ip/' . $ip;
		$request = $this->httpRequestFactory->create( $url, [ 'method' => 'GET' ] );
		$response = $request->execute();

		if ( $response->isOK() ) {
			$content = json_decode( $request->getContent(), true );
			$ipInIpoidFormat = IPUtils::prettifyIP( $ip );
			if ( is_array( $content ) && is_array( $content[$ipInIpoidFormat] ) ) {
				$data = $content[$ipInIpoidFormat];
			} else {
				$this->logger->debug(
					"ipoid results were not in the expected format: " . $request->getContent()
				);
			}
		}

		return $data;
	}

}
