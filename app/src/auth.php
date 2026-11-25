<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name('SISDIS_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function establishUserSession(array $user): void
{
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = (string)$user['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function destroyUserSession(): void
{
    startSecureSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

function currentUser(?PDO $pdo = null): ?array
{
    startSecureSession();
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string)$userId)) {
        return null;
    }

    $pdo ??= getPdo();
    $stmt = $pdo->prepare(
        'SELECT id, username, email, display_name, password_hash, role, created_at, last_login_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => (int)$userId]);
    $user = $stmt->fetch();

    if (!$user || $user['password_hash'] === 'disabled') {
        destroyUserSession();
        return null;
    }

    return $user;
}

function requireAuth(?PDO $pdo = null): array
{
    $user = currentUser($pdo);
    if ($user === null) {
        jsonError('Silakan login terlebih dahulu untuk mengakses layanan ini.', 401);
    }
    return $user;
}

function requireAdmin(?PDO $pdo = null): array
{
    $user = requireAuth($pdo);
    if ($user['role'] !== 'admin') {
        jsonError('Akses ditolak: fitur ini hanya untuk admin.', 403);
    }
    return $user;
}

/**
 * Isolasi privasi (Anti-IDOR):
 * Memastikan user hanya boleh mengakses/mengubah data pribadi miliknya sendiri.
 */
function assertSelfAccess(int $targetUserId, array $actor): void
{
    if ((int)$actor['id'] !== $targetUserId) {
        jsonError(
            'Akses ditolak: Anda tidak diperbolehkan melihat, membaca, atau mengakses data pribadi milik user lain.',
            403
        );
    }
}

/**
 * Isolasi privasi pesan (Anti-IDOR untuk modul chat Giska):
 * Memastikan user hanya boleh membaca pesan jika ia adalah pengirim atau penerima.
 */
function assertMessageAccess(array $messageRow, array $actor): void
{
    $actorId = (int)$actor['id'];
    $senderId = (int)($messageRow['sender_id'] ?? 0);
    $receiverId = (int)($messageRow['receiver_id'] ?? 0);

    if ($actorId !== $senderId && $actorId !== $receiverId) {
        jsonError(
            'Akses ditolak: Anda tidak diperbolehkan membaca percakapan pribadi milik user lain.',
            403
        );
    }
}

/**
 * Memastikan user yang sedang login adalah salah satu pihak dalam percakapan 1-on-1.
 */
function assertConversationParticipant(int $userIdA, int $userIdB, array $actor): void
{
    $actorId = (int)$actor['id'];
    if ($actorId !== $userIdA && $actorId !== $userIdB) {
        jsonError(
            'Akses ditolak: Anda bukan peserta dalam percakapan ini.',
            403
        );
    }
}

/**
 * Data lengkap pribadi — HANYA dikembalikan kepada pemilik akun.
 */
function privateUserView(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'display_name' => $user['display_name'],
        'role' => $user['role'],
        'created_at_utc' => formatDbUtc($user['created_at'] ?? null),
        'last_login_at_utc' => formatDbUtc($user['last_login_at'] ?? null),
    ];
}

/**
 * Data publik minimal untuk daftar kontak — tidak membocorkan email/last_login_at/password_hash.
 */
function publicUserView(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'display_name' => $user['display_name'],
    ];
}

function formatDbUtc(?string $datetime): ?string
{
    if ($datetime === null || $datetime === '') {
        return null;
    }
    return str_replace(' ', 'T', $datetime) . 'Z';
}
