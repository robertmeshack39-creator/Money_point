<?php
/**
 * money/close-day.php — Close Day Page
 * Close the current business day by entering closing balances
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
// Check if day is open
// ---------------------------------------------------------------
try {
    $statusQuery = $pdo->prepare("SELECT * FROM `daily_closing` WHERE `date` = ?");
    $statusQuery->execute([$today]);
    $dayRow = $statusQuery->fetch();
} catch (PDOException $e) {
    $dayRow = null;
}

$day_status = $dayRow['status'] ?? 'not_started';

// If day is not open, redirect to dashboard
if ($day_status !== 'open') {
    header('Location: dashboard.php?msg=day_not_open');
    exit;
}

// ---------------------------------------------------------------
// Get opening balances for display
// ---------------------------------------------------------------
try {
    $openingQuery = $pdo->prepare("
        SELECT db.*, ml.line_name, ml.network 
        FROM `daily_balances` db 
        JOIN `mobile_lines` ml ON db.line_id = ml.id 
        WHERE db.date = ?
    ");
    $openingQuery->execute([$today]);
    $openingBalances = $openingQuery->fetchAll();
} catch (PDOException $e) {
    $openingBalances = [];
}

// ---------------------------------------------------------------
// Get opening cash
// ---------------------------------------------------------------
try {
    $cashQuery = $pdo->prepare("SELECT * FROM `daily_cash` WHERE `date` = ?");
    $cashQuery->execute([$today]);
    $cashData = $cashQuery->fetch();
} catch (PDOException $e) {
    $cashData = null;
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
// Handle: Close Day
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_day') {
    
    $closingBalances = $_POST['balance'] ?? [];
    $closingCash = (float) str_replace(',', '', $_POST['closing_cash'] ?? 0);
    
    // Validate
    $hasError = false;
    foreach ($lines as $line) {
        $lineId = $line['id'];
        $till = (float) str_replace(',', '', $closingBalances[$lineId]['till'] ?? 0);
        $lipa = (float) str_replace(',', '', $closingBalances[$lineId]['lipa'] ?? 0);
        
        if ($till < 0 || $lipa < 0) {
            $error = 'Tafadhali ingiza values sahihi (haziwezi kuwa negative).';
            $hasError = true;
            break;
        }
    }
    
    if ($closingCash < 0) {
        $error = 'Closing cash haiwezi kuwa negative.';
        $hasError = true;
    }
    
    if (!$hasError) {
        try {
            $pdo->beginTransaction();
            
            // Update closing balances for each line
            foreach ($lines as $line) {
                $lineId = $line['id'];
                $till = (float) str_replace(',', '', $closingBalances[$lineId]['till'] ?? 0);
                $lipa = (float) str_replace(',', '', $closingBalances[$lineId]['lipa'] ?? 0);
                
                $stmt = $pdo->prepare("
                    UPDATE `daily_balances` 
                    SET closing_till = ?, closing_lipa = ? 
                    WHERE date = ? AND line_id = ?
                ");
                $stmt->execute([$till, $lipa, $today, $lineId]);
            }
            
            // Update closing cash
            $stmt = $pdo->prepare("UPDATE `daily_cash` SET closing_cash = ? WHERE date = ?");
            $stmt->execute([$closingCash, $today]);
            
            // Calculate totals
            $totalTill = 0;
            $totalLipa = 0;
            foreach ($lines as $line) {
                $lineId = $line['id'];
                $totalTill += (float) str_replace(',', '', $closingBalances[$lineId]['till'] ?? 0);
                $totalLipa += (float) str_replace(',', '', $closingBalances[$lineId]['lipa'] ?? 0);
            }
            $totalFloat = $totalTill + $totalLipa;
            $totalMoney = $totalFloat + $closingCash;
            
            // Update daily closing
            $stmt = $pdo->prepare("
                UPDATE `daily_closing` 
                SET total_till = ?, total_lipa = ?, total_float = ?, 
                    total_cash = ?, total_money = ?, 
                    closed_at = NOW(), status = 'closed', closed_by = ? 
                WHERE date = ?
            ");
            $stmt->execute([$totalTill, $totalLipa, $totalFloat, $closingCash, $totalMoney, $_SESSION['user_id'], $today]);
            
            $pdo->commit();
            
            $success = '✅ Siku imefungwa kwa mafanikio!';
            
            // Redirect to dashboard after 2 seconds
            header('Refresh: 2; URL=dashboard.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Imeshindikana kufunga siku: ' . $e->getMessage();
        }
    }
}

// Calculate opening totals for display
$openingTotalTill = 0;
$openingTotalLipa = 0;
foreach ($openingBalances as $balance) {
    $openingTotalTill += floatval($balance['opening_till'] ?? 0);
    $openingTotalLipa += floatval($balance['opening_lipa'] ?? 0);
}
$openingTotalFloat = $openingTotalTill + $openingTotalLipa;
$openingTotalMoney = $openingTotalFloat + floatval($cashData['opening_cash'] ?? 0);

// Calculate preview totals if form submitted
$previewTotalTill = 0;
$previewTotalLipa = 0;
$previewTotalFloat = 0;
$previewTotalMoney = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['balance'])) {
    $closingBalances = $_POST['balance'] ?? [];
    $closingCash = (float) str_replace(',', '', $_POST['closing_cash'] ?? 0);
    
    foreach ($lines as $line) {
        $lineId = $line['id'];
        $previewTotalTill += (float) str_replace(',', '', $closingBalances[$lineId]['till'] ?? 0);
        $previewTotalLipa += (float) str_replace(',', '', $closingBalances[$lineId]['lipa'] ?? 0);
    }
    $previewTotalFloat = $previewTotalTill + $previewTotalLipa;
    $previewTotalMoney = $previewTotalFloat + $closingCash;
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Close Day — Money Point</title>

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
.btn-mp-danger{ background:linear-gradient(180deg, #E1604F, #C0392B); border:none; color:white; font-weight:600; border-radius:10px; padding:14px 28px; font-family:'Space Grotesk', sans-serif; font-size:15px; box-shadow:0 10px 22px -10px rgba(225,96,79,0.5); }
.btn-mp-danger:hover{ color:white; filter:brightness(1.05); }
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
.badge-opening{ background:rgba(140,160,179,0.15); color:var(--slate); padding:4px 10px; border-radius:6px; font-size:12px; }
.text-muted-light{ color:rgba(255,255,255,0.5); }
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
      <a href="open-day.php"><i class="bi bi-play-circle"></i> Open Day</a>
      <a href="close-day.php" class="active"><i class="bi bi-lock"></i> Close Day</a>
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
        <h1>🔒 Close Day</h1>
      </div>
      <div class="meta">
        <span><?= $currentDate ?></span>
        <span>Welcome, <strong><?= htmlspecialchars($current_user) ?></strong></span>
        <span class="status-pill open">
          <span class="dot"></span> DAY OPEN
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

      <!-- Opening Summary -->
      <div class="mp-card mb-4" style="border-color:rgba(140,160,179,0.15);">
        <div class="section-title">
          <i class="bi bi-arrow-up-circle" style="color:var(--mint);"></i> Opening Summary
          <span class="eyebrow">Siku ya leo</span>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="text-secondary" style="font-size:13px;">Opening Till</div>
            <div class="fw-bold" style="font-size:18px;color:var(--paper);">TSh <?= number_format($openingTotalTill) ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary" style="font-size:13px;">Opening Lipa</div>
            <div class="fw-bold" style="font-size:18px;color:var(--paper);">TSh <?= number_format($openingTotalLipa) ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary" style="font-size:13px;">Opening Cash</div>
            <div class="fw-bold" style="font-size:18px;color:var(--paper);">TSh <?= number_format($cashData['opening_cash'] ?? 0) ?></div>
          </div>
        </div>
      </div>

      <form method="POST" id="closeDayForm">
        <input type="hidden" name="action" value="close_day">

        <div class="mp-card mb-4">
          <div class="section-title">
            <i class="bi bi-phone" style="color:var(--gold-soft);"></i> Closing Balances
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
                <?php foreach ($lines as $line): 
                  // Get current balance for this line
                  $currentBalance = null;
                  foreach ($openingBalances as $ob) {
                    if ($ob['line_id'] == $line['id']) {
                      $currentBalance = $ob;
                      break;
                    }
                  }
                  $currentTill = $currentBalance ? floatval($currentBalance['opening_till'] ?? 0) : 0;
                  $currentLipa = $currentBalance ? floatval($currentBalance['opening_lipa'] ?? 0) : 0;
                ?>
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
                           value="<?= isset($_POST['balance'][$line['id']]['till']) ? htmlspecialchars($_POST['balance'][$line['id']]['till']) : number_format($currentTill) ?>"
                           required>
                  </td>
                  <td>
                    <input type="text" inputmode="numeric" class="mp-form-control lipa-input" 
                           name="balance[<?= $line['id'] ?>][lipa]" 
                           placeholder="0" 
                           value="<?= isset($_POST['balance'][$line['id']]['lipa']) ? htmlspecialchars($_POST['balance'][$line['id']]['lipa']) : number_format($currentLipa) ?>"
                           required>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Closing Cash -->
        <div class="mp-card mb-4">
          <div class="section-title">
            <i class="bi bi-cash" style="color:var(--gold-soft);"></i> Closing Cash
          </div>
          <div class="row">
            <div class="col-md-6">
              <label class="mp-form-label">Cash (TSh)</label>
              <input type="text" inputmode="numeric" class="mp-form-control" id="closingCash" 
                     name="closing_cash" placeholder="0" 
                     value="<?= isset($_POST['closing_cash']) ? htmlspecialchars($_POST['closing_cash']) : number_format($cashData['opening_cash'] ?? 0) ?>"
                     required>
            </div>
          </div>
        </div>

        <!-- Preview Summary -->
        <div class="mp-card mb-4" style="border-color:rgba(231,169,76,0.2);">
          <div class="section-title">
            <i class="bi bi-eye" style="color:var(--gold-soft);"></i> Closing Summary
          </div>
          <div class="summary-line"><span class="l">Total Till</span><span class="v" id="previewTill">TSh <?= number_format($openingTotalTill) ?></span></div>
          <div class="summary-line"><span class="l">Total Lipa Namba</span><span class="v" id="previewLipa">TSh <?= number_format($openingTotalLipa) ?></span></div>
          <div class="summary-line"><span class="l">Total Float</span><span class="v" id="previewFloat">TSh <?= number_format($openingTotalFloat) ?></span></div>
          <div class="summary-line"><span class="l">Cash</span><span class="v" id="previewCash">TSh <?= number_format($cashData['opening_cash'] ?? 0) ?></span></div>
          <div class="summary-line grand"><span class="l">TOTAL MONEY</span><span class="v" id="previewMoney">TSh <?= number_format($openingTotalMoney) ?></span></div>
        </div>

        <!-- Submit -->
        <div class="d-flex gap-3 flex-wrap">
          <button type="submit" class="btn btn-mp-danger" id="closeDayBtn">
            <i class="bi bi-lock-fill me-1"></i> CONFIRM CLOSING
          </button>
          <a href="dashboard.php" class="btn btn-mp-outline">Cancel</a>
        </div>

        <p class="text-secondary mt-3" style="font-size:13px;">
          <i class="bi bi-exclamation-triangle me-1" style="color:var(--red);"></i> 
          <strong style="color:var(--red);">Warning:</strong> Once closed, you cannot edit these values again.
        </p>
      </form>

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
    const closingCash = parseNumber(document.getElementById('closingCash').value);
    const totalMoney = totalFloat + closingCash;
    
    document.getElementById('previewTill').textContent = 'TSh ' + formatNumber(totalTill);
    document.getElementById('previewLipa').textContent = 'TSh ' + formatNumber(totalLipa);
    document.getElementById('previewFloat').textContent = 'TSh ' + formatNumber(totalFloat);
    document.getElementById('previewCash').textContent = 'TSh ' + formatNumber(closingCash);
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

  document.getElementById('closingCash').addEventListener('input', function() {
    updatePreview();
  });

  document.getElementById('closingCash').addEventListener('blur', function() {
    autoFormat(this);
    updatePreview();
  });

  // Initial preview
  updatePreview();
});
</script>
</body>
</html>