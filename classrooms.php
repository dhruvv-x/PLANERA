<html lang="en">
<head>
  <meta charset="utf-8">

  <!-- GLOBAL: fonts + viewport -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Smart Scheduler</title>

  <!-- main site CSS (global) -->
  <link rel="stylesheet" href="/smart_scheduler_app/assets/style.css">
  <!-- small inline page-level tweaks can follow in individual pages if needed -->
</head>
<?php
// classrooms.php - full CRUD for classrooms
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

// ---------- CREATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create'){
    $code = trim($_POST['code'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 30);
    $location = trim($_POST['location'] ?? '');
    $features = trim($_POST['features'] ?? '');
    if($code === ''){
        $errors[] = "Room code is required.";
    }
    if(empty($errors)){
        $stmt = $conn->prepare('INSERT INTO classrooms (code, capacity, location, features, is_active) VALUES (?,?,?,?,1)');
        if(!$stmt){
            $errors[] = "Prepare failed: " . $conn->error;
        } else {
            // types: s i s s
            $stmt->bind_param("siss", $code, $capacity, $location, $features);
            if(!$stmt->execute()){
                $errors[] = "Insert failed: " . $stmt->error;
            } else {
                $success = "Classroom added.";
            }
            $stmt->close();
        }
    }
}

// ---------- UPDATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update'){
    $id = intval($_POST['id'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 30);
    $location = trim($_POST['location'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $is_active = (isset($_POST['is_active']) && $_POST['is_active']=='1') ? 1 : 0;

    if($id <= 0) $errors[] = "Invalid classroom ID.";
    if($code === '') $errors[] = "Room code is required.";

    if(empty($errors)){
        $stmt = $conn->prepare('UPDATE classrooms SET code=?, capacity=?, location=?, features=?, is_active=? WHERE id=?');
        if(!$stmt){
            $errors[] = "Prepare failed: " . $conn->error;
        } else {
            // types: s i s s i i
            $stmt->bind_param("sissii", $code, $capacity, $location, $features, $is_active, $id);
            if(!$stmt->execute()){
                $errors[] = "Update failed: " . $stmt->error;
            } else {
                $success = "Classroom updated.";
            }
            $stmt->close();
        }
    }
}

// ---------- DELETE ----------
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    if($del_id > 0){
        $stmt = $conn->prepare('DELETE FROM classrooms WHERE id = ?');
        if($stmt){
            $stmt->bind_param('i', $del_id);
            if($stmt->execute()){
                $success = "Classroom deleted.";
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

// ---------- FETCH FOR EDIT ----------
$editing = false;
$edit_row = null;
if(isset($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    if($edit_id > 0){
        $stmt = $conn->prepare('SELECT id, code, capacity, location, features, is_active FROM classrooms WHERE id = ? LIMIT 1');
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

// ---------- LIST ----------
$res = $conn->query('SELECT id, code, capacity, location, features, is_active FROM classrooms ORDER BY id DESC');
$classrooms = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Classrooms</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .form-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
    .form-row input, .form-row textarea, .form-row select{padding:6px}
    .small{width:120px}
    .actions a{margin-right:8px}
    .notice{padding:8px;border-radius:6px;margin-bottom:8px}
    .notice.success{background:#e6ffed;border:1px solid #b6f2c6}
    .notice.error{background:#ffecec;border:1px solid #ffb7b7}
    textarea{min-width:220px;min-height:40px}
  </style>
</head>
<body>
<header><a href="index.php">← Back</a> | Classrooms</header>
<main class="container">
  <section class="content">
    <h2>Classrooms</h2>

    <?php if($success): ?>
      <div class="notice success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
      <div class="notice error"><?php echo htmlspecialchars(implode(" | ", $errors)); ?></div>
    <?php endif; ?>

    <?php if($editing && $edit_row): ?>
      <h3>Edit Classroom #<?php echo (int)$edit_row['id']; ?></h3>
      <form method="post" class="inline-form">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
        <div class="form-row">
          <input name="code" placeholder="Room code (e.g. R101)" required value="<?php echo htmlspecialchars($edit_row['code']); ?>">
          <input name="capacity" placeholder="Capacity" class="small" value="<?php echo htmlspecialchars($edit_row['capacity']); ?>">
          <input name="location" placeholder="Location" value="<?php echo htmlspecialchars($edit_row['location']); ?>">
          <textarea name="features" placeholder="Features (comma separated)"><?php echo htmlspecialchars($edit_row['features']); ?></textarea>
          <label>Active
            <select name="is_active">
              <option value="1" <?php echo $edit_row['is_active'] ? 'selected' : ''; ?>>Yes</option>
              <option value="0" <?php echo !$edit_row['is_active'] ? 'selected' : ''; ?>>No</option>
            </select>
          </label>
          <button type="submit">Save</button>
          <a href="classrooms.php">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <h3>Add Classroom</h3>
      <form method="post" class="inline-form">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <input name="code" placeholder="Room code (e.g. R101)" required>
          <input name="capacity" placeholder="Capacity" class="small" value="30">
          <input name="location" placeholder="Location">
          <textarea name="features" placeholder="Features (comma separated)"></textarea>
          <button type="submit">Add</button>
        </div>
      </form>
    <?php endif; ?>

    <h3>Classroom List</h3>
    <table class="list" style="margin-top:10px">
      <tr><th>ID</th><th>Code</th><th>Capacity</th><th>Location</th><th>Features</th><th>Active</th><th>Actions</th></tr>
      <?php if(empty($classrooms)): ?>
        <tr><td colspan="7">No classrooms found.</td></tr>
      <?php else: foreach($classrooms as $c): ?>
        <tr>
          <td><?php echo (int)$c['id']; ?></td>
          <td><?php echo htmlspecialchars($c['code']); ?></td>
          <td><?php echo htmlspecialchars($c['capacity']); ?></td>
          <td><?php echo htmlspecialchars($c['location']); ?></td>
          <td><?php echo htmlspecialchars($c['features']); ?></td>
          <td><?php echo $c['is_active'] ? 'Yes' : 'No'; ?></td>
          <td class="actions">
            <a href="classrooms.php?edit=<?php echo (int)$c['id']; ?>">Edit</a>
            <a href="classrooms.php?delete=<?php echo (int)$c['id']; ?>" onclick="return confirm('Delete this classroom?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>

  </section>
</main>
</body>
</html>
