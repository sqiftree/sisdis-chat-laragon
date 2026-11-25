-- Seed akun kelompok & akun demo (password default: sisdis123)
-- Hash dihasilkan menggunakan bcrypt ($2y$10$), kompatibel dengan password_verify() PHP.

INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'laura', 'laura@sisdis.local', 'Laura', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'user'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'laura');

INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'giska', 'giska@sisdis.local', 'Giska', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'user'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'giska');

INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'wawa', 'wawa@sisdis.local', 'Wawa', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'user'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'wawa');

INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'yashif', 'yashif@sisdis.local', 'Yashif (Cloud DB)', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'yashif');

INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'wantech', 'wantech@sisdis.local', 'Wantech (Backup PC)', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'wantech');

-- Aktifkan akun demo Fase 1 (ayu, bima, citra) bila sebelumnya masih berstatus 'disabled'
INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'ayu', 'ayu@example.test', 'Ayu', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'user'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'ayu');

INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'bima', 'bima@example.test', 'Bima', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'user'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'bima');

INSERT INTO users (username, email, display_name, password_hash, role)
SELECT 'citra', 'citra@example.test', 'Citra', '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu', 'user'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'citra');

UPDATE users
SET password_hash = '$2y$10$PQz72Imv/XO6uq0Xs2EEPO5IpdYti3hIhDKKf/c89o59fZZ.uIbhu'
WHERE password_hash = 'disabled';
