# Authentication and registration checks

## Repeatable demonstration setup
database/sample_data.sql and database/seed_demo.php load the same demo dataset.
See database/DEMO_DATA.md for sample logins and instructions. Existing administrator
and real user data are preserved; repeat setup does not reset demo workflow changes.

```powershell
& 'C:\xampp\php\php.exe' tests\demo_setup.php
& 'C:\xampp\php\php.exe' tests\demo_setup.php --verify-live
```

The seed suite shadows application tables with connection-local temporary tables,
checks complete counts, password hashes, repeated imports, account collisions and
missing-admin rollback, then verifies real row counts are unchanged. The optional
live mode signs in to each existing demo account and opens its dashboard.
The normal isolated suite is included in tests/run_all.php (now 21 suites).

## Complete regression run
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\run_all.php
```

The CLI runner lints every PHP file and executes all 21 suites sequentially. It
requires successful process exits and PASS evidence, returns nonzero for failures,
and saves detailed output to tests/latest-results.log (blocked from browser access).
Latest full run: 93 PHP files passed syntax checks; 21 suites passed; zero failures.
Each test removes only its own temporary fixtures/images. Run outside the live demo.

See docs/DEMO_CHECKLIST.md for preparation, role/browser-session guidance, the
demonstration walkthrough and manual layout checks. Dedicated persistent sample
data is now loaded and documented in database/DEMO_DATA.md; no additional database
import is needed for this run.

## Administrator Recent donations and Pending accounts navigation
Admin dashboard now uses the shared admin sidebar. Recent donations opens
admin/recent.php and displays the latest five matching records; Pending accounts
opens admin/accounts.php, the Account approvals screen. Each menu highlights its
destination. The older Account approvals navigation label is now Pending accounts.
tests/admin_overview.php covers these links and selected states; approval behavior
is also checked by tests/account_approvals.php. Both suites passed.
No SQL migration is required.

## Administrator cancellation and volunteer reassignment
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\admin_workflow.php
```

Checks cover admin-only POST/CSRF, explicit acknowledgement, state restrictions,
inactive replacement volunteers, preserving task identity, stale reassignment,
old-volunteer access removal, acceptance blocking reassignment, atomic cancellation
and preservation of request decisions/task timestamps. Related overview and full
delivery workflow suites also passed. Only temporary fixture records are removed.

Manual check with a test donation:
1. As admin, open All donations > View. Before collection, the Cancel donation form
   closes the donation and its open requests/tasks after explicit acknowledgement.
2. Before an assigned volunteer accepts, use Reassign volunteer before acceptance
   to choose another active volunteer and confirm. The same task moves to that user.
3. The old volunteer cannot open the task afterward. The new volunteer can accept it.
4. After acceptance, reassignment disappears. Cancellation remains available until
   collection. After collection, cancellation is unavailable.

Overdue uncollected food can be explicitly cancelled. Reassignment requires a
currently open pickup window. Reassignment updates the task's assignment date;
assignment-period reports therefore reflect its current assignment. No SQL migration
is required, and no records are deleted by either action.

## Cross-role workflow integration
tests/volunteer_workflow.php now opens donor, organization, volunteer and admin
views at assignment, acceptance, collection, delivery and receipt confirmation.
It verifies matching statuses/counters, allocated-food visibility, read-only views,
three-role completed histories and propagation of receipt updates to the volunteer
dashboard's latest update time. Completed donor detail must select Completed history.

Run with Apache and MySQL active:

```powershell
& 'C:\xampp\php\php.exe' tests\volunteer_workflow.php
```

Related donor decision/management and dashboard suites also passed. The donor
decision suite includes two competing approval workers and requires one winner.
Temporary fixture records are removed. No SQL migration is required.

Expiry is effective/read-only until an action is attempted: overdue uncollected
food shows Expired and blocks pickup actions; history records are preserved.
Delivery and receipt can continue after pickup for food already collected.

