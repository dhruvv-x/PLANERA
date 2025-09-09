<?php
// slots.php - CRUD for timetable time slots
session_start();
require_once 'config.php';

// require login
if(!isset($_SESSION['user'])){
    header('Location: login.php'); exit;
}

$conn = db_connect();
$errors = [];
$success = '';

// ---------- CREATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create'){
    $slot_code = trim($_POST['slot_code'] ?? '');
    $day = trim($_POST['day'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $slot_order = intval($_POST['slot_order'] ?? 1);

    // basic validation
    if($slot_code === '') $errors[] = 'Slot code is required.';
    if($day === '') $errors[] = 'Day is required.';
    if($start_time === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) $errors[] = 'Start time required (HH:MM or HH:MM:SS).';
    if($end_time === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) $errors[] = 'End time required (HH:MM or HH:MM:SS).';
    if($slot_order <= 0) $slot_order = 1;

    if(empty($errors)){
        $stmt = $conn->prepare('INSERT INTO timetable_slots (slot_code, day, start_time, end_time, slot_order) VALUES (?,?,?,?,?)');
        if(!$stmt){
            $errors[] = 'Prepare failed: ' . $conn->error;
        } else {
            $stmt->bind_param('ssssi', $slot_code, $day, $start_time, $end_time, $slot_order);
            if(!$stmt->execute()){
                // handle duplicate slot_code gracefully
                $errors[] = 'Insert failed: ' . $stmt->error;
            } else {
                $success = 'Slot added.';
            }
            $stmt->close();
        }
    }
}

// ---------- UPDATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update'){
    $id = intval($_POST['id'] ?? 0);
    $slot_code = trim($_POST['slot_code'] ?? '');
    $day = trim($_POST['day'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $slot_order = intval($_POST['slot_order'] ?? 1);

    if($id <= 0) $errors[] = 'Invalid slot id.';
    if($slot_code === '') $errors[] = 'Slot code is required.';
    if($day === '') $errors[] = 'Day is required.';
    if($start_time === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) $errors[] = 'Start time required (HH:MM or HH:MM:SS).';
    if($end_time === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) $errors[] = 'End time required (HH:MM or HH:MM:SS).';

    if(empty($errors)){
        $stmt = $conn->prepare('UPDATE timetable_slots SET slot_code=?, day=?, start_time=?, end_time=?, slot_order=? WHERE id=?');
        if(!$stmt){
            $errors[] = 'Prepare failed: ' . $conn->error;
        } else {
            $stmt->bind_param('ssssii', $slot_code, $day, $start_time, $end_time, $slot_order, $id);
            if(!$stmt->execute()){
                $errors[] = 'Update failed: ' . $stmt->error;
            } else {
                $success = 'Slot updated.';
            }
            $stmt->close();
        }
    }
}

// ---------- DELETE ----------
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    if($del_id > 0){
        $stmt = $conn->prepare('DELETE FROM timetable_slots WHERE id = ?');
        if($stmt){
            $stmt->bind_param('i', $del_id);
            if($stmt->execute()){
                $success = 'Slot deleted.';
            } else {
                $errors[] = 'Delete failed: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = 'Prepare failed: ' . $conn->error;
        }
    } else {
        $errors[] = 'Invalid delete id.';
    }
}

// ---------- FETCH FOR EDIT ----------
$editing = false;
$edit_row = null;
if(isset($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    if($edit_id > 0){
        $stmt = $conn->prepare('SELECT id, slot_code, day, TIME_FORMAT(start_time, "%H:%i:%s") as start_time, TIME_FORMAT(end_time, "%H:%i:%s") as end_time, slot_order FROM timetable_slots WHERE id = ? LIMIT 1');
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
$res = $conn->query('SELECT id, slot_code, day, TIME_FORMAT(start_time, "%H:%i:%s") as start_time, TIME_FORMAT(end_time, "%H:%i:%s") as end_time, slot_order FROM timetable_slots ORDER BY day, slot_order, start_time');
$slots = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Timetable Slots</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .form-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
    .form-row input, .form-row select{padding:6px}
    .small{width:120px}
    .notice{padding:8px;border-radius:6px;margin-bottom:8px}
    .notice.success{background:#e6ffed;border:1px solid #b6f2c6}
    .notice.error{background:#ffecec;border:1px solid #ffb7b7}
    table.list th, table.list td{font-size:14px}
  </style>
</head>
<body>
<header><a href="index.php">← Back</a> | Slots</header>
<main class="container">
  <section class="content">
    <h2>Timetable Slots</h2>

    <?php if($success): ?>
      <div class="notice success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
      <div class="notice error"><?php echo htmlspecialchars(implode(" | ", $errors)); ?></div>
    <?php endif; ?>

    <?php if($editing && $edit_row): ?>
      <h3>Edit Slot #<?php echo (int)$edit_row['id']; ?></h3>
      <form method="post" class="inline-form">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
        <div class="form-row">
          <input name="slot_code" placeholder="Slot code (unique)" required value="<?php echo htmlspecialchars($edit_row['slot_code']); ?>">
          <select name="day" required>
            <?php
            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            foreach($days as $d){
                $sel = ($d === $edit_row['day']) ? 'selected' : '';
                echo "<option value=\"{$d}\" $sel>{$d}</option>";
            }
            ?>
          </select>
          <input name="start_time" placeholder="Start (HH:MM)" class="small" required value="<?php echo htmlspecialchars($edit_row['start_time']); ?>">
          <input name="end_time" placeholder="End (HH:MM)" class="small" required value="<?php echo htmlspecialchars($edit_row['end_time']); ?>">
          <input name="slot_order" placeholder="Order" class="small" value="<?php echo (int)$edit_row['slot_order']; ?>">
          <button type="submit">Save</button>
          <a href="slots.php">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <h3>Add New Slot</h3>
      <form method="post" class="inline-form">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <input name="slot_code" placeholder="Slot code (unique) e.g. MON_09_00" required>
          <select name="day" required>
            <option value="">-- Day --</option>
            <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
            <option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option>
          </select>
          <input name="start_time" placeholder="Start (HH:MM)" class="small" required>
          <input name="end_time" placeholder="End (HH:MM)" class="small" required>
          <input name="slot_order" placeholder="Order" class="small" value="1">
          <button type="submit">Add</button>
        </div>
      </form>
    <?php endif; ?>

    <h3>Slot List</h3>
    <table class="list" style="margin-top:10px">
      <tr><th>ID</th><th>Code</th><th>Day</th><th>Start</th><th>End</th><th>Order</th><th>Actions</th></tr>
      <?php if(empty($slots)): ?>
        <tr><td colspan="7">No slots found.</td></tr>
      <?php else: foreach($slots as $s): ?>
        <tr>
          <td><?php echo (int)$s['id']; ?></td>
          <td><?php echo htmlspecialchars($s['slot_code']); ?></td>
          <td><?php echo htmlspecialchars($s['day']); ?></td>
          <td><?php echo htmlspecialchars($s['start_time']); ?></td>
          <td><?php echo htmlspecialchars($s['end_time']); ?></td>
          <td><?php echo (int)$s['slot_order']; ?></td>
          <td>
            <a href="slots.php?edit=<?php echo (int)$s['id']; ?>">Edit</a>
            <a href="slots.php?delete=<?php echo (int)$s['id']; ?>" onclick="return confirm('Delete this slot?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>

  </section>
</main>
</body>
</html>
