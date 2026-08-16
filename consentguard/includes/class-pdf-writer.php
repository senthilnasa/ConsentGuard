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
 * A4 portrait, word-wrapped text lines, colored text, filled rectangles,
 * separator rules, automatic page breaks, "n / m" page footers).
 * Deliberately tiny instead of bundling a PDF library: the proof-of-consent
 * export only needs headed, branded text.
 */
class Pdf_Writer {

	const PAGE_WIDTH  = 595.28; // A4 portrait, points.
	const PAGE_HEIGHT = 841.89;
	const MARGIN_X    = 56;
	const TOP_Y       = 785;
	const BOTTOM_Y    = 64;

	/**
	 * Pages; each page is a list of ops:
	 * text: {op:'text', text, size, bold, x, y, color}
	 * rect: {op:'rect', x, y, w, h, color}.
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
	 * Draws a full-width brand band across the top of the current page with
	 * the given text (white, bold) and optional right-aligned subtext.
	 *
	 * @param string $text    Brand text.
	 * @param string $hex     Band color (hex).
	 * @param string $subtext Right-aligned smaller text (e.g. domain).
	 */
	public function header_band( $text, $hex, $subtext = '' ) {
		$height = 56;
		$this->push_rect( 0, self::PAGE_HEIGHT - $height, self::PAGE_WIDTH, $height, $hex );
		$this->push_text( $text, 18, true, self::MARGIN_X, self::PAGE_HEIGHT - 37, '#ffffff' );
		if ( '' !== $subtext ) {
			$approx_width = strlen( $subtext ) * 0.5 * 10;
			$this->push_text( $subtext, 10, false, self::PAGE_WIDTH - self::MARGIN_X - $approx_width, self::PAGE_HEIGHT - 35, '#ffffff' );
		}
		$this->y = self::PAGE_HEIGHT - $height - 34;
	}

	/**
	 * Adds a large bold title line.
	 *
	 * @param string $text  Text.
	 * @param string $color Hex color.
	 */
	public function title( $text, $color = '' ) {
		$this->line( $text, 20, true, 0, $color );
		$this->space( 10 );
	}

	/**
	 * Adds a section heading.
	 *
	 * @param string $text  Text.
	 * @param string $color Hex color.
	 */
	public function heading( $text, $color = '' ) {
		$this->space( 8 );
		$this->line( $text, 13, true, 0, $color );
		$this->space( 4 );
	}

