<?php
require_once __DIR__ . '/config/auth.php';
$sessionUser = requireLogin();
$sessionUserId = (int)($sessionUser['id'] ?? 0);
$sessionPerms = $sessionUser['permissions'] ?? [];
$sessionCanManageUsers = in_array('manage_users', $sessionPerms, true);

$user = $sessionUser;
$isAdminPreview = false;
$previewUserId = (int)($_GET['view_user'] ?? 0);
$previewTargetLabel = '';

if ($previewUserId > 0 && $sessionCanManageUsers && $previewUserId !== $sessionUserId) {
    try {
        $previewPdo = getDB();
        $previewStmt = $previewPdo->prepare(
            "SELECT u.id, u.username, u.email, u.is_active, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1"
        );
        $previewStmt->execute([$previewUserId]);
        $previewRow = $previewStmt->fetch();

        if ($previewRow && (int)$previewRow['is_active'] === 1) {
            $user = [
                'id' => (int)$previewRow['id'],
                'username' => (string)$previewRow['username'],
                'email' => (string)$previewRow['email'],
                'role' => (string)$previewRow['role_name'],
                'permissions' => getUserPermissions((int)$previewRow['id']),
            ];
            $isAdminPreview = true;
            $previewTargetLabel = (string)$previewRow['username'];
        }
    } catch (Throwable $e) {
        // Fall back alla dashboard standard se il target non e disponibile.
    }
}

$username = $user['username'] ?? 'Utente';
$role = $user['role'] ?? 'free';
$perms = $user['permissions'] ?? [];
$userId = (int)($user['id'] ?? 0);

$has = static function (string $p) use ($perms): bool {
    return in_array($p, $perms, true);
};

$roleLabels = [
    'free' => ['label' => 'Free', 'tone' => 'free'],
    'premium' => ['label' => 'Premium', 'tone' => 'premium'],
    'admin' => ['label' => 'Admin', 'tone' => 'admin'],
];
$roleInfo = $roleLabels[$role] ?? $roleLabels['free'];

$errorCode = (string)($_GET['error'] ?? '');
$flashSuccess = '';
$flashError = '';

if ($errorCode === 'permission_denied') {
    $flashError = 'Non hai i permessi per accedere a questa sezione.';
} elseif ($errorCode === 'csrf_invalid') {
    $flashError = 'Token CSRF non valido. Riprova.';
} elseif ($errorCode === 'upgrade_failed') {
    $flashError = 'Upgrade non completato. Riprova tra qualche minuto.';
} elseif ($errorCode === 'upgrade_not_allowed') {
    $flashError = 'Upgrade disponibile solo per account Free.';
} elseif ($errorCode === 'transactions_disabled') {
    $flashError = 'Errore DB: transazioni disattivate. Impossibile cambiare piano.';
} elseif ($errorCode === 'transactions_config_missing') {
    $flashError = 'Configurazione transazioni non disponibile nel DB. Esegui auth_system.sql aggiornato.';
} elseif ($errorCode === 'preview_readonly') {
    $flashError = 'Modalita anteprima: azioni in scrittura disattivate.';
}

if (($_GET['upgraded'] ?? '') === '1') {
    $flashSuccess = 'Upgrade completato: account ora Pro con permessi Premium.';
}

if (($_GET['tx'] ?? '') === 'enabled') {
    $flashSuccess = 'Transazioni abilitate correttamente.';
} elseif (($_GET['tx'] ?? '') === 'disabled') {
    $flashSuccess = 'Transazioni disabilitate correttamente.';
}

