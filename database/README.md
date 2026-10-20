# Database setup

Database: `food_donation_db`

## Import using phpMyAdmin
1. Start Apache and MySQL in XAMPP.
2. Open http://localhost/phpmyadmin/.
3. Select the server-level Import tab.
4. Choose this folder's schema.sql, leave the format as SQL, and click Import.
5. Open food_donation_db. It should contain five empty tables.

The script creates missing tables without deleting existing records. Running
it again does not upgrade existing table definitions; future schema changes
will have separate SQL instructions. Do not delete a database to fix an import.

## Tables
- users: login credentials, contact details, role, and account approval status.
- organizations: one organization profile per organization user.
- donations: food information, pickup details, deadline, and workflow status.
- donation_requests: requesting organization, delivery address, and donor decision.
- collection_tasks: approved request, assigned volunteer, assigning administrator,
  acceptance, collection, and delivery timestamps.

Names stored in the database use lowercase English values. The UI will display
friendly labels, such as Cooked meals for cooked_meals.
PHP uses Asia/Colombo, and every PDO connection sets the database session to
+05:30 so CURRENT_TIMESTAMP and application dates use the same local time.

## Application connection
includes/config.php defines the local connection settings. This XAMPP installation
uses 127.0.0.1, port 3306, database food_donation_db, user root, and an empty local
password. These defaults are for this local demonstration environment.
Optional FOOD_DB_HOST, FOOD_DB_PORT, FOOD_DB_NAME, FOOD_DB_USER, and FOOD_DB_PASS
environment variables override the defaults if another installation needs them.
Restart Apache after changing its environment variables.

includes/db.php provides db(), which returns a shared PDO connection for each
request. It uses utf8mb4, real prepared statements, associative fetches, and
exception mode. Callers must validate inputs, bind values, check permissions,
and handle query failures appropriately when implementing each feature.

Open the project homepage to verify the connection. It displays Database
connection ready after a successful connection. Connection errors return HTTP
503 and a generic message; the server log receives only the error code.
includes/.htaccess blocks browser access to connection and configuration files.

## Workflow rules and implementation status
1. Public registration permits donor, organization, or volunteer only. Donors
   become active; organizations and volunteers start pending admin approval.
   Admin accounts are created separately using password_hash(), never public registration.
2. Only active users can perform actions for their role. A foreign key ensures
   a user exists; PHP must also check that user's role and account status.
3. A donation starts available. The first pending request changes it to requested.
   Other organizations may also request while it is requested and unallocated.
   Each organization may submit only one request per donation.
4. The owner donor approves one request and rejects other pending requests in
   one transaction. The donation stays requested until volunteer assignment.
   An approved request stays approved throughout delivery and completion.
   A generated column plus a unique index prevents two approved requests for
   the same donation, including concurrent attempts.
5. If all requests are rejected or cancelled before approval, return the donation
   to available unless its deadline has passed. Rejected requests remain history.
6. Admin assigns an active volunteer to an approved request. Create the task and
   change the donation to assigned in one transaction. One task per request;
   reassignment must update that task before acceptance instead of creating duplicates.
   Reassignment is available on admin donation details. It updates the same task's
   volunteer, assigning administrator and assignment time; old-volunteer access ends.
7. The assigned volunteer accepts, collects, and delivers in that order. Accepting
   changes only task status; collecting/delivering also changes donation status.
8. The requesting organization confirms receipt only after delivery. Set the
   donation to completed and record completed_at. The task stays delivered.
9. Donors may edit only available donations with no requests. Donors may cancel
   available/requested donations only before an approval. Admin may cancel
   before collection, cancelling related requests/tasks in the same transaction.
   Admin may also close overdue uncollected food explicitly. No cancellation or
   editing after collection. Never delete donation history. Existing decision and
   acceptance timestamps remain recorded when requests/tasks are cancelled.
10. When the deadline passes, available/requested/assigned donations display as
    expired and block further requests, approvals, assignments, acceptance and pickup.
    Expiry is calculated on reads; GET requests preserve stored donation/request/task
    history rather than rewriting it. Every workflow action rechecks the deadline
    while holding locks. Collected/delivered food is not automatically expired;
    delivery and receipt confirmation may proceed after the pickup deadline.
11. Use PDO transactions and SELECT ... FOR UPDATE on the donation row for
    requests, decisions, assignments, cancellations and status changes.
    Recheck ownership, role, current status, and deadline after acquiring the lock.

## Security and current scope
- password_hash holds PHP password_hash() output; no plaintext passwords or
  sample login accounts are inserted by this script.
- Foreign keys use RESTRICT to preserve history. Suspend accounts instead of
  deleting users with related records.
- Database checks reject nonpositive quantity and invalid pickup/deadline order.
- database/.htaccess blocks HTTP access to this folder under XAMPP Apache.
- Authentication, role checks, CSRF protection, safe uploads, and workflow
  transactions will be implemented in their respective development stages.
- Local database defaults are in the protected includes/config.php file.
  Sample application accounts and donation data come later.
