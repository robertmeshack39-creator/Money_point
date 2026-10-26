<?php
// login.php - Complete login system

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'connection.php';

$error = '';

// If already logged in, go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: money/dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Tafadhali jaza username/email na password.';
    } else {
        try {
            // Check if user exists by username or email
            $stmt = $pdo->prepare(
                'SELECT id, username, email, password_hash, role 
                 FROM users 
                 WHERE username = :identifier OR email = :identifier 
                 LIMIT 1'
            );
            $stmt->execute(['identifier' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session to prevent fixation
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                // Redirect to dashboard
                header('Location: money/dashboard.php');
                exit;
            } else {
                $error = 'Username/Email au Password ni vibaya. Jaribu tena.';
            }
        } catch (PDOException $e) {
            $error = 'System error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Point — Ingia kwenye Akaunti</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0B1C2C;
            --panel: #11283D;
            --panel-2: #0E2233;
            --gold: #E7A94C;
            --gold-soft: #F4C978;
            --mint: #33B889;
            --paper: #F4F1E9;
            --slate: #8CA0B3;
            --line: rgba(231,169,76,0.18);
            --danger: #E1604F;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            height: 100%;
        }
        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(1200px 600px at 85% -10%, rgba(51,184,137,0.16), transparent 60%),
                radial-gradient(900px 500px at -10% 110%, rgba(231,169,76,0.12), transparent 55%),
                linear-gradient(160deg, #081422 0%, var(--ink) 45%, #0C2135 100%);
            color: var(--paper);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
        }
        .stage {
            width: 100%;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            min-height: 100vh;
        }
        /* LEFT: brand / signature */
        .brand {
            position: relative;
            padding: 56px 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid var(--line);
            overflow: hidden;
        }
        .brand::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(var(--line) 1px, transparent 1px),
                linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 42px 42px;
            -webkit-mask-image: radial-gradient(650px 500px at 30% 30%, #000 0%, transparent 75%);
            mask-image: radial-gradient(650px 500px at 30% 30%, #000 0%, transparent 75%);
            opacity: 0.5;
            pointer-events: none;
        }
        .brand-mark {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 20px;
            letter-spacing: 0.2px;
            z-index: 1;
        }
        .brand-mark .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 0 4px rgba(231,169,76,0.18);
        }
        .brand-copy {
            z-index: 1;
            max-width: 420px;
        }
        .brand-copy .eyebrow {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--mint);
            margin-bottom: 18px;
            display: block;
        }
        .brand-copy h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 40px;
            line-height: 1.12;
            font-weight: 600;
            color: var(--paper);
        }
        .brand-copy p {
            margin-top: 16px;
            color: var(--slate);
            font-size: 15px;
            line-height: 1.6;
        }
        /* Device mockup */
        .device {
            z-index: 1;
            margin-top: 44px;
            width: 230px;
            background: linear-gradient(180deg, #16324A, #0E2233);
            border: 1px solid rgba(231,169,76,0.28);
            border-radius: 26px;
            padding: 16px;
            box-shadow: 0 30px 60px -20px rgba(0,0,0,0.55), 0 0 0 1px rgba(255,255,255,0.02) inset;
        }
        .device-notch {
            width: 40px;
            height: 5px;
            border-radius: 4px;
            background: rgba(255,255,255,0.14);
            margin: 0 auto 14px;
        }
        .device-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--slate);
            margin-bottom: 10px;
        }
        .device-amount {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            font-weight: 600;
            color: var(--gold-soft);
            margin: 6px 0 14px;
        }
        .device-check {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(51,184,137,0.12);
            border: 1px solid rgba(51,184,137,0.35);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 12px;
            color: var(--mint);
        }
        .device-check svg {
            flex: none;
        }
        .pulse {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--mint);
            margin-left: auto;
            animation: pulse 1.8s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(51,184,137,0.55); }
            50% { box-shadow: 0 0 0 6px rgba(51,184,137,0); }
        }
        .brand-foot {
            z-index: 1;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--slate);
            letter-spacing: 0.04em;
        }
        /* RIGHT: form */
        .panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: linear-gradient(180deg, rgba(255,255,255,0.015), transparent);
        }
        .card {
            width: 100%;
            max-width: 380px;
        }
        .card h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            font-weight: 600;
            color: var(--paper);
            margin-bottom: 6px;
        }
        .card .sub {
            color: var(--slate);
            font-size: 14px;
            margin-bottom: 32px;
        }
        .field {
            margin-bottom: 18px;
        }
        .field label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--slate);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }
        .field input {
            width: 100%;
            background: var(--panel-2);
            border: 1px solid rgba(140,160,179,0.22);
            border-radius: 10px;
            padding: 13px 14px;
            color: var(--paper);
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .field input::placeholder {
            color: #5C7188;
        }
        .field input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(231,169,76,0.15);
        }
        .row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: var(--slate);
            margin: -4px 0 24px;
        }
        .row-between a {
            color: var(--gold-soft);
            text-decoration: none;
        }
        .row-between a:hover {
            text-decoration: underline;
        }
        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox input {
            accent-color: var(--gold);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        button.submit {
            width: 100%;
            background: linear-gradient(180deg, var(--gold-soft), var(--gold));
            color: #12233A;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: transform .1s ease, box-shadow .15s ease;
            box-shadow: 0 10px 24px -10px rgba(231,169,76,0.55);
        }
        button.submit:hover {
            transform: translateY(-1px);
        }
        button.submit:active {
            transform: translateY(0);
        }
        .alert {
            background: rgba(225,96,79,0.12);
            border: 1px solid rgba(225,96,79,0.4);
            color: #F3B7AD;
            font-size: 13.5px;
            padding: 11px 13px;
            border-radius: 9px;
            margin-bottom: 20px;
        }
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 26px 0;
            color: var(--slate);
            font-size: 12px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(140,160,179,0.2);
        }
        .foot-note {
            text-align: center;
            font-size: 13px;
            color: var(--slate);
        }
        .foot-note a {
            color: var(--gold-soft);
            text-decoration: none;
            font-weight: 500;
        }
        .foot-note a:hover {
            text-decoration: underline;
        }

        @media (max-width: 880px) {
            .stage {
                grid-template-columns: 1fr;
            }
            .brand {
                display: none;
            }
            .panel {
                padding: 28px 20px;
                min-height: 100vh;
            }
        }
    </style>
