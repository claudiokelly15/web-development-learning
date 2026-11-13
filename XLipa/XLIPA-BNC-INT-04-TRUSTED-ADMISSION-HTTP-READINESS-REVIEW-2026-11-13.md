# XLIPA — BNC-INT-04 Trusted Admission and HTTP Readiness Review

13 November 2026 · Africa/Nairobi · Architecture review and implementation planning only

## A. Executive summary

**Operational completion and HTTP activation remain NO-GO.** The repository contains an isolated, internally callable completion command/resolver and a deliberately disabled onboarding preview. Connecting them requires trusted runtime profile provisioning, independent operational admission, private original-request custody, a bounded HTTP boundary and separately approved target/release evidence. None is supplied by a successful schema check or the historical 616-test run.

The smallest safe next implementation is **BNC-INT-05: isolated HTTP input and private-context preparation**, with no routes, writable UI, operational provider binding or working-store access. It can validate the future browser contract using the existing fictional infrastructure while target compatibility and trust ownership are resolved. Approval of this report does not authorize that stage or any activation.

Business-name completion stays optional: registration may create an unnamed merchant; verified members retain dashboard access under existing policies; only an eligible owner choosing profile completion enters the completion flow. Phone-possession proof, identity declarations and later application submission remain separate. This review adds no OTP, NIN or full-KYC prerequisite to dashboard access or completion.

## B. Repository baseline and preservation

HEAD: `70b2d1a4078923df99c9509522f52d4b4481e73c` (`MODIFIED`). Initial index/tracked diff was empty. Initial status contained exactly:

```text
?? XLipa/XLIPA-BNC-INT-03-UI-HTTP-PREPARATION-2026-11-13.md
?? XLipa/backend/tests/Feature/MerchantOnboardingPreparationTest.php
```

These existing INT-03 files were preserved. Read backend AGENTS.md; the repository search found no additional AGENTS.md. Workspace, XLipa and backend `.ai/rules` directories are absent. Read the INT-01 strict-readiness, INT-02 completion and INT-03 UI/HTTP reports, relevant implementation and manifests. This report is the only file created by this stage. No application bootstrap, Artisan, PHPUnit, Pint, migration or dependency command was executed.

Fresh read-only filesystem comparisons matched **29 pins**: six frozen C/SE source/artifact pins, three BNC pins, four INT-01 runtime pins, seven INT-02 runtime/provider pins and nine foundation migration pins. All three manifest digests match their reported values:

| Manifest, relative to backend/database/activation/bnc1 | SHA-256 |
|---|---|
| manifest.json | `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3` |
| integration-manifest.json | `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a` |
| backend-manifest.json | `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990` |

A fresh in-session inventory covers **315 existing Git-selected tracked/untracked nonignored XLipa files**, including the two INT-03 files. It excludes environment/secret/credential/private-key, dependency/storage and database/sidecar paths. This inventory is preservation evidence for this review, not reconstruction of earlier work. Final comparison is recorded at the end.

Historical test evidence, inspected rather than rerun:

| Report | Reported passing tests / assertions | Scope limitation |
|---|---|---|
| INT-01 | 530 / 4,385 | Isolated strict-profile integration |
| INT-02 | 600 / 4,878 | Isolated internal completion/recovery |
| INT-03 | 616 / 5,011; focused 16 / 132 | Isolated DOM/CSS/authorization coverage; no full browser or accessibility certification |

Current source hashes agree with the pinned reports. This does not independently reproduce historical execution or establish production behavior. INT-03 explicitly records its generator's possible dotenv loading; that caveat remains unresolved historical evidence. This review neither ran that generator nor read any dotenv, working database, sidecar or operational credential.

### Findings and discrepancies

- The current dirty state is the expected INT-03 deliverables; INT-03's clean initial state describes an earlier instant, not today's status. No unexpected source modification was found.
- There is **no `config/workflow_schema.php`**. Search of application/config/test sources finds expected-profile injection in tests, and consumption in WorkflowSchemaReadiness; no runtime provisioning was found. No claim is made about hidden deployment infrastructure or config caches, which were not inspected.
- `EnsureAuthenticationReady` independently restricts account access to local/testing and log/array mail. Operational profile selection alone cannot enable production registration, verification or merchant routes. Removing this hold needs its own approved authentication/delivery review; changing APP_ENV to bypass it is unacceptable.
- The public application submission POST already exists but is explicitly denied, including after readiness and writer checks. “DISABLED” means no successful public submission, not absence of its route. Completion has no POST route at all. Chief application routes remain GET-only.
- Frozen manifests are marked isolated-only. The INT-02 manifest pins AppServiceProvider, including its denied binding. Changing that binding invalidates the current release definition. No existing manifest may be silently refreshed to admit a future adapter.
- GET preview checks are weaker than transactional admission by design: they do not authenticate store lineage or scan submission evidence. Their output cannot serve as a write grant.

## C. Current merchant journey

