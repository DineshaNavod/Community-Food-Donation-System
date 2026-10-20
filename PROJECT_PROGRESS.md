# Community Food Donation Coordination System

## Current stage
Stage 10 - Testing, Bug Fixes, Sample Data, and Presentation Preparation
Task 3 - Diagrams, README and installation guide (complete)
Admin approval and initial collection assignment controls are available.

## Completed features
- Confirmed the project folder exists inside C:/xampp/htdocs and was empty.
- Confirmed XAMPP Control Panel, PHP, and the MySQL client are installed.
- Executed PHP CLI successfully: PHP 8.2.12.
- User confirmed localhost and phpMyAdmin work without errors.
- Created admin, donor, organization, volunteer, includes, assets/css,
  assets/js, assets/images, uploads, and database folders.
- Created a responsive PHP welcome page with escaped HTML output.
- Disabled directory listings and blocked browser access to internal notes.
- Verification passed: PHP syntax check; welcome page HTTP 200 with expected
  text; includes/ and PROJECT_PROGRESS.md each returned HTTP 403.
- Reviewed the proposal's scope, requirements, acceptance criteria, and use cases.
  Treat the proposal as reference material and the pasted user prompt as instructions.
- Created food_donation_db on local XAMPP MariaDB 10.4.32 using schema.sql.
- Added five InnoDB tables with foreign keys, indexes, and basic check constraints.
- Added a unique generated-column index to prevent multiple approved requests
  for a donation, and uniqueness for organization requests and collection tasks.
- Documented role approval, transaction, expiry, cancellation, and delivery rules.
- Executed database checks: unique email, positive quantity, deadline ordering,
  donor foreign key, multiple pending requests, duplicate organization requests,
  duplicate approvals, and duplicate collection tasks. All passed.
- Rolled back all test records and verified all five tables are empty.
- Verified database/schema.sql returns HTTP 403 through Apache.
- Created protected configuration with local XAMPP defaults and optional
  FOOD_DB_* environment overrides; browser error display is disabled.
- Added reusable db() PDO connection with utf8mb4, exception mode, associative
  fetches, native prepared statements, and a five-second connection timeout.
- Set PHP timezone to Asia/Colombo and each MySQL session to +05:30.
- Updated the homepage to connect before rendering and show connection readiness.
- Connection failures return generic text and HTTP 503; logs contain only a code.
- Executed syntax checks on config.php, db.php, and index.php; all passed.
- Executed connection checks for reuse, PDO options, charset, selected database,
  PHP/MySQL timezones, disabled error display, and a prepared value round trip.
  All passed. PDO reports emulate-prepares as integer 0 on this installation.
- Simulated a missing database in an isolated CLI process; confirmed generic
  failure output and HTTP response code 503 without changing Apache settings.
- Homepage returned HTTP 200 with Database connection ready. Includes directory,
  config.php, db.php, and schema.sql each returned HTTP 403 when requested.
- Added shared session, escaping, CSRF, and form-input helpers. Session cookies
  use HttpOnly, SameSite=Lax, strict mode, and Secure when accessed over HTTPS.
- Added registration for donors, organizations, and volunteers only. Donors are
  active; organizations/volunteers are pending, regardless of posted status.
- Added validation, password_hash(), prepared inserts, unique-email handling,
  and an atomic transaction for organization user/profile creation.
- Added a responsive registration form with local Bootstrap 5.3.3 CSS, custom
  styling, conditional organization name, escaped values, and success redirects.
- Added a homepage registration link without removing existing readiness text.
- Executed PHP syntax checks on security.php, register.php, index.php, and the
  registration test script; all passed.
- Executed tests/registration.php through local Apache/MySQL. All checks passed:
  session flags/cache headers, CSRF variants, forged admin role, malformed fields,
  validation, escaped output, password secrecy/hashing, each role's approval
  status and organization profile, success redirects, token rotation, duplicates.
- Removed only uniquely named test accounts and linked test profiles after tests.
- Verified HTTP 403 for includes/security.php and tests/registration.php, plus
  HTTP 200 and preserved connection text/registration link on the homepage.
- Visually reviewed the registration form in the in-app browser and confirmed
  choosing Community organization reveals the organization-name field.
- User confirmed successful account creation before this task.
- Added login with password_verify(), generic invalid-credential feedback, active
  account checks, server-side role redirects, session ID regeneration, and new
  CSRF tokens on authentication. Signed-in users skip login/registration pages.
- Added a basic session-based five-minute cooldown after five wrong credentials.
- Added shared authentication guards that read role and status from the database
  per request, deny cross-role access, and revoke inactive/deleted accounts.
- Added POST-only CSRF-protected logout with session/cookie destruction.
- Added four minimal protected role landing pages with account details and sign
  out forms; full dashboard/module work remains for later planned stages.
