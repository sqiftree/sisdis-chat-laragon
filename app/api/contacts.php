<?php
declare(strict_types=1);

/**
 * GET /app/public/api/contacts.php          -> daftar kontak (semua user lain)
 * GET /app/public/api/contacts.php?q=lau    -> cari berdasarkan username / nama tampilan
 *
 * Privasi: data kontak hanya lewat publicUserView() (id, username, display_name).
 * Email, password_hash, dan last_login_at user lain tidak pernah dikirim.
 * Pesan terakhir yang ikut ditampilkan hanya pesan antara SAYA dan kontak itu.
 */

require_once __DIR__ . '/../src/auth.php';

requireMethod('GET');

try {
    $pdo = getPdo();
    $me = requireAuth($pdo);
    $meId = (int)$me['id'];
    $q = trim((string)($_GET['q'] ?? ''));

    $sql = "
        SELECT u.id, u.username, u.display_name,
               lm.id AS last_message_id, lm.sender_id AS last_sender_id,
               lm.body AS last_body, lm.created_at AS last_created_at
        FROM users u
        LEFT JOIN messages lm ON lm.id = (
            SELECT m.id FROM messages m
            WHERE (m.sender_id = :me1 AND m.receiver_id = u.id)
               OR (m.sender_id = u.id AND m.receiver_id = :me2)
            ORDER BY m.id DESC
            LIMIT 1
        )
        WHERE u.id <> :me3
          AND u.password_hash <> 'disabled'
    ";
    $params = [':me1' => $meId, ':me2' => $meId, ':me3' => $meId];

    if ($q !== '') {
        $sql .= ' AND (u.username LIKE :q1 OR u.display_name LIKE :q2)';
        $params[':q1'] = '%' . $q . '%';
        $params[':q2'] = '%' . $q . '%';
    }
    // Kontak yang terakhir chat muncul paling atas, sisanya urut nama
    $sql .= ' ORDER BY (lm.id IS NULL), lm.id DESC, u.display_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $contacts = [];
    foreach ($stmt->fetchAll() as $row) {
        $contact = publicUserView($row);
        $contact['last_message'] = $row['last_message_id'] === null ? null : [
            'id' => (int)$row['last_message_id'],
            'from_me' => (int)$row['last_sender_id'] === $meId,
            'body' => $row['last_body'],
            'created_at_utc' => formatDbUtc($row['last_created_at']),
        ];
        $contacts[] = $contact;
    }

    $config = appConfig();
    jsonResponse([
        'contacts' => $contacts,
        'node_name' => $config['node_name'],
        'server_time_utc' => databaseTime($config['db']),
    ]);
} catch (Throwable $e) {
    jsonError('Gagal memuat daftar kontak.', 500);
}
