CREATE DATABASE IF NOT EXISTS coalizao_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE coalizao_db;

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    company_name VARCHAR(180) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    document VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state CHAR(2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clients_name (name),
    KEY idx_clients_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    cpf VARCHAR(14) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    role VARCHAR(100) NOT NULL,
    status ENUM('active', 'on_leave', 'inactive') NOT NULL DEFAULT 'active',
    admission_date DATE NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_cpf (cpf),
    KEY idx_employees_status (status),
    KEY idx_employees_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    service_type ENUM('security', 'concierge', 'monitoring', 'access_control', 'other') NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NULL,
    state CHAR(2) NULL,
    status ENUM('active', 'paused', 'closed') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_posts_client (client_id),
    KEY idx_service_posts_status (status),
    CONSTRAINT fk_service_posts_client FOREIGN KEY (client_id) REFERENCES clients (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    shift VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_assignments_post (post_id),
    KEY idx_post_assignments_employee (employee_id),
    CONSTRAINT fk_post_assignments_post FOREIGN KEY (post_id) REFERENCES service_posts (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_post_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(160) NOT NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(30) NULL,
    service_type ENUM('security', 'concierge', 'monitoring', 'access_control', 'other') NOT NULL,
    quantity_posts INT UNSIGNED NOT NULL DEFAULT 1,
    billing_period ENUM('monthly', 'eventual') NOT NULL DEFAULT 'monthly',
    estimated_amount DECIMAL(12,2) NULL,
    status ENUM('draft', 'under_review', 'sent', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quotes_client (client_id),
    KEY idx_quotes_status (status),
    KEY idx_quotes_created_at (created_at),
    CONSTRAINT fk_quotes_client FOREIGN KEY (client_id) REFERENCES clients (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NULL,
    post_id BIGINT UNSIGNED NULL,
    contract_number VARCHAR(50) NOT NULL,
    title VARCHAR(180) NOT NULL,
    service_type ENUM('security', 'concierge', 'monitoring', 'access_control', 'other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    renewal_date DATE NULL,
    monthly_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('active', 'pending', 'expiring', 'expired', 'renewed', 'cancelled') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contracts_number (contract_number),
    KEY idx_contracts_client (client_id),
    KEY idx_contracts_post (post_id),
    KEY idx_contracts_status (status),
    KEY idx_contracts_dates (start_date, end_date),
    CONSTRAINT fk_contracts_client FOREIGN KEY (client_id) REFERENCES clients (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_contracts_post FOREIGN KEY (post_id) REFERENCES service_posts (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    details VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contract_history_contract (contract_id),
    KEY idx_contract_history_created_at (created_at),
    CONSTRAINT fk_contract_history_contract FOREIGN KEY (contract_id) REFERENCES contracts (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financial_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_at DATETIME NULL,
    status ENUM('pending', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_financial_entries_contract (contract_id),
    KEY idx_financial_entries_due_date (due_date),
    KEY idx_financial_entries_type_status (type, status),
    CONSTRAINT fk_financial_entries_contract FOREIGN KEY (contract_id) REFERENCES contracts (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_financial_entries_client FOREIGN KEY (client_id) REFERENCES clients (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'answered', 'archived') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_requests_status (status),
    KEY idx_contact_requests_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_password_overrides (
    id TINYINT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_password_resets_token_hash (token_hash),
    KEY idx_admin_password_resets_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS job_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_applications_created_at (created_at)
) ENGINE=InnoDB;

INSERT INTO clients (name, company_name, email, phone, address, city, state)
SELECT 'Condomínio Aurora', 'Condomínio Aurora', NULL, NULL, 'Rua das Palmeiras, 102', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE company_name = 'Condomínio Aurora');

INSERT INTO clients (name, company_name, email, phone, address, city, state)
SELECT 'Grupo Horizonte', 'Grupo Horizonte', NULL, NULL, 'Av. Central, 450', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE company_name = 'Grupo Horizonte');

INSERT INTO clients (name, company_name, email, phone, address, city, state)
SELECT 'Residencial Mirante', 'Residencial Mirante', NULL, NULL, 'Rua do Lago, 88', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE company_name = 'Residencial Mirante');

INSERT INTO service_posts (client_id, name, service_type, address, status)
SELECT c.id, 'Portaria Ed. Aurora', 'concierge', c.address, 'active'
FROM clients c
WHERE c.company_name = 'Condomínio Aurora'
    AND NOT EXISTS (SELECT 1 FROM service_posts WHERE name = 'Portaria Ed. Aurora');

INSERT INTO service_posts (client_id, name, service_type, address, status)
SELECT c.id, 'Vigilância Grupo Horizonte', 'security', c.address, 'active'
FROM clients c
WHERE c.company_name = 'Grupo Horizonte'
    AND NOT EXISTS (SELECT 1 FROM service_posts WHERE name = 'Vigilância Grupo Horizonte');

INSERT INTO service_posts (client_id, name, service_type, address, status)
SELECT c.id, 'Monitoramento Residencial Mirante', 'monitoring', c.address, 'active'
FROM clients c
WHERE c.company_name = 'Residencial Mirante'
    AND NOT EXISTS (SELECT 1 FROM service_posts WHERE name = 'Monitoramento Residencial Mirante');