| Step | Existing implementation | Current boundary |
|---|---|---|
| Registration | Auth/RegisterMerchantOwner → LifecycleMutation → CreateMerchant; business name optional | Supported fictional profiles only; missing runtime selection fails relevant mutations closed. Account middleware/delivery holds remain |
| Email verification | Signed/throttled route; foundation/C ordinary verification; SE/BNC binding-version flow via VerifyAccountEmail | Lifecycle/schema gate; current auth/email epochs; production delivery is not approved |
| Dashboard | Verified active session, tenant member resolution, summary redaction/navigation | Name, phone OTP, NIN and full KYC do not become entry prerequisites |
| Business name | Owner-only disabled preview; internal complete and explicit resolve | No HTTP integration; denied admission; no operational expected profile |
| Application preparation | Read-only status/history and unavailable verification interfaces | Name does not submit application or enable money movement |
| Submission | Internal evidence service plus distinct denied public submission guard | Live phone-possession evidence requirements remain separate; no public success authorized |
| Chief review | Authorized read-only list/detail | No decision writes |

Onboarding uses MerchantOnboardingController, merchants/onboarding.blade.php, auth/layout.blade.php and identical frontend/public xlipa-onboarding.css. The existing owner gate checks active unrevoked owner membership, empty exact name, non-suspended/non-closed merchant, current draft/version and null submission/review projections. Native disabled input/button/fieldset, no completion form, escaped text, description/status landmarks and overview/account links remain intact. The CSS uses workspace variables, two columns then one at 850px, and stacked heading/status at 580px. No live rendering was performed in this review.

## D. Trusted runtime profile selection assessment

### Existing mechanism

WorkflowSchemaReadiness accepts only `foundation`, `b32c`, `se1`, `b32c+se1+bnc1` from `workflow_schema.expected_profile`, optionally a Closure result. It has no database-inferred choice or fallback. Missing, invalid or exceptional selection becomes generic 503. It then verifies release/source compatibility, SQLite enforcement, exact catalogue, exact ledger and FK consistency. Each call rechecks; LifecycleMutation captures the selected profile and checks it again after writer reservation. Submission consumers preserve their selected SE/BNC identity across acquisition. VerificationReadiness also requires independent verification environment authorization.

Foundation/C/SE require the first 9/10/11 exact isolated ledger rows; BNC requires all 12 exact rows. Foundation batches are 1 then 2; C is ID10/batch3, SE ID11/batch4, BNC ID12/batch5. Runtime checks reject unknown/partial/temp/attached schema, not just missing required objects. BNC additionally binds object SQL, ownership, FK and index semantics. These are fixture lineage requirements, not known working-store identities.

### Provisioning specification — not implemented

Use an explicitly owner-approved deployment descriptor delivered through the existing deployment/configuration boundary, outside merchant input and outside the workflow database. It must bind environment, immutable release ID, exact profile, manifest digests, authoritative store identity/generation and current restore/cutover fencing epoch. Authenticate descriptor provenance with a separately controlled deployment approval/signature and pinned verification authority or equivalent independently reviewed immutable release custody. Define the mechanism before implementation; a new signing service is not presumed necessary. A string set in config is an input to validation, not proof of provenance.

Supply a single exact selection before lifecycle mutation; never infer from sqlite_master, ledger presence, an env default, a selected artifact path or a request. Validate descriptor schema, approved release/store binding and validity before exposing it to runtime. On absent/stale/revoked descriptor, manifest mismatch, unsupported profile, uncertain store identity or authority outage, deny mutations. An old foundation descriptor against a BNC store must fail rather than downgrade. Tests' foundation fallback and fixture-selection closure must never be copied into runtime provisioning.

Pin original BNC, INT-01, INT-02 and any future release manifest **independently of the checkout and workflow store**. A mutable manifest beside matching mutable source only demonstrates consistency. Existing hard-coded compatibility digests must remain meaningful. A future provider binding or pinned-source change requires an approved successor version and explicit compatibility chain; preserve old frozen artifacts and never regenerate them during admission. Source-only preparation must also pin the previously unpinned HTTP/context/adapter/selection sources in that future release.

Registration runs LifecycleMutation before its transactional creation; missing selection blocks new-account writes. Its existing-user query and password hashing may precede that gate, so do not claim all registration DB access is gated. Email notification/verification chooses versioned behavior using the same selector; unsupported profile blocks that path. Versioned verification advances email-binding epoch. Preserve old links' stale-binding behavior, minimal signup semantics, authentication responses and generic duplicate-registration handling.

During restore/cutover, deny both command and resolver until old writers are fenced, all application workers/connections/cached descriptors are retired, the selected release/profile and new store generation are independently authenticated, and pending external obligations are reconciled. Descriptor identity must not change opportunistically mid-operation. A refreshed timestamp or matching restored rows does not establish authoritative absence. Reopening a connection only proves a connection was reopened. A runtime selector alone does not supply this fencing; the completion admission boundary must bind the actual connection to it.

Required evidence: descriptor custody/revocation and outage tests; independent digest tamper/release-mix tests; missing/unsupported profile tests; writer-boundary changes; stale worker/cache/reconnect tests; owner-approved target catalogue/ledger report; restore/cutover drill proving old-writer exclusion and descriptor-generation binding; registration/email regression evidence; separately reviewed production authentication/mail readiness. No operational selection is approved here.

## E. Business Name Completion admission assessment

