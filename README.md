# Connect Media SMS for PHP

Official PHP client for the [Connect Media](https://connectmedia.co.ke/) SMS API. Send bulk and transactional SMS to Safaricom, Airtel and Telkom numbers in Kenya and to 190+ countries, check your balance, read sent history with delivery status and receive customer replies.

## Get an API key

1. Create a free account at [dashboard.connectmedia.co.ke](https://dashboard.connectmedia.co.ke/).
2. Generate a 64-character API key under **Profile, then API keys**.
3. Top up any amount and register a sender ID (see [sender ID registration in Kenya](https://connectmedia.co.ke/sender-id-registration-kenya/)).

## Install

```bash
composer require connectmedia/sms
```

## Usage

```php
<?php
require 'vendor/autoload.php';

use ConnectMedia\Sms\Client;
use ConnectMedia\Sms\ConnectMediaException;

$client = new Client(getenv('CM_API_KEY'));

try {
    $client->send('0712345678', 'Your order has shipped.', ['sender' => 'YourBrand']);
    $client->send(['0712345678', '0733000111'], 'Fees are due on Friday.', [
        'sender'     => 'YourSchool',
        'scheduleAt' => new DateTimeImmutable('2026-12-01 09:00:00'),
    ]);
    print_r($client->balance());
    print_r($client->history(10));
    print_r($client->inbox(10));
} catch (ConnectMediaException $e) {
    echo $e->apiCode, ' ', $e->apiMessage;
}
```

Requires PHP 7.4+ with the curl and json extensions.

## Response codes

| Action | Success code |
|---|---|
| send | 201 |
| balance | 200 |
| history | 202 |
| inbox | 302 |

Any other code raises an error carrying the API's `code` and `message` (for example `100` invalid API key, `101` insufficient balance, `104` invalid recipients, `105` invalid message, `500` server error). Network failures use code `network`; a non-JSON reply uses `invalid_response`.

Numbers are normalised for you: `0712345678`, `+254 712 345 678` and `254712345678` all become `254712345678`. International numbers are passed through without punctuation.

## Tests

```bash
php tests/run.php
```

## Links

- Website: [connectmedia.co.ke](https://connectmedia.co.ke/)
- API documentation: [connectmedia.co.ke/developers](https://connectmedia.co.ke/developers/)
- Pricing (SMS from KES 1.0, no minimum top-up, credit never expires): [connectmedia.co.ke/pricing](https://connectmedia.co.ke/pricing/)
- Bulk SMS API for Kenya: [connectmedia.co.ke/bulk-sms-api-kenya](https://connectmedia.co.ke/bulk-sms-api-kenya/)
- Support: info@connectmedia.co.ke · +254 707 339 945

## License

MIT
