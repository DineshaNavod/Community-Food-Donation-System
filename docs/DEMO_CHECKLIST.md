# University demonstration checklist

## Before the presentation

1. Start Apache and MySQL in XAMPP.
2. Open http://localhost/Community%20Food%20Donation%20System/.
3. Check the homepage, registration and login pages.
4. Prepare separate donor, organization and volunteer demonstration accounts.
   Organization and volunteer registrations require Admin > Pending accounts approval.
5. Keep the existing administrator account and real user data unchanged.
6. Use different browser profiles or private windows for different roles. Tabs in
   one browser profile share a login session; signing in in another tab changes
   the role for all those tabs.
7. Use a donation with a pickup deadline comfortably after the presentation.
   Recheck the date/time on the demonstration computer.

## Verify the application

From PowerShell in `C:\xampp\htdocs\Community Food Donation System`:

```powershell
& 'C:\xampp\php\php.exe' tests\run_all.php
```

Expect a successful PHP syntax summary and PASS for all 21 suites. Detailed output
is saved in `tests/latest-results.log`. The tests create and remove only their own
fixture records and images. Avoid running them during the live demonstration.

## Main demonstration: approximately six minutes

1. **Homepage:** explain donors, organizations, volunteers and administration.
2. **Donor:** publish food with quantity, pickup location, availability and deadline.
   Optionally upload an image. Show it in My donations.
3. **Organization:** browse available food and send a request with a delivery address.
4. **Donor:** open Incoming requests and approve the receiving organization.
   Explain that only one organization can be approved for a donation.
5. **Administrator:** open Assign collections and choose an approved active volunteer.
6. **Volunteer:** open My collection tasks > View. Accept, mark collected and mark
   delivered, acknowledging each real-world step.
7. **Organization:** open My requests > View and confirm receipt.
8. **History and reports:** show Completed history, volunteer Completed task history,
   the administrator donation details and Reports and statistics.

## Short additional examples

- Pending organization/volunteer accounts cannot log in before approval.
- A volunteer cannot view another volunteer's task URL.
- Admin may reassign an unaccepted task. The former volunteer loses access.
- Admin may cancel before collection; related open requests/tasks close together.
- Overdue uncollected food shows Expired and cannot be requested or collected.
- Delivery and receipt may continue after the pickup deadline once food is collected.
- Changing an account email requires its current password.
- Suspended accounts cannot sign in or use protected pages while suspended.

## Layout check on the presentation computer

- Check menu selection for Recent donations, Pending accounts and Completed history.
- Check narrow/mobile layout: sidebar and cards stack; tables scroll horizontally.
- Confirm uploaded images fit their cards and form errors remain readable.
- Open a page with no matching records to show its empty-state message.

## Sample data

The local demo data is loaded. See database/DEMO_DATA.md for the three demo logins,
shared demo password, sample scenarios and repeatable SQL/CLI setup instructions.
The existing administrator and real records were preserved. The available sample
food has a two-day pickup window from first setup; create a new donation for a
later presentation rather than expecting a repeat import to reset progress.

Diagrams and installation instructions are available in docs/DIAGRAMS.md and
docs/INSTALLATION.md. The test-case document, final demonstration script and viva
preparation follow in the next Stage 10 task.
