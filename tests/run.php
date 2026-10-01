<?php
// Dependency-free test runner: php tests/run.php

declare(strict_types=1);

require __DIR__ . '/../src/ConnectMediaException.php';
require __DIR__ . '/../src/Client.php';

use ConnectMedia\Sms\Client;
use ConnectMedia\Sms\ConnectMediaException;

$failures = 0;
$count    = 0;
function check(string $name, callable $fn): void
{
    global $failures, $count;
    $count++;
    try {
        $fn();
        echo "ok   $name\n";
    } catch (\Throwable $e) {
        $failures++;
        echo "FAIL $name: " . $e->getMessage() . "\n";
    }
}
function eq($expected, $actual): void
{
    if ($expected !== $actual) {
        throw new \Exception('expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function throws(string $class, callable $fn): \Throwable
{
    try {
        $fn();
    } catch (\Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new \Exception("expected $class, got " . get_class($e));
    }
    throw new \Exception("expected $class, nothing thrown");
}
function fake($response, array &$calls): callable
{
    return function (string $url, array $headers, string $body, int $timeout) use ($response, &$calls): string {
        $calls[] = ['url' => $url, 'headers' => $headers, 'body' => json_decode($body, true)];
        if ($response instanceof \Throwable) {
            throw $response;
        }
        return is_array($response) ? (string) json_encode($response) : $response;
    };
}

check('normalizeMsisdn', function () {
    eq('254712345678', Client::normalizeMsisdn('0712345678'));
    eq('254110123456', Client::normalizeMsisdn('0110 123 456'));
    eq('254712345678', Client::normalizeMsisdn('+254 712-345-678'));
    eq('256712345678', Client::normalizeMsisdn('256712345678'));
});

check('send builds the request', function () {
    $calls = [];
    $res   = (new Client(str_repeat('k', 64), Client::DEFAULT_BASE_URL, 30, fake(['code' => '201', 'message' => 'Queued'], $calls)))
        ->send(['0712345678', '+254733000111'], 'Hi', ['sender' => 'Brand']);
    eq('https://app.connectmedia.co.ke/api.php', $calls[0]['url']);
    eq('Bearer ' . str_repeat('k', 64), $calls[0]['headers']['Authorization']);
    eq(['action' => 'send', 'to' => '254712345678,254733000111', 'message' => 'Hi', 'sender' => 'Brand'], $calls[0]['body']);
    eq('201', $res['code']);
});

check('send splits comma strings and schedules', function () {
    $calls = [];
    (new Client('k', Client::DEFAULT_BASE_URL, 30, fake(['code' => '201', 'message' => 'ok'], $calls)))
        ->send('0712345678, 0722000000', 'Hi', ['scheduleAt' => new \DateTimeImmutable('2026-12-01 09:00:00')]);
    eq('254712345678,254722000000', $calls[0]['body']['to']);
    eq(1, $calls[0]['body']['schedule']);
    eq('2026-12-01 09:00:00', $calls[0]['body']['schedule_datetime']);
});

check('validation', function () {
    $calls = [];
    $c     = new Client('k', Client::DEFAULT_BASE_URL, 30, fake([], $calls));
    throws(\InvalidArgumentException::class, fn () => $c->send([], 'Hi'));
    throws(\InvalidArgumentException::class, fn () => $c->send('0712345678', ''));
    throws(\InvalidArgumentException::class, fn () => $c->send('0712345678', 'Hi', ['sender' => 'ThisIsTooLong']));
    throws(\InvalidArgumentException::class, fn () => new Client(''));
});

check('each action checks its own success code', function () {
    foreach (['balance' => '200', 'history' => '202', 'inbox' => '302'] as $method => $code) {
        $calls = [];
        eq($code, (new Client('k', Client::DEFAULT_BASE_URL, 30, fake(['code' => $code, 'message' => 'ok'], $calls)))->$method()['code']);
        eq($method, $calls[0]['body']['action']);
    }
    $calls = [];
    throws(ConnectMediaException::class, fn () => (new Client('k', Client::DEFAULT_BASE_URL, 30, fake(['code' => '200', 'message' => '?'], $calls)))->send('0712345678', 'Hi'));
});

check('api and parse errors', function () {
    $calls = [];
    $e = throws(ConnectMediaException::class, fn () => (new Client('k', Client::DEFAULT_BASE_URL, 30, fake(['code' => '100', 'message' => 'Invalid or missing API key'], $calls)))->balance());
    eq('100', $e->apiCode);
    $e = throws(ConnectMediaException::class, fn () => (new Client('k', Client::DEFAULT_BASE_URL, 30, fake('<html>', $calls)))->balance());
    eq('invalid_response', $e->apiCode);
});

check('history optional dates', function () {
    $calls = [];
    (new Client('k', Client::DEFAULT_BASE_URL, 30, fake(['code' => '202', 'message' => 'ok'], $calls)))->history(5, 0, '2026-09-01');
    eq(['action' => 'history', 'limit' => 5, 'offset' => 0, 'start_date' => '2026-09-01'], $calls[0]['body']);
});

echo "\n$count tests, $failures failures\n";
exit($failures ? 1 : 0);
