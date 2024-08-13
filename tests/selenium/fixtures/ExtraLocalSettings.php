<?php
/**
 * Settings overrides for IPInfo WDIO tests, for use in WMF CI.
 */

$wgGroupPermissions['sysop']['ipinfo'] = true;
$wgGroupPermissions['sysop']['ipinfo-view-basic'] = true;
$wgGroupPermissions['sysop']['ipinfo-view-full'] = true;
$wgGroupPermissions['sysop']['ipinfo-view-log'] = true;

$wgIPInfoGeoLite2Prefix = realpath( __DIR__ ) . '/maxmind/GeoLite2-';
