<?php
/**
 * portal/simple_pdf.php
 *
 * A minimal, dependency-free PDF writer for the business fee statement
 * feature. No PDF library exists anywhere in this repo, and this project's
 * convention is plain hand-rolled code over vendored SDKs (see
 * portal/supabase.php, portal/stripe.php) — a fee statement is just text,
 * lines, and a table, well within reach of hand-writing valid PDF 1.4
 * bytes directly, with no GD/zlib/Composer dependency.
 *
 * Coordinates passed to this class's methods use a top-left origin (y
 * grows downward, like normal screen/HTML coordinates) for ergonomics;
 * PDF's native coordinate system (bottom-left origin) is handled internally.
 *
 * Usage:
 *   $pdf = new SimplePdf();
 *   $pdf->addPage();
 *   $pdf->text(50, 50, 'Hello', 'F2', 16);
 *   $pdf->line(50, 70, 560, 70);
 *   header('Content-Type: application/pdf');
 *   echo $pdf->output();
 */
class SimplePdf {
    private $pageWidth;
    private $pageHeight;
    private $pages = [];
    private $currentContent = '';
    private $hasContent = false;
    private $images = [];   // name => ['data'=>jpeg bytes,'w'=>px,'h'=>px,'cs'=>colorspace]

    // Adobe's standard Helvetica AFM glyph widths (units per 1000em) for
    // printable ASCII 32-126 — this is published PDF-spec core-font metric
    // data (part of the 14 standard fonts every PDF reader must support),
    // not third-party code. Used only for right-aligning amount columns.
    private static $helveticaWidths = [
        32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,
        42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,
        52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,
        62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,
        72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,
        82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,
        92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,
        102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,
        111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,
        120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,
    ];

    public function __construct($pageWidth = 612, $pageHeight = 792) {
        $this->pageWidth  = $pageWidth;
        $this->pageHeight = $pageHeight;
    }

    public function addPage() {
        if ($this->hasContent) {
            $this->pages[] = $this->currentContent;
        }
        $this->currentContent = '';
        $this->hasContent = true;
    }

