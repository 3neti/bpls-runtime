# IPIL RESCUE GATE 8C — PRIVATE HISTORICAL UAT READY FOR NELSON REALITY WALKTHROUGH

Status: **MATERIALIZED AND AUDITED; NOT WALKTHROUGH-READY — SEARCH-FORM ACCEPTANCE BLOCKER**

Date: 2026-09-12 (Asia/Manila). The commissioned heading is not a readiness claim.

Authority: owner's Gate 8C commission and secure-connection continuation from `640afe9d4a55e5d20ee4c11adccb44f923597a32`. Governing evidence: [Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md), [implementation plan](IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md), [Gate 6](IPIL_RESCUE_GATE_6_FIRST_LOCAL_MATERIALIZATION_2026_09_11.md), and [Gate 8A](IPIL_RESCUE_GATE_8A_HISTORICAL_READ_SURFACE_ACCEPTANCE_2026_09_11.md).

## Actual execution

Secure database configuration was corrected without weakening TLS. Deployment succeeded, the restricted reviewer authenticated, a new target-bound authorization was issued, and the deterministic historical import completed. The immediate audit and a second read-only audit after browser inspection each passed **all 61 checks**, with zero failures and one completed import run.

Exactly **16 accepted associated media objects / 25,399,590 bytes** were privately transferred and independently read back for SHA-256 and size verification. All **19 unresolved objects remain unattached**. The entire rescue corpus was not uploaded. No live Ipil access, workflow-UAT mutation, production migration, reconciliation, cleanup, or Renewal bridge occurred.

Browser acceptance uncovered a product blocker: submitting the unified search form leaves the URL without its query parameters and displays zero unified matches. Reproduced with real business, owner, Application, Permit, and OR examples. Direct authenticated query URLs return the expected Permit and OR matches; the same read-surface service against the target database finds the expected match in all five categories. This is an observed browser submission failure, not evidence of missing imported records. Its precise frontend/build root cause remains unestablished. No product fix was implemented under this continuation's limited configuration scope.

## Secure PostgreSQL correction

Original Cloud alias: `ep-round-leaf-aosjawvm.c-2.aws-ap-southeast-1.pg.laravel.cloud`.

Certificate-correct endpoint: `ep-round-leaf-aosjawvm.c-2.ap-southeast-1.aws.neon.tech`.

The alias's DNS target identified `c-2.ap-southeast-1.aws.neon.tech`. A PostgreSQL TLS handshake verified the endpoint-preserving canonical hostname against the presented wildcard certificate with OS roots: verification return code zero, TLS 1.3, ISRG Root X1 chain. An authenticated read-only query returned database `bpls_ipil_historical_gate8c` and Neon endpoint `ep-round-leaf-aosjawvm`; there were zero public tables before deployment. The exact cluster identity was checked against the provider control plane.

