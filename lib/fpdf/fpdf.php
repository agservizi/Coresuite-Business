<?php
declare(strict_types=1);

/**
 * Minimal FPDF-compatible implementation tailored for daily report generation.
 *
 * This lightweight class supports the subset of the original FPDF API that our
 * application relies on (basic text cells, colour changes, margins, pagination
 * and file output). It produces standards-compliant PDF 1.3 documents by
 * internally assembling the required objects and content streams.
 *
 * It is not a drop-in replacement for the full library, but it keeps the same
 * method signatures so existing code can remain unchanged.
 */
if (class_exists('FPDF')) {
    return;
}

class FPDF
{
    private const DPI = 72.0;

    private float $k; // unit scale factor
    private float $w; // page width in selected unit
    private float $h; // page height in selected unit
    private float $wPt; // page width in points
    private float $hPt; // page height in points
    private float $lMargin = 10.0;
    private float $tMargin = 10.0;
    private float $rMargin = 10.0;
    private float $bMargin = 10.0;
    private float $cMargin = 2.0;
    private float $lineWidth = 0.2;
    private float $fontSizePt = 12.0;
    private float $fontSize = 12.0 / self::DPI;
    private string $fontFamily = 'Arial';
    private string $fontStyle = '';
    private string $currentFontKey = 'F1';
    private array $textColor = [0.0, 0.0, 0.0];
    private array $drawColor = [0.0, 0.0, 0.0];
    private array $fillColor = [1.0, 1.0, 1.0];
    private array $elements = [];
    private array $pages = [];
    private int $page = 0;
    private float $x = 0.0;
    private float $y = 0.0;
    private float $lasth = 0.0;
    private bool $autoPageBreak = true;
    private float $pageBreakTrigger = 0.0;
    private bool $inPage = false;
    // PHP method names are case-insensitive, so we keep only the canonical
    // studly-cased API surface to avoid duplicate declarations.

    public function __construct(string $orientation = 'P', string $unit = 'mm', $size = 'A4')
    {
        $this->setUnit($unit);
        $this->setPageSize($size, strtoupper($orientation));
        $this->SetMargins(10.0, 10.0);
        $this->SetAutoPageBreak(true, 15.0);
    }

    private function setUnit(string $unit): void
    {
        switch (strtolower($unit)) {
            case 'pt':
                $this->k = 1.0;
                break;
            case 'mm':
                $this->k = self::DPI / 25.4;
                break;
            case 'cm':
                $this->k = self::DPI / 2.54;
                break;
            case 'in':
                $this->k = self::DPI;
                break;
            default:
                throw new InvalidArgumentException('Unit not supported: ' . $unit);
        }
    }

    private function setPageSize($size, string $orientation): void
    {
        $sizes = [
            'A4' => [210.0, 297.0],
            'A5' => [148.0, 210.0],
            'LETTER' => [215.9, 279.4],
            'LEGAL' => [215.9, 355.6],
        ];

        if (is_string($size)) {
            $key = strtoupper($size);
            if (!isset($sizes[$key])) {
                throw new InvalidArgumentException('Unknown page size: ' . $size);
            }
            [$w, $h] = $sizes[$key];
        } elseif (is_array($size) && count($size) === 2) {
            [$w, $h] = array_values($size);
        } else {
            throw new InvalidArgumentException('Invalid page size declaration');
        }

        if ($orientation === 'L') {
            [$w, $h] = [$h, $w];
        }

        $this->w = $w;
        $this->h = $h;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;
    }

