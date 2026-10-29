<?php

namespace Tests\Unit;

use App\Support\TcpdfReportFonts;
use Illuminate\Support\Facades\File;
use RuntimeException;
use TCPDF;
use Tests\TestCase;

class TcpdfReportFontsTest extends TestCase
{
    private string $tempFontsDir;

    protected function setUp(): void
    {
        parent::setUp();
        TcpdfReportFonts::clearCache();
        $this->tempFontsDir = storage_path('framework/testing/tcpdf-fonts-'.uniqid());
        File::ensureDirectoryExists($this->tempFontsDir);
    }

    protected function tearDown(): void
    {
        TcpdfReportFonts::clearCache();
        if (is_dir($this->tempFontsDir)) {
            File::deleteDirectory($this->tempFontsDir);
        }
        parent::tearDown();
    }

    public function test_ensure_arial_bold_registers_definition_and_companions(): void
    {
        $this->assertFileExists(base_path('resources/fonts/arialbd.ttf'));

        $font = TcpdfReportFonts::ensureArialBold();

        $this->assertSame('arialbd', $font['family']);
        $this->assertFileExists($font['definition']);
        $this->assertFileExists(dirname($font['definition']).DIRECTORY_SEPARATOR.'arialbd.z');
        $this->assertFileExists(dirname($font['definition']).DIRECTORY_SEPARATOR.'arialbd.ctg.z');

        $php = (string) file_get_contents($font['definition']);
        $this->assertStringContainsString("\$file='arialbd.z'", $php);
        $this->assertStringContainsString("\$ctg='arialbd.ctg.z'", $php);
        $this->assertStringContainsString('TrueTypeUnicode', $php);
    }

    public function test_repeated_ensure_is_idempotent_and_cached(): void
    {
        $first = TcpdfReportFonts::ensureArialBold();
        $mtime = filemtime($first['definition']);
        clearstatcache();

        usleep(50000);
        $second = TcpdfReportFonts::ensureArialBold();

        $this->assertSame($first['family'], $second['family']);
        $this->assertSame($first['definition'], $second['definition']);
        clearstatcache();
        $this->assertSame($mtime, filemtime($second['definition']));
    }

    public function test_bind_to_pdf_allows_setfont_without_vendor_arialbd(): void
    {
        $vendorDir = base_path('vendor/tecnickcom/tcpdf/fonts');
        $moved = [];
        foreach (['arialbd.php', 'arialbd.z', 'arialbd.ctg.z'] as $name) {
            $src = $vendorDir.DIRECTORY_SEPARATOR.$name;
            if (is_file($src)) {
                $dst = $this->tempFontsDir.DIRECTORY_SEPARATOR.$name;
                rename($src, $dst);
                $moved[$src] = $dst;
            }
        }

        try {
            $this->assertFileDoesNotExist($vendorDir.DIRECTORY_SEPARATOR.'arialbd.php');

            $font = TcpdfReportFonts::ensureArialBold();
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            TcpdfReportFonts::bindToPdf($pdf, $font);
            $pdf->SetFont($font['family'], '', 12);
            $pdf->AddPage();
            $pdf->setRTL(true);
            $pdf->Write(0, 'تقرير فحص الكهرباء');
            $binary = $pdf->Output('', 'S');

            $this->assertSame('%PDF', substr($binary, 0, 4));
            $this->assertGreaterThan(1000, strlen($binary));
            $this->assertTrue(str_contains($binary, 'Arial') || str_contains($binary, 'Identity-H'));
        } finally {
            foreach ($moved as $src => $dst) {
                if (is_file($dst)) {
                    rename($dst, $src);
                }
            }
        }
    }

    public function test_missing_source_font_throws_clear_exception(): void
    {
        $original = base_path('resources/fonts/arialbd.ttf');
        $backup = $this->tempFontsDir.DIRECTORY_SEPARATOR.'arialbd.ttf.bak';
        $this->assertFileExists($original);
        rename($original, $backup);
        TcpdfReportFonts::clearCache();

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('resources/fonts/arialbd.ttf');
            TcpdfReportFonts::ensureArialBold();
        } finally {
            rename($backup, $original);
            TcpdfReportFonts::clearCache();
        }
    }

    public function test_unwritable_output_directory_throws(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX-only unwritable directory simulation.');
        }

        $dir = TcpdfReportFonts::outputDirectory();
        $this->assertTrue(is_dir($dir));
        $prev = fileperms($dir);
        chmod($dir, 0555);
        TcpdfReportFonts::clearCache();

        // Force rebuild path by removing definition while dir is read-only — may still
        // succeed if cache files exist and are valid. Delete definition first under writable state.
        chmod($dir, 0775);
        @unlink($dir.DIRECTORY_SEPARATOR.'arialbd.php');
        chmod($dir, 0555);
        TcpdfReportFonts::clearCache();

        try {
            $this->expectException(RuntimeException::class);
            TcpdfReportFonts::ensureArialBold();
        } finally {
            chmod($dir, $prev ?: 0775);
            TcpdfReportFonts::clearCache();
        }
    }
}
