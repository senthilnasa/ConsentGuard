<?php
/**
 * Tests for the automatic script blocker.
 *
 * @package PCM
 */

use PCM\Script_Blocker;
use PHPUnit\Framework\TestCase;

class Script_Blocker_Test extends TestCase {

	/**
	 * Blocker under test.
	 *
	 * @var Script_Blocker
	 */
	private $blocker;

	protected function setUp(): void {
		pcm_test_reset();
		$this->blocker = new Script_Blocker();
	}

	public function test_known_analytics_domain_is_categorized() {
		$this->assertSame( 'analytics', $this->blocker->category_for_url( 'https://www.google-analytics.com/analytics.js' ) );
		$this->assertSame( 'analytics', $this->blocker->category_for_url( 'https://www.clarity.ms/tag/abc' ) );
		$this->assertSame( 'marketing', $this->blocker->category_for_url( 'https://connect.facebook.net/en_US/fbevents.js' ) );
	}

	public function test_subdomains_match() {
		$this->assertSame( 'analytics', $this->blocker->category_for_url( 'https://region1.google-analytics.com/g/collect' ) );
	}

	public function test_same_site_scripts_never_blocked() {
		$this->assertSame( '', $this->blocker->category_for_url( home_url( '/wp-includes/js/jquery.js' ) ) );
	}

	public function test_unknown_domains_not_blocked() {
		$this->assertSame( '', $this->blocker->category_for_url( 'https://cdn.example.org/app.js' ) );
	}

	public function test_allowlist_wins() {
		update_option(
			'pcm_settings',
			array( 'blocker' => array( 'allowlist' => array( 'google-analytics.com' ) ) )
		);
		PCM\Settings::instance()->flush_cache();
		$this->assertSame( '', $this->blocker->category_for_url( 'https://www.google-analytics.com/analytics.js' ) );
	}

	public function test_admin_scanner_classification_blocks() {
		update_option(
			'pcm_settings',
			array( 'scanner' => array( 'classifications' => array( 'tracker.example.net' => 'marketing' ) ) )
		);
		PCM\Settings::instance()->flush_cache();
		$this->assertSame( 'marketing', $this->blocker->category_for_url( 'https://tracker.example.net/t.js' ) );
	}

	public function test_filter_output_neutralizes_external_tracker() {
		$html = '<html><head><script src="https://www.google-analytics.com/analytics.js"></script></head><body></body></html>';
		$out  = $this->blocker->filter_output( $html );
		$this->assertStringContainsString( 'type="text/plain"', $out );
		$this->assertStringContainsString( 'data-pcm-category="analytics"', $out );
		$this->assertStringContainsString( 'data-pcm-autoblocked="1"', $out );
	}

	public function test_filter_output_neutralizes_inline_gtag() {
		$html = '<html><body><script>var s=document.createElement("script");s.src="https://www.googletagmanager.com/gtag/js?id=G-1";</script></body></html>';
		$out  = $this->blocker->filter_output( $html );
		$this->assertStringContainsString( 'type="text/plain"', $out );
	}

	public function test_filter_output_leaves_normal_scripts_alone() {
		$html = '<html><body><script src="https://example.test/app.js"></script><script>console.log(1);</script></body></html>';
		$this->assertSame( $html, $this->blocker->filter_output( $html ) );
	}

	public function test_filter_output_skips_json_ld() {
		$html = '<html><body><script type="application/ld+json">{"@context":"https://schema.org"}</script></body></html>';
		$this->assertSame( $html, $this->blocker->filter_output( $html ) );
	}

	public function test_filter_output_skips_pcm_managed_templates() {
		$html = '<html><body><script type="text/plain" data-pcm-managed="1" data-pcm-category="analytics">gtag()</script></body></html>';
		$this->assertSame( $html, $this->blocker->filter_output( $html ) );
	}

	public function test_filter_output_skips_non_html_payloads() {
		$json = '{"html":"<script src=\"https://www.google-analytics.com/x.js\"></script>"}';
		$this->assertSame( $json, $this->blocker->filter_output( $json ) );
	}

	public function test_autoblock_category_filter_can_veto() {
		add_filter(
			'pcm_autoblock_category',
			static function () {
				return '';
			}
		);
		$html = '<html><body><script src="https://www.google-analytics.com/analytics.js"></script></body></html>';
		$this->assertSame( $html, $this->blocker->filter_output( $html ) );
	}
}
