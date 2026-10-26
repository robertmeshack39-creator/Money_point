<?php
/**
 * money/reports.php — Reports Page
 * Generate daily, weekly, monthly, float and cash reports
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../connection.php';

$current_user = $_SESSION['username'] ?? 'User';
$today = date('Y-m-d');

// ---------------------------------------------------------------
// Report type & date range
// ---------------------------------------------------------------
$report_type = $_GET['report_type'] ?? 'daily';
$startDate   = $_GET['start_date'] ?? date('Y-m-01');
$endDate     = $_GET['end_date'] ?? $today;
$selectedDate = $_GET['selected_date'] ?? $today;

// ---------------------------------------------------------------
// Build query based on report type
// ---------------------------------------------------------------
$sql = "SELECT dc.*, u.username as closed_by_name 
        FROM `daily_closing` dc 
        LEFT JOIN `users` u ON dc.closed_by = u.id 
        WHERE 1=1";
$params = [];

if ($report_type === 'daily') {
    $sql .= " AND dc.date = ?";
    $params[] = $selectedDate;
} elseif ($report_type === 'weekly' || $report_type === 'monthly' || $report_type === 'float' || $report_type === 'cash') {
    $sql .= " AND dc.date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$sql .= " ORDER BY dc.date ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    $records = [];
}

// ---------------------------------------------------------------
// Calculate totals
// ---------------------------------------------------------------
$totalTill = 0;
$totalLipa = 0;
$totalFloat = 0;
$totalCash = 0;
$totalMoney = 0;
$totalDays = count($records);

foreach ($records as $r) {
    $totalTill  += floatval($r['total_till'] ?? 0);
    $totalLipa  += floatval($r['total_lipa'] ?? 0);
    $totalFloat += floatval($r['total_float'] ?? 0);
    $totalCash  += floatval($r['total_cash'] ?? 0);
    $totalMoney += floatval($r['total_money'] ?? 0);
}

$avgFloat = $totalDays > 0 ? $totalFloat / $totalDays : 0;
$avgCash  = $totalDays > 0 ? $totalCash / $totalDays : 0;
$avgMoney = $totalDays > 0 ? $totalMoney / $totalDays : 0;

// ---------------------------------------------------------------
// Get line-wise breakdown for the period
// ---------------------------------------------------------------
try {
    $lineSql = "SELECT ml.line_name, ml.network,
                       SUM(db.opening_till) as sum_opening_till,
                       SUM(db.opening_lipa) as sum_opening_lipa,
                       SUM(db.closing_till) as sum_closing_till,
                       SUM(db.closing_lipa) as sum_closing_lipa
                FROM `daily_balances` db 
                JOIN `mobile_lines` ml ON db.line_id = ml.id 
                WHERE db.date BETWEEN ? AND ?
                GROUP BY ml.id, ml.line_name, ml.network
                ORDER BY ml.line_name";
    $lineStmt = $pdo->prepare($lineSql);
    $lineStmt->execute([$startDate, $endDate]);
    $lineBreakdown = $lineStmt->fetchAll();
} catch (PDOException $e) {
    $lineBreakdown = [];
}

// ---------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------
function formatMoney($n) {
    return 'TSh ' . number_format($n, 0);
}

function formatShort($n) {
    $n = floatval($n);
    if ($n >= 1000000) return number_format($n / 1000000, 2) . 'M';
    if ($n >= 1000) return number_format($n / 1000, 1) . 'K';
    return number_format($n, 0);
}

function networkColor($network) {
    $colors = [
        'vodacom' => '#33B889', 'mpesa' => '#33B889',
        'airtel'  => '#E1604F',
        'tigo'    => '#F4C978', 'yas' => '#F4C978',
        'halotel' => '#5B8DEF', 'halopesa' => '#5B8DEF',
    ];
    $key = strtolower(str_replace(' ', '', $network));
    return $colors[$key] ?? '#8CA0B3';
}

$reportTitles = [
    'daily'   => 'Daily Report',
    'weekly'  => 'Weekly Report',
    'monthly' => 'Monthly Report',
    'float'   => 'Float Report',
    'cash'    => 'Cash Report',
];
$reportTitle = $reportTitles[$report_type] ?? 'Report';
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $reportTitle ?> — Money Point</title>

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
.content{ padding:28px 32px 60px; }
.mp-card{ background:linear-gradient(180deg, var(--panel), var(--panel-2)); border:1px solid var(--line); border-radius:16px; padding:20px 22px; }
.section-title{ font-family:'Space Grotesk', sans-serif; font-size:16px; font-weight:600; color:var(--paper); display:flex; align-items:center; gap:9px; margin-bottom:14px; }
.section-title .eyebrow{ font-family:'JetBrains Mono', monospace; font-size:10.5px; color:var(--slate); font-weight:500; letter-spacing:.06em; text-transform:uppercase; margin-left:auto; }
.mp-form-control, .mp-form-select{ background:var(--panel-2); border:1px solid rgba(140,160,179,0.22); color:var(--paper); border-radius:9px; padding:9px 11px; font-size:14px; width:100%; }
.mp-form-control:focus, .mp-form-select:focus{ background:var(--panel-2); color:var(--paper); border-color:var(--gold); box-shadow:0 0 0 3px rgba(231,169,76,0.14); outline:none; }
.mp-form-control::placeholder{ color:#5C7188; }
.mp-form-select option{ background:var(--panel-2); color:var(--paper); }
.mp-form-label{ font-size:11px; font-weight:500; color:var(--slate); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; display:block; }
.btn-mp-gold{ background:linear-gradient(180deg, var(--gold-soft), var(--gold)); border:none; color:#12233A; font-weight:600; border-radius:10px; padding:10px 20px; font-family:'Space Grotesk', sans-serif; font-size:14px; box-shadow:0 10px 22px -10px rgba(231,169,76,0.5); }
.btn-mp-gold:hover{ color:#12233A; filter:brightness(1.05); }
.btn-mp-outline{ background:transparent; border:1px solid var(--line); color:var(--paper); border-radius:10px; padding:9px 18px; font-size:14px; font-weight:500; }
.btn-mp-outline:hover{ background:rgba(231,169,76,0.08); color:var(--paper); border-color:var(--gold); }
.report-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.report-tabs a{ padding:9px 18px; border-radius:10px; font-size:13.5px; font-weight:500; text-decoration:none; color:var(--slate); border:1px solid var(--line); transition:all .15s ease; }
.report-tabs a:hover{ background:rgba(231,169,76,0.08); color:var(--paper); }
.report-tabs a.active{ background:linear-gradient(180deg, var(--gold-soft), var(--gold)); color:#12233A; border-color:transparent; font-weight:600; }
.stat-box{ background:rgba(255,255,255,0.02); border:1px solid var(--line); border-radius:14px; padding:18px 20px; }
.stat-box .label{ font-size:11.5px; color:var(--slate); text-transform:uppercase; letter-spacing:.05em; font-weight:500; }
.stat-box .value{ font-family:'Space Grotesk', sans-serif; font-size:22px; font-weight:700; color:var(--paper); margin-top:6px; }
.stat-box .value.gold{ color:var(--gold-soft); }
.stat-box .value.mint{ color:var(--mint); }
.stat-box .value.red{ color:var(--red); }
.stat-box .sub{ font-size:11.5px; color:var(--slate); margin-top:4px; }
.table-mp{ --bs-table-bg:transparent; color:var(--paper); margin-bottom:0; }
.table-mp thead th{ font-family:'JetBrains Mono', monospace; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--slate); border-bottom:1px solid var(--line); font-weight:500; padding:12px 10px; }
.table-mp td{ border-bottom:1px solid rgba(140,160,179,0.08); padding:11px 10px; font-size:13.5px; vertical-align:middle; }
.table-mp tbody tr:hover{ background:rgba(231,169,76,0.04); }
.table-mp tfoot td{ font-weight:600; color:var(--gold-soft); border-top:1px solid var(--line); border-bottom:none; padding:14px 10px; font-family:'JetBrains Mono', monospace; }
.network-dot{ width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
.empty-state{ text-align:center; padding:60px 20px; color:var(--slate); }
.empty-state i{ font-size:52px; opacity:0.4; }
@media print {
  .mp-sidebar, .mp-topbar, .report-tabs, .filter-card, .no-print { display:none !important; }
  body { background:white; color:black; }
  .mp-shell { display:block; }
  .content { padding:0; }
  .mp-card { border:1px solid #ddd; background:white; color:black; }
  .stat-box { border:1px solid #ddd; background:white; }
  .stat-box .value, .stat-box .label, .section-title { color:black !important; }
  .table-mp, .table-mp td, .table-mp th { color:black !important; }
}
@media (max-width: 768px){
  .mp-sidebar{ position:fixed; left:-260px; z-index:40; transition:left .2s ease; box-shadow:0 0 40px rgba(0,0,0,.5); }
  .mp-sidebar.open{ left:0; }
  .content{ padding:20px 16px 50px; }
  .mp-topbar{ padding:16px 18px; }
  .mp-card{ padding:16px 14px; }
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
      <a href="close-day.php"><i class="bi bi-lock"></i> Close Day</a>
      <div class="divider"></div>
      <a href="history.php"><i class="bi bi-calendar3"></i> Daily History</a>
      <a href="reports.php" class="active"><i class="bi bi-bar-chart"></i> Reports</a>
      <div class="divider"></div>
      <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
    <div class="sidebar-foot">MARIADB · SESSION-VERIFIED · v1.0</div>
  </aside>

  <main class="mp-main">
    <header class="mp-topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" class="btn btn-mp-outline d-md-none py-2 px-3"><i class="bi bi-list"></i></button>
        <h1>📊 <?= $reportTitle ?></h1>
      </div>
      <div class="meta">
        <span><?= date('l, j F Y') ?></span>
        <span>Welcome, <strong><?= htmlspecialchars($current_user) ?></strong></span>
        <button onclick="window.print()" class="btn btn-mp-outline no-print" style="padding:6px 14px;font-size:13px;">
          <i class="bi bi-printer me-1"></i> Print
        </button>
      </div>
    </header>

    <div class="content">

      <!-- ============ REPORT TYPE TABS ============ -->
      <div class="report-tabs no-print">
        <a href="?report_type=daily&selected_date=<?= $selectedDate ?>" class="<?= $report_type === 'daily' ? 'active' : '' ?>">
          <i class="bi bi-calendar-day me-1"></i> Daily
        </a>
        <a href="?report_type=weekly&start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= $today ?>" class="<?= $report_type === 'weekly' ? 'active' : '' ?>">
          <i class="bi bi-calendar-week me-1"></i> Weekly
        </a>
        <a href="?report_type=monthly&start_date=<?= date('Y-m-01') ?>&end_date=<?= $today ?>" class="<?= $report_type === 'monthly' ? 'active' : '' ?>">
          <i class="bi bi-calendar-month me-1"></i> Monthly
        </a>
        <a href="?report_type=float&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="<?= $report_type === 'float' ? 'active' : '' ?>">
          <i class="bi bi-coins me-1"></i> Float
        </a>
        <a href="?report_type=cash&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="<?= $report_type === 'cash' ? 'active' : '' ?>">
          <i class="bi bi-cash-stack me-1"></i> Cash
        </a>
      </div>

      <!-- ============ FILTER ============ -->
      <div class="mp-card mb-4 no-print">
        <form method="GET" class="row g-3 align-items-end">
          <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">
          
          <?php if ($report_type === 'daily'): ?>
            <div class="col-md-4">
              <label class="mp-form-label">Select Date</label>
              <input type="date" name="selected_date" class="mp-form-control" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>
          <?php else: ?>
            <div class="col-md-3">
              <label class="mp-form-label">From Date</label>
              <input type="date" name="start_date" class="mp-form-control" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-3">
              <label class="mp-form-label">To Date</label>
              <input type="date" name="end_date" class="mp-form-control" value="<?= htmlspecialchars($endDate) ?>">
            </div>
          <?php endif; ?>
          
          <div class="col-md-3">
            <button type="submit" class="btn btn-mp-gold w-100">
              <i class="bi bi-file-earmark-bar-graph me-1"></i> Generate
            </button>
          </div>
        </form>
      </div>

      <!-- ============ REPORT HEADER ============ -->
      <div class="mp-card mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <h4 style="font-family:'Space Grotesk',sans-serif;font-weight:700;margin:0;">
              💰 MONEY POINT
            </h4>
            <div style="color:var(--slate);font-size:13px;margin-top:4px;">
              <?= $reportTitle ?>
            </div>
          </div>
          <div class="text-end">
            <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--slate);">
              Period
            </div>
            <div style="font-weight:600;">
              <?php if ($report_type === 'daily'): ?>
                <?= date('d/m/Y', strtotime($selectedDate)) ?>
              <?php else: ?>
                <?= date('d/m/Y', strtotime($startDate)) ?> — <?= date('d/m/Y', strtotime($endDate)) ?>
              <?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--slate);margin-top:4px;">
              Generated: <?= date('d/m/Y H:i') ?>
            </div>
          </div>
        </div>
      </div>

      <?php if (empty($records)): ?>
        <div class="mp-card">
          <div class="empty-state">
            <i class="bi bi-file-earmark-x"></i>
            <h5 class="mt-3">Hakuna Data</h5>
            <p>Hakuna records kwa kipindi ulichochagua.</p>
          </div>
        </div>
      <?php else: ?>

      <!-- ============ SUMMARY STATS ============ -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="stat-box">
            <div class="label">Total Till</div>
            <div class="value"><?= formatShort($totalTill) ?></div>
            <div class="sub"><?= formatMoney($totalTill) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-box">
            <div class="label">Total Lipa</div>
            <div class="value"><?= formatShort($totalLipa) ?></div>
            <div class="sub"><?= formatMoney($totalLipa) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-box">
            <div class="label">Total Float</div>
            <div class="value mint"><?= formatShort($totalFloat) ?></div>
            <div class="sub"><?= formatMoney($totalFloat) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-box">
            <div class="label">Total Cash</div>
            <div class="value"><?= formatShort($totalCash) ?></div>
            <div class="sub"><?= formatMoney($totalCash) ?></div>
          </div>
        </div>
      </div>

      <!-- ============ GRAND TOTAL ============ -->
      <div class="mp-card mb-4" style="background:linear-gradient(135deg,#1e293b,#0f172a);border-color:rgba(231,169,76,0.3);">
        <div class="row align-items-center">
          <div class="col-md-8">
            <div style="font-size:12px;color:var(--slate);text-transform:uppercase;letter-spacing:.06em;">
              💰 TOTAL MONEY
            </div>
            <div style="font-family:'Space Grotesk',sans-serif;font-size:38px;font-weight:700;color:var(--gold-soft);margin-top:6px;">
              <?= formatMoney($totalMoney) ?>
            </div>
            <div style="font-size:13px;color:var(--slate);margin-top:4px;">
              Float + Cash = <?= formatShort($totalFloat) ?> + <?= formatShort($totalCash) ?>
            </div>
          </div>
          <div class="col-md-4 text-md-end">
            <div style="font-size:12px;color:var(--slate);">Days</div>
            <div style="font-family:'Space Grotesk',sans-serif;font-size:32px;font-weight:700;">
              <?= $totalDays ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ AVERAGES ============ -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="stat-box">
            <div class="label">Average Daily Float</div>
            <div class="value mint"><?= formatShort($avgFloat) ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-box">
            <div class="label">Average Daily Cash</div>
            <div class="value"><?= formatShort($avgCash) ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-box">
            <div class="label">Average Daily Money</div>
            <div class="value gold"><?= formatShort($avgMoney) ?></div>
          </div>
        </div>
      </div>

      <!-- ============ LINE BREAKDOWN ============ -->
      <?php if (!empty($lineBreakdown)): ?>
      <div class="mp-card mb-4">
        <div class="section-title">
          <i class="bi bi-phone" style="color:var(--gold-soft);"></i> Line Breakdown
          <span class="eyebrow"><?= count($lineBreakdown) ?> Lines</span>
        </div>
        <div class="table-responsive">
          <table class="table table-mp">
            <thead>
              <tr>
                <th>Line</th>
                <th>Network</th>
                <th class="text-end">Opening Till</th>
                <th class="text-end">Opening Lipa</th>
                <th class="text-end">Closing Till</th>
                <th class="text-end">Closing Lipa</th>
                <th class="text-end">Total Float</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $lineTotalTill = 0;
              $lineTotalLipa = 0;
              foreach ($lineBreakdown as $lb): 
                $lbTill = floatval($lb['sum_closing_till'] ?? 0);
                $lbLipa = floatval($lb['sum_closing_lipa'] ?? 0);
                $lineTotalTill += $lbTill;
                $lineTotalLipa += $lbLipa;
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($lb['line_name']) ?></strong></td>
                <td>
                  <span class="network-dot" style="background:<?= networkColor($lb['network']) ?>"></span>
                  <?= htmlspecialchars($lb['network']) ?>
                </td>
                <td class="text-end"><?= formatMoney($lb['sum_opening_till'] ?? 0) ?></td>
                <td class="text-end"><?= formatMoney($lb['sum_opening_lipa'] ?? 0) ?></td>
                <td class="text-end"><?= formatMoney($lbTill) ?></td>
                <td class="text-end"><?= formatMoney($lbLipa) ?></td>
                <td class="text-end" style="color:var(--mint);font-weight:600;"><?= formatMoney($lbTill + $lbLipa) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="4" class="text-end">TOTAL</td>
                <td class="text-end"><?= formatMoney($lineTotalTill) ?></td>
                <td class="text-end"><?= formatMoney($lineTotalLipa) ?></td>
                <td class="text-end"><?= formatMoney($lineTotalTill + $lineTotalLipa) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- ============ DAILY BREAKDOWN TABLE ============ -->
      <div class="mp-card mb-4">
        <div class="section-title">
          <i class="bi bi-table" style="color:var(--gold-soft);"></i> Daily Breakdown
          <span class="eyebrow"><?= $totalDays ?> Days</span>
        </div>
        <div class="table-responsive">
          <table class="table table-mp">
            <thead>
              <tr>
                <th>#</th>
                <th>Date</th>
                <th>Status</th>
                <th class="text-end">Till</th>
                <th class="text-end">Lipa</th>
                <th class="text-end">Float</th>
                <th class="text-end">Cash</th>
                <th class="text-end">Total Money</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; foreach ($records as $r): ?>
              <tr>
                <td style="color:var(--slate);"><?= $i++ ?></td>
                <td><strong><?= date('d/m/Y', strtotime($r['date'])) ?></strong></td>
                <td>
                  <?php if ($r['status'] === 'open'): ?>
                    <span style="color:var(--mint);font-size:11.5px;font-weight:600;">● OPEN</span>
                  <?php else: ?>
                    <span style="color:var(--red);font-size:11.5px;font-weight:600;">● CLOSED</span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= formatMoney($r['total_till'] ?? 0) ?></td>
                <td class="text-end"><?= formatMoney($r['total_lipa'] ?? 0) ?></td>
                <td class="text-end" style="color:var(--mint);"><?= formatMoney($r['total_float'] ?? 0) ?></td>
                <td class="text-end"><?= formatMoney($r['total_cash'] ?? 0) ?></td>
                <td class="text-end" style="color:var(--gold-soft);font-weight:600;"><?= formatMoney($r['total_money'] ?? 0) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-end">TOTAL</td>
                <td class="text-end"><?= formatMoney($totalTill) ?></td>
                <td class="text-end"><?= formatMoney($totalLipa) ?></td>
                <td class="text-end"><?= formatMoney($totalFloat) ?></td>
                <td class="text-end"><?= formatMoney($totalCash) ?></td>
                <td class="text-end"><?= formatMoney($totalMoney) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <?php endif; ?>

      <!-- ============ FOOTER ============ -->
      <div class="mp-card text-center" style="font-size:12px;color:var(--slate);">
        <div>MONEY POINT — Mobile Money Float Management System</div>
        <div style="margin-top:4px;">Report generated on <?= date('d/m/Y H:i:s') ?> by <?= htmlspecialchars($current_user) ?></div>
      </div>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.mp-sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target) && !toggle.contains(e.target)) sidebar.classList.remove('open');
    });
  }
});
</script>
</body>
</html>