<?php
declare(strict_types=1);
namespace App\Infrastructure\Storage;

use App\Shared\Utils\Env;

final class LocalStorage
{
    private string $basePath;
    private array  $allowedExt;
    private int    $maxSize;

    public function __construct()
    {
        $this->basePath   = rtrim(Env::get('STORAGE_PATH', __DIR__ . '/../../../storage'), '/');
        $this->allowedExt = array_map('trim', explode(',', Env::get('UPLOAD_ALLOWED_EXT', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar')));
        $this->maxSize    = Env::int('UPLOAD_MAX_SIZE', 10485760);
    }

    /**
     * @return array{safe_name:string, relative_path:string, original_name:string, mime:string, size:int, sha256:string}
     */
    public function upload(array $file, string $subDir): array
    {
        // Validate
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
            throw new \RuntimeException('Ошибка загрузки (код ' . ($file['error'] ?? '?') . ').');
        if ((int)($file['size'] ?? 0) > $this->maxSize)
            throw new \RuntimeException('Файл слишком большой (макс. ' . round($this->maxSize / 1048576, 1) . ' МБ).');

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExt, true))
            throw new \RuntimeException("Расширение .{$ext} не разрешено.");
        if (preg_match('/\.php|\.phtml|\.phar|\.htaccess/i', $file['name'] ?? ''))
            throw new \RuntimeException('Файл содержит запрещённое расширение.');

        // Save
        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $relPath  = $subDir . '/' . $safeName;
        $absDir   = $this->basePath . '/' . $subDir;
        if (!is_dir($absDir)) mkdir($absDir, 0750, true);

        $absPath = $absDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $absPath))
            throw new \RuntimeException('Не удалось сохранить файл.');

        @chmod($absPath, 0640);

        return [
            'safe_name'     => $safeName,
            'relative_path' => $relPath,
            'original_name' => basename($file['name']),
            'mime'          => mime_content_type($absPath) ?: 'application/octet-stream',
            'size'          => filesize($absPath) ?: 0,
            'sha256'        => hash_file('sha256', $absPath),
        ];
    }

    public function absolutePath(string $relativePath): string
    {
        $path = $this->basePath . '/' . $relativePath;
        $real = realpath($path);
        $base = realpath($this->basePath);
        if (!$real || !$base || !str_starts_with($real, $base))
            throw new \RuntimeException('Доступ к файлу запрещён.');
        return $real;
    }

    public function delete(string $relativePath): bool
    {
        try { $abs = $this->absolutePath($relativePath); return file_exists($abs) && unlink($abs); }
        catch (\RuntimeException) { return false; }
    }
}
