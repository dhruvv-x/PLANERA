<?php
    session_start(); require 'config.php';
    if(!isset($_SESSION['user'])){ header('Location: login.php'); exit; }
    $conn = db_connect();
    $departments = $conn->query('SELECT id,code,name FROM departments');
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create'){
        $dept = $_POST['department_id']; $code = $_POST['code']; $name = $_POST['name'];
        $stmt = $conn->prepare('INSERT INTO subjects (department_id,code,name,lecture_hours_per_week) VALUES (?,?,?,?)');
        $lh = intval($_POST['lecture_hours']??0);
        $stmt->bind_param('issi',$dept,$code,$name,$lh); $stmt->execute(); $stmt->close();
        header('Location: subjects.php'); exit;
    }
    $res = $conn->query('SELECT s.*, d.code as dept_code FROM subjects s LEFT JOIN departments d ON s.department_id=d.id ORDER BY s.id DESC');
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Subjects</title><link rel="stylesheet" href="assets/style.css"></head><body>
    <header><a href="index.php">← Back</a> | Subjects</header>
    <main class="container"><section class="content">
      <h2>Subjects</h2>
      <form method="post" class="inline-form"><input type="hidden" name="action" value="create">
        <select name="department_id" required>
          <?php while($d=$departments->fetch_assoc()): ?>
            <option value="<?php echo $d['id'];?>"><?php echo htmlspecialchars($d['code'].' - '.$d['name']);?></option>
          <?php endwhile; ?>
        </select>
        <input name="code" placeholder="Sub code" required> <input name="name" placeholder="Subject name" required>
        <input name="lecture_hours" placeholder="Lecture hrs/week" style="width:120px">
        <button type="submit">Add</button>
      </form>
      <table class="list"><tr><th>ID</th><th>Dept</th><th>Code</th><th>Name</th><th>Lhrs/wk</th></tr>
      <?php while($row=$res->fetch_assoc()): ?>
        <tr><td><?php echo $row['id'];?></td><td><?php echo htmlspecialchars($row['dept_code']);?></td><td><?php echo htmlspecialchars($row['code']);?></td><td><?php echo htmlspecialchars($row['name']);?></td><td><?php echo $row['lecture_hours_per_week'];?></td></tr>
      <?php endwhile; ?>
      </table>
    </section></main></body></html>
    <?php $conn->close(); ?>