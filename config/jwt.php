<?php
// ============================================
// config/jwt.php — JWT puro in PHP (HS256)
// Nessuna dipendenza esterna
// ============================================

// ─── CONFIGURAZIONE ─────────────────────────
define('JWT_SECRET',          getenv('JWT_SECRET') ?: 'CHANGE_ME_IN_PRODUCTION_USE_ENV_VAR_32CHARS!!');
define('JWT_ACCESS_TTL',      300);           // 5 minuti in secondi
define('JWT_REFRESH_TTL',     60 * 60 * 24 * 7); // 7 giorni
define('JWT_ISSUER',          'TradeMarketAi');
define('JWT_ALGO',            'HS256');

// ─── ENCODE / DECODE BASE64URL ───────────────
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    $pad  = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

// ─── CREA ACCESS TOKEN ───────────────────────
/**
 * Genera un JWT Access Token.
 *
 * Payload obbligatorio:
 *   sub  (int)  → user_id
 *   role (str)  → nome ruolo
 *   jti  (str)  → ID univoco token (per blacklist futura)
 */
function jwtCreate(array $payload): string {
    $now = time();

    $header = base64url_encode(json_encode([
        'alg' => JWT_ALGO,
        'typ' => 'JWT',
    ]));

    $claims = array_merge($payload, [
        'iss' => JWT_ISSUER,
        'iat' => $now,
        'nbf' => $now,
        'exp' => $now + JWT_ACCESS_TTL,
        'jti' => bin2hex(random_bytes(8)),
    ]);

    $payloadEncoded = base64url_encode(json_encode($claims));
    $signature      = base64url_encode(
        hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true)
    );

    return "$header.$payloadEncoded.$signature";
}

// ─── VERIFICA E DECODIFICA ───────────────────
/**
 * Ritorna il payload decodificato oppure lancia un'eccezione.
 * Eccezioni:
 *   - InvalidArgumentException  → formato non valido
 *   - RuntimeException          → firma errata | scaduto | nbf non ancora valido
 */
function jwtVerify(string $token): array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new InvalidArgumentException('Token malformato.');
    }

    [$headerB64, $payloadB64, $sigB64] = $parts;

    // Verifica firma
    $expectedSig = base64url_encode(
        hash_hmac('sha256', "$headerB64.$payloadB64", JWT_SECRET, true)
    );
    if (!hash_equals($expectedSig, $sigB64)) {
        throw new RuntimeException('Firma JWT non valida.');
    }

    $payload = json_decode(base64url_decode($payloadB64), true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Payload JWT non valido.');
    }

    $now = time();

    if (isset($payload['nbf']) && $now < $payload['nbf']) {
        throw new RuntimeException('Token non ancora valido (nbf).');
    }
    if (isset($payload['exp']) && $now > $payload['exp']) {
        throw new RuntimeException('Token scaduto.');
    }

    return $payload;
}

// ─── REFRESH TOKEN (opaque, storato su DB) ───
/**
 * Genera un refresh token opaque (256 bit) e lo salva su DB.
 * Ritorna il token in chiaro (da mandare al client).
 */
function refreshTokenCreate(int $userId, string $userAgent = '', string $ip = ''): string {
    $pdo   = getDB();
    $plain = bin2hex(random_bytes(32));      // 64 hex chars
    $hash  = hash('sha256', $plain);         // SHA-256 da salvare su DB

    // Pulisci i refresh token scaduti dello stesso utente
    $pdo->prepare("DELETE FROM refresh_tokens WHERE user_id = ? AND (expires_at < NOW() OR revoked = 1)")
        ->execute([$userId]);

    $pdo->prepare("
        INSERT INTO refresh_tokens (user_id, token_hash, expires_at, user_agent, ip_address)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)
    ")->execute([$userId, $hash, JWT_REFRESH_TTL, substr($userAgent, 0, 255), substr($ip, 0, 45)]);

    return $plain;
}

/**
 * Verifica un refresh token e ritorna i dati utente.
 * Implementa Token Rotation: revoca il vecchio e ne emette uno nuovo.
 *
 * @return array ['user' => [...], 'new_refresh_token' => '...']
 */
function refreshTokenRotate(string $plainToken): array {
    $pdo  = getDB();
    $hash = hash('sha256', $plainToken);

    $stmt = $pdo->prepare("
        SELECT rt.id, rt.user_id, rt.expires_at, rt.revoked,
               u.username, u.email, u.is_active, u.tenant_id, r.name AS role_name
        FROM refresh_tokens rt
        JOIN users u ON rt.user_id = u.id
        JOIN roles  r ON u.role_id  = r.id
        WHERE rt.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new RuntimeException('Refresh token non trovato.');
    }
    if ($row['revoked']) {
        // Possibile replay attack: revoca tutti i token dell'utente
        $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?")
            ->execute([$row['user_id']]);
        throw new RuntimeException('Refresh token già revocato (possibile replay attack).');
    }
    if (strtotime($row['expires_at']) < time()) {
        throw new RuntimeException('Refresh token scaduto.');
    }
    if (!$row['is_active']) {
        throw new RuntimeException('Account disabilitato.');
    }

    // Revoca il vecchio token (rotation)
    $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE id = ?")
        ->execute([$row['id']]);

    // Emetti un nuovo refresh token
    $ua          = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip          = $_SERVER['REMOTE_ADDR']     ?? '';
    $newRefresh  = refreshTokenCreate($row['user_id'], $ua, $ip);

    return [
        'user' => [
            'id'        => (int) $row['user_id'],
            'username'  => $row['username'],
            'email'     => $row['email'],
            'role'      => $row['role_name'],
            'tenant_id' => (int) $row['tenant_id'],
        ],
        'new_refresh_token' => $newRefresh,
    ];
}

/**
 * Revoca un singolo refresh token (logout via API).
 */
function refreshTokenRevoke(string $plainToken): void {
    $pdo  = getDB();
    $hash = hash('sha256', $plainToken);
    $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = ?")
        ->execute([$hash]);
}

// ─── MIDDLEWARE: estrae JWT dal header Authorization ──
/**
 * Legge "Authorization: Bearer <token>", verifica e ritorna il payload.
 * Lancia RuntimeException/InvalidArgumentException in caso di errore.
 */
function jwtFromRequest(): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
               ?? apache_request_headers()['Authorization']
               ?? '';

    if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
        throw new RuntimeException('Authorization header mancante o formato errato.');
    }

    return jwtVerify($m[1]);
}
