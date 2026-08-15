---
paths:
  - 'app/Models/{User,BusinessOwner,Business,PermitApplication}.php'
---

# Models

## Citizen identity is separate from submission
Use the nullable durable User -> BusinessOwner -> Businesses link for legal registry identity. Keep PermitApplication.submitted_by_id as the actor/audit fact; never infer legal ownership from prior applications.