- Linked login from homepage and registration, preserving readiness checks.
- Executed PHP syntax checks on all changed/new PHP files; all passed.
- Executed tests/auth.php: guest access, wrong/malformed credentials, CSRF,
  pending/rejected/suspended accounts, all four role redirects/access matrix,
  escaped names, regenerated/destroyed session replay rejection, logout tokens,
  role changes, account suspension, and session cooldown all passed.
- Reran tests/registration.php after integration; all checks passed.
- Cleaned up only temporary auth/registration test accounts, preserving user data.
- Visually reviewed the login page in the in-app browser.
- Added CLI-only initial administrator setup with validation, prepared insertion,
  password hashing, a transaction, and an advisory lock against setup races.
- Setup refuses existing administrator accounts and never promotes another role.
- Added isolated temporary-table tests for setup; validation, duplicate/admin
  restrictions, hashing, and transaction cleanup checks passed.
- Verified HTTP 403 for CLI setup, its internal helper, and the setup test script.
- Created the initial active demo admin admin@community.test using the CLI tool.
  Name/contact fields are explicit demonstration placeholders. The generated
  password was supplied in chat and is not saved in project files.
- Verified the actual administrator's login, protected dashboard, logout, and
  password hash. Existing user accounts were preserved.
- Updated that same admin account to admin@gmail.com and the user's requested
  password without changing its role or other accounts. Verified the new hash,
  successful login, and dashboard email. Plaintext password is not in project files.
- User confirmed administrator login success before Stage 4.
- Replaced the temporary setup welcome page with a responsive homepage: hero,
  original local basket SVG, community role cards, workflow steps, available-food
  previews/empty state, invitation panel, and shared footer.
- Added shared header/footer to homepage, login, registration, and role landing
  pages. Guests get sign-in/register links; authenticated users get their own
  dashboard link and CSRF-protected POST sign out. Added skip-to-content links.
- Available-food preview uses real database rows, escaping names and excluding
  expired, future, cancelled, approved/allocated donations and inactive donors.
  Public cards omit private pickup/delivery addresses and contact details.
- Executed PHP syntax checks on all changed/new PHP files; all passed.
- Reran authentication and registration suites after shared-navigation integration;
  both passed and cleaned up only their temporary accounts.
- Ran temporary homepage fixtures through local Apache/MySQL: eligible food and
  escaped names, expired/future/cancelled/allocated exclusions, private-data
  omission, authenticated role navigation, and homepage logout all passed.
  Removed all temporary homepage fixtures afterward.
- Visually reviewed desktop and narrow browser layouts, and verified the How it
  works navigation reaches its section. All assets are local; no new SQL needed.

- Added four role dashboard designs with four real statistic cards per role,
  responsive sidebars, recent-record tables, status badges, and account details.
- Admin dashboard includes real pending-account count and latest pending users.
  Approval actions remain in Stage 8; no nonfunctional action buttons were added.
- Dashboard queries scope donor/organization/volunteer data to the authenticated
  account. Admin receives system totals. Read failures show an error, not zeroes.
- Overdue uncollected food displays Expired without dashboard writes. Actual
  expiry transitions will be implemented with workflow actions in later stages.
- Executed syntax checks on dashboard.php, role_home.php, and dashboards.php.
- Executed tests/dashboards.php: role-specific counts, record ownership, overdue
  display, read-only behavior, admin totals/pending accounts, bounded recent lists,
  and safe status labels all passed. All fixtures were rolled back.
- Reran tests/auth.php after dashboard integration; all checks passed.
- Visually reviewed the actual admin dashboard and saved a screenshot. Existing
  accounts and donations were preserved. No SQL changes were required.

- Added donor/add_donation.php with text/category/quantity/unit/location/time
  validation, CSRF protection, and an optional photo. Ownership and Available
  status come from the server, ignoring submitted donor/status fields.
- Creation uses prepared statements and a transaction with a locked active-donor
  recheck; successful POST redirects, rotates CSRF, and shows a dashboard alert.
- Added image MIME/extension/size/dimension checks, GD re-encoding to JPEG,
  random filenames, resizing, and image cleanup after failed database operations.
- Added upload-folder Apache restrictions and homepage rendering of valid photos.
- Found PHP GD disabled during tests. With escalation approval, backed up php.ini,
  enabled the installed GD extension, and restarted the XAMPP Apache2.4 service.
  Backup: C:/xampp/php/php.ini.before-gd-0d62aa99cc514094a5ad219ce64ee88d.
- Executed syntax checks on all changed/new PHP files; all passed.
- Executed tests/donation_creation.php after enabling GD. Donor access, CSRF,
  validation, forged ownership/status, no-image/photo creation, token replay,
  dangerous/fake/oversized uploads, re-encoding, image serving, escaped homepage
  output, and pickup privacy checks all passed. Test records/files were removed.