## Administrator reports and statistics
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\admin_reports.php
```

Checks cover strict calendar dates, inclusive date boundaries, each event's date
column, reconciled current-status totals, all-time activity, received quantities
separated by unit, admin-only access, empty periods and read-only expiry display.
Only temporary fixture records are added and removed.

Manual check:
1. As admin, open Reports and statistics. The default view covers all time.
2. Choose both Start date and End date, then Apply dates. Both days are included.
3. Compare cards with the date descriptions beneath them: deliveries and receipts
   are counted by their actual recorded event dates, not by donation publication date.
4. Status tables describe current statuses of records created/assigned in that
   period; they do not reconstruct a past snapshot.
5. Food received by unit counts completed donations by receipt date. Units remain
   separate. Select All time to clear the dates.

No SQL migration is required. Failed queries show an error instead of false zeros.

## Administrator donation/request overview
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\admin_overview.php
```

Checks cover admin-only list/detail access, search and status filters, pagination,
effective expiry without writes, all-donor visibility, competing requests, escaped
contact/message content, recorded timeline timestamps and empty/missing records.
Only temporary test fixtures are created and removed.

Manual check:
1. As admin, open All donations; filter by food/donor name or donation status.
2. Select View to see the donor, food, organization requests and collection progress.
3. Open Donation requests; search by organization and combine request/donation statuses.
4. Select View to jump to that request within its donation's details.
5. An approved unassigned request links to Assign collections. Empty lists are valid
   when no matching real records exist.

No SQL migration is required. These overview pages read recorded workflow data.

## Volunteer Recent collection tasks navigation
The dashboard and volunteer sidebar now open volunteer/recent.php, showing the
latest five matching tasks with Recent collection tasks selected. This replaces
the old dashboard-only anchor. My collection tasks remains the full paginated list.
tests/volunteer_tasks.php covers routing, the five-task limit, selected state,
guest protection and records scoped to the signed-in volunteer.

Manual check: sign in as volunteer and select Recent collection tasks. Expect its
own page and highlighted menu item. Select a food's View link to open its task.
No SQL migration is required.

## Administrator registered-user management
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\user_management.php
```

Checks cover admin-only lists/actions, search and role/status filters, pagination,
escaped contact details, POST/CSRF, explicit review, protected administrators,
pending/rejected state rules, missing organization profiles, stale/repeated updates,
session access during suspension and sign-in after reactivation. Roles/passwords
and account identity remain unchanged. Only temporary fixture accounts are altered.

Manual check with a test donor/organization/volunteer account:
1. As admin, open Registered users and find the account using search or filters.
2. Review the account, tick the acknowledgement and select Suspend.
3. That user cannot sign in; an already signed-in user loses access on their next
   protected request while suspended.
4. As admin, filter Suspended, review the account and select Reactivate. The user
   can sign in with their original credentials.
5. Administrator rows have no access-change form. Pending registrations link to
   Account approvals and cannot be reactivated through these controls.

No SQL migration is required. Suspension/reactivation preserves related records.

## Volunteer completed history and profile
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\volunteer_tasks.php
& 'C:\xampp\php\php.exe' tests\volunteer_profile.php
```

The task suite includes completed-history membership, receipt filters, food search,
selected navigation, completed detail navigation and cross-volunteer privacy.
The profile suite checks access, CSRF, validation, escaping, stale forms, protected
identity/role/status/password fields and verified email changes. Donor and organization
profile suites also passed after extending the shared profile form.

Manual check:
1. As volunteer, open Completed task history. Delivered tasks appear with their
   delivery time, including those awaiting the organization's receipt confirmation.
2. Filter Receipt status to Awaiting confirmation or Receipt confirmed. Completed
   task history should stay highlighted; My collection tasks should not.
3. Select View on a delivered task. Its history navigation should stay selected.
4. Open My profile, edit contact details and Save profile. The dashboard reflects them.
5. Enter your current password when changing email; sign in using that new email next time.

Volunteer contact addresses do not replace task pickup or request delivery addresses.
No SQL migration is required. Each automated test removes only its own temporary records.

