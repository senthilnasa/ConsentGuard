<?php
/**
 * Tests for the minimal PDF writer.
 *
 * @package PCM
 */

use PCM\Pdf_Writer;
use PHPUnit\Framework\TestCase;

class Pdf_Writer_Test extends TestCase {

	protected function setUp(): void {
		pcm_test_reset();
	}

	private function build_simple() {
		$pdf = new Pdf_Writer( 'Proof of consent' );
		$pdf->title( 'Proof of consent' );
		$pdf->field( 'Consented domain', 'example.test' );
		$pdf->field( 'Consent status', 'Accepted' );
		return $pdf;
	}

	public function test_renders_valid_pdf_structure() {
		$out = $this->build_simple()->render();
		$this->assertStringStartsWith( '%PDF-1.4', $out );
		$this->assertStringContainsString( '%%EOF', $out );
		$this->assertStringContainsString( '/Type /Catalog', $out );
		$this->assertStringContainsString( '/BaseFont /Helvetica', $out );
		$this->assertStringContainsString( '(Proof of consent) Tj', $out );
		$this->assertStringContainsString( '(example.test) Tj', $out );
	}

	public function test_xref_offsets_are_correct() {
		$out = $this->build_simple()->render();
		// Every xref entry must point at the matching "N 0 obj" marker.
		preg_match( '/xref\n0 (\d+)\n/', $out, $m );
		$count = (int) $m[1];
		preg_match_all( '/^(\d{10}) 00000 n /m', $out, $offsets );
		$this->assertCount( $count - 1, $offsets[1] );
		foreach ( $offsets[1] as $i => $offset ) {
			$this->assertSame(
				( $i + 1 ) . ' 0 obj',
				substr( $out, (int) $offset, strlen( ( $i + 1 ) . ' 0 obj' ) ),
				"xref entry $i points at the wrong offset"
			);
		}
	}

	public function test_escapes_pdf_delimiters() {
		$pdf = new Pdf_Writer( 'T' );
		$pdf->paragraph( 'Parens (and) backslash \\ here' );
		$out = $pdf->render();
		$this->assertStringContainsString( 'Parens \\(and\\) backslash \\\\ here', $out );
	}

	public function test_long_content_paginates_with_footers() {
		$pdf = new Pdf_Writer( 'T' );
		for ( $i = 0; $i < 120; $i++ ) {
			$pdf->paragraph( 'Line ' . $i . ': some content that fills the page.' );
		}
		$out = $pdf->render();
		$this->assertStringContainsString( '/Count 3', $out );
		$this->assertStringContainsString( '(1 / 3) Tj', $out );
		$this->assertStringContainsString( '(3 / 3) Tj', $out );
	}

	public function test_stream_lengths_match() {
		$out = $this->build_simple()->render();
		preg_match_all( '#<< /Length (\d+) >>\nstream\n(.*?)\nendstream#s', $out, $m, PREG_SET_ORDER );
		$this->assertNotEmpty( $m );
		foreach ( $m as $stream ) {
			$this->assertSame( (int) $stream[1], strlen( $stream[2] ) );
		}
	}
}