| Existing method or dependency | Required operational meaning | Present versus missing |
|---|---|---|
| expectedProfile() | Independently authenticated BNC selection, consistent with workflow descriptor | Interface and exact BNC check exist; trusted provisioner missing |
| authorized(actor, tenant, expectations, resolving) | Explicit scoped command or resolution authorization from trusted server context | Auth identity equality exists; independent grant/custody mechanism missing |
| Context::authority | Fresh active verified user, actor/authenticated email and epochs, exact session auth epoch, active unrevoked same-tenant owner membership, merchant status | Local transaction checks implemented; production session/identity custody and restore behavior unverified |
| milliseconds() | Trusted millisecond source, bounded 0..253402300799999, nonregression, availability policy | Bounds and per-operation comparisons exist; approved provenance/skew/recovery authority missing |
| recordPolicy()/retentionAllowed() | Approved versioned policy and lawful access/retention/hold decision for current and historical records | Policy syntax and calls exist; actual policy, authority, expiry and hold procedures missing |
| authoritative(connection, …) | Bind actual current connection to approved store lineage, generation, single-writer/cutover/replay fences | Hook exists; no independent authoritative-store implementation |
| reconciliationReady() | No unresolved external audit/recovery/reconciliation obligations for this actor/tenant | Hook exists; external hold registry/custody/process missing |
| verification_limit_state checks | Hold on local pending audit event or observed future time | Implemented as additional veto; local absence cannot discharge external obligations |
| Original expected tuple/name | Secure retention of exact original request before dispatch | Internal signatures exist; browser context storage missing |
| resolving=true | Independently approve explicit resolution using current owner authority and immutable historical tuple | Distinct hook and fresh reconnect exist; operational grant/fence/context implementation missing |

Do not reuse VerificationEnvironment as a permissive completion trust source: it is unprovisioned, its clock defaults to now(), and verification capture/delivery authority is a separate concern. Do not treat provider configuration, schema validity, local record absence or platform role as sufficient admission. Default BusinessNameCompletionAdmission remains DeniedBusinessNameCompletionAdmission: null profile, false authorization/fences/retention/reconciliation, exceptions for time/policy.

The command validates exactly nine expectations, reserves one writer via SubmissionTransaction, rechecks admission/current authority/draft, rejects duplicate recorded UUIDs, and checks exact merchant/draft V. It accepts only empty→nonempty at V+1; a fresh same-name no-op returns unchanged without a new record. Record insertion precedes merchant CAS; frozen triggers synchronize draft and witness. Final bundle/admission checks precede one commit attempt. Never add readiness checks within the deliberate deferred record/witness gap. The record is redacted and has no name commitment; it cannot support arbitrary historical payload claims.

SubmissionTransaction forbids nesting, invokes callback once, makes one commit attempt and purges on uncertain commit acknowledgement or rollback failure. BusinessNameCompletionOutcomeUnknown carries a generic message. Resolver independently authorizes, refuses an active transaction, purges/reconnects, sets recursive_triggers for its session and revalidates full readiness/fences under its own writer. It does not call complete or replay a command. It is read/validate only at the business-row level but uses a transaction and zero-row writer reservation; it is not a suitable read-only target-preflight tool.

## F. Future HTTP integration specification

Everything in this section is **DESIGN ONLY**, subject to the owner decisions below. Do not implement routes, forms, middleware or stores as part of INT-04.

### F1. Minimal browser contract and validation

Propose two separately named same-origin POST actions beneath the existing authorized merchant scope: completion and explicit resolution. Exact path names are approved with the future bounded HTTP diff. Completion body: `_token`, `context_ref`, `business_name` only. Resolution body: `_token`, `context_ref` only. Query strings must be empty; actor/tenant/version/role/time/policy/UUID fields are forbidden browser assertions. Generate request UUID server-side once per prepared context. An opaque context reference is permissible in the POST body/hidden input; the private tuple/name is not. This narrows INT-03's general “no hidden context” rule to allow only a non-authoritative opaque reference, not plaintext or signed-readable context.

Suggested limits for owner approval: **4KiB total raw request body**, **3KiB raw percent-encoded name value and 1KiB decoded name value**, **128 ASCII characters maximum opaque reference**, no uploads. The server's decisive name limit stays **200 Unicode code points after only ASCII-space trimming**. These bounds admit the 800-byte UTF-8 maximum of a 200-code-point name even when fully percent-encoded (2,400 bytes), plus bounded trimming overhead; padding beyond the transport cap may be rejected. Test both raw and decoded limits. Reject oversized bodies at ingress and application boundary, including chunked/missing-length requests; do not trust Content-Length alone.

For the smallest HTML flow, accept only `application/x-www-form-urlencoded` with approved UTF-8 semantics. Reject multipart, JSON and alternate encodings until explicitly supported. Parse raw key occurrences before PHP/Laravel parameter collapse; reject duplicate keys, bracket/nested names, encoded aliases, invalid percent escapes/UTF-8, NUL, extra/query parameters and method overrides. Do not accept a request merely because the collapsed input map appears valid. Read raw bytes once under a cap; never record them. All request inputs are untrusted even when generated by XLipa.

