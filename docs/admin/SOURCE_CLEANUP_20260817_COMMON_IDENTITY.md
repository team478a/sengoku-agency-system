# Source Cleanup: Common Identity Relation Helper

Date: 2026-08-17

## Summary

Removed an unused legacy helper from `includes/functions.php`.

The active `saveAgencyCustomerRelation()` function now delegates to:

`SenNoKuni\CommonIdentity\AgencyCustomerRelationRepository`

## Removed

- `saveAgencyCustomerRelationLegacyBody()`

This function was not referenced by active code and duplicated the newer repository-based implementation.

## Impact

- DB changes: none
- API changes: none
- UI changes: none
- Migration required: no

## Verification

- `php -l includes/functions.php`: OK
- Confirmed `saveAgencyCustomerRelationLegacyBody` is no longer present
- Confirmed `saveAgencyCustomerRelation` remains present

