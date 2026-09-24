<?php

namespace Tests\Feature;

use App\Models\ElectricityReport;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\ElectricityReportPdfGenerator;
use App\Services\ElectricityReportWordGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElectricityReportCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);
    }

    public function test_guest_cannot_access_admin(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_create_report(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.reports.store'), [
                'title' => 'تقرير فحص منزل تجريبي',
                'client_name' => 'عميل',
                'reference_code' => 'E-100',
                'inspection_date' => '2026-09-21',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('electricity_reports', [
            'title' => 'تقرير فحص منزل تجريبي',
            'reference_code' => 'E-100',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_add_section_with_mcb_table_and_colors(): void
    {
        $admin = $this->admin();
        $report = ElectricityReport::query()->create([
            'user_id' => $admin->id,
            'title' => 'تقرير',
            'reference_code' => 'E-1',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reports.sections.store', $report), [
                'name' => 'لوحة التوزيع',
                'description' => 'وصف القسم',
                'comments' => [
                    ['text' => 'تعليق عادي', 'color' => 'none'],
                    ['text' => 'مشكلة', 'color' => 'red'],
                ],
                'has_table' => '1',
                'table_type' => 'mcb_panel',
                'table_title' => 'جدول 2',
                'mcb_groups' => [[
                    'elcb' => '300mA',
                    'have_label_value' => 'Yes',
                    'have_label_color' => 'green',
                    'rows' => [[
                        'left_mcb' => '10A',
                        'left_mcb_color' => 'green',
                        'left_cable' => '1.5 mm²',
                        'left_cable_color' => 'green',
                        'left_status' => 'Valid',
                        'left_status_color' => 'green',
                        'right_mcb' => '30A',
                        'right_mcb_color' => 'red',
                        'right_cable' => '2.5 mm²',
                        'right_cable_color' => 'red',
                        'right_status' => 'INValid',
                        'right_status_color' => 'red',
                    ]],
                ]],
            ])
            ->assertRedirect(route('admin.reports.show', $report));

        $section = ReportSection::query()->first();
        $this->assertNotNull($section);
        $this->assertTrue($section->has_table);
        $this->assertSame('mcb_panel', $section->table_type);
        $this->assertSame('red', $section->commentsList()[1]['color']);
        $this->assertSame('INValid', $section->table_data['groups'][0]['rows'][0]['right']['status']['value']);
        $this->assertSame('red', $section->table_data['groups'][0]['rows'][0]['right']['status']['color']);
    }

    public function test_admin_can_upload_section_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $report = ElectricityReport::query()->create([
            'user_id' => $admin->id,
            'title' => 'تقرير',
        ]);

        $file = UploadedFile::fake()->image('panel.jpg', 400, 300);

        $this->actingAs($admin)
            ->post(route('admin.reports.sections.store', $report), [
                'name' => 'قسم بصور',
                'images' => [$file],
            ])
            ->assertRedirect();

        $section = ReportSection::query()->first();
        $this->assertCount(1, $section->images_json ?? []);
        Storage::disk('public')->assertExists($section->images_json[0]);
    }

    public function test_pdf_and_word_generate_successfully(): void
    {
        $admin = $this->admin();
        $report = ElectricityReport::query()->create([
            'user_id' => $admin->id,
            'title' => 'تقرير الكهرباء فحص منزل سند 2060',
            'reference_code' => 'E-2060',
            'client_name' => 'عميل',
            'inspection_date' => now(),
        ]);

        $report->sections()->create([
            'name' => 'فحص التأريض',
            'has_table' => true,
            'table_type' => 'grounding_main',
            'table_title' => 'جدول 1',
            'table_data' => [
                'grounding_cable' => ['value' => 'نعم', 'color' => 'green'],
                'grounding_reading' => ['value' => '0.59 Ohm', 'color' => 'green'],
                'voltages' => [
                    ['label' => 'R-Y', 'value' => '408.6 VOLT', 'color' => 'none'],
                ],
            ],
            'comments_json' => [],
            'images_json' => [],
            'sort_order' => 1,
        ]);

        $report->sections()->create([
            'name' => 'لوحة التوزيع',
            'description' => 'وصف',
            'comments_json' => [
                ['text' => 'صيانة دورية', 'color' => 'none'],
                ['text' => 'مشكلة قاطع', 'color' => 'red'],
            ],
            'has_table' => true,
            'table_type' => 'mcb_panel',
            'table_title' => 'جدول 2',
            'table_data' => [
                'groups' => [[
                    'elcb' => '300mA',
                    'have_label' => ['value' => 'Yes', 'color' => 'green'],
                    'rows' => [[
                        'left' => [
                            'mcb' => ['value' => '10A', 'color' => 'green'],
                            'cable' => ['value' => '1.5 mm²', 'color' => 'green'],
                            'status' => ['value' => 'Valid', 'color' => 'green'],
                        ],
                        'right' => [
                            'mcb' => ['value' => '30A', 'color' => 'red'],
                            'cable' => ['value' => '2.5 mm²', 'color' => 'red'],
                            'status' => ['value' => 'INValid', 'color' => 'red'],
                        ],
                    ]],
                ]],
            ],
            'images_json' => [],
            'sort_order' => 2,
        ]);

        $report->load('sections');

        $pdf = app(ElectricityReportPdfGenerator::class)->renderBinary($report);
        $docx = app(ElectricityReportWordGenerator::class)->renderBinary($report);

        $this->assertGreaterThan(1000, strlen($pdf));
        $this->assertSame('%PDF', substr($pdf, 0, 4));
        $this->assertGreaterThan(1000, strlen($docx));
        $this->assertSame('PK', substr($docx, 0, 2));

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $docx);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);
        preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $matches);
        $joined = implode('|', $matches[1]);
        $this->assertStringContainsString('ELCB|300mA|Have Lable?|Yes|MCB Rating|Cable Size|Validation Status', $joined);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('تقرير الكهرباء فحص منزل سند 2060', false);

        $this->actingAs($admin)
            ->get(route('admin.reports.pdf', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('admin.reports.word', $report))
            ->assertOk();
    }

    public function test_section_reorder_and_delete(): void
    {
        $admin = $this->admin();
        $report = ElectricityReport::query()->create([
            'user_id' => $admin->id,
            'title' => 'تقرير',
        ]);
        $a = $report->sections()->create(['name' => 'أ', 'sort_order' => 1]);
        $b = $report->sections()->create(['name' => 'ب', 'sort_order' => 2]);

        $this->actingAs($admin)
            ->patch(route('admin.reports.sections.reorder', [$report, $b]), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);

        $this->actingAs($admin)
            ->delete(route('admin.reports.sections.destroy', [$report, $a]))
            ->assertRedirect();

        $this->assertDatabaseMissing('report_sections', ['id' => $a->id]);
    }
}
