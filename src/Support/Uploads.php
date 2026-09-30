<?php
declare(strict_types=1);

namespace HireCraft\Support;

/**
 * Small helper for handling image uploads (job photos, portfolio photos).
 * Validates type/size, writes files under public/uploads/{subdir}/, and
 * returns the paths to store in the DB (relative to the uploads dir, so
 * they can be served back via asset('uploads/...')).
 */
final class Uploads
{
    private const ALLOWED_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const ALLOWED_DOC_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    private const MAX_BYTES = 5 * 1024 * 1024; // 5MB per image
    private const MAX_FILES = 6;

    /**
     * Saves every valid file from a multi-file <input type="file" name="x[]">
     * upload into public/uploads/{subdir}/, skipping empty file slots.
     *
     * @return array{paths: list<string>, errors: list<string>} Paths are
     *   relative to the uploads dir (e.g. "jobs/12/ab3f...jpg").
     */
    public static function saveMany(?array $filesField, string $subdir): array
    {
        $paths = [];
        $errors = [];
        if (!$filesField || !isset($filesField['tmp_name']) || !is_array($filesField['tmp_name'])) {
            return ['paths' => $paths, 'errors' => $errors];
        }

        $count = count($filesField['tmp_name']);
        if ($count > self::MAX_FILES) {
            $errors[] = 'You can attach at most ' . self::MAX_FILES . ' photos.';
            $count = self::MAX_FILES;
        }

        $dir = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . trim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $errors[] = 'Could not prepare the upload folder. Please try again.';
            return ['paths' => $paths, 'errors' => $errors];
        }

        for ($i = 0; $i < $count; $i++) {
            $error = $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue; // an empty slot from the multi-file input — not a failure
            }
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the photos failed to upload (please try again).';
                continue;
            }
            $tmpName = $filesField['tmp_name'][$i];
            $size = (int)($filesField['size'][$i] ?? 0);
            if ($size <= 0 || $size > self::MAX_BYTES) {
                $errors[] = ($filesField['name'][$i] ?? 'A photo') . ' is too large (max 5MB).';
                continue;
            }
            $mime = @mime_content_type($tmpName) ?: '';
            $ext = self::ALLOWED_MIME[$mime] ?? null;
            if ($ext === null) {
                $errors[] = ($filesField['name'][$i] ?? 'A file') . ' is not a supported image type (use JPG, PNG or WebP).';
                continue;
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!is_uploaded_file($tmpName) || !move_uploaded_file($tmpName, $dir . '/' . $filename)) {
                $errors[] = 'Could not save ' . ($filesField['name'][$i] ?? 'a photo') . '.';
                continue;
            }
            $paths[] = trim($subdir, '/') . '/' . $filename;
        }

        return ['paths' => $paths, 'errors' => $errors];
    }

    /**
     * Saves a single profile-picture upload (customer/artisan/admin avatar) —
     * images only, no PDFs — into public/uploads/{subdir}/. The caller is
     * responsible for deleting any previous avatar file once the new one is
     * safely saved and the DB row referencing it is updated.
     *
     * @return array{path: ?string, error: ?string}
     */
    public static function saveImage(?array $fileField, string $subdir): array
    {
        if (!$fileField || !isset($fileField['tmp_name']) || ($fileField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => 'Please choose a photo.'];
        }
        if ($fileField['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'The photo failed to upload (please try again).'];
        }
        $size = (int)($fileField['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['path' => null, 'error' => 'The photo is too large (max 5MB).'];
        }
        $mime = @mime_content_type($fileField['tmp_name']) ?: '';
        $ext = self::ALLOWED_MIME[$mime] ?? null;
        if ($ext === null) {
            return ['path' => null, 'error' => 'Unsupported file type (use JPG, PNG or WebP).'];
        }
        $dir = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . trim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['path' => null, 'error' => 'Could not prepare the upload folder. Please try again.'];
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!is_uploaded_file($fileField['tmp_name']) || !move_uploaded_file($fileField['tmp_name'], $dir . '/' . $filename)) {
            return ['path' => null, 'error' => 'Could not save the photo.'];
        }
        return ['path' => trim($subdir, '/') . '/' . $filename, 'error' => null];
    }

    /**
     * Saves a single document upload (identity/skill/reference verification
     * evidence) — images or PDFs — into public/uploads/{subdir}/.
     *
     * @return array{path: ?string, error: ?string}
     */
    public static function saveOne(?array $fileField, string $subdir): array
    {
        if (!$fileField || !isset($fileField['tmp_name']) || ($fileField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => 'Please attach a document.'];
        }
        if ($fileField['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'The file failed to upload (please try again).'];
        }
        $size = (int)($fileField['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['path' => null, 'error' => 'The file is too large (max 5MB).'];
        }
        $mime = @mime_content_type($fileField['tmp_name']) ?: '';
        $ext = self::ALLOWED_DOC_MIME[$mime] ?? null;
        if ($ext === null) {
            return ['path' => null, 'error' => 'Unsupported file type (use JPG, PNG, WebP or PDF).'];
        }
        $dir = rtrim(HC_CONFIG['app']['upload_dir'], '/') . '/' . trim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['path' => null, 'error' => 'Could not prepare the upload folder. Please try again.'];
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!is_uploaded_file($fileField['tmp_name']) || !move_uploaded_file($fileField['tmp_name'], $dir . '/' . $filename)) {
            return ['path' => null, 'error' => 'Could not save the file.'];
        }
        return ['path' => trim($subdir, '/') . '/' . $filename, 'error' => null];
    }
}
