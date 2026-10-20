<div align="center">

# 🍽️ Community Food Donation Coordination System

### Connecting Surplus Food with Communities in Need

**A web-based food donation coordination platform | University Software Engineering Group Project**

![PHP](https://img.shields.io/badge/PHP-Backend-777BB4?logo=php&logoColor=white) ![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql&logoColor=white) ![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white) ![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?logo=javascript&logoColor=black) ![Status](https://img.shields.io/badge/Project-Academic-172A45)

</div>

---

## 📖 Overview

The **Community Food Donation Coordination System** is a web-based application designed to connect food donors, community organizations, and collection volunteers through one centralized platform. It aims to help reduce food waste, improve donation coordination, and support the distribution of surplus food to communities in need.

The platform provides role-specific features for donors, community organizations, collection volunteers, and administrators. It supports the donation process from food listing and requests through collection, delivery, and receipt confirmation.

## 🎯 Objectives

- Reduce avoidable food waste by making surplus food easier to share.
- Help community organizations discover and request available donations.
- Coordinate food collection and delivery through volunteers.
- Provide visibility into donation and request statuses.
- Support organized administration and recordkeeping.

## ✨ Key Features

| User Role | Main Capabilities |
|---|---|
| **Food Donor** | Register and sign in; manage profile; add, edit, or cancel donations; review and approve or reject requests; track donation status and history. |
| **Community Organization** | Register and sign in; manage organization profile; browse and search donations; submit requests; track request status; confirm receipt. |
| **Collection Volunteer** | Register and sign in; view assigned tasks and pickup/delivery details; update collection and delivery statuses; view task history. |
| **Administrator** | Sign in; manage users and approvals; monitor donations and requests; assign volunteers; review reports and activity. |

> **Implementation note:** The table describes the planned system scope. Update it to reflect the features actually completed and tested before final submission.

## 🏗️ System Architecture

The project uses a **three-tier web application architecture**:

1. **Presentation layer:** HTML5, CSS3, JavaScript, and Bootstrap 5 provide the user interface in a browser.
2. **Application layer:** PHP handles application logic, authentication, authorization, donations, requests, and volunteer workflows.
3. **Data layer:** MySQL stores application data such as users, donations, requests, and collection tasks.

<div align="center">

![System Architecture Diagram](images/system-architecture.png)

*Figure 1. System Architecture — Community Food Donation Coordination System*

</div>

## 👥 UML Use Case Diagram

The use case diagram illustrates how the system's four actors interact with the application: **Food Donor**, **Community Organization**, **Collection Volunteer**, and **Administrator**.

<div align="center">

![UML Use Case Diagram](images/use-case-diagram.png)

*Figure 2. UML Use Case Diagram — Community Food Donation Coordination System*

</div>

## 🔄 Donation Workflow

```text
Food Donor Lists Food
         ↓
Community Organization Requests Food
         ↓
Food Donor Reviews Request
         ↓
Volunteer Is Assigned
         ↓
Food Is Collected
         ↓
Food Is Delivered
         ↓
Organization Confirms Receipt
         ↓
Donation Completed
```

A typical status progression is **Available → Requested → Assigned → Collected → Delivered → Completed**. Requests may also be rejected or donations cancelled, subject to the application's rules.

## 🛠️ Technology Stack

| Area | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| UI Framework | Bootstrap 5 |
| Backend | PHP |
| Database | MySQL |
| Local Server | XAMPP (Apache and MySQL) |
| Version Control | Git and GitHub |

## 📁 Project Structure

The structure below is an **illustrative layout**. Adjust directory names to match the actual repository.

```text
community-food-donation-system/
├── admin/                 # Administrator pages
├── donor/                 # Donor pages
├── organization/          # Organization pages
├── volunteer/             # Volunteer pages
├── includes/              # Shared PHP components
├── assets/
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── images/            # Website assets
├── database/              # Database schema / safe sample data
├── images/                # README documentation diagrams
│   ├── system-architecture.png
│   └── use-case-diagram.png
├── index.php
└── README.md
```


## 🔒 Security Features

- Secure Password Hashing
- Authentication and Session Management
- Role-Based Access Control (RBAC)
- SQL Injection Prevention
- Cross-Site Scripting (XSS) Protection
- Cross-Site Request Forgery (CSRF) Protection
- Server-Side Input Validation
- Secure Database Configuration
- Safe Error Handling
- File Upload Validation
- HTTPS and Secure Cookies
- Authorization Checks for Records


## 🎓 Academic Information

- **Project:** Community Food Donation Coordination System
- **Project Category:** Software Engineering Group Project
- **University:** University of Ruhuna
- **Faculty:** Faculty of Technology
- **Degree Programme:** Bachelor of Information and Communication Technology (Honours)

## 👨‍💻 Development Team

- **Dinesha Navod**
- **Bhashitha Dilshan**
- **Chalindu Jayasooriya**
- **Chenuka Hasith**
- **Maneesha Pavitranjali**


<div align="center">

**Community Food Donation Coordination System**  
*Reducing Food Waste • Supporting Communities • Improving Coordination*

</div>