## Volunteer task updates and complete delivery workflow
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\volunteer_workflow.php
```

This creates one temporary donation and uses real service actions to request it,
approve it and assign a volunteer. HTTP checks then accept, collect and deliver
the food before the organization confirms receipt. Checks include role/ownership,
POST/CSRF, explicit acknowledgement, server-controlled timestamps, token rotation,
skipped/duplicate updates, closed states, inactive volunteers and pickup windows.
Only this test's own fixture records are removed.

Manual check:
1. Sign in as the assigned volunteer, open My collection tasks > View.
2. Check the acknowledgement and select Accept task. Expect Accepted task status.
3. After pickup, acknowledge and select Mark collected. Expect Collected task and
   donation statuses. The pickup window must still be open.
4. After delivery, acknowledge and select Mark delivered. Expect Delivered and an
   awaiting-receipt message. Delivery is possible beyond the pickup deadline once
   food has been collected.
5. Sign in as the receiving organization and confirm receipt from its request page.
   Expect Completed donation status and a recorded receipt milestone.

No SQL migration is required. Volunteer updates do not mark donations completed;
the receiving organization confirms receipt.

## Volunteer collections and administrator assignment
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\volunteer_tasks.php
```

Checks cover role/ownership protection, list filters/pagination, literal searches,
escaped details, private pickup/delivery information, recorded progress, closed
tasks and read-only GET behavior. Assignment checks cover admin-only POST/CSRF,
active participants, approved requests, pickup windows, invalid/duplicate
assignments, server-controlled fields and atomic task/donation updates.
Only temporary test records are changed and removed.

Manual check:
1. Register a volunteer and approve it through Admin > Account approvals.
2. Have an organization request an available donation, then sign in as its donor
   and approve the request through Incoming requests.
3. Sign in as admin, open Assign collections, choose the approved volunteer and
   select Assign volunteer. The donation must still be within its pickup window.
4. Sign in as that volunteer and open My collection tasks > View.
5. Expect food, pickup/delivery contact details and the recorded Assigned milestone.
6. Another volunteer must not be able to view this task's URL.

Accepting tasks and recording collection/delivery are now available. No SQL
migration is required. Existing real requests are not assigned by automated tests.

## Administrator account approvals
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\account_approvals.php
```

Checks cover pending-login blocking, admin-only review/decisions, escaped details,
role filtering, POST/CSRF, explicit review, eligible account states, missing
organization profiles, duplicate decisions, inactive administrators, preservation
of roles/passwords and successful organization/volunteer login after approval.
Only temporary test accounts are changed and removed.

Manual check:
1. Sign in as administrator and select Account approvals.
2. Review your organization's registration details, check the review acknowledgement,
   then select Approve account. Expect a success message.
3. Sign out and sign in using that organization's registered email/password.
4. The organization dashboard, browsing, requests and My profile are now accessible.

No SQL migration is required. This part of Stage 8 was brought forward so users
can test the completed organization module.

## Organization profile editing
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\organization_profile.php
& 'C:\xampp\php\php.exe' tests\profile.php
```

The organization suite checks role/ownership and CSRF protection, validation,
escaped values, selected navigation, atomic contact/organization updates, stale
forms, duplicate-email rollback, password-verified email changes and sign-in.
The donor suite checks the shared profile implementation for regressions.
Each suite removes only its own temporary records.

Manual check with an active organization account:
1. Open My profile. Change organization name/description and contact details.
2. Select Save profile. Expect a success message and updated dashboard details.
3. To change email, enter your current password. Use the new email next sign-in.
4. A new request starts with your updated address; previous request addresses stay
   as recorded. Organization description is optional.

No SQL migration is required. Organization and donor profile pages share
includes/profile_page.php and validation helpers in includes/profile.php.

## Organization receipt confirmation
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\receipt_confirmation.php
```

Checks cover ownership/role restrictions, POST/CSRF and explicit acknowledgement,
invalid workflow states, absent tasks/delivery timestamps, inactive accounts,
forged fields, server-recorded completion, token rotation, duplicate submissions,
completed history and preservation of the volunteer delivery record. Only the
test's own temporary records are removed.

Manual check once an approved request has a delivered donation and a delivered
collection task with a recorded delivery timestamp:
1. Sign in as the receiving organization and open My requests, then View.
2. Check "My organization has received this food" and select Confirm receipt.
3. Expect the success message, Completed status and recorded Receipt confirmed step.
4. Open Completed history; the donation should appear with the history tab selected.
5. Reopening the request must not offer another confirmation form.

Assignment and volunteer delivery updates belong to the following role modules.
No SQL migration is required; do not manually alter real records for testing.

## Organization request history and tracking
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\organization_tracking.php
```

