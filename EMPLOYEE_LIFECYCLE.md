# Employee lifecycle: initial workflow

Run `php artisan migrate` after deployment. New recruitment and lifecycle permissions are installed by migration. Recruitment permissions apply organization-wide; lifecycle case and task permissions support the existing employee scopes.

Recruitment can record vacancies and applications, including candidate contact, notes and application stages. Applications are entered by authorized staff; there is no public application portal, interview scheduling, offers, automated hiring or candidate-document upload yet.

Onboarding, probation and offboarding are tracked as employee cases with start/due dates, notes and manually created checklists. Probation completion requires an outcome. Open checklist tasks must be completed before a case can be closed. Completing an offboarding case does not change employee status, revoke accounts or settle final pay. Those must be handled separately. Case history is restricted by the lifecycle history permission and employee scope.

This is a foundation for the lifecycle; automatic checklists, notifications, approvals, reviews, sensitive-case controls, employee self-service and integrations remain to be developed before it is a full HR lifecycle module.
