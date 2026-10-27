# Organization structure

Run `php artisan migrate` to install unit heads, structure history and their default permissions. Administrators can assign an employee from the organization as a unit head. Leadership is an organizational designation and does not grant a user any role or access permissions.

`units.view` allows the list and organization chart. `units.heads.view` additionally allows head information; names are displayed only when the viewer's scoped `employees.view` grant covers the employee. `units.heads.assign` controls head assignments. To select a head through the interface, the user also needs `units.heads.view` and an applicable `employees.view` grant. `units.history.view` allows recent unit structure history, and head changes are shown only with `units.heads.view`.

The system records unit creation, rename, parent changes and head changes made after this feature is enabled. The page displays the latest 100 entries. Head history stores employee references and displays names only within the viewer's employee scope. Unit hierarchy changes can affect nested-unit permissions and should be reviewed accordingly.
