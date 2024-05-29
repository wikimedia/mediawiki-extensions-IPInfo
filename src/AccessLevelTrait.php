<?php

namespace MediaWiki\IPInfo;

use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;

trait AccessLevelTrait {
	/**
	 * Get the highest access level user has permissions for.
	 *
	 * @param string[] $permissions
	 * @return ?string null if the user has no rights to see IP information
	 */
	public function highestAccessLevel( array $permissions ): ?string {
		// An ordered list of the access levels for viewing IP information, ordered
		// from lowest to highest level.
		// Should be kept up to date with DefaultPresenter::VIEWING_RIGHTS
		$levels = [
			DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT,
			DefaultPresenter::IPINFO_VIEW_FULL_RIGHT,
		];

		$highestLevel = null;
		foreach ( $levels as $level ) {
			if ( in_array( $level, $permissions ) ) {
				$highestLevel = $level;
			}
		}

		return $highestLevel;
	}
}
