<?php
require_once __DIR__ . '/../src/db.php';
$url = rtrim(appConfig()['base_url'], '/') . '/api/status.php';
?>
<!doctype html>
<html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Status Sistem · Sisdis</title>
<style>
body{font:1.1rem/1.6 system-ui;color:#192e2a;background:#f1f6f4;margin:0;padding:8vh 6vw}
main{max-width:38rem;background:white;padding:2rem;margin:auto;border-top:5px solid #19775e}
h1{margin:0}dt{font-weight:bold}dd{margin:0 0 .8rem}button{font:inherit;padding:.4rem 1rem;cursor:pointer}
</style>
<main data-url="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
<h1>Status Sistem</h1><p id="state" role="status">Memeriksa koneksi…</p>
<dl hidden><dt>Server aplikasi</dt><dd id="node"></dd><dt>Waktu database (WIB)</dt><dd id="time"></dd></dl>
<button type="button">Periksa lagi</button>
</main>
<script>
const button = document.querySelector('button');
async function refresh() {
    button.disabled = true;
    const details = document.querySelector('dl');
    details.hidden = true;
    const state = document.querySelector('#state');
    try {
        const response = await fetch(document.querySelector('main').dataset.url, {cache: 'no-store'});
        const result = await response.json();
        if (!result.ok) {
            state.textContent = result.error;
            return;
        }
        document.querySelector('#node').textContent = result.data.node_name;
        document.querySelector('#time').textContent = new Date(result.data.server_time_utc)
            .toLocaleString('id-ID', {timeZone: 'Asia/Jakarta'});
        state.textContent = 'Database terhubung';
        details.hidden = false;
    } catch {
        state.textContent = 'Server aplikasi tidak dapat dijangkau.';
    } finally {
        button.disabled = false;
    }
}
button.addEventListener('click', refresh);
refresh();
</script></html>
