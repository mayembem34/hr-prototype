# Employee and document access scopes

Run `php artisan migrate` after deploying. Existing role permissions are migrated to whole-organization scope, preserving existing access. Role editors can set the scope independently for each employee or document permission: whole organization, own record, a selected unit, or a selected unit and its nested units. Multiple roles combine as a union of their grants. Other permissions remain organization-wide.

Own-record access requires linking an employee profile to a user account in the same organization. Linking is separately controlled by `employees.accounts.assign`; the account selection screen also requires `members.view` and access to the profile. One account can be linked to at most one employee in each organization.

Employee lists, profile access, updates, change history and document operations enforce scopes on the server. To reach document screens, users need matching `employees.view` and `documents.view` grants; document actions also require a matching grant for that action. Creating or updating a profile requires its resulting unit to fall within the create/update scope; own-only create access cannot create a new profile because it has no linked account yet.

To prevent privilege escalation, delegated role managers may only grant or assign a permission if they have that permission across the whole organization. Organization administrators continue to have all organization permissions. Unit access follows the current hierarchy, so moving units changes access to nested-unit scopes. Test organizational changes before using real sensitive records.