- Reran tests/dashboards.php; counts, scoping and status checks all passed.
- Visually reviewed the donor form with a temporary account, then signed it out,
  removed that account, and restored the administrator session. Existing accounts
  and donations were preserved. No schema changes were needed.

- Added donor My donations with literal name search, status filters, completed
  history, twenty records per page, and owned detail pages including photos.
- Added shared donor sidebar links and dashboard links to owned donation details.
- Reused the creation form for editing with validation, CSRF, image preservation,
  replacement/removal, server-controlled owner/status, and content-version checks.
- Edit eligibility: Available, unexpired, and no request history. Recheck under
  transaction/row locks before saving; stale edits return HTTP 409.
- Added POST-only CSRF-protected cancellation with a confirmation prompt. Only
  unexpired Available/Requested donations without an approved request qualify.
  Pending requests and donation status change in one transaction; history remains.
- Existing photo files are removed only after a successful replacement/removal;
  a new uploaded image is removed when its database update fails.
- Executed syntax checks for all changed/new PHP files; all passed.
- Reran donation_creation.php and dashboards.php after shared-form changes; all
  checks passed and temporary records/images were cleaned up.
- Executed donation_management.php: pagination, filters/history, owner scoping,
  escaped details, validation/CSRF, owner/status forgery, stale edits, photo
  replacement/removal, eligibility, POST-only confirmation, and atomic cancellation
  checks all passed. Only test records/files were removed.
- Visually reviewed donor list/detail pages using a temporary donor and two demo
  donations, then removed those exact records and restored the admin session.
- No database schema changes were required.

- Added donor/profile.php for editing the logged-in donor's name, email, phone,
  and address with shared validation, CSRF, prepared queries, row locking, and
  a content-version check against stale forms.
- Email changes require the current password, normalize the email, handle unique
  constraints, and regenerate the session ID. Password, ID, role, and account
  status are never updated from profile fields.
- Added profile links in donor navigation and dashboard account details. Updated
  addresses prefill new donations; existing donation pickup locations remain.
- Executed PHP syntax checks on changed/new PHP files; all passed.
- Executed tests/profile.php through Apache/MySQL: authentication, role/owner
  protection, CSRF, validation, output escaping, forged fields, stale forms,
  duplicate emails, password checks, new-email login, and pickup preservation
  all passed. Temporary accounts and the test donation were removed.
- No schema changes or SQL import were required.

- Added paginated donor incoming-request lists with request-status and owned
  donation filters, plus protected organization/message/delivery detail pages.
- Added POST-only, CSRF-protected approval and rejection with confirmation prompts,
  account/ownership checks, account and donation/request locks, and transactions.
- Approval permits active organizations only, approves one request, rejects other
  pending requests, records decision times, and leaves the donation Requested
  until volunteer assignment. Existing approved decisions cannot be reversed.
- Rejection retains history and keeps Requested while another pending request
  exists, returning to Available after the last rejection. The pending-row check
  uses a locking current read to avoid stale transaction snapshots.
- Added Incoming requests navigation to donor sidebar/dashboard and a per-donation
  request link on donor donation details. No schema changes were needed.
- Executed syntax checks on all changed/new PHP files; all passed.
- Executed tests/donor_requests.php: authentication, owner/role checks, filters,
  escaped output, POST/CSRF checks, approval/rejection rules, inactive organizations,
  deadline/closed-state rejection, timestamps, and decision history all passed.
- Two independent competing approval workers produced exactly one approved request
  and one blocked decision. All request fixtures were cleaned up afterward.
- Reran tests/donation_management.php; all management checks passed and only test
  data/images were removed. Organization request creation UI follows in Stage 6.

- Fixed the user's Completed history navigation bug: completed-filter pages now
  use a dedicated history state and aria-current; My donations is not selected.
  Added regression checks and visually confirmed the corrected highlight.
- Added organization browsing with name/category/pickup filters, twelve results
  per page, real food cards/photos, and active/current/unallocated availability.
- Added role-protected donation details, owned-request status, and delivery/message
  submission. Allocated/closed details remain visible only to prior requesters;
  donor contact is shown only to the approved organization.
- Request submission locks participating accounts in ID order, organization,
  donation and request rows; validates active accounts and pickup window; rejects
  duplicate/allocated requests; inserts Pending and sets Requested atomically.
- Request identity/status comes from the authenticated organization, ignoring
  forged form IDs/status. CSRF, validation, escaping and safe errors are applied.
- Added organization dashboard/sidebar links to browse food and own request details.
- Executed syntax checks on all changed/new PHP files; all passed.
- Executed tests/organization_requests.php: filters/pagination, availability,
  access/privacy, validation/CSRF, duplicates and forged fields, two organizations
  requesting one donation, donor approval, competing rejection and own detail
  visibility all passed. Only test fixtures were removed.
