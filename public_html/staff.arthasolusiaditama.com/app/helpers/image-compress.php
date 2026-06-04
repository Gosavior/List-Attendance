<?php
/**
 * Shared image compression helper.
 * Resizes and compresses uploaded images to reduce file size for faster loading.
 * Does NOT affect existing/old images — only applied on new uploads.
 */

function compressUploadedImage(string $path, int $maxWidth = 1280, int $maxHeight = 1280, int $quality = 75): bool {
    if (!file_exists($path) || !function_exists('imagecreatefromjpeg')) return false;

    $info = @getimagesize($path);
    if (!$info) return false;

    [$origW, $origH, $type] = $info;

    // Skip if already small enough
    if ($origW <= $maxWidth && $origH <= $maxHeight && filesize($path) < 307200) return true;

    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($path); break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($path); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($path); break;
        default: return false;
    }
    if (!$img) return false;

    // Resize if needed
    $w = $origW;
    $h = $origH;
    if ($w > $maxWidth || $h > $maxHeight) {
        $ratio = min($maxWidth / $w, $maxHeight / $h);
        $newW = (int) round($w * $ratio);
        $newH = (int) round($h * $ratio);
        $resized = imagecreatetruecolor($newW, $newH);

        // Preserve transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($img);
        $img = $resized;
    }

    // Save compressed
    switch ($type) {
        case IMAGETYPE_JPEG: $result = imagejpeg($img, $path, $quality); break;
        case IMAGETYPE_PNG:  $result = imagepng($img, $path, 8); break;
        case IMAGETYPE_WEBP: $result = imagewebp($img, $path, $quality); break;
        case IMAGETYPE_GIF:  $result = imagegif($img, $path); break;
        default: $result = imagejpeg($img, $path, $quality);
    }

    imagedestroy($img);
    return $result;
}
