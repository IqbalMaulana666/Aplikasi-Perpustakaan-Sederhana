<?php
/**
 * Layani cover buku sebagai WebP (thumbnail / full) dengan cache disk + HTTP.
 * Tidak memuat file HD di tabel — hanya ukuran yang diminta.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$type = isset($_GET['t']) ? $_GET['t'] : 'thumb';

if ($id <= 0 || !in_array($type, ['thumb', 'full'], true)) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$stmt = $conn->prepare('SELECT id, cover FROM books WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book || empty($book['cover'])) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$safeName = basename($book['cover']);
$sourcePath = realpath(BOOK_COVERS_UPLOAD_DIR . $safeName);
$coversRoot = realpath(BOOK_COVERS_UPLOAD_DIR);

if ($sourcePath === false || $coversRoot === false || strpos($sourcePath, $coversRoot) !== 0 || !is_file($sourcePath)) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$maxW = $type === 'thumb' ? 120 : 320;
$maxH = $type === 'thumb' ? 180 : 480;

$cacheDir = BOOK_COVERS_UPLOAD_DIR . 'cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$cacheBase = $cacheDir . '/b' . $id . '_' . $type;

function bookCoverLoadImage(string $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return @imagecreatefromjpeg($path);
    }
    if ($ext === 'png') {
        return @imagecreatefrompng($path);
    }
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($path);
    }
    return false;
}

function bookCoverGenerate(string $sourcePath, string $cacheFile, int $maxW, int $maxH): bool {
    $image = bookCoverLoadImage($sourcePath);
    if (!$image) {
        return false;
    }

    $w = imagesx($image);
    $h = imagesy($image);
    if ($w < 1 || $h < 1) {
        imagedestroy($image);
        return false;
    }

    $ratio = min($maxW / $w, $maxH / $h, 1.0);
    $nw = max(1, (int) round($w * $ratio));
    $nh = max(1, (int) round($h * $ratio));

    $canvas = imagecreatetruecolor($nw, $nh);
    if (!$canvas) {
        imagedestroy($image);
        return false;
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    imagealphablending($canvas, true);

    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($image);

    $quality = 82;
    $ok = false;
    if (function_exists('imagewebp')) {
        $ok = imagewebp($canvas, $cacheFile, $quality);
    } else {
        $ok = imagejpeg($canvas, $cacheFile, 88);
    }
    imagedestroy($canvas);
    return $ok;
}

$sourceMtime = filemtime($sourcePath);
$cacheFile = $cacheBase . (function_exists('imagewebp') ? '.webp' : '.jpg');
$needBuild = !is_file($cacheFile) || filemtime($cacheFile) < $sourceMtime;

if ($needBuild) {
    if (!bookCoverGenerate($sourcePath, $cacheFile, $maxW, $maxH)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }
}

$ext = strtolower(pathinfo($cacheFile, PATHINFO_EXTENSION));
$mime = ($ext === 'webp') ? 'image/webp' : 'image/jpeg';

$etag = '"' . md5_file($cacheFile) . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header('HTTP/1.1 304 Not Modified');
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=31536000, immutable');
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($cacheFile));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

readfile($cacheFile);
