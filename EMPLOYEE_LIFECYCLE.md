# Employee lifecycle

Run `php artisan migrate` after deployment. Recruitment permissions apply organization-wide; lifecycle case and task permissions support the existing employee scopes. Self-service permissions apply only to the signed-in employee's active organization membership.

Authorized staff can record vacancies and candidate applications, schedule interviews, record offers and mark an offer accepted. Hiring requires an accepted offer and a candidate email. If the email does not already have an account, the system creates one and sends a password invitation using the configured mail transport. Otherwise it links the existing account. The application becomes hired, and an onboarding case with suggested tasks is created when the employee profile exists. Invitations are not delivered when the default log mail transport is used.

Staff can start onboarding, probation and offboarding cases with a suggested checklist, add tasks and record probation outcomes. The employee can view and complete tasks assigned to them and receive in-system lifecycle notifications. All checklist tasks must be completed before a case closes.

Finalizing offboarding requires the separate scoped `lifecycle.offboarding.finalize` permission. It marks the employee as left and deactivates their membership and removes assigned roles in that organization only. It blocks self-offboarding, platform administrators and removal of the last active organization administrator. Other organization memberships remain active. An administrator can reactivate a membership; its previous custom roles are not automatically restored.

Interview and offer details are internal records: public applications, candidate self-service offer acceptance, formal interview evaluation and offer-approval workflows are not yet implemented. Lifecycle notifications are in-system only; interview invitations, background reminders, payroll settlement and external service deprovisioning are not yet automated. Completing a checklist item is a recorded acknowledgement, not verification that an external action occurred.
