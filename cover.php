<?php
// cover.php
// Serve cover image extracted from an .epub file in uploads/
// Usage: cover.php?f=uploaded-file.epub

$uploadsDir = __DIR__ . '/uploads/';
$cacheDir = $uploadsDir . 'covers/';

if (empty($_GET['f'])) {
    http_response_code(400);
    exit;
}

$file = basename($_GET['f']); // sanitize
$epubPath = $uploadsDir . $file;
if (!file_exists($epubPath) || strtolower(pathinfo($epubPath, PATHINFO_EXTENSION)) !== 'epub') {
    http_response_code(404);
    exit;
}

if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

$cacheKey = md5($file);
$cached = glob($cacheDir . $cacheKey . '.*');
if (!empty($cached)) {
    $cachedFile = $cached[0];
    $ext = strtolower(pathinfo($cachedFile, PATHINFO_EXTENSION));
    $mime = 'image/jpeg';
    if ($ext === 'png') $mime = 'image/png';
    if ($ext === 'gif') $mime = 'image/gif';
    header('Content-Type: ' . $mime);
    readfile($cachedFile);
    exit;
}

$zip = new ZipArchive;
if ($zip->open($epubPath) !== true) {
    http_response_code(500);
    exit;
}

$coverPath = '';

$container = $zip->getFromName('META-INF/container.xml');
$opfPath = null;
if ($container) {
    $dom = new DOMDocument();
    @$dom->loadXML($container);
    $xpath = new DOMXPath($dom);
    $rootfile = $xpath->query('//rootfile')->item(0);
    if ($rootfile) $opfPath = $rootfile->getAttribute('full-path');
}

if ($opfPath && ($opf = $zip->getFromName($opfPath))) {
    $dom = new DOMDocument();
    @$dom->loadXML($opf);
    $xpath = new DOMXPath($dom);

    // EPUB2 meta name="cover"
    $metaCover = $xpath->query('//meta[@name="cover"]')->item(0);
    if ($metaCover) {
        $coverId = $metaCover->getAttribute('content');
        $item = $xpath->query('//manifest/item[@id="' . $coverId . '"]')->item(0);
        if ($item) $coverHref = $item->getAttribute('href');
    }

    // EPUB3 properties cover-image
    if (empty($coverHref)) {
        $item = $xpath->query('//manifest/item[contains(@properties,"cover-image")]')->item(0);
        if ($item) $coverHref = $item->getAttribute('href');
    }

    // fallback by id or name
    if (empty($coverHref)) {
        $item = $xpath->query('//manifest/item[@id="cover"]')->item(0);
        if ($item) $coverHref = $item->getAttribute('href');
    }

    if (empty($coverHref)) {
        $items = $xpath->query('//manifest/item');
        foreach ($items as $it) {
            $href = $it->getAttribute('href');
            if (preg_match('/cover\.(jpe?g|png|gif)$/i', $href)) { $coverHref = $href; break; }
        }
    }

    if (!empty($coverHref)) {
        $opfDir = dirname($opfPath);
        $coverPath = ($opfDir === '.' ? '' : $opfDir . '/') . $coverHref;
        $coverPath = preg_replace('#/+#','/',$coverPath);
    }
}

// fallback: scan all entries for likely cover names
if (empty($coverPath)) {
    for ($i=0;$i<$zip->numFiles;$i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#(?:cover|cover-image|images/cover).*(?:\.jpe?g|\.png|\.gif)$#i', $name)) {
            $coverPath = $name; break;
        }
    }
}

if (!empty($coverPath) && ($img = $zip->getFromName($coverPath)) !== false) {
    $ext = strtolower(pathinfo($coverPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif'])) $ext = 'jpg';
    $cachedFile = $cacheDir . $cacheKey . '.' . $ext;
    file_put_contents($cachedFile, $img);
    $mime = 'image/jpeg';
    if ($ext === 'png') $mime = 'image/png';
    if ($ext === 'gif') $mime = 'image/gif';
    header('Content-Type: ' . $mime);
    echo $img;
    $zip->close();
    exit;
}

$zip->close();
http_response_code(404);
exit;
