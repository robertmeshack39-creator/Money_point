<?php
/**
 * money/open-day.php — Open Day Page
 * Start a new business day by entering opening balances
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../connection.php';

$current_user = $_SESSION['username'] ?? 'User';
$today = date('Y-m-d');
$currentDate = date('l, j F Y');

$success = '';
$error = '';

// ---------------------------------------------------------------
// Check if day already exists
// ---------------------------------------------------------------
try {
    $statusQuery = $pdo->prepare("SELECT * FROM `daily_closing` WHERE `date` = ?");
    $statusQuery->execute([$today]);
    $dayRow = $statusQuery->fetch();
} catch (PDOException $e) {
    $dayRow = null;
}

$day_status = $dayRow['status'] ?? 'not_started';

// If day is already open or closed, redirect to dashboard
if ($day_status === 'open' || $day_status === 'closed') {
    header('Location: dashboard.php?msg=day_already_' . $day_status);
    exit;
}

// ---------------------------------------------------------------
// Get all active lines
// ---------------------------------------------------------------
try {
    $linesQuery = $pdo->query("SELECT * FROM `mobile_lines` WHERE `status` = 'active' ORDER BY `line_name`");
    $lines = $linesQuery->fetchAll();
} catch (PDOException $e) {
    $lines = [];
}

// ---------------------------------------------------------------
// Handle: Open Day
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'open_day') {
    
    $openingBalances = $_POST['balance'] ?? [];
    $openingCash = (float) str_replace(',', '', $_POST['opening_cash'] ?? 0);
    
    // Validate
    $hasError = false;
    foreach ($lines as $line) {
        $lineId = $line['id'];
        $till = (float) str_replace(',', '', $openingBalances[$lineId]['till'] ?? 0);
        $lipa = (float) str_replace(',', '', $openingBalances[$lineId]['lipa'] ?? 0);
        
        if ($till < 0 || $lipa < 0) {
            $error = 'Tafadhali ingiza values sahihi (haziwezi kuwa negative).';
            $hasError = true;
            break;
        }
    }
    
    if ($openingCash < 0) {
        $error = 'Opening cash haiwezi kuwa negative.';
        $hasError = true;
    }
    
    if (!$hasError) {
        try {
            $pdo->beginTransaction();
            
            // Create daily closing record
            $stmt = $pdo->prepare("
                INSERT INTO `daily_closing` (date, opened_at, status) 
                VALUES (?, NOW(), 'open')
            ");
            $stmt->execute([$today]);
            
            // Insert opening balances for each line
            foreach ($lines as $line) {
                $lineId = $line['id'];
                $till = (float) str_replace(',', '', $openingBalances[$lineId]['till'] ?? 0);
                $lipa = (float) str_replace(',', '', $openingBalances[$lineId]['lipa'] ?? 0);
                
                $stmt = $pdo->prepare("
                    INSERT INTO `daily_balances` (date, line_id, opening_till, opening_lipa, closing_till, closing_lipa) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$today, $lineId, $till, $lipa, $till, $lipa]);
            }
            
            // Insert opening cash
            $stmt = $pdo->prepare("INSERT INTO `daily_cash` (date, opening_cash) VALUES (?, ?)");
            $stmt->execute([$today, $openingCash]);
            
            // Calculate totals
            $totalTill = 0;
            $totalLipa = 0;
            foreach ($lines as $line) {
                $lineId = $line['id'];
                $totalTill += (float) str_replace(',', '', $openingBalances[$lineId]['till'] ?? 0);
                $totalLipa += (float) str_replace(',', '', $openingBalances[$lineId]['lipa'] ?? 0);
            }
            $totalFloat = $totalTill + $totalLipa;
            $totalMoney = $totalFloat + $openingCash;
            
            // Update daily closing with totals
            $stmt = $pdo->prepare("
                UPDATE `daily_closing` 
                SET total_till = ?, total_lipa = ?, total_float = ?, total_cash = ?, total_money = ? 
                WHERE date = ?
            ");
            $stmt->execute([$totalTill, $totalLipa, $totalFloat, $openingCash, $totalMoney, $today]);
            
            $pdo->commit();
            
            $success = '✅ Siku imefunguliwa kwa mafanikio!';
            
            // Redirect to dashboard after 2 seconds
            header('Refresh: 2; URL=dashboard.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Imeshindikana kufungua siku: ' . $e->getMessage();
        }
    }
}

// Calculate totals for display (if form submitted with error)
$previewTotalTill = 0;
$previewTotalLipa = 0;
$previewTotalFloat = 0;
$previewTotalMoney = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['balance'])) {
    $openingBalances = $_POST['balance'] ?? [];
    $openingCash = (float) str_replace(',', '', $_POST['opening_cash'] ?? 0);
    
    foreach ($lines as $line) {
        $lineId = $line['id'];
        $previewTotalTill += (float) str_replace(',', '', $openingBalances[$lineId]['till'] ?? 0);
        $previewTotalLipa += (float) str_replace(',', '', $openingBalances[$lineId]['lipa'] ?? 0);
    }
    $previewTotalFloat = $previewTotalTill + $previewTotalLipa;
    $previewTotalMoney = $previewTotalFloat + $openingCash;
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Open Day — Money Point</title>

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
.status-pill.idle{ background:rgba(140,160,179,0.12); color:var(--slate); border:1px solid rgba(140,160,179,0.3); }
.status-pill .dot{ width:6px; height:6px; border-radius:50%; background:currentColor; }
.content{ padding:28px 32px 60px; max-width:900px; margin:0 auto; }
.mp-card{ background:linear-gradient(180deg, var(--panel), var(--panel-2)); border:1px solid var(--line); border-radius:16px; padding:24px 28px; }
.section-title{ font-family:'Space Grotesk', sans-serif; font-size:17px; font-weight:600; color:var(--paper); display:flex; align-items:center; gap:9px; margin-bottom:18px; }
.section-title .eyebrow{ font-family:'JetBrains Mono', monospace; font-size:10.5px; color:var(--slate); font-weight:500; letter-spacing:.06em; text-transform:uppercase; margin-left:auto; }
.mp-form-control{ background:var(--panel-2); border:1px solid rgba(140,160,179,0.22); color:var(--paper); border-radius:9px; padding:9px 11px; font-size:14px; width:100%; }
.mp-form-control:focus{ background:var(--panel-2); color:var(--paper); border-color:var(--gold); box-shadow:0 0 0 3px rgba(231,169,76,0.14); outline:none; }
.mp-form-control::placeholder{ color:#5C7188; }
.mp-form-label{ font-size:11px; font-weight:500; color:var(--slate); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; display:block; }
.btn-mp-gold{ background:linear-gradient(180deg, var(--gold-soft), var(--gold)); border:none; color:#12233A; font-weight:600; border-radius:10px; padding:14px 28px; font-family:'Space Grotesk', sans-serif; font-size:15px; box-shadow:0 10px 22px -10px rgba(231,169,76,0.5); }
.btn-mp-gold:hover{ color:#12233A; filter:brightness(1.05); }
.btn-mp-outline{ background:transparent; border:1px solid var(--line); color:var(--paper); border-radius:10px; padding:10px 18px; font-size:14px; font-weight:500; }
.btn-mp-outline:hover{ background:rgba(231,169,76,0.08); color:var(--paper); border-color:var(--gold); }
.alert-mp{ border-radius:10px; padding:12px 15px; font-size:14px; margin-bottom:18px; }
.alert-mp.success{ background:rgba(51,184,137,0.12); border:1px solid rgba(51,184,137,0.4); color:#8FE3C3; }
.alert-mp.error{ background:rgba(225,96,79,0.12); border:1px solid rgba(225,96,79,0.4); color:#F3B7AD; }
.summary-line{ display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid rgba(140,160,179,0.08); font-size:14px; }
.summary-line:last-child{ border-bottom:none; }
.summary-line .l{ color:var(--slate); }
.summary-line .v{ font-weight:600; font-family:'JetBrains Mono', monospace; }
.summary-line.grand{ margin-top:4px; padding-top:14px; border-top:1px solid var(--line); font-size:16px; }
.summary-line.grand .v{ color:var(--gold-soft); font-size:20px; }
.table-mp{ --bs-table-bg:transparent; color:var(--paper); margin-bottom:0; }
.table-mp thead th{ font-family:'JetBrains Mono', monospace; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--slate); border-bottom:1px solid var(--line); font-weight:500; padding-bottom:12px; }
.table-mp td{ border-bottom:1px solid rgba(140,160,179,0.08); padding:10px 6px; font-size:14px; vertical-align:middle; }
.table-mp tr:last-child td{ border-bottom:none; }
.network-dot{ width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
@media (max-width: 768px){
  .mp-sidebar{ position:fixed; left:-260px; z-index:40; transition:left .2s ease; box-shadow:0 0 40px rgba(0,0,0,.5); }
  .mp-sidebar.open{ left:0; }
  .content{ padding:20px 16px 50px; }
  .mp-topbar{ padding:16px 18px; }
  .mp-card{ padding:18px 16px; }
}
</style>
</head>
<body>
<div class="mp-shell">

  <aside class="mp-sidebar">
    <div class="brand"><span class="dot"></span> MONEY&nbsp;POINT</div>
    <nav class="mp-nav">
      <a href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
      <a href="lines.php"><i class="bi bi-phone"></i> Lines / Float</a>
      <div class="divider"></div>
      <a href="open-day.php" class="active"><i class="bi bi-play-circle"></i> Open Day</a>
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
        <h1>🚀 Open Day</h1>
      </div>
      <div class="meta">
        <span><?= $currentDate ?></span>
        <span>Welcome, <strong><?= htmlspecialchars($current_user) ?></strong></span>
        <span class="status-pill idle">
          <span class="dot"></span> DAY NOT STARTED
        </span>
      </div>
    </header>

    <div class="content">

      <?php if ($success): ?>
        <div class="alert-mp success"><i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <div class="text-center mt-3">
          <a href="dashboard.php" class="btn btn-mp-gold">Go to Dashboard</a>
        </div>
      <?php else: ?>

      <?php if ($error): ?>
        <div class="alert-mp error"><i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (empty($lines)): ?>
        <div class="mp-card text-center py-5">
          <i class="bi bi-exclamation-circle" style="font-size:48px;color:var(--slate);"></i>
          <h4 class="mt-3">Hakuna Lines Zilizosajiliwa</h4>
          <p class="text-secondary">Tafadhali ongeza lines kwanza kwenye page ya <a href="lines.php" style="color:var(--gold-soft);">Lines / Float</a></p>
          <a href="lines.php" class="btn btn-mp-outline mt-2"><i class="bi bi-arrow-left me-1"></i> Nenda kwenye Lines</a>
        </div>
      <?php else: ?>

      <form method="POST" id="openDayForm">
        <input type="hidden" name="action" value="open_day">

        <div class="mp-card mb-4">
          <div class="section-title">
            <i class="bi bi-phone" style="color:var(--gold-soft);"></i> Opening Balances
            <span class="eyebrow"><?= count($lines) ?> Lines</span>
          </div>

          <div class="table-responsive">
            <table class="table table-mp">
              <thead>
                <tr>
                  <th>Line</th>
                  <th>Network</th>
                  <th style="width:35%;">Till (TSh)</th>
                  <th style="width:35%;">Lipa Namba (TSh)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lines as $line): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($line['line_name']) ?></strong></td>
                  <td>
                    <span class="network-dot" style="background:<?= $line['network'] == 'Vodacom' ? '#33B889' : ($line['network'] == 'Airtel' ? '#E1604F' : ($line['network'] == 'Tigo' ? '#F4C978' : '#5B8DEF')) ?>"></span>
                    <?= htmlspecialchars($line['network']) ?>
                  </td>
                  <td>
                    <input type="text" inputmode="numeric" class="mp-form-control till-input" 
                           name="balance[<?= $line['id'] ?>][till]" 
                           placeholder="0" 
                           value="<?= isset($_POST['balance'][$line['id']]['till']) ? htmlspecialchars($_POST['balance'][$line['id']]['till']) : '' ?>"
                           required>
                  </td>
                  <td>
                    <input type="text" inputmode="numeric" class="mp-form-control lipa-input" 
                           name="balance[<?= $line['id'] ?>][lipa]" 
                           placeholder="0" 
                           value="<?= isset($_POST['balance'][$line['id']]['lipa']) ? htmlspecialchars($_POST['balance'][$line['id']]['lipa']) : '' ?>"
                           required>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Opening Cash -->
        <div class="mp-card mb-4">
          <div class="section-title">
            <i class="bi bi-cash" style="color:var(--gold-soft);"></i> Opening Cash
          </div>
          <div class="row">
            <div class="col-md-6">
              <label class="mp-form-label">Cash (TSh)</label>
              <input type="text" inputmode="numeric" class="mp-form-control" id="openingCash" 
                     name="opening_cash" placeholder="0" 
                     value="<?= isset($_POST['opening_cash']) ? htmlspecialchars($_POST['opening_cash']) : '' ?>"
                     required>
            </div>
          </div>
        </div>

        <!-- Preview Summary -->
        <div class="mp-card mb-4" id="previewCard">
          <div class="section-title">
            <i class="bi bi-eye" style="color:var(--gold-soft);"></i> Preview Summary
          </div>
          <div class="summary-line"><span class="l">Total Till</span><span class="v" id="previewTill">TSh 0</span></div>
          <div class="summary-line"><span class="l">Total Lipa Namba</span><span class="v" id="previewLipa">TSh 0</span></div>
          <div class="summary-line"><span class="l">Total Float</span><span class="v" id="previewFloat">TSh 0</span></div>
          <div class="summary-line grand"><span class="l">Total Money</span><span class="v" id="previewMoney">TSh 0</span></div>
        </div>

        <!-- Submit -->
        <div class="d-flex gap-3 flex-wrap">
          <button type="submit" class="btn btn-mp-gold" id="openDayBtn">
            <i class="bi bi-play-fill me-1"></i> OPEN DAY
          </button>
          <a href="dashboard.php" class="btn btn-mp-outline">Cancel</a>
        </div>

        <p class="text-secondary mt-3" style="font-size:13px;">
          <i class="bi bi-info-circle me-1"></i> 
          Once you open the day, you can start recording transactions.
        </p>
      </form>

      <?php endif; ?>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // Sidebar toggle
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.mp-sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target) && !toggle.contains(e.target)) sidebar.classList.remove('open');
    });
  }

  // Format number with commas
  function formatNumber(n) {
    return n.toLocaleString('en-US');
  }

  // Parse number from string
  function parseNumber(str) {
    return parseFloat(str.replace(/,/g, '')) || 0;
  }

  // Update preview
  function updatePreview() {
    let totalTill = 0;
    let totalLipa = 0;
    
    document.querySelectorAll('.till-input').forEach(input => {
      totalTill += parseNumber(input.value);
    });
    
    document.querySelectorAll('.lipa-input').forEach(input => {
      totalLipa += parseNumber(input.value);
    });
    
    const totalFloat = totalTill + totalLipa;
    const openingCash = parseNumber(document.getElementById('openingCash').value);
    const totalMoney = totalFloat + openingCash;
    
    document.getElementById('previewTill').textContent = 'TSh ' + formatNumber(totalTill);
    document.getElementById('previewLipa').textContent = 'TSh ' + formatNumber(totalLipa);
    document.getElementById('previewFloat').textContent = 'TSh ' + formatNumber(totalFloat);
    document.getElementById('previewMoney').textContent = 'TSh ' + formatNumber(totalMoney);
  }

  // Auto-format inputs
  function autoFormat(input) {
    let value = input.value.replace(/,/g, '');
    if (value === '') return;
    let num = parseFloat(value);
    if (!isNaN(num)) {
      input.value = formatNumber(num);
    }
  }

  // Add event listeners
  document.querySelectorAll('.till-input, .lipa-input').forEach(input => {
    input.addEventListener('input', function() {
      updatePreview();
    });
    input.addEventListener('blur', function() {
      autoFormat(this);
      updatePreview();
    });
  });

  document.getElementById('openingCash').addEventListener('input', function() {
    updatePreview();
  });

  document.getElementById('openingCash').addEventListener('blur', function() {
    autoFormat(this);
    updatePreview();
  });

  // Initial preview
  updatePreview();
});
</script>
</body>
</html>