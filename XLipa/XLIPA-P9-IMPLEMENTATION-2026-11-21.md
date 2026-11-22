# XLipa P9 implementation — 21 November 2026

**P9: PASS for fictional, isolated implementation and verification. Operational use remains NO-GO.** All required isolated checks completed successfully. Stopped for owner review. P10 and the final UI redesign have not started.

## Recovered status

Recovered a clean workspace at `8c68aa1` (`P8`). No P9 implementation, tests, report or partial scaffolds existed. The accepted P8 report explicitly ended with “P9 not started.” Consequently no completed P9 work was restarted. Read `backend/AGENTS.md`; `.ai/rules` is absent. The referenced testing-best-practices skill is not installed. Confirmed installed Laravel v13.35.0 using Composer. No dependencies were installed or application dependency declarations changed.

Reused P1–P3 approved merchant journeys, P4 payment authorization/idempotency/store, P5 ledger, P6 links and checkout, P7 wallet, P8 financial lifecycle, Merchant Analytics and the existing static demo/exporter. Did not repeat the architecture review or change branding.

## Implemented functionality

- Merchant API v1 has scoped bearer authentication, owner/evidence/approval/epoch revalidation, merchant-bound credentials, private/no-store responses, strict JSON claims, idempotent P6 link creation, bounded link/attempt reads, P4 payment-state reads and 120 requests per credential per minute. No credentials in query strings, browser Origin access or CORS grants. The API has no credential-management privilege or financial posting endpoint.
- Owner-only credential issue, expiry, atomic rotation and idempotent revocation. Tokens use 256 bits of random secret material; storage retains only a digest and last four characters. The current credential is checked inside the payment engine's exact reserved write transaction. The temporary isolated bearer context restores the complete prior authentication/session state, including failures.
- QR and Pay Button associations reference an actual same-merchant P6 link. Dynamic links require expiry and permit one globally reserved customer attempt; direct P6 checkout URLs obey that limit too. Reusable links retain P6 independent-session behavior and nonce idempotency. Mixed modes on the same link reject. Link disable/expiry applies to checkout resolution. Locally generated version 6-L byte-mode QR SVG has independent reference-vector evidence. Buttons contain a fixed escaped link and noopener/noreferrer, without scripts, iframes or secrets.
- Merchant webhooks sweep P4 confirmed facts into immutable events and unique endpoint deliveries. Secrets are encrypted with an explicitly injected synthetic custody key, independently of APP_KEY. Every attempt revalidates the HTTPS destination and all public IPv4 DNS answers, then passes a pinned address to the fictional transport. No external transport exists. Credentials/userinfo, query/fragment, custom ports, IP-literal destinations, private/reserved/CGNAT/mixed answers and redirects are rejected or never followed.
- Outgoing exact-body HMAC-SHA256 signatures, event and delivery ids, fresh signed timestamps, durable delivery claims and attempt history, three-attempt maximum, bounded backoff and stale-lease recovery. Receiver verification enforces timestamp tolerance and signed event id, reserves a durable event/body digest and performs business changes in the same transaction. Identical duplicates do no work; conflicting signed replays reject; receiver failure rolls back the receipt. Webhooks never post financial journals.
- Merchant API/credential/QR/button/webhook/documentation screens and necessary existing navigation. Authorized Chief API governance shows redacted metadata; ordinary Staff and unrelated merchants are denied. Existing dashboard and analytics details retained.
- [Developer integration guide](XLIPA-P9-DEVELOPER-INTEGRATION.md) documents actual test routes, scopes, amounts, idempotency, errors, signing, verification, replay handling and operational limitations, with HTTP/PHP examples.

All service execution and HTTP registration remain **fictional CLI/test-only**, following P4–P8. P9 routes are registered only by the explicit test harness; no operational application route, provider or middleware binding was added.

## Changed files — 19 source/documentation paths

New application classes: `backend/app/Actions/Payments/{MerchantDeveloperTools,MerchantWebhooks,FictionalWebhookTransport,CheckoutQr}.php`.

Existing infrastructure extensions: `backend/app/Actions/Payments/PaymentEngine.php` delegates a scoped capability guard to its actual writer; `PaymentStore.php` checks that guard before and after reserved work; `PaymentLinkService.php` atomically enforces dynamic checkout consumption when the isolated P9 fixture exists.

Presentation: `backend/resources/views/merchants/developers.blade.php`, existing merchant/admin navigation and a small responsive integration-example addition in `frontend/assets/xlipa-product.css` (mirrored to the existing ignored public asset).

Tests/fixtures: `backend/tests/Feature/FunctionalMerchantDeveloperTest.php`, `backend/tests/Support/{DeveloperStoreFixture,MerchantDeveloperHttpHarness}.php`, existing `SafeBrowserDemoExportTest.php`.

Demo: existing `scripts/demo-server.cjs` and `scripts/Test-Safe-Browser-Demo.cjs`. Documentation: this report and the integration guide. No new demo framework, operational schema or dependency.

## Final verification evidence

