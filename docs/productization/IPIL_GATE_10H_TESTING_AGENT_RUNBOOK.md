# IPIL BPLS Gate 10H — Human Turnover Review Runbook

**Audience:** Anaïs and the BPLS Testing Agent
**Environment:** `http://bpls-gate10.test/`
**Accepted candidate:** `258f7d10263eea353228c584ad0d9a1fce861818`

This is a hand-holding guide for reviewing the private synthetic Gate 10 turnover environment. Read the whole guide before beginning.

## 1. What this review is—and is not

You are reviewing a local synthetic BPLS product. It is not production and does not represent legal municipal authority.

Nothing in this environment:

- collects real money;
- issues a legally effective permit;
- activates municipal fee policy;
- migrates historical taxpayers;
- grants statutory authority;
- changes production, Cloud, historical, or reporting systems.

The accepted Gate 10G specimen is Application 2. Its evidence is protected. Do not create a replacement Application, edit its amounts, reissue its receipts, or “repair” it manually.

### Choose a starting mode

Write the selected mode at the top of the observation log before opening the first staff page:

- **Mode A - read-only baseline review:** inspect the accepted Application 2 evidence only. Its application list may still display the workflow status `Pending Payment` even though the accepted schedule, Collection, receipts, Permit, release, and public verification are complete. Treat that label as a known presentation finding unless the underlying evidence is also wrong; do not stop solely because the label is stale.
- **Mode B - fresh walkthrough from scratch:** use a separately prepared, unsubmitted synthetic Citizen application. Do not use Application 2, Application 291, or another completed specimen as the current transaction. If no fresh specimen identity has been explicitly prepared and authorized, stop with `CURRENT SPECIMEN NOT PREPARED`.

## 2. Before opening the site

1. Obtain the private credential handoff from the owner. It is stored locally at `storage/app/private/gate10h-turnover-handoff.md` and must not be copied into Git, screenshots, tickets, or broad email.
2. Confirm that the URL is exactly `http://bpls-gate10.test/`.
3. Confirm that the browser is not pointed at production, Cloud UAT, the historical environment, or a reporting site.
4. Use the account for the role being reviewed. A shared synthetic password does not grant shared authority.
5. Keep a simple review log: role, page, result, screenshot/reference if privately permitted, and any defect.

## 3. Rules while testing

- Start from the ordinary product navigation.
- Use Dashboard and Inbox / My Work for staff roles.
- Do not use hidden URLs, database tools, Laravel commands, Laboratory, Cleanroom, Storyboards, actor switching, or provisioning controls.
- Do not guess a missing action. If an expected task is absent, stop and record it.
- Do not click a destructive or financial action merely to see what happens.
- Stop at the first genuine product blocker in any commissioned action sequence.
- A browser or viewport-tool failure is a testing limitation, not a product PASS.

## 4. Public and citizen shell

Open the site in a fresh browser window.

### Check the public home

1. Confirm the page identifies the Municipality of Ipil BPLS.
2. Confirm the ordinary choices are visible: Apply, Continue/Track, Staff Login, and Verify.
3. Confirm no Laboratory, Cleanroom, persona selector, actor switcher, or provisioning control is visible.
4. Confirm synthetic/no-legal-effect disclosures remain available where relevant.

### Check citizen access

1. Sign in using the synthetic Citizen account from the private handoff.
2. Confirm the citizen sees citizen navigation only.
3. Confirm the citizen cannot open Staff Inbox, Staff Dashboard, Laboratory, Cleanroom, or provisioning routes.
4. Confirm the citizen shell does not expose another user’s application, payment, receipt, or permit details.
5. Sign out.

## 5. Staff login and Inbox

For each staff role, repeat this sequence with that role’s account:

1. Open Staff Login.
2. Enter the account identifier from the private handoff.
3. Enter the private synthetic password.
4. Confirm the displayed identity and municipal position are correct.
5. Open Dashboard, then Inbox / My Work.
6. Confirm the landing page is ordinary staff work—not an engineering or Laboratory surface.
7. Confirm the visible tasks belong to that role and do not include another office’s work.
8. Open the task through its visible action button.
9. Use the page’s Back to My Work link to return.
10. Sign out before testing the next role.

Required role accounts are listed in the private handoff: BPLO, Assessor, Engineering, Health, MENRO, Treasury, Municipal Treasurer, Cashier, Mayor’s Office, Releasing, and the Citizen account.

