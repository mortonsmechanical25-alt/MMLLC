-- Add to the EXISTING Service Desk database. Do not reimport the original dump.
CREATE TABLE IF NOT EXISTS desk_memberships (
  user_id INT UNSIGNED NOT NULL,
  workspace_owner INT UNSIGNED NOT NULL,
  role ENUM('staff','viewer') NOT NULL DEFAULT 'viewer',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id),
  KEY workspace_owner(workspace_owner),
  CONSTRAINT desk_member_user FOREIGN KEY(user_id) REFERENCES users(id),
  CONSTRAINT desk_member_workspace FOREIGN KEY(workspace_owner) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
