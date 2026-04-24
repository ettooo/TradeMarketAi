<?php
// ============================================
// api/index.php  Router API REST
// ============================================

declare(strict_types=1);

define('API_CONTEXT', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/tenant.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function respondOk(array $data): never {
    respond(200, $data);
}

function respondCreated(array $data): never {
    respond(201, $data);
}

function respondError(int $code, string $message, array $extra = []): never {
    respond($code, array_merge(['error' => $message], $extra));
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function normalizeApiPath(string $requestUri): string {
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $pathNorm = str_replace('\\', '/', $path);

    if ($baseDir !== '' && $baseDir !== '.' && str_starts_with($pathNorm, $baseDir)) {
        $pathNorm = substr($pathNorm, strlen($baseDir));
    }

    return '/' . trim($pathNorm, '/');
}

function getEffectivePermissionNames(int $userId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT p.name
         FROM users u
         JOIN role_permissions rp ON u.role_id = rp.role_id
         JOIN permissions p ON rp.permission_id = p.id
         WHERE u.id = ? AND u.is_active = 1
         UNION
         SELECT p.name
         FROM user_permissions up
         JOIN permissions p ON up.permission_id = p.id
         WHERE up.user_id = ?
         ORDER BY name"
    );
    $stmt->execute([$userId, $userId]);

    return array_column($stmt->fetchAll(), 'name');
}

function getEffectivePermissionsDetailed(int $userId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.description, 'role' AS source
         FROM users u
         JOIN role_permissions rp ON u.role_id = rp.role_id
         JOIN permissions p ON rp.permission_id = p.id
         WHERE u.id = ?
         UNION
         SELECT p.id, p.name, p.description, 'direct' AS source
         FROM user_permissions up
         JOIN permissions p ON up.permission_id = p.id
         WHERE up.user_id = ?
         ORDER BY name"
    );
    $stmt->execute([$userId, $userId]);

    return $stmt->fetchAll();
}

function requireJwt(): array {
    static $cachedPayload = null;
    if (is_array($cachedPayload)) {
        return $cachedPayload;
    }

    try {
        $cachedPayload = jwtFromRequest();
    } catch (Throwable $e) {
        $msg = $e->getMessage();

        if (stripos($msg, 'Authorization header mancante') !== false) {
            respondError(401, 'Non autorizzato: token JWT mancante.');
        }

        respondError(401, 'Non autorizzato: token JWT non valido o scaduto.');
    }

    // Validate tenant: the JWT's tid must match the current tenant.
    $tenant = getCurrentTenant();
    $jwtTenantId = (int)($cachedPayload['tid'] ?? 0);
    if ($tenant !== null && $jwtTenantId > 0 && $jwtTenantId !== (int)$tenant['id']) {
        respondError(401, 'Token JWT non valido per questo tenant.');
    }

    return $cachedPayload;
}

function isPublicRoute(string $method, string $uri): bool {
    return ($method === 'POST' && $uri === '/auth/login')
        || ($method === 'POST' && $uri === '/auth/refresh')
        || ($method === 'GET'  && $uri === '/tenant');
}

