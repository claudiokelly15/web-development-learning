# XLIPA — BNC-INT-06B Part 1: Authority Custody and Target Preflight Preparation

13 November 2026 · Africa/Nairobi · Read-only source inspection and documentation

## A. Executive summary

**The existing contracts can be connected without another interface milestone, but the operating facts and custody owners are not established.** This review identifies source-supported infrastructure candidates, an authorization packet for Part 2 and bounded implementation gates. It neither qualifies a target nor enables an adapter.

The repository has Laravel configuration mechanisms for database, sessions, cache, encryption, mail and workers; source-pinned workflow checks; internal completion/resolution; and isolated trust/context contracts. It does not supply an independently approved runtime descriptor, deployment/revocation registry, authentic store lineage, trusted time/policy authority or durable original-request custody. The source also deliberately blocks account routes outside local/testing and log/array mail. Those holds cannot be removed by setting configuration to convenient values.

The shortest path is: owner names environment/target/authorities and approves Part 2 metadata scope → gather target-specific evidence → approve a concrete successor release and existing-backend custody design → implement and test the existing contracts together in isolation → integrate HTTP/UI in isolation → separately approve migration, recovery, authentication/email, release and operational rollout. No Part 2 work or later implementation began here.

## B. Current repository baseline

HEAD: `70b2d1a4078923df99c9509522f52d4b4481e73c` (`MODIFIED`). Initial index and tracked diffs were empty. Sixteen existing untracked paths were present: INT-03/04/05/06A reports, the four INT-05 PHP files, MerchantOnboardingPreparationTest and the seven INT-06A PHP files. All were preserved.

Read backend AGENTS.md and INT-01 through INT-06A reports; reviewed relevant application, configuration, migration, manifest, script, installed framework-handler and fixture source. No additional AGENTS.md was found. Workspace/XLipa/backend `.ai/rules` directories are absent. Only this report is created. No PHP/Blade/CSS/JavaScript, route, provider, configuration, SQL, migration, manifest or test file is changed.

Fresh pre-documentation source inventory: **329** Git-selected tracked/untracked nonignored XLipa source/asset/test/config/report files. Environment/secret/credential/private-key, dependencies/storage and database/sidecar paths were excluded from that hash inventory. Read-only pin checks matched **29 manifest/foundation source/artifact pins**, plus all **four INT-05 report source hashes**. Hashing code/artifacts is distinct from hashing any database; no database or backup was hashed.

Historical evidence, not rerun:

| Stage | Reported combined tests / assertions | Meaning |
|---|---|---|
| INT-01 | 530 / 4,385 | Isolated strict schema selection and consumers |
| INT-02 | 600 / 4,878 | Isolated command and explicit resolver |
| INT-03 | 616 / 5,011 | Read-only UI, DOM/CSS and authorization; browser/AT limitations remain |
| INT-04 | No tests rerun | Architecture review |
| INT-05 | 702 / 5,284; focused 86 / 273 | Pure input and fictional private context |
| INT-06A | 770 / 6,848; focused 68 / 1,564 | Isolated trust contracts and fictional authorities |

No tests, Pint, PHP execution, Artisan, migration, application bootstrap or target command ran in Part 1. The INT-03 generator's possible dotenv-loading caveat remains historical; this review did not load any dotenv. Earlier preservation counts refer to their own stage baselines, not today's larger inventory.

## C. Existing operational infrastructure assessment

“Available” below means represented in source, not installed, configured, reachable or operationally approved. Actual effective configuration, deployment environment, topology, credentials, runtime paths, database contents and service settings were not inspected.

| Mechanism and source | Current source-level evidence | Assessment / next evidence |
|---|---|---|
| Deployment/release | XLipa README and Start-XLipa.ps1 describe a local Herd workflow; startup script also synchronizes frontend files. Backend README is framework boilerplate. No project-specific CI/CD/release approval pipeline was found in the searched Git paths | Local development documentation, not proof of the current host mapping or any deployment. Do not execute startup/sync during evidence gathering |
| Dependency/build scripts | composer.json includes setup/create-project hooks which generate environment/key material and run migrations; package/build mechanisms exist | Tools are present, but these scripts are unsafe shortcuts for this review/preflight; none was executed |
| Auth | config/auth.php declares a session web guard and Eloquent user provider; app middleware/policies implement current auth-version and merchant boundaries | Implemented in source and historically tested; effective settings and operational session custody remain unknown |
| Database | config/database.php defaults to SQLite and defines other engine templates plus Redis connection templates | Templates do not prove actual engine/services. Current frozen workflow/readiness/transaction code is SQLite-specific |
| Session | config/session.php defaults to database driver, 120-minute lifetime, encryption false, JSON serialization, HttpOnly true, SameSite lax; Secure/domain/connection are configurable | Defaults only, not actual settings. No driver is qualified as durable private custody |
| Cache/locking | config/cache.php includes database/cache_locks, file, array, Redis, Memcached and other adapters; database is the source default. Failover may fall back from database to array | Backend availability/configuration unknown. Ephemeral fallback must never carry uncertainty custody or restore authority |
| Encryption | config/app.php exposes Laravel cipher/key/previous-key mechanisms; SubmissionEvidenceCipher implements a separate protected submission-evidence contract | Crypto primitives and another domain's contract exist. No completion-context encryption adapter or approved key custody exists; do not repurpose submission keys/contracts implicitly |
| Email | config/mail.php defaults to log and includes SMTP/other transport templates and failover | Not delivery evidence. Local logs can contain verification/reset links; none was read. Sending requires a separate controlled review |
| Queues/workers | config/queue.php offers database/Redis/etc. with retry/failover settings; source default is database | No evidence of running workers/topology. Queue retry behavior cannot be used to replay completion; maintenance mode alone does not fence every worker |
| Runtime trust | TrustedWorkflowProfile and three authority interfaces; existing denied admission binding | Implemented isolated boundary; operational descriptor, measurement, revocation and authority adapters are missing |