Checks cover role/ownership protection, contact privacy, pagination and filters,
escaped output, completed-history membership, selected navigation, recorded
tracking timestamps, closed/expired warnings and read-only GET behavior.
Only this test's own accounts, donations, requests and tasks are removed.

Manual check with an active organization account:
1. Open My requests and filter by food name, request status or delivery status.
2. Select View to see the request's tracking page. Pending requests show the
   recorded submission; approved requests show the collection/delivery timeline.
3. Completed history should highlight its own navigation item and contain only
   completed donations approved for this organization.
4. Tracking is also linked from dashboard recent requests and requested food details.

Tracking displays recorded data. Assignment and volunteer actions will be added
in their role modules; organization receipt confirmation is now available.
No SQL migration is required.

## Organization browsing and request submission
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\organization_requests.php
```

Checks cover browsing filters/pagination, current availability, donor/organization
account status, private allocation visibility, escaped output, POST/CSRF and form
validation, server-controlled organization identity, duplicate requests, multiple
pending organizations, donor approval and competitor rejection. Only its own
test accounts, requests and donations are removed.

Manual check with an active organization account:
1. Sign in, then choose Browse food. Pending accounts remain blocked until approval
   (approve them through Admin > Account approvals).
2. Search food names and pickup locations, or choose a category.
3. Select View food, review details, enter a delivery address and optional message,
   then Send donation request. Expect Pending status and a success message.
4. Sign in as the donor and review Incoming requests. Approve or reject.
5. Sign back in as the organization. Its dashboard's recent food link shows the
   recorded request and updated status, even after it leaves available browsing.
6. A second request from the same organization must fail. Other organizations
   can request unallocated food, but only one can be approved.

No SQL migration is required. Full organization request history/tracking is now
available. Existing real accounts were not approved or changed during tests.

## Completed history selected-tab fix
As donor, select Completed history. The Completed history item should have the
highlight; My donations should not. Selecting My donations restores its highlight.
The bug was missing active-page state/aria-current, not missing CSS. These checks
are included in tests/donation_management.php and were also reviewed visually.

## Incoming organization requests and donor decisions
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\donor_requests.php
```

The test creates temporary donors, organizations, donations and requests. It
checks owner/role protection, filters, escaped content, CSRF and POST requirements,
approval/rejection, decision timestamps, inactive organizations, expired/closed
donations, and competing approvals in two independent PHP workers. Exactly one
approval must succeed. Only the test's own records are removed afterward.

Manual check:
1. Sign in as donor and open Incoming requests. An empty list is expected until
   an organization requests one of your donations (organization UI is Stage 6).
2. Use request-status filters, or View requests from a donation detail page.
3. Select Review to see the organization's details, delivery address and message.
4. Approve an eligible pending request and accept the confirmation prompt. Expect
   Approved for the chosen request and Rejected for its pending competitors.
   The donation stays Requested until an admin assigns a volunteer.
5. Reject a pending request. If others are pending, donation remains Requested;
   after the last rejection it returns to Available. Rejected requests remain
   in history and the same organization cannot submit a duplicate request.
6. Approved/decided/cancelled requests cannot be decided again; expired or closed
   donations reject decisions. Inactive organizations cannot be approved.

The database's existing unique approval index remains a second protection
against multiple allocations. No SQL migration is required for this task.

## Donor profile
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\profile.php
```

Checks cover access control, CSRF, validation, escaped output, forged account
fields, stale forms, duplicate emails, password confirmation for email changes,
new-email login, and preservation of existing donation pickup locations. Only
the test's accounts and donation are created/removed.

Manual check:
1. Sign in as donor and select Edit profile from the dashboard, or My profile
   from the donor module sidebar.
2. Update name, phone, or address and save. Expect a success confirmation and
   updated account details. Current password is optional when email is unchanged.
3. To change the email, enter the correct current password. Expect confirmation;
   use the new email with the same password for future login.
4. An email already used by another account must be rejected without changes.
5. New donation forms prefill the updated address. Existing donation pickup
   addresses remain as recorded. No SQL migration is required.

Profile editing changes contact details only. Roles, account approval status,
and password are controlled separately and cannot be changed through this form.

## Donor donation management
With Apache and MySQL running, execute from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\donation_management.php
```

