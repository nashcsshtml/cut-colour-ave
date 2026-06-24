# Provider / Employee Mobile App Authentication

How a mobile app authenticates an **employee (provider)** with email + password and
makes authenticated, per-provider–scoped requests against the Amelia backend.

> **No code change is required.** This flow already exists and is enabled by default.
> It deliberately does **not** use the external `/api/v1/*` API-key surface — that key
> represents the site admin and is unscoped (it would grant cross-provider/admin access).
> The provider Cabinet flow below is scoped to the authenticated provider by construction.

## Why not the API-key API?

The `/api/v1/*` routes (`src/Infrastructure/API/ApiRoutes/**`) are guarded by
`Api::callMainFunction` (`src/Infrastructure/API/Api.php`), which checks the static
`Amelia` API key. A valid key only causes the per-request CSRF nonce to be skipped
(`Controller.php` — `$validApiCall`); it does **not** scope the caller to a user. A
provider authenticated this way would see every provider's data. Wrong model for a
mobile app, and it would mean shipping a shared admin secret inside the app.

The **Cabinet** flow below issues a per-provider JWT instead.

## 1. Log in

```
POST {site}/wp-admin/admin-ajax.php?action=wpamelia_api&call=/users/authenticate
Content-Type: application/json

{
  "email": "employee@example.com",
  "password": "••••••••",
  "cabinetType": "provider"
}
```

- **No `Amelia` API key required** — `/users/authenticate` is a Cabinet (Starter-tier)
  route, not part of the key-gated API set.
- **No CSRF nonce required** — `LoginCabinetCommand` is explicitly nonce-exempt
  (`src/Application/Commands/Command.php`, `validateNonce`).
- `cabinetType: "provider"` is what makes this **employees only** — see §3.

### Success response

```json
{
  "message": "Successfully",
  "data": {
    "user":  { "...": "full provider object", "type": "provider" },
    "is_wp_user": false,
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

- `data.token` is a JWT signed with the **provider cabinet's `headerJwtSecret`**
  (`UserApplicationService::getAuthenticatedUserResponse`). Store it securely on device
  (Keychain / Keystore).
- Token lifetime = `providerCabinet.tokenValidTime` (Amelia → Settings → Roles). Re-run
  the login call to obtain a fresh token; there is no separate refresh endpoint for the
  header flow.

### Failure

`data.invalid_credentials: true` → wrong email/password, or the account is not allowed
into the provider cabinet (see §3).

## 2. Make authenticated requests

Send the token as a Bearer header on every subsequent call:

```
GET {site}/wp-admin/admin-ajax.php?action=wpamelia_api&call=/appointments
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

- The header is read in `Command::setToken` and gated by the `enabledHttpAuthorization`
  role setting, which **defaults to `true`** (`ActivationSettingsHook.php`). Confirm it
  is on at Amelia → Settings → Roles if requests come back unauthorized.
- The backend resolves the token (`UserApplicationService::getAuthenticatedUser`,
  validated against the same `headerJwtSecret`) and scopes the request to that provider
  via `authorization($token, 'provider')`. The employee only sees their own
  appointments / schedule.

Two non-obvious rules a mobile client must follow:

- **Always send `?source=cabinet-provider`** (a query param, even on POSTs). This is what
  flips the handler into cabinet mode (`isCabinetPage = getPage() === 'cabinet'`) and
  engages per-provider scoping. Without it the handler ignores the token and the request
  is treated as an unscoped dashboard call. The web Employee Panel sends exactly this
  (see `v3/src/views/public/Cabinet/**`, `source: 'cabinet-' + cabinetType`).
- **Auth failures are NOT HTTP 401.** An expired/invalid token yields `RESULT_ERROR` with
  `data.reauthorize = true`, which `Controller::__invoke` maps to HTTP **409/500**. Key
  your re-login off the `data.reauthorize` body flag, not the status code (the web panel
  does: `error.response.data.data.reauthorize → logout`).

## 2a. Cabinet data endpoints (use these, not `/api/v1/*`)

The `/api/v1/*` routes require the admin API key and won't accept the JWT. Use the cabinet
(no-prefix) routes instead. All take `Authorization: Bearer <token>` + `?source=cabinet-provider`.

| Operation                | Method & path                          | Body         | Scoping |
| ------------------------ | -------------------------------------- | ------------ | ------- |
| List appointments        | `GET /appointments`                    | —            | server forces the provider's own id; **do not send `providerId`** |
| Appointment detail       | `GET /appointments/{id}`               | —            | provider-checked |
| Update appt. status       | `POST /appointments/status/{id}`       | `{ status }` | provider-checked; returns new status in `data.status` |
| List events              | `GET /events`                          | —            | provider-scoped |
| Event detail             | `GET /events/{id}`                     | —            | provider-scoped |