The top-level README contains dated local-state claims and older roadmap checkpoints. They are not current configuration or database evidence and are not reproduced as operating facts here. Generic config database/session defaults can differ from that historical README without proving an actual deployment discrepancy. No unexpected source modification was found.

In the requested implementation terms: strict checks/denied defaults are **already implemented**; Laravel backend/encryption/mail mechanisms are **available in source**, while whether they are actually configured is unknown; authority custody and recovery procedures are **design only**; operational provisioners/context adapters are **missing**; approval, store, policy and time ownership are **externally dependent**. “Available but not configured” is not asserted about a deployment without evidence.

## D. Release authority and provenance

### What already exists

WorkflowSchemaReadiness verifies supported profile selection, canonical release manifests, pinned source compatibility, exact catalogue/ledger and enforcement. TrustedWorkflowProfile distinguishes requested/approved profile, release identity, exact manifest/source maps, environment, store identity/generation/fence, worker, approval provenance, validity and revocation. It validates supplied observed artifacts; it does **not** measure files or establish authenticity. TrustedReleaseApproval requires an independent approval decision. The only new authority implementations are fictional fixtures; AppServiceProvider retains denied completion/submission admission.

### Smallest proposed custody arrangement

Use the owner's existing deployment/artifact/access-control facilities if Part 2 establishes them. An approval record held outside the writable checkout and workflow database can be sufficient **only if** a distinct authorized principal controls approval/revocation and the runtime can authenticate a current decision. No new signing service is proposed. If existing facilities cannot maintain that separation and outage/revocation behavior, stop and present the specific gap before selecting any new infrastructure.

The concrete approval record must bind: immutable release ID; exact approved profile; current BNC/INT-01/INT-02 and reviewed successor manifest chain; exact approved source/build identity; environment/origin; authoritative store ID; generation and fence epoch; admitted worker/deployment identity; approval provenance and validity; revocation decision/version. The schema vocabulary stays exactly foundation, b32c, se1, b32c+se1+bnc1. No selection from tables, migration counts, APP_ENV defaults or merchant input.

Assign three roles even if existing infrastructure hosts them: release builder produces immutable artifact/measurement; approver authorizes exact artifact and scope; deploy operator installs only that approved artifact. The running application must not rewrite approvals, advance its own fence epoch or repair mismatched manifests. Separation must survive restore and application-account compromise; merely storing another JSON file under the same writable account does not meet it. An existing offline approval/signature process or independently protected artifact registry may implement the boundary; specific mechanism, verifier and credentials require owner review, not inference.

Independent measurement must cover the artifact actually loaded, not a different checkout: source bytes, manifest chain, relevant dependency/build identity and workers' active release. Define how mutable files, symlink switches, cached configuration/routes and long-lived workers are retired/rechecked. A Git commit alone omits untracked work and does not prove deployed bytes. This workspace currently has uncommitted implementation; do not label HEAD as its complete release identity.

Approval expiry/revocation or verifier outage must fail closed for new mutation and explicit resolution admission. Preserve unresolved context during an outage; never grant “absent” from a stale approval. Before activating any successor provider binding, approve its new release definition: the current INT-02 manifest pins AppServiceProvider and cannot be regenerated to disguise a changed binding. Frozen C/SE/BNC artifacts remain unchanged unless separately authorized through their actual successor-contract process.

**Status:** interfaces and comparisons implemented/isolated; deployment mechanism available only as local source tooling; independent approval/measurement/revocation custody missing or externally dependent. No production-ready claim.

## E. Database authority and store lineage

### Known from source

