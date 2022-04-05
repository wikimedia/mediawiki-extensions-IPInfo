<?php

namespace MediaWiki\IPInfo;

trait AccessLevelTrait {
	/**
	 * Get the highest access level user has permissions for.
	 *
	 * @param string[] $permissions
	 * @return ?string null if the user has no rights to see IP information
	 */
	public function highestAccessLevel( array $permissions ): ?string {
		// An ordered list of the access levels for viewing IP infomation, ordered
		// from lowest to highest level.
		// Should be kept up-to-date with DefaultPresenter::VIEWING_RIGHTS
		$levels = [
			'ipinfo-view-basic',
			'ipinfo-view-full',
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
