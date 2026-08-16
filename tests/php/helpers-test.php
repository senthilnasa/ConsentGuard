<?php
/**
 * Tests for helper sanitizers.
 *
 * @package PCM
 */

use PHPUnit\Framework\TestCase;

class Helpers_Test extends TestCase {

	protected function setUp(): void {
		pcm_test_reset();
	}

	public function test_ga4_id_valid() {
		$this->assertSame( 'G-ABC1234567', pcm_sanitize_ga4_id( 'g-abc1234567' ) );
	}

	public function test_ga4_id_invalid() {
		$this->assertSame( '', pcm_sanitize_ga4_id( 'UA-12345-1' ) );
		$this->assertSame( '', pcm_sanitize_ga4_id( 'G-<script>' ) );
		$this->assertSame( '', pcm_sanitize_ga4_id( '' ) );
	}

	public function test_gtm_id() {
		$this->assertSame( 'GTM-ABC1234', pcm_sanitize_gtm_id( 'gtm-abc1234' ) );
		$this->assertSame( '', pcm_sanitize_gtm_id( 'G-ABC1234567' ) );
	}

	public function test_clarity_id() {
		$this->assertSame( 'abcd1234', pcm_sanitize_clarity_id( 'abcd1234' ) );
		$this->assertSame( '', pcm_sanitize_clarity_id( 'ab' ) );
		$this->assertSame( '', pcm_sanitize_clarity_id( 'abc"def' ) );
	}

	public function test_cloudflare_token() {
		$this->assertSame( 'deadbeefdeadbeef', pcm_sanitize_cf_token( 'deadbeefdeadbeef' ) );
		$this->assertSame( '', pcm_sanitize_cf_token( 'not-a-token!' ) );
	}

	public function test_domain_sanitizer() {
		$this->assertSame( 'clarity.ms', pcm_sanitize_domain( 'https://clarity.ms/tag/x' ) );
		$this->assertSame( 'www.google-analytics.com', pcm_sanitize_domain( 'WWW.Google-Analytics.com' ) );
		$this->assertSame( '*.example.com', pcm_sanitize_domain( '*.example.com' ) );
		$this->assertSame( '', pcm_sanitize_domain( 'not a domain' ) );
		$this->assertSame( '', pcm_sanitize_domain( 'localhost' ) );
	}

	public function test_category_slug() {
		$this->assertSame( 'my-category_2', pcm_sanitize_category_slug( 'My-Category_2!' ) );
		$this->assertSame( 40, strlen( pcm_sanitize_category_slug( str_repeat( 'a', 60 ) ) ) );
	}

	public function test_script_code_requires_unfiltered_html() {
		$GLOBALS['pcm_test_user_caps']['unfiltered_html'] = true;
		$this->assertSame( '<script>x()</script>', pcm_sanitize_script_code( '<script>x()</script>' ) );

		$GLOBALS['pcm_test_user_caps']['unfiltered_html'] = false;
		$this->assertStringNotContainsString( '<script>', pcm_sanitize_script_code( '<script>x()</script>' ) );
		$GLOBALS['pcm_test_user_caps']['unfiltered_html'] = true;
	}
}