- Database configuration contains engine templates, but WorkflowSchemaReadiness, SubmissionTransaction and frozen C/SE/BNC SQL require SQLite semantics. A MySQL/MariaDB/PostgreSQL/SQL Server template is not workflow support or a migration plan. Any non-SQLite target is a separate compatibility decision.
- Supported exact isolated ledger prefixes are foundation 9 rows, C 10, SE 11, BNC 12. C/SE/BNC fixture IDs/batches are fixed in manifests. The actual target ledger is unknown; never rewrite IDs/batches or relax readiness to make it match.
- Current readiness rejects unknown/partial catalogue, attached schema and temporary objects and verifies connection constraints. These checks are capability/integrity gates, not store authenticity.
- BNC's installer is guarded to testing memory SQLite; generic migration and downgrade are not an operational activation procedure. Its downgrade throws. No approved working-target installer or migration recovery procedure is established.
- SubmissionTransaction makes one callback/commit attempt and purges on uncertain commit or rollback failure. The resolver purges/reconnects and validates under its own writer reservation. It is not a read-only preflight tool and a fresh PDO is not lineage proof.

### Facts Part 2 must establish

An authorized operator must identify engine/runtime, physical/logical target, deployment topology, filesystem/storage semantics, readers/writers and worker identities through approved metadata—not path guessing or a local row that claims to be authoritative. A logical store ID must be controlled by the storage/deployment owner and bound to the actual connection; filename, SQLite schema match, hostname string, DSN or reopening alone is insufficient. Actual deployment support remains undecided.

Maintain generation/fence/revocation authority outside the restore domain that can roll back the workflow DB. Restore/cutover must first exclude old writers at a layer they cannot bypass; revoke/drain app and queue workers, retire stale connections and descriptors, reconcile pending context/audit/replay obligations, then independently approve new lineage and admission. An application flag or maintenance page cannot alone exclude an old process still holding write access. The exact storage/process control is target-specific and must be proven.

Replay fencing must retain original actor/tenant/UUID and dispatch evidence across restart, response loss and restore. A restored old workflow database must not erase independent uncertainty/dispatch holds or become authoritative merely because its schema is valid. Likewise restoring the custody store and approval registry to a jointly stale state is unsafe. Define which independent evidence survives and who can reconcile it. Clock, generation and approval renewal cannot be synthesized from restored rows.

Backup existence is not backup authenticity or restore readiness. Separately authorize provenance/custody/completeness evidence and an isolated restore drill. Database file copying, database hashing, WAL handling and restoring an earlier snapshot have separate risks/permissions. Migration commit uncertainty requires quarantine and approved authoritative inspection; never automatic rerun, ledger repair or blind downgrade.

**Status:** source-level SQLite and exact fixture contracts are known; actual store, engine, ledger, topology, backup validity, connection authority and recovery remain unverified and require owner-authorized target evidence.

If Part 2 confirms a compatible SQLite target, the smallest deployment candidate to qualify is one authoritative store on a controlled host with proven durable local storage and fencing for every app/queue writer. Do not infer support for shared network filesystems, independently writable replicas or cross-host failover from Laravel's generic connection templates. This is a candidate for evidence gathering, not an approved operational model; a different actual topology requires its own compatibility/reliability decision.

## F. Private-context storage options

The existing interface already specifies original actor/tenant/session/generation, application/attempt/membership/epochs/profile versions, UUID, canonical name bytes, preparation/dispatch times and state. The fake models prepared→dispatching→completed/unchanged/unresolved, once-only dispatch and explicit resolution, but has no durability or encryption. No operational implementation exists.

### Candidate assessment

| Candidate within existing mechanisms | What source offers | Why not accepted now | Smallest permitted next step |
|---|---|---|---|
| Standard database/file session payload | Server-side handler, optional session encryption, storage across requests | Session ID rotation/logout/destruction/GC and whole-payload writes do not implement durable per-context CAS or unresolved holds | Keep session for authentication/reference only; do not place the sole original request/uncertainty record there |
| Standard cache/database cache | TTL values and locks; existing cache/cache_locks schema | Eviction, expiry, clear/failover and lock lease expiry are not an atomic durable dispatch decision. Database backing alone changes none of those semantics | Reuse for abuse limits/optional performance only after review; never authoritative custody/fence or replay absence |
| Redis or another configurable backend | Connection/cache/session/queue templates | Deployment, persistence, failover loss, eviction, atomic operations, confidentiality and restore generations unknown | Qualify an already operated service only if Part 2 supplies evidence; do not introduce one by default |
| Dedicated custody table/store using an already approved durable DB engine | Laravel DB/transaction/encryption infrastructure can support an adapter | Table/schema, unique/CAS rules, encryption, retention, restore coordination and actual durability are not implemented | Preferred minimal fallback for separate design approval if no existing qualified store suffices; no new service or table is authorized now |
| Cookie payload, process array, ordinary TTL file/cache or queue job | Configurable transport/temporary storage | Client custody or loss/expiry/replay behavior cannot preserve the required private history | Reject as sole custody, even if encrypted or signed |

