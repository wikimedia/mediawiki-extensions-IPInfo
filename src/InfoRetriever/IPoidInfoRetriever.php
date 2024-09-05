<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\IPInfo\Info\IPoidInfo;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;

/**
 * Manager for getting information from the iPoid service.
 */
class IPoidInfoRetriever extends BaseInfoRetriever {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoIpoidUrl',
	];

	private ServiceOptions $options;

	private HttpRequestFactory $httpRequestFactory;

	private LoggerInterface $logger;

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

	/** @inheritDoc */
	public function getName(): string {
		return 'ipinfo-source-ipoid';
	}

	/**
	 * @inheritDoc
	 * @return IPoidInfo
	 */
	public function retrieveFor( UserIdentity $user, ?string $ip ): IPoidInfo {
		if ( $ip === null ) {
			return new IPoidInfo();
		}

		if ( $this->options->get( 'IPInfoIpoidUrl' ) ) {
			$data = $this->getData( $ip );

			return self::makeIPoidInfo( $data );
		}

		return new IPoidInfo();
	}

	/**
	 * Convert raw data from the IPoid service into an {@link IPoidInfo} object.
	 * @param array $data IP data returned by IPInfo
	 * @return IPoidInfo
	 */
	private static function makeIPoidInfo( array $data ): IPoidInfo {
		$info = [
			'behaviors' => $data['behaviors'] ?? null,
			'risks' => $data['risks'] ?? null,
			'connectionTypes' => $data['types'] ?? null,
			'tunnelOperators' => $data['tunnels'] ?? null,
			'proxies' => $data['proxies'] ?? null,
			'numUsersOnThisIP' => $data['client_count'] ?? null,
		];

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
	 * Retrieve IP information for the given IPs from the IPoid service.
	 * @param UserIdentity $user
	 * @param string[] $ips IP addresses in human-readable form
	 * @return IPoidInfo[] Map of IPoidInfo instances keyed by IP address
	 */
	public function retrieveBatch( UserIdentity $user, array $ips ): array {
		$reqs = [];

		foreach ( $ips as $ip ) {
			$reqs[] = [
				'url' => $this->getFeedEndpointUrl( $ip ),
				'method' => 'GET'
			];
		}

		$httpClient = $this->httpRequestFactory->createMultiClient();
		$reqs = $httpClient->runMulti( $reqs );
		$infoByIp = [];

		foreach ( $reqs as $i => $req ) {
			$ip = $ips[$i];
			if ( $req['response']['code'] === 200 ) {
				$data = $this->processRequestBody( $ip, $req['response']['body'] );
				$infoByIp[$ip] = self::makeIPoidInfo( $data );
			} else {
				$infoByIp[$ip] = new IPoidInfo();
			}
		}

		return $infoByIp;
	}

	/**
	 * Call the iPoid API to get data for an IP address.
	 *
	 * @param string $ip
	 * @return mixed[] Data returned by iPoid
	 */
	private function getData( string $ip ): array {
		$url = $this->getFeedEndpointUrl( $ip );
		$request = $this->httpRequestFactory->create( $url, [ 'method' => 'GET' ] );
		$response = $request->execute();

		if ( $response->isOK() ) {
			return $this->processRequestBody( $ip, $request->getContent() );
		}

		return [];
	}

	/**
	 * Get the IPoid feed endpoint URL for looking up IP data.
	 * @param string $ip The IP to look up data for
	 * @return string Fully qualified endpoint URL
	 */
	private function getFeedEndpointUrl( string $ip ): string {
		$baseUrl = $this->options->get( 'IPInfoIpoidUrl' );
		return $baseUrl . '/feed/v1/ip/' . $ip;
	}

	/**
	 * Parse the given HTTP request body, which is assumed to hold data for the given IP.
	 *
	 * @param string $ip The IP to fetch data for
	 * @param string $body HTTP response body
	 * @return array Parsed response data for the given IP, or empty array if no data was available.
	 */
	private function processRequestBody( string $ip, string $body ): array {
		$content = json_decode( $body, true );
		if ( is_array( $content ) ) {
			$sanitizedIp = IPUtils::sanitizeIP( $ip );
			foreach ( $content as $key => $value ) {
				if ( $sanitizedIp === IPUtils::sanitizeIP( $key ) ) {
					return $value;
				}
			}

			return [];
		}

		$this->logger->debug(
			"ipoid results were not in the expected format: " . $body
		);

		return [];
	}

}
