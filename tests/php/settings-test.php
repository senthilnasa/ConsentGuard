<?php
/**
 * Tests for Settings sanitization and merging.
 *
 * @package PCM
 */

use PCM\Settings;
use PHPUnit\Framework\TestCase;

class Settings_Test extends TestCase {

	protected function setUp(): void {
		pcm_test_reset();
	}

	public function test_defaults_contain_builtin_categories() {
		$defaults = Settings::defaults();
		foreach ( array( 'necessary', 'functional', 'analytics', 'marketing', 'preferences' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $defaults['categories'] );
		}
		$this->assertTrue( $defaults['categories']['necessary']['required'] );
	}

	public function test_sanitize_drops_unknown_sections() {
		$out = Settings::sanitize( array( 'evil' => array( 'x' => 1 ) ) );
		$this->assertArrayNotHasKey( 'evil', $out );
	}

	public function test_sanitize_ga4() {
		$out = Settings::sanitize(
			array(
				'ga4' => array(
					'enabled'        => '1',
					'measurement_id' => 'g-abc1234567',
					'category'       => 'Analytics!!',
				),
			)
		);
		$this->assertTrue( $out['ga4']['enabled'] );
		$this->assertSame( 'G-ABC1234567', $out['ga4']['measurement_id'] );
		$this->assertSame( 'analytics', $out['ga4']['category'] );
	}

	public function test_sanitize_rejects_invalid_measurement_id() {
		$out = Settings::sanitize( array( 'ga4' => array( 'measurement_id' => '"><script>alert(1)</script>' ) ) );
		$this->assertSame( '', $out['ga4']['measurement_id'] );
	}

	public function test_sanitize_banner_colors_fall_back() {
		$out = Settings::sanitize( array( 'banner' => array( 'primary_color' => 'javascript:alert(1)' ) ) );
		$this->assertSame( '#1a73e8', $out['banner']['primary_color'] );
	}

	public function test_sanitize_categories_never_drops_necessary() {
		$out = Settings::sanitize(
			array(
				'categories' => array(
					'custom' => array(
						'label'    => 'Custom',
						'required' => '1',
					),
				),
			)
		);
		$this->assertArrayHasKey( 'necessary', $out['categories'] );
		$this->assertTrue( $out['categories']['necessary']['required'] );
		$this->assertArrayHasKey( 'custom', $out['categories'] );
		$this->assertFalse( $out['categories']['custom']['builtin'] );
	}

	public function test_sanitize_custom_scripts_requires_name_and_code() {
		$out = Settings::sanitize(
			array(
				'custom_scripts' => array(
					array( 'name' => '', 'code' => '<script>a()</script>' ),
					array( 'name' => 'Pixel', 'code' => '<script>b()</script>', 'category' => 'marketing', 'position' => 'header', 'enabled' => '1' ),
				),
			)
		);
		$this->assertCount( 1, $out['custom_scripts'] );
		$this->assertSame( 'Pixel', $out['custom_scripts'][0]['name'] );
		$this->assertSame( 'header', $out['custom_scripts'][0]['position'] );
	}

	public function test_sanitize_blocker_domains() {
		$out = Settings::sanitize(
			array(
				'blocker' => array(
					'enabled'   => '1',
					'domains'   => array(
						'clarity.ms'  => 'analytics',
						'bad domain!' => 'analytics',
					),
					'allowlist' => array( 'cdn.example.com', 'nope nope' ),
				),
			)
		);
		$this->assertArrayHasKey( 'clarity.ms', $out['blocker']['domains'] );
		$this->assertArrayNotHasKey( 'bad domain!', $out['blocker']['domains'] );
		$this->assertSame( array( 'cdn.example.com' ), $out['blocker']['allowlist'] );
	}

	public function test_sanitize_blocker_domains_plain_list_defaults_to_analytics() {
		$out = Settings::sanitize(
			array(
				'blocker' => array( 'domains' => array( 'tracker.example.com' ) ),
			)
		);
		$this->assertSame( array( 'tracker.example.com' => 'analytics' ), $out['blocker']['domains'] );
	}

	public function test_sanitize_jurisdiction_rules() {
		$out = Settings::sanitize(
			array(
				'jurisdictions' => array(
					'rules' => array(
						'IN'  => 'dpdp',
						'xx1' => 'gdpr',
					),
				),
			)
		);
		$this->assertSame( array( 'IN' => 'dpdp' ), $out['jurisdictions']['rules'] );
	}

	public function test_merge_recursive_saved_scalars_win() {
		$merged = Settings::merge_recursive(
			array( 'a' => array( 'b' => 1, 'c' => 2 ) ),
			array( 'a' => array( 'b' => 9 ) )
		);
		$this->assertSame( 9, $merged['a']['b'] );
		$this->assertSame( 2, $merged['a']['c'] );
	}

	public function test_update_section_replaces_custom_scripts() {
		$settings = Settings::instance();
		$settings->update_section( 'custom_scripts', array( array( 'id' => 'a', 'name' => 'A', 'category' => 'marketing', 'position' => 'footer', 'enabled' => true, 'code' => 'x' ) ) );
		$settings->update_section( 'custom_scripts', array() );
		$this->assertSame( array(), $settings->get( 'custom_scripts' ) );
	}