Installed framework source confirms these limits: DatabaseSessionHandler base64-encodes its payload, updates last_activity and deletes rows on destroy/GC; base64 is not encryption. CacheBasedSessionHandler writes with TTL and forgets on destroy. Higher-level session encryption is configurable and its source default is false; actual encryption/settings were not read. Encryption would not fix deletion, atomicity or recovery by itself. No driver was qualified in Part 1.

### Concrete acceptance conditions for a durable adapter

The selected existing backend must prove authenticated encryption and key custody/rotation/recovery; bounded private payload; exact actor/tenant/original UUID and expectation/name retention; atomic prepared→dispatching compare-and-swap with one durable dispatch winner; original context committed **before** invoking complete; restart/read-after-crash durability; stable unresolved status after lost acknowledgement; independent explicit resolution; monotone time/fence checks; policy-aware cleanup with an unresolved-hold veto. A lock timeout is not permission to dispatch again.

Use a trusted internal dispatch capability, never a browser-supplied grant. On a crash between durable dispatch and command invocation, retain unresolved rather than reissue. On a crash after commit/response loss, use the original context and existing resolver classifications. Terminal custody write failure must not create a false browser success or retry. Names/UUIDs/session/context material must not enter URLs, logs, analytics, old-input flashes or unencrypted snapshots. Opaque context references may be session-held, but must not confer authority by themselves.

The current workflow catalogue excludes new tables/attached schemas. Therefore do not slip a custody table into the workflow store or reuse frozen BNC/submission rows: either separately address an approved custody store using the existing DB infrastructure, with independently coordinated restore/fence behavior, or obtain an explicit successor catalogue/schema release approval. Do not attach that store to the strict workflow SQLite connection. The placement decision must minimize infrastructure while respecting the frozen catalogue and restore domain.

Session rotation/logout must revoke old access while preserving original custody. The current fake correctly denies a changed session/generation; no post-rotation handoff exists. A future current-owner recovery flow needs fresh authentication and separately authorized resolution, immutable original operation binding and atomic supersession of access capabilities. It must not pretend the original session is still authenticated, replace UUID/name or let a platform administrator bypass merchant ownership. If the owner no longer has the recorded current membership authority, keep the case held; no implicit authority-transfer mechanism is approved. Any demonstrated interface change needs separate approval; no interface edit is needed or authorized in Part 1.

Expiry of payload access and unresolved retention may conflict. Resolve that policy before rollout: prevent new dispatch, preserve a lawful recovery hold and bounded private access procedure, and never interpret deletion, key loss, cache miss or expired context as rollback/absence. Cleanup may remove only policy-approved terminal/preparation data with proven no unresolved obligation; restoration must not resurrect old dispatch permissions. Specify cleanup operator, evidence, failure handling and non-sensitive audit of authorized deletion.

## G. Time, policy and authorization dependencies

| Authority | Existing local evidence | Missing independently authenticated authority / recovery behavior |
|---|---|---|
| Trusted time | Time-policy interface, bounds/nonregression tests, fictional clock; other existing lifecycle/notification code uses now() | Named source/provenance, skew/jump policy, persistent nonregression across restart/restore, outage veto and recovery approval. INT-06A's 1,000ms test jump bound is not production policy |
| Record/retention policy | Version syntax and current/historical callback checks | Approved policy versions, legal/operational access rules, revocation, retention/hold conflict decision, key/custody lifecycle and historical access authority; no new policy is active |
| Current owner | Context::authority reloads active verified user, exact tenant/member/owner and epochs; policies reject platform-role override | Authentic session/identity source, fresh grant issuance and revocation at dispatch/resolution, operational restore consistency |
| Completion grant | Existing admission interface; fictional mode-specific exact tuple grant | Trusted issuer scoped to actor/tenant/application/membership/epochs/versions/UUID and original custody; availability, revocation and replay proof |
| Resolution grant | Separate resolving mode and fresh resolver; current authority checked separately from historical tuple | Deliberate current-owner authorization, original-context retrieval, authoritative reconnect, retention permission and post-session-rotation recovery policy |
| External reconciliation | Local verification_limit_state pending-audit/future-time veto and external readiness hook | Durable external obligation/hold custody, authorized clearing/reconciliation, survival across target restore and outage. Local absence never clears an external hold |

The operator/owner must name these authorities and where approvals persist. Configuration, source constants, normal application clock, schema validity, matching DB rows and passing fixtures provide no independent operational authorization. An unavailable/revoked authority blocks admission; it does not erase context or authorize a fresh request. Returning authority after an outage requires revalidation of descriptor/release/store/clock/current owner and unresolved obligations, not simply restarting a worker.

No new service is required by this report. Existing independently controlled deployment/storage/identity procedures may provide authority if evidence supports them; otherwise identify the exact missing property and seek a narrow owner decision.

## H. Authentication and email readiness

Source path for the minimal journey is registration → signed email verification → authenticated verified-member dashboard → optional owner-selected business-name completion. Important boundaries:

