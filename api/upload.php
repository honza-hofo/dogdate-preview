<?php
/**
 * DogDate API - Shared photo upload handler
 */

require_once __DIR__ . '/config.php';

/**
 * Process and save an uploaded image file
 * @param array $file - $_FILES element
 * @param int $maxWidth - Max width in pixels
 * @param int $maxHeight - Max height in pixels
 * @param int $quality - JPEG quality (1-100)
 * @return string - URL path to saved file
 */
function processUpload(array $file, int $maxWidth = 300, int $maxHeight = 300, int $quality = 60): string {
    // Validate upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Soubor je příliš velký (server limit).',
            UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
            UPLOAD_ERR_PARTIAL => 'Soubor byl nahrán jen částečně.',
            UPLOAD_ERR_NO_FILE => 'Žádný soubor nebyl nahrán.',
            UPLOAD_ERR_NO_TMP_DIR => 'Chybí dočasný adresář.',
            UPLOAD_ERR_CANT_WRITE => 'Nepodařilo se zapsat soubor.',
        ];
        $msg = $errors[$file['error']] ?? 'Neznámá chyba při nahrávání.';
        jsonError($msg, 400);
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        jsonError('Soubor je příliš velký. Maximum je 10 MB.', 400);
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        jsonError('Povolené formáty: JPEG, PNG, GIF, WebP.', 400);
    }

    // Verify it's actually an image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        jsonError('Nahraný soubor není platný obrázek.', 400);
    }

    // Load image based on type
    switch ($mimeType) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file['tmp_name']);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            jsonError('Nepodporovaný formát obrázku.', 400);
    }

    if (!$source) {
        jsonError('Nepodařilo se zpracovat obrázek.', 500);
    }

    // Get original dimensions
    $origWidth = imagesx($source);
    $origHeight = imagesy($source);

    // Calculate new dimensions (maintain aspect ratio, fit within max)
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    if ($ratio >= 1) {
        // Image is already smaller than max
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    } else {
        $newWidth = (int)round($origWidth * $ratio);
        $newHeight = (int)round($origHeight * $ratio);
    }

    // Create resized image
    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG
    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Generate unique filename
    $filename = uniqid('img_', true) . '.jpg';
    $filepath = UPLOAD_DIR . $filename;

    // Save as JPEG
    $success = imagejpeg($resized, $filepath, $quality);

    // Free memory
    imagedestroy($source);
    imagedestroy($resized);

    if (!$success) {
        jsonError('Nepodařilo se uložit obrázek.', 500);
    }

    return UPLOAD_URL . $filename;
}

// If called directly as an endpoint
if (basename($_SERVER['SCRIPT_FILENAME']) === 'upload.php') {
    $userId = requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda není povolena.', 405);
    }

    if (empty($_FILES['photo'])) {
        jsonError('Žádný soubor nebyl nahrán.', 400);
    }

    $url = processUpload($_FILES['photo']);
    jsonResponse(['success' => true, 'url' => $url]);
}