Server context holds exactly `application_id`, `attempt_number`, `merchant_profile_version`, `draft_profile_version`, `membership_id`, `authority_version`, `auth_version`, `email_binding_version`, `completion_request_id`, plus authenticated actor/tenant/custody metadata. For any serialized decimal expectations crossing the trusted storage/parser boundary, require ASCII `[1-9][0-9]*`, compare length/lexical value against decimal PHP_INT_MAX **before** casting, then produce positive PHP integers. Reject signs, whitespace, leading zero, exponent, Unicode digits, float, boolean, arrays and zero. Use exact ULID rules from Context::input; UUID is lowercase 8-4-4-4-12 hex with hyphens. Do not strengthen to UUID-v4-only or normalize uppercase silently: the frozen UUID contract does not require version/variant bits. The future server may generate a random UUID, but must preserve its exact bytes thereafter.

Name validation must mirror Context::input: require a UTF-8 string; reject Unicode Cc/Cf/Zl/Zp **before** trimming, trim only U+0020, reject empty result and remaining boundary Unicode Z separators, retain interior allowed spaces/case/bytes without NFC/NFKC/case folding, require at most 200 code points. Tabs/newlines/NUL/invisible formats must not disappear through global middleware. Scope TrimStrings and ConvertEmptyStringsToNull exemptions only to the proposed completion action. Current bootstrap excludes only nin/code from TrimStrings and has no completion-specific empty-string exemption. Preserve signup normalization and unrelated requests. Browser maxlength is advisory and counts differently for some Unicode input; server validation controls acceptance and error text must explain the approved limit without echoing rejected values.

### F2. Owner authority and browser controls

Retain AuthenticationResponseHeaders/auth/EnsureAuthenticationReady/EnsureActiveAuthentication/verified/ResolveMerchantContext; do not remove their current holds to make a test or deployment pass. Resolve an active unrevoked owner freshly, verify current session/auth/email binding, tenant and non-suspended/non-closed merchant before reading private context. Recheck at dispatch via Context::authority inside the transaction. Manager/staff/platform-only users, foreign context references, revoked membership, changed membership ID or invalid epochs fail closed. Resolution requires a currently authorized owner for the recorded membership and independent explicit resolution grant; old record epochs do not override current session authority.

Use Laravel CSRF verification with real middleware-enabled tests; no exemption or GET side effect. Require trusted host/origin, HTTPS, Secure+HttpOnly cookies, appropriate SameSite and narrow domain/path; retain no-store/private, no-referrer, nosniff, frame denial for success and all errors. Source session config defaults HttpOnly true/SameSite lax, but secure/encryption/driver are environment dependent; this review did not inspect actual values. SameSite or CORS alone is not CSRF validation. Test hostile Origin, absent/invalid token, stale session, logout, session rotation and cross-tenant navigation.

Proposed owner-approved quotas: completion 5/minute per actor+merchant and 30/minute per trusted client IP; resolution 10/minute per actor+merchant and 60/minute per trusted IP, plus small outstanding-context caps. Use the existing limiter/cache infrastructure; trusted proxy configuration and limiter outage must fail closed. No raw name/reference in limiter keys. Distributed limits must survive worker changes and cannot be reset to discharge unresolved work. Quotas are proposed, not installed or benchmarked.

Render through escaped Blade; never interpolate into script/HTML/raw output. Allowlisted generic error messages only. Explicitly exclude business_name/context_ref/private expectations from old input, flashes, logs, query/request tracing, analytics, debug dumps and exception chains; disable debug for operational responses. Current dontFlash omits those future fields, and existing generic error rendering scopes do not yet implement completion outcomes. Retain exception-type-only logging, with approved redacted correlation identifiers. No context reference or name in redirect URLs. A validation failure must not unexpectedly repopulate a sensitive rejected value from session storage.

### F3. Private custody and dispatch state machine

Use a bounded encrypted server-side facility within existing infrastructure, not a new product/service. Propose reusing the configured server-side session backend only if it provides durable, restore-fenced, atomic per-context transitions and retention; otherwise that choice is rejected until an existing suitable store is explicitly approved. Plain cookie sessions, process memory, client-signed readable blobs and an evictable cache as the sole uncertainty record are insufficient. Encryption keys, store authority, access and expiry must be reviewed; do not reuse workflow tables by implication or change their schema.

Context states: **prepared → dispatching → completed/unchanged or unresolved**. Before invoking complete, atomically bind the once-only context to actor, tenant, originating session, exact expectation tuple, original UUID and canonical desired name, and durably record dispatching. Invoke the internal command exactly once, without an outer transaction or framework retry wrapper. Crash between dispatching and invocation is treated conservatively as unresolved; it cannot justify a second invocation. Browser refresh/double-click/reload/worker retry must never execute again. On a lost response or crash after dispatch, mark/show unresolved and require explicit resolution. Failure to persist private custody means no command execution.

Suggested context retention for owner decision: 30-minute unsubmitted preparation lifetime and a separately approved resolution window, provisionally 24 hours. Actual unresolved custody must not be silently deleted or reclassified at that window: either maintain a reviewed private recovery hold under an approved retention rule or deny activation until the conflict is resolved. No new write is permitted merely because context expires. An expired context, lost desired bytes, inaccessible key or restored context generation yields unresolved/support review, never absent/success. Define cleanup/access on logout/revocation without destroying required unresolved custody. Newly authenticated resolution may rebind display access after fresh ownership checks; it must retain original operation bytes/UUID and cannot mint a replacement operation.

