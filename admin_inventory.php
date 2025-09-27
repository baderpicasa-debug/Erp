<?php
require_once __DIR__ . '/config.php';

// Require login
if (empty($_SESSION['is_admin'])) {
  header('Location: admin_login.php');
  exit;
}

$conn = db();

// ---------------- Filters ----------------
$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['employee'])) {
  $where .= " AND e.name LIKE ?";
  $params[] = "%" . $_GET['employee'] . "%";
  $types .= 's';
}
if (!empty($_GET['branch'])) {
  $where .= " AND e.branch LIKE ?";
  $params[] = "%" . $_GET['branch'] . "%";
  $types .= 's';
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
  $where .= " AND DATE(l.created_at) BETWEEN ? AND ?";
  $params[] = $_GET['from'];
  $params[] = $_GET['to'];
  $types .= 'ss';
}

// ---------------- Base SQL ----------------
$sql_logs_base = "SELECT 
                    l.id, 
                    e.name AS employee, 
                    e.id AS employee_id, 
                    e.branch, 
                    i.item_name, 
                    l.qty_change, 
                    l.note, 
                    l.created_at
                  FROM branch_inventory_logs l
                  JOIN employees e ON l.employee_id = e.id
                  JOIN inventory_items i ON l.item_id = i.id
                  WHERE $where";

// ---------------- Helper ----------------
function runQuery($conn, $sql, $params = [], $types = '') {
  if (empty($params)) return $conn->query($sql);
  
  // A more robust type handling can be implemented here, but for this case, 's' is sufficient.
  if ($types === '') {
    $types = str_repeat('s', count($params));
  }
  
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    die("SQL Prepare Error: " . $conn->error);
  }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  return $stmt->get_result();
}

// ---------------- Exports ----------------
if (isset($_GET['export'])) {
  $export = $_GET['export'];

  if ($export === 'logs') {
    // تصدير السجلات المفصلة
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="inventory_logs.csv"');
    $out = fopen("php://output", "w");
    // BOM علشان العربي
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ["ID","Employee","Branch","Item","Qty","Note","Date/Time"]);
    $sql_export = $sql_logs_base . " ORDER BY DATE(created_at), created_at";
    $res_export = runQuery($conn, $sql_export, $params, $types);
    if ($res_export) {
      while ($r = $res_export->fetch_assoc()) {
        fputcsv($out, [
          $r['id'],
          $r['employee'],
          $r['branch'],
          $r['item_name'],
          $r['qty_change'],
          $r['note'],
          $r['created_at'],
        ]);
      }
    }
    fclose($out);
    exit;
  }

  if ($export === 'summary') {
    // تصدير التجميعة اليومية
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="inventory_summary_by_date.csv"');
    $out = fopen("php://output", "w");
    // BOM
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ["Date","Total Qty"]);
    $sql_summary_export = "SELECT DATE(l.created_at) as d, SUM(l.qty_change) as total_qty
                           FROM branch_inventory_logs l
                           JOIN employees e ON l.employee_id = e.id
                           JOIN inventory_items i ON l.item_id = i.id
                           WHERE " . $where . "
                           GROUP BY DATE(l.created_at)
                           ORDER BY DATE(l.created_at)";
    $res_summary = runQuery($conn, $sql_summary_export, $params, $types);
    if ($res_summary) {
      while ($r = $res_summary->fetch_assoc()) {
        fputcsv($out, [$r['d'], $r['total_qty']]);
      }
    }
    fclose($out);
    exit;
  }
}

// ---------------- Main Query (for page) ----------------
$sql = $sql_logs_base . " ORDER BY DATE(l.created_at), l.created_at";
$res = runQuery($conn, $sql, $params, $types);

// ---------------- Grouping + Stats ----------------
$groups = [];
$total_records = 0;
$total_qty = 0;
$employees = [];
$items = [];

