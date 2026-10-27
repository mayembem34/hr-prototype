# Employee records

After pulling this change, run `php artisan migrate`. The migration adds employee records, change history and four default permissions: `employees.view`, `employees.create`, `employees.update` and `employees.history.view`.

Employees belong to one organization. Employee numbers are unique within that organization. A unit and reporting manager are optional and must belong to the same organization. Reporting relationships cannot contain cycles. Employee changes made through these screens are recorded with the acting user and before/after values.

Grant `employees.view` to access employee screens. For editing, also grant `employees.update`; for creating, grant `employees.create`. `employees.history.view` allows a reader to see the change history. `units.view` allows units to be displayed and selected in the form. Employee and document permissions support organization, own-record, unit and nested-unit scopes; see [SCOPED_ACCESS.md](SCOPED_ACCESS.md).

This first employee profile covers basic contact and employment information. Profiles can be linked to user accounts and have private documents. Unit heads, complete employment history and a general audit trail remain outstanding. Avoid using production personal data until the remaining privacy, retention and operational controls are implemented.
