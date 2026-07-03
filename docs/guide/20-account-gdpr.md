# 20 — Account & GDPR (polish) · Cuenta y RGPD

Goal / Objetivo: let users clear their data or delete their account (right to erasure), plus a privacy note.

---

## Backend

`AccountController`:
- `POST /api/account/clear` — deletes all of the current user's transactions (keeps the account). Useful to
  reset the demo.
- `DELETE /api/account` — deletes the user's transactions **and** the user, then **invalidates the session**
  (GDPR art. 17, right to erasure).

```php
$em->createQuery('DELETE FROM '.Transaction::class.' t WHERE t.user = :u')->setParameter('u', $user)->execute();
$em->remove($user); $em->flush();
$request->getSession()->invalidate();
```

- **EN:** A bulk DQL `DELETE` removes the rows in one query; then the `User` is removed. Data isolation from
  the auth work means "delete my data" is a simple, safe `WHERE user = me`.
- **ES:** Un `DELETE` masivo en DQL borra las filas en una consulta; luego se elimina el `User`. El
  aislamiento de datos de la auth hace que "borrar mis datos" sea un `WHERE user = yo` simple y seguro.

## Frontend

- An **Account** page (`/account`, reached by clicking the email in the navbar) with three cards: **clear my
  data**, **delete account** (a red danger button), and a short **privacy** note. Both destructive actions
  ask for confirmation.
- `AuthContext` gains `deleteAccount()`, which calls the endpoint and clears the user (→ back to the login
  screen).

## Verify

```powershell
POST /api/account/clear   # → { "cleared": 15 }   (transactions drop to 0)
DELETE /api/account       # → { "deleted": true } (account gone; session invalidated)
```

- **EN:** After deletion the session is invalidated; the app returns to the login screen. A stale request
  from a deleted account is treated as logged-out by the frontend.
- **ES:** Tras borrar, la sesión se invalida; la app vuelve al login. Una petición obsoleta de una cuenta
  borrada se trata como "sin sesión" en el frontend.

---

**Next / Siguiente:** UX & responsive polish, then API integration tests, then deploy.
/ Pulido UX y responsive, luego tests de integración de la API, y luego deploy.
