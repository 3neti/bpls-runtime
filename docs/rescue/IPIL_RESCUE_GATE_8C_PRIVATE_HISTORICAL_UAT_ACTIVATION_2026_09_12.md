# IPIL RESCUE GATE 8C — PRIVATE HISTORICAL UAT READY FOR NELSON REALITY WALKTHROUGH

Status: **INCOMPLETE — NOT READY; NO HISTORICAL DATA TRANSFER**

Date: 2026-09-12 (Asia/Manila)

Authority: owner's Gate 8C Private Historical UAT Activation commission. Governing evidence: [Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md), [implementation plan](IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md), [Gate 6](IPIL_RESCUE_GATE_6_FIRST_LOCAL_MATERIALIZATION_2026_09_11.md), and [Gate 8A](IPIL_RESCUE_GATE_8A_HISTORICAL_READ_SURFACE_ACCEPTANCE_2026_09_11.md). The required heading is the commissioned report title, not a readiness claim.

## Actual execution and access gate

A separate Cloud application, empty environment, and PostgreSQL 17 cluster/database were provisioned. No deployment, migration, historical seed, media transfer, or remote historical audit ran. No source connection was made. Accepted source artifacts and the existing local Gate 6 database were not modified.

The owner must identify the initial authorized reviewer email before access is provisioned. A question was submitted in the task. The proposed mechanism is existing BPLS/Laravel sign-in with an explicit reviewer allowlist, no public registration, no preview-persona switching, and a historical-only route boundary. No recipient, reviewer credential, or taxpayer-derived User has been invented.

Local code implements a fail-closed, opt-in middleware bound to the new environment ID. It requires the historical UAT environment classification, disables preview mode, validates the configured reviewer, rejects other authenticated users, admits only authentication and read-only historical routes, and adds private/no-store, no-index, and no-referrer headers. It is not yet deployed or browser-accepted. Default-disabled behavior preserves other environments.

## Provisioning evidence and separation

| Resource | Verified identity / state |
| --- | --- |
| New Cloud application | `app-a2b8d56c-d074-4005-82ae-2679e2f60f09` — BPLS Ipil Historical Review Gate8C |
| New environment | `env-a2b8d5b4-7ab8-4c99-a711-a88053b7fde5` — historical-ipil-uat-gate8c |
| Environment control-plane state | `running`, branch `main`, `currentDeploymentId=null`, `databaseSchemaId=null`; this is NOT evidence of a deployed application |
| New PostgreSQL 17 cluster | `twilight-bonus-30025572` — bpls_ipil_historical_uat_gate8c, available, ap-southeast-1 |
| Intended historical database | `1099424` — bpls_ipil_historical_gate8c; not attached or seeded |
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

## Required return checklist

| # | Item | Actual result |
| --- | --- | --- |
| 1 | Starting SHA | `b851a56390a69ab3845e116f919544ecdc8684ec` |
| 2 | Final/deployed SHA | Local packet on `agent/migration/ipil-gate8c`; no deployed SHA |
| 3 | Historical environment | New environment identified above; not activated |
| 4 | Workflow separation | Separate application, environment, and cluster; no workflow write |
| 5 | Deployment | None; deployment ID null |
| 6 | Access | Local restriction code prepared; reviewer identity and live proof pending |
| 7 | Authorization identity | Not issued; Gate 6 guard not weakened |
| 8 | Corpus/profile/plan/manifest | Frozen identities recorded above; Gate 8C pre-write revalidation not run |
| 9 | Historical import run | None |
| 10 | Materialized counts | Not run; expected owners 3,194, businesses 3,212, Applications 3,137, schedules 7,648, payments 5,874, receipt claims 5,873, permits 2,766, clearances 14,615 |
| 11 | Financial anchor | Not measured in Cloud; required exact PHP 93,295,317.20 independently for completed payments and schedule paid |
| 12 | Anomalies | Not measured in Cloud; retain 348/24 non-cent totals, 196 duplicate OR groups/744 events, 15 missing-Application permits, 10/10 broken business/owner edges, 110 broken clearance-type references |
| 13 | Media transfer | None; private destination/checksum verification pending |
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
| 31 | Blocker / remaining execution | Initial reviewer identity required. Then finish target-bound authorization and transport support, configure/deploy, establish access, materialize, audit, verify private media, and browser-test. These are NOT complete. |
| 32 | Nelson recommendation | NO — do not invite walkthrough yet |

## Verification and handoff

Final focused restriction/read-surface tests passed **8 tests / 114 assertions**. They cover unauthenticated access, normal sign-in, registration refusal, a different authenticated staff member, the accepted reviewer, operational-route refusal, private response headers, invalid environment/reviewer configuration, and refusal to silently disable restrictions in `historical-uat`. Unchanged rescue contracts passed **26 tests / 217 assertions**. Targeted PHPStan passed with zero errors; Pint and Git whitespace checks passed.

The full suite in the fresh worktree reported 929 tests: 924 passed, one skipped, one assertion failure, and three errors (16,021 assertions). The four failures concerned preview routes/personas: the fresh worktree lacked the preview startup configuration used to register those conditional routes and provision actors. Rerunning both affected files (`ClassicLifecycleCeremonyTest` and `NewApplicationHappyPathLifecycleScenarioTest`) with the explicit synthetic preview startup configuration passed **8 tests / 341 assertions**. The entire suite was not rerun with that configuration, so this report does not claim a green full-suite run. These test settings are not authorization to enable preview mode in historical UAT.

Worktree: `/Users/rli/PhpstormProjects/bpls-gate8c`. Original checkout: `/Users/rli/PhpstormProjects/bpls-runtime`; its four unrelated guidance edits remain untouched. No push, deployment, production migration, source mutation, history correction, reconciliation, or renewal bridge occurred.

**GATE 8C: FAIL — PRIVATE HISTORICAL UAT NOT READY FOR NELSON**
