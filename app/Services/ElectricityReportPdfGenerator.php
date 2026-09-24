<?php

namespace App\Services;

use App\Models\ElectricityReport;
use App\Models\ReportSection;
use App\Support\HighlightColor;
use App\Support\RasterImageForTcpdf;
use TCPDF;
use Throwable;

/**
 * Generates electricity inspection PDFs matching the GIS sample Word report layout.
 */
class ElectricityReportPdfGenerator
{
    private string $font = 'arialbd';

    private string $fontBold = 'arialbd';

    private float $left = 15;

    private float $right = 15;

    private float $contentW = 180;

    public function renderBinary(ElectricityReport $report): string
    {
        $prev = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        set_error_handler(function ($errno, $message, $file) {
            return str_contains((string) $file, 'tcpdf');
        });

        $tempImages = [];

        try {
            $this->registerReportFonts();

            $logoPath = $this->resolveLogoPath();
            $pdf = new ElectricityReportTCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->reportNo = $report->reportNumber();
            $pdf->reportDate = $report->reportInspectionDateFormatted();
            $pdf->logoPath = $logoPath;
            $pdf->reportFont = $this->font;
            $pdf->reportFontBold = $this->fontBold;

            $pdf->SetCreator(config('app.name'));
            $pdf->SetAuthor(config('app.name'));
            $pdf->SetTitle('تقرير فحص الكهرباء — '.$report->title);
            $pdf->SetMargins($this->left, 26, $this->right);
            $pdf->SetHeaderMargin(6);
            $pdf->SetFooterMargin(18);
            $pdf->setRTL(true);
            $pdf->SetFont($this->font, '', 11);
            $pdf->setCellPaddings(2, 1.5, 2, 1.5);
            $pdf->setCellHeightRatio(1.35);

            $this->contentW = $pdf->getPageWidth() - $this->left - $this->right;

            // Page 1: title alone (no header/footer)
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddPage();
            $this->renderCoverTitlePage($pdf);

            // Page 2+: content with header/footer
            $pdf->setPrintHeader(true);
            $pdf->setPrintFooter(true);
            $pdf->SetAutoPageBreak(true, 24);
            $pdf->AddPage();

            $sections = $report->sections;
            $hasGrounding = $sections->contains(
                fn (ReportSection $s) => $s->has_table && $s->table_type === 'grounding_main'
            );
            if ($hasGrounding) {
                $this->renderLegend($pdf);
            }

            foreach ($sections as $sec) {
                $this->ensureSpace($pdf, 28);
                $this->renderSection($pdf, $sec, $tempImages);
            }

            return $pdf->Output('', 'S');
        } finally {
            restore_error_handler();
            foreach ($tempImages as $tmp) {
                if (is_string($tmp) && is_file($tmp)) {
                    @unlink($tmp);
                }
            }
            if ($prev !== false) {
                ini_set('memory_limit', $prev);
            }
        }
    }