function requireApiPermission(int $userId, string $permission): void {
    $perms = getEffectivePermissionNames($userId);
    if (!in_array($permission, $perms, true)) {
        respondError(403, "Accesso negato: permesso '$permission' richiesto.");
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = normalizeApiPath($_SERVER['REQUEST_URI'] ?? '/');

// Default deny: tutte le rotte API richiedono JWT tranne quelle pubbliche.
if (!isPublicRoute($method, $uri)) {
    requireJwt();
}

if ($method === 'POST' && $uri === '/auth/login') {
    $body = getBody();
    $email = trim((string)($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');

    if ($email === '' || $password === '') {
        respondError(422, 'email e password sono obbligatori.');
    }

    $tenant = getCurrentTenant();
    if (!$tenant) {
        respondError(404, 'Tenant non trovato o non attivo.');
    }
    $tenantId = (int)$tenant['id'];

    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.email, u.password_hash, u.is_active, r.name AS role
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE u.email = ? AND u.tenant_id = ?
         LIMIT 1"
    );
    $stmt->execute([$email, $tenantId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        respondError(401, 'Credenziali non valide.');
    }
    if (!(int)$user['is_active']) {
        respondError(403, 'Account disabilitato.');
    }

    $permissions = getEffectivePermissionNames((int)$user['id']);
    $accessToken = jwtCreate([
        'sub'  => (int)$user['id'],
        'name' => $user['username'],
        'role' => $user['role'],
        'tid'  => $tenantId,
    ]);

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $refreshToken = refreshTokenCreate((int)$user['id'], $ua, $ip);

    respondOk([
        'message' => 'Login effettuato con successo.',
        'token_type' => 'Bearer',
        'expires_in' => JWT_ACCESS_TTL,
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'tenant' => ['id' => $tenantId, 'slug' => $tenant['slug'], 'name' => $tenant['name']],
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'permissions' => $permissions,
        ],
    ]);
}

if ($method === 'POST' && $uri === '/auth/refresh') {
    $body = getBody();
    $token = (string)($body['refresh_token'] ?? '');

    if ($token === '') {
        respondError(422, 'refresh_token obbligatorio.');
    }

    try {
        $result = refreshTokenRotate($token);
    } catch (Throwable $e) {
        respondError(401, $e->getMessage());
    }

    $u = $result['user'];
    $uTenantId = (int)$u['tenant_id'];

    // Validate tenant context.
    $tenant = getCurrentTenant();
    if ($tenant && $uTenantId !== (int)$tenant['id']) {
        respondError(401, 'Refresh token non valido per questo tenant.');
    }

    $permissions = getEffectivePermissionNames((int)$u['id']);

    $accessToken = jwtCreate([
        'sub'  => (int)$u['id'],
        'name' => $u['username'],
        'role' => $u['role'],
        'tid'  => $uTenantId,
    ]);

    respondOk([
        'message' => 'Token rinnovato.',
        'token_type' => 'Bearer',
        'expires_in' => JWT_ACCESS_TTL,
        'access_token' => $accessToken,
        'refresh_token' => $result['new_refresh_token'],
        'user' => [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'email' => $u['email'],
            'role' => $u['role'],
            'permissions' => $permissions,
        ],
    ]);
}

if ($method === 'POST' && $uri === '/auth/logout') {
    $body = getBody();
    $token = (string)($body['refresh_token'] ?? '');
    if ($token !== '') {
        refreshTokenRevoke($token);
    }

    respondOk(['message' => 'Logout effettuato.']);
}

if ($method === 'GET' && $uri === '/me/permissions') {
    $payload = requireJwt();
    $userId = (int)($payload['sub'] ?? 0);

    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.description
         FROM permissions p
         WHERE p.name IN (
            SELECT p2.name
            FROM users u
            JOIN role_permissions rp ON u.role_id = rp.role_id
            JOIN permissions p2 ON rp.permission_id = p2.id
            WHERE u.id = ? AND u.is_active = 1
            UNION
            SELECT p3.name
            FROM user_permissions up
            JOIN permissions p3 ON up.permission_id = p3.id
            WHERE up.user_id = ?
         )
         ORDER BY p.name"
    );
    $stmt->execute([$userId, $userId]);
    $permissions = $stmt->fetchAll();

    $uStmt = $pdo->prepare('SELECT u.id, u.username, u.email, r.name AS role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    if (!$user) {
        respondError(404, 'Utente non trovato.');
    }

    respondOk([
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
        'permissions' => $permissions,
        'token_info' => [
            'issued_at' => date('c', (int)($payload['iat'] ?? time())),
            'expires_at' => date('c', (int)($payload['exp'] ?? time())),
            'expires_in' => ((int)($payload['exp'] ?? time())) - time(),
        ],
    ]);
}

if ($method === 'GET' && $uri === '/permissions') {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_permissions');

    $pdo = getDB();
    $rows = $pdo->query('SELECT id, name, description, created_at FROM permissions ORDER BY name')->fetchAll();

    respondOk(['permissions' => $rows, 'total' => count($rows)]);
}

if ($method === 'POST' && $uri === '/permissions') {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_permissions');

    $body = getBody();
    $name = trim((string)($body['name'] ?? ''));
    $description = trim((string)($body['description'] ?? ''));

    if (!preg_match('/^[a-z_]{3,100}$/', $name)) {
        respondError(422, 'name deve contenere solo lettere minuscole e underscore (3-100 chars).');
    }

    try {
        $pdo = getDB();
        $pdo->prepare('INSERT INTO permissions (name, description) VALUES (?, ?)')
            ->execute([$name, $description]);

        respondCreated([
            'message' => 'Permesso creato.',
            'permission' => ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'description' => $description],
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respondError(409, "Il permesso '$name' esiste gia.");
        }
        respondError(500, 'Errore database.');
    }
}

if ($method === 'PUT' && preg_match('#^/permissions/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_permissions');

    $permId = (int)$m[1];
    $body = getBody();
    $name = trim((string)($body['name'] ?? ''));
    $description = trim((string)($body['description'] ?? ''));

    if ($name !== '' && !preg_match('/^[a-z_]{3,100}$/', $name)) {
        respondError(422, 'name deve contenere solo lettere minuscole e underscore.');
    }

    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, name, description FROM permissions WHERE id = ?');
    $stmt->execute([$permId]);
    $row = $stmt->fetch();

    if (!$row) {
        respondError(404, 'Permesso non trovato.');
    }

    $newName = $name !== '' ? $name : $row['name'];
    $newDesc = $description !== '' ? $description : $row['description'];

    try {
        $pdo->prepare('UPDATE permissions SET name = ?, description = ? WHERE id = ?')
            ->execute([$newName, $newDesc, $permId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respondError(409, 'Nome gia in uso.');
        }
        respondError(500, 'Errore database.');
    }

    respondOk([
        'message' => 'Permesso aggiornato.',
        'permission' => ['id' => $permId, 'name' => $newName, 'description' => $newDesc],
    ]);
}

if ($method === 'DELETE' && preg_match('#^/permissions/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_permissions');

    $permId = (int)$m[1];
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, name FROM permissions WHERE id = ?');
    $stmt->execute([$permId]);
    $perm = $stmt->fetch();

    if (!$perm) {
        respondError(404, 'Permesso non trovato.');
    }

    $pdo->prepare('DELETE FROM permissions WHERE id = ?')->execute([$permId]);
    respondOk(['message' => "Permesso '{$perm['name']}' eliminato."]);
}

if ($method === 'GET' && preg_match('#^/users/(\d+)/permissions$#', $uri, $m)) {
    $payload = requireJwt();
    $caller = (int)$payload['sub'];
    $targetId = (int)$m[1];

    if ($caller !== $targetId) {
        requireApiPermission($caller, 'manage_permissions');
    }

    $pdo = getDB();
    $permissions = getEffectivePermissionsDetailed($targetId);

    $uStmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $uStmt->execute([$targetId]);
    $user = $uStmt->fetch();
    if (!$user) {
        respondError(404, 'Utente non trovato.');
    }

    respondOk(['user' => $user, 'permissions' => $permissions]);
}

if ($method === 'POST' && preg_match('#^/users/(\d+)/permissions$#', $uri, $m)) {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_permissions');

    $targetId = (int)$m[1];
    $permissionId = (int)(getBody()['permission_id'] ?? 0);
    if ($permissionId <= 0) {
        respondError(422, 'permission_id obbligatorio.');
    }

    $pdo = getDB();

    $uStmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $uStmt->execute([$targetId]);
    $user = $uStmt->fetch();
    if (!$user) {
        respondError(404, 'Utente non trovato.');
    }

    $pStmt = $pdo->prepare('SELECT id, name FROM permissions WHERE id = ?');
    $pStmt->execute([$permissionId]);
    $perm = $pStmt->fetch();
    if (!$perm) {
        respondError(404, 'Permesso non trovato.');
    }

    try {
        $pdo->prepare('INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)')
            ->execute([$targetId, $permissionId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respondError(409, "L'utente ha gia il permesso '{$perm['name']}'.");
        }
        respondError(500, 'Errore database.');
    }

    respondCreated(['message' => "Permesso '{$perm['name']}' assegnato all'utente '{$user['username']}'."]);
}

if ($method === 'DELETE' && preg_match('#^/users/(\d+)/permissions/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_permissions');

    $targetId = (int)$m[1];
    $permissionId = (int)$m[2];

    $pdo = getDB();

    $uStmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $uStmt->execute([$targetId]);
    $user = $uStmt->fetch();
    if (!$user) {
        respondError(404, 'Utente non trovato.');
    }

    $pStmt = $pdo->prepare('SELECT id, name FROM permissions WHERE id = ?');
    $pStmt->execute([$permissionId]);
    $perm = $pStmt->fetch();
    if (!$perm) {
        respondError(404, 'Permesso non trovato.');
    }

    $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?')
        ->execute([$targetId, $permissionId]);

    respondOk(['message' => "Permesso '{$perm['name']}' rimosso dall'utente '{$user['username']}'."]);
}

if ($method === 'GET' && $uri === '/market-data') {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    requireApiPermission($userId, 'view_market_data');

    $pdo = getDB();
    $rows = $pdo->query('SELECT symbol, name, price, change_pct, volume, market_cap, fetched_at FROM market_data ORDER BY symbol')->fetchAll();
    respondOk(['market_data' => $rows, 'total' => count($rows)]);
}

if ($method === 'GET' && $uri === '/market-data/advanced') {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    requireApiPermission($userId, 'view_market_advanced');

    $pdo = getDB();
    $rows = $pdo->query('SELECT symbol, name, price, change_pct, volume, market_cap, fetched_at FROM market_data ORDER BY ABS(change_pct) DESC LIMIT 25')->fetchAll();
    respondOk(['advanced_market_data' => $rows, 'note' => 'Top movimenti per variazione percentuale.']);
}

if ($method === 'GET' && $uri === '/portfolio') {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    requireApiPermission($userId, 'manage_portfolio');

    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT p.id AS portfolio_id, p.name AS portfolio_name,
                pi.id AS item_id, pi.symbol, pi.quantity, pi.purchase_price, pi.purchased_at
         FROM portfolios p
         LEFT JOIN portfolio_items pi ON pi.portfolio_id = p.id
         WHERE p.user_id = ?
         ORDER BY p.created_at, pi.purchased_at DESC"
    );
    $stmt->execute([$userId]);

    respondOk(['portfolio_items' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $uri === '/portfolio/items') {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    requireApiPermission($userId, 'manage_portfolio');

    $body = getBody();
    $symbol = strtoupper(trim((string)($body['symbol'] ?? '')));
    $quantity = (int)($body['quantity'] ?? 0);
    $price = (float)($body['purchase_price'] ?? 0);

    if (!preg_match('/^[A-Z.]{1,10}$/', $symbol) || $quantity <= 0 || $price <= 0) {
        respondError(422, 'Dati non validi: symbol, quantity, purchase_price obbligatori.');
    }

    $pdo = getDB();
    $pfStmt = $pdo->prepare('SELECT id FROM portfolios WHERE user_id = ? ORDER BY id LIMIT 1');
    $pfStmt->execute([$userId]);
    $portfolio = $pfStmt->fetch();

    if (!$portfolio) {
        $pdo->prepare('INSERT INTO portfolios (user_id, name) VALUES (?, ?)')
            ->execute([$userId, 'Portafoglio principale']);
        $portfolioId = (int)$pdo->lastInsertId();
    } else {
        $portfolioId = (int)$portfolio['id'];
    }

    $stmt = $pdo->prepare('INSERT INTO portfolio_items (portfolio_id, symbol, quantity, purchase_price) VALUES (?, ?, ?, ?)');
    $stmt->execute([$portfolioId, $symbol, $quantity, $price]);

    respondCreated(['message' => 'Posizione aggiunta al portafoglio.', 'item_id' => (int)$pdo->lastInsertId()]);
}

if ($method === 'DELETE' && preg_match('#^/portfolio/items/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    requireApiPermission($userId, 'manage_portfolio');

    $itemId = (int)$m[1];
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "DELETE pi
         FROM portfolio_items pi
         JOIN portfolios p ON p.id = pi.portfolio_id
         WHERE pi.id = ? AND p.user_id = ?"
    );
    $stmt->execute([$itemId, $userId]);

    if ($stmt->rowCount() === 0) {
        respondError(404, 'Elemento portafoglio non trovato.');
    }

    respondOk(['message' => 'Elemento rimosso dal portafoglio.']);
}

if ($method === 'GET' && $uri === '/alerts') {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    $perms = getEffectivePermissionNames($userId);

    if (!in_array('set_basic_alerts', $perms, true) && !in_array('set_advanced_alerts', $perms, true)) {
        respondError(403, 'Permesso mancante per visualizzare gli alert.');
    }

    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, symbol, condition_type, threshold, is_active, created_at FROM alerts WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);

    respondOk(['alerts' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $uri === '/alerts') {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    $perms = getEffectivePermissionNames($userId);

    $isAdvanced = in_array('set_advanced_alerts', $perms, true);
    $isBasic = in_array('set_basic_alerts', $perms, true);
    if (!$isBasic && !$isAdvanced) {
        respondError(403, 'Permesso mancante per creare alert.');
    }

    $body = getBody();
    $symbol = strtoupper(trim((string)($body['symbol'] ?? '')));
    $condition = (string)($body['condition_type'] ?? '');
    $threshold = (float)($body['threshold'] ?? 0);

    if (!preg_match('/^[A-Z.]{1,10}$/', $symbol) || !in_array($condition, ['above', 'below'], true) || $threshold <= 0) {
        respondError(422, 'Dati alert non validi.');
    }

    $pdo = getDB();
    if (!$isAdvanced) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM alerts WHERE user_id = ? AND is_active = 1');
        $countStmt->execute([$userId]);
        $count = (int)($countStmt->fetch()['cnt'] ?? 0);
        if ($count >= 3) {
            respondError(403, 'Piano free: massimo 3 alert attivi.');
        }
    }

    $stmt = $pdo->prepare('INSERT INTO alerts (user_id, symbol, condition_type, threshold, is_active) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute([$userId, $symbol, $condition, $threshold]);

    respondCreated(['message' => 'Alert creato.', 'alert_id' => (int)$pdo->lastInsertId()]);
}

if ($method === 'DELETE' && preg_match('#^/alerts/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    $userId = (int)$payload['sub'];
    $perms = getEffectivePermissionNames($userId);

    if (!in_array('set_basic_alerts', $perms, true) && !in_array('set_advanced_alerts', $perms, true)) {
        respondError(403, 'Permesso mancante per gestire alert.');
    }

    $alertId = (int)$m[1];
    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM alerts WHERE id = ? AND user_id = ?');
    $stmt->execute([$alertId, $userId]);

    if ($stmt->rowCount() === 0) {
        respondError(404, 'Alert non trovato.');
    }

    respondOk(['message' => 'Alert eliminato.']);
}

// ─── TENANT ENDPOINTS ────────────────────────────────────────────────────────

// GET /tenant — informazioni sul tenant corrente (pubblico)
if ($method === 'GET' && $uri === '/tenant') {
    $tenant = getCurrentTenant();
    if (!$tenant) {
        respondError(404, 'Tenant non trovato o non attivo.');
    }

    respondOk([
        'tenant' => [
            'id'   => $tenant['id'],
            'slug' => $tenant['slug'],
            'name' => $tenant['name'],
            'plan' => $tenant['plan'],
        ],
    ]);
}

// GET /tenants — elenco di tutti i tenant (richiede manage_tenants)
if ($method === 'GET' && $uri === '/tenants') {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_tenants');

    $pdo  = getDB();
    $rows = $pdo->query('SELECT id, slug, name, plan, is_active, created_at FROM tenants ORDER BY created_at DESC')->fetchAll();

    respondOk(['tenants' => $rows, 'total' => count($rows)]);
}

// POST /tenants — crea un nuovo tenant (richiede manage_tenants)
if ($method === 'POST' && $uri === '/tenants') {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_tenants');

    $body = getBody();
    $slug = strtolower(trim((string)($body['slug'] ?? '')));
    $name = trim((string)($body['name'] ?? ''));
    $plan = (string)($body['plan'] ?? 'basic');

    if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$/', $slug)) {
        respondError(422, 'slug non valido: usa solo lettere minuscole, cifre e trattini (3-63 chars).');
    }
    if (strlen($name) < 2 || strlen($name) > 100) {
        respondError(422, 'name deve essere tra 2 e 100 caratteri.');
    }
    if (!in_array($plan, ['basic', 'professional', 'enterprise'], true)) {
        respondError(422, "plan deve essere 'basic', 'professional' o 'enterprise'.");
    }

    $pdo = getDB();
    try {
        $pdo->prepare('INSERT INTO tenants (slug, name, plan) VALUES (?, ?, ?)')->execute([$slug, $name, $plan]);
        $newId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respondError(409, "Il tenant con slug '$slug' esiste già.");
        }
        respondError(500, 'Errore database.');
    }

    respondCreated([
        'message' => 'Tenant creato.',
        'tenant'  => ['id' => $newId, 'slug' => $slug, 'name' => $name, 'plan' => $plan, 'is_active' => true],
    ]);
}

// GET /tenants/{id} — dettaglio tenant (richiede manage_tenants)
if ($method === 'GET' && preg_match('#^/tenants/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_tenants');

    $tenantId = (int)$m[1];
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, slug, name, plan, is_active, created_at FROM tenants WHERE id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();

    if (!$row) {
        respondError(404, 'Tenant non trovato.');
    }

    // Count users in tenant
    $cntStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM users WHERE tenant_id = ?');
    $cntStmt->execute([$tenantId]);
    $cntRow = $cntStmt->fetch();

    respondOk(['tenant' => $row, 'user_count' => (int)($cntRow['cnt'] ?? 0)]);
}

// PUT /tenants/{id} — aggiorna tenant (richiede manage_tenants)
if ($method === 'PUT' && preg_match('#^/tenants/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_tenants');

    $tenantId = (int)$m[1];
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, slug, name, plan, is_active FROM tenants WHERE id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();

    if (!$row) {
        respondError(404, 'Tenant non trovato.');
    }

    $body      = getBody();
    $newName   = trim((string)($body['name']      ?? '')) ?: $row['name'];
    $newPlan   = (string)($body['plan']      ?? '') ?: $row['plan'];
    $newActive = isset($body['is_active']) ? ((bool)$body['is_active'] ? 1 : 0) : (int)$row['is_active'];

    if (strlen($newName) < 2 || strlen($newName) > 100) {
        respondError(422, 'name deve essere tra 2 e 100 caratteri.');
    }
    if (!in_array($newPlan, ['basic', 'professional', 'enterprise'], true)) {
        respondError(422, "plan deve essere 'basic', 'professional' o 'enterprise'.");
    }

    $pdo->prepare('UPDATE tenants SET name = ?, plan = ?, is_active = ? WHERE id = ?')
        ->execute([$newName, $newPlan, $newActive, $tenantId]);

    respondOk([
        'message' => 'Tenant aggiornato.',
        'tenant'  => ['id' => $tenantId, 'slug' => $row['slug'], 'name' => $newName, 'plan' => $newPlan, 'is_active' => (bool)$newActive],
    ]);
}

// DELETE /tenants/{id} — disattiva tenant (richiede manage_tenants; non elimina i dati)
if ($method === 'DELETE' && preg_match('#^/tenants/(\d+)$#', $uri, $m)) {
    $payload = requireJwt();
    requireApiPermission((int)$payload['sub'], 'manage_tenants');

    $tenantId = (int)$m[1];
    if ($tenantId === 1) {
        respondError(403, 'Il tenant predefinito non può essere disattivato.');
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, slug FROM tenants WHERE id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();

    if (!$row) {
        respondError(404, 'Tenant non trovato.');
    }

    $pdo->prepare('UPDATE tenants SET is_active = 0 WHERE id = ?')->execute([$tenantId]);

    respondOk(['message' => "Tenant '{$row['slug']}' disattivato."]);
}

// GET /tenant/users — utenti nel tenant corrente (richiede manage_users)
if ($method === 'GET' && $uri === '/tenant/users') {
    $payload  = requireJwt();
    $callerId = (int)$payload['sub'];
    requireApiPermission($callerId, 'manage_users');

    $tenant = getCurrentTenant();
    if (!$tenant) {
        respondError(404, 'Tenant non trovato.');
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.email, r.name AS role, u.is_active, u.created_at
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE u.tenant_id = ?
         ORDER BY u.created_at DESC"
    );
    $stmt->execute([(int)$tenant['id']]);

    respondOk(['users' => $stmt->fetchAll(), 'tenant' => ['id' => $tenant['id'], 'slug' => $tenant['slug']]]);
}

respondError(404, "Endpoint non trovato: $method $uri");
