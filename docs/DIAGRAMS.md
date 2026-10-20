# System diagrams

These Mermaid diagrams describe the implemented five-table PHP application.
Open this Markdown file in a Mermaid-capable preview to view them. Diagram source
is included directly in the file so it can be edited and copied into project documentation.

## Entity relationship diagram

```mermaid
erDiagram
    USERS ||--o| ORGANIZATIONS : "organization profile"
    USERS ||--o{ DONATIONS : "donor publishes"
    ORGANIZATIONS ||--o{ DONATION_REQUESTS : submits
    DONATIONS ||--o{ DONATION_REQUESTS : receives
    DONATION_REQUESTS ||--o| COLLECTION_TASKS : "has assignment"
    USERS ||--o{ COLLECTION_TASKS : "volunteer performs"
    USERS ||--o{ COLLECTION_TASKS : "admin assigns"

    USERS {
        int id PK
        string full_name
        string email UK
        string password_hash
        string phone
        string address
        enum role
        enum status
        datetime created_at
        datetime updated_at
    }
    ORGANIZATIONS {
        int id PK
        int user_id FK,UK
        string organization_name
        text description
        datetime created_at
    }
    DONATIONS {
        int id PK
        int donor_id FK
        string food_name
        enum category
        text description
        decimal quantity
        enum quantity_unit
        string pickup_location
        datetime available_at
        datetime deadline_at
        string image_path
        enum status
        datetime completed_at
        datetime created_at
        datetime updated_at
    }
    DONATION_REQUESTS {
        int id PK
        int donation_id FK
        int organization_id FK
        text message
        string delivery_address
        enum status
        datetime decided_at
        int approved_donation_id UK "generated, nullable"
        datetime created_at
        datetime updated_at
    }
    COLLECTION_TASKS {
        int id PK
        int request_id FK,UK
        int volunteer_id FK
        int assigned_by FK
        enum status
        datetime assigned_at
        datetime accepted_at
        datetime collected_at
        datetime delivered_at
        datetime updated_at
    }
```

`users` stores all four roles. Role restrictions on donor_id, volunteer_id and
assigned_by are checked in PHP; the foreign keys alone establish user existence.
One organization profile belongs to one organization user. A request is unique
for the pair (donation_id, organization_id). Its generated approved_donation_id
is populated only when approved; a unique index permits one approved request per
donation while allowing multiple pending requests. A request has at most one task.
Reassignment updates that task rather than inserting a duplicate.

## Use-case overview

Mermaid flowchart notation is used for actors, oval use cases and the system boundary.
Associations show which role can perform each use case; they do not represent sequence.

```mermaid
flowchart LR
    Donor[Food donor]
    Org[Community organization]
    Vol[Collection volunteer]
    Admin[Administrator]
    subgraph System[Community Food Donation Coordination System]
        Login([Sign in and sign out])
        Profile([Edit own profile])
        Publish([Publish and manage own donations])
        Review([Approve or reject food requests])
        Request([Browse and request available food])
        Receipt([Track progress and confirm receipt])
        Deliver([Accept task and record collection or delivery])
        History([View own history])
        Accounts([Approve registrations and manage user access])
        Assign([Assign or reassign volunteers])
        Cancel([Cancel uncollected donations])
        Monitor([Monitor records and view reports])
    end
    Donor --- Login
    Org --- Login
    Vol --- Login
    Admin --- Login
    Donor --- Profile
    Org --- Profile
    Vol --- Profile
    Donor --- Publish
    Donor --- Review
    Org --- Request
    Org --- Receipt
    Vol --- Deliver
    Donor --- History
    Org --- History
    Vol --- History
    Admin --- Accounts
    Admin --- Assign
    Admin --- Cancel
    Admin --- Monitor
```

Public registration supports donors, organizations and volunteers. Organization
and volunteer registrations require approval; administrator provisioning is CLI-only.
All protected actions also require the correct role, ownership, current workflow
state and a valid CSRF token. Administrator profile editing is not implemented.

## Basic system architecture

```mermaid
flowchart TB
    Browser[Browser: HTML, Bootstrap CSS, custom CSS and JavaScript]
    subgraph XAMPP[Local XAMPP server]
        Apache[Apache HTTP server]
        Public[Homepage and authentication pages]
        Roles[Donor, organization, volunteer and admin PHP pages]
        Guards[Session, role, ownership, CSRF and validation checks]
        Services[Shared PHP workflow helpers]
        PDO[PDO prepared statements and transactions]
        DB[(MariaDB or MySQL: five InnoDB tables)]
        Upload[GD image validation and JPEG re-encoding]
        Photos[(uploads/donations: generated JPEG files)]
        Session[(Server-side PHP sessions)]
    end
    Browser <-->|HTTP requests and rendered responses| Apache
    Apache --> Public
    Apache --> Roles
    Public --> Guards
    Roles --> Guards
    Guards <--> Session
    Guards --> Services
    Services --> PDO
    PDO <--> DB
    Services --> Upload
    Upload --> Photos
    Apache -->|Validated image names only| Photos
    CLI[CLI: administrator setup, demo SQL loader and tests] --> PDO
```

Pages render HTML on the server; this is not a single-page application. Photo
files are stored on disk, while their paths are stored in donations.image_path.
Internal source, SQL, tests and documentation directories are blocked through
Apache .htaccess files. PHP files in role directories enforce authentication and
authorization before rendering or changing data. CLI fixtures use separate
temporary records/tables and preserve real application history.
