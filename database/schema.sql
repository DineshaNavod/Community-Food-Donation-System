-- Community Food Donation Coordination System
-- Compatible with MySQL 8+ and XAMPP MariaDB 10.4+.
-- Creates tables only; never drops existing tables or records.
CREATE DATABASE IF NOT EXISTS food_donation_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE food_donation_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(25) NOT NULL,
    address VARCHAR(255) NOT NULL,
    role ENUM('donor', 'organization', 'volunteer', 'admin') NOT NULL,
    status ENUM('pending', 'active', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    organization_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organizations_user (user_id),
    CONSTRAINT fk_organizations_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS donations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id INT UNSIGNED NOT NULL,
    food_name VARCHAR(150) NOT NULL,
    category ENUM('cooked_meals', 'bakery', 'produce', 'dry_goods', 'dairy', 'other') NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    quantity_unit ENUM('portions', 'kg', 'litres', 'items', 'packs') NOT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    available_at DATETIME NOT NULL,
    deadline_at DATETIME NOT NULL,
    image_path VARCHAR(255) NULL,
    status ENUM('available', 'requested', 'assigned', 'collected', 'delivered',
        'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'available',
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_donations_donor_status (donor_id, status),
    KEY idx_donations_status_deadline (status, deadline_at),
    KEY idx_donations_category (category),
    CONSTRAINT fk_donations_donor FOREIGN KEY (donor_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_donations_quantity CHECK (quantity > 0),
    CONSTRAINT chk_donations_times CHECK (deadline_at > available_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS donation_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donation_id INT UNSIGNED NOT NULL,
    organization_id INT UNSIGNED NOT NULL,
    message TEXT NULL,
    delivery_address VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Only approved rows get a value. UNIQUE permits multiple NULLs,
    -- so there may be many pending requests but only one approval per donation.
    approved_donation_id INT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN status = 'approved' THEN donation_id ELSE NULL END) STORED,
    UNIQUE KEY uq_request_organization (donation_id, organization_id),
    UNIQUE KEY uq_one_approved_request (approved_donation_id),
    KEY idx_requests_organization_status (organization_id, status),
    KEY idx_requests_donation_status (donation_id, status),
    CONSTRAINT fk_requests_donation FOREIGN KEY (donation_id)
        REFERENCES donations (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_requests_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS collection_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    volunteer_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    status ENUM('assigned', 'accepted', 'collected', 'delivered', 'cancelled')
        NOT NULL DEFAULT 'assigned',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    collected_at DATETIME NULL,
    delivered_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_task_request (request_id),
    KEY idx_tasks_volunteer_status (volunteer_id, status),
    CONSTRAINT fk_tasks_request FOREIGN KEY (request_id)
        REFERENCES donation_requests (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_tasks_volunteer FOREIGN KEY (volunteer_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_tasks_admin FOREIGN KEY (assigned_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;