	public function test_sanitize_is_presence_based() {
		// A form carrying only one consent field must not reset the others.
		$out = Settings::sanitize( array( 'consent' => array( 'banner_enabled' => '1' ) ) );
		$this->assertSame( array( 'banner_enabled' => true ), $out['consent'] );

		// Unchecked checkbox posts an empty string via its hidden companion.
		$out = Settings::sanitize( array( 'consent' => array( 'store_records' => '' ) ) );
		$this->assertSame( array( 'store_records' => false ), $out['consent'] );
	}

	public function test_partial_update_preserves_other_section_values() {
		$settings = Settings::instance();
		$settings->update_section( 'consent', array( 'consent_version' => '3.0' ) );
		$settings->update_section( 'consent', Settings::sanitize( array( 'consent' => array( 'banner_enabled' => '' ) ) )['consent'] );
		$this->assertSame( '3.0', $settings->get( 'consent.consent_version' ) );
		$this->assertFalse( $settings->get( 'consent.banner_enabled' ) );
	}

	public function test_sanitize_advanced_respect_gpc() {
		$out = Settings::sanitize( array( 'advanced' => array( 'respect_gpc' => '1' ) ) );
		$this->assertTrue( $out['advanced']['respect_gpc'] );
		$this->assertArrayNotHasKey( 'debug', $out['advanced'] );
	}

	public function test_profile_mode_is_validated() {
		$out = Settings::sanitize(
			array(
				'jurisdictions' => array(
					'profiles' => array(
						'us' => array( 'label' => 'US', 'mode' => 'opt_out' ),
						'xx' => array( 'label' => 'XX', 'mode' => 'evil' ),
					),
				),
			)
		);
		$this->assertSame( 'opt_out', $out['jurisdictions']['profiles']['us']['mode'] );
		$this->assertSame( 'opt_in', $out['jurisdictions']['profiles']['xx']['mode'] );
	}

	public function test_sanitize_cookie_inventory() {
		$out = Settings::sanitize(
			array(
				'cookies' => array(
					'analytics' => array(
						array( 'name' => '_ga', 'duration' => '1 year', 'description' => '<b>GA</b> cookie' ),
						array( 'name' => '', 'duration' => 'x', 'description' => 'dropped' ),
					),
					'Bad Cat!'  => array( array( 'name' => 'x' ) ),
				),
			)
		);
		$this->assertCount( 1, $out['cookies']['analytics'] );
		$this->assertSame( '_ga', $out['cookies']['analytics'][0]['name'] );
		$this->assertStringNotContainsString( '<b>', $out['cookies']['analytics'][0]['description'] );
		$this->assertArrayHasKey( 'badcat', $out['cookies'] );
	}

	public function test_sanitize_iframe_domains() {
		$out = Settings::sanitize(
			array(
				'blocker' => array(
					'iframe_domains' => array(
						'youtube.com' => 'functional',
						'bad domain'  => 'functional',
					),
				),
			)
		);
		$this->assertSame( array( 'youtube.com' => 'functional' ), $out['blocker']['iframe_domains'] );
	}

	public function test_sanitize_translations() {
		$out = Settings::sanitize(
			array(
				'translations' => array(
					'ta_IN'      => array(
						'banner'     => array(
							'title' => 'உங்கள் தனியுரிமையை மதிக்கிறோம்',
							'evil'  => 'dropped',
						),
						'categories' => array(
							'analytics' => array( 'label' => 'பகுப்பாய்வு' ),
						),
					),
					'bad locale!' => array( 'banner' => array( 'title' => 'x' ) ),
				),
			)
		);
		$this->assertSame( 'உங்கள் தனியுரிமையை மதிக்கிறோம்', $out['translations']['ta_IN']['banner']['title'] );
		$this->assertArrayNotHasKey( 'evil', $out['translations']['ta_IN']['banner'] );
		$this->assertSame( 'பகுப்பாய்வு', $out['translations']['ta_IN']['categories']['analytics']['label'] );
		$this->assertCount( 1, $out['translations'] );
	}

	public function test_sanitize_reopen_widget_settings() {
		$out = Settings::sanitize(
			array(
				'banner' => array(
					'reopen_position' => 'top-right',
					'reopen_icon_url' => 'https://example.test/icon.png',
					'reopen_draggable' => '1',
				),
			)
		);
		$this->assertSame( 'top-right', $out['banner']['reopen_position'] );
		$this->assertSame( 'https://example.test/icon.png', $out['banner']['reopen_icon_url'] );
		$this->assertTrue( $out['banner']['reopen_draggable'] );

		$out = Settings::sanitize( array( 'banner' => array( 'reopen_position' => 'middle' ) ) );
		$this->assertSame( 'bottom-left', $out['banner']['reopen_position'] );
	}

	public function test_banner_theme_is_validated() {
		$out = Settings::sanitize( array( 'banner' => array( 'theme' => 'dark' ) ) );
		$this->assertSame( 'dark', $out['banner']['theme'] );
		$out = Settings::sanitize( array( 'banner' => array( 'theme' => 'neon' ) ) );
		$this->assertSame( 'light', $out['banner']['theme'] );
	}

	public function test_retention_bounds() {
		$out = Settings::sanitize( array( 'consent' => array( 'retention_days' => 5 ) ) );
		$this->assertSame( 30, $out['consent']['retention_days'] );
		$out = Settings::sanitize( array( 'consent' => array( 'retention_days' => 99999 ) ) );
		$this->assertSame( 3650, $out['consent']['retention_days'] );
	}
}