### F4. Transaction outcomes and explicit resolution

| Internal result | Browser behavior required |
|---|---|
| completed | Acknowledge business name saved only after acknowledged command result and safely recorded terminal custody; refresh scoped status |
| unchanged | Say no change required; do not fabricate completion ID or history |
| 422 / 403 / 404 / 409 / 503 | Generic validation/denial/conflict/unavailable; no automatic retry or latest-version substitution; distinguish unresolved custody before offering any new preparation |
| OutcomeUnknown, timeout, lost response, crash with dispatching context | Unresolved: do not claim saved/rolled back; show explicit check-outcome action, no repeat submit |
| resolver confirmed | Acknowledge existing result under original tuple/current unchanged conditions; retain original event ID |
| resolver absent | State original operation was not found under admitted authoritative conditions; no automatic execution. Any later new operation is a separate owner choice after terminal authoritative resolution and fresh preparation |
| resolver historical | State a prior event exists, not that desired name is currently saved; no payload proof |
| resolver held, denied, unavailable, unknown or expired custody | Continue unresolved/held status; no inference from local absence and no new command |

A proposed 303 redirect may carry only a generic flash state and opaque server-custodied outcome, never name/tuple/reference in its URL. If terminal custody persistence fails after an acknowledged command, retain conservative unresolved behavior for subsequent visits. Resolution is a separate deliberate POST, not auto-run by GET, refresh, polling, background retry or command error catch. It uses the original tuple/name and a fresh authoritative connection; no automatic UUID replacement. Record proven resolution classification atomically in private custody without mutating BNC records or fabricating receipts. Resolver uncertainty has the same conservative handling as command uncertainty.

## G. Working database compatibility and proposed preflight

**No target preflight was performed or authorized by this review.** Do not use `artisan migrate`, migration status, WorkflowSchemaReadiness, resolver or ordinary app bootstrap against the target as a shortcut. Even source inspection shows BNC install() is guarded to testing/:memory: and its artifact down() throws; generic migrate cannot become an operational installer by setting a flag. SE/C frozen contracts also remain in force. Exact fixture IDs/batches and SQL formatting may not match a working lineage; never edit ledger rows or loosen catalogue checks to obtain readiness.

Proposed separate owner-authorized procedure:

1. Obtain written scope identifying exact target/environment, operator, immutable release, read-only access method, time window, permitted metadata/consistency reads and output custody. Authorize any backup, snapshot, file hash or restore separately; they are not included merely by approving read-only metadata access.
2. Verify reviewed source/migration provenance and independent release digests offline. Identify retired synthetic migrations and ensure they cannot enter an operational migration plan. Do not read target dotenv/secrets into a report or tool transcript; the authorized operator supplies access through existing controlled mechanisms.
3. Prove read-only access at the storage/connection layer, runtime isolation, no app boot hooks/session writes, no schema repair/PRAGMA mutations and no migration execution. For SQLite, review WAL/sidecar behavior and coherent snapshot semantics before opening: ordinary read-only-looking tools may create or require sidecars. Stop if safe access cannot be demonstrated; no live file copying or immutable=1 shortcut that ignores active WAL consistency.
4. Capture allowlisted target facts in an approved consistent read: actual migration id/name/batch rows, table/index/trigger catalogue and SQL definitions, columns, FKs/index collations, enforcement/runtime version and approved integrity/FK checks. Limit exposure to metadata and redacted aggregate consistency evidence; no merchant data export. Record missing/unknown objects and lineage differences, including generated ledger gaps/batches, without altering them.
5. Compare offline with exact foundation/C/SE/BNC contracts. Produce a difference report, not an automatic profile choice. If the target is unsupported, stop for a separately reviewed target-bound transition/recovery design. Do not invent migration compatibility from fixture success.
6. Separately authorize and attest backup authenticity, provenance, encryption/access custody and completeness, including SQLite WAL coherence; prove restore in an isolated environment with real target-sensitive handling approved in advance. Verify schema/data invariants, release/profile compatibility, replay fences, old-writer exclusion and pending custody/obligations after restore. Hashing a source artifact here is not hashing or authenticating a database backup.
7. Before any later migration authorization, define one-attempt migration transaction/ledger ownership, commit acknowledgement uncertainty, quarantine, authoritative fresh inspection and recovery without automatic rerun. Demonstrate injected before/after-commit loss and interruption/storage failures on approved isolated clones. BNC downgrade is disabled; blind rollback or restoring an old DB without external fence/custody recovery is not a recovery plan.
8. Return evidence and an explicit target-specific release recommendation for owner sign-off. A passed read-only preflight does not authorize migration, runtime selection, mail enablement or writes. Independently approve the execution/recovery window and successor release only after all gates pass.

## H. Security gap matrix

Classification applies to each stated requirement, not the feature as a whole. **I** = IMPLEMENTED AND VERIFIED IN ISOLATION; **O** = IMPLEMENTED BUT NOT OPERATIONALLY VERIFIED; **D** = DESIGN ONLY; **M** = MISSING; **X** = BLOCKED BY EXTERNAL DEPENDENCY. I uses historical inspected reports, not a fresh test run. No classification means production-ready.