- Reran donation_management.php (including selected-tab regression) and
  dashboards.php; both passed. No SQL schema changes were required.
- Visually checked fixed Completed history, organization food-name search and
  request form using temporary preview accounts; removed those exact accounts
  and demo donation, and restored the admin session. Existing data was preserved.

## Organization request history and tracking
- Added paginated My requests with food-name, request-status and delivery-status filters.
- Added Completed history scoped to donations approved for the signed-in organization.
- Added protected request tracking with recorded approval, assignment, collection,
  delivery and receipt timestamps. Unrecorded steps remain awaiting progress.
- Donor and volunteer contacts appear only for the organization's approved request.
- Linked tracking from the dashboard and food detail; selected navigation follows
  My requests or Completed history correctly.
- PHP syntax checks passed. Executed organization_tracking.php,
  organization_requests.php and dashboards.php; all passed. Tracking checks cover
  ownership/privacy, filters/pagination, escaping, recorded timestamps, navigation,
  closed/expired donations and read-only GET behavior. Only test fixtures were removed.
- No SQL migration is required. Receipt confirmation was added in Task 3;
  administrator assignment and volunteer updates follow in their role modules.

## Organization receipt confirmation
- Added an explicit receipt acknowledgement form on eligible request tracking pages.
- Added POST/CSRF-protected confirmation scoped to the active organization and its
  approved request. Requires both donation and collection task to be delivered,
  with a recorded delivery timestamp and no existing completion.
- Locks account, donation, request and task rows within a transaction before
  recording server-side completed_at and setting the donation to completed.
- Preserves request approval and volunteer delivery records. Duplicate submissions
  cannot replace the completion timestamp. Pickup deadline expiry does not prevent
  confirmation of food already delivered.
- Shows a success message, recorded receipt milestone and Completed history entry.
- PHP syntax checks passed. Executed receipt_confirmation.php and reran
  organization_tracking.php; all passed. Tests cover ownership/role protection,
  GET/CSRF/acknowledgement, invalid workflow states, missing tasks/timestamps,
  inactive accounts, forged fields, token rotation, duplicates and history.
- Removed only temporary test fixtures. No SQL migration is required.

## Organization profile editing
- Added My profile to organization navigation and dashboard with selected-tab state.
- Organizations can edit organization name/description and contact name/email/phone/address.
- Shared the existing donor profile controller/view to retain the same validation,
  escaping, CSRF, password-verified email changes, session/token rotation and role guards.
- Saves contact and organization fields in one transaction, scoped to the signed-in
  user. A combined content version rejects stale contact or organization edits.
- Existing request delivery addresses remain recorded; new requests use the updated
  address as their default. Role, status, password and ownership cannot be edited.
- PHP syntax checks passed. Executed organization_profile.php and profile.php;
  all passed, including malformed inputs, scoped changes, escaping, stale forms,
  duplicate-email rollback, optional description clearing and sign-in with new email.
- Removed only temporary test records. No SQL migration is required.

## Administrator account approvals (priority change)
- User identified that pending organization accounts could not test Stage 6 without
  an approval interface. Implemented this portion of Stage 8 before Stage 7.
- Added admin Account approvals with organization/volunteer filter, registration
  details, pagination and explicit review acknowledgement before approve/reject.
- Linked approvals from the admin dashboard and Pending accounts panel.
- POST/CSRF and active-admin checks protect decisions. A transaction locks accounts
  in ID order, allows only pending organization/volunteer targets, requires a linked
  organization profile before approval and prevents repeated decisions.
- Approval sets active; rejection sets rejected. Account identity, role, password
  and organization details are preserved. Existing users are not auto-approved.
- PHP syntax checks passed. Executed account_approvals.php and dashboards.php;
  all passed, including pending-login blocking, approval enabling organization
  profile access, volunteer approval, rejected-login blocking and access controls.
- Only temporary test accounts were removed. No SQL migration is required.

## Volunteer collections and administrator assignment
- Added My collection tasks with status/food-name filters, pagination and links
  from the volunteer dashboard to protected task details.
- Assigned volunteers can see food quantities, pickup window, donor contact/pickup
  location, receiving organization/contact and recorded request delivery address.
- Shows recorded assignment, acceptance, collection, delivery and receipt milestones.
  Closed/expired tasks display a warning and omit collection contact information.
- Added Admin > Assign collections so the new volunteer list is testable with real
  approved requests. Active volunteers can be selected for approved unassigned
  donations within their pickup window, ordered by deadline.
- Assignment is POST/CSRF protected. A transaction locks participating active
  accounts, donation, request and task records, inserts one assigned task and changes
  donation status to assigned. Role/status and assigned_by are server-controlled.
