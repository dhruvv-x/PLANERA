<?php
// faculties.php - full CRUD (create, read, update, delete) for faculties
session_start();
require_once 'config.php';

// Require login
if(!isset($_SESSION['user'])){
    header('Location: login.php');
    exit;
}

$conn = db_connect();
$errors = [];
$success = '';

// ---------- Handle CREATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create'){
    $employee_code = trim($_POST['employee_code'] ?? '');
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $max_classes   = intval($_POST['max_classes'] ?? 6);
    $avg_leaves    = floatval($_POST['avg_leaves'] ?? 0);

    if($full_name === ''){
        $errors[] = "Full name is required.";
    }

    if(empty($errors)){
        $stmt = $conn->prepare('INSERT INTO faculties (employee_code, full_name, email, phone, max_classes_per_day, avg_leaves_per_month, is_active) VALUES (?,?,?,?,?,?,1)');
        if(!$stmt){
            $errors[] = "Prepare failed: " . $conn->error;
        } else {
            // types: 4 strings, 1 int, 1 double => "ssssid"
            $stmt->bind_param("ssssid", $employee_code, $full_name, $email, $phone, $max_classes, $avg_leaves);
            if(!$stmt->execute()){
                $errors[] = "Insert failed: " . $stmt->error;
            } else {
                $success = "Faculty added successfully.";
            }
            $stmt->close();
        }
    }
}

// ---------- Handle UPDATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update'){
    $id            = intval($_POST['id'] ?? 0);
    $employee_code = trim($_POST['employee_code'] ?? '');
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $max_classes   = intval($_POST['max_classes'] ?? 6);
    $avg_leaves    = floatval($_POST['avg_leaves'] ?? 0);
    $is_active     = (isset($_POST['is_active']) && $_POST['is_active']=='1') ? 1 : 0;

    if($id <= 0){
        $errors[] = "Invalid ID for update.";
    }
    if($full_name === ''){
        $errors[] = "Full name is required.";
    }

    if(empty($errors)){
        $stmt = $conn->prepare('UPDATE faculties SET employee_code=?, full_name=?, email=?, phone=?, max_classes_per_day=?, avg_leaves_per_month=?, is_active=? WHERE id=?');
        if(!$stmt){
            $errors[] = "Prepare failed: " . $conn->error;
        } else {
            // types: s s s s i d i i -> but mysqli types doesn't have separate for booleans; using 'i' for ints
            $stmt->bind_param("ssssidii", $employee_code, $full_name, $email, $phone, $max_classes, $avg_leaves, $is_active, $id);
            if(!$stmt->execute()){
                $errors[] = "Update failed: " . $stmt->error;
            } else {
                $success = "Faculty updated successfully.";
            }
            $stmt->close();
        }
    }
}

// ---------- Handle DELETE ----------
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    if($del_id > 0){
        $stmt = $conn->prepare('DELETE FROM faculties WHERE id = ?');
        if($stmt){
            $stmt->bind_param('i', $del_id);
            if($stmt->execute()){
                $success = "Faculty deleted.";
            } else {
                $errors[] = "Delete failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Prepare failed: " . $conn->error;
        }
    } else {
        $errors[] = "Invalid delete id.";
    }
}