| Requirement / class | Existing implementation | Missing dependency | Risk | Proposed solution | Verification evidence required | Owner decision |
|---|---|---|---|---|---|---|
| Exact schema/profile gate — I | WorkflowSchemaReadiness, exact SQL/catalogue/ledger/enforcement | Operational target evidence | Wrong profile or partial schema | Preserve gate; target-bound reviewed compatibility | INT-01 reports; future target metadata diff | Approve separate preflight |
| Trusted profile provisioner — M | Config read/optional Closure only | Authenticated descriptor/custody | Attacker-selected profile; unavailable lifecycle | D descriptor in section D | Missing/stale/revoked/mixed-release tests | Name deployment authority |
| Independent release authenticity — X | Hash/canonical manifest comparisons | External immutable pins/release approval | Coordinated source+manifest replacement | Independently pin successor chain | Signature/custody and tamper evidence | Approve release owner/mechanism |
| Pinned provider changes — D | Denied binding pinned in INT-02 | Successor manifest compatibility contract | Accidental denial or weakened source gate | Versioned release preparation, preserve old pins | New-source pins and mismatch tests | Approve successor design first |
| Registration/email profile integration — I | Lifecycle writer recheck; email-binding epochs | Operational selection/mail/auth readiness | Account mutation unavailable or stale verification | Preserve contracts and prove production dependencies | Historical auth/workflow tests; future delivery/epoch tests | Keep current holds until separately reviewed |
| Production account access — O | EnsureAuthenticationReady local/testing + log/array guard | Approved operational identity/mail/environment review | Entire production journey blocked or bypassed | Separate bounded authentication readiness review | No env spoofing; signed-link/delivery/session evidence | Explicit scope/approval required |
| Current owner/tenant/session checks — I | Fresh Context::authority and owner policy | Operational session custody proof | Cross-tenant/revoked owner mutation | Reuse checks; verify browser epoch handling | INT-02/03 reports; raw HTTP revocation races | Approve no role override |
| Denied default admission — I | Null/false/throw adapter in provider | None for retaining hold | Permissive accidental activation | Keep denied binding until release gate | Existing denied-before-query tests | Retain denied default |
| Operational command/resolution grants — M | authorized(..., resolving) interface | Trusted grant issuance/binding | Forged operation or replay | Server-held context-specific grants | Wrong actor/tuple/tenant/mode/expiry tests | Choose grant custody |
| Trusted time — X | Bounds/nonregression checks | Approved time provenance/skew/recovery source | Future/stale record or false retention | Independent trusted source and fail-closed policy | Regression/jump/outage/drill evidence | Name time authority |
| Record policy/retention — X | Policy syntax and callback checks | Approved policy/hold/access/expiry authority | Unauthorized retention or lost recovery context | Versioned policy with unresolved-hold procedure | Historical/current policy denial tests | Approve policy and resolution window |
| Actual connection/store lineage — X | authoritative(Connection, …) hook | Authenticated store generation and fencing | Restored stale store reports absence | Bind connection to independent generation | Reconnect/wrong-store/restore drills | Name storage/fence owner |
| Restore/cutover/replay fences — X | Admission veto points | Old-worker exclusion and durable external epoch | Split writers, duplicate operation | Freeze/revoke/retire/re-admit procedure | Multi-process stale-worker and restore evidence | Approve operating procedure |
| Audit/reconciliation holds — O | Local pending/future-time veto, external hook | External obligation custody and reconciliation process | Lost hold after restore | Keep both independent checks | Hold outage/restore/clear authorization tests | Assign reconciler authority |
| Command/CAS/trigger atomicity — I | Record-first, V→V+1, witness/draft triggers, one commit | Production storage fault evidence | Partial result/false success | Preserve transaction/SQL contracts | INT-02 reports plus power-loss/storage drill | Approve target reliability evidence |
| Unknown handling/resolver — I | Generic unknown; explicit fresh resolver | Operational resolution grant and custody | Unsafe automatic retry | Preserve original tuple; explicit resolution | Historical command/resolver tests; browser crash tests | Approve conservative classifications |
| Private durable original context — M | SensitiveParameter signatures only | Approved encrypted atomic custody/lifecycle | Lost desired bytes, duplicate dispatch, leakage | F3 state machine on approved existing store | Crash/restore/expiry/eviction/concurrency tests | Choose store/access/retention |
| Strict raw HTTP parser — D | Internal validator only | Boundary before parameter collapse | Duplicate-key bypass or altered bytes | F1 bounded single media-type contract | Raw encoded body/duplicate/overflow fuzz cases | Approve allowlist and caps |
| Scoped normalization — D | Global nin/code trim exemptions only | Completion-only trim/null exemption | Forbidden whitespace accepted after trimming | Scoped exclusions, preserve validator bytes | Raw controls/Unicode/boundary tests | Preserve frozen normalization |
| CSRF/origin/session transport — O | Laravel web routing, host check, configurable cookies | Completion action and deployed cookie/proxy proof | Forged request/session exposure | F2 without exemptions | Real CSRF middleware/browser tests | Approve origin and HTTPS configuration |
| Completion/resolution limits — D | Generic auth/verification limiters exist | New quotas/trusted proxy/outage behavior | Abuse/resource exhaustion | Existing limiter with F2 scope | Limit isolation/distributed/outage tests | Approve numeric quotas |
| Privacy/error/redaction — O | Escaped Blade, no-store, exception-type logging | Future fields' dontFlash and outcome rendering | Name/context leakage or false success | F2/F4 scoped safe responses | Debug-off errors, old input, log/analytics inspection | Approve safe public messages |
| Read-only accessible UI — I | Disabled card, labels/navigation/CSS breakpoints | Full viewport/keyboard/AT evidence for writable flow | Misleading action or inaccessible status | Preserve preview; test future state messages | INT-03 DOM/CSS; future browser audit | Accept limits; approve later form diff |
| Working schema/ledger compatibility — X | Exact fixture contracts only | Owner-authorized target evidence | Invalid migration or unsupported ledger | Section G preflight then separate transition | Signed metadata diff and restore evidence | Authorize target preflight separately |
| BNC operational migration/recovery — M | Memory-only installer; down disabled | Target-bound installer/recovery release | Commit ambiguity or destructive downgrade | Separately approved transition, never bypass guard | Isolated target clone/fault evidence | Separate execution approval |
| Submission/Chief/payment freeze — I | Public submission abort, GET-only Chief, denied submission binding | Separate later milestone approvals | Scope creep from BNC success | Retain holds | Existing source/routes and historical regressions | No activation in BNC stages |

