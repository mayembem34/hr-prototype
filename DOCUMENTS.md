# Employee documents

Run `php artisan migrate` after deploying. Employee document metadata, action history and the default document permissions are installed by the migration.

Documents are stored on Laravel's `local` disk, which defaults to `storage/app/private`. Keep this location outside the web server's public document root; do not link it into public storage. Files are served only through authenticated, permission-checked routes. The application accepts PDF, JPEG, PNG and DOCX up to 10 MB; PDF and image previews are sandboxed. Archived files remain on disk and must be restored before preview or download. Back up the private storage directory together with the database.

Document access requires `employees.view` plus the specific document permission. `documents.view` exposes the list, and `documents.upload`, `documents.preview`, `documents.download`, `documents.archive`, `documents.restore` and `documents.audit.view` control the respective actions. A practical role also needs `documents.view` to reach the list and action controls. Employee and document permissions support scoped access; see [SCOPED_ACCESS.md](SCOPED_ACCESS.md). Separate sensitive document category controls are not implemented.

Upload, preview and download requests, archive and restore actions are logged with actor and time. A preview/download request log does not prove the file was received. This history is not a complete, tamper-proof audit system. Retention, malware scanning, and general audit and recovery controls remain to be implemented before production use.
