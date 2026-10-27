# Foundation: first slice

This branch adds platform administrators, organization administrators, organization records, nested units, branding settings and basic help. Custom roles and permission scopes, employees, documents, audit trails, organization switching for regular staff, and the remaining planned features are not implemented yet. Branding colors are stored but are not yet applied to the interface; a contrast-safe preview and defaults reset still need to be added. This is an early prototype, not production-ready.

After configuring MySQL and installing dependencies as described in README, run:

```bash
php artisan migrate
php artisan storage:link
php artisan hr:platform-admin admin@example.com "Platform Admin"
php artisan serve
```

The command prompts for a password. Sign in, create an organization, then assign an organization administrator. Existing accounts can be assigned by email without changing their passwords. Store real credentials outside source control.

Run tests with `php artisan test`. GitHub Actions runs them with SQLite. MySQL behavior requires separate testing. Organization administrators cannot currently remove or downgrade administrators; platform administrators can assign them. No public self-registration is enabled.
