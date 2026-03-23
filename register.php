<?php
require_once __DIR__ . '/config/auth.php';

if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Richiesta non valida. Riprova.';
    } else {
        $result = registerUser(
            $_POST['username'] ?? '',
            $_POST['email'] ?? '',
            $_POST['password'] ?? ''
        );

        if ($result['success']) {
            header('Location: login.php?registered=1');
            exit;
        }

        $error = $result['message'];
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione | TradeMarketAi Pro</title>
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
            --label: #314766;
            --input-bg: #fbfdff;
            --radius-xl: 22px;
            --radius-md: 12px;
            --shadow: 0 20px 45px rgba(17, 33, 58, 0.14);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 20%, rgba(34,166,153,0.18), transparent 32%),
                radial-gradient(circle at 90% 86%, rgba(15,108,189,0.2), transparent 30%),
                linear-gradient(140deg, #eef3f9 0%, #f8fbff 100%);
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
            --label: #c0cee1;
            --input-bg: #17263b;
            --shadow: 0 24px 50px rgba(0, 0, 0, 0.42);
            background:
                radial-gradient(circle at 10% 20%, rgba(45,198,175,0.2), transparent 32%),
                radial-gradient(circle at 90% 86%, rgba(59,151,223,0.2), transparent 30%),
                linear-gradient(140deg, #0c1421 0%, #101b2c 100%);
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
            width: min(1020px, 100%);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            box-shadow: var(--shadow);
            animation: enter .45s ease-out;
        }

        .form-wrap { padding: 40px; }

        .panel {
            padding: 40px;
            background: linear-gradient(160deg, #0f6cbd 0%, #1f7fc8 45%, #22a699 100%);
            color: #eef8ff;
            position: relative;
        }

        .panel::after {
            content: '';
            position: absolute;
            width: 280px;
            height: 280px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            left: -120px;
            bottom: -100px;
        }

        .brand {
            margin: 0 0 20px;
            font-family: 'Fraunces', serif;
            font-size: 1.8rem;
            letter-spacing: .3px;
        }

        .panel h1 { margin: 0 0 12px; font-size: 1.9rem; line-height: 1.2; }
        .panel p { margin: 0; line-height: 1.7; }

        h2 { margin: 0; font-size: 1.4rem; font-weight: 800; }
        .sub { margin: 8px 0 24px; color: var(--muted); font-size: .95rem; }

        .alert {
            margin-bottom: 16px;
            border: 1px solid #fecdca;
            background: #fef3f2;
            color: var(--danger);
            border-radius: var(--radius-md);
            padding: 11px 12px;
            font-size: .92rem;
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

        .pwd {
            margin-top: 6px;
            font-size: .82rem;
            color: var(--muted);
            line-height: 1.7;
        }

        .pwd .ok { color: #067647; }
        .pwd .ko { color: #b42318; }

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

        .switch {
            margin-top: 20px;
            text-align: center;
            color: var(--muted);
            font-size: .92rem;
        }

        .switch a { color: var(--brand); text-decoration: none; font-weight: 700; }

        body.dark .alert {
            background: #3a1818;
            border-color: #6e2a2a;
        }

        @keyframes enter {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .form-wrap, .panel { padding: 28px; }
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Attiva o disattiva tema scuro"></button>
    <div class="shell">
        <section class="form-wrap">
            <h2>Crea Il Tuo Account</h2>
            <p class="sub">Registrazione con ruolo Free e attivazione immediata.</p>

            <?php if ($error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" minlength="3" maxlength="50" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required oninput="checkPassword(this.value)">
                    <div class="pwd" id="pwdHints">
                        <div id="r1" class="ko">Minimo 8 caratteri</div>
                        <div id="r2" class="ko">Almeno una lettera maiuscola</div>
                        <div id="r3" class="ko">Almeno un numero</div>
                    </div>
                </div>

                <button class="btn" type="submit">Completa Registrazione</button>
            </form>

            <p class="switch">Hai gia un account? <a href="login.php">Accedi</a></p>
        </section>

        <section class="panel">
            <p class="brand">TradeMarketAi</p>
            <h1>Piattaforma Pronta Per Ruoli, API JWT E Moduli Finance</h1>
            <p>Il tuo account parte dal piano Free con accesso ai contenuti base, profilo e strumenti iniziali di portfolio e alert.</p>
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

        function setRule(id, ok) {
            const el = document.getElementById(id);
            el.className = ok ? 'ok' : 'ko';
        }

        function checkPassword(value) {
            setRule('r1', value.length >= 8);
            setRule('r2', /[A-Z]/.test(value));
            setRule('r3', /[0-9]/.test(value));
        }
    </script>
</body>
</html>