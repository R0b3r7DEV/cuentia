# 42 — Password reset · Recuperar la contraseña

Goal / Objetivo: let a user who forgot their password choose a new one through a link sent by e-mail,
without opening a way to spam people, to find out who has an account, or to take over accounts.

*Objetivo: que quien olvide su contraseña elija una nueva con un enlace enviado por correo, sin abrir la
puerta al spam, a averiguar quién tiene cuenta ni a robar cuentas.*

---

## Flow / Flujo

1. Login screen → **«¿Has olvidado la contraseña?»** → e-mail → `POST /api/password/forgot`.
2. The answer is **always the same** (`202 reset_requested`), whether the account exists or not.
3. If it exists, an e-mail with `FRONTEND_URL/restablecer#token=…` is sent (Brevo). Valid **1 hour**, **once**.
4. The link opens the "new password" form (twice, ≥ 8 characters) → `POST /api/password/reset` → log in.

## Security decisions / Decisiones de seguridad

| Threat / Amenaza | Defence / Defensa |
|---|---|
| Someone reads the database | Only the **SHA-256** of the token is stored; the token itself only travels in the e-mail. |
| Guessing a token | 256 random bits (`random_bytes(32)`), plus a limit of 10 tries / 15 min per IP. |
| Finding out who has an account | Same status and body for known and unknown e-mails; delivery errors are logged, never returned. |
| Spamming a victim's inbox | Max 3 requests / hour per e-mail and 5 / 15 min per IP (`symfony/rate-limiter`), then 429 + `Retry-After`. |
| An old link found later | Expires in 1 hour; asking again kills the previous link; using one deletes all pending ones. |
| Token leaking through logs | It goes after the `#`, which browsers never send to a server (not in access logs, proxies or `Referer`); the page removes it from the address bar as soon as it reads it. |
| Someone already logged in elsewhere | Changing the password ends the other sessions: Symfony compares the stored password hash on each request. |

E-mail lookup is case-insensitive (registration stores the address as typed).

## Configuration / Configuración

| Variable | Example | |
|---|---|---|
| `MAILER_DSN` | `brevo+api://API_KEY@default` | Brevo → *SMTP & API* → *API keys*. Locally `null://null` (nothing is sent). |
| `MAILER_FROM` | `Cuentia <no-reply@yourdomain>` | Must be a **sender verified in Brevo** (*Senders, Domains & Dedicated IPs*). |
| `FRONTEND_URL` | `https://cuentia.vercel.app` | Where the link points. |

All three are declared in `render.yaml` (`sync: false`): fill them in Render → service → *Environment*.
For local testing with real delivery, `compose.override.yaml` includes Mailpit (`smtp://localhost:1025`).

## Tests

`tests/Api/PasswordResetTest.php`: full flow (e-mail, hashed storage, new password works, old one does not,
single use), same answer for unknown e-mails and no e-mail sent, expired token, a new link invalidates the
previous, weak password / empty / unknown token, rate limit → 429, other sessions logged out, endpoints public.
