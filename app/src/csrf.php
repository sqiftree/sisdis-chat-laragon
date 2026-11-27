<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Cek token CSRF untuk request POST (PRD 9.1).
 * Token didapat client dari response login.php / me.php (field "csrf_token"),
 * lalu dikirim lagi lewat header "X-CSRF-Token" atau field body "csrf_token".
 */
function requireCsrf(array $body = []): void
{
    startSecureSession();
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';

    if (!is_string($sent) || $expected === '' || !hash_equals($expected, $sent)) {
        jsonError('Token CSRF tidak valid. Muat ulang halaman lalu coba lagi.', 403);
    }
}
