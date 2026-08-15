<?php
/**
 * Tests for jurisdiction resolution.
 *
 * @package PCM
 */

use PCM\Geolocation_Manager;
use PHPUnit\Framework\TestCase;

class Geolocation_Test extends TestCase {

	/**
	 * Manager under test.
	 *
	 * @var Geolocation_Manager
	 */
	private $geo;

	protected function setUp(): void {
		pcm_test_reset();
		unset( $_SERVER['HTTP_CF_IPCOUNTRY'], $_SERVER['GEOIP_COUNTRY_CODE'] );
		$this->geo = new Geolocation_Manager();
	}

	private function enable_geo() {
		update_option( 'pcm_settings', array( 'jurisdictions' => array( 'geo_enabled' => true ) ) );
		PCM\Settings::instance()->flush_cache();
	}

	public function test_detection_disabled_by_default() {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';
		$this->assertSame( '', $this->geo->detect_country() );
	}

	public function test_detects_cloudflare_header() {
		$this->enable_geo();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'IN';
		$this->assertSame( 'IN', $this->geo->detect_country() );
	}

	public function test_ignores_unknown_placeholder() {
		$this->enable_geo();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
		$this->assertSame( '', $this->geo->detect_country() );
	}

	public function test_india_maps_to_dpdp_profile() {
		$resolved = $this->geo->resolve_profile( 'IN' );
		$this->assertSame( 'dpdp', $resolved['key'] );
	}

	public function test_eu_country_maps_to_gdpr() {
		$resolved = $this->geo->resolve_profile( 'FR' );
		$this->assertSame( 'gdpr', $resolved['key'] );
	}

	public function test_uk_maps_to_uk_gdpr() {
		$resolved = $this->geo->resolve_profile( 'GB' );
		$this->assertSame( 'uk_gdpr', $resolved['key'] );
	}

	public function test_unknown_country_gets_default_profile() {
		$resolved = $this->geo->resolve_profile( 'US' );
		$this->assertSame( 'gdpr', $resolved['key'], 'Default profile is the strict gdpr profile out of the box.' );
	}

	public function test_no_country_gets_default_profile() {
		$resolved = $this->geo->resolve_profile( '' );
		$this->assertSame( 'gdpr', $resolved['key'] );
		$this->assertTrue( $resolved['profile']['require_consent'] );
	}
}
