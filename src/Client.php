<?php

declare(strict_types=1);

namespace ConnectMedia\Sms;

/**
 * Connect Media SMS API client.
 *
 *     $client = new \ConnectMedia\Sms\Client('YOUR_64_CHARACTER_API_KEY');
 *     $client->send('0712345678', 'Your order has shipped.', ['sender' => 'YourBrand']);
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://dashboard.connectmedia.co.ke/api.php';

    /** Application code returned in the JSON envelope when each action succeeds. */
    private const SUCCESS_CODES = ['send' => '201', 'balance' => '200', 'history' => '202', 'inbox' => '302'];

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    /** @var callable(string, array<string,string>, string, int): string */
    private $transport;

    /**
     * @param callable|null $transport fn(string $url, array $headers, string $body, int $timeout): string
     */
    public function __construct(string $apiKey, string $baseUrl = self::DEFAULT_BASE_URL, int $timeout = 30, ?callable $transport = null)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        $this->apiKey    = $apiKey;
        $this->baseUrl   = $baseUrl;
        $this->timeout   = $timeout;
        $this->transport = $transport ?? [self::class, 'curlTransport'];
    }

    /**
     * Return a number in 2547XXXXXXXX form: strips spaces, dashes, brackets and a
     * leading '+', and converts Kenyan local numbers (07XXXXXXXX / 01XXXXXXXX).
     */
    public static function normalizeMsisdn(string $number): string
    {
        $digits = (string) preg_replace('/[\s\-()+]/', '', $number);
        return preg_match('/^0[17]\d{8}$/', $digits) ? '254' . substr($digits, 1) : $digits;
    }

    /**
     * Send an SMS to one number, a comma-separated string or an array of numbers.
     *
     * @param string|string[] $to
     * @param array{sender?: string, scheduleAt?: \DateTimeInterface} $options
     * @return array<string, mixed>
     */
    public function send($to, string $message, array $options = []): array
    {
        $numbers = [];
        foreach ((array) $to as $part) {
            foreach (explode(',', (string) $part) as $n) {
                if (trim($n) !== '') {
                    $numbers[] = self::normalizeMsisdn($n);
                }
            }
        }
        if (!$numbers) {
            throw new \InvalidArgumentException('at least one recipient is required');
        }
        if ($message === '') {
            throw new \InvalidArgumentException('message is required');
        }
        $payload = ['to' => implode(',', $numbers), 'message' => $message];
        if (!empty($options['sender'])) {
            if (strlen($options['sender']) > 11) {
                throw new \InvalidArgumentException('sender must be 11 characters or fewer');
            }
            $payload['sender'] = $options['sender'];
        }
        if (isset($options['scheduleAt'])) {
            $payload['schedule']          = 1;
            $payload['schedule_datetime'] = $options['scheduleAt']->format('Y-m-d H:i:s');
        }
        return $this->call('send', $payload);
    }

    /** @return array<string, mixed> */
    public function balance(): array
    {
        return $this->call('balance', []);
    }

    /**
     * List sent messages with delivery status. Dates are YYYY-MM-DD.
     *
     * @return array<string, mixed>
     */
    public function history(int $limit = 50, int $offset = 0, ?string $startDate = null, ?string $endDate = null): array
    {
        $payload = ['limit' => $limit, 'offset' => $offset];
        if ($startDate !== null) {
            $payload['start_date'] = $startDate;
        }
        if ($endDate !== null) {
            $payload['end_date'] = $endDate;
        }
        return $this->call('history', $payload);
    }

    /**
     * List replies received from customers (two-way SMS).
     *
     * @return array<string, mixed>
     */
    public function inbox(int $limit = 50): array
    {
        return $this->call('inbox', ['limit' => $limit]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function call(string $action, array $payload): array
    {
        $body    = (string) json_encode(['action' => $action] + $payload);
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'connectmedia-sms-php/10.0.0',
        ];
        $raw  = ($this->transport)($this->baseUrl, $headers, $body, $this->timeout);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new ConnectMediaException('invalid_response', 'The API did not return JSON');
        }
        $code = (string) ($data['code'] ?? '');
        if ($code !== self::SUCCESS_CODES[$action]) {
            throw new ConnectMediaException($code !== '' ? $code : 'unknown', (string) ($data['message'] ?? 'Unknown error'), $data);
        }
        return $data;
    }

    /** @param array<string, string> $headers */
    private static function curlTransport(string $url, array $headers, string $body, int $timeout): string
    {
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = "$k: $v";
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ConnectMediaException('network', $error);
        }
        curl_close($ch);
        return (string) $raw;
    }
}
