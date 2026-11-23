-- Kontak demonstrasi. Akun dikunci sampai pengelola memasang hash password.
INSERT INTO users (username, email, display_name, password_hash)
SELECT 'ayu', 'ayu@example.test', 'Ayu', 'disabled'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'ayu');
INSERT INTO users (username, email, display_name, password_hash)
SELECT 'bima', 'bima@example.test', 'Bima', 'disabled'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'bima');
INSERT INTO users (username, email, display_name, password_hash)
SELECT 'citra', 'citra@example.test', 'Citra', 'disabled'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'citra');