## 6. Accepted Application 2 evidence review

Application 2 is the preserved Gate 10G acceptance specimen. Review it as evidence; do not start a new lifecycle action.

Confirm the following facts where the ordinary product makes them visible:

- Collection #1: ₱4,175.00;
- payment schedule: ₱4,175.00 paid and ₱0.00 balance;
- receipts `3333331` through `3333336`;
- six-receipt total: ₱4,175.00;
- four office certifications, each completed once;
- Mayoral Authorization recorded once;
- Permit `BP-2026-0002`;
- public verification identity `BPV-2-c0e4c01a86743f7a`;
- BPLO release completed once.

Do not change any of these records. If the site presents an action that would mutate them, do not click it; record the action and stop.

## 7. Receipt and document checks

Open the permitted receipt/detail surfaces and verify the following representative receipts:

| Receipt | Expected evidence |
|---|---|
| `3333331` | Mayor’s Permit Fee — ₱1,000.00 |
| `3333332` | Laminated ID ₱25.00 + Occupation Fee ₱100.00 — ₱125.00 |
| `3333335` | Health Certificate ₱100.00 + Sanitary Permit Fee ₱200.00 — ₱300.00 |
| `3333336` | Solid Waste Management Fee — ₱2,500.00 |

For each receipt:

1. Confirm the receipt number.
2. Confirm the item rows belong to that receipt only.
3. Confirm the receipt total.
4. Confirm the municipal civil date is September 17, 2026.
5. Open the PDF only through the visible product control.
6. Confirm the PDF shows the same item rows, amount, and date.

Do not void, reissue, edit, or allocate a receipt.

## 8. Permit and public verification checks

Use the completed Application 2 evidence only.

1. Confirm the Permit identity is `BP-2026-0002`.
2. Confirm its verification identity is `BPV-2-c0e4c01a86743f7a`.
3. Confirm the Permit is bound to all six receipt identities.
4. Confirm the bound receipt total remains ₱4,175.00.
5. Open public verification through the visible product link.
6. Confirm public verification succeeds after reload.
7. Confirm public verification does not expose private applicant, document, Collection, Assessment, receipt-detail, or authorization data.
8. Confirm the public page does not claim production legal effect.

## 9. Responsive and keyboard review

When the browser tool supports exact viewport control:

1. Set the viewport to exactly `390×844`.
2. Repeat the representative receipt and public-verification checks.
3. Confirm there is no page-level horizontal overflow.
4. Confirm long receipt numbers, amounts, dates, and action buttons remain readable and reachable.
5. With the mouse removed, tab through the page.
6. Confirm Back, PDF, Print, and relevant links receive visible focus.
7. Confirm no action is reachable only by color or hover.

If the tool cannot apply the exact viewport, record **NOT TESTED — TOOLING LIMITATION**. Do not change the product to accommodate the tool.

## 10. Engineering-isolation review

While signed in as each ordinary role, verify that the visible navigation and direct ordinary workflow pages do not expose:

- Lifecycle Laboratory;
- Cleanroom controls;
- storyboard or specimen loaders;
- actor/persona switching;
- test-actor provisioning;
- database or deployment controls.

Do not probe engineering routes by guessing URLs. A commissioned security test must use its own explicit authorization and test plan.

## 11. How to report a defect

Stop at the first genuine blocker and record:

1. role and account used (never record the password);
2. URL and visible page title;
3. exact action attempted;
4. expected result;
5. actual result and error text;
6. whether the problem is reproducible after reload;
7. whether any record changed;
8. viewport and browser details;
9. whether the issue is product behavior or test-tool failure.

Do not fix the database manually, retry an uncertain financial action, create a replacement specimen, or continue past a genuine blocker without a new bounded authorization.

## 12. Completion checklist

The turnover review is complete when:

- public and citizen boundaries are verified;
- every required staff role can authenticate normally;
- each role lands in its ordinary work surface;
- Application 2 evidence remains unchanged;
- receipt rows, totals, dates, Permit identity, release, and public verification match the accepted baseline;
- responsive and keyboard results are recorded, including any tooling limitation;
- no ordinary surface exposes engineering controls;
- all known deferred items are recorded;
- no secret appears in the run log or shared report.

This runbook is a review aid. It does not authorize production cutover, historical migration, real-user provisioning, policy activation, branch integration, or statutory/legal claims.