- Rejects duplicate, expired, future, closed, unapproved and inactive-party assignments.
- PHP syntax checks passed. Executed volunteer_tasks.php and dashboards.php; all
  passed. Coverage includes filters/pagination, ownership/privacy, escaping,
  recorded timestamps, read-only GET behavior, assignment validation and workflow.
- Only temporary test fixtures were removed. No SQL migration is required.

## Volunteer task updates
- Added eligible Accept task, Mark collected and Mark delivered forms with explicit
  acknowledgement, POST/CSRF protection, success messages and token rotation.
- Active volunteers can update only their own assigned tasks. Transactions lock
  account, donation, request and task in the established order and recheck state.
- Requires approved request and ordered assigned -> accepted -> collected -> delivered
  transitions with real prerequisite timestamps. Task timestamps come from the server.
- Acceptance leaves donation assigned; collection and delivery update both task and
  donation atomically. Duplicate/stale submissions cannot overwrite recorded timestamps.
- Acceptance/collection require an open pickup window. Delivery remains possible after
  pickup expiry for already collected food; organization receipt alone marks completed.
- PHP syntax checks passed. Executed volunteer_workflow.php, volunteer_tasks.php and
  receipt_confirmation.php; all passed. Full request-to-completed workflow passed,
  along with access/CSRF checks, skipped steps, closed states, expiry and repeat updates.
- Only temporary test records were removed. No SQL migration is required.

## Volunteer history and profile editing
- Added Completed task history for the signed-in volunteer's delivered tasks with
  recorded delivery timestamps and approved requests. Includes donations awaiting
  receipt and completed donations; receipt filters distinguish these states.
- Reuses task pagination and food-name search, shows delivered timestamps and keeps
  history/detail selected navigation correct. Other volunteers' records remain private.
- Added My profile for full name, email, phone and contact address using the shared
  profile implementation. Email changes require the current password; existing
  validation, ownership, CSRF, stale-form and session/token protections apply.
- Added dashboard/history/profile navigation and volunteer-specific address guidance.
- PHP syntax checks passed. Executed volunteer_tasks.php (including history tests),
  volunteer_profile.php, profile.php and organization_profile.php; all passed.
- Tests cover history membership/receipt filters, search, navigation, role/ownership,
  escaped values, stale profiles, protected account fields and verified email changes.
- Only temporary test records were removed. No SQL migration is required.

## Administrator registered-user management
- Added Registered users with name/email/organization search, role/status filters,
  pagination at 20, contact/registration details and selected navigation.
- Added active/suspended status badges and links from administrator navigation.
- Active administrators can suspend active donor/organization/volunteer accounts
  and reactivate suspended ones after explicit review. Administrator accounts are
  protected. Pending/rejected registrations cannot bypass the approval workflow.
- POST/CSRF and transaction locks protect changes; stale/repeated transitions fail.
  Reactivation requires a linked organization profile for organization accounts.
- Existing per-request account checks block suspended sign-in and protected access.
  Roles, passwords, identity and donation/request/task history are preserved.
- PHP syntax checks passed. Executed user_management.php, account_approvals.php and
  dashboards.php; all passed. Tests cover list filters/pagination, escaping, role
  guards, protected administrators, state rules, suspension/session blocking,
  reactivation and unchanged credentials. Only temporary test records were removed.
- No SQL migration is required.

## Volunteer Recent collection tasks navigation fix
- User reported the Recent collection tasks menu did not work. Its dashboard link
  previously targeted the existing in-page recent-records section without selecting
  a separate navigation item.
- Added volunteer/recent.php using the protected task list in latest-five mode.
  Dashboard and volunteer sidebar now link to it, with its own selected state.
- Preserves filtering and volunteer ownership; My collection tasks retains its
  full paginated list and Completed task history retains its receipt filters.
- PHP syntax checks and volunteer_tasks.php passed, including recent-page routing,
  five-task limit, selected state, guest protection and cross-volunteer privacy.
- Only temporary test records were removed. No SQL migration is required.

## Administrator donation and request overview
- Added All donations and Donation requests with food/donor/organization name
  searches, status filters, combined request/donation filters and pagination at 20.
- Donation list shows donor, quantity, request count and effective donation status;
  request list shows organization, request decision, task state and volunteer.
- Added protected donation details with donor/pickup information, all competing
  requests, recipient contacts/delivery addresses and recorded collection timelines.
- Approved-request timelines reuse organization tracking rules: no inferred collection
  or receipt timestamps, and rejected competitors do not inherit receipt confirmation.
- Added assignment links for approved, unassigned requested donations and navigation
  from admin dashboard/sidebar. Request rows link to their section of donation details.
- PHP syntax checks passed. Executed admin_overview.php and dashboards.php; all
  passed. Coverage includes role protection, pagination, filters, cross-donor data,
  competing requests, escaping, timestamp accuracy, empty states and read-only expiry.
