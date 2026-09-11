# IPIL RESCUE GATE 8C — PRIVATE HISTORICAL UAT READY FOR NELSON REALITY WALKTHROUGH

Status: **BLOCKED — DATABASE TLS HOSTNAME VERIFICATION; NO HISTORICAL DATA TRANSFER**

Date: 2026-09-12 (Asia/Manila)

Authority: owner's Gate 8C Private Historical UAT Activation commission. Governing evidence: [Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md), [implementation plan](IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md), [Gate 6](IPIL_RESCUE_GATE_6_FIRST_LOCAL_MATERIALIZATION_2026_09_11.md), and [Gate 8A](IPIL_RESCUE_GATE_8A_HISTORICAL_READ_SURFACE_ACCEPTANCE_2026_09_11.md). The required heading is the commissioned report title, not a readiness claim.

## Actual execution and access gate

A separate Cloud application, environment, PostgreSQL 17 cluster/database, and private object-storage bucket were provisioned. Two deployments built successfully but failed at the migration command before creating any database tables. No historical seed, media transfer, or remote historical audit ran. No source connection was made. Accepted source artifacts and the existing local Gate 6 database were not modified.

The owner supplied the initial reviewer identity. It is configured privately in the isolated environment and is absent from Git and this report. Account provisioning has not run because deployment has not succeeded. The mechanism is existing BPLS/Laravel sign-in with an explicit reviewer allowlist, no public registration, no preview-persona switching, and a historical-only route boundary. The new guarded provisioning command grants only staff entry and historical-view permissions; no taxpayer-derived User is created. A bootstrap credential remains in a local mode-0600, Git-ignored runtime artifact and was not sent to Cloud configuration or emitted in tool output.

Local code implements a fail-closed, opt-in middleware bound to the new environment ID. It requires the historical UAT environment classification, disables preview mode, validates the configured reviewer, rejects other authenticated users, admits only authentication and read-only historical routes, and adds private/no-store, no-index, and no-referrer headers. It is not yet deployed or browser-accepted. Default-disabled behavior preserves other environments.

## Provisioning evidence and separation

| Resource | Verified identity / state |
| --- | --- |
| New Cloud application | `app-a2b8d56c-d074-4005-82ae-2679e2f60f09` — BPLS Ipil Historical Review Gate8C |
| New environment | `env-a2b8d5b4-7ab8-4c99-a711-a88053b7fde5` — historical-ipil-uat-gate8c |
| Environment control-plane state | Branch `agent/migration/ipil-gate8c`, database `1099424` attached, automatic push-to-deploy disabled; no successful deployment |
| New PostgreSQL 17 cluster | `twilight-bonus-30025572` — bpls_ipil_historical_uat_gate8c, available, ap-southeast-1 |
| Intended historical database | `1099424` — bpls_ipil_historical_gate8c; attached, zero public tables after both failed deployments |
| Private historical media bucket | `fls-a2b95366-1364-456a-8471-86c4feac19fc` — bpls-ipil-historical-gate8c; no media transfer |
| Media credential separation | Local materializer read/write key; separate read-only runtime key. Values remain private; no public URLs or whole-corpus upload |
| Provider-created default database | `1099422` — production; empty/unselected by this task, NOT a production migration target |
| Protected workflow application ID | `app-a28928ff-2881-48eb-bfbb-f89cfac5f51d` |
| Protected workflow environment | `env-a2892915-0c8a-4e9d-b974-a1f8adfad1c1`, database `1040718`, branch `release/workflow-handoff-uat-20260911` |

The new resources may incur Cloud charges; no teardown was attempted. Retain them for continuation unless the owner authorizes deletion. The new cluster uses 0.25 minimum/maximum compute units, 300-second suspend, and one-day retention.

The installed CLI's cluster provisioning presets use version-suffixed types, but the live API returns a separate type and version. The initial CLI attempt failed before resource creation. A read-only type inventory established `neon_serverless_postgres` and supported version `17`; an API request without the version was rejected (422), and the corrected explicit version request returned 201. Credentials were consumed only within the authenticated process and never printed.

Automatic safety review rejected adding an environment to the workflow application's container. That rejected operation was not retried or bypassed. A wholly separate application was created instead; the workflow environment/database were not changed.

## Frozen chain — expected, not a new execution authorization

