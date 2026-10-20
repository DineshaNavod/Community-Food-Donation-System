# Midnight Navy, Warm Orange and Cream theme

## Exact files changed

Website presentation changes:

1. `C:\xampp\htdocs\Community Food Donation System\assets\css\style.css`
2. `C:\xampp\htdocs\Community Food Donation System\assets\images\community-basket.svg`

The shared stylesheet applies to the public pages and all four roles. The SVG
change warms its decorative background and changes its brand symbols to navy;
natural food illustration colors remain. No PHP templates, backend logic, SQL,
database schema, JavaScript or Bootstrap distribution files were edited.

This document and PROJECT_PROGRESS.md record the work. The existing regression
runner updated its protected result log.

## Core CSS palette

The shared stylesheet begins with:

```css
:root {
    --primary: #172A45;
    --secondary: #F28C45;
    --background: #FFF7ED;
    --accent: #F4B860;
    --text: #334155;

    --green: var(--primary);
    --ink: var(--text);
    --cream: var(--background);
}
```

The compatibility aliases keep existing PHP class/variable names working. Additional
shared variables define warm surfaces, readable muted text, borders, focus states
and semantic warning/error tints. Hardcoded green UI colors now reference these tokens.

Main component treatments include:

```css
.btn-community {
    background: var(--secondary);
    border: 1px solid var(--primary);
    color: var(--primary);
}
.btn-community:hover,
.btn-community:focus-visible {
    background: var(--accent);
    border-color: var(--primary);
    color: var(--primary);
}
.account-sidebar a[aria-current="page"] {
    background: var(--secondary);
    color: var(--primary);
}
.status-green {
    background: var(--primary);
    color: var(--background);
}
```

These snippets explain the color changes; retain the complete stylesheet's existing
padding, typography and responsive rules. Bootstrap overrides in that file also
theme success/outline buttons, success badges, alerts, tables, form controls and
checkboxes. `.text-secondary` remains a readable muted text color rather than
using bright orange as small body text. Destructive controls retain a dark red-brown
semantic color. Status words and all functional class names remain unchanged.

## Verification actually performed

- Full existing regression runner: **93 PHP files passed lint; all 21 suites passed**.
  Detailed output is in `tests/latest-results.log`.
- SHA-256 comparison: all **98 PHP/SQL/JavaScript/Bootstrap files unchanged**.
- Compared existing layout/typography declarations against the previous stylesheet:
  no changes to their dimensions, grids, spacing, fonts or responsive behavior.
- Browser visual inspection: homepage at a narrow width of about 515 px and desktop
  around 1265 px; login; registration; all four dashboards; donor add-food form;
  organization delivered-request/receipt form; admin reports/date filters.
- Browser checks used existing accounts and did not submit donations, receipt
  confirmations, profile changes or administrative workflow actions.
- Saved screenshots include `navy-orange-home-desktop.png`, `navy-orange-home.png`
  and dashboard previews in the task's visualization directory.

Calculated contrast for the declared color pairs:

| Pair | Ratio |
| --- | --- |
| Body text on cream | 9.75:1 |
| Navy text on warm-orange buttons/selected menu | 5.93:1 |
| Navy text on accent hover | 8.17:1 |
| Cream text on navy sidebar/success badge | 13.61:1 |
| Muted text on warm surface | 6.31:1 |
| Dark-orange heading on cream | 6.29:1 |
| Warning text on orange tint | 5.49:1 |
| Error text on error tint | 5.85:1 |
| Progress badge text on navy tint | 12.28:1 |
| Form border against its surface | 3.07:1 |

These checked pairs exceed 4.5:1 for normal text and 3:1 for the input border.
This is targeted contrast verification, not a claim of a complete accessibility audit.
Focus outlines use navy on light surfaces and accent on navy surfaces.

## Major-page verification checklist

Hard-refresh with Ctrl+F5 first. Use the existing demo accounts and administrator.
The following steps cover remaining page combinations as well as repeat checks
for the pages visually inspected above; they are not claims that every listed
page/state was visually inspected during this change.

| Page(s), relative to project URL | Check |
| --- | --- |
| `index.php` | Cream background, navy headings, orange calls to action, warm illustration backdrop; scroll through roles, available food and footer. |
| `login.php` | Navy welcome panel, cream text, readable inputs and orange sign-in button; keyboard focus visible. |
| `register.php` | Switch all three registration roles; organization field appears as before. Check labels, help text, password fields and button. |
| `donor/index.php` | Navy sidebar, orange selected item/stat borders, readable recent-donation table and status badges. |
| `donor/add_donation.php`, `donor/edit_donation.php?id=...` | Focus inputs/selects, choose a file without submitting, inspect date fields, save/cancel buttons and invalid-feedback colors. |
| `donor/donations.php`, `?status=completed` | Filters, pagination, table scroll and correct selected history menu. |
| `donor/donation.php?id=...` | Food/image card, badges and cancellation outline. No action submission needed for color review. |
| `donor/requests.php`, `donor/request.php?id=...` | Request table, details and approve/reject controls remain visually distinct. |
| `donor/profile.php` | Contact form and current-password field; focus/validation styling. |
| `organization/index.php` | Same dashboard palette and readable request/donation status pairs. |
| `organization/donations.php`, `organization/donation.php?id=...` | Search/category filters, food cards and request address/message form. |
| `organization/requests.php`, `?delivery=completed` | Table and filters, completed-history selection, pagination. |
| `organization/request.php?id=...` | Tracking dots, recorded dates, receipt checkbox and orange confirmation button. |
| `organization/profile.php` | Organization description/contact form uses the shared input and button colors. |
| `volunteer/index.php`, `volunteer/recent.php` | Dashboard/recent tasks and selected navigation remain consistent. |
| `volunteer/tasks.php`, `volunteer/history.php` | Task and receipt-status filters, table, pagination and badges. |
| `volunteer/task.php?id=...` | Readable pickup/delivery details, timeline and existing action/acknowledgement controls. |
| `volunteer/profile.php` | Same profile field/focus styling as donor and organization profiles. |
| `admin/index.php`, `admin/recent.php` | Navy/orange navigation, statistics, recent table and selected menu. |
| `admin/accounts.php` | Pending-account cards, acknowledgement checkbox and visually distinct approval/rejection buttons. |
| `admin/users.php` | Search/filter controls, account details, status badges and suspend/reactivate controls. |
| `admin/donations.php`, `admin/requests.php` | Overview tables, filters and navigation. |
| `admin/donation.php?id=...` | Food/request cards, progress timeline, cancellation and reassignment controls when eligible. |
| `admin/assignments.php` | Volunteer select and orange assignment button. |
| `admin/reports.php` | Date filters, statistic cards, status tables and separate quantity units. |

At desktop and narrow widths, check header wrapping, sidebar stacking, form columns
and horizontal scrolling inside wide tables. The original 991 px and 575 px media
breakpoints were retained. Also use Tab to inspect focus and inspect hover/disabled
states. Exact 390 px/768 px coverage and every possible error/disabled state were
not exhaustively visually tested during this change.