    public function SetMargins(float $left, float $top, ?float $right = null): void
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right ?? $left;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
    }

    public function SetAutoPageBreak(bool $auto, float $margin = 0.0): void
    {
        $this->autoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->pageBreakTrigger = $this->h - $margin;
    }

    public function SetLineWidth(float $width): void
    {
        $this->lineWidth = $width;
    }

    public function GetX(): float
    {
        return $this->x;
    }

    public function GetY(): float
    {
        return $this->y;
    }

    public function SetXY(float $x, float $y): void
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function Line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->elements[] = [
            'type' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'drawColor' => $this->drawColor,
            'lineWidth' => $this->lineWidth,
        ];
    }

    public function MultiCell(float $w, float $h, string $text, int $border = 0, string $align = 'J', bool $fill = false): void
    {
        if ($w <= 0.0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $lines = $this->wrapTextIntoLines($text, $w);
        $lineCount = count($lines);

        foreach ($lines as $index => $line) {
            $lineBorder = $border;
            if ($border === 1 && $index > 0 && $index < ($lineCount - 1)) {
                $lineBorder = 0;
            }
            $this->Cell($w, $h, $line, $lineBorder, 1, $align === 'J' ? 'L' : $align, $fill);
        }

        $this->x = $this->lMargin;
    }

    public function SetFont(string $family, string $style = '', float $size = 0.0): void
    {
        $family = strtolower($family);
        if ($family === 'arial') {
            $family = 'helvetica';
        }

        $style = strtoupper($style);
        if ($size <= 0.0) {
            $size = $this->fontSizePt;
        }

        $this->fontFamily = $family;
        $this->fontStyle = $style;
        $this->fontSizePt = $size;
        $this->fontSize = $size / self::DPI;

        if ($style === 'B') {
            $this->currentFontKey = 'F2';
        } elseif ($style === 'I') {
            $this->currentFontKey = 'F3';
        } else {
            $this->currentFontKey = 'F1';
        }
    }

    public function SetTextColor(int $r, ?int $g = null, ?int $b = null): void
    {
        if ($g === null || $b === null) {
            $g = $r;
            $b = $r;
        }
        $this->textColor = [
            max(0.0, min(1.0, $r / 255)),
            max(0.0, min(1.0, $g / 255)),
            max(0.0, min(1.0, $b / 255)),
        ];
    }

    public function SetDrawColor(int $r, ?int $g = null, ?int $b = null): void
    {
        if ($g === null || $b === null) {
            $g = $r;
            $b = $r;
        }
        $this->drawColor = [
            max(0.0, min(1.0, $r / 255)),
            max(0.0, min(1.0, $g / 255)),
            max(0.0, min(1.0, $b / 255)),
        ];
    }

    public function SetFillColor(int $r, ?int $g = null, ?int $b = null): void
    {
        if ($g === null || $b === null) {
            $g = $r;
            $b = $r;
        }
        $this->fillColor = [
            max(0.0, min(1.0, $r / 255)),
            max(0.0, min(1.0, $g / 255)),
            max(0.0, min(1.0, $b / 255)),
        ];
    }

    public function AddPage(string $orientation = '', $size = '', bool $rotation = false): void
    {
        if ($this->inPage) {
            $this->endPage();
        }

        $this->page++;
        $this->elements = [];
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->lasth = 0.0;
        $this->inPage = true;
    }

    private function endPage(): void
    {
        $this->pages[$this->page] = $this->elements;
        $this->elements = [];
        $this->inPage = false;
    }

    public function Cell(float $w, float $h = 0.0, string $txt = '', int $border = 0, int $ln = 0, string $align = '', bool $fill = false, string $link = ''): void
    {
        if ($h <= 0.0) {
            $h = $this->fontSize * self::DPI * 0.35;
        }

        if ($w <= 0.0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $textWidth = $this->GetStringWidth($txt);
        $textX = $this->x + $this->cMargin;

        if ($align === 'R') {
            $textX = $this->x + $w - $this->cMargin - $textWidth;
        } elseif ($align === 'C') {
            $textX = $this->x + ($w / 2) - ($textWidth / 2);
        }

        $baseline = $this->y + $h - ($this->fontSize * 0.35 * self::DPI / $this->k);

        if ($fill || $border > 0) {
            $this->elements[] = [
                'type' => 'rect',
                'x' => $this->x,
                'y' => $this->y,
                'w' => $w,
                'h' => $h,
                'border' => $border > 0,
                'fill' => $fill,
                'drawColor' => $this->drawColor,
                'fillColor' => $this->fillColor,
                'lineWidth' => $this->lineWidth,
            ];
        }

        $this->elements[] = [
            'type' => 'text',
            'x' => $textX,
            'y' => $baseline,
            'text' => $txt,
            'font' => $this->currentFontKey,
            'size' => $this->fontSizePt,
            'color' => $this->textColor,
        ];

        $this->lasth = $h;

        if ($ln > 0) {
            $this->x = $this->lMargin;
            $this->y += $h;
            if ($this->autoPageBreak && $this->y >= $this->pageBreakTrigger) {
                $this->AddPage();
            }
        } else {
            $this->x += $w;
        }
    }

    public function Ln(?float $h = null): void
    {
        $this->x = $this->lMargin;
        if ($h === null) {
            $this->y += $this->lasth > 0.0 ? $this->lasth : ($this->fontSize * self::DPI / $this->k * 1.2);
        } else {
            $this->y += $h;
        }
        if ($this->autoPageBreak && $this->y >= $this->pageBreakTrigger) {
            $this->AddPage();
        }
    }

    public function GetStringWidth(string $s): float
    {
        $length = mb_strlen($s, 'UTF-8');
        $avgWidth = 0.5 * $this->fontSizePt / $this->k;
        return max(0.0, $length * $avgWidth);
    }

    public function Output(string $dest = 'F', string $name = '', bool $isUTF8 = false)
    {
        if ($this->inPage) {
            $this->endPage();
        }

        $pdf = $this->buildDocument();

        if ($dest === 'S') {
            return $pdf;
        }

        if ($dest === 'I' || $dest === 'D') {
            header('Content-Type: application/pdf');
            if ($dest === 'D') {
                header('Content-Disposition: attachment; filename="' . ($name ?: 'document.pdf') . '"');
            }
            echo $pdf;
            return null;
        }

        $filePath = $name !== '' ? $name : 'document.pdf';
        file_put_contents($filePath, $pdf);
        return null;
    }

    private function buildDocument(): string
    {
        $objects = [];
        $offsets = [];
        $buffer = "%PDF-1.3\n";

        $fonts = [
            'F1' => ['name' => 'Helvetica'],
            'F2' => ['name' => 'Helvetica-Bold'],
            'F3' => ['name' => 'Helvetica-Oblique'],
        ];

        $contentObjects = [];
        foreach ($this->pages as $pageIndex => $elements) {
            $contentObjects[$pageIndex] = $this->buildContentStream($elements);
        }

        // 1: Catalog
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';

        // 2: Pages
        $kids = [];
        $pageCount = count($this->pages);
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = sprintf('%d 0 R', ($i * 2) + 5);
        }
        $objects[] = sprintf('2 0 obj << /Type /Pages /Kids [ %s ] /Count %d >> endobj', implode(' ', $kids), $pageCount);

        // 3..: Fonts
        $fontMap = [];
        $objNumber = 3;
        foreach ($fonts as $key => $font) {
            $objects[] = sprintf('%d 0 obj << /Type /Font /Subtype /Type1 /BaseFont /%s >> endobj', $objNumber, $font['name']);
            $fontMap[$key] = $objNumber;
            $objNumber++;
        }

        // Pages + contents
        foreach ($this->pages as $index => $elements) {
            $pageObjNum = $objNumber;
            $contentObjNum = $objNumber + 1;

            $fontResources = [];
            foreach ($fontMap as $key => $number) {
                $fontResources[] = sprintf('/%s %d 0 R', $key, $number);
            }

            $objects[] = sprintf('%d 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /ProcSet [/PDF /Text] /Font << %s >> >> /Contents %d 0 R >> endobj', $pageObjNum, $this->wPt, $this->hPt, implode(' ', $fontResources), $contentObjNum);

            $stream = $contentObjects[$index];
            $objects[] = sprintf('%d 0 obj << /Length %d >> stream\n%s\nendstream endobj', $contentObjNum, strlen($stream), $stream);

            $objNumber += 2;
        }

        // Info object placeholder (not storing metadata for now)
        $objects[] = ' ';

        // Build final buffer with offsets
        $offset = strlen($buffer);
        foreach ($objects as $object) {
            if (trim($object) === '') {
                continue;
            }
            $offsets[] = $offset;
            $buffer .= $object . "\n";
            $offset = strlen($buffer);
        }

        $xrefPos = strlen($buffer);
        $buffer .= "xref\n0 " . (count($offsets) + 1) . "\n";
        $buffer .= "0000000000 65535 f \n";
        foreach ($offsets as $position) {
            $buffer .= sprintf("%010d 00000 n \n", $position);
        }

        $buffer .= "trailer\n<< /Size " . (count($offsets) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

        return $buffer;
    }

    private function buildContentStream(array $elements): string
    {
        $output = "BT\n";
        $currentFont = '';
        $currentSize = 0.0;
        $currentColor = [-1.0, -1.0, -1.0];
        $currentStroke = [-1.0, -1.0, -1.0];
        $currentFill = [-1.0, -1.0, -1.0];
        $currentLineWidth = -1.0;

        foreach ($elements as $element) {
            if ($element['type'] === 'text') {
                if ($element['font'] !== $currentFont || $element['size'] !== $currentSize) {
                    $output .= sprintf('/%s %.2F Tf\n', $element['font'], $element['size']);
                    $currentFont = $element['font'];
                    $currentSize = $element['size'];
                }

                if ($element['color'] !== $currentColor) {
                    $output .= sprintf('%.3F %.3F %.3F rg\n', $element['color'][0], $element['color'][1], $element['color'][2]);
                    $currentColor = $element['color'];
                }

                $x = $element['x'] * $this->k;
                $y = ($this->h - $element['y']) * $this->k;
                $output .= sprintf('1 0 0 1 %.2F %.2F Tm (%s) Tj\n', $x, $y, $this->escapeText($element['text']));
                continue;
            }

            $output .= "ET\n";

            if ($element['type'] === 'rect') {
                if ($element['border']) {
                    if ($element['drawColor'] !== $currentStroke) {
                        $currentStroke = $element['drawColor'];
                        $output .= sprintf('%.3F %.3F %.3F RG\n', $currentStroke[0], $currentStroke[1], $currentStroke[2]);
                    }
                    if ($element['lineWidth'] !== $currentLineWidth) {
                        $currentLineWidth = $element['lineWidth'];
                        $output .= sprintf('%.3F w\n', max(0.1, $currentLineWidth) * $this->k);
                    }
                }

                if ($element['fill']) {
                    if ($element['fillColor'] !== $currentFill) {
                        $currentFill = $element['fillColor'];
                        $output .= sprintf('%.3F %.3F %.3F rg\n', $currentFill[0], $currentFill[1], $currentFill[2]);
                    }
                }

                $x = $element['x'] * $this->k;
                $y = ($this->h - $element['y'] - $element['h']) * $this->k;
                $w = $element['w'] * $this->k;
                $h = $element['h'] * $this->k;

                $op = 'n';
                if ($element['fill'] && $element['border']) {
                    $op = 'B';
                } elseif ($element['fill']) {
                    $op = 'f';
                } elseif ($element['border']) {
                    $op = 'S';
                }

                if ($op !== 'n') {
                    $output .= sprintf('%.2F %.2F %.2F %.2F re %s\n', $x, $y, $w, $h, $op);
                }

                $output .= "BT\n";
                $currentFont = '';
                $currentSize = 0.0;
                $currentColor = [-1.0, -1.0, -1.0];
                continue;
            }

            if ($element['type'] === 'line') {
                if ($element['drawColor'] !== $currentStroke) {
                    $currentStroke = $element['drawColor'];
                    $output .= sprintf('%.3F %.3F %.3F RG\n', $currentStroke[0], $currentStroke[1], $currentStroke[2]);
                }
                if ($element['lineWidth'] !== $currentLineWidth) {
                    $currentLineWidth = $element['lineWidth'];
                    $output .= sprintf('%.3F w\n', max(0.1, $currentLineWidth) * $this->k);
                }

                $x1 = $element['x1'] * $this->k;
                $y1 = ($this->h - $element['y1']) * $this->k;
                $x2 = $element['x2'] * $this->k;
                $y2 = ($this->h - $element['y2']) * $this->k;
                $output .= sprintf('%.2F %.2F m %.2F %.2F l S\n', $x1, $y1, $x2, $y2);

                $output .= "BT\n";
                $currentFont = '';
                $currentSize = 0.0;
                $currentColor = [-1.0, -1.0, -1.0];
                continue;
            }

            $output .= "BT\n";
        }

        $output .= "ET";
        return $output;
    }

    /**
     * @return array<int,string>
     */
    private function wrapTextIntoLines(string $text, float $width): array
    {
        $innerWidth = max(1.0, $width - ($this->cMargin * 2));
        $avgCharWidth = max(0.1, 0.5 * $this->fontSizePt / $this->k);
        $maxChars = max(1, (int) floor($innerWidth / $avgCharWidth));

        $rawLines = preg_split("/\r\n|\r|\n/", $text) ?: [$text];
        $lines = [];

        foreach ($rawLines as $rawLine) {
            if ($rawLine === '') {
                $lines[] = '';
                continue;
            }

            $wrapped = wordwrap($rawLine, $maxChars, "\n", true);
            $lines = array_merge($lines, explode("\n", $wrapped));
        }

        if ($lines === []) {
            $lines[] = '';
        }

        return array_map(
            static function (string $line): string {
                return rtrim($line);
            },
            $lines
        );
    }

    private function escapeText(string $text): string
    {
        $text = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
        return str_replace(array("\r", "\n"), array('\\r', '\\n'), $text);
    }
}