if (($_GET['cursor'] ?? '') === 'enabled') {
    $flashSuccess = 'Cursori live abilitati correttamente.';
} elseif (($_GET['cursor'] ?? '') === 'disabled') {
    $flashSuccess = 'Cursori live disabilitati correttamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAdminPreview) {
        header('Location: dashboard.php?error=preview_readonly');
        exit;
    }

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        header('Location: dashboard.php?error=csrf_invalid');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upgrade_to_pro') {
        if ($role !== 'free') {
            header('Location: dashboard.php?error=upgrade_not_allowed');
            exit;
        }

        try {
            $pdoTx = getDB();
            $pdoTx->beginTransaction();

            $pdoTx->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('transactions_enabled', '1')");

            $txStmt = $pdoTx->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'transactions_enabled' FOR UPDATE");
            $txStmt->execute();
            $txRow = $txStmt->fetch();
            $transactionsEnabledNow = isset($txRow['setting_value']) && (string)$txRow['setting_value'] === '1';

            if (!$transactionsEnabledNow) {
                throw new RuntimeException('TRANSACTIONS_DISABLED');
            }

            $userLockStmt = $pdoTx->prepare(
                "SELECT u.id, u.role_id, r.name AS role_name
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ?
                 FOR UPDATE"
            );
            $userLockStmt->execute([$userId]);
            $lockedUser = $userLockStmt->fetch();

            if (!$lockedUser || (string)$lockedUser['role_name'] !== 'free') {
                throw new RuntimeException('UPGRADE_NOT_ALLOWED');
            }

            $premiumRoleStmt = $pdoTx->prepare("SELECT id FROM roles WHERE name = 'premium' LIMIT 1");
            $premiumRoleStmt->execute();
            $premiumRole = $premiumRoleStmt->fetch();

            if (!$premiumRole) {
                throw new RuntimeException('PREMIUM_ROLE_MISSING');
            }

            $insertTxStmt = $pdoTx->prepare(
                'INSERT INTO subscription_transactions (user_id, from_role_id, to_role_id, status, notes)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insertTxStmt->execute([
                $userId,
                (int)$lockedUser['role_id'],
                (int)$premiumRole['id'],
                'completed',
                'Upgrade piano Free a Pro da dashboard web',
            ]);

            $upgradeStmt = $pdoTx->prepare('UPDATE users SET role_id = ? WHERE id = ?');
            $upgradeStmt->execute([(int)$premiumRole['id'], $userId]);

            $pdoTx->commit();
            header('Location: dashboard.php?upgraded=1');
            exit;
        } catch (Throwable $e) {
            if (isset($pdoTx) && $pdoTx instanceof PDO && $pdoTx->inTransaction()) {
                $pdoTx->rollBack();
            }

            $message = strtoupper($e->getMessage());
            if (str_contains($message, 'TRANSACTIONS_DISABLED') || str_contains($message, 'TRANSAZIONI_DISATTIVATE_DB')) {
                header('Location: dashboard.php?error=transactions_disabled');
                exit;
            }

            if (str_contains($message, 'SYSTEM_SETTINGS') || str_contains($message, 'SUBSCRIPTION_TRANSACTIONS')) {
                header('Location: dashboard.php?error=transactions_config_missing');
                exit;
            }

            if (str_contains($message, 'UPGRADE_NOT_ALLOWED')) {
                header('Location: dashboard.php?error=upgrade_not_allowed');
                exit;
            }

            header('Location: dashboard.php?error=upgrade_failed');
            exit;
        }
    }

    if ($action === 'toggle_transactions') {
        if (!$has('manage_users')) {
            header('Location: dashboard.php?error=permission_denied');
            exit;
        }

        $newValue = (($_POST['transactions_enabled'] ?? '0') === '1') ? '1' : '0';

        try {
            $pdoTx = getDB();
            $pdoTx->beginTransaction();
            $pdoTx->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('transactions_enabled', '1')");

            $updTxStmt = $pdoTx->prepare('UPDATE system_settings SET setting_value = ? WHERE setting_key = ?');
            $updTxStmt->execute([$newValue, 'transactions_enabled']);

            $pdoTx->commit();
            $txStatus = $newValue === '1' ? 'enabled' : 'disabled';
            header('Location: dashboard.php?tx=' . $txStatus . '#admin');
            exit;
        } catch (Throwable $e) {
            if (isset($pdoTx) && $pdoTx instanceof PDO && $pdoTx->inTransaction()) {
                $pdoTx->rollBack();
            }

            header('Location: dashboard.php?error=transactions_config_missing#admin');
            exit;
        }
    }

    if ($action === 'toggle_cursor_tracking') {
        if (!$has('manage_users')) {
            header('Location: dashboard.php?error=permission_denied');
            exit;
        }

        $newValue = (($_POST['cursor_tracking_enabled'] ?? '0') === '1') ? '1' : '0';

        try {
            $pdoTx = getDB();
            $pdoTx->beginTransaction();
            $pdoTx->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('cursor_tracking_enabled', '1')");

            $updCursorStmt = $pdoTx->prepare('UPDATE system_settings SET setting_value = ? WHERE setting_key = ?');
            $updCursorStmt->execute([$newValue, 'cursor_tracking_enabled']);

            $pdoTx->commit();
            $cursorStatus = $newValue === '1' ? 'enabled' : 'disabled';
            header('Location: dashboard.php?cursor=' . $cursorStatus . '#admin');
            exit;
        } catch (Throwable $e) {
            if (isset($pdoTx) && $pdoTx instanceof PDO && $pdoTx->inTransaction()) {
                $pdoTx->rollBack();
            }

            header('Location: dashboard.php?error=cursor_config_error#admin');
            exit;
        }
    }
}

$pdo = null;
$marketRows = [];
$portfolioRows = [];
$alertsRows = [];
$adminUsers = [];
$roleStats = [];
$viewAccessRows = [];
$activeSessionsRows = [];
$transactionsEnabled = true;
$transactionsConfigAvailable = true;
$cursorTrackingEnabled = true;