- EnsureAuthenticationReady checks account schema and permits only local/testing with log/array mail. This deliberately prevents a production/real-mail account journey. Current environment is unknown; do not infer it from source defaults or historical local documentation and do not bypass the restriction by changing APP_ENV or mail configuration.
- RegisterMerchantOwner preserves optional businessName and uses LifecycleMutation for creation. Workflow expected-profile provisioning remains absent in source; missing selection denies relevant lifecycle mutations. Password hashing and an existing-user query may precede that gate, so it is not a blanket guarantee that registration never reads before schema admission.
- Login regenerates the session and stores auth_version; logout invalidates the session and rotates CSRF token. EnsureActiveAuthentication reloads the user and rejects inactive/stale-session epochs. Session Secure/encryption/domain/store are configurable and not operationally verified here.
- User notification selection uses LifecycleMutation to choose versioned email links. VersionedVerifyEmail signs an email-binding epoch, and VerifyAccountEmail performs fresh auth/email/epoch checks and advances the binding version under the lifecycle gate. Foundation/C ordinary verification and SE/BNC versioned behavior must retain compatibility.
- Verified email plus active authenticated account/session and eligible merchant membership is sufficient for dashboard access under current policies; it is not access for every verified user. No phone OTP, NIN, full KYC or mandatory business name is added. Completion is a separate current-owner action; a platform administrator gains no automatic owner power.
- The public application submission POST exists but remains explicitly denied. Its future phone-possession/identity requirements are separate from dashboard entry/completion. Chief review is read-only and independent. Naming a business enables neither application submission nor payments.

A separate bounded authentication/email review must approve exact controlled environment and origin/HTTPS; operational mail identity/domain and delivery authority; signed URL/cookie/proxy/session behavior; trusted profile/lifecycle readiness; stale link/epoch/rate-limit/error privacy; bounced/replayed/misdirected link handling; and recovery after key/session/worker changes. Use synthetic recipients first and separately authorized controlled delivery evidence later. Do not use log fallback as proof that verification email was delivered, or expose verification/reset tokens in operator output. Do not remove the readiness hold until its approved replacement and release evidence exist.

## I. Target-specific preflight procedure — proposed only

**Part 1 authorizes none of this access. Part 2 requires a new explicit owner authorization packet.** Do not use the application, Artisan route/config/migration commands, resolver, startup/sync scripts or dependency hooks as a preflight shortcut. They can bootstrap, write or acquire a writer.

### Authorization packet required before any target access

| Field | Required owner-supplied value |
|---|---|
| Purpose/scope | INT-06B Part 2, read-only metadata evidence only; no activation |
| Environment | Exact controlled environment and organization/owner; do not assume production |
| Target | Exact engine, logical/physical store identity/location or approved endpoint, and ownership; no guessed paths |
| Operator/access | Named authorized operator; approved tool/runtime and read-only access method; secret custody through existing secure means, never a pasted credential |
| Metadata allowlist | Exact schema/ledger/enforcement/consistency reads and allowed redacted configuration attestations; exclude account/merchant payloads and tokens |
| Window/limits | Start/end with timezone, expiry, time/size/load bounds, permitted coordination and stop contact |
| Isolation | No app boot/jobs/triggers-as-probes, no writes/repair/changed PRAGMAs, proven read-only storage/connection behavior and coherent read view |
| Output custody | Named recipient/location/access, redaction, integrity attestation, retention and disposal; no public/unreviewed upload |
| Stop conditions | Target mismatch, insufficient read-only guarantee, secret/user-data exposure, unsafe WAL behavior, active cutover/unknown authority, unexpected objects/ledger or operator/owner withdrawal |
| Explicit exclusions | Backup/copy/snapshot/database hash, restore, migration, ledger repair, runtime config/bindings, deployment, live email/providers and writes |

### Proposed sequence after that approval

