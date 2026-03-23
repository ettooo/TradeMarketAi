<?php
require_once __DIR__ . '/config/auth.php';
$user = requirePermission('view_profile');

$username = $user['username'];
$email = $user['email'];
$role = $user['role'];
$userId = (int)$user['id'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('edit_profile')) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Richiesta non valida.';
    } else {
        $newUsername = trim($_POST['new_username'] ?? '');

        if (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
            $error = 'Username non valido (3-50 caratteri).';
        } else {
            $pdo = getDB();
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$newUsername, $userId]);

            if ($check->fetch()) {
                $error = 'Username gia in uso.';
            } else {
                $upd = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                $upd->execute([$newUsername, $userId]);
                $username = $newUsername;
                $success = 'Profilo aggiornato con successo.';
            }
        }
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo | TradeMarketAi Pro</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap');

        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --text: #11213a;
            --muted: #5f6f88;
            --line: #d9e2ef;
            --brand: #0f6cbd;
            --danger: #b42318;
            --ok: #067647;
            --label: #314766;
            --input-bg: #fbfdff;
            --radius-xl: 18px;
            --radius-md: 12px;
            --shadow: 0 16px 36px rgba(17, 33, 58, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 20% 8%, rgba(34,166,153,0.16), transparent 35%),
                radial-gradient(circle at 85% 90%, rgba(15,108,189,0.14), transparent 30%),
                linear-gradient(150deg, #eef3f9 0%, #f8fbff 100%);
            padding: 30px 20px;
        }

        body.dark {
            --bg: #0f1728;
            --surface: #131f31;
            --text: #eaf0fb;
            --muted: #a8b7cf;
            --line: #2a3a53;
            --brand: #3b97df;
            --danger: #ff8a84;
            --ok: #72dba6;
            --label: #c0cee1;
            --input-bg: #17263b;
            --shadow: 0 20px 44px rgba(0, 0, 0, 0.38);
            background:
                radial-gradient(circle at 20% 8%, rgba(45,198,175,0.18), transparent 35%),
                radial-gradient(circle at 85% 90%, rgba(59,151,223,0.2), transparent 30%),
                linear-gradient(150deg, #0c1421 0%, #101b2c 100%);
        }

        .theme-toggle {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 10;
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--text);
            border-radius: 999px;
            padding: 9px 14px;
            font: inherit;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(17, 33, 58, 0.16);
        }

        .wrap {
            width: min(760px, 100%);
            margin: 0 auto;
        }

        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .back {
            color: var(--brand);
            text-decoration: none;
            font-weight: 700;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            padding: 28px;
            animation: enter .35s ease-out;
        }

        h1 {
            margin: 0;
            font-size: 1.65rem;
            font-family: 'Fraunces', serif;
            letter-spacing: .2px;
        }

        .sub {
            margin: 6px 0 20px;
            color: var(--muted);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .field { margin-bottom: 14px; }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--label);
        }

        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            font: inherit;
            color: var(--text);
            background: var(--input-bg);
        }

        input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(15,108,189,.14);
        }

        input[readonly] { background: var(--bg); color: var(--muted); }

        .btn {
            margin-top: 8px;
            border: 0;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--brand), #0f86d6);
            color: #fff;
            padding: 12px 18px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .alert {
            margin-bottom: 14px;
            border-radius: var(--radius-md);
            padding: 11px 12px;
            border: 1px solid;
            font-size: .92rem;
        }

        .alert.error { color: var(--danger); background: #fef3f2; border-color: #fecdca; }
        .alert.ok { color: var(--ok); background: #ecfdf3; border-color: #abefc6; }

        body.dark .alert.error { background: #3a1818; border-color: #6e2a2a; }
        body.dark .alert.ok { background: #123126; border-color: #1d6046; }

        @keyframes enter {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 700px) {
            .grid { grid-template-columns: 1fr; }
            .card { padding: 22px; }
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Attiva o disattiva tema scuro"></button>
    <div class="wrap">
        <div class="top">
            <a class="back" href="dashboard.php">Torna Alla Dashboard</a>
        </div>

        <section class="card">
            <h1>Profilo Utente</h1>
            <p class="sub">Gestione dati account e impostazioni personali.</p>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert ok"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="grid">
                    <div class="field">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($email) ?>" readonly>
                    </div>

                    <div class="field">
                        <label>Ruolo</label>
                        <input type="text" value="<?= htmlspecialchars(ucfirst($role)) ?>" readonly>
                    </div>
                </div>

                <?php if (can('edit_profile')): ?>
                    <div class="field">
                        <label for="new_username">Username</label>
                        <input id="new_username" type="text" name="new_username" value="<?= htmlspecialchars($username) ?>" required>
                    </div>
                    <button class="btn" type="submit">Salva Modifiche</button>
                <?php else: ?>
                    <div class="field">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($username) ?>" readonly>
                    </div>
                <?php endif; ?>
            </form>
        </section>
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

            syncLabel();
        })();
    </script>
</body>
</html>