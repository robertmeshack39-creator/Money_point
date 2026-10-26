<?php
/**
 * money/lines.php — Money Point 
 * View + update each mobile money line's Till / Lipa Namba float for today,
 * and register new lines.
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../connection.php';

$current_user = $_SESSION['username'] ?? 'User';
$current_role = $_SESSION['role'] ?? 'user';
$today = date('Y-m-d');

$success = '';
$error = '';

// ---------------------------------------------------------------
// Day status
// ---------------------------------------------------------------
try {
    $statusQuery = $pdo->prepare("SELECT * FROM `daily_closing` WHERE `date` = ?");
    $statusQuery->execute([$today]);
    $dayRow = $statusQuery->fetch();
} catch (PDOException $e) {
    $dayRow = null;
}
$day_status = $dayRow['status'] ?? 'not_started';

// ---------------------------------------------------------------
// Handle: update float for existing lines
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_float') {

    if ($day_status !== 'open') {
        $error = 'Huwezi kubadilisha float — siku haijafunguliwa (Day is not open).';
    } else {
        try {
            $pdo->beginTransaction();

            $lineIds = $_POST['line_id'] ?? [];
            $tills   = $_POST['till'] ?? [];
            $lipas   = $_POST['lipa'] ?? [];

            foreach ($lineIds as $i => $lineId) {
                $lineId = (int) $lineId;
                $till = (float) str_replace(',', '', $tills[$i] ?? 0);
                $lipa = (float) str_replace(',', '', $lipas[$i] ?? 0);

                $check = $pdo->prepare("SELECT id FROM `daily_balances` WHERE line_id = ? AND date = ?");
                $check->execute([$lineId, $today]);
                $existing = $check->fetch();

                if ($existing) {
                    $upd = $pdo->prepare("
                        UPDATE `daily_balances`
                        SET closing_till = ?, closing_lipa = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$till, $lipa, $existing['id']]);
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO `daily_balances` (line_id, date, opening_till, opening_lipa, closing_till, closing_lipa)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$lineId, $today, $till, $lipa, $till, $lipa]);
                }
            }

            $pdo->commit();
            $success = 'Float za lines zimesasishwa kwa mafanikio.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Imeshindikana kusasisha float: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------
// Handle: add a new line - FIXED: mobile_lines
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_line') {
    $lineName = trim($_POST['line_name'] ?? '');
    $network  = trim($_POST['network'] ?? '');

    if ($lineName === '' || $network === '') {
        $error = 'Tafadhali jaza jina la line na network.';
    } else {
        try {
            // FIXED: Changed 'lines' to 'mobile_lines'
            $ins = $pdo->prepare("INSERT INTO `mobile_lines` (line_name, network, status) VALUES (?, ?, 'active')");
            $ins->execute([$lineName, $network]);
            $success = 'Line mpya "' . htmlspecialchars($lineName) . '" imeongezwa.';
        } catch (PDOException $e) {
            $error = 'Imeshindikana kuongeza line: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------
// Fetch all active lines - FIXED: mobile_lines
// ---------------------------------------------------------------
try {
    // FIXED: Changed 'lines' to 'mobile_lines'
    $linesQuery = $pdo->query("SELECT * FROM `mobile_lines` WHERE `status` = 'active' ORDER BY `line_name`");
    $mp_all_lines = $linesQuery->fetchAll();
} catch (PDOException $e) {
    $mp_all_lines = [];
}

try {
    $balQuery = $pdo->prepare("SELECT * FROM `daily_balances` WHERE `date` = ?");
    $balQuery->execute([$today]);
    $mp_balances_raw = $balQuery->fetchAll();
} catch (PDOException $e) {
    $mp_balances_raw = [];
}

$mp_lines = [];
$mp_total_till = 0;
$mp_total_lipa = 0;
foreach ($mp_all_lines as $line) {
    $match = null;
    foreach ($mp_balances_raw as $b) {
        if ($b['line_id'] == $line['id']) { $match = $b; break; }
    }
    $till = $match ? floatval($match['closing_till'] ?? $match['opening_till'] ?? 0) : 0;
    $lipa = $match ? floatval($match['closing_lipa'] ?? $match['opening_lipa'] ?? 0) : 0;
    $mp_total_till += $till;
    $mp_total_lipa += $lipa;
    $mp_lines[] = [
        'id'      => $line['id'],
        'name'    => $line['line_name'],
        'network' => $line['network'],
        'till'    => $till,
        'lipa'    => $lipa,
    ];
}
$mp_total_float = $mp_total_till + $mp_total_lipa;

$mp_network_colors = [
    'vodacom' => '#33B889', 'mpesa'   => '#33B889',
    'airtel'  => '#E1604F',
    'tigo'    => '#F4C978', 'yas'     => '#F4C978',
    'halotel' => '#5B8DEF', 'halopesa'=> '#5B8DEF',
];
function mp_line_color(string $network): string {
    global $mp_network_colors;
    $key = strtolower(str_replace(' ', '', $network));
    return $mp_network_colors[$key] ?? '#8CA0B3';
}
function mp_money(float $n): string {
    return 'TSh ' . number_format($n, 0);
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lines / Float — Money Point</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
:root{
  --ink:#0B1C2C; --panel:#11283D; --panel-2:#0E2233;
  --gold:#E7A94C; --gold-soft:#F4C978; --mint:#33B889; --red:#E1604F;
  --paper:#F4F1E9; --slate:#8CA0B3; --line:rgba(231,169,76,0.16);
}
*{ box-sizing:border-box; }
body{
  font-family:'Inter', sans-serif;
  background:
    radial-gradient(1100px 550px at 90% -10%, rgba(51,184,137,0.10), transparent 55%),
    radial-gradient(900px 500px at -10% 110%, rgba(231,169,76,0.08), transparent 55%),
    linear-gradient(165deg, #081422 0%, var(--ink) 45%, #0C2135 100%);
  color:var(--paper); min-height:100vh; margin:0;
}
.mp-shell{ display:flex; min-height:100vh; }
.mp-sidebar{ width:250px; flex:none; background:var(--panel-2); border-right:1px solid var(--line); display:flex; flex-direction:column; position:sticky; top:0; height:100vh; }
.mp-sidebar .brand{ display:flex; align-items:center; gap:10px; padding:22px 22px 18px; font-family:'Space Grotesk', sans-serif; font-weight:700; font-size:18px; border-bottom:1px solid var(--line); }
.mp-sidebar .brand .dot{ width:9px; height:9px; border-radius:50%; background:var(--gold); box-shadow:0 0 0 4px rgba(231,169,76,0.16); }
.mp-nav{ padding:14px 12px; flex:1; overflow-y:auto; }
.mp-nav a{ display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:10px; color:var(--slate); text-decoration:none; font-size:14.5px; font-weight:500; margin-bottom:4px; transition:background .15s ease, color .15s ease; }
.mp-nav a i{ font-size:16px; width:18px; text-align:center; }
.mp-nav a:hover{ background:rgba(231,169,76,0.08); color:var(--paper); }
.mp-nav a.active{ background:linear-gradient(90deg, rgba(231,169,76,0.16), rgba(231,169,76,0.02)); color:var(--gold-soft); border:1px solid var(--line); }
.mp-nav .divider{ height:1px; background:var(--line); margin:12px 6px; }
.mp-sidebar .sidebar-foot{ padding:16px 22px; border-top:1px solid var(--line); font-family:'JetBrains Mono', monospace; font-size:10.5px; color:var(--slate); letter-spacing:.04em; }
.mp-main{ flex:1; min-width:0; }
.mp-topbar{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; padding:22px 32px; border-bottom:1px solid var(--line); background:rgba(11,28,44,0.5); backdrop-filter:blur(6px); position:sticky; top:0; z-index:10; }
.mp-topbar h1{ font-family:'Space Grotesk', sans-serif; font-size:19px; font-weight:600; color:var(--paper); margin:0; }
.mp-topbar .meta{ display:flex; align-items:center; gap:18px; font-size:13.5px; color:var(--slate); flex-wrap:wrap; }
.mp-topbar .meta strong{ color:var(--paper); font-weight:600; }
.status-pill{ display:inline-flex; align-items:center; gap:7px; padding:6px 13px; border-radius:999px; font-size:12.5px; font-weight:600; font-family:'JetBrains Mono', monospace; letter-spacing:.03em; }
.status-pill.open{ background:rgba(51,184,137,0.14); color:var(--mint); border:1px solid rgba(51,184,137,0.4); }
.status-pill.closed{ background:rgba(225,96,79,0.14); color:var(--red); border:1px solid rgba(225,96,79,0.4); }
.status-pill.idle{ background:rgba(140,160,179,0.12); color:var(--slate); border:1px solid rgba(140,160,179,0.3); }
.status-pill .dot{ width:6px; height:6px; border-radius:50%; background:currentColor; }
.content{ padding:28px 32px 60px; }
.mp-card{ background:linear-gradient(180deg, var(--panel), var(--panel-2)); border:1px solid var(--line); border-radius:16px; padding:20px 22px; }
.section-title{ font-family:'Space Grotesk', sans-serif; font-size:16px; font-weight:600; color:var(--paper); display:flex; align-items:center; gap:9px; margin-bottom:14px; }
.section-title .eyebrow{ font-family:'JetBrains Mono', monospace; font-size:10.5px; color:var(--slate); font-weight:500; letter-spacing:.06em; text-transform:uppercase; margin-left:auto; }
.table-mp{ --bs-table-bg:transparent; color:var(--paper); margin-bottom:0; }
.table-mp thead th{ font-family:'JetBrains Mono', monospace; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--slate); border-bottom:1px solid var(--line); font-weight:500; padding-bottom:12px; }
.table-mp td{ border-bottom:1px solid rgba(140,160,179,0.08); padding:12px 8px; font-size:14px; vertical-align:middle; }
.table-mp tr:last-child td{ border-bottom:none; }
.table-mp tr.total-row td{ font-weight:600; color:var(--gold-soft); border-top:1px solid var(--line); border-bottom:none; }
.network-badge{ display:inline-flex; align-items:center; gap:7px; font-weight:500; }
.network-dot{ width:8px; height:8px; border-radius:50%; }
.mp-form-control{ background:var(--panel-2); border:1px solid rgba(140,160,179,0.22); color:var(--paper); border-radius:9px; padding:9px 11px; font-size:14px; width:100%; }
.mp-form-control:focus{ background:var(--panel-2); color:var(--paper); border-color:var(--gold); box-shadow:0 0 0 3px rgba(231,169,76,0.14); outline:none; }
.mp-form-control::placeholder{ color:#5C7188; }
.mp-form-label{ font-size:11px; font-weight:500; color:var(--slate); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; display:block; }
.btn-mp-gold{ background:linear-gradient(180deg, var(--gold-soft), var(--gold)); border:none; color:#12233A; font-weight:600; border-radius:10px; padding:11px 20px; font-family:'Space Grotesk', sans-serif; font-size:14px; box-shadow:0 10px 22px -10px rgba(231,169,76,0.5); }
.btn-mp-gold:hover{ color:#12233A; filter:brightness(1.05); }
.btn-mp-outline{ background:transparent; border:1px solid var(--line); color:var(--paper); border-radius:10px; padding:10px 18px; font-size:14px; font-weight:500; }
.btn-mp-outline:hover{ background:rgba(231,169,76,0.08); color:var(--paper); border-color:var(--gold); }
.alert-mp{ border-radius:10px; padding:12px 15px; font-size:14px; margin-bottom:18px; }
.alert-mp.success{ background:rgba(51,184,137,0.12); border:1px solid rgba(51,184,137,0.4); color:#8FE3C3; }
.alert-mp.error{ background:rgba(225,96,79,0.12); border:1px solid rgba(225,96,79,0.4); color:#F3B7AD; }
.summary-line{ display:flex; justify-content:space-between; align-items:center; padding:11px 0; border-bottom:1px solid rgba(140,160,179,0.1); font-size:14px; }
.summary-line:last-child{ border-bottom:none; }
.summary-line .l{ color:var(--slate); }
.summary-line .v{ font-weight:600; font-family:'JetBrains Mono', monospace; }
.summary-line.grand{ margin-top:6px; padding-top:16px; border-top:1px solid var(--line); font-size:16px; }
.summary-line.grand .v{ color:var(--gold-soft); font-size:19px; }
.disabled-note{ font-size:12.5px; color:var(--slate); margin-top:10px; }
@media (max-width: 900px){
  .mp-sidebar{ position:fixed; left:-260px; z-index:40; transition:left .2s ease; box-shadow:0 0 40px rgba(0,0,0,.5); }
  .mp-sidebar.open{ left:0; }
  .content{ padding:20px 16px 50px; }
  .mp-topbar{ padding:16px 18px; }
}
</style>
</head>
<body>
<div class="mp-shell">

  <aside class="mp-sidebar">
    <div class="brand"><span class="dot"></span> MONEY&nbsp;POINT</div>
    <nav class="mp-nav">
      <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
      <a href="lines.php" class="active"><i class="bi bi-phone"></i> Lines / Float</a>
      <div class="divider"></div>
      <a href="open-day.php"><i class="bi bi-play-circle"></i> Open Day</a>
      <a href="close-day.php"><i class="bi bi-lock"></i> Close Day</a>
      <div class="divider"></div>
      <a href="history.php"><i class="bi bi-calendar3"></i> Daily History</a>
      <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
      <div class="divider"></div>
     
      <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
    <div class="sidebar-foot">MARIADB · SESSION-VERIFIED · v1.0</div>
  </aside>

  <main class="mp-main">
    <header class="mp-topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" class="btn btn-mp-outline d-md-none py-2 px-3"><i class="bi bi-list"></i></button>
        <h1>Lines / Float</h1>
      </div>
      <div class="meta">
        <span><?= date('l, j F Y') ?></span>
        <span>Welcome, <strong><?= htmlspecialchars($current_user) ?></strong></span>
        <span class="status-pill <?= $day_status === 'open' ? 'open' : ($day_status === 'closed' ? 'closed' : 'idle') ?>">
          <span class="dot"></span> DAY <?= $day_status === 'not_started' ? 'NOT STARTED' : strtoupper($day_status) ?>
        </span>
      </div>
    </header>

    <div class="content">

      <?php if ($success): ?><div class="alert-mp success"><i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert-mp error"><i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="row g-4">
        <!-- ===================== LINES + FLOAT EDIT FORM ===================== -->
        <div class="col-lg-8">
          <div class="mp-card">
            <div class="section-title">Mobile Money Lines <span class="eyebrow"><?= count($mp_lines) ?> Lines</span></div>

            <?php if (empty($mp_lines)): ?>
              <p class="text-secondary py-3">Hakuna line zilizosajiliwa bado. Ongeza line kwa fomu iliyo pembeni.</p>
            <?php else: ?>
            <form method="POST" id="floatForm">
              <input type="hidden" name="action" value="update_float">
              <div class="table-responsive">
                <table class="table table-mp">
                  <thead>
                    <tr>
                      <th>Line</th>
                      <th>Network</th>
                      <th style="width:170px;">Till</th>
                      <th style="width:170px;">Lipa Namba</th>
                      <th class="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($mp_lines as $i => $line): ?>
                    <tr>
                      <td>
                        <?= htmlspecialchars($line['name']) ?>
                        <input type="hidden" name="line_id[]" value="<?= $line['id'] ?>">
                      </td>
                      <td>
                        <span class="network-badge">
                          <span class="network-dot" style="background:<?= mp_line_color($line['network']) ?>"></span>
                          <?= htmlspecialchars($line['network']) ?>
                        </span>
                      </td>
                      <td>
                        <input type="text" inputmode="numeric" class="mp-form-control line-till" name="till[]"
                               value="<?= number_format($line['till']) ?>" <?= $day_status !== 'open' ? 'disabled' : '' ?>>
                      </td>
                      <td>
                        <input type="text" inputmode="numeric" class="mp-form-control line-lipa" name="lipa[]"
                               value="<?= number_format($line['lipa']) ?>" <?= $day_status !== 'open' ? 'disabled' : '' ?>>
                      </td>
                      <td class="text-end line-total" data-total><?= mp_money($line['till'] + $line['lipa']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                      <td colspan="2">TOTAL</td>
                      <td id="sumTill"><?= mp_money($mp_total_till) ?></td>
                      <td id="sumLipa"><?= mp_money($mp_total_lipa) ?></td>
                      <td class="text-end" id="sumFloat"><?= mp_money($mp_total_float) ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <?php if ($day_status === 'open'): ?>
                <button type="submit" class="btn btn-mp-gold mt-3"><i class="bi bi-save me-1"></i> Hifadhi Mabadiliko</button>
              <?php else: ?>
                <p class="disabled-note"><i class="bi bi-info-circle me-1"></i> Float haziwezi kubadilishwa mpaka siku ifunguliwe (Open Day).</p>
              <?php endif; ?>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- ===================== SIDE PANEL: TOTALS + ADD LINE ===================== -->
        <div class="col-lg-4">
          <div class="mp-card mb-4">
            <div class="section-title">Float Summary</div>
            <div class="summary-line"><span class="l">Total Till</span><span class="v"><?= mp_money($mp_total_till) ?></span></div>
            <div class="summary-line"><span class="l">Total Lipa Namba</span><span class="v"><?= mp_money($mp_total_lipa) ?></span></div>
            <div class="summary-line grand"><span class="l">Total Float</span><span class="v"><?= mp_money($mp_total_float) ?></span></div>
          </div>

          <div class="mp-card">
            <div class="section-title"><i class="bi bi-plus-circle"></i> Ongeza Line Mpya</div>
            <form method="POST">
              <input type="hidden" name="action" value="add_line">
              <div class="mb-3">
                <label class="mp-form-label">Jina la Line</label>
                <input type="text" name="line_name" class="mp-form-control" placeholder="Line 5" required>
              </div>
              <div class="mb-3">
                <label class="mp-form-label">Network</label>
                <input type="text" name="network" class="mp-form-control" placeholder="M-Pesa / Airtel / Yas / HaloPesa" required>
              </div>
              <button type="submit" class="btn btn-mp-outline w-100"><i class="bi bi-plus-lg me-1"></i> Ongeza Line</button>
            </form>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.mp-sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target) && !toggle.contains(e.target)) sidebar.classList.remove('open');
    });
  }

  const form = document.getElementById('floatForm');
  if (!form) return;

  const fmt = (n) => 'TSh ' + Math.round(n).toLocaleString('en-US');
  const num = (v) => { const n = parseFloat((v || '0').toString().replace(/,/g, '')); return isNaN(n) ? 0 : n; };

  function recalc() {
    let totalTill = 0, totalLipa = 0;
    document.querySelectorAll('#floatForm tbody tr').forEach((row) => {
      const tillInput = row.querySelector('.line-till');
      const lipaInput = row.querySelector('.line-lipa');
      if (!tillInput || !lipaInput) return;
      const till = num(tillInput.value);
      const lipa = num(lipaInput.value);
      totalTill += till;
      totalLipa += lipa;
      const totalCell = row.querySelector('[data-total]');
      if (totalCell) totalCell.textContent = fmt(till + lipa);
    });
    document.getElementById('sumTill').textContent = fmt(totalTill);
    document.getElementById('sumLipa').textContent = fmt(totalLipa);
    document.getElementById('sumFloat').textContent = fmt(totalTill + totalLipa);
  }

  form.addEventListener('input', recalc);
});
</script>
</body>
</html>