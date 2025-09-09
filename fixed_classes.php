<?php
// fixed_classes.php - Manage fixed/special classes (CRUD)
session_start();
require_once 'config.php';

// require login
if(!isset($_SESSION['user'])){
    header('Location: login.php'); exit;
}

$conn = db_connect();
$errors = [];
$success = '';

// Helper: fetch lookup lists
function fetch_all_assoc($conn, $sql) {
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

$batches = fetch_all_assoc($conn, 'SELECT id, name FROM batches ORDER BY name');
$subjects = fetch_all_assoc($conn, 'SELECT id, code, name FROM subjects ORDER BY code');
$slots = fetch_all_assoc($conn, 'SELECT id, slot_code, day, start_time, end_time FROM timetable_slots ORDER BY day, slot_order');
$classrooms = fetch_all_assoc($conn, 'SELECT id, code, capacity FROM classrooms ORDER BY code');
$faculties = fetch_all_assoc($conn, 'SELECT id, full_name, employee_code FROM faculties ORDER BY full_name');

// ---------- CREATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create'){
    $batch_id = intval($_POST['batch_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $slot_id = intval($_POST['slot_id'] ?? 0);
    $classroom_id = intval($_POST['classroom_id'] ?? 0) ?: null;
    $faculty_id = intval($_POST['faculty_id'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');
    $created_by = $_SESSION['user']['id'] ?? null;

    if($batch_id <= 0) $errors[] = "Select a batch.";
    if($subject_id <= 0) $errors[] = "Select a subject.";
    if($slot_id <= 0) $errors[] = "Select a slot.";

    if(empty($errors)){
        $stmt = $conn->prepare('INSERT INTO fixed_classes (batch_id, subject_id, slot_id, classroom_id, faculty_id, notes, created_by) VALUES (?,?,?,?,?,?,?)');
        if(!$stmt){
            $errors[] = "Prepare failed: " . $conn->error;
        } else {
            // classroom_id and faculty_id may be null: bind as integers or null via param references
            // Use 'i' for ints and 's' for notes, last param created_by integer (may be null)
            // We'll coerce nulls to NULL using bind_param with nullables handled as ints with value 0 and then pass NULL via DEFAULT? Simpler: use explicit types and handle nulls with NULL in SQL using separate query.
            // Use this approach: bind params with intval and pass nulls as NULL via 'i' but we need to use mysqli_stmt::bind_param which cannot accept null to set SQL NULL easily.
            // Workaround: if classroom_id or faculty_id is null, set to NULL in SQL via separate prepare.
            if($classroom_id === null && $faculty_id === null){
                $stmt->close();
                $stmt = $conn->prepare('INSERT INTO fixed_classes (batch_id, subject_id, slot_id, classroom_id, faculty_id, notes, created_by) VALUES (?,?,?,NULL,NULL,?,?)');
                $stmt->bind_param('iiiss', $batch_id, $subject_id, $slot_id, $notes, $created_by);
            } elseif($classroom_id === null){
                $stmt->close();
                $stmt = $conn->prepare('INSERT INTO fixed_classes (batch_id, subject_id, slot_id, classroom_id, faculty_id, notes, created_by) VALUES (?,?,?,NULL,?,?,?)');
                $stmt->bind_param('iiiisi', $batch_id, $subject_id, $slot_id, $faculty_id, $notes, $created_by);
            } elseif($faculty_id === null){
                $stmt->close();
                $stmt = $conn->prepare('INSERT INTO fixed_classes (batch_id, subject_id, slot_id, classroom_id, faculty_id, notes, created_by) VALUES (?,?,?,?,NULL,?,?)');
                $stmt->bind_param('iiiiss', $batch_id, $subject_id, $slot_id, $classroom_id, $notes, $created_by);
            } else {
                // both present
                $stmt->bind_param('iiiiiis', $batch_id, $subject_id, $slot_id, $classroom_id, $faculty_id, $notes, $created_by);
            }

            // Execute (some branches already bound)
            if(!$stmt->execute()){
                $errors[] = "Insert failed: " . $stmt->error;
            } else {
                $success = "Fixed class added.";
            }
            $stmt->close();
        }
    }
}

// ---------- UPDATE ----------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update'){
    $id = intval($_POST['id'] ?? 0);
    $batch_id = intval($_POST['batch_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $slot_id = intval($_POST['slot_id'] ?? 0);
    $classroom_id = intval($_POST['classroom_id'] ?? 0) ?: null;
    $faculty_id = intval($_POST['faculty_id'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');

    if($id <= 0) $errors[] = "Invalid record id.";
    if($batch_id <= 0) $errors[] = "Select a batch.";
    if($subject_id <= 0) $errors[] = "Select a subject.";
    if($slot_id <= 0) $errors[] = "Select a slot.";

    if(empty($errors)){
        // Build flexible update SQL allowing NULLs
        if($classroom_id === null && $faculty_id === null){
            $stmt = $conn->prepare('UPDATE fixed_classes SET batch_id=?, subject_id=?, slot_id=?, classroom_id=NULL, faculty_id=NULL, notes=? WHERE id=?');
            $stmt->bind_param('iiisi', $batch_id, $subject_id, $slot_id, $notes, $id);
        } elseif($classroom_id === null){
            $stmt = $conn->prepare('UPDATE fixed_classes SET batch_id=?, subject_id=?, slot_id=?, classroom_id=NULL, faculty_id=?, notes=? WHERE id=?');
            $stmt->bind_param('iiiisi', $batch_id, $subject_id, $slot_id, $faculty_id, $notes, $id);
        } elseif($faculty_id === null){
            $stmt = $conn->prepare('UPDATE fixed_classes SET batch_id=?, subject_id=?, slot_id=?, classroom_id=?, faculty_id=NULL, notes=? WHERE id=?');
            $stmt->bind_param('iiisii', $batch_id, $subject_id, $slot_id, $classroom_id, $notes, $id);
        } else {
            $stmt = $conn->prepare('UPDATE fixed_classes SET batch_id=?, subject_id=?, slot_id=?, classroom_id=?, faculty_id=?, notes=? WHERE id=?');
            $stmt->bind_param('iiiiisi', $batch_id, $subject_id, $slot_id, $classroom_id, $faculty_id, $notes, $id);
        }

        if(!$stmt){
            $errors[] = "Prepare failed: " . $conn->error;
        } else {
            if(!$stmt->execute()){
                $errors[] = "Update failed: " . $stmt->error;
            } else {
                $success = "Fixed class updated.";
            }
            $stmt->close();
        }
    }
}

// ---------- DELETE ----------
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    if($del_id > 0){
        $stmt = $conn->prepare('DELETE FROM fixed_classes WHERE id = ?');
        if($stmt){
            $stmt->bind_param('i', $del_id);
            if($stmt->execute()){
                $success = "Fixed class deleted.";
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

// ---------- FETCH single record for edit ----------
$editing = false;
$edit_row = null;
if(isset($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    if($edit_id > 0){
        $stmt = $conn->prepare('SELECT id, batch_id, subject_id, slot_id, classroom_id, faculty_id, notes FROM fixed_classes WHERE id = ? LIMIT 1');
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
$list_sql = "
SELECT fc.id, b.name AS batch_name, s.code AS subject_code, s.name AS subject_name,
       ts.slot_code, ts.day, ts.start_time, ts.end_time,
       cr.code AS classroom_code, f.full_name AS faculty_name, fc.notes, fc.created_at
FROM fixed_classes fc
LEFT JOIN batches b ON fc.batch_id = b.id
LEFT JOIN subjects s ON fc.subject_id = s.id
LEFT JOIN timetable_slots ts ON fc.slot_id = ts.id
LEFT JOIN classrooms cr ON fc.classroom_id = cr.id
LEFT JOIN faculties f ON fc.faculty_id = f.id
ORDER BY ts.day, ts.slot_order, fc.id DESC
";
$res = $conn->query($list_sql);
$fixed_classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Fixed Classes</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .form-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
    .form-row select, .form-row input, .form-row textarea{padding:6px}
    .small{width:130px}
    .notice{padding:8px;border-radius:6px;margin-bottom:8px}
    .notice.success{background:#e6ffed;border:1px solid #b6f2c6}
    .notice.error{background:#ffecec;border:1px solid #ffb7b7}
    table.list th, table.list td{font-size:14px}
    textarea{min-width:240px;min-height:40px}
  </style>
</head>
<body>
<header><a href="index.php">← Back</a> | Fixed Classes</header>
<main class="container">
  <section class="content">
    <h2>Fixed / Special Classes</h2>

    <?php if($success): ?>
      <div class="notice success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if(!empty($errors)): ?>
      <div class="notice error"><?php echo htmlspecialchars(implode(" | ", $errors)); ?></div>
    <?php endif; ?>

    <?php if($editing && $edit_row): ?>
      <h3>Edit Fixed Class #<?php echo (int)$edit_row['id']; ?></h3>
      <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
        <div class="form-row">
          <select name="batch_id" required>
            <option value="">-- Batch --</option>
            <?php foreach($batches as $b): $sel = ($b['id']==$edit_row['batch_id']) ? 'selected' : ''; ?>
              <option value="<?php echo $b['id'];?>" <?php echo $sel;?>><?php echo htmlspecialchars($b['name']);?></option>
            <?php endforeach;?>
          </select>

          <select name="subject_id" required>
            <option value="">-- Subject --</option>
            <?php foreach($subjects as $s): $sel = ($s['id']==$edit_row['subject_id']) ? 'selected' : ''; ?>
              <option value="<?php echo $s['id'];?>" <?php echo $sel;?>><?php echo htmlspecialchars($s['code'].' - '.$s['name']);?></option>
            <?php endforeach;?>
          </select>

          <select name="slot_id" required>
            <option value="">-- Slot --</option>
            <?php foreach($slots as $sl): $sel = ($sl['id']==$edit_row['slot_id']) ? 'selected' : ''; ?>
              <option value="<?php echo $sl['id'];?>" <?php echo $sel;?>><?php echo htmlspecialchars($sl['day'].' '.$sl['slot_code'].' ('.$sl['start_time'].'-'.$sl['end_time'].')');?></option>
            <?php endforeach;?>
          </select>

          <select name="classroom_id">
            <option value="0">-- Classroom (optional) --</option>
            <?php foreach($classrooms as $cr): $sel = ($cr['id']==$edit_row['classroom_id']) ? 'selected' : ''; ?>
              <option value="<?php echo $cr['id'];?>" <?php echo $sel;?>><?php echo htmlspecialchars($cr['code'].' ('.$cr['capacity'].')');?></option>
            <?php endforeach;?>
          </select>

          <select name="faculty_id">
            <option value="0">-- Faculty (optional) --</option>
            <?php foreach($faculties as $fa): $sel = ($fa['id']==$edit_row['faculty_id']) ? 'selected' : ''; ?>
              <option value="<?php echo $fa['id'];?>" <?php echo $sel;?>><?php echo htmlspecialchars($fa['full_name'].' ('.$fa['employee_code'].')');?></option>
            <?php endforeach;?>
          </select>

          <textarea name="notes" placeholder="Notes"><?php echo htmlspecialchars($edit_row['notes']);?></textarea>

          <button type="submit">Save</button>
          <a href="fixed_classes.php">Cancel</a>
        </div>
      </form>

    <?php else: ?>

      <h3>Add Fixed Class</h3>
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <select name="batch_id" required>
            <option value="">-- Batch --</option>
            <?php foreach($batches as $b): ?>
              <option value="<?php echo $b['id'];?>"><?php echo htmlspecialchars($b['name']);?></option>
            <?php endforeach;?>
          </select>

          <select name="subject_id" required>
            <option value="">-- Subject --</option>
            <?php foreach($subjects as $s): ?>
              <option value="<?php echo $s['id'];?>"><?php echo htmlspecialchars($s['code'].' - '.$s['name']);?></option>
            <?php endforeach;?>
          </select>

          <select name="slot_id" required>
            <option value="">-- Slot --</option>
            <?php foreach($slots as $sl): ?>
              <option value="<?php echo $sl['id'];?>"><?php echo htmlspecialchars($sl['day'].' '.$sl['slot_code'].' ('.$sl['start_time'].'-'.$sl['end_time'].')');?></option>
            <?php endforeach;?>
          </select>

          <select name="classroom_id">
            <option value="0">-- Classroom (optional) --</option>
            <?php foreach($classrooms as $cr): ?>
              <option value="<?php echo $cr['id'];?>"><?php echo htmlspecialchars($cr['code'].' ('.$cr['capacity'].')');?></option>
            <?php endforeach;?>
          </select>

          <select name="faculty_id">
            <option value="0">-- Faculty (optional) --</option>
            <?php foreach($faculties as $fa): ?>
              <option value="<?php echo $fa['id'];?>"><?php echo htmlspecialchars($fa['full_name'].' ('.$fa['employee_code'].')');?></option>
            <?php endforeach;?>
          </select>

          <textarea name="notes" placeholder="Notes"></textarea>

          <button type="submit">Add</button>
        </div>
      </form>

    <?php endif; ?>

    <h3>Fixed Class List</h3>
    <table class="list" style="margin-top:10px">
      <tr>
        <th>ID</th><th>Batch</th><th>Subject</th><th>Slot</th><th>Classroom</th><th>Faculty</th><th>Notes</th><th>Created At</th><th>Actions</th>
      </tr>
      <?php if(empty($fixed_classes)): ?>
        <tr><td colspan="9">No fixed classes found.</td></tr>
      <?php else: foreach($fixed_classes as $fc): ?>
        <tr>
          <td><?php echo (int)$fc['id']; ?></td>
          <td><?php echo htmlspecialchars($fc['batch_name']); ?></td>
          <td><?php echo htmlspecialchars($fc['subject_code'].' - '.$fc['subject_name']); ?></td>
          <td><?php echo htmlspecialchars(($fc['day'] ? $fc['day'].' ' : '') . ($fc['slot_code'] ?? '') . ' ' . ($fc['start_time'] ? '(' . $fc['start_time'] . '-' . $fc['end_time'] . ')' : '')); ?></td>
          <td><?php echo htmlspecialchars($fc['classroom_code'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($fc['faculty_name'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($fc['notes']); ?></td>
          <td><?php echo htmlspecialchars($fc['created_at']); ?></td>
          <td>
            <a href="fixed_classes.php?edit=<?php echo (int)$fc['id']; ?>">Edit</a>
            <a href="fixed_classes.php?delete=<?php echo (int)$fc['id']; ?>" onclick="return confirm('Delete this fixed class?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>

  </section>
</main>
</body>
</html>