## I. Shortest safe bounded delivery sequence — proposals only

The stages below are ordered gates. Work may stop after any stage; approval never rolls forward automatically. Proposed filenames are concrete review targets, not files created by this review. No new external service, product or payment feature is proposed.

| Stage | Objective and exact scope | Expected files | Dependencies / tests | Acceptance and explicit NO-GO | Complexity / owner approval |
|---|---|---|---|---|---|
| **BNC-INT-05** | Isolated HTTP input/private-context preparation only: pure raw-body validator, custody interface/state specification and fictional adapter; no routes/controllers/forms/config/provider changes | New `app/Actions/Merchants/BusinessNameCompletionHttpInput.php`, `BusinessNameCompletionRequestContext.php`; new `tests/Support/FakeBusinessNameCompletionRequestContext.php`; new `tests/Feature/BusinessNameCompletionHttpPreparationTest.php`; stage report | Owner agrees F1/F3 schema, limits and conservative lifecycle; existing isolated bootstrap. Test duplicate keys, trim/null preservation, UUID/integer overflow, UTF-8, exact allowlist, once-only dispatch transitions, expiry/redaction and wrong actor/tenant | Existing manifests/pins unchanged; no operational adapter/storage; deny all runtime use; focused + established bounded regressions. NO-GO if preserving original bytes/uncertainty cannot be demonstrated | Medium; explicit approval of bounded stage and proposed files required |
| **BNC-INT-06** | Establish target/release/trust contract and implement selector/admission/context adapters **in isolation only** after owner-authorized read-only preflight; no runtime binding or deployment | Proposed `app/Actions/Merchants/TrustedWorkflowProfile.php`, `TrustedBusinessNameCompletionAdmission.php`, `StoredBusinessNameCompletionRequestContext.php`; matching isolated tests/support; new versioned release-definition artifact/report at an approved path. Existing pinned sources/manifests remain untouched | Approved section G preflight, named custody/time/policy/fence owners, actual existing-store choice, successor manifest strategy; reject unknown target lineage. Contract/concurrency/restore/outage tests using fictional trust adapters; target evidence separate | Adapter provenance and actual connection binding independently reviewed; no facade/env/db-derived trust; no operational configuration. NO-GO for missing trust authority, unsupported ledger or unapproved successor compatibility | High; separate target-access authorization and implementation approval required; split this stage if evidence uncovers migration differences |
| **BNC-INT-07** | Integrate completion and explicit resolution HTTP/UI in isolated environment only, keeping runtime denied and deployment disabled | Proposed `app/Http/Controllers/MerchantBusinessNameCompletionController.php`, scoped input middleware; `routes/web.php`, `bootstrap/app.php`, `resources/views/merchants/onboarding.blade.php`, affected CSS copies only if needed; isolated HTTP/browser tests. Any provider/source-pin change only through approved successor release contract | INT-05/06 accepted; durable atomic custody semantics proven; scoped exemptions/errors/limits; production account hold remains. Test real CSRF, origin/session, full raw bodies, manager/staff/revocation/tenant isolation, multi-tab races, crash-before/after dispatch/ack, resolver result matrix and no automatic retries | Fictional end-to-end owner capability works; unnamed dashboard access preserved; named merchant cannot edit to another name; no operational runtime binding. NO-GO on custody loss/leakage, form enabled without resolution, source mismatch or changed signup behavior | Medium–high; explicit route/form/source-release scope approval required |
| **BNC-INT-08** | Target-bound migration/reliability and operational account-readiness release preparation, no activation yet | Exact execution/recovery artifacts selected only after preflight; successor release definitions; possibly `EnsureAuthenticationReady.php`/auth delivery configuration and their tests **only under separate approved authentication scope**. No automatic edits to frozen installers/SQL/manifests | INT-06 evidence, independently approved operational mail/session readiness, backup/restore/writer-fence evidence; target-clone migration commit uncertainty and storage fault tests; source-pinned regression/browser release candidate | Complete immutable release + rollback/quarantine runbook and safe target transition, owner-reviewed authentication change. NO-GO for unresolved target ledger, restore, old-writer exclusion, migration ambiguity or authentication/delivery dependency | High; explicit separate target transition/auth scope and later execution approval required |
| **BNC-INT-09** | Owner-controlled BNC-only rollout and validation, separately authorized after all gates | Only approved successor deployment descriptor/binding/release artifacts; exact target changes fixed by INT-08, not improvised during rollout | All J criteria, source/release approval and explicit working migration/activation authorization. Verify minimal registration→email→dashboard→optional completion with explicit resolution under approved operational evidence | Smallest end-to-end completion capability only; submission/Chief/payments remain disabled. NO-GO on any missing gate; stop and fence instead of falling back | High operational risk; explicit target-bound execution/activation approval required, not granted here |

