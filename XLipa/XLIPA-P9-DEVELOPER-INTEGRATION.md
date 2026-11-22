# XLipa P9 integration guide

This specification describes verified fictional CLI integrations. There is **no operational API base URL**. Routes are installed only by `Tests\Support\MerchantDeveloperHttpHarness`; they are absent from ordinary application routes. Production APIs, external webhooks, live provider calls and financial activation remain disabled. No Selcom endpoint is specified or implied.

## Merchant API v1

Test prefix: `/__isolated-api/v1/merchants/{merchant}`.

| Method and suffix | Required scope | Behavior |
| --- | --- | --- |
| GET `/links` | `links:read` | Latest 100 links and latest 100 attempts for this merchant |
| POST `/links` | `links:write` | Create an existing P6 fixed-amount checkout link |
| GET `/payments/{reference}` | `payments:read` | Read the existing P4 payment state; no provider query or money movement |

Credentials are 256-bit random bearer secrets, shown once. Only a SHA-256 digest is retained. Issue only necessary scopes, keep tokens on your server, and never put credentials in a URL, widget, browser bundle or analytics. Tokens expire within 90 days. Rotation atomically revokes the previous token and issues its replacement; revocation is idempotent. Revocation and expiry are checked within every reserved API mutation. The issuing owner, membership epoch, auth version, approval and submission evidence must still be current. A credential cannot be used for another merchant, even with a valid token. Requests are limited to 120 per credential per minute.

Example HTTP request (placeholders; not a live endpoint):

```http
POST /__isolated-api/v1/merchants/{merchant}/links HTTP/1.1
Authorization: Bearer <test-token-shown-once>
Idempotency-Key: merchant-order-example-0001
Content-Type: application/json
Accept: application/json

{"amount_tzs":12500,"currency":"TZS","description":"Order 1048"}
```

`amount_tzs` is an integer in whole TZS, from 1 through 1,000,000,000,000. Optional `expires_at` is an integer Unix millisecond timestamp. Unknown claims, query parameters and browser Origin headers are rejected. The idempotency key is 16–80 ASCII letters, digits, underscores or hyphens. Identical retries return the same link; changing any payment field with the same key returns 409. The key belongs to the merchant and survives credential rotation. An API link creation does not dispatch a payment.

Successful responses contain `api_version: "v1"` and `data`. Errors contain a generic `error`. 401 means invalid, expired or revoked credentials; 403 means missing scope or authority; 404 means an unavailable resource; 409 means conflicting or stale state; 422 means invalid claims; 429 means the minute quota is exhausted. 503 and uncertain responses require inspection before retrying. Responses are private/no-store, with no CORS grant. Credentials cannot manage credentials or webhook secrets through the Merchant API.

## Checkout tools

An owner creates a fixed-amount P6 link, then associates a QR or Pay Button with its exact link id. Repeating the same association is idempotent. Cross-merchant associations are rejected by both the service and fixture constraints. The public checkout takes its amount, merchant and description from the link; customer requests cannot override them.

Reusable QR codes and buttons permit independent customer sessions. Dynamic QR codes require a link expiry and reserve at most one checkout attempt across all customer sessions, including customers opening the direct P6 URL. The same customer retries its reserved attempt without another provider dispatch. Dynamic and reusable modes cannot be mixed on a link. Disabling or expiring the P6 link prevents new checkout; historical status remains available under P6 rules.

QR images are generated locally as QR Model 2 version 6-L byte-mode SVG with a four-module quiet zone. No remote renderer or payer data is used. Pay Buttons are plain escaped links with `target="_blank" rel="noopener noreferrer"`; they embed no JavaScript, iframe, secret or caller-provided HTML. Test URLs use `http://xlipa.test` under the isolated harness. Operational HTTPS checkout hosting must be separately admitted before any live use. Do not deploy the test snippet as a production integration.

## Signed outgoing webhooks

The durable outbox sweeps existing P4 `payment_confirmations`, never caller-submitted status. Each confirmation produces one immutable `payment.confirmed` event; each enabled endpoint at event creation gets one delivery. Repeating the sweep does not duplicate events or deliveries. New endpoints do not receive historical events automatically. Run the sweep again after an interrupted invocation to recover unswept confirmations.

Event structure:

```json
{"id":"<event-uuid>","type":"payment.confirmed","api_version":"v1","created_at":0,"data":{"id":"<confirmation-id>","payment_reference":"<reference>","amount_tzs":12500,"currency":"TZS"}}
```

Headers: `X-XLipa-Event`, `X-XLipa-Delivery`, `X-XLipa-Timestamp`, `X-XLipa-Signature`. The timestamp is Unix seconds; the signature is `v1=` followed by lowercase hex HMAC-SHA256 over `timestamp + "." + exact_raw_body`, keyed by the endpoint secret. Endpoint secrets are random, shown once, and encrypted using an explicitly supplied synthetic custody key independent of APP_KEY. The API governance view never reveals them. Replace an endpoint and disable its predecessor to rotate the webhook secret; queued predecessor deliveries are not dispatched after disabling it.

Receiver verification example:

```php
$timestamp = $headers['X-XLipa-Timestamp'] ?? '';
$signature = $headers['X-XLipa-Signature'] ?? '';
if (!preg_match('/\A[0-9]{1,12}\z/D', $timestamp)
    || abs(time() - (int) $timestamp) > 300
    || !hash_equals('v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $endpointSecret), $signature)) {
    // Return 401 before changing business state.
    return;
}
$event = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
// Validate schema/type/version; match the signed id to X-XLipa-Event.
// In one database transaction, reserve the event id and raw-body digest,
// then make idempotent business changes. Identical duplicates acknowledge
// without processing; a conflicting body for an existing id rejects.
```

The executable `MerchantWebhooks::accept()` demonstrates durable receipt reservation and business work in one explicit store transaction. If business work throws, receipt reservation rolls back. Deduplicate by signed event id, not delivery id. Confirmation is not settlement: a webhook must never independently credit a wallet or authorize a withdrawal.

Delivery is at least once, maximum three attempts, with 2-second and 4-second retry delays after the first two failures. Each attempt durably claims a 60-second lease before transport. A stale claim can retry after lease expiry; receivers must deduplicate because an acknowledgment may have been lost. Only 2xx completes delivery. Redirects are not followed. Exhausted delivery requires review; no unbounded or manual replay endpoint is exposed.

Destinations require HTTPS, a DNS hostname and default port 443. Userinfo, query strings, fragments, IP-literal URLs and private/reserved/mixed DNS answers are rejected. This initial validator permits public IPv4 only and revalidates before each attempt. The transport receives the validated pinned address. **The only transport is `FictionalWebhookTransport`: it records requests in memory and opens no network connection.** Production transport, TLS validation with pinned DNS, network egress controls, durable custody, worker scheduling, retention and load admission remain separate operational requirements.

## Owner and Chief visibility

Merchant pages show scoped credential metadata, link associations, QR images, button markup, destination state and delivery status. Chief Admin API governance requires the existing Chief authorization gate and reveals redacted metadata. Ordinary Admin/Staff and unrelated merchants cannot access that governance view or another merchant's developer screens.

The Safe Browser Demo uses the existing static exporter/server. Its credentials, QR, Pay Button, webhook and documentation pages are read-only fictional snapshots. All forms are disabled. It does not authenticate API requests, dispatch payments, deliver webhooks or exercise credential mutations. Those behaviors are verified only by isolated functional tests.