### Pagination (for infinite scroll / load-on-scroll)

Both list endpoints paginate server-side, but with **different** param names, page-size
sources, and total fields:

| | `GET /appointments` | `GET /events` |
| --- | --- | --- |
| Page param | `page` (1-based) | `page` (1-based) |
| Page size | server-fixed (`general.itemsPerPage`); **client sends no limit** | `limit` — **client sends it** |
| Items in response | `data.appointments` — object **grouped by date** (`{ "YYYY-MM-DD": { date, appointments[] } }`) | `data.events` — **flat array** |
| Total for stop-detection | `data.total` (≡ `data.filteredCount`) | `data.count` |
| Unfiltered total | `data.totalCount` | `data.countTotal` |
| Free-text search | `search` (matches customer name/email/phone + service name) | `search` (matches event name) |

Notes:
- **You must send `page`** to engage the `LIMIT`. With no `page`, the query returns
  *everything* (no limit) and `total` stays `0` — so always paginate.
- `data.total` is a real non-null count on every success path when `page` is sent
  (`GetAppointmentsCommandHandler` line 409 gate + line 467 assignment). The only response
  without it is the auth-error path (`data.reauthorize`), which a scroll loop must treat as
  re-auth, not "load more". Safe stop signal: **accumulated items ≥ `total`**.
