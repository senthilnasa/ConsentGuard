<?php
/**
 * Tests for consent domain logic.
 *
 * @package PCM
 */

use PCM\Consent_Manager;
use PCM\Consent_Storage;
use PHPUnit\Framework\TestCase;

class Consent_Manager_Test extends TestCase {

	/**
	 * Manager under test.
	 *
	 * @var Consent_Manager
	 */
	private $manager;

	protected function setUp(): void {
		pcm_test_reset();
		$this->manager = new Consent_Manager( new Consent_Storage() );
	}

	private function set_cookie( array $data ) {
		$_COOKIE['pcm_consent'] = rawurlencode( json_encode( $data ) );
	}

	public function test_no_cookie_means_no_consent() {
		$this->assertNull( $this->manager->get_request_consent() );
		$this->assertFalse( $this->manager->has_consent( 'analytics' ) );
		$this->assertFalse( $this->manager->has_consent( 'marketing' ) );
	}

	public function test_necessary_always_granted() {
		$this->assertTrue( $this->manager->has_consent( 'necessary' ) );
	}

	public function test_analytics_accepted() {
		$this->set_cookie(
			array(
				'version'    => '1.0',
				'categories' => array( 'analytics' => true ),
			)
		);
		$this->assertTrue( $this->manager->has_consent( 'analytics' ) );
		$this->assertFalse( $this->manager->has_consent( 'marketing' ) );
	}

	public function test_reject_all_rejects_optional_categories() {
		$this->set_cookie(
			array(
				'version'    => '1.0',
				'categories' => array(),
			)
		);
		$state = $this->manager->get_request_consent();
		$this->assertTrue( $state['necessary'] );
		$this->assertFalse( $state['functional'] );
		$this->assertFalse( $state['analytics'] );
		$this->assertFalse( $state['marketing'] );
		$this->assertFalse( $state['preferences'] );
	}

	public function test_consent_version_change_invalidates_consent() {
		$this->set_cookie(
			array(
				'version'    => '0.9',
				'categories' => array( 'analytics' => true ),
			)
		);
		$this->assertNull( $this->manager->get_request_consent() );
		$this->assertFalse( $this->manager->has_consent( 'analytics' ) );
	}

	public function test_malformed_cookie_is_ignored() {
		$_COOKIE['pcm_consent'] = 'not-json%%%';
		$this->assertNull( $this->manager->get_request_consent() );
	}

	public function test_sanitize_record_requires_categories() {
		$result = $this->manager->sanitize_record( array() );
		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_sanitize_record_rejects_invalid_anonymous_id() {
		$result = $this->manager->sanitize_record(
			array(
				'categories'   => array( 'analytics' => true ),
				'anonymous_id' => 'DROP TABLE users',
			)
		);
		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_sanitize_record_normalizes_builtins() {
		$record = $this->manager->sanitize_record(
			array(
				'categories'      => array(
					'analytics' => true,
					'marketing' => false,
					'necessary' => false, // Must be forced back on.
					'hacker'    => true,  // Unknown: dropped.
				),
				'consent_version' => '1.0',
				'action'          => 'custom',
			)
		);
		$this->assertSame( 1, $record['necessary'] );
		$this->assertSame( 1, $record['analytics'] );
		$this->assertSame( 0, $record['marketing'] );
		$this->assertNull( $record['extra_categories'] );
		$this->assertSame( 'custom', $record['action'] );
	}

	public function test_sanitize_record_stores_custom_categories_as_json() {
		update_option(
			'pcm_settings',
			array(
				'categories' => array_merge(
					PCM\Settings::default_categories(),
					array(
						'chat' => array(
							'label'       => 'Chat',
							'description' => '',
							'required'    => false,
							'builtin'     => false,
						),
					)
				),
			)
		);
		PCM\Settings::instance()->flush_cache();
		$manager = new Consent_Manager( new Consent_Storage() );

		$record = $manager->sanitize_record(
			array( 'categories' => array( 'chat' => true ) )
		);
		$this->assertSame( array( 'chat' => true ), json_decode( $record['extra_categories'], true ) );
	}

	public function test_sanitize_record_unknown_action_becomes_update() {
		$record = $this->manager->sanitize_record(
			array(
				'categories' => array( 'analytics' => true ),
				'action'     => 'evil',
			)
		);
		$this->assertSame( 'update', $record['action'] );
	}

	public function test_client_config_applies_locale_overrides() {
		update_option(
			'pcm_settings',
			array(
				'translations' => array(
					'en_US' => array(
						'banner'     => array( 'title' => 'Overridden Title' ),
						'categories' => array( 'analytics' => array( 'label' => 'Insights' ) ),
					),
				),
			)
		);
		PCM\Settings::instance()->flush_cache();

		$config = ( new Consent_Manager( new Consent_Storage() ) )->get_client_config();
		$this->assertSame( 'Overridden Title', $config['banner']['title'] );
		$this->assertSame( 'Insights', $config['categories']['analytics']['label'] );
	}

	public function test_client_config_discovery_only_for_admins() {
		$GLOBALS['pcm_test_user_caps']['manage_options'] = false;
		$config = $this->manager->get_client_config();
		$this->assertFalse( $config['discover'] );
		$this->assertSame( '', $config['restNonce'] );

		$GLOBALS['pcm_test_user_caps']['manage_options'] = true;
		$config = $this->manager->get_client_config();
		$this->assertTrue( $config['discover'] );
		$this->assertNotSame( '', $config['restNonce'] );
		$this->assertContains( 'pcm_consent', $config['knownCookies'] );
	}

	public function test_sanitize_record_region_is_bounded() {
		$record = $this->manager->sanitize_record(
			array(
				'categories' => array(),
				'region'     => 'gdpr_profile_with_a_really_long_name',
			)
		);
		$this->assertLessThanOrEqual( 8, strlen( $record['region'] ) );
	}
}
