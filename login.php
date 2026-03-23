<?php
require_once __DIR__ . '/config/auth.php';

if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Richiesta non valida. Riprova.';
    } else {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        }
        $error = $result['message'];
    }
}

$csrfToken = getCsrfToken();

if (($_GET['registered'] ?? '') === '1') {
    $success = 'Registrazione completata. Ora puoi accedere.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TradeMarketAi Pro</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap');

        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --text: #11213a;
            --muted: #5f6f88;
            --line: #d9e2ef;
            --brand: #0f6cbd;
            --brand-2: #22a699;
            --danger: #b42318;
            --ok: #067647;
            --label: #314766;
            --input-bg: #fbfdff;
            --shadow: 0 20px 45px rgba(17, 33, 58, 0.14);
            --radius-xl: 22px;
            --radius-md: 12px;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 15% 10%, rgba(34,166,153,0.18), transparent 35%),
                radial-gradient(circle at 85% 80%, rgba(15,108,189,0.18), transparent 30%),
                linear-gradient(145deg, #eef3f9 0%, #f8fbff 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        body.dark {
            --bg: #0f1728;
            --surface: #131f31;
            --text: #eaf0fb;
            --muted: #a8b7cf;
            --line: #2a3a53;
            --brand: #3b97df;
            --brand-2: #2dc6af;
            --danger: #ff8a84;
            --ok: #72dba6;
            --label: #c0cee1;
            --input-bg: #17263b;
            --shadow: 0 24px 50px rgba(0, 0, 0, 0.42);
            background:
                radial-gradient(circle at 15% 10%, rgba(45,198,175,0.18), transparent 35%),
                radial-gradient(circle at 85% 80%, rgba(59,151,223,0.2), transparent 30%),
                linear-gradient(145deg, #0c1421 0%, #101b2c 100%);
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

        .shell {
            width: min(980px, 100%);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            animation: rise .45s ease-out;
        }

        .panel {
            padding: 42px;
            background: linear-gradient(160deg, #0f6cbd 0%, #0e5aa2 45%, #0c436f 100%);
            color: #f5f9ff;
            position: relative;
        }

        .panel::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            right: -120px;
            top: -80px;
        }

        .brand {
            font-family: 'Fraunces', serif;
            font-size: 1.8rem;
            margin: 0 0 22px;
            letter-spacing: .3px;
        }

        .panel h1 {
            margin: 0 0 12px;
            font-size: 1.9rem;
            line-height: 1.2;
        }

        .panel p {
            margin: 0;
            color: rgba(245,249,255,.9);
            line-height: 1.7;
        }

        .form-wrap {
            padding: 42px;
            background: var(--surface);
        }

        .form-wrap h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 800;
        }

        .sub {
            margin: 8px 0 24px;
            color: var(--muted);
            font-size: .95rem;
        }

        label {
            display: block;
            margin: 0 0 8px;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--label);
        }

        .field { margin-bottom: 16px; }

        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            font: inherit;
            color: var(--text);
            background: var(--input-bg);
            transition: border-color .2s, box-shadow .2s;
        }

        input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(15,108,189,.14);
        }

        .btn {
            width: 100%;
            margin-top: 8px;
            border: 0;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--brand), #0f86d6);
            color: #fff;
            padding: 12px 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover { filter: brightness(1.03); }

        .alert {
            margin-bottom: 16px;
            border-radius: var(--radius-md);
            padding: 11px 12px;
            font-size: .92rem;
            border: 1px solid;
        }

        .alert.error { color: var(--danger); background: #fef3f2; border-color: #fecdca; }
        .alert.ok { color: var(--ok); background: #ecfdf3; border-color: #abefc6; }

        body.dark .alert.error { background: #3a1818; border-color: #6e2a2a; }
        body.dark .alert.ok { background: #123126; border-color: #1d6046; }

        .switch {
            margin-top: 20px;
            text-align: center;
            color: var(--muted);
            font-size: .92rem;
        }

        .switch a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .panel { padding: 28px; }
            .form-wrap { padding: 28px; }
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Attiva o disattiva tema scuro"></button>
    <div class="shell">
        <section class="panel">
            <p class="brand">TradeMarketAi</p>
            <h1>Accesso Sicuro Con Gestione Ruoli E Permessi</h1>
            <p>Ambiente professionale per autenticazione, autorizzazioni granulari, API JWT e casi d'uso finance integrati.</p>
        </section>

        <section class="form-wrap">
            <h2>Accedi Al Tuo Account</h2>
            <p class="sub">Inserisci le tue credenziali per entrare nella dashboard.</p>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert ok"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button class="btn" type="submit">Entra Nella Dashboard</button>
            </form>

            <p class="switch">Non hai un account? <a href="register.php">Registrati</a></p>
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