- ⚠️ `total` is marked `// TODO: Redesign - remove total` in `GetAppointmentsCommandHandler`.
  `data.filteredCount` carries the **identical value** (line 471) and has no removal TODO,
  so the mobile client reads `data.total ?? data.filteredCount` (see `lib/api.ts`). If that
  refactor lands, the app keeps working off `filteredCount`. There is no PHPUnit test pinning
  this shape (it's built deep in the handler); the mobile **live contract test** is where the
  field's presence + numeric-ness should be asserted.
- `getLimit()` computes offset as `(page - 1) * itemsPerPage` (`AbstractRepository::getLimit`).
- The web Employee Cabinet uses classic numbered pagination, not scroll; the mobile app
  reconstructs the same totals into an infinite-scroll accumulator.

**Not available over the provider JWT** (the handlers authorize via the WordPress session /
capabilities, with no token path): **delete appointment, update event status, delete event**.
Exposing them safely would require adding token-based provider-ownership checks to those
handlers (an IDOR risk if done naively) — out of scope for this auth model. Manage those
from the web dashboard, or treat as a separate, deliberate plugin change.

## 2b. Version negotiation (`/mobile/info`)

Different sites run different plugin builds, so the app negotiates compatibility
**before** it relies on the `/mobile/v1/*` contract. The plugin exposes one tiny,
**unversioned, unauthenticated** handshake:

```
GET {site}/wp-admin/admin-ajax.php?action=wpamelia_api&call=/mobile/info
→ 200 { "message": "success",
        "data": { "pluginVersion": "9.5", "mobileApi": { "min": 1, "max": 1 } } }
```

- `mobileApi.{min,max}` = inclusive range of mobile-API **contract** versions this
  build serves (an integer, independent of the plugin semver). Source of truth:
  `GetMobileInfoController::MOBILE_API_MIN / MOBILE_API_MAX`.
- `pluginVersion` = `AMELIA_VERSION`, informational only.
- It is intentionally outside `/mobile/v1/` and requires no token — it is the thing
  used to *detect* breaking changes, so it must never change shape or require auth.
  `GetMobileInfoController` extends the base `Controller` (not `MobileV1Controller`)
  and returns its payload directly, bypassing the command pipeline.

The app declares the single contract version it was built for and compares:

| Condition | Verdict | User action |
| --------- | ------- | ----------- |
| `appVersion > mobileApi.max` | plugin too old | **update the plugin** |
| `appVersion < mobileApi.min` | contract dropped (breaking change) | **update the app** |
| in range | compatible | — |
| route 404s (`{"message":"Not found."}`, no `data`) | predates `/mobile/info` | **update the plugin** |
| WP returns literal `0` / non-JSON | Amelia absent/inactive or wrong URL | connection error (not an update) |
| network failure | unknown | **fail open** — never blocks |

These response shapes are verified live (handshake `200` JSON unauthenticated;
unknown route `404 {"message":"Not found."}` with no `data` via `AmeliaErrorHandler`;
no-Bearer `/mobile/v1/*` → `409 {reauthorize:true}`).

> **Release invariant:** `/mobile/info` MUST ship in the same release as the
> `/mobile/v1/*` routes — otherwise the release that introduces mobile support
> fails its own compatibility check.

> **Bump protocol:** raise `MOBILE_API_MAX` for a new additive contract version;
> raise `MOBILE_API_MIN` only when deliberately dropping support for an old app
> contract (this forces those app builds to update).

## 3. Employee-only enforcement

The login rejects anyone whose Amelia type is not `provider` (admins are also allowed
through, as on web):

```php
// UserApplicationService::getAuthenticatedUserResponse
if ($user->getType() !== $cabinetType && $user->getType() !== AbstractUser::USER_ROLE_ADMIN) {
    // -> invalid_credentials
}
```

So with `cabinetType: "provider"`, a customer can never authenticate. Managers
(WP-account based) are handled through the WordPress-session path, not the email/password
path — a pure email/password mobile login returns either a `provider` or an `admin`.

## 4. Determining the role

Read **`data.user.type`** from the login response. It is one of four string values
(`AbstractUser` constants):

| `data.user.type` | Meaning            | WordPress role        |
| ---------------- | ------------------ | --------------------- |
| `admin`          | Site administrator | `administrator`       |
| `manager`        | Amelia manager     | `wpamelia-manager`    |
| `provider`       | Employee           | `wpamelia-provider`   |
| `customer`       | Customer           | `wpamelia-customer`   |

The mapping from WordPress role → Amelia role is in
`UserRoles::getUserAmeliaRole` (`src/Infrastructure/WP/UserRoles/UserRoles.php`),
resolved in priority order: `administrator` → `manager` → `provider` → `customer`
(a `super_admin` also resolves to `admin`).

For the mobile employee app you will normally only ever see `provider` (regular
employee) or `admin` (an admin using the employee panel). Branch the UI on
`data.user.type` if admins should see additional capabilities.

## Source references

| Concern                       | Location |
| ----------------------------- | -------- |
| Login route (no key/nonce)    | `src/Infrastructure/Routes/Cabinet/Cabinet.php` → `/users/authenticate` |
| Nonce exemption               | `src/Application/Commands/Command.php` → `validateNonce` |
| Token issued (in body)        | `src/Application/Services/User/UserApplicationService.php` → `getAuthenticatedUserResponse` |
| Bearer header read            | `src/Application/Commands/Command.php` → `setToken` (`enabledHttpAuthorization`) |
| Token validated + scoped      | `src/Application/Services/User/UserApplicationService.php` → `getAuthenticatedUser` / `authorization` |
| Employee-only check           | `getAuthenticatedUserResponse` (`type !== cabinetType`) |
| Role constants / WP mapping   | `src/Domain/Entity/User/AbstractUser.php`, `src/Infrastructure/WP/UserRoles/UserRoles.php` |
| Cabinet data routes           | `src/Infrastructure/Routes/Booking/**` (`/appointments`, `/events`, `/appointments/status/{id}`) |
| `source=cabinet` scoping      | `src/Application/Commands/Booking/Appointment/GetAppointmentsCommandHandler.php` (`isCabinetPage`, provider id pinning) |
| `reauthorize` (not 401)       | `src/Application/Controller/Controller.php` → `__invoke` (maps RESULT_ERROR to 409/500) |
| Version handshake             | `src/Application/Controller/Mobile/GetMobileInfoController.php`, route in `src/Infrastructure/Routes/Mobile/MobileV1.php` |
| Routing 404 shape             | `src/Infrastructure/Common/AmeliaErrorHandler.php` (`{message}` only, no `data`) |
| Reference client              | `amelia-mobile-app` repo → `lib/api.ts` + `context/AuthContext.tsx` (+ `__tests__/api.test.ts`) |

## Regression guard

- **Plugin:** `tests/phpunit/Application/Commands/LoginNonceExemptionTest.php` locks the
  nonce exemption that lets the mobile app log in without a nonce/API key. Runs in CI via
  the existing `phpunit` step in `bitbucket-pipelines.yml`.
- **Plugin:** `tests/phpunit/Application/Controller/Mobile/GetMobileInfoControllerTest.php`
  pins the `/mobile/info` handshake payload (success + integer `mobileApi.min/max`) the app
  compares against.
- **Mobile app:** `__tests__/api.test.ts` (`npm test`) locks the security-critical client
  invariants: never sends the admin `Amelia` key, always sends `source=cabinet-provider`,
  never sends a client `providerId`, and re-logs-in on the `reauthorize` body flag.
