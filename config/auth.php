<?php
// ============================================
// config/auth.php — Funzioni di autenticazione
// ============================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

const ACCESS_COOKIE_NAME = 'tma_access_token';
const REFRESH_COOKIE_NAME = 'tma_refresh_token';
const CSRF_COOKIE_NAME = 'tma_csrf_token';

function isHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    return (($_SERVER['SERVER_PORT'] ?? null) === '443');
}

function cookieOptions(int $ttl, bool $httpOnly): array {
    return [
        'expires' => time() + $ttl,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => $httpOnly,
        'samesite' => 'Strict',
    ];
}

function clearAuthCookies(): void {
    setcookie(ACCESS_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    setcookie(REFRESH_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function validateRegistrationInput(string $username, string $email, string $password): array {
    $username = trim($username);
    $email = trim(strtolower($email));

    if (strlen($username) < 3 || strlen($username) > 50) {
        return ['success' => false, 'message' => 'Username deve essere tra 3 e 50 caratteri.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email non valida.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'La password deve avere almeno 8 caratteri.'];
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['success' => false, 'message' => 'La password deve contenere almeno una maiuscola e un numero.'];
    }

    return [
        'success' => true,
        'username' => $username,
        'email' => $email,
        'password' => $password,
    ];
}

function userHasPermission(array $permissions, string $permission): bool {
    return in_array($permission, $permissions, true);
}

function setAuthCookies(int $userId, string $username, string $role, string $refreshToken): void {
    $accessToken = jwtCreate([
        'sub' => $userId,
        'name' => $username,
        'role' => $role,
    ]);

    setcookie(ACCESS_COOKIE_NAME, $accessToken, cookieOptions(JWT_ACCESS_TTL, true));
    setcookie(REFRESH_COOKIE_NAME, $refreshToken, cookieOptions(JWT_REFRESH_TTL, true));
}

// Backward compatibility: retained as no-op to avoid session usage.
function startSession(): void {
}

function getCurrentUser(): ?array {
    static $cachedUser = null;
    static $resolved = false;

    if ($resolved) {
        return $cachedUser;
    }

    $resolved = true;
    $accessToken = (string)($_COOKIE[ACCESS_COOKIE_NAME] ?? '');
    $refreshToken = (string)($_COOKIE[REFRESH_COOKIE_NAME] ?? '');

    $payload = null;
    if ($accessToken !== '') {
        try {
            $payload = jwtVerify($accessToken);
        } catch (Throwable $e) {
            $payload = null;
        }
    }

    // Auto-refresh for web pages when access token is expired/invalid.
    if (!is_array($payload) && $refreshToken !== '') {
        try {
            $rotated = refreshTokenRotate($refreshToken);
            $u = $rotated['user'];
            setAuthCookies((int)$u['id'], (string)$u['username'], (string)$u['role'], (string)$rotated['new_refresh_token']);

            $payload = [
                'sub' => (int)$u['id'],
                'name' => (string)$u['username'],
                'role' => (string)$u['role'],
            ];
        } catch (Throwable $e) {
            clearAuthCookies();
            return null;
        }
    }

    if (!is_array($payload) || empty($payload['sub'])) {
        return null;
    }

    $userId = (int)$payload['sub'];
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.email, u.is_active, r.name AS role_name
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE u.id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || !(int)$row['is_active']) {
        clearAuthCookies();
        return null;
    }

    $cachedUser = [
        'id' => (int)$row['id'],
        'username' => (string)$row['username'],
        'email' => (string)$row['email'],
        'role' => (string)$row['role_name'],
        'permissions' => getUserPermissions((int)$row['id']),
    ];

    return $cachedUser;
}

function isAuthenticated(): bool {
    return is_array(getCurrentUser());
}

// Recupera i permessi di un utente dal DB
function getUserPermissions(int $userId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
            SELECT p.name
        FROM users u
        JOIN role_permissions rp ON u.role_id = rp.role_id
        JOIN permissions p       ON rp.permission_id = p.id
        WHERE u.id = ? AND u.is_active = 1
            UNION
            SELECT p.name
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ?
    ");
    $stmt->execute([$userId, $userId]);
    return array_column($stmt->fetchAll(), 'name');
}

// Controlla se l'utente corrente ha un permesso
function can(string $permission): bool {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    return userHasPermission($user['permissions'], $permission);
}

// Verifica che l'utente sia loggato, altrimenti redirect
function requireLogin(): array {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

// Verifica permesso o redirect con errore
function requirePermission(string $permission): array {
    $user = requireLogin();
    if (!userHasPermission($user['permissions'], $permission)) {
        header('Location: dashboard.php?error=permission_denied');
        exit;
    }

    return $user;
}

// Login utente
function loginUser(string $email, string $password): array {
    $pdo = getDB();
    $identifier = trim($email);
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.password_hash, u.is_active,
               r.name AS role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.email = ? OR u.username = ?
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Email o password non corretti.'];
    }
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Account disabilitato. Contatta il supporto.'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Email o password non corretti.'];
    }

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $refreshToken = refreshTokenCreate((int)$user['id'], (string)$ua, (string)$ip);
    setAuthCookies((int)$user['id'], (string)$user['username'], (string)$user['role_name'], $refreshToken);

    return ['success' => true];
}

// Registrazione nuovo utente (ruolo free di default)
function registerUser(string $username, string $email, string $password): array {
    $pdo = getDB();

    $validation = validateRegistrationInput($username, $email, $password);
    if (!$validation['success']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    $username = $validation['username'];
    $email = $validation['email'];

    // Verifica unicità
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $check->execute([$email, $username]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Username o email già in uso.'];
    }

    // Recupera id ruolo free
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'free' LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch();
        if (!$role) {
            return ['success' => false, 'message' => "Ruolo 'free' non configurato nel database."];
        }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $ins  = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
    $ins->execute([$username, $email, $hash, $role['id']]);

        // Crea un portfolio principale se la tabella esiste (casi d'uso finance).
        $newUserId = (int) $pdo->lastInsertId();
        try {
            $pf = $pdo->prepare("INSERT INTO portfolios (user_id, name) VALUES (?, ?)");
            $pf->execute([$newUserId, 'Portafoglio principale']);
        } catch (Throwable $e) {
            // Non blocca la registrazione se il modulo portfolio non e disponibile.
        }

    return ['success' => true];
}

// Logout
function logoutUser(): void {
    $refreshToken = (string)($_COOKIE[REFRESH_COOKIE_NAME] ?? '');
    if ($refreshToken !== '') {
        try {
            refreshTokenRevoke($refreshToken);
        } catch (Throwable $e) {
            // Ignore revoke errors during logout.
        }
    }

    clearAuthCookies();
}

function getCsrfToken(): string {
    $current = (string)($_COOKIE[CSRF_COOKIE_NAME] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $current)) {
        return $current;
    }

    $token = bin2hex(random_bytes(32));
    setcookie(CSRF_COOKIE_NAME, $token, cookieOptions(60 * 60 * 24 * 30, false));
    return $token;
}

function validateCsrfToken(?string $submitted): bool {
    $cookie = (string)($_COOKIE[CSRF_COOKIE_NAME] ?? '');
    if ($cookie === '' || empty($submitted)) {
        return false;
    }

    return hash_equals($cookie, (string)$submitted);
}