The test creates uniquely named donors, an organization, requests, and donations
to verify scoping, search/status filters, pagination/history, details, editing,
CSRF, stale content versions, photo preservation/replacement/removal, eligibility,
and cancellation. It removes only its own records and files afterward.

Manual checks:
1. Sign in as a donor and choose My donations. Search by food name, filter status,
   or select Completed history. Lists show twenty rows per page.
2. Select View to see the description, photo, pickup information, times, and status.
3. An unexpired Available donation with no requests offers Edit donation. Saving
   can keep, replace, or remove its photo. Existing requests prevent editing,
   including rejected request history. An expired donation cannot be revived.
4. Eligible Available/Requested donations offer Confirm cancellation. Accept the
   confirmation prompt. Donation and pending requests become Cancelled together;
   existing history remains. Approved/assigned/collected/delivered/completed,
   already-cancelled, and expired donations cannot be cancelled by the donor.
5. Two open edit forms: save one, then submit the older version. Expect HTTP 409
   and a request to reload; the newer change must remain intact.
6. Access another donor's donation ID: expect 404, including edit/cancel attempts.

Overdue uncollected donations are displayed/filterable as Expired without list
or detail GET requests changing database state. Workflow expiry transitions
remain a later integration task. No SQL import is needed for management.

## Donor donation creation and images
Start Apache and MySQL, then run from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\donation_creation.php
```

Checks cover donor-only access, CSRF, text/quantity/category/unit/date validation,
forged owner/status fields, plain creation, success redirects, duplicate form-token
rejection, image MIME/extension/size/dimension validation, JPEG re-encoding,
stripping appended file content, Apache image serving, escaped public previews,
and private pickup-address omission. Test-created accounts, donations, images,
and temporary image files are removed afterward; existing records are preserved.

Manual check:
1. Sign out of admin and sign in using a donor account.
2. Click Share food on the donor dashboard.
3. Enter a food name, category, description, positive quantity/unit, pickup
   location, available time, and a future deadline after the available time.
4. Optionally choose a JPEG, PNG, or WebP image up to 2 MB, 4096 pixels per side,
   and 8 megapixels. All accepted images are re-encoded as JPEG, up to 1600 pixels.
5. Publish. Expect a success alert, an Available donation in recent records, and
   updated statistics. If its available time has arrived, it can appear among
   the latest three public homepage donations, including its photo if provided.
6. Refresh the dashboard: it must not create another donation.

Time values use Asia/Colombo. Future pickup starts are allowed; public previews
show only donations currently within their pickup window. Text errors preserve
entered values, but browsers require the photo to be selected again.

## PHP image dependency
PHP GD must be enabled. On this installation it was enabled in
C:\xampp\php\php.ini by changing ;extension=gd to extension=gd, then restarting
XAMPP Apache. The original configuration was backed up to
C:\xampp\php\php.ini.before-gd-0d62aa99cc514094a5ad219ce64ee88d.
Verify CLI support with `& 'C:\xampp\php\php.exe' --ri gd`.
On a fresh XAMPP installation, enable GD and restart Apache before testing photos.
Keep uploads/donations writable by Apache. uploads/.htaccess permits only the
generated JPEG filenames; other uploads and configuration files are denied.
No SQL migration or new database tables are required.

## Role dashboards
Run from PowerShell in the project folder with MySQL running:

```powershell
& 'C:\xampp\php\php.exe' tests\dashboards.php
```

This creates temporary role, donation, request, and task records within one
transaction, checks scoped counts/records and system totals, then rolls all
fixtures back. It does not change existing application accounts or history.
It also verifies overdue food is displayed as Expired without updating records.

For a manual check, sign in and open My dashboard. Expect four statistic cards,
Recent records, and Your account details. Admin also sees Pending accounts.
Sidebar links scroll to the corresponding sections. Empty histories show a
message instead of sample records. Narrow layouts stack the sidebar and cards;
populated tables scroll horizontally within their panel.

Donor/organization/volunteer dashboards display only that user's related data.
Use tests/auth.php to verify other roles' pages return 403. Pending users cannot
sign in until approved through Admin > Account approvals. These dashboards display
summary information; donation/task actions arrive in the next role modules.
No SQL import or migration is required.

## Homepage and shared navigation
Open http://localhost/Community%20Food%20Donation%20System/ with Apache and MySQL
running. Expect the hero, role cards, four workflow steps, available food, and
community invitation. With no eligible donations, a real empty state appears.

Check Home, How it works, Available food, Sign in, and Join the community links.
After signing in, the homepage should show My dashboard and a Sign out form.
The dashboard link must open the current user's role page. Navigation and footer
are shared across homepage, registration, login, and the four account pages.

Resize the browser to check stacked mobile content and wrapping navigation.
Use the existing authentication/registration scripts below after navigation edits.

Stage 4 verification executed temporary homepage data checks for available-food
rendering, expired/future/cancelled/allocated filtering, escaped food names,
private-data omission, authenticated donor navigation, and homepage logout.
The temporary records were removed afterward. Desktop and narrow layouts were
visually reviewed. No SQL migration is required for this task.

## Initial administrator setup
Run `& 'C:\xampp\php\php.exe' tests\admin_setup.php` from PowerShell in the
project folder. It checks validation, password hashing, refusal to promote
an existing donor, repeated setup, and a suspended existing administrator.
It uses a connection-local temporary users table; real accounts are untouched.
See database/ADMIN_SETUP.md for setup instructions on a fresh installation.

## Authentication
Start Apache and MySQL, then run from the project folder:

```powershell
& 'C:\xampp\php\php.exe' tests\auth.php
```

This creates uniquely named temporary accounts for all four roles and blocked
account states. It verifies guest protection, credentials, CSRF, role redirects,
all role-access combinations, session regeneration, invalidation of old IDs,
escaped account names, logout, suspension/role changes during a session, and
the session login cooldown. It removes only its own accounts and profiles.
It does not create a permanent administrator or change existing users.

Manual checks:
1. Open http://localhost/Community%20Food%20Donation%20System/login.php.
2. Sign in with your registered donor account. Expect the donor account overview.
3. Visit admin/index.php while signed in as donor. Expect Access denied (403).
4. Click Sign out, then revisit donor/index.php. Expect a redirect to login.
5. Pending organization/volunteer accounts cannot sign in until approved.

The four role index pages are minimal protected account landing pages. Full
dashboard design and role features will be added in the planned later stages.
The demo administrator is admin@gmail.com; its current password was requested
by the user in chat. Admin > Account approvals can now activate pending users.

After five incorrect credential attempts in one session, login pauses for five
minutes. This simple session-based cooldown is suitable for the local demo;
it is not an account-wide or network-wide rate limit.

## Registration

Start Apache and MySQL in XAMPP. From PowerShell in
`C:\xampp\htdocs\Community Food Donation System`, run:

```powershell
& 'C:\xampp\php\php.exe' tests\registration.php
```

The test uses the local Apache registration page and configured PDO connection.
It creates uniquely named `registration-check-...@example.test` accounts, checks
registration for all three public roles, then deletes only those test accounts
and their organization profiles in a finally block. Existing accounts are not
part of the cleanup. Do not run this test against a shared demonstration while
someone is interacting with the temporary test accounts.

Checks cover session cookie flags, cache headers, missing/forged/cross-session
CSRF tokens, forged admin roles, invalid inputs and field types, password limits,
matching confirmation, required organization name, escaped form values, hidden
passwords, role/status rules, verifiable password hashes, organization profiles,
success redirects, token rotation, and duplicate registration.

Expected output: PASS lines, followed by Test accounts cleaned up, and exit code 0.
The tests folder is blocked through Apache and the script also requires PHP CLI.

## Manual check
1. Open http://localhost/Community%20Food%20Donation%20System/register.php.
2. Choose Community organization: its name field should appear and be required.
3. Choose Food donor or Collection volunteer: that field should disappear.
4. Register using your own test details and a password of 8 to 72 bytes.
5. A donor confirmation says the account is active. Organization and volunteer
   confirmations say pending administrator approval.
6. In phpMyAdmin, inspect users. Passwords must be hashes, never the entered text.
   Organization accounts also have a linked organizations row.
7. Refresh the success page: it should show the form without submitting again.

No SQL import is required. Login and role-protected pages are now available.
Bootstrap CSS 5.3.3 is stored in assets/css/bootstrap.min.css from jsDelivr's
official Bootstrap npm package; registration has no external runtime assets.