if ($res && $res->num_rows) {
  while($r = $res->fetch_assoc()) {
    $date = substr($r['created_at'],0,10); // yyyy-mm-dd
    if (!isset($groups[$date])) {
      $groups[$date] = [
        'date'  => $date,
        'total_qty'=> 0,
        'items' => []
      ];
    }
    $groups[$date]['total_qty'] += (float)$r['qty_change'];
    $groups[$date]['items'][] = $r;

    $total_records++;
    $total_qty += (float)$r['qty_change'];
    $employees[$r['employee']] = true;
    $items[$r['item_name']] = true;
  }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Management</title>
<style>
  body{font-family:Arial, sans-serif;background:#f4f6f8;margin:20px;}
  h2{text-align:center;margin-bottom:20px}
      .topbar { display: flex; justify-content: space-between; align-items: center; max-width: 1100px; margin: 0 auto 15px; }

  form{background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);max-width:1100px;margin:0 auto 12px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
  input,button,select{padding:8px;border:1px solid #ddd;border-radius:6px}
  button{background:#007bff;color:#fff;cursor:pointer;border:none}
  button:hover{background:#0056b3}
  .export-bar{background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);max-width:1100px;margin:0 auto 20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
  .export-btn{background:#28a745;}
  .export-btn:hover{background:#1e7e34;}
  table{width:100%;border-collapse:collapse;background:#fff;margin-top:10px;}
  th,td{border:1px solid #eee;padding:8px;text-align:center}
  th{background:#f3f3f3}
  .toggle-btn{cursor:pointer;color:#007bff;text-decoration:underline;}
  .hidden{display:none;}
  .sub-table{margin:10px auto;width:95%;border:1px solid #ccc;}
  .actions a{margin:0 5px;text-decoration:none;}
  .actions a.edit{color:#007bff;}
  .actions a.delete{color:#c00;}
  .cards{display:flex;gap:15px;justify-content:center;flex-wrap:wrap;margin:20px auto;max-width:1100px;}
  .card{flex:1;min-width:180px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:15px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);}
  .card h3{margin:0;font-size:16px;color:#555;}
  .card p{margin:8px 0 0;font-size:22px;font-weight:bold;color:#007bff;}
</style>
<script>
function toggleItems(id){
  const row = document.getElementById("items-"+id);
  row.classList.toggle("hidden");
}
</script>
</head>
<body>
<div class="topbar">
    <h2>📊 Inventory Management</h2>
    <div><a href="index.php"> ⚙️ Dashboard</a> | <a href="logout.php">Sign out</a></div>
</div>

<form method="get">
  <input type="text" name="employee" placeholder="Employee" value="<?= htmlspecialchars($_GET['employee'] ?? '') ?>">
  <input type="text" name="branch" placeholder="Branch" value="<?= htmlspecialchars($_GET['branch'] ?? '') ?>">
  <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
  <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
  <button type="submit">Filter</button>
</form>

<div class="export-bar">
  <form method="get" style="display:inline;">
    <input type="hidden" name="employee" value="<?= htmlspecialchars($_GET['employee'] ?? '') ?>">
    <input type="hidden" name="branch"   value="<?= htmlspecialchars($_GET['branch'] ?? '') ?>">
    <input type="hidden" name="from"     value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
    <input type="hidden" name="to"       value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
    <input type="hidden" name="export" value="logs">
    <button type="submit" class="export-btn">⬇ Export Logs (CSV)</button>
  </form>

  <form method="get" style="display:inline;">
    <input type="hidden" name="employee" value="<?= htmlspecialchars($_GET['employee'] ?? '') ?>">
    <input type="hidden" name="branch"   value="<?= htmlspecialchars($_GET['branch'] ?? '') ?>">
    <input type="hidden" name="from"     value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
    <input type="hidden" name="to"       value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
    <input type="hidden" name="export" value="summary">
    <button type="submit" class="export-btn">⬇ Export Summary by Date (CSV)</button>
  </form>
</div>

<div class="cards">
  <div class="card"><h3>Total Records</h3><p><?= $total_records ?></p></div>
  <div class="card"><h3>Total Qty</h3><p><?= $total_qty ?></p></div>
  <div class="card"><h3>Employees</h3><p><?= count($employees) ?></p></div>
  <div class="card"><h3>Unique Items</h3><p><?= count($items) ?></p></div>
</div>

<table>
<thead>
<tr>
  <th>#</th>
  <th>Date</th>
  <th>Total Qty</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($groups): 
  $i=1;
  foreach($groups as $date=>$g): ?>
<tr>
  <td><?= $i++ ?></td>
  <td><?= $g['date'] ?></td>
  <td><?= $g['total_qty'] ?></td>
  <td><span class="toggle-btn" onclick="toggleItems('<?= md5($date) ?>')">View Items</span></td>
</tr>
<tr id="items-<?= md5($date) ?>" class="hidden">
  <td colspan="4">
    <table class="sub-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Branch</th>
          <th>Item</th>
          <th>Qty</th>
          <th>Note</th>
          <th>Date/Time</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($g['items'] as $item): ?>
        <tr>
          <td>
            <a href="employee_stats.php?id=<?= urlencode($item['employee_id']) ?>">
              <?= htmlspecialchars($item['employee']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($item['branch']) ?></td>
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td><?= $item['qty_change'] ?></td>
          <td><?= htmlspecialchars($item['note']) ?></td>
          <td><?= $item['created_at'] ?></td>
          <td class="actions">
            <a href="edit_inventory.php?id=<?= $item['id'] ?>" class="edit">✏️ Edit</a> | 
            <a href="delete_inventory.php?id=<?= $item['id'] ?>" class="delete" onclick="return confirm('⚠️ Are you sure you want to delete this record?');">🗑️ Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="4">No records found</td></tr>
<?php endif; ?>
</tbody>
</table>

</body>
</html>