| Check | Actual result | Evidence |
| --- | --- | --- |
| Expanded P9 acceptance | 10 tests / 492 assertions / exit 0 | `.recovery/p9-focused-final.log` |
| QR independent reference | Two exact 41×41 matrix SHA-256 matches: checkout URL and maximum 134-byte payload | `.recovery/p9-qr-reference.log`; assertions in P9 suite |
| Final P9 demo export | 1 test / 275 assertions / exit 0; 53 snapshots | `.recovery/p9-demo-final.log` |
| Required formatter | exit 0 | `.recovery/p9-pint.log` |
| P4–P8 / analytics / Chief regression | 223 tests / 10,233 assertions / exit 0 | `.recovery/p9-regression.log` |
| Final browser responsive verification | 55 HTTP pages / 16 assets / 67 navigation targets / 165 viewport checks / exit 0 | `.recovery/p9-browser-final.log` |
| P0 frozen-source and isolation guards | 29 source pins / 3 manifest digests / 4 INT-05 pins / 4 isolation guards: PASS | `.recovery/p9-p0.log` |
| P0 syntax and formatting | 240 PHP syntax checks / 15 non-mutating formatting checks: PASS | Same P0 log |
| P0 functional suite | 33 tests / 964 assertions: PASS | Same P0 log |
| P0 bounded regression | 546 tests / 4,879 assertions: PASS | Same P0 log |
| P0 final preservation | PASS / entire command exit 0 | Same P0 log |
| Final Git whitespace check | exit 0 | `git diff --check` |
| Final running demo ownership | State token matches health; static-visual-only: PASS | Existing `scripts/demo-state.json` and localhost health check |

Counts overlap; do not add them as a unique total. Early failed runs are superseded by the final focused log. Tests found and corrected session merge behavior and a capability check attached to a different wrapper than the actual transaction writer. The regression assertions remain in the suite.

QR reference vectors were independently generated with [Project Nayuki's reference implementation](https://www.nayuki.io/page/qr-code-generator-library), forced byte mode, version 6, Low ECC, mask 0, no ECC boost. The reference was retrieved only into ignored evidence and executed without filesystem/network bindings in a VM. No reference library was added to application dependencies.

## Demo and preservation

The recorded prior demo PID had been recycled into `svchost.exe`. The existing ownership guard refused to stop it. Verified there was no listener/health response on port 18761; archived the stale state to `.recovery/p9-stale-demo-state.json` and copied prior exports to `.recovery/p9-prior-demo-a62e47d10151425aaf813abba78f5cd7`. No unrelated process was stopped. Started the same localhost-only static demo lifecycle on port 18761.

The exporter uses actual fictional credentials, checkout associations and outbox delivery results, asserts retained analytics and financial totals, and checks every exported page for credential/webhook-secret leakage. All snapshot forms are disabled; no API writes, financial writes, real webhook delivery or provider call is available through the demo.

Final demo remains running at **http://127.0.0.1:18761/**. P9 destinations: `/merchant-api`, `/api-credentials`, `/qr-payments`, `/pay-buttons`, `/merchant-webhooks`, `/integration-docs` and `/chief-api-governance`. The 53 fixture snapshots plus directory/feature pages account for 55 HTTP pages in browser verification. Exported example URLs are neutralized by the existing static demo sanitizer; the integration guide documents the actual isolated harness contract.

Final browser evidence: **164 PNGs** plus `qa.json` in `.recovery/ui-ux-evidence-1795291488219`. Verified 1440, 768 and 390-pixel widths; no overflow, broken images, missing styles, enabled form controls or exposed credential tokens. Verified visible in-flow six-link P9 navigation, readable QR sizes, the actual dynamic/reusable QR previews, safe button attributes, real fictional pending/delivered outbox states, keyboard preview navigation, actual wallet navigation, POST 405 and protected-path 404 responses. Existing P8 balance checks still show simulated available TZS 8,000, reserved TZS 2,000 and operational available TZS 0. Visually inspected the final mobile QR content, tablet button/navigation content, desktop webhook history and Chief governance, alongside credential and mobile page layouts. Earlier exports/screenshots were retained.

Visual review caught inherited global public-navigation positioning on P9 mobile pages. Reset only the P9 tool navigation's positioning and added an explicit visibility assertion and content screenshots. No dashboard redesign or branding replacement was performed. Final frontend-only corrections were covered by the accepted final exporter/browser runs; P0 verified the unchanged final PHP sources.

## Security and remaining operational limits

No working SQLite database/sidecar, real environment file, operational APP_KEY, credentials or private keys accessed. No frozen schema, migration, manifest, dependency declaration or existing isolation bootstrap changed. No operational provider binding, live Selcom endpoint, public API activation, external delivery, payment/withdrawal, migration, commit, push or deployment. B3.2-C and SE-1.0 remain NO-GO.

Operational admission remains separate: approved schema provisioning, durable real key custody/rotation, HTTPS API hosting, a reviewed TLS/DNS-pinned HTTP transport with network egress controls, worker scheduling, retention, operational reconciliation/recovery and load testing. IPv6 webhook destinations are conservatively unavailable. The only delivery transport is an in-memory fictional recorder. These limits are deliberate and do not authorize live use.

## Final acceptance and stopping point

| P9 component | Isolated acceptance |
| --- | --- |
| Versioned Merchant API, authentication and permission scopes | PASS |
| Credential generation, rotation, expiry and revocation | PASS |
| Dynamic/reusable QR checkout and same-merchant association | PASS |
| Secure Pay Button checkout association | PASS |
| Signed webhook events and durable delivery tracking | PASS |
| Bounded retries, stale-lease recovery and receiver replay protection | PASS |
| Safe destination validation and fictional-only transport | PASS |
| Developer documentation and integration examples | PASS |
| Authorized redacted Chief API governance | PASS |
| Existing accounting, analytics, UI and P0 preservation | PASS |
| Existing read-only responsive Safe Browser Demo | PASS |

**Remaining isolated implementation blockers: none.** Operational requirements listed above remain unapproved and unimplemented where they require live hosting, real custody, egress transport or operational admission; this PASS does not certify those capabilities. No working-database or provider activation is authorized.

**Final stopping point: P9 complete for isolated owner review, with all changes uncommitted and the owned demo left running. No P10, final platform redesign, commit, push or deployment.**