	/**
	 * Adds a "Label / value" block like the reference proof layout.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	public function field( $label, $value ) {
		$this->space( 8 );
		$this->line( $label, 9, true, 0, '#777777' );
		$this->space( 1 );
		$this->line( '' !== (string) $value ? (string) $value : '-', 11.5, false );
		$this->space( 6 );
		$this->hr();
	}

	/**
	 * Adds a word-wrapped paragraph.
	 *
	 * @param string $text   Text.
	 * @param int    $size   Font size.
	 * @param bool   $bold   Bold.
	 * @param int    $indent Extra left indent in points.
	 * @param string $color  Hex color.
	 */
	public function paragraph( $text, $size = 11, $bold = false, $indent = 0, $color = '' ) {
		$this->line( $text, $size, $bold, $indent, $color );
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
	 * Draws a thin horizontal separator rule at the cursor.
	 *
	 * @param string $hex    Rule color.
	 * @param int    $indent Left indent.
	 */
	public function hr( $hex = '#e6e6e6', $indent = 0 ) {
		$this->ensure_room( 6 );
		$this->push_rect( self::MARGIN_X + $indent, $this->y, self::PAGE_WIDTH - 2 * self::MARGIN_X - $indent, 0.8, $hex );
		$this->y -= 6;
	}

	/**
	 * Draws a filled box (used as cookie-entry background).
	 *
	 * @param float  $height Box height.
	 * @param string $hex    Fill color.
	 * @param int    $indent Left indent.
	 */
	public function box( $height, $hex = '#f4f4f4', $indent = 0 ) {
		$this->push_rect(
			self::MARGIN_X + $indent,
			$this->y - $height,
			self::PAGE_WIDTH - 2 * self::MARGIN_X - $indent,
			$height,
			$hex
		);
	}

	/**
	 * Adds a wrapped text line (may span multiple physical lines).
	 *
	 * @param string $text   Text.
	 * @param float  $size   Font size.
	 * @param bool   $bold   Bold.
	 * @param int    $indent Extra left indent.
	 * @param string $color  Hex color ('' = default ink).
	 */
	public function line( $text, $size = 11, $bold = false, $indent = 0, $color = '' ) {
		$max_width = self::PAGE_WIDTH - 2 * self::MARGIN_X - $indent;
		// Helvetica average glyph width ≈ 0.52 em; conservative wrap estimate.
		$max_chars = max( 8, (int) floor( $max_width / ( 0.52 * $size ) ) );

		$text = $this->to_pdf_text( $text );
		$rows = explode( "\n", wordwrap( $text, $max_chars, "\n", true ) );

		foreach ( $rows as $row ) {
			$line_height = $size * 1.45;
			$this->ensure_room( $line_height );
			$this->y -= $line_height;
			$this->push_text( $row, $size, $bold, self::MARGIN_X + $indent, $this->y, $color );
		}
	}

	/**
	 * Number of physical lines a text will occupy at a size/indent
	 * (used to pre-size boxes behind wrapped text).
	 *
	 * @param string $text   Text.
	 * @param float  $size   Font size.
	 * @param int    $indent Indent.
	 * @return int
	 */
	public function measure_lines( $text, $size = 11, $indent = 0 ) {
		$max_width = self::PAGE_WIDTH - 2 * self::MARGIN_X - $indent;
		$max_chars = max( 8, (int) floor( $max_width / ( 0.52 * $size ) ) );
		return count( explode( "\n", wordwrap( $this->to_pdf_text( $text ), $max_chars, "\n", true ) ) );
	}

	/**
	 * Remaining vertical space on the current page.
	 *
	 * @return float
	 */
	public function room_left() {
		return $this->y - self::BOTTOM_Y;
	}

	/**
	 * Starts a new page when fewer than $needed points remain.
	 *
	 * @param float $needed Needed points.
	 */
	public function ensure_room( $needed ) {
		if ( $this->y - $needed < self::BOTTOM_Y ) {
			$this->pages[] = array();
			$this->y       = self::TOP_Y;
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

		foreach ( $this->pages as $index => $ops ) {
			$content = '';

			// Rectangles first so text always sits above fills.
			foreach ( $ops as $op ) {
				if ( 'rect' === $op['op'] ) {
					$content .= sprintf(
						"%s rg %.2F %.2F %.2F %.2F re f\n",
						$this->rgb( $op['color'] ),
						$op['x'],
						$op['y'],
						$op['w'],
						$op['h']
					);
				}
			}

			$content .= "BT\n";
			foreach ( $ops as $op ) {
				if ( 'text' !== $op['op'] ) {
					continue;
				}
				$content .= sprintf(
					"%s rg /%s %.2F Tf\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\n",
					$this->rgb( $op['color'] ?: '#1f2430' ),
					$op['bold'] ? 'F2' : 'F1',
					$op['size'],
					$op['x'],
					$op['y'],
					$this->escape( $op['text'] )
				);
			}
			// Footer: "n / m".
			$content .= sprintf(
				"0.55 0.55 0.55 rg /F1 9 Tf\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\n",
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
			'<< /Title (%s) /Producer (ConsentGuard) /CreationDate (D:%s) >>',
			$this->escape( $this->to_pdf_text( $this->doc_title ) ),
			gmdate( 'YmdHis' ) . 'Z'
		);
		$info_id   = count( $objects );

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
	 * Queues a text op on the current page.
	 *
	 * @param string $text  Text (already WinAnsi).
	 * @param float  $size  Size.
	 * @param bool   $bold  Bold.
	 * @param float  $x     X.
	 * @param float  $y     Y.
	 * @param string $color Hex color.
	 */
	private function push_text( $text, $size, $bold, $x, $y, $color = '' ) {
		$this->pages[ count( $this->pages ) - 1 ][] = array(
			'op'    => 'text',
			'text'  => $text,
			'size'  => $size,
			'bold'  => $bold,
			'x'     => $x,
			'y'     => $y,
			'color' => $color,
		);
	}

	/**
	 * Queues a rectangle op on the current page.
	 *
	 * @param float  $x     X.
	 * @param float  $y     Y (bottom edge).
	 * @param float  $w     Width.
	 * @param float  $h     Height.
	 * @param string $color Hex fill.
	 */
	private function push_rect( $x, $y, $w, $h, $color ) {
		$this->pages[ count( $this->pages ) - 1 ][] = array(
			'op'    => 'rect',
			'x'     => $x,
			'y'     => $y,
			'w'     => $w,
			'h'     => $h,
			'color' => $color,
		);
	}

	/**
	 * Hex color to PDF "r g b" floats.
	 *
	 * @param string $hex Hex color.
	 * @return string
	 */
	private function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			$hex = '1f2430';
		}
		return sprintf(
			'%.3F %.3F %.3F',
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255
		);
	}

	/**
	 * Converts UTF-8 to WinAnsi-safe text for core-font PDFs.
	 *
	 * @param string $text UTF-8 text.
	 * @return string
	 */
	private function to_pdf_text( $text ) {
		$converted = function_exists( 'iconv' )
			? @iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', (string) $text ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- iconv notices on untranslatable bytes are expected; the fallback below handles failure.
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
		return strtr(
			(string) $text,
			array(
				'\\' => '\\\\',
				'('  => '\\(',
				')'  => '\\)',
				"\r" => ' ',
				"\n" => ' ',
			)
		);
	}
}
