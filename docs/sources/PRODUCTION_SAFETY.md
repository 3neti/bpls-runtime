# Production Safety Protocol

The currently deployed BPLS application is live production evidence, not a test sandbox.

Permitted by default:

- authenticate using authorized accounts
- navigate pages
- inspect visible content, menus, forms, reports, and documents
- capture screenshots
- note URLs and role-visible surfaces
- observe non-mutating browser behavior
- download artifacts the authenticated user is plainly authorized to retrieve

Not permitted by default:

- create, edit, approve, reject, assess, pay, release, upload, delete, archive, or bulk-modify records
- change users, roles, configuration, workflows, fee schedules, or integrations
- trigger SMS, email, payment gateways, load tests, fuzzers, vulnerability scans, brute-force route discovery, or undocumented mutation APIs

If an action's consequence is uncertain, classify it as mutating and do not execute it.

Required request format for any production mutation exception:

```text
Unknown behavior:
Why source inspection is insufficient:
Proposed production operation:
Potential side effects:
Affected records/systems:
Isolation method:
Cleanup method:
Expected evidence:
```

Credentials, cookies, tokens, session dumps, and secrets must remain outside Git.