- Only temporary test fixtures were removed. No SQL migration is required.

## Administrator reports and statistics
- Added Reports and statistics with all-time or inclusive start/end date filters,
  validated calendar dates and Sri Lanka time boundaries.
- Six summary cards count users by registration, donations by publication,
  requests by submission, tasks by assignment, recorded deliveries by delivery
  timestamp and confirmed receipts by confirmation timestamp.
- Added current-status breakdowns for donations, requests and assigned tasks,
  plus registrations by role. Period labels explain current versus historical state.
- Food received is summed by unit for donations completed in the selected period;
  kilograms, portions and other units are never combined.
- Reads totals and breakdowns in one transaction snapshot. Overdue uncollected food
  appears expired without changing database workflow records. Empty periods explain
  that no data exists, while failed report queries return generic errors.
- PHP syntax checks passed. Executed admin_reports.php and dashboards.php; all
  passed. Tests cover date boundaries, independent event dates, reconciling totals,
  separate units, all-time counts, admin-only access, invalid dates and read-only GET.
- Only temporary test fixtures were removed. No SQL migration is required.

## Cross-role workflow integration review
- Extended the real request/approval/assignment/accept/collect/deliver/receipt workflow
  test to render donor, organization, volunteer and admin pages at each transition.
- Verifies participant dashboard counters, removal of allocated food from browsing
  and homepage preview, read-only views and completed-history membership for all roles.
- Fixed volunteer dashboard recent updates to use the latest donation/task update,
  so receipt confirmation is reflected even though the task remains delivered.
- Fixed completed donor donation details to retain Completed history selection and
  link back to completed history rather than My donations.
- Updated database workflow notes to match read-only effective expiry: blocks future
  pickup actions while preserving recorded requests/tasks and allowing receipt after delivery.
- PHP syntax checks passed. Executed volunteer_workflow.php, dashboards.php,
  donation_management.php and donor_requests.php; all passed after the test helper
  import was corrected. Concurrent donor approvals still produce exactly one winner.
- Only temporary fixtures/images were removed. Existing records were preserved.
- Review identified planned administrator cancellation/reassignment controls not yet
  exposed. These are the next Stage 9 task; Stage 9 is not yet complete.
- No SQL migration is required.

## Administrator cancellation and reassignment
- Added cancellation and reassignment controls to Admin > All donations > View.
- Admin may cancel uncollected available/requested/assigned/expired food, including
  accepted tasks. One transaction closes pending/approved requests and assigned/
  accepted tasks, preserves existing decisions and collection records, and cancels
  the donation. Collected/delivered/completed/closed donations cannot be cancelled.
- Admin may select a different active volunteer only before task acceptance and
  within the pickup window. Requires active admin, donor and organization; locks
  participating accounts and donation/request/task records in the established order.
- Reassignment preserves the task ID/request, updates the unaccepted assignment
  and assignment timestamp, removes old-volunteer access and rejects stale forms.
- Forms require POST, CSRF and explicit acknowledgement. No records are deleted.
- PHP syntax checks passed. Executed admin_workflow.php, admin_overview.php and
  volunteer_workflow.php; all passed. Tests cover permissions, CSRF, invalid states,
  old-volunteer access, post-acceptance protection, atomic closure and preserved history.
- Only temporary fixtures were removed. No SQL migration is required.

## Administrator Recent donations and Pending accounts navigation fix
- Replaced dashboard-only anchors with working page navigation. Recent donations
  opens admin/recent.php (latest five matching donations); Pending accounts opens
  the existing account approval screen at admin/accounts.php.
- Admin dashboard now uses the shared admin sidebar so menu labels/destinations
  and selected state remain consistent across administrator pages.
- Pending accounts is the navigation label for the Account approvals screen.
- PHP syntax checks passed. admin_overview.php and account_approvals.php passed,
  including recent five-item limit, selected tabs, guest protection and approvals.
- Only temporary fixture records were removed. No SQL migration is required.

## Complete regression check and demonstration preparation
- Added tests/run_all.php: CLI-only PHP syntax verification and sequential execution
  of all 20 suites, with per-suite results, nonzero exit on failed checks and a detailed
  log in the Apache-protected tests directory. Requires PASS evidence in suite output
  as well as successful exit, guarding against early connection-helper exits.
- Executed the full suite against local Apache/MySQL: all 90 PHP files passed lint,
  all 20 test suites passed, and zero failed checks were reported.
- Suites cover registration, admin setup, authentication/roles, dashboards, uploads,
  donor management/decisions, profiles, organization requests/tracking/receipt,
  approvals/access management, volunteer workflows, reports and admin controls.