| Artifact | Immutable binding |
| --- | --- |
| Corpus | `ipil-20260910t153224z-2ab19c17` |
| Corpus fingerprint | `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81` |
| Mapping profile | `ipil-rescue-mapping-v1.0.0` |
| Profile fingerprint | `edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c` |
| Seed plan | `ipil-seed-plan-874d26eb21ed0041` |
| Plan fingerprint | `874d26eb21ed004147bdec32fdbd4cbf097101defdf37a7697776ea42080d9fb` |
| Accepted Gate 5 manifest fingerprint | `c906494f1c37a4375d78343037e4c5a443986aba9df65217ab4efa4fdbfb64b6` |

The existing Gate 6 execution guard remains unchanged and local-only. No Gate 8C authorization has been issued. It must bind these artifacts, the exact separate environment/database, the deployed code, and private media destination before historical writes. The full corpus must stay local: only accepted materialized data and the 16 accepted media copies may be transported under the commissioned scope. Nineteen unresolved objects must stay unattached.

## Continuation from f50520e — implementation and deployment blocker

Continuation starting SHA: `f50520ee04894d40c6826882040d0ad8f4edd056`. Activation code is committed and pushed only on `agent/migration/ipil-gate8c` at `640afe9d4a55e5d20ee4c11adccb44f923597a32`. The authenticated Cloud application repository binding and existing Git remote both identify `3neti/bpls-runtime`; this was verified before publishing. Neither `main` nor the workflow release branch was pushed.

The packet adds a separate Gate 8C authorization contract, guarded reviewer provisioning, explicit private-historical execution mode, private S3-compatible media support, source/destination media size and SHA-256 verification, and truthful remote audit/seed metadata. Gate 6 remains local-only. The historical directory's previously hard-coded paid total now comes from the actual historical completed-payment evidence using exact decimal arithmetic. The standard Flysystem S3 adapter and its locked dependencies support private R2 transport. No historical mappings or accepted artifacts were changed.

The copied local corpus passed offline verification again: 97 bound files, 324,873 source identities, 324,833 database rows, 35 media objects, five pricing records, and canonical corpus fingerprint unchanged. This is integrity verification, not a Cloud materialization/audit claim.

Deployment attempts:

| Deployment | Actual result |
| --- | --- |
| `depl-a2b95d40-a463-4467-ac24-8edfd27316b1` | Build passed; deploy command failed with PostgreSQL TLS hostname/certificate mismatch |
| `depl-a2b95f5b-48de-470c-baf2-3c5e7f24060f` | Build passed; explicit connection-URL correction did not resolve the same TLS mismatch |

The Cloud database alias terminates in `.pg.laravel.cloud`, while the failed Cloud-side connection reports a certificate for `*.c-2.ap-southeast-1.aws.neon.tech`. The local connection to the provider-supplied hostname succeeds with `sslmode=verify-full` and system roots; the Cloud failure shows an IPv6 address. Different network resolution/termination is a hypothesis, not yet a proven root cause. The attempted explicit `DB_URL` still used the provider-supplied alias, not an independently verified Neon hostname. Certificate verification was not disabled or downgraded.

The [deployment skill](../../.ai/skills/deploying-laravel-cloud/SKILL.md) requires pausing after the same error recurs after one correction. Resume by resolving the certificate-correct Cloud-side connection to this exact historical database, retaining full verification, then retrying deployment. Do not issue execution authorization or seed until live restricted access and all target bindings are proved. Reviewer email is no longer a blocker.

## Required return checklist