</head>
<body>
    <div class="stage">

        <!-- LEFT: brand / signature -->
        <section class="brand">
            <div class="brand-mark">
                <span class="dot"></span> MONEY&nbsp;POINT
            </div>

            <div class="brand-copy">
                <span class="eyebrow">Uhamisho wa Papo Hapo</span>
                <h1>Fedha zako, mkononi mwako, kwa sekunde.</h1>
                <p>Ingia ili kutuma, kupokea na kufuatilia miamala yako yote ya pesa mtandaoni kwa usalama.</p>

                <div class="device">
                    <div class="device-notch"></div>
                    <div class="device-row"><span>KUTOKA</span><span>KWENDA</span></div>
                    <div class="device-row"><span>0712 *** 348</span><span>0655 *** 902</span></div>
                    <div class="device-amount">TSh 120,000</div>
                    <div class="device-check">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                            <path d="M20 6L9 17l-5-5" stroke="#33B889" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Muamala umekamilika
                        <span class="pulse"></span>
                    </div>
                </div>
            </div>

            <div class="brand-foot">SRARS-GRADE SECURITY · MARIADB · SESSION-VERIFIED</div>
        </section>

        <!-- RIGHT: login form -->
        <section class="panel">
            <div class="card">
                <h2>Karibu tena</h2>
                <p class="sub">Ingia kwenye akaunti yako ya Money Point.</p>

                <?php if ($error): ?>
                    <div class="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" autocomplete="off" novalidate>

                    <div class="field">
                        <label for="identifier">Username au Email</label>
                        <input type="text" id="identifier" name="identifier" placeholder="jina.lako au jina@mfano.com" required autofocus>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="row-between">
                        <label class="checkbox">
                            <input type="checkbox" name="remember"> Nikumbuke
                        </label>
                        <a href="forgot_password.php">Umesahau password?</a>
                    </div>

                    <button type="submit" class="submit">Ingia</button>
                </form>

                <div class="divider">AU</div>
                <p class="foot-note">Huna akaunti? <a href="register.php">Jisajili sasa</a></p>
            </div>
        </section>

    </div>
</body>
</html>