INT-06/08 are genuine blockers, not optional polish. If existing operational infrastructure cannot meet custody/time/fencing requirements without architecture expansion, stop for a separate owner decision; do not add infrastructure automatically. Later application submission and Chief decision-write stages are excluded from this sequence.

## J. Acceptance criteria for any future operational GO

1. Owner-approved immutable release/profile/manifest chain, authenticated descriptor, supported target ledger/catalogue and current authoritative store generation; no inferred/fallback profile.
2. Approved runtime selector, independent admission and private context storage; failure/outage/restore denies safely. All current frozen SQL, registration and authority invariants remain intact or have separately approved successor compatibility evidence.
3. Operational authentication/session/mail readiness reviewed without environment spoofing or removal of security prerequisites; original optional-name signup and name-free dashboard access remain.
4. Exact raw HTTP allowlist/limits/normalization, CSRF/origin/cookie/headers, limiter/redaction and integer/UUID contracts pass real boundary tests; no plaintext private context or name in URLs, flashes, logs or analytics.
5. One durable context/UUID and at most one command dispatch; double-click/crash/lost response cannot retry. Explicit resolution retains original bytes, independent current owner authority, fresh store fencing and all conservative classifications. Expiry cannot discharge an unresolved result.
6. Target-bound migration/recovery and authentic backup/restore evidence accepted; uncertain commit or authority means quarantine/hold, not rerun/rollback claim. Old workers and stale store generations cannot authorize command or absence.
7. Fresh focused, bounded regression, raw HTTP/browser/accessibility and production-relevant fault evidence on the exact approved release, with actual commands/counts/exits. Historical tests alone do not meet this criterion.
8. Separate signed owner execution/activation approval names the exact environment, release, target, window, fences and rollback/recovery operator. Public submission, Chief writes and payments remain independently held.

## K. Explicit owner decisions

The report is complete for review; no permission is needed to finish it. Before future work, the owner must decide:

- Approve or revise INT-05 only, including proposed parser/transport caps and private-context schema; do not implicitly approve later stages.
- Name deployment/release authentication and store-lineage/fencing authorities; choose the existing infrastructure and evidence that can satisfy them.
- Approve time source, record/hold/retention/access policy, preparation/resolution windows and unresolved-case custody. Proposed numeric windows/quotas are not active policy.
- Choose a durable encrypted server-side context store with atomic transitions and restore behavior; reject deployment if only ephemeral/client-held custody is available.
- Separately authorize the exact read-only target preflight in section G, including its permitted metadata, isolation, operator and report custody. Backup/copy/hash/restore and writes need their own authorization.
- Approve a successor release/manifest compatibility approach and separately scoped production authentication/mail review before any pinned provider or account-readiness change.
- Require an independent final activation decision after operational evidence; keep later application submission, Chief decisions, identity providers, Selcom and payments out of BNC approval.

## L. Final recommendation and verification record

**GO for owner review of this planning report only. NO-GO for operational profile provisioning, completion HTTP activation or working migration.** Recommend INT-05 as the next smallest bounded implementation; it has not started.

Read-only commands used: `git status --short`, `git log -1 --format='%H %s'`, `rg --files -g AGENTS.md`, targeted `rg` and `Get-Content` on source/config/report files, `Test-Path` on rule/config paths, and `Get-FileHash -Algorithm SHA256` on allowlisted source/artifact/report paths. Source-pin verification parsed the three BNC manifests' base_pins/source_pins and b32c selection-manifest foundation pins, compared each source hash and failed on mismatch. Result: 29/29 matched, exit 0; three manifest digests matched. Some initial guessed source paths were absent; corrected paths were located with rg, with no mutation. No test, syntax or formatting run was necessary for a report-only review; **no tests were rerun**.

Final preservation comparison: **315 pre-existing files unchanged, zero changed, zero missing; this report is the sole added path relative to initial status**, exit 0. The two INT-03 untracked files remain byte-preserved. Index remains empty; `git diff --check` and explicit report trailing-whitespace checks passed, exit 0. All required A–L sections are present. No working database/sidecar was opened, queried, copied, hashed or migrated. No operational secret/credential was read. No PHP/Blade/CSS/JS, route, configuration, adapter, manifest, migration or SQL file was changed. No commit, reset, revert, push, deployment, OTP/NIN/Selcom/payment operation or activation was performed.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public merchant application submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
