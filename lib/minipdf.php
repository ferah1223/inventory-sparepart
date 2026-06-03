<?php
/**
 * MiniPDF — Self-contained PDF 1.4 Generator for PHP
 * 
 * A minimal, zero-dependency library that generates valid PDF binary output.
 * Supports: A4 pages, text output, line drawing, cell/table rendering with
 * borders, multi-page documents, auto page-break, and file download.
 *
 * Built-in font: Helvetica (standard PDF font, no embedding needed).
 *
 * Usage:
 *   $pdf = new MiniPDF();
 *   $pdf->addPage();
 *   $pdf->setFont('Helvetica', 'B', 16);
 *   $pdf->cell(100, 10, 'Hello World');
 *   $pdf->output('document.pdf');
 *
 * @author  Bengkel Jaya Inventory System
 * @version 1.0
 * @license MIT
 */

class MiniPDF
{
    // ========================================================================
    // Constants: A4 page dimensions in PDF points (1 pt = 1/72 inch)
    // ========================================================================
    public $A4_WIDTH  = 595.28;   // 210 mm
    public $A4_HEIGHT = 841.89;   // 297 mm

    // ========================================================================
    // Page margins (in points)
    // ========================================================================
    public $marginLeft   = 40;
    public $marginTop    = 40;
    public $marginRight  = 40;
    public $marginBottom = 40;

    // ========================================================================
    // Internal state
    // ========================================================================
    private $pages        = [];     // Array of page content streams (strings)
    private $currentPage  = -1;    // Index of the current page
    private $objectOffsets = [];   // Byte offsets of each PDF object (for xref)
    private $objectCount   = 0;   // Total number of PDF objects written
    private $buffer        = '';   // Output buffer for the final PDF binary

    // Current drawing / text state
    private $x            = 0;     // Current X position (from left)
    private $y            = 0;     // Current Y position (from top of page)
    private $lineWidth    = 0.5;   // Stroke width in points
    private $drawColor    = [0, 0, 0];     // Stroke color RGB
    private $fillColor    = [255, 255, 255]; // Fill color RGB
    private $textColor    = [0, 0, 0];     // Text color RGB
    private $fontSize     = 10;    // Current font size in points
    private $fontName     = 'Helvetica';
    private $fontStyle    = '';    // '', 'B', 'I', 'BI'

    // Auto page-break settings
    private $autoPageBreak      = true;
    private $pageBreakTrigger;   // Y threshold that triggers a page break

    // ========================================================================
    // Standard font widths (character widths in 1/1000 of unit for Helvetica)
    // We store the full encoding table for accurate text width calculation.
    // ========================================================================
    private static $helveticaWidths = [
        // ASCII 32–126 (space through tilde)
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,
    ];

    /**
     * Constructor — set up default page-break threshold.
     */
    public function __construct()
    {
        $this->pageBreakTrigger = $this->A4_HEIGHT - $this->marginBottom;
        $this->x = $this->marginLeft;
        $this->y = $this->marginTop;
    }

    // ========================================================================
    // PAGE MANAGEMENT
    // ========================================================================

    /**
     * Add a new page to the document and move the cursor to top-left.
     */
    public function addPage(): void
    {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
        $this->x = $this->marginLeft;
        $this->y = $this->marginTop;
        // Set initial graphics state on the new page
        $this->_emit('1 J 1 j');  // round line caps and joins
        $this->setLineWidth($this->lineWidth);
    }

    /**
     * Returns the effective content width (page width minus left and right margins).
     */
    public function getContentWidth(): float
    {
        return $this->A4_WIDTH - $this->marginLeft - $this->marginRight;
    }

    /**
     * Enable or disable automatic page breaks when Y exceeds the page bottom.
     */
    public function setAutoPageBreak(bool $enable, float $margin = 10): void
    {
        $this->autoPageBreak = $enable;
        $this->pageBreakTrigger = $this->A4_HEIGHT - $this->marginBottom - $margin;
    }

    /**
     * Check if we need a page break before drawing something of the given height.
     * If so, automatically add a new page.
     */
    public function checkPageBreak(float $h): void
    {
        if ($this->autoPageBreak && ($this->y + $h > $this->pageBreakTrigger)) {
            $this->addPage();
        }
    }

