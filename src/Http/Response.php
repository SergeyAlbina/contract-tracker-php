<?php
declare(strict_types=1);
namespace App\Http;

final class Response
{
    private function __construct(
        private readonly string $body,
        private readonly int    $status,
        private readonly array  $headers,
    ) {}

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $status,
            ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function download(string $body, string $filename, string $mime = 'application/octet-stream'): self
    {
        return new self($body, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($body),
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) header("{$k}: {$v}");
        echo $this->body;
    }
}
