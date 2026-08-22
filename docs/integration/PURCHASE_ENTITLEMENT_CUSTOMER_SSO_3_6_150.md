# Purchase Entitlement and Customer SSO Guide

Version: 3.6.150
Date: 2026-08-18

This document explains the minimum integration flow for external services such as NFT market, passport, cart, and school systems.

## Goal

When a customer purchases a product in an external service, sengoku-ai.com should be able to:

1. Resolve or create the common customer ID.
2. Store the purchase and entitlement state.
3. Keep the assigned agency relationship.
4. Issue a customer SSO token so the customer can move to another connected service.

## Standard Flow

1. External service resolves the customer.
2. External service sends purchase completion or entitlement grant.
3. sengoku-ai.com stores the customer entitlement.
4. External service requests a customer SSO token when it needs to move the customer to another connected portal.

## Authentication

Use the API key issued by sengoku-ai.com for the connected site.

Headers:

```http
x-api-key: YOUR_SENGOKU_AI_ISSUED_KEY
Idempotency-Key: stable-event-key
Content-Type: application/json
```

`Authorization: Bearer YOUR_SENGOKU_AI_ISSUED_KEY` is also accepted for compatible endpoints.

## Resolve Customer

Endpoint:

```http
POST /api/common-users/resolve
```

Use this before sending purchase or entitlement data if the external service only has its own user ID.

Minimum body:

```json
{
  "system_key": "sengoku-market",
  "external_user_id": "market-user-123",
  "email": "customer@example.com",
  "display_name": "Customer Name"
}
```

The returned `common_user_id` is the shared customer identifier.

## Save Purchase and Entitlement

Endpoint:

```http
POST /api/integrations/events
```

Recommended event names:

- `purchase.completed`
- `order.completed`
- `payment.succeeded`
- `entitlement.granted`
- `entitlement.revoked`
- `payment.refunded`

Minimum body:

```json
{
  "event": "purchase.completed",
  "system_key": "sengoku-market",
  "external_user_id": "market-user-123",
  "common_user_id": "cu_abc123",
  "project_key": "sengoku-influencer",
  "product_code": "nft-membership",
  "order_id": "order-1001",
  "entitlement_status": "active"
}
```

`product_code` and `order_id` identify the purchased right. Send the same `Idempotency-Key` when retrying the same event.

Entitlement status values:

- `active`
- `pending`
- `revoked`
- `expired`

If `entitlement_status` is omitted, purchase completion events are treated as `active`, and refund/revoke events are treated as `revoked`.

## Customer SSO Token

Endpoint:

```http
POST /api/sso/customer-token
```

Use this when an external service needs a signed JWT for a customer.

Minimum body:

```json
{
  "client_key": "sengoku-passport",
  "common_user_id": "cu_abc123",
  "return_to": "https://example.com/mypage"
}
```

Alternative lookup by external user:

```json
{
  "client_key": "sengoku-passport",
  "system_key": "sengoku-market",
  "external_user_id": "market-user-123"
}
```

The response includes:

```json
{
  "ok": true,
  "token_type": "Bearer",
  "expires_in": 120,
  "sso_token": "JWT...",
  "common_user_id": "cu_abc123",
  "client_key": "sengoku-passport",
  "jwks_url": "https://sengoku-ai.com/api/sso/jwks.php"
}
```

## JWT Contents

The customer SSO JWT is signed with the existing SSO signing key.

Main claims:

- `iss`: sengoku-ai.com issuer URL
- `sub`: common customer ID
- `common_user_id`: common customer ID
- `aud`: target connected site audience
- `actor_type`: `customer`
- `display_name`
- `wallet_address`
- `system_links`
- `agency_relations`
- `entitlements`
- `iat`, `exp`, `jti`

The target service should verify the JWT using the JWKS endpoint.

## Notes for Operation

- Apply DB migrations after uploading the update ZIP.
- If API scopes are restricted, allow `sso:issue` for partners that call `/api/sso/customer-token`.
- Do not use the internal agency numeric ID for integration. Use stable keys such as `common_user_id`, `system_key`, `external_user_id`, and `agent_code`.
