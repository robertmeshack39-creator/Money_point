<?php
/**
 * money/dashboard.php — Money Point (single-file version)
 * Everything (auth, data, styling, markup, charts) lives in this one file.
 * Place inside money/ alongside your existing ../connection.php, ../login.php, ../logout.php
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

$day_status = 'not_started'; // not_started | open | closed
$mp_total_till  = 0;
$mp_total_lipa  = 0;
$mp_total_float = 0;
$mp_total_money = 0;
$mp_opening_cash = 0;
$mp_cash         = 0;
$mp_lines_raw    = [];

if ($dayRow) {
    $day_status = $dayRow['status'];

    try {
        $balancesQuery = $pdo->prepare("
            SELECT db.*, l.line_name, l.network
            FROM `daily_balances` db
            JOIN `lines` l ON db.line_id = l.id
            WHERE db.date = ?
        ");
        $balancesQuery->execute([$today]);
        $mp_lines_raw = $balancesQuery->fetchAll();
    } catch (PDOException $e) {
        $mp_lines_raw = [];
    }

    try {
        $cashQuery = $pdo->prepare("SELECT * FROM `daily_cash` WHERE `date` = ?");
        $cashQuery->execute([$today]);
        $cashRow = $cashQuery->fetch();
    } catch (PDOException $e) {
        $cashRow = null;
    }

    $mp_opening_cash = floatval($cashRow['opening_cash'] ?? 0);
    $mp_cash         = floatval($cashRow['closing_cash'] ?? $cashRow['opening_cash'] ?? 0);

    foreach ($mp_lines_raw as $b) {
        $mp_total_till += floatval($b['closing_till'] ?? $b['opening_till'] ?? 0);
        $mp_total_lipa += floatval($b['closing_lipa'] ?? $b['opening_lipa'] ?? 0);
    }
    $mp_total_float = $mp_total_till + $mp_total_lipa;
    $mp_total_money = $mp_total_float + $mp_cash;
}

// ---------------------------------------------------------------
// All active lines (so the table always has a row per line)
// ---------------------------------------------------------------
try {
    $linesQuery = $pdo->query("SELECT * FROM `lines` WHERE `status` = 'active' ORDER BY `line_name`");
    $mp_all_lines = $linesQuery->fetchAll();
} catch (PDOException $e) {
    $mp_all_lines = [];
}

$mp_lines = [];
foreach ($mp_all_lines as $line) {
    $match = null;
    foreach ($mp_lines_raw as $b) {
        if ($b['line_id'] == $line['id']) { $match = $b; break; }
    }
    $till = $match ? floatval($match['closing_till'] ?? $match['opening_till'] ?? 0) : 0;
    $lipa = $match ? floatval($match['closing_lipa'] ?? $match['opening_lipa'] ?? 0) : 0;
    $mp_lines[] = [
        'name'    => $line['line_name'],
        'network' => $line['network'],
        'till'    => $till,
        'lipa'    => $lipa,
    ];
}

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

// ---------------------------------------------------------------
// Recent (last) closing + trend
// ---------------------------------------------------------------
try {
    $lastClosingQuery = $pdo->prepare("
        SELECT * FROM `daily_closing`
        WHERE `status` = 'closed'
        ORDER BY `date` DESC
        LIMIT 1
    ");
    $lastClosingQuery->execute();
    $mp_last_closing = $lastClosingQuery->fetch();
} catch (PDOException $e) {
    $mp_last_closing = null;
}

$trend = null;
if ($mp_last_closing && $day_status === 'open') {
    $prevTotal = floatval($mp_last_closing['total_money'] ?? 0);
    if ($prevTotal > 0) {
        $diff = $mp_total_money - $prevTotal;
        $trend = ['diff' => $diff, 'pct' => ($diff / $prevTotal) * 100];
    }
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
<title>Dashboard — Money Point</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

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
.mp-sidebar{
  width:250px; flex:none; background:var(--panel-2);
  border-right:1px solid var(--line);
  display:flex; flex-direction:column;
  position:sticky; top:0; height:100vh;
}
.mp-sidebar .brand{
  display:flex; align-items:center; gap:10px; padding:22px 22px 18px;
  font-family:'Space Grotesk', sans-serif; font-weight:700; font-size:18px;
  border-bottom:1px solid var(--line);
}
.mp-sidebar .brand .dot{ width:9px; height:9px; border-radius:50%; background:var(--gold); box-shadow:0 0 0 4px rgba(231,169,76,0.16); }
.mp-nav{ padding:14px 12px; flex:1; overflow-y:auto; }
.mp-nav a{
  display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:10px;
  color:var(--slate); text-decoration:none; font-size:14.5px; font-weight:500; margin-bottom:4px;
  transition:background .15s ease, color .15s ease;
}
.mp-nav a i{ font-size:16px; width:18px; text-align:center; }
.mp-nav a:hover{ background:rgba(231,169,76,0.08); color:var(--paper); }
.mp-nav a.active{
  background:linear-gradient(90deg, rgba(231,169,76,0.16), rgba(231,169,76,0.02));
  color:var(--gold-soft); border:1px solid var(--line);
}
.mp-nav .divider{ height:1px; background:var(--line); margin:12px 6px; }
.mp-sidebar .sidebar-foot{
  padding:16px 22px; border-top:1px solid var(--line);
  font-family:'JetBrains Mono', monospace; font-size:10.5px; color:var(--slate); letter-spacing:.04em;
}
.mp-main{ flex:1; min-width:0; }
.mp-topbar{
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
  padding:22px 32px; border-bottom:1px solid var(--line);
  background:rgba(11,28,44,0.5); backdrop-filter:blur(6px);
  position:sticky; top:0; z-index:10;
}
.mp-topbar h1{ font-family:'Space Grotesk', sans-serif; font-size:19px; font-weight:600; color:var(--paper); margin:0; }
.mp-topbar .meta{ display:flex; align-items:center; gap:18px; font-size:13.5px; color:var(--slate); flex-wrap:wrap; }
.mp-topbar .meta strong{ color:var(--paper); font-weight:600; }
.mp-topbar #liveClock{ font-family:'JetBrains Mono', monospace; color:var(--gold-soft); }
.role-chip{
  font-family:'JetBrains Mono', monospace; font-size:10px; text-transform:uppercase; letter-spacing:.05em;
  background:rgba(231,169,76,0.12); color:var(--gold-soft); border:1px solid var(--line);
  border-radius:999px; padding:2px 8px; margin-left:6px;
}
.status-pill{
  display:inline-flex; align-items:center; gap:7px; padding:6px 13px; border-radius:999px;
  font-size:12.5px; font-weight:600; font-family:'JetBrains Mono', monospace; letter-spacing:.03em;
}
.status-pill.open{ background:rgba(51,184,137,0.14); color:var(--mint); border:1px solid rgba(51,184,137,0.4); }
.status-pill.closed{ background:rgba(225,96,79,0.14); color:var(--red); border:1px solid rgba(225,96,79,0.4); }
.status-pill.idle{ background:rgba(140,160,179,0.12); color:var(--slate); border:1px solid rgba(140,160,179,0.3); }
.status-pill .dot{ width:6px; height:6px; border-radius:50%; background:currentColor; animation:blip 1.8s ease-in-out infinite; }
@keyframes blip{ 0%,100%{ opacity:1; } 50%{ opacity:.55; } }
.content{ padding:28px 32px 60px; }
.mp-card{ background:linear-gradient(180deg, var(--panel), var(--panel-2)); border:1px solid var(--line); border-radius:16px; padding:20px 22px; }
.stat-card{ position:relative; overflow:hidden; transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
.stat-card:hover{ transform:translateY(-3px); box-shadow:0 18px 34px -18px rgba(0,0,0,0.55); border-color:rgba(231,169,76,0.3); }
.stat-card .label{ font-family:'JetBrains Mono', monospace; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--slate); }
.stat-card .value{ font-family:'Space Grotesk', sans-serif; font-size:26px; font-weight:600; color:var(--paper); margin-top:8px; }
.stat-card .icon{
  position:absolute; right:16px; top:16px; width:38px; height:38px; border-radius:10px;
  display:flex; align-items:center; justify-content:center; background:rgba(231,169,76,0.1); color:var(--gold-soft); font-size:17px;
}
.stat-card.total{ background:linear-gradient(135deg, rgba(231,169,76,0.16), var(--panel-2)); border-color:rgba(231,169,76,0.35); }
.stat-card.total .value{ font-size:32px; color:var(--gold-soft); }
.trend{ display:inline-flex; align-items:center; gap:4px; font-family:'JetBrains Mono', monospace; font-size:11.5px; font-weight:500; margin-top:6px; }
.trend.up{ color:var(--mint); } .trend.down{ color:var(--red); } .trend.flat{ color:var(--slate); }
.section-title{ font-family:'Space Grotesk', sans-serif; font-size:16px; font-weight:600; color:var(--paper); display:flex; align-items:center; gap:9px; margin-bottom:14px; }
.section-title .eyebrow{ font-family:'JetBrains Mono', monospace; font-size:10.5px; color:var(--slate); font-weight:500; letter-spacing:.06em; text-transform:uppercase; margin-left:auto; }
.chart-card canvas{ max-height:230px; }
.chart-legend{ display:flex; flex-wrap:wrap; gap:14px; margin-top:14px; }
.chart-legend .item{ display:flex; align-items:center; gap:7px; font-size:12.5px; color:var(--slate); }
.chart-legend .swatch{ width:9px; height:9px; border-radius:3px; }
.table-mp{ --bs-table-bg:transparent; color:var(--paper); margin-bottom:0; }
.table-mp thead th{ font-family:'JetBrains Mono', monospace; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--slate); border-bottom:1px solid var(--line); font-weight:500; padding-bottom:12px; }
.table-mp td{ border-bottom:1px solid rgba(140,160,179,0.08); padding:13px 8px; font-size:14px; vertical-align:middle; }
.table-mp tr:last-child td{ border-bottom:none; }
.table-mp tr.total-row td{ font-weight:600; color:var(--gold-soft); border-top:1px solid var(--line); border-bottom:none; }
.network-badge{ display:inline-flex; align-items:center; gap:7px; font-weight:500; }
.network-dot{ width:8px; height:8px; border-radius:50%; }
.btn-mp-gold{ background:linear-gradient(180deg, var(--gold-soft), var(--gold)); border:none; color:#12233A; font-weight:600; border-radius:10px; padding:11px 20px; font-family:'Space Grotesk', sans-serif; font-size:14px; box-shadow:0 10px 22px -10px rgba(231,169,76,0.5); }
.btn-mp-gold:hover{ color:#12233A; filter:brightness(1.05); }
.btn-mp-outline{ background:transparent; border:1px solid var(--line); color:var(--paper); border-radius:10px; padding:10px 18px; font-size:14px; font-weight:500; }
.btn-mp-outline:hover{ background:rgba(231,169,76,0.08); color:var(--paper); border-color:var(--gold); }
.btn-mp-danger{ background:linear-gradient(180deg, #E97C6C, var(--red)); border:none; color:#2A0E09; font-weight:700; border-radius:12px; padding:15px 20px; font-family:'Space Grotesk', sans-serif; font-size:15.5px; box-shadow:0 12px 26px -12px rgba(225,96,79,0.55); width:100%; }
.empty-day{ text-align:center; padding:50px 20px; }
.empty-day i{ font-size:34px; color:var(--slate); }
.empty-day h3{ font-family:'Space Grotesk', sans-serif; margin-top:14px; font-size:18px; }
.empty-day p{ color:var(--slate); font-size:14px; margin-top:6px; }
.summary-line{ display:flex; justify-content:space-between; align-items:center; padding:11px 0; border-bottom:1px solid rgba(140,160,179,0.1); font-size:14px; }
.summary-line:last-child{ border-bottom:none; }
.summary-line .l{ color:var(--slate); }
.summary-line .v{ font-weight:600; font-family:'JetBrains Mono', monospace; }
.summary-line.grand{ margin-top:6px; padding-top:16px; border-top:1px solid var(--line); font-size:16px; }
.summary-line.grand .v{ color:var(--gold-soft); font-size:19px; }
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

  <!-- ===================== SIDEBAR ===================== -->
  <aside class="mp-sidebar">
    <div class="brand"><span class="dot"></span> MONEY&nbsp;POINT</div>
    <nav class="mp-nav">
      <a href="dashboard.php" class="active"><i class="bi bi-house-door"></i> Dashboard</a>
      <a href="lines.php"><i class="bi bi-phone"></i> Lines / Float</a>
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

    <!-- ===================== TOPBAR ===================== -->
    <header class="mp-topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" class="btn btn-mp-outline d-md-none py-2 px-3"><i class="bi bi-list"></i></button>
        <h1>Mobile Money Float Management System</h1>
      </div>
      <div class="meta">
        <span><?= date('l, j F Y') ?></span>
        <span id="liveClock"><?= date('H:i:s') ?></span>
        <span>Welcome, <strong><?= htmlspecialchars($current_user) ?></strong><span class="role-chip"><?= htmlspecialchars($current_role) ?></span></span>
        <span class="status-pill <?= $day_status === 'open' ? 'open' : ($day_status === 'closed' ? 'closed' : 'idle') ?>">
          <span class="dot"></span> DAY <?= $day_status === 'not_started' ? 'NOT STARTED' : strtoupper($day_status) ?>
        </span>
      </div>
    </header>

    <div class="content">

      <?php if ($day_status === 'not_started'): ?>
        <div class="mp-card empty-day mb-4">
          <i class="bi bi-moon-stars"></i>
          <h3>No active business day</h3>
          <p>Fungua siku ili kuanza kufuatilia float na cash ya leo.</p>
          <a href="open-day.php" class="btn btn-mp-gold mt-3 px-4"><i class="bi bi-play-circle me-1"></i> OPEN DAY</a>
        </div>
      <?php else: ?>

      <div class="section-title">Today's Summary <span class="eyebrow"><?= date('d/m/Y') ?></span></div>
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="mp-card stat-card">
            <div class="icon"><i class="bi bi-cash-stack"></i></div>
            <div class="label">Total Till</div>
            <div class="value" data-countup="<?= $mp_total_till ?>" data-prefix="TSh ">TSh 0</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="mp-card stat-card">
            <div class="icon"><i class="bi bi-phone"></i></div>
            <div class="label">Total Lipa Namba</div>
            <div class="value" data-countup="<?= $mp_total_lipa ?>" data-prefix="TSh ">TSh 0</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="mp-card stat-card">
            <div class="icon"><i class="bi bi-layers"></i></div>
            <div class="label">Total Float</div>
            <div class="value" data-countup="<?= $mp_total_float ?>" data-prefix="TSh ">TSh 0</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="mp-card stat-card">
            <div class="icon"><i class="bi bi-wallet2"></i></div>
            <div class="label">Cash</div>
            <div class="value" data-countup="<?= $mp_cash ?>" data-prefix="TSh ">TSh 0</div>
          </div>
        </div>
      </div>

      <div class="mp-card stat-card total mb-4">
        <div class="icon"><i class="bi bi-currency-exchange"></i></div>
        <div class="label">Total Money (Float + Cash)</div>
        <div class="value" data-countup="<?= $mp_total_money ?>" data-prefix="TSh ">TSh 0</div>
        <?php if ($trend): ?>
          <div class="trend <?= $trend['diff'] > 0 ? 'up' : ($trend['diff'] < 0 ? 'down' : 'flat') ?>">
            <i class="bi bi-arrow-<?= $trend['diff'] > 0 ? 'up-right' : ($trend['diff'] < 0 ? 'down-right' : 'right') ?>"></i>
            <?= ($trend['diff'] >= 0 ? '+' : '') . number_format($trend['diff']) ?> TSh
            (<?= ($trend['pct'] >= 0 ? '+' : '') . number_format($trend['pct'], 1) ?>%) vs last closing
          </div>
        <?php endif; ?>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-lg-4">
          <div class="mp-card chart-card h-100">
            <div class="section-title">Money Composition</div>
            <canvas id="compositionChart"></canvas>
            <div class="chart-legend">
              <span class="item"><span class="swatch" style="background:#E7A94C"></span> Till</span>
              <span class="item"><span class="swatch" style="background:#33B889"></span> Lipa Namba</span>
              <span class="item"><span class="swatch" style="background:#5B8DEF"></span> Cash</span>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="mp-card chart-card h-100">
            <div class="section-title">Float by Line <span class="eyebrow">Till vs Lipa Namba</span></div>
            <canvas id="lineChart"></canvas>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-8">
          <div class="mp-card h-100">
            <div class="section-title">Mobile Money Lines <span class="eyebrow"><?= count($mp_lines) ?> Lines</span></div>
            <div class="table-responsive">
              <table class="table table-mp">
                <thead>
                  <tr><th>Line</th><th>Network</th><th class="text-end">Till</th><th class="text-end">Lipa Namba</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                  <?php if (empty($mp_lines)): ?>
                    <tr><td colspan="5" class="text-center text-secondary py-4">Hakuna line zilizosajiliwa bado.</td></tr>
                  <?php else: foreach ($mp_lines as $line): ?>
                  <tr>
                    <td><?= htmlspecialchars($line['name']) ?></td>
                    <td><span class="network-badge"><span class="network-dot" style="background:<?= mp_line_color($line['network']) ?>"></span> <?= htmlspecialchars($line['network']) ?></span></td>
                    <td class="text-end"><?= mp_money($line['till']) ?></td>
                    <td class="text-end"><?= mp_money($line['lipa']) ?></td>
                    <td class="text-end"><?= mp_money($line['till'] + $line['lipa']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <tr class="total-row">
                    <td colspan="2">TOTAL</td>
                    <td class="text-end"><?= mp_money($mp_total_till) ?></td>
                    <td class="text-end"><?= mp_money($mp_total_lipa) ?></td>
                    <td class="text-end"><?= mp_money($mp_total_float) ?></td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="mp-card mb-4">
            <div class="section-title">Current Float</div>
            <div class="summary-line"><span class="l">Till</span><span class="v"><?= mp_money($mp_total_till) ?></span></div>
            <div class="summary-line"><span class="l">Lipa Namba</span><span class="v"><?= mp_money($mp_total_lipa) ?></span></div>
            <div class="summary-line grand"><span class="l">Total Float</span><span class="v"><?= mp_money($mp_total_float) ?></span></div>
          </div>
          <div class="mp-card">
            <div class="section-title"><i class="bi bi-cash-coin"></i> Cash</div>
            <div class="summary-line"><span class="l">Opening Cash</span><span class="v"><?= mp_money($mp_opening_cash) ?></span></div>
            <div class="summary-line"><span class="l">Current Cash</span><span class="v"><?= mp_money($mp_cash) ?></span></div>
          </div>
        </div>
      </div>

      <div class="mp-card mt-4">
        <div class="row align-items-center g-3">
          <div class="col-md-8">
            <div class="section-title mb-1"><i class="bi bi-lock"></i> Ready to close the day?</div>
            <p class="text-secondary mb-0" style="font-size:14px;">Confirm your closing cash and lock in today's totals.</p>
          </div>
          <div class="col-md-4">
            <a href="close-day.php" class="btn btn-mp-danger d-block text-center text-decoration-none"><i class="bi bi-lock-fill me-1"></i> CLOSE DAY</a>
          </div>
        </div>
      </div>

      <?php endif; ?>

      <?php if ($mp_last_closing): ?>
      <div class="mp-card mt-4">
        <div class="section-title">Recent Closing <span class="eyebrow"><?= date('d/m/Y', strtotime($mp_last_closing['date'])) ?></span></div>
        <div class="row g-3">
          <div class="col-6 col-md-3"><div class="summary-line"><span class="l">Float</span><span class="v"><?= mp_money((float)$mp_last_closing['total_float']) ?></span></div></div>
          <div class="col-6 col-md-3"><div class="summary-line"><span class="l">Cash</span><span class="v"><?= mp_money((float)$mp_last_closing['total_cash']) ?></span></div></div>
          <div class="col-6 col-md-3"><div class="summary-line"><span class="l">Total</span><span class="v"><?= mp_money((float)$mp_last_closing['total_money']) ?></span></div></div>
          <div class="col-6 col-md-3 d-flex align-items-center"><a href="history.php" class="btn btn-mp-outline btn-sm">VIEW DETAILS</a></div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---------------- Live clock ----------------
function mpTickClock() {
  const el = document.getElementById('liveClock');
  if (!el) return;
  const now = new Date();
  el.textContent = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0');
}
setInterval(mpTickClock, 1000);
mpTickClock();

// ---------------- Sidebar toggle (mobile) ----------------
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.mp-sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target) && !toggle.contains(e.target)) sidebar.classList.remove('open');
    });
  }
});

// ---------------- Count-up animation ----------------
function mpCountUp(el, endValue, duration = 900) {
  const startTime = performance.now();
  const prefix = el.dataset.prefix || '';
  function tick(now) {
    const progress = Math.min((now - startTime) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = prefix + Math.round(endValue * eased).toLocaleString('en-US');
    if (progress < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-countup]').forEach((el) => mpCountUp(el, parseFloat(el.dataset.countup || '0')));
});

// ---------------- Charts ----------------
Chart.defaults.color = '#8CA0B3';
Chart.defaults.font.family = "'Inter', sans-serif";

<?php if ($day_status !== 'not_started'): ?>
new Chart(document.getElementById('compositionChart'), {
  type: 'doughnut',
  data: {
    labels: ['Till', 'Lipa Namba', 'Cash'],
    datasets: [{
      data: [<?= $mp_total_till ?>, <?= $mp_total_lipa ?>, <?= $mp_cash ?>],
      backgroundColor: ['#E7A94C', '#33B889', '#5B8DEF'],
      borderColor: '#0E2233', borderWidth: 3, hoverOffset: 6
    }]
  },
  options: { cutout: '68%', plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('lineChart'), {
  type: 'bar',
  data: {
    labels: [<?php foreach ($mp_lines as $l) echo "'" . addslashes($l['name']) . "',"; ?>],
    datasets: [
      { label: 'Till', data: [<?php foreach ($mp_lines as $l) echo $l['till'] . ','; ?>], backgroundColor: '#E7A94C', borderRadius: 6, maxBarThickness: 28 },
      { label: 'Lipa Namba', data: [<?php foreach ($mp_lines as $l) echo $l['lipa'] . ','; ?>], backgroundColor: '#33B889', borderRadius: 6, maxBarThickness: 28 }
    ]
  },
  options: {
    responsive: true,
    scales: {
      x: { grid: { display: false } },
      y: { grid: { color: 'rgba(140,160,179,0.12)' }, ticks: { callback: (v) => (v/1000) + 'K' } }
    },
    plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } }
  }
});
<?php endif; ?>
</script>
</body>
</html>