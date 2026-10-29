<?php

namespace Tests\Feature;

use App\Models\ElectricityReport;
use App\Models\User;
use App\Services\ElectricityReportPdfGenerator;
use App\Support\TcpdfReportFonts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectricityReportPdfFontsTest extends TestCase
{
    use RefreshDatabase;

    private function seedReport(): ElectricityReport
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $report = ElectricityReport::query()->create([
            'user_id' => $admin->id,
            'title' => 'تقرير فحص الكهرباء — اختبار الخطوط',
            'client_name' => 'عميل تجريبي',
            'reference_code' => 'E-FONT',
            'inspection_date' => now(),
        ]);

        $report->sections()->create([
            'name' => 'لوحة التوزيع — الطابق الأرضي',
            'description' => 'وصف عربي للتحقق من الترميز Unicode',
            'comments_json' => [
                ['text' => 'تعليق أحمر يحتاج إصلاح', 'color' => 'red'],
                ['text' => 'تعليق سليم', 'color' => 'green'],
            ],
            'has_table' => true,
            'table_type' => 'mcb_panel',
            'table_title' => 'جدول القواطع',
            'table_data' => [
                'groups' => [[
                    'elcb' => 'ELCB 30mA / 63A',
                    'have_label' => ['value' => 'Yes', 'color' => 'green'],
                    'rows' => [[
                        'left' => [
                            'mcb' => ['value' => '10A', 'color' => 'green'],
                            'cable' => ['value' => '1.5', 'color' => 'green'],
                            'status' => ['value' => 'Valid', 'color' => 'green'],
                        ],
                        'right' => [
                            'mcb' => ['value' => '30A', 'color' => 'red'],
                            'cable' => ['value' => '2.5', 'color' => 'red'],
                            'status' => ['value' => 'Invalid', 'color' => 'red'],
                        ],
                    ]],
                ]],
            ],
            'images_json' => [],
            'sort_order' => 1,
        ]);

        return $report->load('sections');
    }

    public function test_pdf_generation_succeeds_when_vendor_arialbd_absent(): void
    {
        $vendorDir = base_path('vendor/tecnickcom/tcpdf/fonts');
        $stash = storage_path('framework/testing/vendor-arialbd-'.uniqid());
        mkdir($stash, 0775, true);
        $moved = [];
        foreach (['arialbd.php', 'arialbd.z', 'arialbd.ctg.z'] as $name) {
            $src = $vendorDir.DIRECTORY_SEPARATOR.$name;
            if (is_file($src)) {
                $dst = $stash.DIRECTORY_SEPARATOR.$name;
                rename($src, $dst);
                $moved[] = [$src, $dst];
            }
        }

        TcpdfReportFonts::clearCache();

        try {
            $this->assertFileDoesNotExist($vendorDir.DIRECTORY_SEPARATOR.'arialbd.php');

            $report = $this->seedReport();
            $binary = app(ElectricityReportPdfGenerator::class)->renderBinary($report);

            $this->assertSame('%PDF', substr($binary, 0, 4));
            $this->assertGreaterThan(2000, strlen($binary));
            // Embedded TrueType Unicode uses Identity-H / Arial metrics
            $this->assertTrue(
                str_contains($binary, 'Identity-H') || str_contains($binary, 'Arial'),
                'Expected embedded Unicode/Arial font markers in PDF'
            );

            // Second generation (cache / reuse path)
            $binary2 = app(ElectricityReportPdfGenerator::class)->renderBinary($report);
            $this->assertSame('%PDF', substr($binary2, 0, 4));
            $this->assertGreaterThan(2000, strlen($binary2));
        } finally {
            foreach ($moved as [$src, $dst]) {
                if (is_file($dst)) {
                    rename($dst, $src);
                }
            }
            @rmdir($stash);
            TcpdfReportFonts::clearCache();
        }
    }

    public function test_http_pdf_endpoint_works_with_storage_fonts(): void
    {
        TcpdfReportFonts::clearCache();
        $report = $this->seedReport();
        $admin = User::query()->where('is_admin', true)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.reports.pdf', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
