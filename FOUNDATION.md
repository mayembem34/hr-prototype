# Foundation

The prototype supports organizations, administrators, nested units, branding settings, custom organization roles and operation permissions. See [ROLES.md](ROLES.md) for permissions and their current scope. Employee profiles, private documents and scoped access are available; a general audit trail remains outstanding. Branding colors are saved but not applied to the interface. This is an early prototype, not production-ready.

After configuring MySQL and installing dependencies as described in README, run:

```bash
php artisan migrate
php artisan storage:link
php artisan hr:platform-admin admin@example.com "Platform Admin"
php artisan serve
```

The command prompts for a password. Sign in, create an organization, then assign an organization administrator. Existing accounts retain their passwords. No public self-registration is enabled.

Run tests with `php artisan test`. GitHub Actions runs them against SQLite and MySQL. Organization administrators can assign additional administrators; removal and downgrade of administrators are not yet available.