    /** Encodes to WinAnsi (core-font-compatible) and escapes PDF literal-string special chars. */
    private function esc($s) {
        $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string)$s);
        if ($s === false) $s = preg_replace('/[^\x20-\x7E]/', '?', (string)$s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    /** Approximate rendered width of $text at $size using Helvetica's published metrics. */
    public function textWidth($text, $size = 10) {
        $w = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($text[$i]);
            $w += self::$helveticaWidths[$code] ?? 556;
        }
        return $w / 1000 * $size;
    }

    /** Draws left-aligned text with its top-left origin at ($x, $y). */
    public function text($x, $y, $text, $font = 'F1', $size = 10) {
        $py = $this->pageHeight - $y - $size; // approximate baseline from a top-anchored y
        $this->currentContent .= sprintf(
            "BT /%s %d Tf %.2f %.2f Td (%s) Tj ET\n",
            $font, $size, $x, $py, $this->esc($text)
        );
    }

    /** Draws $text right-aligned so it ends at $rightX. */
    public function textRight($rightX, $y, $text, $font = 'F1', $size = 10) {
        $x = $rightX - $this->textWidth($text, $size);
        $this->text($x, $y, $text, $font, $size);
    }

    public function line($x1, $y1, $x2, $y2, $width = 0.5) {
        $py1 = $this->pageHeight - $y1;
        $py2 = $this->pageHeight - $y2;
        $this->currentContent .= sprintf("%.2f w %.2f %.2f m %.2f %.2f l S\n", $width, $x1, $py1, $x2, $py2);
    }

    /** $y/$w/$h use the top-left-origin convention like text(); $fill draws filled instead of outlined. */
    public function rect($x, $y, $w, $h, $fill = false) {
        $py = $this->pageHeight - $y - $h;
        $op = $fill ? 'f' : 'S';
        $this->currentContent .= sprintf("%.2f %.2f %.2f %.2f re %s\n", $x, $py, $w, $h, $op);
    }

    /** Sets fill (and stroke) colour for subsequent shapes and text; 0-255 per channel. */
    public function setColor($r, $g, $b) {
        $this->currentContent .= sprintf("%.3f %.3f %.3f rg %.3f %.3f %.3f RG\n", $r/255, $g/255, $b/255, $r/255, $g/255, $b/255);
    }

    /**
     * Draws a QR matrix (array of '0'/'1' strings, as from QRcode::text())
     * as vector squares, $size points wide, top-left at ($x,$y). Adds a white
     * quiet zone of 4 modules around it.
     */
    public function qr($x, $y, $size, array $matrix) {
        $n = count($matrix);
        if (!$n) return;
        $m = $size / ($n + 8);
        $this->setColor(255, 255, 255);
        $this->rect($x, $y, $size, $size, true);
        $this->setColor(0, 0, 0);
        foreach ($matrix as $r => $row) {
            $len = strlen($row);
            for ($c = 0; $c < $len; $c++) {
                if ($row[$c] !== '1') continue;
                $run = 1; // merge horizontal runs to keep the stream small
                while ($c + $run < $len && $row[$c + $run] === '1') $run++;
                $this->rect($x + (4 + $c) * $m, $y + (4 + $r) * $m, $run * $m + 0.01, $m + 0.01, true);
                $c += $run - 1;
            }
        }
    }

    /** Registers JPEG bytes as an image (no GD needed). Returns its name, or null if not a usable JPEG. */
    public function addJpeg($bytes) {
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) return null;
        $ch = $info['channels'] ?? 3;
        if ($ch === 4) return null; // CMYK JPEGs: skip
        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = ['data' => $bytes, 'w' => $info[0], 'h' => $info[1], 'cs' => $ch === 1 ? '/DeviceGray' : '/DeviceRGB'];
        return $name;
    }

    public function imageSize($name) { return isset($this->images[$name]) ? [$this->images[$name]['w'], $this->images[$name]['h']] : null; }

    /** Draws a registered image with its top-left at ($x,$y), $w x $h points. */
    public function image($name, $x, $y, $w, $h) {
        if (!isset($this->images[$name])) return;
        $py = $this->pageHeight - $y - $h;
        $this->currentContent .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $w, $h, $x, $py, $name);
    }

    public function pageHeight() { return $this->pageHeight; }
    public function pageWidth()  { return $this->pageWidth; }

    /** Returns the raw PDF bytes as a string (does not write headers/output itself). */
    public function output() {
        if ($this->hasContent) {
            $this->pages[] = $this->currentContent;
        }
        if (!$this->pages) {
            $this->pages[] = '';
        }

        $n = 1;
        $catalogNum     = $n++;
        $pagesNum       = $n++;
        $fontRegularNum = $n++;
        $fontBoldNum    = $n++;

        $imageNums = [];
        foreach ($this->images as $name => $_) $imageNums[$name] = $n++;

        $pageNums = [];
        $contentNums = [];
        foreach ($this->pages as $content) {
            $pageNums[]    = $n++;
            $contentNums[] = $n++;
        }

        $objects = [];
        $objects[$catalogNum] = "<< /Type /Catalog /Pages {$pagesNum} 0 R >>";
        $kids = implode(' ', array_map(fn($pn) => "{$pn} 0 R", $pageNums));
        $objects[$pagesNum] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageNums) . " >>";
        $objects[$fontRegularNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[$fontBoldNum]    = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $xobj = '';
        foreach ($this->images as $name => $img) {
            $objects[$imageNums[$name]] = "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace {$img['cs']} "
                . "/BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['data']) . " >>\nstream\n{$img['data']}\nendstream";
            $xobj .= "/{$name} {$imageNums[$name]} 0 R ";
        }
        $xobjRes = $xobj ? " /XObject << {$xobj}>>" : '';

        foreach ($this->pages as $i => $content) {
            $pageNum    = $pageNums[$i];
            $contentNum = $contentNums[$i];
            $objects[$pageNum] =
                "<< /Type /Page /Parent {$pagesNum} 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] "
                . "/Resources << /Font << /F1 {$fontRegularNum} 0 R /F2 {$fontBoldNum} 0 R >>{$xobjRes} >> "
                . "/Contents {$contentNum} 0 R >>";
            $objects[$contentNum] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $totalObjects = $n; // highest object number + 1
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjects; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$totalObjects} /Root {$catalogNum} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