    private function renderCoverTitlePage(TCPDF $pdf): void
    {
        $pageH = $pdf->getPageHeight();
        $pdf->SetFont($this->fontBold, '', 28);
        $pdf->SetTextColor(192, 0, 0);
        $pdf->SetY(($pageH / 2) - 12);
        $pdf->Cell($this->contentW, 14, 'تقرير فحص الكهرباء', 0, 1, 'C', false, '', 0, false, 'T', 'M');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function renderLegend(TCPDF $pdf): void
    {
        $pdf->SetFont($this->fontBold, '', 12);
        $pdf->Cell($this->contentW, 7, 'الجداول:', 0, 1, 'R');

        $half = $this->contentW / 2;
        $pdf->SetFont($this->font, '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 80, 80);
        $pdf->MultiCell($half, 9, 'المحدد باللون الأحمر يجب اتخاذ اجراء لإصلاحه.', 1, 'C', true, 0);
        $pdf->SetFillColor(100, 220, 60);
        $pdf->MultiCell($half, 9, 'المحدد باللون الأخضر موصل بطريقة صحيحة.', 1, 'C', true, 1);
        $pdf->Ln(4);
    }

    /** @param list<string> $tempImages */
    private function renderSection(TCPDF $pdf, ReportSection $sec, array &$tempImages): void
    {
        $hasVisual = trim((string) $sec->description) !== ''
            || $sec->commentsList() !== []
            || $sec->imageAbsolutePaths() !== [];

        // Standalone grounding table (no photos/comments) — like جدول 1 in the sample
        if ($sec->has_table && $sec->table_type === 'grounding_main' && ! $hasVisual) {
            if ($sec->table_title) {
                $pdf->SetFont($this->fontBold, '', 12);
                $pdf->SetTextColor(192, 0, 0);
                $pdf->Cell($this->contentW, 7, $sec->table_title, 0, 1, 'R');
                $pdf->SetTextColor(0, 0, 0);
            }
            $this->renderGroundingTable($pdf, $sec);
            $pdf->Ln(6);

            return;
        }

        // Section title
        $pdf->SetFont($this->fontBold, '', 13);
        $pdf->SetTextColor(0, 0, 0);
        $title = trim($sec->name);
        if ($title !== '' && ! str_ends_with($title, ':') && ! str_ends_with($title, '：')) {
            $title .= ':';
        }
        if ($title !== '') {
            $pdf->Cell($this->contentW, 8, $title, 0, 1, 'R');
        }

        if ($hasVisual) {
            $this->renderSectionCard($pdf, $sec, $tempImages);
            $pdf->Ln(3);
        }

        if ($sec->has_table) {
            if ($sec->table_title) {
                $this->ensureSpace($pdf, 20);
                $pdf->SetFont($this->fontBold, '', 11);
                $pdf->SetTextColor(192, 0, 0);
                $pdf->Cell($this->contentW, 7, $sec->table_title, 0, 1, 'R');
                $pdf->SetTextColor(0, 0, 0);
            }
            if ($sec->table_type === 'grounding_main') {
                $this->renderGroundingTable($pdf, $sec);
            } elseif ($sec->table_type === 'mcb_panel') {
                $this->renderMcbTable($pdf, $sec);
            }
            $pdf->Ln(5);
        }
    }

    /** @param list<string> $tempImages */
    private function renderSectionCard(TCPDF $pdf, ReportSection $sec, array &$tempImages): void
    {
        $startY = $pdf->GetY();
        $startPage = $pdf->getPage();
        $pad = 3;
        $innerW = $this->contentW - ($pad * 2);

        // Reserve top padding inside the border
        $pdf->SetY($startY + $pad);
        $pdf->SetX($this->left + $pad);

        if (trim((string) $sec->description) !== '') {
            $pdf->SetFont($this->fontBold, '', 10);
            $pdf->MultiCell($innerW, 6, 'الوصف: '.$sec->description, 0, 'R', false, 1, $this->left + $pad);
            $pdf->Ln(1);
        }

        $images = $sec->imageAbsolutePaths();
        if ($images !== []) {
            $this->renderImagesRow($pdf, $images, $tempImages, $innerW, $this->left + $pad);
            $pdf->Ln(2);
        }

        $comments = $sec->commentsList();
        if ($comments !== []) {
            $pdf->SetFont($this->fontBold, '', 10);
            $pdf->MultiCell($innerW, 6, 'التعليقات:', 0, 'R', false, 1, $this->left + $pad);
            foreach ($comments as $comment) {
                $this->ensureSpace($pdf, 12);
                $body = ltrim($comment['text'], " \t•\-–—*·");
                $text = '• '.$body;
                $fill = HighlightColor::hexFill($comment['color']);
                $pdf->SetFont($this->font, '', 9);
                if ($fill) {
                    [$r, $g, $b] = $this->hexToRgb($fill);
                    $pdf->SetFillColor($r, $g, $b);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->MultiCell($innerW, 6, $text, 0, 'R', true, 1, $this->left + $pad);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->MultiCell($innerW, 6, $text, 0, 'R', false, 1, $this->left + $pad);
                }
                $pdf->Ln(1);
            }
        }

        $endY = $pdf->GetY() + $pad;
        $endPage = $pdf->getPage();

        // Draw border only when card stayed on one page
        if ($endPage === $startPage && $endY > $startY + 4) {
            $pdf->Rect($this->left, $startY, $this->contentW, $endY - $startY);
            $pdf->SetY($endY);
        } else {
            $pdf->SetY($endY);
        }
    }

    /**
     * @param  list<string>  $images
     * @param  list<string>  $tempImages
     */
    private function renderImagesRow(TCPDF $pdf, array $images, array &$tempImages, float $innerW, float $xStart): void
    {
        $perRow = 4;
        $gap = 3;

        foreach (array_chunk($images, $perRow) as $rowImages) {
            $count = count($rowImages);
            $imgW = ($innerW - ($gap * max(0, $count - 1))) / $count;
            $imgH = min(52, $imgW * 0.72);

            $this->ensureSpace($pdf, $imgH + 4);
            $y = $pdf->GetY();

            $pdf->setRTL(false);
            foreach ($rowImages as $i => $imgPath) {
                [$embed, $isTemp] = RasterImageForTcpdf::prepareForEmbedding($imgPath);
                if ($isTemp) {
                    $tempImages[] = $embed;
                }
                $x = $xStart + ($i * ($imgW + $gap));
                try {
                    $pdf->Image($embed, $x, $y, $imgW, $imgH, '', '', '', false, 120, '', false, false, 1);
                } catch (Throwable) {
                    // skip broken image
                }
            }
            $pdf->setRTL(true);
            $pdf->SetY($y + $imgH + 2);
        }
    }

    private function renderGroundingTable(TCPDF $pdf, ReportSection $sec): void
    {
        $data = is_array($sec->table_data) ? $sec->table_data : [];
        $cable = $data['grounding_cable'] ?? ['value' => '', 'color' => 'none'];
        $reading = $data['grounding_reading'] ?? ['value' => '', 'color' => 'none'];
        $voltages = is_array($data['voltages'] ?? null) ? $data['voltages'] : [];
        $half = $this->contentW / 2;
        $rowH = 8;

        $this->ensureSpace($pdf, 40);

        // Header
        $pdf->SetFont($this->fontBold, '', 11);
        $pdf->SetFillColor(70, 70, 70);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($this->contentW, $rowH, 'فحص التأريض', 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        $this->kvRow($pdf, 'هل كابل التأريض موجود؟', (string) ($cable['value'] ?? ''), $cable['color'] ?? null, $half, $rowH);
        $this->kvRow($pdf, 'قراءة فحص التأريض', (string) ($reading['value'] ?? ''), $reading['color'] ?? null, $half, $rowH);

        // Voltage header
        $pdf->SetFont($this->fontBold, '', 11);
        $pdf->SetFillColor(70, 70, 70);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($this->contentW, $rowH, 'ضغط لوحة التوزيع الرئيسية', 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        // Sample uses one label|value per row
        foreach ($voltages as $v) {
            $label = (string) ($v['label'] ?? '');
            $value = (string) ($v['value'] ?? '');
            if ($label === '' && $value === '') {
                continue;
            }
            $this->kvRow($pdf, $label, $value, $v['color'] ?? null, $half, $rowH);
        }
    }

    private function kvRow(TCPDF $pdf, string $label, string $value, ?string $color, float $half, float $h): void
    {
        $pdf->SetFont($this->fontBold, '', 10);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($half, $h, $label, 1, 0, 'R', false);

        $hex = HighlightColor::hexFill($color ?? 'none');
        if ($hex) {
            [$r, $g, $b] = $this->hexToRgb($hex);
            $pdf->SetFillColor($r, $g, $b);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $pdf->SetFont($this->font, '', 10);
        $pdf->Cell($half, $h, $value !== '' ? $value : ' ', 1, 1, 'C', (bool) $hex);
    }

    private function renderMcbTable(TCPDF $pdf, ReportSection $sec): void
    {
        $data = is_array($sec->table_data) ? $sec->table_data : [];
        $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];
        $col = $this->contentW / 6;
        $h = 7;

        // MCB tables are English LTR — draw left-to-right regardless of document RTL
        $wasRtl = $pdf->getRTL();
        $pdf->setRTL(false);

        foreach ($groups as $gi => $group) {
            $this->ensureSpace($pdf, 35);

            $pdf->SetFont($this->fontBold, '', 9);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($col, $h, 'ELCB', 1, 0, 'C', false);
            $pdf->Cell($col, $h, (string) ($group['elcb'] ?? ''), 1, 0, 'C', false);

            $have = $group['have_label'] ?? null;
            $showHave = $gi === 0 && is_array($have) && trim((string) ($have['value'] ?? '')) !== '';
            if ($showHave) {
                $hex = HighlightColor::hexFill($have['color'] ?? 'green');
                if ($hex) {
                    [$r, $g, $b] = $this->hexToRgb($hex);
                    $pdf->SetFillColor($r, $g, $b);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                }
                $pdf->Cell($col * 2, $h, 'Have Lable?', 1, 0, 'C', (bool) $hex);
                $pdf->Cell($col * 2, $h, (string) ($have['value'] ?? ''), 1, 1, 'C', (bool) $hex);
            } else {
                $pdf->Cell($col * 2, $h, '', 1, 0, 'C', false);
                $pdf->Cell($col * 2, $h, '', 1, 1, 'C', false);
            }

            $pdf->SetFont($this->fontBold, '', 8);
            $pdf->SetFillColor(217, 217, 217);
            $headers = ['MCB Rating', 'Cable Size', 'Validation Status', 'MCB Rating', 'Cable Size', 'Validation Status'];
            foreach ($headers as $i => $label) {
                $pdf->Cell($col, 6, $label, 1, $i === 5 ? 1 : 0, 'C', true);
            }

            $pdf->SetFont($this->font, '', 8);
            $pdf->SetTextColor(0, 0, 0);
            foreach ($group['rows'] ?? [] as $row) {
                $this->ensureSpace($pdf, 10);
                foreach ([$row['left'] ?? [], $row['right'] ?? []] as $sideIdx => $side) {
                    foreach (['mcb', 'cable', 'status'] as $field) {
                        $cell = $side[$field] ?? ['value' => '', 'color' => 'none'];
                        $hex = HighlightColor::hexFill($cell['color'] ?? 'none');
                        if ($hex) {
                            [$r, $g, $b] = $this->hexToRgb($hex);
                            $pdf->SetFillColor($r, $g, $b);
                        } else {
                            $pdf->SetFillColor(255, 255, 255);
                        }
                        $isLast = $sideIdx === 1 && $field === 'status';
                        $val = (string) ($cell['value'] ?? '');
                        $pdf->Cell($col, $h, $val !== '' ? $val : ' ', 1, $isLast ? 1 : 0, 'C', (bool) $hex);
                    }
                }
            }
            $pdf->Ln(4);
        }

        $pdf->setRTL($wasRtl);
    }

    private function ensureSpace(TCPDF $pdf, float $needed): void
    {
        $limit = $pdf->getPageHeight() - $pdf->getBreakMargin() - 2;
        if ($pdf->GetY() + $needed > $limit) {
            $pdf->AddPage();
        }
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function resolveLogoPath(): ?string
    {
        foreach ([env('REPORT_LOGO_PATH'), 'images/company-logo.png', 'images/logo.png'] as $candidate) {
            if (! $candidate) {
                continue;
            }
            $path = public_path($candidate);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function registerReportFonts(): void
    {
        $boldTtf = base_path('resources/fonts/arialbd.ttf');
        if (! is_file($boldTtf)) {
            return;
        }
        $name = \TCPDF_FONTS::addTTFfont($boldTtf, 'TrueTypeUnicode', '', 32);
        if (is_string($name) && $name !== '') {
            $this->font = $name;
            $this->fontBold = $name;
        }
    }
}

class ElectricityReportTCPDF extends TCPDF
{
    public string $reportNo = '';

    public string $reportDate = '';

    public ?string $logoPath = null;

    public string $reportFont = 'arialbd';

    public string $reportFontBold = 'arialbd';

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->tcpdflink = false;
    }

    public function Header(): void
    {
        if ($this->PageNo() <= 1) {
            return;
        }

        $this->setRTL(false);
        if ($this->logoPath && is_readable($this->logoPath)) {
            try {
                $this->Image($this->logoPath, 14, 7, 22, 14, '', '', '', false, 300);
            } catch (Throwable) {
            }
        }

        $pageW = $this->getPageWidth();
        $this->SetTextColor(192, 0, 0);
        $this->SetFont($this->reportFontBold, '', 10);
        $this->SetXY($pageW - 95, 8);
        $this->setRTL(true);
        $this->Cell(80, 5, 'رقم التقرير '.$this->reportNo, 0, 2, 'R');
        $this->SetFont($this->reportFont, '', 9);
        $this->Cell(80, 5, 'تاريخ الفحص '.$this->reportDate, 0, 0, 'R');

        $this->SetTextColor(0, 0, 0);
        $this->SetY(24);
    }

    public function Footer(): void
    {
        // Cover page has no footer
        if ($this->PageNo() <= 1) {
            return;
        }

        $pageW = $this->getPageWidth();
        $pageH = $this->getPageHeight();
        $this->setRTL(false);

        $maroon = [128, 0, 0];
        $rowY = $pageH - 16;
        $iconSize = 3.4;
        $iconY = $rowY + 0.55;
        $textY = $rowY;

        $whatsappIcon = public_path('images/whatsapp_icon.png');
        $emailIcon = public_path('images/email_icon.png');

        $x = 14.0;
        if (is_file($whatsappIcon)) {
            try {
                $this->Image($whatsappIcon, $x, $iconY, $iconSize, $iconSize, '', '', '', false, 300);
            } catch (Throwable) {
            }
        }
        $x += $iconSize + 1.0;
        $this->SetFont($this->reportFontBold, '', 8);
        $this->SetTextColor($maroon[0], $maroon[1], $maroon[2]);
        $this->SetXY($x, $textY);
        $this->Cell(17, 4.2, '36698895', 0, 0, 'L');

        $x = 36.5;
        if (is_file($emailIcon)) {
            try {
                $this->Image($emailIcon, $x, $iconY, $iconSize, $iconSize, '', '', '', false, 300);
            } catch (Throwable) {
            }
        }
        $x += $iconSize + 1.0;
        $this->SetFont($this->reportFontBold, '', 7.5);
        $this->SetTextColor($maroon[0], $maroon[1], $maroon[2]);
        $this->SetXY($x, $textY);
        $this->Cell(42, 4.2, 'infogisgulf@gmail.com', 0, 0, 'L');

        // Center page number
        $this->SetFont($this->reportFont, '', 8);
        $this->SetTextColor(120, 0, 0);
        $this->SetXY($pageW / 2 - 10, $pageH - 12);
        $this->Cell(20, 4, (string) $this->getAliasNumPage().' / '.$this->getAliasNbPages(), 0, 0, 'C');

        // Right company
        $this->SetFont($this->reportFontBold, '', 8);
        $this->SetTextColor($maroon[0], $maroon[1], $maroon[2]);
        $this->SetXY($pageW - 95, $pageH - 12);
        $this->Cell(80, 4, 'GIS VALUATION AND EVALUATION', 0, 0, 'R');

        $this->setRTL(true);
        $this->SetTextColor(0, 0, 0);
    }
}
