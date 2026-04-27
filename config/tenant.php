<?php
// ============================================
// config/tenant.php — Risoluzione del Tenant (Multi-Tenancy)
// ============================================

require_once __DIR__ . '/db.php';

/**
 * Risolve lo slug del tenant dalla richiesta HTTP.
 * Priorità: header X-Tenant-Slug > sottodominio > env DEFAULT_TENANT_SLUG > 'default'
 */
function resolveTenantSlug(): string {
    // 1. Header esplicito (utile per API e ambienti di sviluppo/testing)
    $header = $_SERVER['HTTP_X_TENANT_SLUG'] ?? '';
    if ($header !== '') {
        return strtolower((string)preg_replace('/[^a-z0-9\-]/i', '', $header));
    }

    // 2. Sottodominio (es. acme.trademarketai.com → slug 'acme')
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $host = explode(':', $host)[0]; // rimuovi porta
    $parts = explode('.', $host);
    if (count($parts) >= 3 && $parts[0] !== 'www') {
        return (string)preg_replace('/[^a-z0-9\-]/', '', $parts[0]);
    }

    // 3. Variabile d'ambiente (utile in container per tenant fissi)
    $envSlug = (string)(getenv('DEFAULT_TENANT_SLUG') ?: '');
    if ($envSlug !== '') {
        return $envSlug;
    }

    return 'default';
}

/**
 * Recupera i dati del tenant corrente dal DB (con caching statico per la richiesta).
 *
 * @return array{id:int,slug:string,name:string,plan:string,is_active:bool}|null
 */
function getCurrentTenant(): ?array {
    static $tenant = null;
    static $resolved = false;

    if ($resolved) {
        return $tenant;
    }

    $resolved = true;
    $slug = resolveTenantSlug();

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, slug, name, plan, is_active FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        // La tabella tenants potrebbe non esistere ancora (pre-migrazione).
        return null;
    }

    if (!$row || !(int)$row['is_active']) {
        return null;
    }

    $tenant = [
        'id'        => (int)$row['id'],
        'slug'      => (string)$row['slug'],
        'name'      => (string)$row['name'],
        'plan'      => (string)$row['plan'],
        'is_active' => true,
    ];

    return $tenant;
}

/**
 * Come getCurrentTenant() ma termina con un errore HTTP 404 se il tenant non esiste.
 *
 * @return array{id:int,slug:string,name:string,plan:string,is_active:bool}
 */
function requireTenant(): array {
    $tenant = getCurrentTenant();
    if (!$tenant) {
        $slug = htmlspecialchars(resolveTenantSlug(), ENT_QUOTES, 'UTF-8');

        if (defined('API_CONTEXT')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            echo json_encode(
                ['error' => "Tenant '$slug' non trovato o non attivo."],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        http_response_code(404);
        echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">'
            . '<title>Organizzazione non trovata</title></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding:80px">'
            . '<h1>Organizzazione non trovata</h1>'
            . "<p>Il tenant <strong>$slug</strong> non esiste o non è attivo.</p>"
            . '</body></html>';
        exit;
    }

    return $tenant;
}