| # | Item | Actual result |
| --- | --- | --- |
| 1 | Starting SHA | Original `b851a56390a69ab3845e116f919544ecdc8684ec`; this continuation `f50520ee04894d40c6826882040d0ad8f4edd056` |
| 2 | Final/deployed SHA | Activation code `640afe9d4a55e5d20ee4c11adccb44f923597a32`; both deployment attempts used it; no successfully deployed SHA |
| 3 | Historical environment | New environment identified above; not activated |
| 4 | Workflow separation | Separate application, environment, and cluster; no workflow write |
| 5 | Deployment | Two failed attempts listed above; build passed, database setup failed |
| 6 | Access | Reviewer identity privately configured; account provisioning and live proof pending |
| 7 | Authorization identity | Not issued; Gate 6 guard not weakened |
| 8 | Corpus/profile/plan/manifest | Corpus integrity reverified unchanged; full Gate 8C target-bound pre-write chain still pending |
| 9 | Historical import run | None |
| 10 | Materialized counts | Not run; expected owners 3,194, businesses 3,212, Applications 3,137, schedules 7,648, payments 5,874, receipt claims 5,873, permits 2,766, clearances 14,615 |
| 11 | Financial anchor | Not measured in Cloud; required exact PHP 93,295,317.20 independently for completed payments and schedule paid |
| 12 | Anomalies | Not measured in Cloud; retain 348/24 non-cent totals, 196 duplicate OR groups/744 events, 15 missing-Application permits, 10/10 broken business/owner edges, 110 broken clearance-type references |
| 13 | Media transfer | None; private bucket and separate materializer/runtime keys configured; actual checksum transfer validation pending |
| 14 | Unresolved media | No attachment attempted; all 19 remain outside this activation |
| 15 | ipil:audit | Not run remotely; no parity claim |
| 16 | Operational isolation | No operational execution invoked; post-materialization audit still required |
| 17 | Directory | Cloud browser test not run |
| 18 | Owner | Cloud browser test not run |
| 19 | Business History | Cloud browser test not run |
| 20 | Historical Application | Cloud browser test not run |
| 21 | Search | Cloud real-record test not run |
| 22 | OR lookup | Cloud real-record test not run |
| 23 | Permit lookup | Cloud real-record test not run |
| 24 | Historical document | Cloud private retrieval not tested |
| 25 | Priority reports | DEFER under Gate 8A; no new report exposed |
| 26 | Desktop | Not tested on Cloud |
| 27 | 390×844 | Not tested on Cloud |
| 28 | Console/network | No application browser acceptance claim |
| 29 | Tests | See verification below |
| 30 | Privacy/Git | Code/tests/aggregate documentation only; no taxpayer row, media, credential, dump, or PII manifest committed |
| 31 | Blocker / remaining execution | Repeated Cloud PostgreSQL TLS hostname mismatch. Resolve secure connection/deployment, provision reviewer and prove live access, issue target-bound authorization, materialize, audit, verify private media, and browser-test. These are NOT complete. |
| 32 | Nelson recommendation | NO — do not invite walkthrough yet |

## Verification and handoff

Final focused restriction/read-surface tests passed **8 tests / 114 assertions**. They cover unauthenticated access, normal sign-in, registration refusal, a different authenticated staff member, the accepted reviewer, operational-route refusal, private response headers, invalid environment/reviewer configuration, and refusal to silently disable restrictions in `historical-uat`. Unchanged rescue contracts passed **26 tests / 217 assertions**. Targeted PHPStan passed with zero errors; Pint and Git whitespace checks passed.

The full suite in the fresh worktree reported 929 tests: 924 passed, one skipped, one assertion failure, and three errors (16,021 assertions). The four failures concerned preview routes/personas: the fresh worktree lacked the preview startup configuration used to register those conditional routes and provision actors. Rerunning both affected files (`ClassicLifecycleCeremonyTest` and `NewApplicationHappyPathLifecycleScenarioTest`) with the explicit synthetic preview startup configuration passed **8 tests / 341 assertions**. The entire suite was not rerun with that configuration, so this report does not claim a green full-suite run. These test settings are not authorization to enable preview mode in historical UAT.

Continuation verification: **41 tests / 349 assertions passed**, covering the new activation guard, reviewer refusal outside the explicit environment, preserved Gate 6 refusal, private media disk selection, historical route restriction, dynamic historical total, and rescue contracts. Targeted PHPStan passed with zero errors after increasing its local analysis memory limit; Pint and Git whitespace checks passed. No new full-suite or real Cloud browser parity claim is made.

Worktree: `/Users/rli/PhpstormProjects/bpls-gate8c`. Original checkout: `/Users/rli/PhpstormProjects/bpls-runtime`; its four unrelated guidance edits remain untouched. The isolated code branch was pushed and two deployments attempted, as recorded above. No production migration, historical data transfer, source mutation, history correction, reconciliation, or renewal bridge occurred. The Cloud resources remain provisioned and may incur charges.

**GATE 8C: FAIL — PRIVATE HISTORICAL UAT NOT READY FOR NELSON**