The isolated Cloud connection URL retains **sslmode=verify-full**, with OS roots at `/etc/ssl/certs/ca-certificates.crt`. The local materializer also uses verify-full and its OS trust bundle. Successful Cloud migrations prove the corrected connection from the runtime. No require/verify-ca downgrade, hostname-verification disablement, insecure flags, or trust bypass was used. Provider context: [Laravel Cloud PostgreSQL](https://laravel.com/cloud/docs/resources/databases/postgres), [Neon secure connections](https://neon.com/docs/connect/connect-securely).

## Exact target separation

| Resource | Verified identity |
| --- | --- |
| Historical application | `app-a2b8d56c-d074-4005-82ae-2679e2f60f09` |
| Historical environment | `env-a2b8d5b4-7ab8-4c99-a711-a88053b7fde5`, historical-ipil-uat-gate8c |
| Branch / deployed SHA | `agent/migration/ipil-gate8c` / `cfb6f3b2d7ac77a22227b63fdb64b646ce132da2` |
| Final activation deployment | `depl-a2b96b62-7c9a-44c7-a25c-f5481f38b0de`, deployment.succeeded |
| PostgreSQL cluster | `twilight-bonus-30025572`, PostgreSQL 17, ap-southeast-1 |
| Historical database | **1099424**, `bpls_ipil_historical_gate8c` |
| Private bucket | `fls-a2b95366-1364-456a-8471-86c4feac19fc`; display name bpls-ipil-historical-gate8c |
| Provider default database | 1099422: not selected or used |
| Protected workflow application | `app-a28928ff-2881-48eb-bfbb-f89cfac5f51d`: not modified |
| Protected workflow environment/database | `env-a2892915-0c8a-4e9d-b974-a1f8adfad1c1` / 1040718: not modified |

[Restricted historical UAT](https://bpls-ipil-historical-review-gate8c-historical-ipil-uat-g-cwkour.laravel.cloud/staff/ipil-history) uses ordinary BPLS sign-in with an exact-reviewer allowlist, not public taxpayer search. Automatic push-to-deploy and deploy hooks remain disabled. Only the isolated branch was pushed, after verifying the repository binding as `3neti/bpls-runtime`. Main and the workflow release branch were not pushed.

Earlier deployments `depl-a2b95d40-a463-4467-ac24-8edfd27316b1` and `depl-a2b95f5b-48de-470c-baf2-3c5e7f24060f` failed before schema creation on TLS hostname mismatch. The explicitly authorized canonical-host correction succeeded in `depl-a2b9652d-12fb-446a-9683-a43b23778793`. Deployment `depl-a2b969ce-e891-4d4e-b0d6-ee014563f38e` attached the isolated read-only media integration. Provider-generated storage configuration established that the physical S3 bucket name is its resource ID, not its display name. The final deployed commit narrowly corrects that authorization constant and its test. Historical writes started only after final deployment and target verification.

Resources remain provisioned and may incur charges; no teardown was attempted. Separate read/write materializer and read-only runtime media keys remain private.

## Immutable execution chain

| Artifact | Binding |
| --- | --- |
| Corpus | `ipil-20260910t153224z-2ab19c17` |
| Corpus SHA-256 | `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81` |
| Profile | `ipil-rescue-mapping-v1.0.0` |
| Profile SHA-256 | `edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c` |
| Plan | `ipil-seed-plan-874d26eb21ed0041` |
| Plan SHA-256 | `874d26eb21ed004147bdec32fdbd4cbf097101defdf37a7697776ea42080d9fb` |
| Accepted Gate 5 manifest SHA-256 | `c906494f1c37a4375d78343037e4c5a443986aba9df65217ab4efa4fdbfb64b6` |
| New Gate 8C authorization SHA-256 | `b4838b84d35c2e08a335be42232162ea3b7583664df79e4ff843ea9fe5cd6046` |
| Completed import run | `ipil-g8c-20260912002534-ycsqqnja` |

The private authorization binds the exact application/environment/cluster/database, verified host identity, deployed commit/deployment, private media target, reviewer fingerprint, immutable chain, and 16-object limit. Its body is not committed. Gate 6's local-only guard is unchanged. Pre-import corpus verification and plan fingerprint checks passed. The accepted Gate 5 manifest compares byte-for-byte equal to the original checkout's private manifest after execution.

One Gate 8C import ran. The second audit was read-only, not another seed; the accepted two-run idempotency proof remains Gate 6 evidence.

## Quantitative parity

| Evidence | Audited actual = required |
| --- | ---: |
| Source identities / evidence excluding authentication payloads | 324,873 / 246,230 |
| Owners | 3,194 |
| Businesses | 3,212 |
| Historical Applications | 3,137 |
| Renewal / New / Additional | 2,621 / 461 / 55 |
| Schedules | 7,648 |
| Payments | 5,874 |
| Receipt claims | 5,873 |
| Permit claims | 2,766 |
| Clearance claims | 14,615 |
| Completed-payment total | **PHP 93,295,317.20** |
| Aggregate schedule-paid total | **PHP 93,295,317.20** |
| Non-cent-exact Application / Schedule totals | 348 / 24 |
| Duplicate OR groups / events | 196 / 744 |
| Permits missing Applications | 15 |
| Broken Permit business / owner edges | 10 / 10 |
| Broken clearance-type references | 110 |
| Accepted media / unresolved media | 16 / 19 |
| Unresolved media imported / checksum mismatches | 0 / 0 |

Both audits also pass source statuses, additional missing-edge counts, provenance bindings, and operational-isolation checks. No tolerance or current Price recalculation was used. Audited historical actionability, taxpayer-derived Users, operational Applications, current liabilities, Collections, receipts, work, Payment Orders, certifications/signatures, fabricated lodging manifests, and historical media on operational Applications are all zero. No fuzzy merge, automatic PSGC acceptance, or historical correction occurred.

The 16 objects comprise one accepted business upload and 15 generated/layout/platform artifacts, retained in separate media collections. The business upload is privately retrievable; its business has no historical Application, and none was fabricated for attachment. Authenticated application streaming avoids public or published signed storage URLs. Independent remote read-back verified exactly 16 objects and 25,399,590 bytes. Anonymous document access returned HTTP 302 to login.

## Restricted access and credential follow-up

The initial owner-authorized reviewer has only staff-entry and historical-view permissions, not Admin. Ordinary email/password login succeeded before import. Anonymous history redirects to login; the authenticated reviewer requesting operational Applications received 404. The restriction layer admits authentication plus GET/HEAD historical routes, rejects other identities, and excludes registration, preview personas, account claiming, exports, and operational mutations. Private/no-store, no-index and no-referrer policy is covered by focused tests.

**Credential follow-up remains open:** the temporary bootstrap password unexpectedly appeared in a local file-picker preview during sign-in preparation. It was not committed. A rotation attempt was rejected by the safety check pending explicit approval; this was not bypassed. Approval was requested in the task, with no response received before this report closed. Neither password nor reviewer email is reproduced here. Rotate the temporary credential with explicit approval before broader reviewer handoff; do not treat it as a durable shared walkthrough password.

## Required return checklist

| # | Item | Actual result |
| --- | --- | --- |
| 1 | Starting SHA | This continuation `640afe9d4a55e5d20ee4c11adccb44f923597a32`; accepted Gate 8A `b851a56390a69ab3845e116f919544ecdc8684ec` |
| 2 | Final/deployed SHA | `cfb6f3b2d7ac77a22227b63fdb64b646ce132da2`; this later report-only commit is not deployed |
| 3 | Historical environment | Separate environment above: deployed and populated |
| 4 | Workflow separation | Separate application, environment, cluster/database and storage; no workflow mutation |
| 5 | Deployment | `depl-a2b96b62-7c9a-44c7-a25c-f5481f38b0de`, succeeded with full TLS verification |
| 6 | Access | Exact restricted reviewer; ordinary sign-in proved; credential rotation pending approval |
| 7 | Authorization | New Gate 8C fingerprint above; Gate 6 guard unchanged |
| 8 | Corpus/profile/plan/manifest | All bindings pass; accepted manifest byte comparison passes |
| 9 | Import | `ipil-g8c-20260912002534-ycsqqnja`, completed |
| 10 | Counts | All required anchors match the table above |
| 11 | Financial anchor | Both totals exactly PHP 93,295,317.20 |
| 12 | Anomalies | All accepted anomalies preserved |
| 13 | Media | 16 private objects, full source/destination and independent remote verification pass |
| 14 | Unresolved media | All 19 unattached; zero imported |
| 15 | ipil:audit | Immediate and post-inspection audits pass all 61 checks |
| 16 | Operational isolation | Audit zeroes and browser route refusal pass; no continuation actions |
| 17 | Directory | Real records/anchors visible; page-two pagination verified |
| 18 | Owner | Owner/business/history navigation works; explicit owner-not-User wording |
| 19 | Business History | Real Application links and truthful year/type/status; missing evidence explicit |
| 20 | Historical Application | Finance, schedules, payments, OR, Permit, classifications, clearances, and non-operational state verified |
| 21 | Search | **FAIL:** on-page submissions drop parameters; five real categories tested. Read-service searches return expected matches |
| 22 | OR lookup | Direct authenticated query returns the claim; on-page submission fails |
| 23 | Permit lookup | Direct authenticated query returns the claim; on-page submission fails |
| 24 | Historical document | Private browser image loads (500×410); anonymous request redirects to login |
| 25 | Priority reports | DEFER per Gate 8A; no new reports exposed. Directory quantitative anchors match audit |
| 26 | Desktop | Directory/owner/business/Application navigation exercised; 1280×720 owner/directory overflow checks pass. Search form fails |
| 27 | 390×844 | Directory/owner/Business History/Application/document-link layouts show no document-level horizontal overflow; mobile owner visually inspected. Search form fails |
| 28 | Console/network | Inspected error log empty; no unexpected history/document server error encountered. Intentional operational 404 and anonymous-document 302 are security outcomes |
| 29 | Tests | Scoped results below; no new full-suite green claim |
| 30 | Privacy/Git | Code/tests/aggregate docs only; no real taxpayer rows, media, PII manifests, credentials or dumps committed |
| 31 | Blockers | Search-form submission defect; temporary credential rotation approval. TLS/import/media are resolved |
| 32 | Nelson recommendation | **NO** until the bounded defect is corrected/retested and credential handoff secured |

## Dispositions and next boundary

- **MATCH:** counts, exact totals, statuses, anomalies, provenance and accepted media bytes.
- **ADAPT:** isolated restricted UAT, certificate-correct provider host with full verification, authenticated private media streaming, non-operational history presentation.
- **IMPROVE:** separately authorize a bounded search-query propagation correction and regression tests. Recheck all five lookup categories, filter/sort/clear state, and desktop/mobile behavior. No historical data changes are indicated.
- **DEFER:** full report reconstruction, PSGC/LOB acceptance, collision/anomaly cleanup, unresolved media, Renewal bridge and production migration.

No reseed is indicated. Retain the audited materialization and use read-only validation in the follow-up. Do not broaden credential rotation into permission changes or public access.

## Verification and preservation

The preceding activation packet passed **41 tests / 349 assertions**, covering activation authorization, reviewer guards, preserved Gate 6 restrictions, private disk selection, historical route isolation, dynamic totals and rescue contracts. This continuation's bucket-binding change passed **15 tests / 133 assertions** and Pint. Git whitespace validation passed. No full suite was rerun in this continuation.

Earlier baseline full-suite evidence remains in the prior report revision: 924 passed, one skipped, one assertion failure and three errors among 929 tests (16,021 assertions). The four preview-configuration failures passed when the two affected files ran with explicit synthetic preview startup settings (8 tests / 341 assertions). That is not a full-suite green claim or authority to enable preview in historical UAT. Prior targeted PHPStan checks passed.

Private execution evidence remains in Git-ignored `storage/app/private/ipil-rescue/gate8c/`: authorization, access proof, import result, both audits, and media verification. Do not commit runtime artifacts, reviewer identity, real-record examples, private document URLs, or taxpayer screenshots.

Worktree: `/Users/rli/PhpstormProjects/bpls-gate8c`. Original checkout: `/Users/rli/PhpstormProjects/bpls-runtime`. Its four unrelated guidance edits remain untouched and uncommitted by this task: `.ai/skills/deploying-laravel-cloud/SKILL.md`, `.ai/skills/deploying-laravel-cloud/reference/checklists.md`, `AGENTS.md`, and `CLAUDE.md`.

GATE 8C: FAIL — PRIVATE HISTORICAL UAT NOT READY FOR NELSON
