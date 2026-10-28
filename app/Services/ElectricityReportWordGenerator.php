<?php

namespace App\Services;

use App\Models\ElectricityReport;
use App\Models\ReportSection;
use App\Support\HighlightColor;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;

class ElectricityReportWordGenerator
{
    private ?string $logoPath = null;

    public function __construct()
    {
        $this->logoPath = $this->resolveLogoPath();
    }

    private function resolveLogoPath(): ?string
    {
        $candidates = array_filter([
            config('report.logo_path'),
            'images/newLog.jpeg',
            'images/company-logo.png',
            'images/company-logo1.png',
        ]);

        foreach ($candidates as $candidate) {
            $path = public_path((string) $candidate);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function renderBinary(ElectricityReport $report): string
    {
        $prev = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $phpWord = new PhpWord;
            $phpWord->setDefaultFontName('Arial');
            $phpWord->setDefaultFontSize(11);
            $phpWord->getSettings()->setThemeFontLang(new Language('ar-SA', null, 'ar-SA'));
            $phpWord->setDefaultParagraphStyle(['alignment' => Jc::RIGHT, 'bidi' => true]);

            // Match sample margins; different first page = cover title only
            $section = $phpWord->addSection([
                'marginTop' => 700,
                'marginBottom' => 1100,
                'marginLeft' => 900,
                'marginRight' => 900,
                'titlePage' => true,
            ]);

            $rtlRight = ['alignment' => Jc::RIGHT, 'bidi' => true, 'spaceAfter' => 60];
            $rtlCenter = ['alignment' => Jc::CENTER, 'bidi' => true, 'spaceAfter' => 60];

            $phpWord->addTableStyle('ReportBox', [
                'width' => 10000, 'unit' => TblWidth::TWIP, 'layout' => 'fixed', 'bidiVisual' => true,
                'borderColor' => '000000', 'borderSize' => 8, 'cellMargin' => 80, 'alignment' => Jc::CENTER,
            ]);
            $phpWord->addTableStyle('ReportGrid', [
                'width' => 10000, 'unit' => TblWidth::TWIP, 'layout' => 'fixed', 'bidiVisual' => true,
                'borderColor' => '000000', 'borderSize' => 6, 'cellMargin' => 50, 'alignment' => Jc::CENTER,
            ]);
            // MCB tables are English LTR — must NOT use bidiVisual or columns reverse
            $phpWord->addTableStyle('McbGrid', [
                'width' => 10000, 'unit' => TblWidth::TWIP, 'layout' => 'fixed', 'bidiVisual' => false,
                'borderColor' => '000000', 'borderSize' => 6, 'cellMargin' => 40, 'alignment' => Jc::CENTER,
            ]);
            $phpWord->addTableStyle('HeaderBar', [
                'width' => 10000, 'unit' => TblWidth::TWIP, 'layout' => 'fixed', 'bidiVisual' => true,
                'borderColor' => 'FFFFFF', 'borderSize' => 0, 'cellMargin' => 40, 'alignment' => Jc::CENTER,
            ]);
            $phpWord->addTableStyle('FooterBar', [
                'width' => 10000, 'unit' => TblWidth::TWIP, 'layout' => 'fixed', 'bidiVisual' => false,
                'borderColor' => 'FFFFFF', 'borderSize' => 0, 'cellMargin' => 20, 'alignment' => Jc::CENTER,
            ]);

            // Empty first-page header/footer so cover stays clean
            $section->addHeader(Header::FIRST);
            $section->addFooter(Footer::FIRST);

            $this->addRepeatingHeader($section, $report);
            $this->addRepeatingFooter($section);

            // Page 1 — title alone
            for ($i = 0; $i < 8; $i++) {
                $section->addTextBreak(1);
            }
            $section->addText('تقرير فحص الكهرباء', [
                'bold' => true, 'size' => 28, 'color' => 'C00000', 'rtl' => true,
            ], $rtlCenter);
            $section->addPageBreak();

            // Property / client info on content start
            $section->addText($report->title, ['bold' => true, 'size' => 13, 'rtl' => true], $rtlCenter);
            $client = trim((string) ($report->client_name ?: ''));
            $addr = $report->propertyAddress();
            $metaLine = trim(($client !== '' ? 'العميل: '.$client : '').($addr !== '---' ? ($client !== '' ? '  —  ' : '').$addr : ''));
            if ($metaLine !== '') {
                $section->addText($metaLine, ['size' => 10, 'color' => '555555', 'rtl' => true], $rtlCenter);
            }
            $section->addTextBreak(1);

            $sections = $report->sections;
            $hasGrounding = $sections->contains(fn (ReportSection $s) => $s->has_table && $s->table_type === 'grounding_main');
            if ($hasGrounding) {
                $this->renderLegend($section, $rtlRight);
            }

            foreach ($sections as $sec) {
                $this->renderSectionBlock($section, $sec, $rtlRight, $rtlCenter);
            }

            $tmp = tempnam(sys_get_temp_dir(), 'elecdocx');
            $path = $tmp.'.docx';
            @unlink($tmp);
            IOFactory::createWriter($phpWord, 'Word2007')->save($path);
            $binary = (string) file_get_contents($path);
            @unlink($path);

            return $binary;
        } finally {
            if ($prev !== false) {
                ini_set('memory_limit', $prev);
            }
        }
    }

    private function addRepeatingHeader(Section $section, ElectricityReport $report): void
    {
        $header = $section->addHeader();
        $table = $header->addTable('HeaderBar');
        $table->addRow();

        // Right cell (meta) — in bidiVisual first cell is right
        $meta = $table->addCell(6000);
        $meta->addText('رقم التقرير '.$report->reportNumber(), [
            'bold' => true, 'size' => 10, 'color' => 'C00000', 'rtl' => true,
        ], ['alignment' => Jc::RIGHT, 'bidi' => true]);
        $meta->addText('تاريخ الفحص '.$report->reportInspectionDateFormatted(), [
            'bold' => true, 'size' => 10, 'color' => 'C00000', 'rtl' => true,
        ], ['alignment' => Jc::RIGHT, 'bidi' => true]);

        $logo = $table->addCell(4000);
        if ($this->logoPath && is_file($this->logoPath)) {
            // Height only — preserve aspect ratio
            $logo->addImage($this->logoPath, ['height' => 42, 'alignment' => Jc::LEFT]);
        }
    }

    private function addRepeatingFooter(Section $section): void
    {
        $footer = $section->addFooter();
        $table = $footer->addTable('FooterBar');
        $table->addRow();

        $ltr = ['alignment' => Jc::LEFT, 'bidi' => false, 'spaceAfter' => 0];
        $rtlRight = ['alignment' => Jc::RIGHT, 'bidi' => true, 'spaceAfter' => 0];
        $font = ['bold' => true, 'size' => 8, 'color' => '800000', 'rtl' => false];
        $fontSm = ['size' => 7, 'color' => '800000', 'rtl' => false];
        $fontAr = ['bold' => true, 'size' => 8, 'color' => '800000', 'rtl' => true];

        $whatsappIcon = public_path('images/whatsapp_icon.png');
        $emailIcon = public_path('images/email_icon.png');
        $instagramIcon = public_path('images/instagram.png');

        // Left: contacts + address
        $contacts = $table->addCell(5600);
        $row = $contacts->addTable([
            'width' => 5500, 'unit' => TblWidth::TWIP, 'layout' => 'fixed',
            'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 10,
        ]);
        $row->addRow();

        $waIcon = $row->addCell(380);
        if (is_file($whatsappIcon)) {
            try {
                $waIcon->addImage($whatsappIcon, ['width' => 11, 'height' => 11]);
            } catch (\Throwable) {
            }
        }
        $row->addCell(1200)->addText('36698895', $font, $ltr);

        $emIcon = $row->addCell(380);
        if (is_file($emailIcon)) {
            try {
                $emIcon->addImage($emailIcon, ['width' => 11, 'height' => 11]);
            } catch (\Throwable) {
            }
        }
        $row->addCell(2300)->addText('infogisgulf@gmail.com', $font, $ltr);

        $igIcon = $row->addCell(380);
        if (is_file($instagramIcon)) {
            try {
                $igIcon->addImage($instagramIcon, ['width' => 11, 'height' => 11]);
            } catch (\Throwable) {
            }
        }
        $row->addCell(1400)->addText('gis.Bahrain', $font, $ltr);

        $contacts->addText('Seef District - Kingdom of Bahrain', $fontSm, $ltr);

        // Right: company block
        $company = $table->addCell(4400);
        $company->addText('GIS VALUATION AND EVALUATION', [
            'bold' => true, 'size' => 8, 'color' => '800000', 'rtl' => false,
        ], ['alignment' => Jc::RIGHT, 'bidi' => false, 'spaceAfter' => 0]);
        $company->addText('جي إي إس للتقييم والتثمين العقاري', $fontAr, $rtlRight);
        $company->addText('C.R. 160528-1', $fontSm, ['alignment' => Jc::RIGHT, 'bidi' => false, 'spaceAfter' => 0]);
    }

    private function renderLegend(Section $section, array $rtlRight): void
    {
        $section->addText('الجداول:', ['bold' => true, 'size' => 12, 'rtl' => true], $rtlRight);
        $legend = $section->addTable('ReportGrid');
        $legend->addRow();
        $legend->addCell(5000, ['bgColor' => 'FF5050'])->addText(
            'المحدد باللون الأحمر يجب اتخاذ اجراء لإصلاحه.',
            ['bold' => true, 'size' => 10, 'rtl' => true],
            $rtlRight
        );
        $legend->addCell(5000, ['bgColor' => '64DC3C'])->addText(
            'المحدد باللون الأخضر موصل بطريقة صحيحة.',
            ['bold' => true, 'size' => 10, 'rtl' => true],
            $rtlRight
        );
        $section->addTextBreak(1);
    }

    private function renderSectionBlock(Section $section, ReportSection $sec, array $rtlRight, array $rtlCenter): void
    {
        $hasVisual = trim((string) $sec->description) !== ''
            || $sec->commentsList() !== []
            || $sec->imageAbsolutePaths() !== [];

        if ($sec->has_table && $sec->table_type === 'grounding_main' && ! $hasVisual) {
            if ($sec->table_title) {
                $section->addText($sec->table_title, [
                    'bold' => true, 'size' => 12, 'color' => 'C00000', 'rtl' => true,
                ], $rtlRight);
            }
            $this->renderGroundingTable($section, $sec, $rtlRight, $rtlCenter);
            $section->addTextBreak(1);

            return;
        }

        $title = trim($sec->name);
        if ($title !== '' && ! str_ends_with($title, ':') && ! str_ends_with($title, '：')) {
            $title .= ':';
        }
        if ($title !== '') {
            $section->addText($title, ['bold' => true, 'size' => 13, 'rtl' => true], $rtlRight);
        }

        if ($hasVisual) {
            $box = $section->addTable('ReportBox');
            $box->addRow();
            $cell = $box->addCell(10000);

            if (trim((string) $sec->description) !== '') {
                $cell->addText('الوصف: '.$sec->description, [
                    'bold' => true, 'size' => 11, 'rtl' => true,
                ], $rtlRight);
            }

            $images = $sec->imageAbsolutePaths();
            if ($images !== []) {
                // Sample: 5 slots per row; unused slots stay empty (no stretch)
                $slots = 5;
                $slotW = (int) floor(9800 / $slots);
                foreach (array_chunk($images, $slots) as $slice) {
                    $imgTable = $cell->addTable([
                        'width' => 9800, 'unit' => TblWidth::TWIP, 'layout' => 'fixed',
                        'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 40,
                    ]);
                    $imgTable->addRow();
                    for ($i = 0; $i < $slots; $i++) {
                        $c = $imgTable->addCell($slotW);
                        if (! isset($slice[$i])) {
                            continue;
                        }
                        try {
                            // Width only — keep natural proportions
                            $c->addImage($slice[$i], [
                                'width' => 115,
                                'alignment' => Jc::CENTER,
                            ]);
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            $comments = $sec->commentsList();
            if ($comments !== []) {
                $cell->addText('التعليقات:', ['bold' => true, 'size' => 11, 'rtl' => true], $rtlRight);
                foreach ($comments as $comment) {
                    $fill = HighlightColor::hexFill($comment['color']);
                    $font = ['size' => 10, 'rtl' => true];
                    $hl = HighlightColor::wordHighlight($comment['color']);
                    if ($hl) {
                        $font['fgColor'] = $hl;
                    }
                    if ($fill) {
                        // Put each highlighted comment in its own 1-cell table for solid background
                        $ct = $cell->addTable([
                            'width' => 9600, 'unit' => TblWidth::TWIP,
                            'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 40,
                        ]);
                        $ct->addRow();
                        $ct->addCell(9600, ['bgColor' => $fill])->addText('• '.$comment['text'], $font, $rtlRight);
                    } else {
                        $cell->addText('• '.$comment['text'], $font, $rtlRight);
                    }
                }
            }
            $section->addTextBreak(1);
        }

        if ($sec->has_table) {
            if ($sec->table_title) {
                $section->addText($sec->table_title, [
                    'bold' => true, 'size' => 12, 'color' => 'C00000', 'rtl' => true,
                ], $rtlRight);
            }
            if ($sec->table_type === 'grounding_main') {
                $this->renderGroundingTable($section, $sec, $rtlRight, $rtlCenter);
            } elseif ($sec->table_type === 'mcb_panel') {
                $this->renderMcbTable($section, $sec, $rtlRight, $rtlCenter);
            }
            $section->addTextBreak(1);
        }
    }

    private function renderGroundingTable(Section $section, ReportSection $sec, array $rtlRight, array $rtlCenter): void
    {
        $data = is_array($sec->table_data) ? $sec->table_data : [];
        $cable = $data['grounding_cable'] ?? ['value' => '', 'color' => 'none'];
        $reading = $data['grounding_reading'] ?? ['value' => '', 'color' => 'none'];
        $voltages = is_array($data['voltages'] ?? null) ? $data['voltages'] : [];

        $table = $section->addTable('ReportGrid');

        $table->addRow();
        $table->addCell(10000, ['gridSpan' => 2, 'bgColor' => '464646'])
            ->addText('فحص التأريض', ['bold' => true, 'size' => 11, 'color' => 'FFFFFF', 'rtl' => true], $rtlCenter);

        $table->addRow();
        $this->addCell($table, 5000, 'هل كابل التأريض موجود؟', null, $rtlRight, true);
        $this->addCell($table, 5000, (string) ($cable['value'] ?? ''), $cable['color'] ?? null, $rtlCenter, true);

        $table->addRow();
        $this->addCell($table, 5000, 'قراءة فحص التأريض', null, $rtlRight, true);
        $this->addCell($table, 5000, (string) ($reading['value'] ?? ''), $reading['color'] ?? null, $rtlCenter, true);

        $table->addRow();
        $table->addCell(10000, ['gridSpan' => 2, 'bgColor' => '464646'])
            ->addText('ضغط لوحة التوزيع الرئيسية', ['bold' => true, 'size' => 11, 'color' => 'FFFFFF', 'rtl' => true], $rtlCenter);

        foreach ($voltages as $v) {
            $label = (string) ($v['label'] ?? '');
            $value = (string) ($v['value'] ?? '');
            if ($label === '' && $value === '') {
                continue;
            }
            $table->addRow();
            $this->addCell($table, 5000, $label, null, $rtlCenter, true);
            $this->addCell($table, 5000, $value, $v['color'] ?? null, $rtlCenter);
        }
    }

    private function renderMcbTable(Section $section, ReportSection $sec, array $rtlRight, array $rtlCenter): void
    {
        $data = is_array($sec->table_data) ? $sec->table_data : [];
        $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];
        $col = 1667;
        $ltrCenter = ['alignment' => Jc::CENTER, 'bidi' => false, 'spaceAfter' => 0];

        foreach ($groups as $gi => $group) {
            $table = $section->addTable('McbGrid');

            // Header: ELCB | rating | Have Lable? | Yes  (LTR — matches sample)
            $table->addRow();
            $this->addLtrCell($table, $col, 'ELCB', null, $ltrCenter, true);
            $this->addLtrCell($table, $col, (string) ($group['elcb'] ?? ''), null, $ltrCenter, true);

            $have = $group['have_label'] ?? null;
            $showHave = $gi === 0 && is_array($have) && trim((string) ($have['value'] ?? '')) !== '';
            if ($showHave) {
                $this->addLtrCell($table, $col * 2, 'Have Lable?', $have['color'] ?? 'green', $ltrCenter, true);
                $this->addLtrCell($table, $col * 2, (string) ($have['value'] ?? ''), $have['color'] ?? 'green', $ltrCenter, true);
            } else {
                $table->addCell($col * 2);
                $table->addCell($col * 2);
            }

            // Sub-headers
            $table->addRow();
            foreach (['MCB Rating', 'Cable Size', 'Validation Status', 'MCB Rating', 'Cable Size', 'Validation Status'] as $h) {
                $c = $table->addCell($col, ['bgColor' => 'D9D9D9']);
                $c->addText($h, ['bold' => true, 'size' => 9, 'name' => 'Arial', 'color' => '000000'], $ltrCenter);
            }

            foreach ($group['rows'] ?? [] as $row) {
                $table->addRow();
                // Left circuit then right circuit (LTR)
                foreach ([$row['left'] ?? [], $row['right'] ?? []] as $side) {
                    $this->addLtrCell($table, $col, (string) ($side['mcb']['value'] ?? ''), $side['mcb']['color'] ?? null, $ltrCenter);
                    $this->addLtrCell($table, $col, (string) ($side['cable']['value'] ?? ''), $side['cable']['color'] ?? null, $ltrCenter);
                    $this->addLtrCell($table, $col, (string) ($side['status']['value'] ?? ''), $side['status']['color'] ?? null, $ltrCenter);
                }
            }

            $section->addTextBreak(1);
        }
    }

    private function addLtrCell($table, int $width, string $text, ?string $color, array $par, bool $bold = false): void
    {
        $fill = $color ? HighlightColor::hexFill($color) : null;
        $opts = $fill ? ['bgColor' => $fill] : [];
        $cell = $table->addCell($width, $opts);
        $font = ['size' => 10, 'name' => 'Arial', 'bold' => $bold, 'color' => '000000', 'rtl' => false];
        $cell->addText($text !== '' ? $text : ' ', $font, $par);
    }

    private function addCell($table, int $width, string $text, ?string $color, array $par, bool $bold = false): void
    {
        $fill = $color ? HighlightColor::hexFill($color) : null;
        $opts = $fill ? ['bgColor' => $fill] : [];
        $cell = $table->addCell($width, $opts);
        $font = ['size' => 10, 'name' => 'Arial', 'bold' => $bold, 'color' => '000000', 'rtl' => true];
        $cell->addText($text !== '' ? $text : ' ', $font, $par);
    }
}
