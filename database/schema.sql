SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(120) NOT NULL UNIQUE,
  display_name  VARCHAR(80)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  last_login_at DATETIME(3)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id    INT UNSIGNED NOT NULL,
  receiver_id  INT UNSIGNED NOT NULL,
  body         TEXT         NOT NULL,
  created_at   DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),   -- waktu SERVER CLOUD
  delivered_at DATETIME(3)  NULL,
  read_at      DATETIME(3)  NULL,
  origin_node  VARCHAR(50)  NULL,                                    -- nama app server pengirim (demo)
  FOREIGN KEY (sender_id)   REFERENCES users(id),
  FOREIGN KEY (receiver_id) REFERENCES users(id),
  INDEX idx_conv (sender_id, receiver_id, id),
  INDEX idx_inbox (receiver_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backup_logs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requested_by  INT UNSIGNED NOT NULL,
  type          ENUM('backup','restore') NOT NULL,
  filename      VARCHAR(255) NULL,
  status        ENUM('pending','success','failed') NOT NULL,
  size_bytes    BIGINT UNSIGNED NULL,
  message       VARCHAR(255) NULL,
  created_at    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
