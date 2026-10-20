# Demonstration accounts and sample data

The local database has been loaded with three active fictional demonstration
accounts. All three use the password **Demo@1234**:

| Role | Email |
| --- | --- |
| Donor | donor.demo@food.test |
| Organization | organization.demo@food.test |
| Volunteer | volunteer.demo@food.test |

Use your existing `admin@gmail.com` account for administration. Its password,
account details and existing real records were not changed. Demo organization
and volunteer accounts are pre-approved for the presentation; public registrations
still require approval through Admin > Pending accounts.

## Setup on another installation

Import database/schema.sql and create the initial administrator first. Then either:

```powershell
& 'C:\xampp\php\php.exe' database\seed_demo.php
```

Or select the application database in phpMyAdmin and import database/sample_data.sql.
Both methods use the same SQL. The database account needs permission to create/drop
the setup script's `community_food_demo_seed_v1` procedure. Local XAMPP root has this.

Only bcrypt hashes are inserted into users.password_hash. The setup temporarily
creates its own procedure and removes it after execution. No schema migration is needed.

## Included donations

| Food | Purpose |
| --- | --- |
| [Demo] Fresh vegetable meal packs | Available food for a complete live workflow |
| [Demo] Bakery bread rolls | Pending request for the donor to review |
| [Demo] Fresh garden vegetables | Approved request ready for admin assignment |
| [Demo] Dry rice bags | Assigned task ready for the volunteer to accept |
| [Demo] Delivered fruit baskets | Organization can confirm receipt |
| [Demo] Completed community lunch | Completed history and reports |
| [Demo] Expired dairy example | Expired food blocked from requests/pickup |

All names, contacts and addresses are fictional and marked Demo. No food photos
are included. There are seven donations, five requests and three collection tasks.

## Repeating setup

- A successful repeat detects the three demo identities and adds nothing.
- Passwords, suspensions and demonstration workflow edits are preserved.
- Conflicting or partially existing demo accounts stop setup without replacing users.
- The existing administrator is required and is never reset or replaced.
- Dates are relative to the first setup. Available examples have a two-day pickup
  window. Re-running does not refresh deadlines or undo progress; publish a new
  demo donation through the donor form when preparing a later presentation.
- There is no automatic reset/delete command. Existing real records remain intact.

## Checks

```powershell
& 'C:\xampp\php\php.exe' tests\demo_setup.php
& 'C:\xampp\php\php.exe' tests\demo_setup.php --verify-live
```

The normal test uses connection-local temporary tables and checks counts,
password hashes, repeat imports, collision handling and preservation of real row
counts. The optional live check additionally signs in to the existing demo accounts
and opens each dashboard without changing donation progress.

Use separate browser profiles/private windows for different roles, or sign out
between roles. Tabs in one profile share the application login session.
