<?php

namespace App\Support;

/**
 * يحوّل الصور (PNG/WebP/…) إلى JPEG بخلفية بيضاء حتى يعمل TCPDF بدون Imagick عند وجود قناة شفافة.
 */
final class RasterImageForTcpdf
{
    /**
     * @return array{0: string, 1: bool} المسار المطلق للاستخدام، وهل يجب حذف الملف بعد الاستخدام
     */
    public static function prepareForEmbedding(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            return [$absolutePath, false];
        }

        $mime = @mime_content_type($absolutePath) ?: '';
        if (in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            return [$absolutePath, false];
        }

        if (! extension_loaded('gd')) {
            return [$absolutePath, false];
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false) {
            return [$absolutePath, false];
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return [$absolutePath, false];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return [$absolutePath, false];
        }

        $dst = imagecreatetruecolor($w, $h);
        if ($dst === false) {
            imagedestroy($src);

            return [$absolutePath, false];
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        $tmp = tempnam(sys_get_temp_dir(), 'tcpdf_raster');
        if ($tmp === false) {
            imagedestroy($dst);

            return [$absolutePath, false];
        }

        $jpg = $tmp.'.jpg';
        @unlink($tmp);
        imagejpeg($dst, $jpg, 90);
        imagedestroy($dst);

        return [$jpg, true];
    }

    /**
     * يستبدل الملف في التخزين بنسخة JPEG عند الحاجة (يفيد قبل TCPDF ولعرض متّسق).
     *
     * @return string|null المسار النسبي الجديد إن تغيّر، أو null
     */
    public static function convertStoredFileToJpegIfNeeded(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $relativePath): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        if (! $disk->exists($relativePath)) {
            return null;
        }

        $full = $disk->path($relativePath);
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return null;
        }

        $binary = @file_get_contents($full);
        if ($binary === false) {
            return null;
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }

        $dst = imagecreatetruecolor($w, $h);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        $dir = dirname($relativePath);
        $base = pathinfo($relativePath, PATHINFO_FILENAME);
        $newRelative = ($dir !== '.' ? $dir.'/' : '').$base.'.jpg';

        $tmp = tempnam(sys_get_temp_dir(), 'upjpg');
        if ($tmp === false) {
            imagedestroy($dst);

            return null;
        }
        $tmpJpg = $tmp.'.jpg';
        @unlink($tmp);
        imagejpeg($dst, $tmpJpg, 92);
        imagedestroy($dst);

        $disk->put($newRelative, file_get_contents($tmpJpg));
        @unlink($tmpJpg);
        if ($newRelative !== $relativePath) {
            $disk->delete($relativePath);
        }

        return $newRelative;
    }
}
