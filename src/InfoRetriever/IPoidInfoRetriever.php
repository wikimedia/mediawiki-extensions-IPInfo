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
class IPoidInfoRetriever implements InfoRetriever {
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
	public function retrieveFor( UserIdentity $user ): IPoidInfo {
		$ip = $user->getName();

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
	 * Call the iPoid API to get data for an IP address.
	 *
	 * @param string $ip
	 * @return mixed[] Data returned by iPoid
	 */
	private function getData( string $ip ): array {
		$data = [];

		$baseUrl = $this->options->get( 'IPInfoIpoidUrl' );
		$url = $baseUrl . '/feed/v1/ip/' . $ip;
		$request = $this->httpRequestFactory->create( $url, [ 'method' => 'GET' ] );
		$response = $request->execute();

		if ( $response->isOK() ) {
			$content = json_decode( $request->getContent(), true );
			if ( is_array( $content ) ) {
				$sanitizedIp = IPUtils::sanitizeIP( $ip );
				foreach ( $content as $key => $value ) {
					if ( $sanitizedIp === IPUtils::sanitizeIP( $key ) ) {
						$data = $value;
					}
				}
			} else {
				$this->logger->debug(
					"ipoid results were not in the expected format: " . $request->getContent()
				);
			}
		}

		return $data;
	}

}