    // ========================================================================
    // MARGIN & POSITION
    // ========================================================================

    public function setMargins(float $left, float $top, float $right = null, float $bottom = null): void
    {
        $this->marginLeft  = $left;
        $this->marginTop   = $top;
        $this->marginRight  = $right  ?? $left;
        $this->marginBottom = $bottom ?? $top;
        $this->pageBreakTrigger = $this->A4_HEIGHT - $this->marginBottom;
    }

    public function setXY(float $x, float $y): void
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function setX(float $x): void
    {
        $this->x = $x;
    }

    public function setY(float $y): void
    {
        $this->x = $this->marginLeft;
        $this->y = $y;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    // ========================================================================
    // GRAPHICS STATE
    // ========================================================================

    /**
     * Set the line (stroke) width in points.
     */
    public function setLineWidth(float $w): void
    {
        $this->lineWidth = $w;
        $this->_emit(sprintf('%.2F w', $w));
    }

    /**
     * Set the draw (stroke) color as RGB [0-255, 0-255, 0-255].
     */
    public function setDrawColor(array $rgb): void
    {
        $this->drawColor = $rgb;
        $this->_emit(sprintf('%.3F %.3F %.3F RG', $rgb[0]/255, $rgb[1]/255, $rgb[2]/255));
    }

    /**
     * Set the fill color as RGB [0-255, 0-255, 0-255].
     */
    public function setFillColor(array $rgb): void
    {
        $this->fillColor = $rgb;
        $this->_emit(sprintf('%.3F %.3F %.3F rg', $rgb[0]/255, $rgb[1]/255, $rgb[2]/255));
    }

    /**
     * Set the text color as RGB [0-255, 0-255, 0-255].
     */
    public function setTextColor(array $rgb): void
    {
        $this->textColor = $rgb;
    }

    // ========================================================================
    // FONT
    // ========================================================================

    /**
     * Set the current font.
     *
     * @param string $family  Only 'Helvetica' is supported (standard PDF font).
     * @param string $style   '' (regular), 'B' (bold), 'I' (italic), 'BI' (bold italic)
     * @param float  $size    Font size in points
     */
    public function setFont(string $family, string $style = '', float $size = 10): void
    {
        $this->fontName  = $family;
        $this->fontStyle = $style;
        $this->fontSize  = $size;
        // Emit PDF font selection command: /F1 10 Tf
        $this->_emit(sprintf('/F1 %.1F Tf', $size));
    }

    // ========================================================================
    // TEXT OUTPUT
    // ========================================================================

    /**
     * Output a text string at the current position.
     * The Y coordinate in PDF is bottom-up; we convert from top-down.
     *
     * @param string $txt  The text to output
     */
    public function text(string $txt): void
    {
        $safe = $this->_escapePdfString($txt);
        // In PDF, text commands use: (text) Tj
        // We position with: x y Td (text displacement)
        $pdfY = $this->A4_HEIGHT - $this->y - $this->fontSize;
        $this->_emit(sprintf('BT %.2F %.2F Td (%s) Tj ET', $this->x, $pdfY, $safe));
    }

    /**
     * Output a cell (rectangle with optional text).
     *
     * @param float       $w     Cell width
     * @param float       $h     Cell height
     * @param string      $txt   Cell text content
     * @param int         $border  0=none, 1=frame, or combination: 'LTRB' flags
     *                              1=left, 2=top, 4=right, 8=bottom (additive)
     *                              Special: 1 = draw all borders (frame)
     * @param string      $align  'L' (left), 'C' (center), 'R' (right)
     * @param bool        $fill   Whether to fill the cell background
     * @param bool        $ln     If true, move to next line after cell (line break)
     */
    public function cell(float $w, float $h, string $txt = '', int $border = 0,
                         string $align = 'L', bool $fill = false, bool $ln = false): void
    {
        // Draw fill rectangle if requested
        if ($fill) {
            $pdfY = $this->A4_HEIGHT - $this->y - $h;
            $this->_emit(sprintf('q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q',
                $this->fillColor[0]/255, $this->fillColor[1]/255, $this->fillColor[2]/255,
                $this->x, $pdfY, $w, $h));
        }

        // Draw border
        if ($border) {
            $pdfY = $this->A4_HEIGHT - $this->y - $h;
            if ($border === 1) {
                // Full frame
                $this->_emit(sprintf('%.2F %.2F %.2F %.2F re S', $this->x, $pdfY, $w, $h));
            } else {
                // Individual sides: L=1, T=2, R=4, B=8
                $x1 = $this->x;
                $y1 = $pdfY;
                $x2 = $this->x + $w;
                $y2 = $pdfY + $h;
                if ($border & 1) { // Left
                    $this->_emit(sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $y1, $x1, $y2));
                }
                if ($border & 2) { // Top
                    $this->_emit(sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $y2, $x2, $y2));
                }
                if ($border & 4) { // Right
                    $this->_emit(sprintf('%.2F %.2F m %.2F %.2F l S', $x2, $y2, $x2, $y1));
                }
                if ($border & 8) { // Bottom
                    $this->_emit(sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $y1, $x2, $y1));
                }
            }
        }

        // Draw text inside the cell
        if ($txt !== '') {
            $txtWidth = $this->getStringWidth($txt);
            $padding  = 2; // Internal horizontal padding

            // Calculate X position based on alignment
            switch ($align) {
                case 'C':
                    $txtX = $this->x + ($w - $txtWidth) / 2;
                    break;
                case 'R':
                    $txtX = $this->x + $w - $txtWidth - $padding;
                    break;
                default: // 'L'
                    $txtX = $this->x + $padding;
                    break;
            }

            // Center text vertically within cell
            $txtY = $this->A4_HEIGHT - $this->y - ($h + $this->fontSize) / 2
                    - ($this->fontSize * 0.35); // baseline adjustment

            $safe = $this->_escapePdfString($txt);
            $this->_emit(sprintf('BT %.2F %.2F Td (%s) Tj ET', $txtX, $txtY, $safe));
        }

        // Advance position
        if ($ln) {
            // Move to next line
            $this->y += $h;
            $this->x = $this->marginLeft;
        } else {
            $this->x += $w;
        }
    }

    /**
     * Render a row of cells (one table row).
     *
     * @param float   $h       Row height
     * @param array   $data    Array of cell text strings
     * @param array   $widths  Array of cell widths
     * @param int     $border  Border flag (see cell())
     * @param array   $aligns  Array of alignment strings per column (default 'L')
     * @param bool    $fill    Whether to fill the row
     */
    public function row(float $h, array $data, array $widths, int $border = 1,
                        array $aligns = [], bool $fill = false): void
    {
        foreach ($data as $i => $txt) {
            $w = $widths[$i] ?? 50;
            $a = $aligns[$i] ?? 'L';
            $isLast = ($i === count($data) - 1);
            $this->cell($w, $h, (string)$txt, $border, $a, $fill, $isLast);
        }
    }

    // ========================================================================
    // LINE DRAWING
    // ========================================================================

    /**
     * Draw a straight line from (x1, y1) to (x2, y2).
     * Coordinates are in top-down system (converted to PDF bottom-up internally).
     */
    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $py1 = $this->A4_HEIGHT - $y1;
        $py2 = $this->A4_HEIGHT - $y2;
        $this->_emit(sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $py1, $x2, $py2));
    }

    /**
     * Draw a horizontal line at the current Y position.
     */
    public function hr(float $y = null, ?float $x1 = null, ?float $x2 = null): void
    {
        $y  = $y  ?? $this->y;
        $x1 = $x1 ?? $this->marginLeft;
        $x2 = $x2 ?? ($this->A4_WIDTH - $this->marginRight);
        $this->line($x1, $y, $x2, $y);
    }

    /**
     * Draw a filled rectangle (no stroke). Useful for colored backgrounds.
     * Coordinates are top-down.
     */
    public function rect(float $x, float $y, float $w, float $h, array $color): void
    {
        $pdfY = $this->A4_HEIGHT - $y - $h;
        $this->_emit(sprintf('q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q',
            $color[0]/255, $color[1]/255, $color[2]/255,
            $x, $pdfY, $w, $h));
    }

    // ========================================================================
    // TEXT MEASUREMENT
    // ========================================================================

    /**
     * Calculate the width of a string in points for the current font and size.
     * Uses the built-in Helvetica width table.
     */
    public function getStringWidth(string $txt): float
    {
        $w = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $c = ord($txt[$i]);
            if ($c >= 32 && $c <= 126) {
                $w += self::$helveticaWidths[$c - 32];
            } else {
                $w += 500; // default for unknown chars
            }
        }
        // Widths are per 1000 units; scale by font size
        return $w * $this->fontSize / 1000;
    }

    /**
     * Calculate width of the longest string in an array (useful for column sizing).
     */
    public function getMaxStringWidth(array $strings): float
    {
        $max = 0;
        foreach ($strings as $s) {
            $w = $this->getStringWidth((string)$s);
            if ($w > $max) $max = $w;
        }
        return $max;
    }

    // ========================================================================
    // PAGE NUMBERS
    // ========================================================================

    /**
     * Output page number at the bottom center of the current page.
     * Call this after all page content is written (or in a footer pass).
     *
     * @param string $format  Format string; use {nb} for total page count, {pn} for current page number
     */
    public function pageNumbers(string $format = 'Halaman {pn} dari {nb}', float $size = 8): void
    {
        $total = count($this->pages);
        for ($i = 0; $i < $total; $i++) {
            $num  = $i + 1;
            $text = str_replace(['{pn}', '{nb}'], [$num, $total], $format);
            $w    = 0;
            for ($c = 0; $c < strlen($text); $c++) {
                $ch = ord($text[$c]);
                if ($ch >= 32 && $ch <= 126) {
                    $w += self::$helveticaWidths[$ch - 32];
                } else {
                    $w += 500;
                }
            }
            $w = $w * $size / 1000;

            $x = ($this->A4_WIDTH - $w) / 2;
            $y = $this->A4_HEIGHT - 25; // 25pt from bottom

            $safe = $this->_escapePdfString($text);
            $pageStream = &$this->pages[$i];
            // Append the page number command to the page content stream
            $pageStream .= sprintf("\nBT /F1 %.1F Tf %.2F %.2F Td (%s) Tj ET",
                $size, $x, $y, $safe);
        }
    }

    // ========================================================================
    // PDF OUTPUT
    // ========================================================================

    /**
     * Generate the final PDF binary and send it as a file download.
     *
     * @param string $filename  Suggested filename for the download
     */
    public function output(string $filename = 'document.pdf'): void
    {
        $pdf = $this->_buildPdf();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $pdf;
        exit;
    }

    /**
     * Get the raw PDF binary string (for testing or saving to file).
     */
    public function getRaw(): string
    {
        return $this->_buildPdf();
    }

    // ========================================================================
    // PRIVATE: Internal PDF construction
    // ========================================================================

    /**
     * Emit a PDF content-stream operator to the current page.
     */
    private function _emit(string $cmd): void
    {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
        $this->pages[$this->currentPage] .= $cmd . "\n";
    }

    /**
     * Escape a string for inclusion in a PDF content stream or string literal.
     * PDF strings use parentheses; backslash and parens must be escaped.
     */
    private function _escapePdfString(string $txt): string
    {
        // Replace characters that are special in PDF string literals
        $txt = str_replace('\\', '\\\\', $txt);
        $txt = str_replace('(',  '\\(',  $txt);
        $txt = str_replace(')',  '\\)',  $txt);
        // Replace non-ASCII with a dot (our Helvetica is WinAnsiEncoding)
        $txt = preg_replace('/[^\x20-\x7E]/', '.', $txt);
        return $txt;
    }

    /**
     * Build the complete PDF binary.
     *
     * PDF structure:
     *   %PDF-1.4
     *   obj 1: Catalog
     *   obj 2: Page Tree (parent)
     *   obj 3..N-1: Page objects + content streams + font
     *   xref table
     *   trailer
     *   %%EOF
     */
    private function _buildPdf(): string
    {
        $this->buffer = '';
        $this->objectOffsets = [];
        $this->objectCount = 0;

        // PDF header
        $this->_writeRaw("%PDF-1.4\n");
        // Add a comment with high-byte characters to signal binary content
        $this->_writeRaw("%\xE2\xE3\xCF\xD3\n");

        $totalPages = count($this->pages);

        // We'll allocate object numbers:
        //   obj 1 = Catalog
        //   obj 2 = Pages (page tree root)
        //   obj 3 = Font (Helvetica)
        //   obj 4..(4+totalPages-1) = Page objects
        //   obj (4+totalPages)..(4+2*totalPages-1) = Content stream objects
        $catalogObjNum     = 1;
        $pagesTreeObjNum   = 2;
        $fontObjNum        = 3;
        $firstPageObjNum   = 4;
        $firstContentObjNum = 4 + $totalPages;

        // --- Object 1: Catalog ---
        $this->_beginObject($catalogObjNum);
        $this->_writeRaw("<< /Type /Catalog /Pages {$pagesTreeObjNum} 0 R >>\n");
        $this->_endObject();

        // --- Object 2: Pages tree ---
        $kids = '';
        for ($i = 0; $i < $totalPages; $i++) {
            $kids .= ($firstPageObjNum + $i) . ' 0 R ';
        }
        $this->_beginObject($pagesTreeObjNum);
        $this->_writeRaw("<< /Type /Pages /Kids [{$kids}] /Count {$totalPages} >>\n");
        $this->_endObject();

        // --- Object 3: Font ---
        $this->_beginObject($fontObjNum);
        $this->_writeRaw("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\n");
        $this->_endObject();

        // --- Page objects and content streams ---
        for ($i = 0; $i < $totalPages; $i++) {
            $pageObjNum    = $firstPageObjNum + $i;
            $contentObjNum = $firstContentObjNum + $i;
            $pageStream    = $this->pages[$i];

            // Page object
            $this->_beginObject($pageObjNum);
            $this->_writeRaw(sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] " .
                "/Contents %d 0 R /Resources << /Font << /F1 %d 0 R >> >> >>\n",
                $pagesTreeObjNum, $this->A4_WIDTH, $this->A4_HEIGHT,
                $contentObjNum, $fontObjNum
            ));
            $this->_endObject();

            // Content stream object
            $this->_beginObject($contentObjNum);
            $this->_writeRaw("<< /Length " . strlen($pageStream) . " >>\n");
            $this->_writeRaw("stream\n");
            $this->_writeRaw($pageStream);
            $this->_writeRaw("endstream\n");
            $this->_endObject();
        }

        // --- Cross-reference table ---
        $xrefOffset = strlen($this->buffer);
        $totalObjects = $this->objectCount + 1; // +1 for the free entry (obj 0)

        $this->_writeRaw("xref\n");
        $this->_writeRaw("0 {$totalObjects}\n");
        // Entry 0: free, linked to max
        $this->_writeRaw("0000000000 65535 f \n");
        // Entries 1..N
        for ($i = 1; $i <= $this->objectCount; $i++) {
            $this->_writeRaw(sprintf("%010d 00000 n \n", $this->objectOffsets[$i]));
        }

        // --- Trailer ---
        $this->_writeRaw("trailer\n");
        $this->_writeRaw(sprintf("<< /Size %d /Root %d 0 R >>\n", $totalObjects, $catalogObjNum));
        $this->_writeRaw("startxref\n");
        $this->_writeRaw("{$xrefOffset}\n");
        $this->_writeRaw("%%EOF\n");

        return $this->buffer;
    }

    /**
     * Write raw bytes to the output buffer.
     */
    private function _writeRaw(string $data): void
    {
        $this->buffer .= $data;
    }

    /**
     * Begin a new PDF object and record its byte offset for the xref table.
     */
    private function _beginObject(int $num): void
    {
        $this->objectOffsets[$num] = strlen($this->buffer);
        $this->objectCount = max($this->objectCount, $num);
        $this->_writeRaw("{$num} 0 obj\n");
    }

    /**
     * End a PDF object.
     */
    private function _endObject(): void
    {
        $this->_writeRaw("endobj\n");
    }
}
