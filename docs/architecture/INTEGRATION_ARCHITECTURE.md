# Integration Architecture

Architecture date: 2026-08-13

Unknown integrations remain explicit seams.

## Payment Integration

Status from Discovery: partial or missing. Citizen payment is described as mock/no live gateway.

Architecture:

- define a `PaymentGateway` contract for initiating online payments, receiving callbacks, querying status, and normalizing references;
- define a `PaymentReconciliationService` boundary for matching gateway events to Treasury collections;
- persist gateway events separately from accepted collections;
- process webhooks through signed, rate-limited, idempotent endpoints;
- keep OTC/manual payment recording independent of gateway availability.

Do not choose a gateway until owner policy and LGU approval are known.

## Notifications

Status from Discovery: TOR requires notifications; SMS/email implementation not confirmed.

Architecture:

- use Laravel notifications behind named notification types;
- queue outbound email/SMS;
- record notification attempts and delivery status when the channel supports it;
- support database/in-app notifications first if schedule requires;
- add SMS/mail providers through contracts/configuration.

## Treasury Interactions

Status from Discovery: internal Treasury collection is in scope; external Treasury systems are not clearly evidenced.

Architecture:

- model Treasury collections inside Laravel as canonical runtime behavior;
- isolate any future external Treasury export/import behind `TreasuryIntegration` contracts;
- keep daily collection and abstract outputs as reports/artifacts.

## Public Permit Verification

Architecture:

- public signed/opaque verification route;
- minimal public data response;
- QR tokens linked to issued permits;
- no exposure of private application/payment data.

## Report Exports

Architecture:

- report request creates `report_run`;
- queued job builds full result;
- output saved to storage;
- user downloads artifact through authorized route;
- failed jobs record reason and are retryable when safe.

## Document Rendering

Architecture:

- document request creates or references a document artifact;
- renderer receives document type and data context;
- output saved to storage;
- rendering technology remains replaceable.

Known possible renderers:

- HTML-to-PDF adapter;
- server-side PDF library;
- spreadsheet/CSV writer for reports.

No renderer is selected in this phase.

## Future Government / LGU Integrations

Status: TOR mentions integration-ready architecture and possible government systems.

Architecture:

- do not build speculative integrations;
- reserve integration records and contracts where a real endpoint appears;
- record outbound/inbound payloads with correlation IDs when integrations are added.
