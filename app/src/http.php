<?php
declare(strict_types=1);

function setJsonHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
}

function jsonResponse(array $data, int $status = 200): never
{
    setJsonHeaders();
    http_response_code($status);
    echo json_encode(['ok' => true, 'data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400, array $extra = []): never
{
    setJsonHeaders();
    http_response_code($status);
    $payload = array_merge(['ok' => false, 'error' => $message], $extra);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function requireMethod(string ...$allowed): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $allowedUpper = array_map('strtoupper', $allowed);
    if (!in_array($method, $allowedUpper, true)) {
        header('Allow: ' . implode(', ', $allowedUpper));
        jsonError('Metode HTTP tidak diizinkan.', 405);
    }
}

function readRequestBody(): array
{
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    $raw = file_get_contents('php://input');

    if (
        str_contains($contentType, 'application/json')
        || ($raw !== false && trim($raw) !== '' && str_starts_with(ltrim($raw), '{'))
    ) {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            jsonError('Format body JSON tidak valid.', 400);
        }
        return $decoded;
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    if ($raw !== false && trim($raw) !== '') {
        parse_str($raw, $parsed);
        if (is_array($parsed)) {
            return $parsed;
        }
    }

    return [];
}
