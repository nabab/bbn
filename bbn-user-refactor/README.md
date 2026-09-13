# bbn/user refactor

This is a migration-oriented package layout that keeps the public class name `bbn\User`.

## Layout

- `bbn\User`: public facade and compatibility surface
- `bbn\User\State`: shared mutable runtime state
- `bbn\User\Sessions`: user/session namespace access
- `bbn\User\Auth`: login workflow
- `bbn\User\Passwords`: hashing and password persistence
- `bbn\User\Tokens`: token persistence
- `bbn\User\Profile`: identity/profile loading
- `bbn\User\Caches`: filesystem cache
- `bbn\User\Locales`: per-user SQLite database

## Important

The supplied `User` class contains several thousand lines and many implicit contracts. This package is a concrete refactoring foundation, not a claim of drop-in behavioral parity yet.

Before replacing production code:

1. Copy the complete existing `$default_class_cfg` into `src/User.php`.
2. Move the original bodies marked by comments into their target components.
3. Add characterization tests against the old class.
4. Run old and new implementations against the same session/database fixtures.
5. Replace `Common` only after all its methods have owners.

## Recommended extraction order

1. `Caches`, `Locales`, `Passwords` — low coupling.
2. `Tokens`, `Profile` — moderate coupling.
3. `Sessions` — high coupling and persistence semantics.
4. `Auth` and constructor routing — highest risk.

## Why composition

Inheritance would create several partial kinds of user and make state synchronization harder. Components keep `bbn\User` as the single domain object while making implementation classes independently testable.