try {
    $pdo = getDB();

    // Leggi stato cursor tracking
    try {
        $cursorStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'cursor_tracking_enabled' LIMIT 1");
        $cursorStmt->execute();
        $cursorRow = $cursorStmt->fetch();
        if ($cursorRow) {
            $cursorTrackingEnabled = ((string)$cursorRow['setting_value'] === '1');
        }
    } catch (Throwable $e) {
        $cursorTrackingEnabled = true;
    }

    if ($has('view_market_data')) {
        $marketRows = $pdo->query('SELECT symbol, name, price, change_pct, volume, market_cap, fetched_at FROM market_data ORDER BY symbol LIMIT 10')->fetchAll();
    }

    if ($has('manage_portfolio')) {
        $pfStmt = $pdo->prepare(
            "SELECT p.id AS portfolio_id, p.name AS portfolio_name,
                    pi.id AS item_id, pi.symbol, pi.quantity, pi.purchase_price,
                    (pi.quantity * pi.purchase_price) AS invested
             FROM portfolios p
             LEFT JOIN portfolio_items pi ON pi.portfolio_id = p.id
             WHERE p.user_id = ?
             ORDER BY p.created_at, pi.purchased_at DESC"
        );
        $pfStmt->execute([$userId]);
        $portfolioRows = $pfStmt->fetchAll();
    }

    if ($has('set_basic_alerts') || $has('set_advanced_alerts')) {
        $aStmt = $pdo->prepare('SELECT id, symbol, condition_type, threshold, is_active, created_at FROM alerts WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
        $aStmt->execute([$userId]);
        $alertsRows = $aStmt->fetchAll();
    }

    try {
        $txStateStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'transactions_enabled' LIMIT 1");
        $txStateStmt->execute();
        $txStateRow = $txStateStmt->fetch();
        if ($txStateRow) {
            $transactionsEnabled = ((string)$txStateRow['setting_value'] === '1');
        }
    } catch (Throwable $e) {
        $transactionsConfigAvailable = false;
    }

    if ($has('manage_users')) {
        $adminUsers = $pdo->query(
            "SELECT u.id, u.username, u.email, r.name AS role, u.is_active, u.created_at
             FROM users u
             JOIN roles r ON u.role_id = r.id
             ORDER BY u.created_at DESC
             LIMIT 12"
        )->fetchAll();

        // Uso esplicito vista SQL: monitor sessioni attive con refresh token validi.
        try {
            $activeSessionsRows = $pdo->query(
                'SELECT username, ip_address, user_agent, expires_at, login_time FROM view_active_sessions ORDER BY login_time DESC LIMIT 8'
            )->fetchAll();
        } catch (Throwable $e) {
            $activeSessionsRows = [];
        }
    }

    if ($has('view_reports')) {
        $roleStats = $pdo->query(
            "SELECT r.name, COUNT(u.id) AS cnt
             FROM roles r
             LEFT JOIN users u ON r.id = u.role_id
             GROUP BY r.id, r.name
             ORDER BY r.id"
        )->fetchAll();

        // Uso esplicito vista SQL: matrice accessi utenti/ruoli/permessi.
        try {
            $viewAccessRows = $pdo->query(
                "SELECT user_id, username, email, role_name, COUNT(permission_name) AS permissions_count
                 FROM view_user_access_control
                 GROUP BY user_id, username, email, role_name
                 ORDER BY permissions_count DESC, username ASC
                 LIMIT 12"
            )->fetchAll();
        } catch (Throwable $e) {
            $viewAccessRows = [];
        }
    }
} catch (Throwable $e) {
    // Le sezioni degradano in modo sicuro in caso di errore DB.
}

$initial = strtoupper(substr($username, 0, 1));
$csrfToken = getCsrfToken();
$cursorRoom = 'tm_ai/dashboard/live_cursor';
$cursorClientSuffix = bin2hex(random_bytes(4));
$cursorClientId = 'tmai-' . $userId . '-' . $cursorClientSuffix;
$showCursorLiveSection = $isAdminPreview || (!$sessionCanManageUsers && $cursorTrackingEnabled);
$showAdminCursorMonitor = $sessionCanManageUsers && !$isAdminPreview;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | TradeMarketAi Pro</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap');

        :root {
            --bg: #f4f7fb;
            --panel: #ffffff;
            --surface: #fbfdff;
            --text: #11213a;
            --muted: #5f6f88;
            --line: #d9e2ef;
            --brand: #0f6cbd;
            --brand-2: #22a699;
            --warn: #b54708;
            --danger: #b42318;
            --ok: #067647;
            --head: #3c5373;
            --radius-lg: 18px;
            --radius-md: 12px;
            --shadow: 0 16px 36px rgba(17, 33, 58, 0.10);
            --sidebar-w: 270px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 8% 12%, rgba(34,166,153,0.12), transparent 33%),
                radial-gradient(circle at 92% 90%, rgba(15,108,189,0.14), transparent 30%),
                linear-gradient(155deg, #eef3f9 0%, #f8fbff 100%);
            min-height: 100vh;
        }

        body.dark {
            --bg: #0f1728;
            --panel: #132036;
            --surface: #182844;
            --text: #eaf0fb;
            --muted: #a8b7cf;
            --line: #2a3a53;
            --brand: #3b97df;
            --brand-2: #2dc6af;
            --warn: #ffb876;
            --danger: #ff8a84;
            --ok: #72dba6;
            --head: #b6c6db;
            --shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
            background:
                radial-gradient(circle at 8% 12%, rgba(45,198,175,0.16), transparent 33%),
                radial-gradient(circle at 92% 90%, rgba(59,151,223,0.18), transparent 30%),
                linear-gradient(155deg, #0c1421 0%, #101b2c 100%);
        }

        .theme-toggle {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 20;
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--text);
            border-radius: 999px;
            padding: 9px 14px;
            font: inherit;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(17, 33, 58, 0.16);
        }

        .layout {
            display: grid;
            grid-template-columns: var(--sidebar-w) 1fr;
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            border-right: 1px solid var(--line);
            background: linear-gradient(180deg, #0f6cbd 0%, #0f5ba0 50%, #0d4f8d 100%);
            color: #f0f7ff;
            padding: 22px 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .brand {
            margin: 0;
            font-family: 'Fraunces', serif;
            font-size: 1.45rem;
            letter-spacing: .2px;
            padding: 0 10px;
        }

        .nav-title {
            margin: 10px 10px 4px;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(240,247,255,.75);
        }

        .nav a {
            display: block;
            text-decoration: none;
            color: #edf5ff;
            padding: 10px 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: .92rem;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(255,255,255,.16);
        }

        .user-card {
            margin-top: auto;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 12px;
            padding: 12px;
        }

        .user-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.22);
            font-weight: 800;
        }

        .role-pill {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .role-pill.free { background: #eaf2ff; color: #114b8d; }
        .role-pill.premium { background: #fff4e5; color: #8a4b07; }
        .role-pill.admin { background: #fdecec; color: #912018; }

        .logout {
            margin-top: 8px;
            width: 100%;
            display: inline-block;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            font-size: .88rem;
            border-radius: 10px;
            padding: 9px 10px;
            background: #fff;
            color: #0d4f8d;
        }

        .main {
            padding: 26px;
        }

        .header {
            margin-bottom: 18px;
            animation: rise .35s ease-out;
        }

        .header h1 {
            margin: 0;
            font-family: 'Fraunces', serif;
            font-size: 1.9rem;
            font-weight: 600;
        }

        .header p {
            margin: 8px 0 0;
            color: var(--muted);
        }

        .grid-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 16px;
            animation: rise .35s ease-out;
        }

        .stat-value { font-size: 1.5rem; font-weight: 800; }
        .stat-label { color: var(--muted); font-size: .88rem; }

        .section {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 14px;
            overflow: hidden;
            animation: rise .4s ease-out;
        }

        .section-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .section-head h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
        }

        .section-body {
            padding: 14px 16px;
        }

        .chip-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .8rem;
            border: 1px solid;
            font-weight: 600;
        }

        .chip.on { color: var(--ok); border-color: #8de2bb; background: #ecfdf3; }
        .chip.off { color: var(--danger); border-color: #f6b5b0; background: #fff4f3; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem;
        }

        th, td {
            text-align: left;
            padding: 10px 8px;
            border-bottom: 1px solid var(--line);
        }

        th {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--head);
        }

        tr:last-child td { border-bottom: 0; }

        .up { color: var(--ok); font-weight: 700; }
        .down { color: var(--danger); font-weight: 700; }
        .muted { color: var(--muted); }

        .warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: .9rem;
            margin-bottom: 12px;
        }

        body.dark .warning {
            background: #3a2918;
            border-color: #7a4f2e;
            color: #ffd5ae;
        }

        .success {
            background: #ecfdf3;
            border: 1px solid #abefc6;
            color: #067647;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: .9rem;
            margin-bottom: 12px;
        }

        body.dark .success {
            background: #123126;
            border-color: #1d6046;
            color: #b2f5d3;
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .status-dot {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: .9rem;
        }

        .status-dot::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #98a2b3;
        }

        .status-dot.on::before { background: #12b76a; }
        .status-dot.off::before { background: #f04438; }

        .btn-action {
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 9px 12px;
            font: inherit;
            font-size: .85rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-action.primary {
            background: var(--brand);
            color: #fff;
        }

        .btn-action.danger {
            background: #fff2f1;
            border-color: #fecdca;
            color: #b42318;
        }

        body.dark .btn-action.danger {
            background: #3a1818;
            border-color: #6e2a2a;
            color: #ffb4af;
        }

        .section-note {
            margin-top: 8px;
            font-size: .84rem;
            color: var(--muted);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .kpi {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 11px;
        }

        .kpi strong { display: block; font-size: 1.15rem; margin-top: 4px; }

        .view-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 6px 0 10px;
        }

        .view-toggle {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--text);
            border-radius: 10px;
            padding: 7px 10px;
            font: inherit;
            font-size: .85rem;
            font-weight: 700;
            cursor: pointer;
        }

        .view-toggle:hover {
            border-color: var(--brand);
            color: var(--brand);
        }

        .view-block.is-hidden {
            display: none;
        }

        .cursor-section {
            position: relative;
            overflow: hidden;
        }

        .cursor-status {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }

        .cursor-badge {
            border: 1px solid var(--line);
            background: var(--surface);
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .78rem;
            font-weight: 700;
            color: var(--muted);
        }

        .cursor-badge.ok {
            border-color: #8de2bb;
            background: #ecfdf3;
            color: #067647;
        }

        .cursor-stage {
            border: 1px dashed var(--line);
            border-radius: 10px;
            padding: 10px;
            background: var(--surface);
            color: var(--muted);
            font-size: .86rem;
        }

        .remote-cursor {
            position: fixed;
            left: 0;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22);
            z-index: 9999;
            pointer-events: none;
            transform: translate(-50%, -50%);
            transition: left .05s linear, top .05s linear;
        }

        .remote-cursor::after {
            content: '';
            position: absolute;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #fff;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            opacity: .65;
        }

        .remote-cursor-label {
            position: absolute;
            top: 18px;
            left: 10px;
            background: rgba(17, 33, 58, 0.9);
            color: #ffffff;
            border-radius: 8px;
            font-size: .72rem;
            font-weight: 700;
            padding: 3px 7px;
            white-space: nowrap;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1080px) {
            .grid-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .kpi-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 880px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; }
            .main { padding: 16px; }
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Attiva o disattiva tema scuro"></button>
    <div class="layout">
        <aside class="sidebar">
            <h2 class="brand">TradeMarketAi</h2>

            <div class="nav">
                <p class="nav-title">Navigazione</p>
                <a href="dashboard.php" class="active">Dashboard</a>
                <?php if ($has('view_profile')): ?><a href="profile.php">Profilo</a><?php endif; ?>
                <a href="#plan">Piano</a>
                <a href="#market">Mercato</a>
                <a href="#portfolio">Portafoglio</a>
                <a href="#alerts">Alert</a>
                <?php if ($has('manage_users')): ?><a href="#admin">Amministrazione</a><?php endif; ?>
            </div>

            <div class="user-card">
                <div class="user-row">
                    <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                    <div>
                        <div><?= htmlspecialchars($username) ?></div>
                        <span class="role-pill <?= htmlspecialchars($roleInfo['tone']) ?>"><?= htmlspecialchars($roleInfo['label']) ?></span>
                    </div>
                </div>
                <a class="logout" href="logout.php">Esci</a>
            </div>
        </aside>

        <main class="main">
            <?php if ($flashSuccess): ?>
                <div class="success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="warning"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?php if ($isAdminPreview): ?>
                <div class="warning">
                    Modalita anteprima utente attiva: stai visualizzando la dashboard di <strong><?= htmlspecialchars($previewTargetLabel) ?></strong>.
                    <a href="dashboard.php" style="margin-left:8px;">Torna alla dashboard admin</a>
                </div>
            <?php endif; ?>

            <header class="header">
                <h1>Dashboard Operativa</h1>
                <p>Panoramica professionale di accessi, funzionalita e dati operativi.</p>
            </header>

            <?php if ($showCursorLiveSection): ?>
                <section id="cursor-live" class="section cursor-section">
                    <div class="section-head">
                        <h3>Cursori Live Multiutente (MQTT)</h3>
                        <span class="muted"><?= $isAdminPreview ? 'Vista anteprima utente: monitor cursori dedicato' : 'Canale condiviso in tempo reale' ?></span>
                    </div>
                    <div class="section-body">
                        <div class="cursor-status">
                            <span class="cursor-badge" id="mqttConnState">MQTT: in connessione...</span>
                            <span class="cursor-badge" id="cursorUsersCount">Utenti visibili: 0</span>
                            <span class="cursor-badge">Room: <?= htmlspecialchars($cursorRoom) ?></span>
                        </div>
                        <div class="cursor-stage">
                            Muovi il mouse nella pagina per trasmettere la posizione agli altri utenti collegati alla dashboard.
                        </div>
                        <p class="section-note">
                            Demo tecnica: usa un broker WebSocket locale su localhost:9001. In produzione conviene usare un broker privato autenticato.
                        </p>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($showAdminCursorMonitor): ?>
                <section id="admin-cursor-monitor" class="section cursor-section">
                    <div class="section-head">
                        <h3>📊 Admin Cursor Monitor</h3>
                        <span class="muted">Panoramica di tutti i cursori connessi (visibile solo agli admin)</span>
                    </div>
                    <div class="section-body">
                        <div class="cursor-status" style="margin-bottom: 12px;">
                            <span class="cursor-badge" id="adminCursorCount">Cursori connessi: 0</span>
                            <span class="cursor-badge" id="adminWSState">WebSocket: in connessione...</span>
                        </div>
                        <div id="adminCursorList" class="admin-cursor-list" style="
                            display: grid;
                            grid-gap: 8px;
                            max-height: 300px;
                            overflow-y: auto;
                            padding: 8px;
                            background: var(--surface, rgba(0,0,0,0.02));
                            border-radius: 4px;
                            border: 1px solid var(--line, rgba(0,0,0,0.1));
                        "></div>
                        <p class="section-note">
                            I cursori appaiono qui indipendentemente dallo stato del toggle "Cursori Live". Sezione riservata ai moderatori.
                        </p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="grid-stats">
                <article class="card">
                    <div class="stat-value"><?= count($perms) ?></div>
                    <div class="stat-label">Permessi Attivi</div>
                </article>
                <article class="card">
                    <div class="stat-value"><?= htmlspecialchars($roleInfo['label']) ?></div>
                    <div class="stat-label">Ruolo Corrente</div>
                </article>
                <article class="card">
                    <div class="stat-value"><?= date('d/m/Y') ?></div>
                    <div class="stat-label">Data Sessione</div>
                </article>
                <article class="card">
                    <div class="stat-value">Online</div>
                    <div class="stat-label">Stato Account</div>
                </article>
            </section>

            <section id="plan" class="section">
                <div class="section-head">
                    <h3>Piano Account</h3>
                    <span class="muted">Upgrade Free -> Pro con transazione PDO</span>
                </div>
                <div class="section-body">
                    <div class="action-row" style="margin-bottom:10px;">
                        <div class="status-dot <?= $transactionsEnabled ? 'on' : 'off' ?>">
                            Transazioni: <?= $transactionsEnabled ? 'attive' : 'disattive' ?>
                        </div>
                        <div class="muted">Piano corrente: <strong><?= htmlspecialchars($roleInfo['label']) ?></strong></div>
                    </div>

                    <?php if (!$transactionsConfigAvailable): ?>
                        <p class="muted">Config transazioni non trovata nel DB. Esegui auth_system.sql aggiornato.</p>
                    <?php elseif ($role === 'free'): ?>
                        <form method="POST" class="action-row">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="upgrade_to_pro">
                            <button type="submit" class="btn-action primary">Passa a Pro</button>
                        </form>
                        <p class="section-note">L'upgrade registra la transazione e aggiorna il ruolo su premium nello stesso commit.</p>
                    <?php else: ?>
                        <p class="muted">Il tuo account non e Free: upgrade non necessario.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="section">
                <div class="section-head"><h3>Permessi Effettivi</h3></div>
                <div class="section-body">
                    <div class="chip-wrap">
                        <?php
                        $allPerms = [
                            'view_dashboard', 'view_profile', 'edit_profile', 'view_free_content', 'view_premium_content',
                            'download_files', 'manage_users', 'manage_roles', 'view_reports', 'manage_content',
                            'manage_permissions', 'view_market_data', 'view_market_advanced', 'view_ai_analysis',
                            'run_simulation', 'set_basic_alerts', 'set_advanced_alerts', 'manage_portfolio', 'manage_multi_portfolio'
                        ];
                        foreach ($allPerms as $p):
                        ?>
                            <span class="chip <?= $has($p) ? 'on' : 'off' ?>"><?= htmlspecialchars($p) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section id="market" class="section">
                <div class="section-head">
                    <h3>Dati Mercato</h3>
                    <span class="muted">Permesso: view_market_data</span>
                </div>
                <div class="section-body">
                    <?php if ($has('view_market_data')): ?>
                        <?php if (!empty($marketRows)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Symbol</th><th>Nome</th><th>Prezzo</th><th>Var %</th><th>Volume</th><th>Market Cap</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marketRows as $m): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($m['symbol']) ?></strong></td>
                                            <td><?= htmlspecialchars($m['name']) ?></td>
                                            <td><?= number_format((float)$m['price'], 2, ',', '.') ?></td>
                                            <td class="<?= ((float)$m['change_pct'] >= 0) ? 'up' : 'down' ?>"><?= number_format((float)$m['change_pct'], 2, ',', '.') ?>%</td>
                                            <td><?= number_format((float)$m['volume'], 0, ',', '.') ?></td>
                                            <td><?= number_format((float)$m['market_cap'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="muted">Nessun dato mercato disponibile.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted">Accesso negato: manca il permesso `view_market_data`.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="portfolio" class="section">
                <div class="section-head">
                    <h3>Portafoglio Virtuale</h3>
                    <span class="muted">Permesso: manage_portfolio</span>
                </div>
                <div class="section-body">
                    <?php if ($has('manage_portfolio')): ?>
                        <?php
                        $positions = array_values(array_filter($portfolioRows, static fn($r) => !empty($r['item_id'])));
                        $totalInvested = 0.0;
                        foreach ($positions as $p) { $totalInvested += (float)$p['invested']; }
                        ?>
                        <div class="kpi-grid" style="margin-bottom:12px;">
                            <div class="kpi">Posizioni <strong><?= count($positions) ?></strong></div>
                            <div class="kpi">Capitale Investito <strong><?= number_format($totalInvested, 2, ',', '.') ?></strong></div>
                            <div class="kpi">Portafogli <strong><?= count(array_unique(array_column($portfolioRows, 'portfolio_id'))) ?></strong></div>
                        </div>

                        <?php if (!empty($positions)): ?>
                            <table>
                                <thead>
                                    <tr><th>Symbol</th><th>Qta</th><th>Prezzo Acquisto</th><th>Investito</th><th>Portafoglio</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($positions as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['symbol']) ?></strong></td>
                                        <td><?= (int)$row['quantity'] ?></td>
                                        <td><?= number_format((float)$row['purchase_price'], 2, ',', '.') ?></td>
                                        <td><?= number_format((float)$row['invested'], 2, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($row['portfolio_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="muted">Nessuna posizione in portafoglio.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted">Accesso negato: manca il permesso `manage_portfolio`.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="alerts" class="section">
                <div class="section-head">
                    <h3>Alert Prezzo</h3>
                    <span class="muted">Permessi: set_basic_alerts / set_advanced_alerts</span>
                </div>
                <div class="section-body">
                    <?php if ($has('set_basic_alerts') || $has('set_advanced_alerts')): ?>
                        <?php if (!empty($alertsRows)): ?>
                            <table>
                                <thead>
                                    <tr><th>Symbol</th><th>Condizione</th><th>Soglia</th><th>Stato</th><th>Creato Il</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($alertsRows as $a): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($a['symbol']) ?></strong></td>
                                        <td><?= htmlspecialchars($a['condition_type']) ?></td>
                                        <td><?= number_format((float)$a['threshold'], 2, ',', '.') ?></td>
                                        <td><?= ((int)$a['is_active'] === 1) ? 'Attivo' : 'Disattivo' ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="muted">Nessun alert configurato.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted">Accesso negato: mancano i permessi sugli alert.</p>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($has('manage_users')): ?>
                <section id="admin" class="section">
                    <div class="section-head"><h3>Amministrazione Utenti</h3></div>
                    <div class="section-body">
                        <div class="action-row" style="margin-bottom:14px;">
                            <div class="status-dot <?= $transactionsEnabled ? 'on' : 'off' ?>">
                                Stato transazioni: <?= $transactionsEnabled ? 'attive' : 'disattive' ?>
                            </div>
                            <?php if ($transactionsConfigAvailable): ?>
                                <form method="POST" class="action-row">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="toggle_transactions">
                                    <input type="hidden" name="transactions_enabled" value="<?= $transactionsEnabled ? '0' : '1' ?>">
                                    <button type="submit" class="btn-action <?= $transactionsEnabled ? 'danger' : 'primary' ?>">
                                        <?= $transactionsEnabled ? 'Disattiva transazioni' : 'Attiva transazioni' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Migration DB mancante: impossibile gestire lo switch.</span>
                            <?php endif; ?>
                        </div>

                        <div class="action-row" style="margin-bottom:14px; padding-top: 14px; border-top: 1px solid var(--line);">
                            <div class="status-dot <?= $cursorTrackingEnabled ? 'on' : 'off' ?>">
                                Stato cursori live: <?= $cursorTrackingEnabled ? 'attivi' : 'disattivi' ?>
                            </div>
                            <form method="POST" class="action-row">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="toggle_cursor_tracking">
                                <input type="hidden" name="cursor_tracking_enabled" value="<?= $cursorTrackingEnabled ? '0' : '1' ?>">
                                <button type="submit" class="btn-action <?= $cursorTrackingEnabled ? 'danger' : 'primary' ?>">
                                    <?= $cursorTrackingEnabled ? 'Disattiva cursori live' : 'Attiva cursori live' ?>
                                </button>
                            </form>
                        </div>

                        <?php if (!empty($adminUsers)): ?>
                            <table>
                                <thead>
                                    <tr><th>ID</th><th>Username</th><th>Email</th><th>Ruolo</th><th>Stato</th><th>Registrato</th><th>Azioni</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($adminUsers as $u): ?>
                                    <tr>
                                        <td>#<?= (int)$u['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= htmlspecialchars($u['role']) ?></td>
                                        <td><?= ((int)$u['is_active'] === 1) ? 'Attivo' : 'Disabilitato' ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($u['created_at']))) ?></td>
                                        <td>
                                            <?php if ((int)$u['id'] !== $sessionUserId): ?>
                                                <a
                                                    class="btn-action primary"
                                                    style="padding:6px 10px; font-size:12px;"
                                                    href="dashboard.php?view_user=<?= (int)$u['id'] ?>"
                                                    target="_blank"
                                                    rel="noopener"
                                                >Apri dashboard</a>
                                            <?php else: ?>
                                                <span class="muted">Sessione corrente</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="muted">Nessun utente trovato.</p>
                        <?php endif; ?>

                        <div style="height:12px;"></div>
                        <div class="view-toolbar">
                            <h4 style="margin:0;">Sessioni Attive (Vista SQL)</h4>
                            <button type="button" class="view-toggle" data-target="view-active-sessions" data-default-label="Mostra vista">Mostra vista</button>
                        </div>
                        <div id="view-active-sessions" class="view-block is-hidden">
                            <?php if (!empty($activeSessionsRows)): ?>
                                <table>
                                    <thead>
                                        <tr><th>Username</th><th>IP</th><th>User Agent</th><th>Scadenza</th><th>Login</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($activeSessionsRows as $s): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($s['username']) ?></strong></td>
                                            <td><?= htmlspecialchars($s['ip_address'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars(substr((string)($s['user_agent'] ?? ''), 0, 55)) ?></td>
                                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$s['expires_at']))) ?></td>
                                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$s['login_time']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="muted">Vista `view_active_sessions` non disponibile o senza risultati.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($has('view_reports')): ?>
                <section class="section">
                    <div class="section-head"><h3>Report Ruoli</h3></div>
                    <div class="section-body">
                        <div class="kpi-grid">
                            <?php foreach ($roleStats as $s): ?>
                                <div class="kpi">
                                    <?= htmlspecialchars(strtoupper($s['name'])) ?>
                                    <strong><?= (int)$s['cnt'] ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="height:12px;"></div>
                        <div class="view-toolbar">
                            <h4 style="margin:0;">Matrice Accessi (Vista SQL)</h4>
                            <button type="button" class="view-toggle" data-target="view-user-access" data-default-label="Mostra vista">Mostra vista</button>
                        </div>
                        <div id="view-user-access" class="view-block is-hidden">
                            <?php if (!empty($viewAccessRows)): ?>
                                <table>
                                    <thead>
                                        <tr><th>User ID</th><th>Username</th><th>Email</th><th>Ruolo</th><th>Permessi</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($viewAccessRows as $r): ?>
                                        <tr>
                                            <td>#<?= (int)$r['user_id'] ?></td>
                                            <td><strong><?= htmlspecialchars($r['username']) ?></strong></td>
                                            <td><?= htmlspecialchars($r['email']) ?></td>
                                            <td><?= htmlspecialchars($r['role_name']) ?></td>
                                            <td><?= (int)$r['permissions_count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="muted">Vista `view_user_access_control` non disponibile o senza risultati.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script>
        (function () {
            const key = 'tma-theme';
            const saved = localStorage.getItem(key);
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (!saved && prefersDark)) {
                document.body.classList.add('dark');
            }

            const btn = document.getElementById('themeToggle');
            const syncLabel = function () {
                btn.textContent = document.body.classList.contains('dark') ? 'Modalita Chiara' : 'Modalita Scura';
            };

            btn.addEventListener('click', function () {
                document.body.classList.toggle('dark');
                localStorage.setItem(key, document.body.classList.contains('dark') ? 'dark' : 'light');
                syncLabel();
            });

            const viewButtons = document.querySelectorAll('.view-toggle');
            viewButtons.forEach(function (button) {
                const targetId = button.getAttribute('data-target');
                const target = targetId ? document.getElementById(targetId) : null;
                if (!target) {
                    button.disabled = true;
                    return;
                }

                const updateButtonLabel = function () {
                    const hidden = target.classList.contains('is-hidden');
                    button.textContent = hidden ? 'Mostra vista' : 'Nascondi vista';
                };

                button.addEventListener('click', function () {
                    target.classList.toggle('is-hidden');
                    updateButtonLabel();
                });

                updateButtonLabel();
            });

            syncLabel();

            const mqttBadge = document.getElementById('mqttConnState');
            const usersCountBadge = document.getElementById('cursorUsersCount');

            // Admin sections
            const adminCursorMonitor = document.getElementById('admin-cursor-monitor');
            const adminCursorListContainer = document.getElementById('adminCursorList');
            const adminCursorCount = document.getElementById('adminCursorCount');
            const adminWSState = document.getElementById('adminWSState');
            // Non uscire mai in anticipo: anche senza UI visibile alcuni utenti
            // devono continuare a pubblicare il cursore per la vista admin.

            const room = <?= json_encode($cursorRoom, JSON_UNESCAPED_SLASHES) ?>;
            const selfClientId = <?= json_encode($cursorClientId, JSON_UNESCAPED_SLASHES) ?>;
            const selfUser = {
                id: <?= (int)$userId ?>,
                username: <?= json_encode($username, JSON_UNESCAPED_UNICODE) ?>,
                role: <?= json_encode($roleInfo['label'], JSON_UNESCAPED_UNICODE) ?>,
            };
            const sessionIsAdmin = <?= $sessionCanManageUsers ? 'true' : 'false' ?>;
            const isAdminPreviewMode = <?= $isAdminPreview ? 'true' : 'false' ?>;
            const publishAsAdminCursor = sessionIsAdmin && !isAdminPreviewMode;

            const remotePointers = new Map();
            const adminRemoteCursors = new Map(); // Separate tracking for admin monitor
            let connected = false;
            let lastSentAt = 0;
            let ws = null;
            let reconnectTimer = null;
            const isAdmin = !!adminCursorMonitor; // True only when admin monitor section is present in this view
            const cursorTrackingEnabled = <?= json_encode($cursorTrackingEnabled) ?>;
            const canSeeUserCursors = cursorTrackingEnabled || isAdmin || isAdminPreviewMode;
            const colorFromId = function (value) {
                let hash = 0;
                for (let i = 0; i < value.length; i += 1) {
                    hash = (hash << 5) - hash + value.charCodeAt(i);
                    hash |= 0;
                }
                const hue = Math.abs(hash) % 360;
                return 'hsl(' + hue + ' 74% 48%)';
            };

            const updateConnectionBadge = function () {
                const status = connected ? 'connesso @ ws://localhost:9001' : 'offline';
                if (mqttBadge) {
                    mqttBadge.textContent = 'MQTT: ' + status;
                    mqttBadge.classList.toggle('ok', connected);
                }
                if (adminWSState) {
                    adminWSState.textContent = 'WebSocket: ' + status;
                    adminWSState.classList.toggle('ok', connected);
                }
            };

            const updateUsersCount = function () {
                if (usersCountBadge) {
                    usersCountBadge.textContent = 'Utenti visibili: ' + remotePointers.size;
                }
            };

            const updateAdminMonitor = function () {
                if (!adminCursorListContainer ||!adminCursorCount) {
                    return;
                }

                adminCursorCount.textContent = 'Cursori connessi: ' + adminRemoteCursors.size;

                // Clear and rebuild list
                adminCursorListContainer.innerHTML = '';

                if (adminRemoteCursors.size === 0) {
                    const emptyMsg = document.createElement('p');
                    emptyMsg.style.cssText = 'color: var(--muted); text-align: center; padding: 16px 0; margin: 0;';
                    emptyMsg.textContent = 'Nessun cursore connesso';
                    adminCursorListContainer.appendChild(emptyMsg);
                    return;
                }

                // Show each connected cursor
                adminRemoteCursors.forEach(function (cursor, clientId) {
                    const item = document.createElement('div');
                    item.style.cssText = `
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        padding: 8px;
                        background: white;
                        border-left: 3px solid ${cursor.color};
                        border-radius: 2px;
                    `;

                    const dot = document.createElement('div');
                    dot.style.cssText = `width: 8px; height: 8px; background: ${cursor.color}; border-radius: 50%;`;

                    const info = document.createElement('div');
                    info.style.cssText = 'flex: 1; font-size: 12px;';
                    info.innerHTML = `
                        <strong>${cursor.username}</strong>
                        <div style="color: var(--muted); font-size: 11px;">
                            Posizione: ${Math.round(cursor.x)}, ${Math.round(cursor.y)} | ${new Date(cursor.ts).toLocaleTimeString()}
                        </div>
                    `;

                    const actions = document.createElement('div');
                    if (cursor.userId > 0) {
                        const openUserDashboard = document.createElement('a');
                        openUserDashboard.href = 'dashboard.php?view_user=' + encodeURIComponent(String(cursor.userId));
                        openUserDashboard.target = '_blank';
                        openUserDashboard.rel = 'noopener';
                        openUserDashboard.textContent = 'Apri dashboard';
                        openUserDashboard.style.cssText = 'font-size: 11px; color: var(--brand); text-decoration: none; border: 1px solid var(--line); padding: 4px 8px; border-radius: 6px; white-space: nowrap;';
                        actions.appendChild(openUserDashboard);
                    }

                    item.appendChild(dot);
                    item.appendChild(info);
                    item.appendChild(actions);
                    adminCursorListContainer.appendChild(item);
                });
            };

            const ensurePointer = function (peerId, label, color) {
                const existing = remotePointers.get(peerId);
                if (existing) {
                    return existing;
                }

                const dot = document.createElement('div');
                dot.className = 'remote-cursor';
                dot.style.background = color;

                const text = document.createElement('div');
                text.className = 'remote-cursor-label';
                text.textContent = label;
                dot.appendChild(text);

                document.body.appendChild(dot);
                const entry = { element: dot, lastSeen: Date.now() };
                remotePointers.set(peerId, entry);
                updateUsersCount();
                return entry;
            };

            const removePointer = function (peerId) {
                const entry = remotePointers.get(peerId);
                if (!entry) {
                    return;
                }
                entry.element.remove();
                remotePointers.delete(peerId);
                updateUsersCount();
            };

            const scheduleReconnect = function () {
                if (reconnectTimer !== null) {
                    return;
                }

                reconnectTimer = window.setTimeout(function () {
                    reconnectTimer = null;
                    connectToServer();
                }, 2000);
            };

            const connectToServer = function () {
                if (connected && ws && ws.readyState === WebSocket.OPEN) {
                    return;
                }

                if (ws) {
                    try {
                        ws.close();
                    } catch (e) {
                        // no-op
                    }
                }

                if (mqttBadge) {
                    mqttBadge.textContent = 'MQTT: connessione (ws://localhost:9001)...';
                    mqttBadge.classList.remove('ok');
                }

                try {
                    ws = new WebSocket('ws://localhost:9001');

                    ws.onopen = function () {
                        console.log('[Cursor] WebSocket connected');
                        connected = true;
                        updateConnectionBadge();

                        // Join room
                        ws.send(JSON.stringify({
                            action: 'join',
                            room: room
                        }));
                    };

                    ws.onmessage = function (event) {
                        try {
                            const message = JSON.parse(event.data);

                            if (message.type === 'cursor' && message.clientId !== selfClientId) {
                                const peerId = String(message.clientId || '');
                                const peerLabel = String(message.username || 'Utente');
                                const peerColor = String(message.color || colorFromId(peerId));
                                const peerUserId = Number.parseInt(String(message.userId || '0'), 10) || 0;
                                
                                    // Check if sender is admin
                                    const senderIsAdmin = message.isAdmin === true;

                                    // Regular cursor display (not for admin cursors shown to normal users)
                                    if (!senderIsAdmin && canSeeUserCursors) {
                                        const item = ensurePointer(peerId, peerLabel, peerColor);
                                        item.element.style.left = message.x + 'px';
                                        item.element.style.top = message.y + 'px';
                                        item.lastSeen = Date.now();
                                    }

                                // Update admin monitor (always track all cursors)
                                if (isAdmin) {
                                    adminRemoteCursors.set(peerId, {
                                        clientId: peerId,
                                        userId: peerUserId,
                                        username: peerLabel,
                                        color: peerColor,
                                        x: message.x,
                                        y: message.y,
                                        ts: message.ts || Date.now()
                                    });
                                    updateAdminMonitor();
                                }
                            }
                        } catch (e) {
                            // Ignore parse errors
                        }
                    };

                    ws.onerror = function (err) {
                        console.error('[Cursor] WebSocket error:', err);
                        connected = false;
                        if (mqttBadge) mqttBadge.textContent = 'MQTT: errore connessione';
                        if (mqttBadge) mqttBadge.classList.remove('ok');
                        if (adminWSState) adminWSState.textContent = 'WebSocket: errore connessione';
                        if (adminWSState) adminWSState.classList.remove('ok');
                        scheduleReconnect();
                    };

                    ws.onclose = function () {
                        console.log('[Cursor] WebSocket closed');
                        connected = false;
                        updateConnectionBadge();
                        scheduleReconnect();
                    };
                } catch (e) {
                    console.error('[Cursor] Connection error:', e);
                    connected = false;
                    if (mqttBadge) mqttBadge.textContent = 'MQTT: offline';
                    if (mqttBadge) mqttBadge.classList.remove('ok');
                    if (adminWSState) adminWSState.textContent = 'WebSocket: offline';
                    if (adminWSState) adminWSState.classList.remove('ok');
                    scheduleReconnect();
                }
            };

            const publishPointer = function (clientX, clientY) {
                if (!connected || !ws || ws.readyState !== WebSocket.OPEN) {
                    return;
                }

                const now = Date.now();
                if (now - lastSentAt < 40) {
                    return;
                }
                lastSentAt = now;

                const payload = {
                    action: 'cursor',
                    clientId: selfClientId,
                    userId: selfUser.id,
                    username: selfUser.username,
                    x: clientX,
                    y: clientY,
                    color: colorFromId(selfClientId),
                    ts: now,
                    isAdmin: publishAsAdminCursor
                };

                try {
                    ws.send(JSON.stringify(payload));
                } catch (e) {
                    // Ignore send errors
                }
            };

            window.addEventListener('mousemove', function (event) {
                publishPointer(event.clientX, event.clientY);
            }, { passive: true });

            setInterval(function () {
                const now = Date.now();
                remotePointers.forEach(function (entry, peerId) {
                    if (now - entry.lastSeen > 5000) {
                        removePointer(peerId);
                    }
                });
            }, 2000);

            window.addEventListener('beforeunload', function () {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({
                        action: 'leave'
                    }));
                    ws.close();
                }
            });

            connectToServer();
            updateConnectionBadge();
            updateUsersCount();
        })();
    </script>
</body>
</html>
