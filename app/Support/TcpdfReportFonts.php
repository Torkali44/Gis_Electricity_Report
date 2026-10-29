<?php

namespace App\Support;

use RuntimeException;
use TCPDF;
use TCPDF_FONTS;

/**
 * Registers TrueType Unicode fonts for TCPDF outside vendor/, and binds them
 * onto PDF instances so SetFont() can resolve definition + companion files.
 *
 * Root cause addressed: addTTFfont() may write to storage/app/tcpdf-fonts, but
 * TCPDF SetFont()/AddFont() only search K_PATH_FONTS (vendor fonts) unless an
 * absolute font-definition path is passed to AddFont().
 */
final class TcpdfReportFonts
{
    /** @var array{family: string, definition: string}|null */
    private static ?array $cached = null;

    public static function outputDirectory(): string
    {
        return storage_path('app/tcpdf-fonts');
    }

    /**
     * Ensure Arial Bold (Unicode) is generated under storage and return
     * family name + absolute path to the TCPDF .php definition.
     *
     * @return array{family: string, definition: string}
     */
    public static function ensureArialBold(): array
    {
        if (self::$cached !== null && self::definitionIsValid(self::$cached['definition'], self::$cached['family'])) {
            return self::$cached;
        }

        $sourceTtf = base_path('resources/fonts/arialbd.ttf');
        if (! is_file($sourceTtf) || ! is_readable($sourceTtf)) {
            throw new RuntimeException(
                'Required TCPDF source font is missing: resources/fonts/arialbd.ttf'
            );
        }

        $dir = self::outputDirectory();
        self::ensureWritableDirectory($dir);

        $lockPath = $dir.DIRECTORY_SEPARATOR.'.generate.lock';
        $lock = @fopen($lockPath, 'c+');
        if ($lock === false) {
            throw new RuntimeException('Unable to lock TCPDF font output directory for writing.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire TCPDF font generation lock.');
            }

            $family = self::convertOrReuse($sourceTtf, $dir);
            $definition = $dir.DIRECTORY_SEPARATOR.$family.'.php';

            if (! self::definitionIsValid($definition, $family)) {
                throw new RuntimeException(
                    'TCPDF font definition or companion files are missing/invalid after registration.'
                );
            }

            self::$cached = [
                'family' => $family,
                'definition' => $definition,
            ];

            return self::$cached;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Bind a previously ensured font onto a TCPDF instance via absolute path.
     * Must be called before the first SetFont() for that family.
     */
    public static function bindToPdf(TCPDF $pdf, ?array $font = null): void
    {
        $font ??= self::ensureArialBold();
        $pdf->AddFont($font['family'], '', $font['definition']);
    }

    /** @internal testing helper */
    public static function clearCache(): void
    {
        self::$cached = null;
    }

    private static function convertOrReuse(string $sourceTtf, string $dir): string
    {
        $outpath = $dir.DIRECTORY_SEPARATOR;

        // Preferred family name derived from arialbd.ttf by TCPDF is "arialbd".
        $expectedFamily = 'arialbd';
        $expectedDef = $outpath.$expectedFamily.'.php';

        if (self::definitionIsValid($expectedDef, $expectedFamily)) {
            return $expectedFamily;
        }

        // Incomplete previous generation — remove stale pieces before rebuild.
        foreach ([$expectedFamily.'.php', $expectedFamily.'.z', $expectedFamily.'.ctg.z'] as $piece) {
            $path = $outpath.$piece;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $name = TCPDF_FONTS::addTTFfont(
            $sourceTtf,
            'TrueTypeUnicode',
            '',
            32,
            $outpath
        );

        if (! is_string($name) || $name === '') {
            throw new RuntimeException('TCPDF_FONTS::addTTFfont failed to register arialbd.ttf.');
        }

        return $name;
    }

    private static function definitionIsValid(string $definitionPhp, string $family): bool
    {
        if ($definitionPhp === '' || ! is_file($definitionPhp) || ! is_readable($definitionPhp)) {
            return false;
        }

        $dir = dirname($definitionPhp).DIRECTORY_SEPARATOR;
        $z = $dir.$family.'.z';
        $ctg = $dir.$family.'.ctg.z';

        if (! is_file($z) || ! is_readable($z) || filesize($z) < 100) {
            return false;
        }
        if (! is_file($ctg) || ! is_readable($ctg) || filesize($ctg) < 10) {
            return false;
        }

        // Sanity-check definition declares the companion file names.
        $contents = (string) file_get_contents($definitionPhp);
        if ($contents === '' || ! str_contains($contents, "\$file='{$family}.z'") || ! str_contains($contents, "\$ctg='{$family}.ctg.z'")) {
            return false;
        }

        return true;
    }

    private static function ensureWritableDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new RuntimeException('Unable to create TCPDF font output directory.');
            }
        }

        if (! is_writable($dir)) {
            throw new RuntimeException('TCPDF font output directory is not writable.');
        }
    }
}