- Test fixtures/images were cleaned by their respective suites; existing data preserved.
- Added docs/DEMO_CHECKLIST.md with setup checks, browser-session guidance, a six-minute
  walkthrough, additional scenarios, layout checklist and the next sample-data plan.
- Updated administrator setup notes to reflect available administration screens.
- No persistent demo accounts or donation records were added in this task.
- Manual presentation-computer layout checks remain listed in the checklist; automated
  regression success does not imply that every page was visually reviewed in this task.

## Repeatable sample data and demonstration accounts
- Added database/sample_data.sql as the single demo-data source and a CLI loader
  database/seed_demo.php. SQL uses a transaction, setup lock, collision checks and
  a temporary setup procedure, removed afterward. No schema migration is required.
- Loaded 3 active fictional donor/organization/volunteer accounts, 1 organization,
  7 donations, 5 requests and 3 tasks into the local application database.
- Existing administrator and real records were preserved. Demo password hashes
  are bcrypt; credentials and scenario details are documented in database/DEMO_DATA.md.
- Dataset covers available food, pending request, approved/unassigned request,
  assigned task, delivered/awaiting receipt, completed history and effective expiry.
- Repeat setup adds nothing and preserves changed passwords, access and workflow
  progress. Conflicting/partial demo identities abort without replacing real users.
- Isolated seed tests use temporary tables; test real row counts remained unchanged.
  Live checks verified all three demo account logins and dashboard access.
- Added the isolated seed suite to run_all.php. Full run after live seed passed:
  93 PHP files linted, all 21 suites passed, zero failed checks. Demo records retained.
- Updated demonstration checklist with loaded-data instructions. Available sample
  deadlines are relative to first setup and are not reset by repeat imports.

## Diagrams and installation documentation
- Added root README.md describing implemented roles, workflow, technology,
  folder structure, tests and links to supporting project documents.
- Added docs/DIAGRAMS.md with editable Mermaid ER, use-case overview and basic
  architecture diagrams, including five-table relationships, allocation constraints,
  server-side role/workflow checks and database/photo/session storage.
- Added docs/INSTALLATION.md with existing-versus-fresh installation instructions,
  XAMPP/PHP extensions, schema import, connection configuration, secure CLI admin
  provisioning, optional sample setup, verification, troubleshooting and backup.
- Documented exact default test URL requirements, shared-tab sessions, GD uploads,
  current navigation names and demo deadline/repeat behavior.
- Checked local Markdown links and balanced code fences; all passed. Verified
  create_admin.php --help against the documented options. No application or database
  behavior changed; no database import was performed.
- Diagrams are Mermaid source embedded in Markdown, intended for a Mermaid-capable
  preview. This task did not generate separate PNG/PDF diagram exports.

## Midnight Navy / Warm Orange / Cream theme update
- Applied the user's requested palette through assets/css/style.css, preserving
  existing template classes with compatibility aliases and Bootstrap color overrides.
- Updated the homepage SVG's decorative background/brand colors. Only these two
  presentation assets changed; PHP/SQL/JS/Bootstrap sources remain unchanged.
- Kept existing layout, typography, dimensions, functionality and media breakpoints.
- Used navy text on orange buttons, readable muted text, semantic error colors and
  strong focus/border colors. Ten calculated text/control contrast pairs pass their
  4.5:1/3:1 thresholds; full accessibility audit is not claimed.
- Visually inspected public/auth pages, all four dashboards, donor form, organization
  receipt form and admin reports, including narrow/desktop homepage presentations.
- Existing regression runner passed: 93 PHP files linted, all 21 suites passed.
- SHA-256 verification found all 98 PHP/SQL/JS/Bootstrap files unchanged. Existing
  layout/typography declarations also compared unchanged.
- Added docs/THEME_CHANGE.md with exact paths, CSS changes, evidence and major-page
  verification steps, distinguishing actual checks from remaining manual combinations.

## Interview-ready README and developer credits
- Rewrote README.md as a portfolio/repository entry point with a clear problem and
  solution, four-role feature matrix, lifecycle rules, five-table design, engineering
  decisions, security, setup/demo instructions, testing evidence and scope limitations.
- Added all five developer credits supplied by the user: Dinesha Nawod, Bhashitha
  Dilshan, Chalindu Jayasooriya, Chenuka Hasith and Maneesha Pavitranjali. No individual
  responsibilities, external profiles or contribution claims were invented.
- Created docs/images/system-architecture.png (1800 x 1670), an editable SVG and
  docs/build_architecture.py. The diagram covers user roles, browser UI, Apache/PHP
  modules, shared guards/workflow helpers, PDO/transactions, sessions, GD, database,
  uploaded files and CLI tooling. Embedded the PNG with a repository-relative path.
- Added the existing homepage screenshot as a portable README interface preview.
- Visually inspected the generated architecture image and corrected connections.
  Verified PNG integrity, SVG XML, all 33 README links/anchors, balanced code fences
  and the five developer names. No PHP, SQL, CSS or application behavior changed.
