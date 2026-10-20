-- ADDITIVE migration. Run against your EXISTING Service Desk database.
-- Do NOT import deskpri.sql or rerun setup.php when upgrading.
CREATE TABLE IF NOT EXISTS google_connections (
    owner INT UNSIGNED NOT NULL PRIMARY KEY,
    refresh_token TEXT NOT NULL,
    account_name VARCHAR(100) NOT NULL DEFAULT '',
    location_name VARCHAR(100) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS google_post_operations (
    owner INT UNSIGNED NOT NULL,
    operation_id CHAR(36) NOT NULL,
    state VARCHAR(20) NOT NULL,
    post_name VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (owner, operation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
