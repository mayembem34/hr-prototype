# Employee development records

After deployment run `php artisan migrate`. Development records cover performance reviews, training, qualifications and career development. Each area has separate view, create and update permissions in the default permission list, with organization, own, unit and nested-unit scopes. The page requires its view permission; creation and editing additionally require their respective permissions. Assign roles deliberately: anyone granted view can see all details in records within their scope, including performance notes. The employee's own scope can be used to provide self-service access.

Records track a title, notes, status, dates, provider or issuer and reference. For qualifications, use the due date for expiry. Records are internal tracking tools: this does not provide formal review cycles or approvals, training enrollment or delivery, credential verification, attachments, expiry notifications, or automated career plans. Those processes remain manual.