1. **Offline release preparation:** compare approved source/migration manifest digests against independently held release approval. Identify exact expected profile and retired synthetic migrations. Prepare a bounded, reviewable metadata query/tool plan without target access or app bootstrap. Owner reviews actual commands/tool behavior before execution.
2. **Prove target and read-only method:** authorized operator confirms exact target/environment, credentials' least privilege, connection/driver semantics, file/device permissions, runtime version, active writer/cutover state and output controls. Do not read operational dotenv into a transcript. If proving safe access itself requires additional metadata beyond the packet, stop to extend authorization.
3. **SQLite WAL/sidecar gate, if applicable:** determine approved coherent read strategy with the storage owner before opening. A nominal read-only open may require existing WAL/shared-memory access or create sidecars depending on circumstances. If it would write/create files, checkpoint, lock disruptively, repair or copy, stop; do not silently use immutable=1 on an active WAL database or copy only the main file. Sidecar/content reads are outside Part 1 and must be explicitly scoped in Part 2. An approved snapshot is a distinct permission, not automatic metadata authority.
4. **Collect only approved metadata in a coherent view:** actual migration id/name/batch ledger; exact table/index/trigger SQL catalogue; columns/FKs/index ownership/collation; connection/runtime enforcement and any specifically authorized consistency/integrity checks. Metadata schemas can contain sensitive literals; redact before export and stop if safe redaction is uncertain. Record the collection window/version and whether the view remained coherent. No merchant/account rows, credentials, token payloads, session values or private context are permitted outputs.
5. **Compare offline, never repair:** compare actual facts to frozen source/migration provenance, exact supported profile/catalogue/ledger and constraint/enforcement requirements. Highlight extra/missing/reordered/unsupported state. Report mismatch as unsupported/held rather than selecting a convenient profile. A non-SQLite engine or differing fixture ledger is a separate reviewed compatibility problem.
6. **Authority and restore evidence:** collect only separately permitted attestations of store identity, generation/fence registry custody, approval revocation and old-worker controls. Record backup/restore/commit-uncertainty evidence as missing where not authorized; Part 2 metadata cannot certify it by implication. Do not clear audit/replay holds.
7. **Return target evidence for owner decision:** produce a redacted metadata/provenance/compatibility report with exact commands/exits, evidence custody and limitations. End access at the approved window. Passing metadata inspection does not authorize migration, storage creation, operational selection or writing. Stop for owner review before implementation/execution scopes.

### Permissions that remain separate

| Operation | Requires distinct approval/evidence |
|---|---|
| Metadata reads | The Part 2 packet above; target, operator, allowlist and coherent read-only method |
| Backup/file copy/snapshot/database hashing | Exact object set including WAL coherence, operator, load/window, confidentiality/encryption/custody, integrity/provenance and destination; not part of ordinary metadata scope |
| Restore drill | Authorized source/destination, approved target-sensitive isolation, authentic backup/keys, release/profile compatibility, old-writer exclusion, external context/grant/replay recovery and generation/fence reassignment |
| Migration | Target-bound one-attempt schema+ledger ownership and approved installer, preflight/backup/restore evidence, failure/quarantine procedure and owner execution window; no generic migrate or downgrade shortcut |
| Uncertain migration commit | Fence/quarantine, retain original operation evidence, independently authorized fresh inspection/reconciliation; never rerun or edit ledger merely because a tool timed out |
| Runtime/HTTP activation | Independent approved release, authority/custody/authentication gates, exact target/worker rollout and revocation/recovery plan; not inferred from any earlier approval |

## J. Security gap and dependency matrix

Classifications are literal evidence limits, not production-readiness labels. VERIFIED IN ISOLATION refers to inspected historical reports, not fresh execution in Part 1. A dependency may have source mechanisms but still need external evidence/authorization.

