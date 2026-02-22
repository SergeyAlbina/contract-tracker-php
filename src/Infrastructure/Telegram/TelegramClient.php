<?php
declare(strict_types=1);
namespace App\Infrastructure\Telegram;

use App\Shared\Utils\Env;

final class TelegramClient
{
    private string $token;
    private string $chatId;

    public function __construct()
    {
        $this->token  = Env::get('TELEGRAM_BOT_TOKEN');
        $this->chatId = Env::get('TELEGRAM_CHAT_ID');
    }

    public function isConfigured(): bool { return $this->token !== '' && $this->chatId !== ''; }

    public function send(string $text, string $parseMode = 'HTML'): bool
    {
        if (!$this->isConfigured()) return false;
        $ch = curl_init('https://api.telegram.org/bot' . $this->token . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['chat_id' => $this->chatId, 'text' => $text, 'parse_mode' => $parseMode]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    /** Отправка уведомления о контракте (используется из ContractsService) */
    public function notifyContract(string $action, array $c): bool
    {
        return $this->send(
            "📋 <b>{$action}: #{$c['number']}</b>\n"
            . "{$c['subject']}\n"
            . "💰 " . number_format((float)($c['total_amount'] ?? 0), 2, '.', ' ') . " ₽"
        );
    }
}
