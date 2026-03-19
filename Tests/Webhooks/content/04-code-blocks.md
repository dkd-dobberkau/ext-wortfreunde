# TYPO3 Webhook-Integration einrichten

So konfigurierst du den Wortfreunde Connector.

## 1. Extension installieren

```bash
composer require wortfreunde/wortfreunde-connector
```

## 2. Webhook Secret setzen

Im TYPO3 Backend unter **System → Wortfreunde → Settings** das Secret eintragen.

## 3. Signatur verifizieren

Die Signatur wird so berechnet:

```php
$message = $timestamp . '.' . $body;
$expected = hash_hmac('sha256', $message, $secret);
return hash_equals($expected, $signature);
```

## 4. Testen

```bash
./send-webhook.sh payloads/ping.json
```

Fertig — Posts werden automatisch synchronisiert.
