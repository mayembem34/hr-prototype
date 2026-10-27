# HR prototype

Laravel application foundation for the planned multi-organisation HR management system.

The HR features have not yet been implemented.

## Local setup

Install PHP and Composer, Node.js and npm, and MySQL. Create a MySQL database and user, then:

```bash
composer install
cp .env.example .env
```

Set the MySQL `DB_*` values in `.env`, then run:

```bash
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

The source scaffold is based on the official Laravel 13 application skeleton. Dependencies have not been installed or tested in the creation environment.
