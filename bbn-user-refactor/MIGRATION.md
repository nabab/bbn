# Method ownership map

## Sessions

`_init_session`, `_get_session`, `_set_session`, `_retrieve_session`, `_sess_info`, `getSession`, `setSession`, `unsetSession`, `getOsession`, `setOsession`, `hasSession`, `saveSession`, `closeSession`, `updateActivity`, `getSessionDbId`, `getSalt`, `checkSalt`.

## Auth

`isLoginRequest`, `_check_credentials`, `_login`, `_authenticate`, `logIn`, `recordAttempt`, `checkAttempts`, `checkSession`, `logout`.

## Passwords

`_hash`, `_check_password`, `getPassword`, `setPassword`, `forcePassword`, reset-password request handling.

## Tokens

`addToken`, `getIdByAccessToken`, API token/device lookup and update methods.

## Profile

`_user_info`, `refreshInfo`, `updateInfo`, configuration access, name/email/group/info access.

## Caches

All cache methods.

## Locales

`getLocaleDatabase`.

## Remaining focused components to add

- `Phones`: phone parsing and verification workflows
- `Hotlinks`: magic strings, expiry and password links
- `Permissions`: permission account/token persistence
- `Crypto`: user encryption key, crypt/decrypt
- `Paths`: user data/tmp directory setup
- `Mailer`: configured mailer creation