| Dependency / classification | Current evidence | Missing evidence | Risk | Recommended approach | Required authority | Next action |
|---|---|---|---|---|---|---|
| Schema/ledger/source gate — VERIFIED IN ISOLATION | Frozen strict gate and prior reports; fresh source pins | Actual target compatibility | Unsupported store admitted | Preserve exact gate; offline target comparison | Owner + DB/release reviewer | Part 2 packet |
| Runtime selection — MISSING | Selector input and isolated descriptor validator | Real authenticated descriptor provisioning | Missing lifecycle availability or untrusted profile | Wire existing contract only after approval/evidence | Deployment/release approver | Name authority and artifact format |
| Release authenticity — EXTERNAL DEPENDENCY | Manifest comparisons; approval interface | Independent approval/revocation custody and actual artifact measurement | Coordinated source/manifest replacement | Existing protected release facilities, no assumed new signer | Independent release approver | Evidence of separation and revocation |
| Deployment/worker inventory — SOURCE-LEVEL EVIDENCE ONLY | Herd/local script and queue templates | Actual environment/topology/active release | Stale workers or wrong target | Authorized operator inventory; immutable release rollout | Environment operator | Scope nonsecret attestations |
| Store identity/generation/fence — EXTERNAL DEPENDENCY | Lineage contract; fictional enrollment | Actual connection binding, external epoch and old-writer exclusion | Restored/split store grants absence | Existing storage/deployment controls, proven fence | Store/cutover owner | Part 2 metadata + separately approved drill |
| Working ledger/catalogue — REQUIRES OWNER AUTHORIZATION | Exact fixture lineage only | Target facts | Invalid migration/false compatibility | Allowlisted coherent read-only preflight | Exact target owner | Approve Part 2 only |
| Private context — MISSING | Interface/fake; reusable DB/encryption mechanisms | Durable encrypted CAS store and adapter | Lost UUID/name or duplicate dispatch | Qualify existing backend; smallest dedicated custody schema if needed | Storage/security/retention owner | Concrete design after evidence |
| Standard sessions — SOURCE-LEVEL EVIDENCE ONLY | Driver/expiry/encryption templates; destroy/GC source | Effective config and custody semantics | Logout/expiry deletes sole context | Auth/reference only; independent unresolved custody | Identity/storage owner | Reject sole session custody |
| Cache/lock durability — SOURCE-LEVEL EVIDENCE ONLY | TTL, locks, ephemeral failover templates | Persistence/eviction/lease/recovery guarantees | Cache miss/lease expiry interpreted as retry | Performance/limits only unless backend separately qualified | Storage/operator | Document no custody failover |
| Atomic dispatch/replay — VERIFIED IN ISOLATION | INT-05 single-process model; INT-06A fictional veto | Multi-process crash/restore/ABA evidence | Duplicate execution | Durable CAS before one invocation; retain uncertainty | Custody/store authority | Adapter fault-test plan |
| Session rotation/logout recovery — DESIGN ONLY | Rotation/invalidation source; strict fake denial | Approved reauthorization/handoff and retained originals | Lost access or unauthorized rebinding | Fresh current-owner explicit resolution, original bytes retained | Identity + policy owner | Decide recovery rules before rollout |
| Trusted time — EXTERNAL DEPENDENCY | Interface, bounds and fictional clock | Provenance, persistent nonregression/jump/outage policy | False validity/retention/absence | Existing trusted host/deployment authority if proven | Time/operations owner | Supply source and recovery policy |
| Retention/policy revocation — EXTERNAL DEPENDENCY | Current/historical checks | Approved versions, unresolved-hold/access/deletion rules | Premature deletion or indefinite unauthorized retention | Explicit versioned policy and lawful held-state procedure | Policy/retention owner | Resolve expiry/hold conflict |
| Owner/tenant/epoch checks — VERIFIED IN ISOLATION | Fresh DB checks, policy and session auth epochs | Operational identity/grant custody | Revoked/wrong-tenant action | Reuse checks and mode-specific independent grants | Identity/grant issuer | Identify issuer/revocation evidence |
| Reconciliation holds — MISSING | Local pending/future-time veto and hook | External durable hold registry/clearing procedure | Restored local absence loses obligation | Independent hold custody and explicit reconciliation | Reconciliation owner | Define persistent evidence and access |
| Encryption/key custody — SOURCE-LEVEL EVIDENCE ONLY | Laravel cipher/config and separate evidence cipher | Context-specific key access, rotation and restore evidence | Private context loss/leakage | Reuse reviewed primitive; distinct approved domain/custody | Security/storage owner | Decide key lifecycle without reading secrets |
| Operational auth/email — REQUIRES OWNER AUTHORIZATION | Deliberate local/testing + log/array hold; signed links | Controlled mail/origin/session/delivery release | Bypassed auth or undelivered verification | Separate bounded auth/email readiness review | Identity/mail/release owner | Approve review scope; retain hold |
| HTTP boundary — DESIGN ONLY | Pure INT-05 validator and disabled UI | Integrated raw parsing/CSRF/origin/limits/flash/browser tests | Byte transformation/CSRF/privacy/false success | Later isolated integration of existing capabilities | Product/security owner | Await custody/release contract approval |
| Backup/restore/commit recovery — REQUIRES OWNER AUTHORIZATION | Fictional recovery tests and contracts | Target provenance, coherent backup, restore/fencing/power-loss evidence | Irrecoverable or ambiguous mutation | Separate least-privilege backup/drill/execution approvals | DB/recovery/release owner | Keep missing evidence explicit |
| Submission/Chief/providers/payments — VERIFIED IN ISOLATION | Explicit source holds and prior regressions | Separate future milestone authorization | Scope expansion from BNC success | Preserve holds throughout all BNC stages | Product/security owner | No activation in this roadmap |

## K. Recommended minimum implementation roadmap

This roadmap connects existing capabilities; it does not propose another set of interfaces. Stages may be split only where evidence reveals a concrete incompatibility. Every gate needs explicit approval and no later scope is implied.