- README test results explicitly refer to the prior 93-file/21-suite regression run;
  no new application test run was claimed for this documentation-only task.

## Short architecture-focused README
- Shortened README.md at the user's request. Retained the embedded architecture
  image, four architectural layers, role table, workflow, quick setup, test command
  and all five developer credits. Detailed instructions remain linked separately.
- Verified local document/image links. No application code or database changes.

## Files created
- docs/build_architecture.py
- docs/images/system-architecture.png
- docs/images/system-architecture.svg
- docs/images/interface-preview.png
- docs/THEME_CHANGE.md
- README.md
- docs/INSTALLATION.md
- docs/DIAGRAMS.md
- database/sample_data.sql
- database/seed_demo.php
- database/DEMO_DATA.md
- includes/demo_setup.php
- tests/demo_setup.php
- tests/run_all.php
- docs/.htaccess
- docs/DEMO_CHECKLIST.md
- admin/recent.php
- includes/admin_workflow.php
- admin/cancel_donation.php
- admin/reassign_volunteer.php
- tests/admin_workflow.php
- includes/admin_reports.php
- admin/reports.php
- tests/admin_reports.php
- includes/admin_records.php
- admin/donations.php
- admin/requests.php
- admin/donation.php
- tests/admin_overview.php
- volunteer/recent.php
- admin/users.php
- admin/change_access.php
- tests/user_management.php
- volunteer/history.php
- volunteer/profile.php
- tests/volunteer_profile.php
- volunteer/update_task.php
- tests/volunteer_workflow.php
- includes/volunteer_tasks.php
- includes/volunteer_sidebar.php
- volunteer/tasks.php
- volunteer/task.php
- admin/assignments.php
- admin/assign_volunteer.php
- tests/volunteer_tasks.php
- includes/admin_accounts.php
- includes/admin_sidebar.php
- admin/accounts.php
- admin/decide_account.php
- tests/account_approvals.php
- includes/profile_page.php
- organization/profile.php
- tests/organization_profile.php
- organization/confirm_receipt.php
- tests/receipt_confirmation.php
- includes/organization_requests.php
- organization/requests.php
- organization/request.php
- tests/organization_tracking.php
- includes/organization_donations.php
- includes/organization_sidebar.php
- organization/donations.php
- organization/donation.php
- organization/request_donation.php
- tests/organization_requests.php
- includes/donor_requests.php
- donor/requests.php
- donor/request.php
- donor/decide_request.php
- tests/donor_requests.php
- includes/profile.php
- donor/profile.php
- tests/profile.php
- includes/donor_donations.php
- includes/donor_sidebar.php
- donor/donations.php
- donor/donation.php
- donor/edit_donation.php
- donor/cancel_donation.php
- assets/js/donations.js
- tests/donation_management.php
- includes/donation_form.php
- donor/add_donation.php
- uploads/.htaccess
- uploads/donations/ (image storage folder)
- tests/donation_creation.php
- includes/dashboard.php
- tests/dashboards.php
- PROJECT_PROGRESS.md
- index.php
- .htaccess
- database/schema.sql
- database/.htaccess
- database/README.md
- includes/.htaccess
- includes/config.php
- includes/db.php
- includes/security.php
- register.php
- assets/css/bootstrap.min.css
- assets/css/style.css
- assets/js/register.js
- tests/.htaccess
- tests/registration.php
- tests/README.md
- includes/auth.php
- includes/role_home.php
- login.php
- logout.php
- donor/index.php
- organization/index.php
- volunteer/index.php
- admin/index.php
- tests/auth.php
- includes/admin_setup.php
- database/create_admin.php
- database/ADMIN_SETUP.md
- tests/admin_setup.php
- includes/header.php
- includes/footer.php
- assets/images/community-basket.svg

## Database tables created
- users
- organizations
- donations
- donation_requests
- collection_tasks

## Known issues
- Core donation/request/assignment/collection/delivery/receipt transitions are available.
- All automated regression checks passed. Manual presentation-computer layout checks
  and remaining Stage 10 deliverables are documented in docs/DEMO_CHECKLIST.md.
- Dedicated sample data and diagrams/installation guides are ready. Test-case
  documentation, the final presentation script and viva preparation remain.
- Database settings currently use this local XAMPP root account with no password;
  other installations must configure their own credentials.

## Next task
Wait for the user's NEXT before Stage 10 - Task 4:
prepare the test-case document, presentation demonstration script and viva questions/answers.

## README removed at user request
- Removed the root README, its architecture PNG/SVG, interface preview and image generator.
- Removed obsolete image links from docs/DIAGRAMS.md; retained the Mermaid diagrams.
- Application code and database unchanged.

