<?php
    session_start(); require 'config.php';
    if(!isset($_SESSION['user'])){ header('Location: login.php'); exit; }
    $conn = db_connect();
    // Handle create
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create'){
        $code = $_POST['code']; $name = $_POST['name'];
        $stmt = $conn->prepare('INSERT INTO departments (code,name) VALUES (?,?)');
        $stmt->bind_param('ss',$code,$name); $stmt->execute(); $stmt->close();
        header('Location: departments.php'); exit;
    }
    $res = $conn->query('SELECT * FROM departments ORDER BY id DESC');
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Departments</title><link rel="stylesheet" href="assets/style.css"></head><body>
    <header><a href="index.php">← Back</a> | Departments</header>
    <main class="container"><section class="content">
      <h2>Departments</h2>
      <form method="post" class="inline-form"><input type="hidden" name="action" value="create">
        <input name="code" placeholder="Code (e.g. CSE)" required> <input name="name" placeholder="Department name" required>
        <button type="submit">Add</button>
      </form>
      <table class="list"><tr><th>ID</th><th>Code</th><th>Name</th></tr>
      <?php while($row=$res->fetch_assoc()): ?>
        <tr><td><?php echo $row['id'];?></td><td><?php echo htmlspecialchars($row['code']);?></td><td><?php echo htmlspecialchars($row['name']);?></td></tr>
      <?php endwhile; ?>
      </table>
    </section></main></body></html>
    <?php $conn->close(); ?>