# Initial administrator setup

Stage 3 - Task 3

The initial demonstration administrator has been created:
- Email: admin@gmail.com
- Name: Community Administrator
- Status: active
- Phone 0000000000 and address Local university demonstration are demo placeholders.
- The password was changed to the user's requested value, provided in chat.
  Only the password hash is stored in the database; no plaintext password is
  saved in project files.

Open http://localhost/Community%20Food%20Donation%20System/login.php and sign in
with that email and password. Expect the administrator account overview.
Click Sign out to destroy the session. Administrator user management, Pending
accounts approval, collection assignment, donation tracking and reports are available.

## Setup on a fresh database

Do not rerun setup on this database: an administrator already exists. The tool
will refuse another setup even if the existing administrator is suspended.
It never promotes or overwrites an existing account.

For another fresh installation, start XAMPP MySQL, import schema.sql, and open
PowerShell in C:\xampp\htdocs\Community Food Donation System. Run:

```powershell
$adminPassword = Read-Host 'Choose the initial admin password' -AsSecureString
$plainAdminPassword = [System.Net.NetworkCredential]::new('', $adminPassword).Password
try {
    $plainAdminPassword | & 'C:\xampp\php\php.exe' database\create_admin.php --name="Community Administrator" --email="admin@gmail.com" --phone="0000000000" --address="Local university demonstration"
} finally {
    Remove-Variable plainAdminPassword, adminPassword
}
```

Choose 8 to 72 bytes for the password. Edit the example name/contact details
when setting up a real local project account. The secure prompt hides input;
the password goes through standard input rather than command-line arguments
or shell history. The temporary plaintext variable is removed afterward.

Expected output: Initial administrator created successfully.
Then sign in at login.php. No additional SQL changes are required.

## Security and checks

- database/.htaccess blocks browser access; create_admin.php also checks CLI mode.
- Server-side validation, prepared inserts, and password_hash() are used.
- A database advisory lock serializes setup commands; a transaction covers the
  existing-account checks and insertion.
- Setup refuses another administrator and refuses emails used by another role.
- Passwords are not printed by the setup command or saved in a plaintext file.
- Account approval is available through Admin > Pending accounts.

Run isolated setup checks with:

```powershell
& 'C:\xampp\php\php.exe' tests\admin_setup.php
```

The test shadows users with a connection-local temporary table. It does not
modify application accounts and can run after an initial admin exists.
