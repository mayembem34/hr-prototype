# Roles and permissions

Run `php artisan migrate` to create the permission catalogue and role tables. Every existing operation has a named permission in the `permissions` table; the migration inserts the default catalogue for both fresh and existing databases. Future operations should add their permissions through new migrations, check the permission before access, and add authorization tests.

Platform administrators have all platform and organization permissions. Organization administrators have all permissions within their organization, including assigning another administrator. Creating organizations remains platform-only. The ability to assign organization administrators is reserved to administrators and cannot be placed in a custom role.

Custom roles belong to one organization. Their permissions combine when a user has multiple roles. Role and assignment managers cannot grant or alter access exceeding their own permissions. To make a read-only role, include `organization.view` and the relevant `*.view` permissions, without write permissions. Employee and document permissions support organization, own-record and unit scopes as described in [SCOPED_ACCESS.md](SCOPED_ACCESS.md). Other permissions apply organization-wide.

The authentication, organization list and sign-out actions have catalogue entries; sign-in is public, sign-out and organization listing require a session. UI sections additionally require corresponding read permissions. To use role-assignment screens, grant role, member and assignment view permissions alongside assignment update.
