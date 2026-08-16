---
paths:
  - 'app/{Actions,Http/Controllers/Staff,Models}/**'
---

# Staff Models

## Distinguish effective access from assignments
Authorization projections must distinguish stored role-permission assignments from effective access granted by the Admin runtime override. Surface permission-catalog drift explicitly; never present override access as if it came from an assignment row.