// ---------- Fetch single record for editing (if requested) ----------
$editing = false;
$edit_row = null;
if(isset($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    if($edit_id > 0){
        $stmt = $conn->prepare('SELECT id, employee_code, full_name, email, phone, max_classes_per_day, avg_leaves_per_month, is_active FROM faculties WHERE id = ? LIMIT 1');
        if($stmt){
            $stmt->bind_param('i', $edit_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $edit_row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if($edit_row) $editing = true;
        }
    }
}

// ---------- Fetch list ----------
$res = $conn->query('SELECT id, employee_code, full_name, email, phone, max_classes_per_day, avg_leaves_per_month, is_active FROM faculties ORDER BY id DESC');
$faculties = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Faculties</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .form-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
    .form-row input, .form-row select{padding:6px}
    .small{width:120px}
    .actions a{margin-right:8px}
    .notice{padding:8px;border-radius:6px;margin-bottom:8px}
    .notice.success{background:#e6ffed;border:1px solid #b6f2c6}
    .notice.error{background:#ffecec;border:1px solid #ffb7b7}
  </style>
</head>
<body>
<header><a href="index.php">← Back</a> | Faculties</header>
<main class="container">
  <section class="content">
    <h2>Faculties</h2>

    <?php if($success): ?>
      <div class="notice success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
      <div class="notice error"><?php echo htmlspecialchars(implode(" | ", $errors)); ?></div>
    <?php endif; ?>

    <?php if($editing && $edit_row): ?>
      <h3>Edit Faculty #<?php echo (int)$edit_row['id']; ?></h3>
      <form method="post" class="inline-form">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
        <div class="form-row">
          <input name="employee_code" placeholder="Emp code" value="<?php echo htmlspecialchars($edit_row['employee_code']); ?>">
          <input name="full_name" placeholder="Full name" required value="<?php echo htmlspecialchars($edit_row['full_name']); ?>">
          <input name="email" placeholder="Email" value="<?php echo htmlspecialchars($edit_row['email']); ?>">
          <input name="phone" placeholder="Phone" class="small" value="<?php echo htmlspecialchars($edit_row['phone']); ?>">
          <input name="max_classes" placeholder="Max classes/day" class="small" value="<?php echo htmlspecialchars($edit_row['max_classes_per_day']); ?>">
          <input name="avg_leaves" placeholder="Avg leaves/mo" class="small" value="<?php echo htmlspecialchars($edit_row['avg_leaves_per_month']); ?>">
          <label>Active
            <select name="is_active">
              <option value="1" <?php echo $edit_row['is_active'] ? 'selected' : ''; ?>>Yes</option>
              <option value="0" <?php echo !$edit_row['is_active'] ? 'selected' : ''; ?>>No</option>
            </select>
          </label>
          <button type="submit">Save</button>
          <a href="faculties.php">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <h3>Add New Faculty</h3>
      <form method="post" class="inline-form">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <input name="employee_code" placeholder="Emp code (optional)">
          <input name="full_name" placeholder="Full name" required>
          <input name="email" placeholder="Email">
          <input name="phone" placeholder="Phone" class="small">
          <input name="max_classes" placeholder="Max classes/day" class="small" value="6">
          <input name="avg_leaves" placeholder="Avg leaves/mo" class="small" value="0">
          <button type="submit">Add</button>
        </div>
      </form>
    <?php endif; ?>

    <h3>Faculty List</h3>
    <table class="list" style="margin-top:10px">
      <tr><th>ID</th><th>Emp Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Max/day</th><th>Avg leaves</th><th>Active</th><th>Actions</th></tr>
      <?php if(empty($faculties)): ?>
        <tr><td colspan="9">No faculties found.</td></tr>
      <?php else: foreach($faculties as $f): ?>
        <tr>
          <td><?php echo (int)$f['id']; ?></td>
          <td><?php echo htmlspecialchars($f['employee_code']); ?></td>
          <td><?php echo htmlspecialchars($f['full_name']); ?></td>
          <td><?php echo htmlspecialchars($f['email']); ?></td>
          <td><?php echo htmlspecialchars($f['phone']); ?></td>
          <td><?php echo htmlspecialchars($f['max_classes_per_day']); ?></td>
          <td><?php echo htmlspecialchars($f['avg_leaves_per_month']); ?></td>
          <td><?php echo $f['is_active'] ? 'Yes' : 'No'; ?></td>
          <td class="actions">
            <a href="faculties.php?edit=<?php echo (int)$f['id']; ?>">Edit</a>
            <a href="faculties.php?delete=<?php echo (int)$f['id']; ?>" onclick="return confirm('Delete this faculty?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>

  </section>
</main>
</body>
</html>