| Stage | Exact prerequisites and scope | Acceptance criteria | NO-GO conditions | Owner decision |
|---|---|---|---|---|
| **INT-06B Part 2: target-specific read-only evidence** | Completed authorization packet; qualified read-only method; exact environment/target/operator; source/release references. Metadata and approved nonsecret attestations only | Redacted actual ledger/catalogue/enforcement/engine report and named custody authorities; gaps and coherent-view limits explicit; no target mutation | Unidentified target, unsafe read/WAL behavior, secrets/user-data exposure, unknown active cutover, expired scope, unsupported state needing repair | Explicit target-specific read-only approval; no backup/hash/restore/migration permission implied |
| **Bounded runtime/custody/admission implementation, isolated first** | Accepted Part 2 evidence; chosen existing backend and retention/handoff rules; independent approval/measurement/fence owners; approved successor release/catalogue design | Implement concrete adapters behind existing interfaces together; encrypted original custody, durable CAS, independent completion/resolution grants, real owner/epochs and fail-closed profile/store/time/revocation behavior; focused, bounded, multi-process/crash/restore tests on fictional or separately authorized sensitive clones | No independent authority; ephemeral custody; unresolved schema/ledger incompatibility; unknown retention; silent provider-pin regeneration; unsafe session recovery | Approve exact adapter files, custody schema/store and successor compatibility plan; no operational provider binding |
| **Future isolated HTTP/UI integration** | Accepted integrated adapters/contracts and durable-custody semantics; same raw/name rules; reviewed scoped middleware/errors | Optional eligible-owner completion and explicit resolution work end-to-end in isolated fixtures; actual CSRF/origin/session/byte/limit/privacy/browser/accessibility tests pass; default runtime admission remains denied | Form can dispatch without retained original context/resolution; automatic retry/UUID replacement; changed signup/dashboard gates; unreviewed pinned-source change | Approve concrete route/controller/view/middleware diff for isolation only |
| **Operational release preparation and controlled rollout** | Independently approved target migration/backup/restore/commit-uncertainty evidence, auth/email readiness, release/measurement/revocation, storage/time/policy/fences and operators; exact release candidate tests | Target-bound execution/recovery window, immutable approved bytes/descriptors, real old-writer exclusion, controlled optional BNC journey and explicit-resolution validation; revocation/hold path proven | Any missing target/recovery/auth/custody authority; migration uncertainty; stale worker/fence; activation inferred from fixtures | Separate written migration/execution/activation authorization naming target, release and rollback/quarantine operators |

Application submission, Chief decision writes, live OTP/NIN, Selcom and payments are excluded. No business name, phone possession proof or KYC is introduced as a new dashboard entry condition. If Part 2 shows an unsupported engine/ledger or no qualified durable backend, stop for one concrete compatibility/storage decision instead of drafting more interfaces or enabling a permissive adapter.

## L. Owner decisions required

1. Identify the intended controlled environment, exact target and authorized operator; do not assume local or production from this report. Approve Part 2's complete packet before access.
2. Name release approver, deploy operator, source measurer, store/fence owner, identity/grant issuer, time authority, retention owner and reconciler. Identify which existing facilities provide independent custody and revocation; leave unsupported claims marked missing.
3. Choose a candidate durable custody backend **after evidence**, approving its placement outside strict workflow catalogue or a reviewed successor schema; define encryption/key access, retention/holds, cleanup, restart/restore and session-rotation recovery.
4. Approve a concrete successor release/manifest compatibility strategy before any pinned provider/source change. Do not regenerate current manifests or use HEAD alone as identity for uncommitted work.
5. Separately approve operational authentication/email readiness review; keep current restrictions intact until a reviewed replacement is accepted. Never approve an APP_ENV/mail-setting bypass as the solution.
6. Keep backup/snapshot/database hashing, restore, migration and activation approvals separate from metadata access. Define recipients/output retention and stop contact for each authorized operation.

No answer is needed to complete Part 1. These decisions are prerequisites for later work; this report grants none of them.

## M. Preservation verification

Initial and final verification uses Git status/index inspection, allowlisted source/report `Get-Content`/`rg`, configuration-template source and file SHA-256 comparisons only. No effective runtime settings were read. No test/formatter/application command was necessary or executed. An initial guessed notification path was absent; the correct app/Notifications source was then inspected without mutation.

Existing manifest SHA-256 values matched:

| Manifest | SHA-256 |
|---|---|
| BNC | `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3` |
| INT-01 integration | `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a` |
| INT-02 backend | `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990` |

Initial and final results: **29/29 manifest/foundation source pins**, **4/4 INT-05 report pins** and all three existing manifest digests matched, exit 0. Final inventory comparison found **329 pre-existing files unchanged, zero changed, zero missing; this report is the sole added path**, exit 0. All sixteen initial untracked paths remain byte-preserved; the index is empty. `git diff --check` and explicit report trailing-whitespace checks passed, exit 0; required A–N sections are present. No unexpected source discrepancy was found; historical README/runtime statements remain explicitly unverified.

No dotenv, operational credentials/keys, working database, WAL/sidecar, backup content or user data was accessed. Only source/artifact/report files were hashed. No operational queries, bootstrap, migrations, tests, configuration changes, bindings, commit, push, reset, revert or deployment occurred. No Part 2 target inspection was executed.

## N. Final GO/NO-GO assessment

**GO for owner review of Part 1 documentation only. NO-GO for target access until separate Part 2 authorization, and NO-GO for operational provisioning/activation.** The sole deliverable is this report.

**STOP FOR OWNER REVIEW.**

- B3.2-C activation: **NO-GO**.
- SE-1.0 activation: **NO-GO**.
- Business Name Completion HTTP writes: **DISABLED**.
- Public merchant application submission: **DISABLED**.
- Chief Admin decision writes: **DISABLED**.
- Live OTP/NIN integrations: **DISABLED**.
- Selcom live integration: **DISABLED**.
- Live payments: **DISABLED**.
- Working database migrations: **NOT AUTHORIZED**.
- Operational adapter enablement: **NOT AUTHORIZED**.
