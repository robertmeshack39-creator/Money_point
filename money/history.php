<?php
/**
 * money/history.php — Daily History Page
 * View all daily closing records with search and filter
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
// Filter parameters
// ---------------------------------------------------------------
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$status    = $_GET['status'] ?? '';
$search    = trim($_GET['search'] ?? '');

// ---------------------------------------------------------------
// Build query
// ---------------------------------------------------------------
$sql = "SELECT dc.*, u.username as closed_by_name 
        FROM `daily_closing` dc 
        LEFT JOIN `users` u ON dc.closed_by = u.id 
        WHERE 1=1";
$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND dc.date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
} elseif (!empty($startDate)) {
    $sql .= " AND dc.date >= ?";
    $params[] = $startDate;
} elseif (!empty($endDate)) {
    $sql .= " AND dc.date <= ?";
    $params[] = $endDate;
}

if (!empty($status)) {
    $sql .= " AND dc.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY dc.date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    $records = [];
}

// ---------------------------------------------------------------
// Calculate summary statistics
// ---------------------------------------------------------------
$totalRecords = count($records);
$sumTill = 0;
$sumLipa = 0;
$sumFloat = 0;
$sumCash = 0;
$sumMoney = 0;

foreach ($records as $r) {
    $sumTill  += floatval($r['total_till'] ?? 0);
    $sumLipa  += floatval($r['total_lipa'] ?? 0);
    $sumFloat += floatval($r['total_float'] ?? 0);
    $sumCash  += floatval($r['total_cash'] ?? 0);
    $sumMoney += floatval($r['total_money'] ?? 0);
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

function statusBadge($status) {
    if ($status === 'open') {
        return '<span class="badge-status open"><i class="bi bi-circle-fill" style="font-size:6px;"></i> OPEN</span>';
    } elseif ($status === 'closed') {
        return '<span class="badge-status closed"><i class="bi bi-circle-fill" style="font-size:6px;"></i> CLOSED</span>';
    }
    return '<span class="badge-status idle"><i class="bi bi-circle-fill" style="font-size:6px;"></i> N/A</span>';
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily History — Money Point</title>

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
.table-mp{ --bs-table-bg:transparent; color:var(--paper); margin-bottom:0; }
.table-mp thead th{ font-family:'JetBrains Mono', monospace; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--slate); border-bottom:1px solid var(--line); font-weight:500; padding:12px 10px; }
.table-mp td{ border-bottom:1px solid rgba(140,160,179,0.08); padding:12px 10px; font-size:13.5px; vertical-align:middle; }
.table-mp tbody tr:hover{ background:rgba(231,169,76,0.04); }
.table-mp tfoot td{ font-weight:600; color:var(--gold-soft); border-top:1px solid var(--line); border-bottom:none; padding:14px 10px; font-family:'JetBrains Mono', monospace; }
.badge-status{ display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:600; font-family:'JetBrains Mono', monospace; }
.badge-status.open{ background:rgba(51,184,137,0.15); color:var(--mint); border:1px solid rgba(51,184,137,0.35); }
.badge-status.closed{ background:rgba(225,96,79,0.15); color:var(--red); border:1px solid rgba(225,96,79,0.35); }
.badge-status.idle{ background:rgba(140,160,179,0.12); color:var(--slate); border:1px solid rgba(140,160,179,0.3); }
.stat-mini{ background:rgba(255,255,255,0.02); border:1px solid var(--line); border-radius:12px; padding:14px 16px; }
.stat-mini .label{ font-size:11px; color:var(--slate); text-transform:uppercase; letter-spacing:.05em; font-weight:500; }
.stat-mini .value{ font-family:'Space Grotesk', sans-serif; font-size:20px; font-weight:600; color:var(--paper); margin-top:4px; }
.stat-mini .value.gold{ color:var(--gold-soft); }
.stat-mini .value.mint{ color:var(--mint); }
.empty-state{ text-align:center; padding:60px 20px; color:var(--slate); }
.empty-state i{ font-size:52px; opacity:0.4; }
.filter-card{ background:linear-gradient(180deg, var(--panel), var(--panel-2)); border:1px solid var(--line); border-radius:16px; padding:18px 22px; margin-bottom:20px; }
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
      <a href="history.php" class="active"><i class="bi bi-calendar3"></i> Daily History</a>
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
        <h1>📅 Daily History</h1>
      </div>
      <div class="meta">
        <span><?= date('l, j F Y') ?></span>
        <span>Welcome, <strong><?= htmlspecialchars($current_user) ?></strong></span>
      </div>
    </header>

    <div class="content">

      <!-- ============ SUMMARY STATS ============ -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="stat-mini">
            <div class="label">Records</div>
            <div class="value"><?= number_format($totalRecords) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-mini">
            <div class="label">Total Float</div>
            <div class="value mint"><?= formatShort($sumFloat) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-mini">
            <div class="label">Total Cash</div>
            <div class="value"><?= formatShort($sumCash) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-mini">
            <div class="label">Total Money</div>
            <div class="value gold"><?= formatShort($sumMoney) ?></div>
          </div>
        </div>
      </div>

      <!-- ============ FILTERS ============ -->
      <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="mp-form-label">From Date</label>
            <input type="date" name="start_date" class="mp-form-control" value="<?= htmlspecialchars($startDate) ?>">
          </div>
          <div class="col-md-3">
            <label class="mp-form-label">To Date</label>
            <input type="date" name="end_date" class="mp-form-control" value="<?= htmlspecialchars($endDate) ?>">
          </div>
          <div class="col-md-2">
            <label class="mp-form-label">Status</label>
            <select name="status" class="mp-form-select">
              <option value="">All</option>
              <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
              <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-mp-gold w-100">
              <i class="bi bi-search me-1"></i> Filter
            </button>
          </div>
          <div class="col-md-2">
            <a href="history.php" class="btn btn-mp-outline w-100">
              <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
            </a>
          </div>
        </form>
      </div>

      <!-- ============ QUICK FILTERS ============ -->
      <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="history.php" class="btn btn-mp-outline btn-sm py-2 px-3">All Time</a>
        <a href="history.php?start_date=<?= $today ?>&end_date=<?= $today ?>" class="btn btn-mp-outline btn-sm py-2 px-3">Today</a>
        <a href="history.php?start_date=<?= date('Y-m-d', strtotime('-1 day')) ?>&end_date=<?= date('Y-m-d', strtotime('-1 day')) ?>" class="btn btn-mp-outline btn-sm py-2 px-3">Yesterday</a>
        <a href="history.php?start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= $today ?>" class="btn btn-mp-outline btn-sm py-2 px-3">Last 7 Days</a>
        <a href="history.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= $today ?>" class="btn btn-mp-outline btn-sm py-2 px-3">This Month</a>
        <a href="history.php?status=closed" class="btn btn-mp-outline btn-sm py-2 px-3">Closed Only</a>
      </div>

      <!-- ============ HISTORY TABLE ============ -->
      <div class="mp-card">
        <div class="section-title">
          <i class="bi bi-clock-history" style="color:var(--gold-soft);"></i> Records
          <span class="eyebrow"><?= count($records) ?> Found</span>
        </div>

        <?php if (empty($records)): ?>
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h5 class="mt-3">Hakuna Records</h5>
            <p>Hakuna records zinazolingana na filter yako.</p>
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-mp">
            <thead>
              <tr>
                <th>Date</th>
                <th>Status</th>
                <th class="text-end">Till</th>
                <th class="text-end">Lipa</th>
                <th class="text-end">Float</th>
                <th class="text-end">Cash</th>
                <th class="text-end">Total Money</th>
                <th>Closed By</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $r): ?>
              <tr>
                <td>
                  <strong><?= date('d/m/Y', strtotime($r['date'])) ?></strong>
                  <div style="font-size:11.5px;color:var(--slate);">
                    <?= date('D', strtotime($r['date'])) ?>
                  </div>
                </td>
                <td><?= statusBadge($r['status']) ?></td>
                <td class="text-end"><?= formatMoney($r['total_till'] ?? 0) ?></td>
                <td class="text-end"><?= formatMoney($r['total_lipa'] ?? 0) ?></td>
                <td class="text-end" style="color:var(--mint);font-weight:600;"><?= formatMoney($r['total_float'] ?? 0) ?></td>
                <td class="text-end"><?= formatMoney($r['total_cash'] ?? 0) ?></td>
                <td class="text-end" style="color:var(--gold-soft);font-weight:700;"><?= formatMoney($r['total_money'] ?? 0) ?></td>
                <td>
                  <?php if ($r['closed_by_name']): ?>
                    <span style="font-size:13px;"><?= htmlspecialchars($r['closed_by_name']) ?></span>
                  <?php else: ?>
                    <span style="color:var(--slate);font-size:12px;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2" class="text-end">TOTAL (<?= $totalRecords ?> days)</td>
                <td class="text-end"><?= formatMoney($sumTill) ?></td>
                <td class="text-end"><?= formatMoney($sumLipa) ?></td>
                <td class="text-end"><?= formatMoney($sumFloat) ?></td>
                <td class="text-end"><?= formatMoney($sumCash) ?></td>
                <td class="text-end"><?= formatMoney($sumMoney) ?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>
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