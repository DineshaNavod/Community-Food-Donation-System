# XAMPP installation and troubleshooting

## Already installed on this computer

The project is in `C:\xampp\htdocs\Community Food Donation System`, and the database
is `food_donation_db`. Start Apache and MySQL, then open:

http://localhost/Community%20Food%20Donation%20System/

The administrator and demonstration accounts already exist. Do not recreate the
administrator or delete/reimport the database to start the application.

## Install on another Windows computer

### 1. Prepare PHP and Apache

Use XAMPP with PHP 8.2+ and MariaDB/MySQL supporting InnoDB and generated columns.
The project was verified with PHP 8.2.12 and MariaDB 10.4.32. Required PHP extensions
are `pdo_mysql`, `mbstring`, `fileinfo`, `session` and `gd`. The automated HTTP tests
also require `curl`; the full runner and concurrency tests require PHP process functions.

Copy the complete project folder to:

```text
C:\xampp\htdocs\Community Food Donation System
```

Keep the `.htaccess` files inside the root, includes, database, docs, tests and
uploads folders. Apache must honor them (`AllowOverride` enabled for htdocs).
Internal directories should return HTTP 403. `uploads/donations` must be writable
by the Apache process; only application-generated JPEG names are served.

In XAMPP, open Apache > Config > PHP (php.ini). Enable GD (`extension=gd`) if it is
disabled, then restart Apache. CLI and Apache may use different php.ini files;
check the CLI with `C:\xampp\php\php.exe --ini`.

Start Apache and MySQL. Confirm http://localhost/ and http://localhost/phpmyadmin/ work.

### 2. Import the schema

1. In phpMyAdmin, select the server-level Import tab.
2. Import `database/schema.sql` as SQL.
3. Confirm `food_donation_db` contains users, organizations, donations,
   donation_requests and collection_tasks.

The schema creates missing tables; it does not drop or migrate existing tables.
On another installation with important existing data, use a backup and check the
database name before importing. Never delete existing records as a setup shortcut.

### 3. Configure the connection

`includes/config.php` uses these local XAMPP defaults:

| Setting | Default | Optional environment override |
| --- | --- | --- |
| Host | 127.0.0.1 | FOOD_DB_HOST |
| Port | 3306 | FOOD_DB_PORT |
| Database | food_donation_db | FOOD_DB_NAME |
| Database user | root | FOOD_DB_USER |
| Database password | Empty | FOOD_DB_PASS |

Set environment overrides for both Apache and CLI, then restart Apache, or edit
the local configuration deliberately for the destination computer. Apache does
not inherit variables set only in a separate PowerShell session.

`APP_BASE_PATH` is derived from the project folder name directly under htdocs.
The automated HTTP tests expect the exact default folder name/URL above and port
80; update their base URLs if you use another folder or Apache port. `schema.sql`
explicitly selects food_donation_db; adjust that SQL first if choosing another database.

### 4. Create the first administrator on a fresh database

Open PowerShell in the project folder and run:

```powershell
$setupAdminPassword = Read-Host 'Choose the initial admin password' -AsSecureString
$setupAdminPlain = [System.Net.NetworkCredential]::new('', $setupAdminPassword).Password
try {
    $setupAdminPlain | & 'C:\xampp\php\php.exe' database\create_admin.php --name="Community Administrator" --email="admin@gmail.com" --phone="0770000000" --address="University administration office"
} finally {
    Remove-Variable setupAdminPlain, setupAdminPassword
}
```

Choose 8–72 bytes for the password. The tool reads it through standard input and
stores a hash. It refuses if any administrator already exists; it never promotes
or overwrites another user. On this existing installation, skip this step.

### 5. Optional demonstration data

With an active administrator already present:

```powershell
& 'C:\xampp\php\php.exe' database\seed_demo.php
```

Alternatively, select the application database in phpMyAdmin and import
`database/sample_data.sql`. The database account needs CREATE ROUTINE and ALTER
ROUTINE privileges to create/drop the script's setup procedure. The ordinary
application workflow does not create procedures.

See `database/DEMO_DATA.md` for demo credentials and examples. A repeat import
preserves demo progress and passwords, and does not refresh deadlines. Conflicting
or partially existing demo identities stop setup without overwriting users.

### 6. Verify

```powershell
& 'C:\xampp\php\php.exe' tests\run_all.php
```

Expect PHP syntax PASS and 21 passing suites. Results are saved to
`tests/latest-results.log`. Run outside a live presentation; test fixtures are
temporary and separate from real accounts and demo data.

Open the homepage and sign in. Public organization/volunteer registrations require
Admin > Pending accounts approval. Tabs in the same browser profile share a
session: use separate browser profiles/private windows, or sign out between roles.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Database temporarily unavailable | MySQL running, database imported, host/port/credentials matching Apache and CLI |
| Photo upload unavailable | GD enabled in Apache's php.ini; Apache restarted; uploads/donations writable |
| PHP CLI command not found | Use the full C:\xampp\php\php.exe path |
| Organization/volunteer cannot sign in | Admin > Pending accounts approval; Registered users status must be Active |
| Other browser tab switches role | Tabs share the session cookie; use separate profiles or sign out |
| Donation not available or assignment blocked | Pickup window, donor/recipient/volunteer account status, approved request and existing assignment |
| Reassignment/cancellation control absent | Reassignment ends at acceptance; cancellation ends at collection |
| Menu appears unchanged | Refresh the page; use the current Recent/Pending menu links |
| Internal directories are accessible | Restore .htaccess files and Apache AllowOverride configuration |
| HTTP tests fail with a different folder/port | Test base URLs assume the documented default URL |
| Sample SQL reports permission error | Use an account permitted to create/drop the setup procedure |

Visitor errors intentionally omit technical details. Check Apache's error log
(`C:\xampp\apache\logs\error.log`) or the configured PHP error log when diagnosing
a failure. Report query errors are not displayed as false zero statistics.

## Backup and move the project

Export food_donation_db through phpMyAdmin and copy the complete project folder,
including uploads/donations and hidden .htaccess files. Keep SQL exports outside
public web folders. On the destination, import that backup instead of starting
again with demo data, configure the connection and retain the original passwords.
