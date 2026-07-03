# 18 — Authentication & multi-user (Phase 4) · Autenticación y multiusuario

Goal / Objetivo: real accounts — each user sees only their own data.

> This is the biggest structural change: every query, service and endpoint is now **scoped to the logged-in
> user**. / Es el cambio estructural más grande: cada consulta, servicio y endpoint queda **acotado al
> usuario logueado**.

---

## Backend

### 1. The `User` entity + security
- `User` implements `UserInterface` + `PasswordAuthenticatedUserInterface` (email as identity, hashed
  password, roles). `UserRepository` also implements `PasswordUpgraderInterface`.
- `composer require symfony/security-bundle`; `config/packages/security.yaml` configures an **entity
  provider** (users by email), a **session-based `json_login`**, logout, and access control.

```yaml
firewalls:
  main:
    json_login: { check_path: api_login, username_path: email, password_path: password }
    logout: { path: api_logout }
access_control:
  - { path: ^/api/(login|register|me|health), roles: PUBLIC_ACCESS }
  - { path: ^/api, roles: ROLE_USER }
```

### 2. Endpoints
- `POST /api/register` — creates a user (password hashed with `UserPasswordHasherInterface`).
- `POST /api/login` — handled by the firewall; the controller returns the user via `#[CurrentUser]`.
- `GET /api/me` — the current user, or 401.
- `POST /api/logout` — cleared by the firewall.

### 3. Scoping every query to the user
- `Transaction` gains a `ManyToOne User`; imports set it.
- `TransactionRepository::findForUser($user)`; every service (`VatService`, `IrpfService`,
  `ForecastService`, `ChatService`, `CategorizerService`) and the stats SQL now filter by the current
  user, passed down from each controller via `#[CurrentUser] User $user`.

- **EN:** This is the security core of any SaaS: data isolation enforced in the query, so one account can
  never see another's transactions.
- **ES:** Este es el núcleo de seguridad de cualquier SaaS: el aislamiento de datos se impone en la
  consulta, para que una cuenta nunca vea los movimientos de otra.

## Frontend

- `AuthContext` (`useAuth`): holds the user, calls `/api/me` on load, and exposes `login` / `register` /
  `logout`. `App` **gates** everything — logged-out users get the `AuthPage` (login/register); logged-in
  users get the app. The navbar shows the email and a **Log out** button.
- Cookies flow automatically because, in dev, the Vite proxy makes frontend and backend the **same origin**.

## Tested & verified

- Unit tests updated to the new signatures (pass a `User`, stub `findForUser`). `php bin/phpunit` →
  **OK (11 tests, 37 assertions)**.
- End-to-end (session cookies): register → login → `/api/me` → import (scoped) → a user sees **only their
  own** transactions; logged-out requests to `/api` return **401**.

> **Deploy note / Nota de deploy:** with the frontend and backend on **different domains** (e.g. Vercel +
> Railway), session cookies need `SameSite=None; Secure` and CORS with credentials. That's configured at
> deploy time. / Con frontend y backend en **dominios distintos**, las cookies de sesión necesitan
> `SameSite=None; Secure` y CORS con credenciales, que se configuran en el deploy.

---

**Next / Siguiente:** production hardening + the live deploy.
/ endurecimiento de producción y el deploy en vivo.
