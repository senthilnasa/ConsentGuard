<?php
/**
 * Minimal dependency-free PDF writer.
 *
 * @package PCM
 */

namespace PCM;

defined( 'ABSPATH' ) || exit;

/**
 * Produces simple, valid PDF 1.4 documents (Helvetica / Helvetica-Bold,
 * A4 portrait, word-wrapped text lines, automatic page breaks, "n / m"
 * page footers). Deliberately tiny instead of bundling a PDF library:
 * the proof-of-consent export only needs headed text.
 */
class Pdf_Writer {

	const PAGE_WIDTH  = 595.28; // A4 portrait, points.
	const PAGE_HEIGHT = 841.89;
	const MARGIN_X    = 56;
	const TOP_Y       = 785;
	const BOTTOM_Y    = 64;

	/**
	 * Pages; each page is a list of line ops
	 * {text, size, bold, x, y}.
	 *
	 * @var array[]
	 */
	private $pages = array( array() );

	/**
	 * Current vertical cursor.
	 *
	 * @var float
	 */
	private $y = self::TOP_Y;

	/**
	 * Document title (PDF metadata).
	 *
	 * @var string
	 */
	private $doc_title;

	/**
	 * Constructor.
	 *
	 * @param string $doc_title Document title for the PDF metadata.
	 */
	public function __construct( $doc_title = '' ) {
		$this->doc_title = $doc_title;
	}

	/**
	 * Adds a large bold title line.
	 *
	 * @param string $text Text.
	 */
	public function title( $text ) {
		$this->line( $text, 20, true );
		$this->space( 10 );
	}

	/**
	 * Adds a section heading.
	 *
	 * @param string $text Text.
	 */
	public function heading( $text ) {
		$this->space( 8 );
		$this->line( $text, 13, true );
		$this->space( 4 );
	}

	/**
	 * Adds a "Label / value" block like the reference proof layout.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	public function field( $label, $value ) {
		$this->space( 6 );
		$this->line( $label, 10, true );
		$this->line( '' !== (string) $value ? (string) $value : '-', 11, false );
	}

	/**
	 * Adds a word-wrapped paragraph.
	 *
	 * @param string $text   Text.
	 * @param int    $size   Font size.
	 * @param bool   $bold   Bold.
	 * @param int    $indent Extra left indent in points.
	 */
	public function paragraph( $text, $size = 11, $bold = false, $indent = 0 ) {
		$this->line( $text, $size, $bold, $indent );
	}

	/**
	 * Adds vertical whitespace.
	 *
	 * @param float $points Points.
	 */
	public function space( $points ) {
		$this->y -= $points;
	}

	/**
	 * Adds a wrapped text line (may span multiple physical lines).
	 *
	 * @param string $text   Text.
	 * @param int    $size   Font size.
	 * @param bool   $bold   Bold.
	 * @param int    $indent Extra left indent.
	 */
	public function line( $text, $size = 11, $bold = false, $indent = 0 ) {
		$max_width = self::PAGE_WIDTH - 2 * self::MARGIN_X - $indent;
		// Helvetica average glyph width ≈ 0.52 em; conservative wrap estimate.
		$max_chars = max( 8, (int) floor( $max_width / ( 0.52 * $size ) ) );

		$text = $this->to_pdf_text( $text );
		$rows = explode( "\n", wordwrap( $text, $max_chars, "\n", true ) );

		foreach ( $rows as $row ) {
			$line_height = $size * 1.45;
			if ( $this->y - $line_height < self::BOTTOM_Y ) {
				$this->pages[] = array();
				$this->y       = self::TOP_Y;
			}
			$this->y -= $line_height;

			$this->pages[ count( $this->pages ) - 1 ][] = array(
				'text' => $row,
				'size' => $size,
				'bold' => $bold,
				'x'    => self::MARGIN_X + $indent,
				'y'    => $this->y,
			);
		}
	}

	/**
	 * Renders the document and returns the raw PDF bytes.
	 *
	 * @return string
	 */
	public function render() {
		$total = count( $this->pages );

		// Fixed object ids: 1 catalog, 2 pages, 3 F1, 4 F2, then per page:
		// page object + content object.
		$objects   = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

		$page_ids = array();
		for ( $i = 0; $i < $total; $i++ ) {
			$page_ids[] = ( 5 + $i * 2 ) . ' 0 R';
		}
		$objects[] = sprintf(
			'<< /Type /Pages /Kids [%s] /Count %d >>',
			implode( ' ', $page_ids ),
			$total
		);
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

		foreach ( $this->pages as $index => $lines ) {
			$content = "BT\n";
			foreach ( $lines as $line ) {
				$content .= sprintf(
					"/%s %d Tf\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\n",
					$line['bold'] ? 'F2' : 'F1',
					$line['size'],
					$line['x'],
					$line['y'],
					$this->escape( $line['text'] )
				);
			}
			// Footer: "n / m".
			$content .= sprintf(
				"/F1 9 Tf\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\n",
				self::PAGE_WIDTH - self::MARGIN_X - 40,
				40,
				$this->escape( ( $index + 1 ) . ' / ' . $total )
			);
			$content .= 'ET';

			$objects[] = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
				self::PAGE_WIDTH,
				self::PAGE_HEIGHT,
				5 + $index * 2 + 1
			);
			$objects[] = sprintf( "<< /Length %d >>\nstream\n%s\nendstream", strlen( $content ), $content );
		}

		$objects[] = sprintf(
			'<< /Title (%s) /Producer (PrivacyPress) /CreationDate (D:%s) >>',
			$this->escape( $this->to_pdf_text( $this->doc_title ) ),
			gmdate( 'YmdHis' ) . 'Z'
		);
		$info_id = count( $objects );

		// Assemble with a cross-reference table.
		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $i => $body ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $i + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref_pos = strlen( $pdf );
		$pdf     .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n";
		$pdf     .= "0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}
		$pdf .= sprintf(
			"trailer\n<< /Size %d /Root 1 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF\n",
			count( $objects ) + 1,
			$info_id,
			$xref_pos
		);

		return $pdf;
	}

	/**
	 * Converts UTF-8 to WinAnsi-safe text for core-font PDFs.
	 *
	 * @param string $text UTF-8 text.
	 * @return string
	 */
	private function to_pdf_text( $text ) {
		$converted = function_exists( 'iconv' )
			? @iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', (string) $text )
			: false;
		if ( false === $converted ) {
			$converted = preg_replace( '/[^\x20-\x7E]/', '?', (string) $text );
		}
		return $converted;
	}

	/**
	 * Escapes PDF string delimiters.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function escape( $text ) {
		return strtr( (string) $text, array(
			'\\' => '\\\\',
			'('  => '\\(',
			')'  => '\\)',
			"\r" => ' ',
			"\n" => ' ',
		) );
	}
}
