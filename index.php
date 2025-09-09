<?php
// index.php - PLANERA interactive dashboard (single-file, includes AJAX handlers)
// Place this file in your project root. Requires config.php -> db_connect()

session_start();
require 'config.php';
if(!isset($_SESSION['user'])){ header('Location: login.php'); exit; }

$conn = db_connect();

// -----------------------
// AJAX endpoints (same file)
// -----------------------
$action = $_GET['action'] ?? null;

if($action === 'counts'){
    // return JSON counts
    $out = [];
    $tables = [
        'batches','subjects','faculties','classrooms','timetable_slots','fixed_classes','timetable_options'
    ];
    foreach($tables as $t){
        $res = $conn->query("SELECT COUNT(*) AS c FROM $t");
        $out[$t] = $res ? intval($res->fetch_assoc()['c'] ?? 0) : 0;
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    $conn->close();
    exit;
}

if($action === 'list_options'){
    // return recent options as JSON
    $limit = intval($_GET['limit'] ?? 8);
    $stmt = $conn->prepare('SELECT topts.id, topts.batch_id, topts.score, topts.created_at, b.name AS batch_name
                            FROM timetable_options topts
                            LEFT JOIN batches b ON topts.batch_id=b.id
                            ORDER BY topts.created_at DESC
                            LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    header('Content-Type: application/json');
    echo json_encode($rows);
    $stmt->close();
    $conn->close();
    exit;
}

if($action === 'option_details'){
    // return HTML snippet for timetable entries of an option id
    $opt = intval($_GET['id'] ?? 0);
    if($opt <= 0){ http_response_code(400); echo 'Invalid option id'; exit; }

    $q = "SELECT te.id, te.day, ts.slot_order, ts.slot_code, ts.start_time, ts.end_time,
                 s.code AS subject_code, s.name AS subject_name,
                 f.full_name AS faculty_name,
                 c.code AS classroom_code, te.is_fixed
          FROM timetable_entries te
          LEFT JOIN timetable_slots ts ON te.slot_id = ts.id
          LEFT JOIN subjects s ON te.subject_id = s.id
          LEFT JOIN faculties f ON te.faculty_id = f.id
          LEFT JOIN classrooms c ON te.classroom_id = c.id
          WHERE te.option_id = ?
          ORDER BY FIELD(te.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), ts.slot_order";
    $stmt = $conn->prepare($q);
    $stmt->bind_param('i', $opt);
    $stmt->execute();
    $res = $stmt->get_result();
    $entries = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    // Build a simple table grouped by day and slot_order
    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $grid = [];
    foreach($entries as $e){
        $d = $e['day'] ?? 'Monday';
        $o = intval($e['slot_order'] ?? 0);
        if(!isset($grid[$d])) $grid[$d] = [];
        $grid[$d][$o] = $e;
    }

    // Build HTML (fragment)
    ob_start();
    ?>
    <div style="padding:10px;font-family:Inter,Arial,Helvetica,sans-serif;color:#0b1220">
      <h3 style="margin:0 0 10px">Timetable Preview — Option #<?php echo (int)$opt; ?></h3>
      <div style="overflow:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="text-align:left">
              <th style="padding:8px;border-bottom:1px solid #e6eef6;width:140px">Day</th>
              <th style="padding:8px;border-bottom:1px solid #e6eef6">Slot</th>
              <th style="padding:8px;border-bottom:1px solid #e6eef6">Subject</th>
              <th style="padding:8px;border-bottom:1px solid #e6eef6">Faculty</th>
              <th style="padding:8px;border-bottom:1px solid #e6eef6">Room</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $has = false;
            foreach($days as $d){
                if(empty($grid[$d])) continue;
                foreach($grid[$d] as $slotOrder => $row){
                    $has = true;
                    $slotLabel = ($row['slot_code'] ? $row['slot_code'].' ' : 'Slot '.$slotOrder) . (!empty($row['start_time']) ? ' ('.substr($row['start_time'],0,5).')' : '');
                    echo '<tr>';
                    echo '<td style="padding:8px;border-bottom:1px solid #f3f6fb">'.htmlspecialchars($d).'</td>';
                    echo '<td style="padding:8px;border-bottom:1px solid #f3f6fb">'.htmlspecialchars($slotLabel).'</td>';
                    echo '<td style="padding:8px;border-bottom:1px solid #f3f6fb">'.htmlspecialchars(trim(($row['subject_code']?:'').' '.$row['subject_name'])).'</td>';
                    echo '<td style="padding:8px;border-bottom:1px solid #f3f6fb">'.htmlspecialchars($row['faculty_name'] ?? '-').'</td>';
                    echo '<td style="padding:8px;border-bottom:1px solid #f3f6fb">'.htmlspecialchars($row['classroom_code'] ?? '-').'</td>';
                    echo '</tr>';
                }
            }
            if(!$has) echo '<tr><td colspan="5" style="padding:12px;color:#64748b">No timetable entries found for this option.</td></tr>';
            ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    $conn->close();
    exit;
}

// -----------------------
// Page render (normal GET)
// -----------------------

// Fetch initial counts for server-rendered page (fast)
$counts = [];
$tables = ['batches','subjects','faculties','classrooms','timetable_slots','fixed_classes','timetable_options'];
foreach($tables as $t){
    $r = $conn->query("SELECT COUNT(*) AS c FROM $t");
    $counts[$t] = $r ? intval($r->fetch_assoc()['c'] ?? 0) : 0;
}

// Fetch recent timetable options (server side for initial render)
$opts = [];
$optRes = $conn->query('SELECT topts.id, topts.batch_id, topts.score, topts.created_at, b.name AS batch_name FROM timetable_options topts LEFT JOIN batches b ON topts.batch_id=b.id ORDER BY topts.created_at DESC LIMIT 6');
if($optRes) $opts = $optRes->fetch_all(MYSQLI_ASSOC);

$user = $_SESSION['user'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>PLANERA — Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">

  <style>
    /* Light professional theme (compact) */
    :root{
      --bg:#f6f9fb; --card:#fff; --muted:#64748b; --text:#0b1220;
      --accent-a:#4b6cff; --accent-b:#22c7d8;
      --accent-grad:linear-gradient(90deg,var(--accent-a),var(--accent-b));
      --glass-border:1px solid #e6eef6; --radius:12px;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:'Inter',system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
    .wrap{max-width:1200px;margin:20px auto;padding:20px}

    header{display:flex;justify-content:space-between;align-items:center;padding:14px;border-radius:12px;background:var(--card);border:var(--glass-border);box-shadow:0 10px 30px rgba(11,20,26,0.04)}
    .brand{display:flex;align-items:center;gap:14px}
    .brand-badge{width:52px;height:52px;border-radius:10px;background:var(--accent-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-family:'Montserrat';font-size:18px}
    .brand-title{font-family:'Montserrat';font-weight:700;font-size:18px}
    .brand-sub{font-size:13px;color:var(--muted);font-weight:700}

    .controls{display:flex;gap:12px;align-items:center}
    .btn{background:var(--accent-grad);border:0;padding:10px 14px;border-radius:10px;color:#021019;font-weight:800;cursor:pointer;box-shadow:0 10px 30px rgba(75,108,255,0.12)}
    .btn.ghost{background:transparent;border:1px solid #e6eef6;color:var(--text);box-shadow:none}

    .layout{display:grid;grid-template-columns:260px 1fr;gap:18px;margin-top:18px}
    .sidebar{background:var(--card);border:var(--glass-border);padding:14px;border-radius:12px}
    .nav a{display:block;padding:10px;border-radius:8px;color:var(--text);font-weight:700;text-decoration:none;margin-bottom:6px}
    .nav a:hover{background:rgba(75,108,255,0.06)}

    .content{background:var(--card);border:var(--glass-border);padding:18px;border-radius:12px}
    .grid {display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    .stat{background:#fff;padding:14px;border-radius:10px;border:1px solid #eef6fb;box-shadow:0 6px 18px rgba(11,20,26,0.04);display:flex;flex-direction:column;gap:6px}
    .stat .k{font-weight:800;color:var(--muted);font-size:13px}
    .stat .v{font-weight:900;font-size:26px}

    .quick-actions{display:flex;gap:12px;margin-top:12px}
    .search{margin-top:12px;display:flex;gap:8px}
    .search input{flex:1;padding:10px;border-radius:10px;border:1px solid #e6eef6}

    table{width:100%;border-collapse:collapse;margin-top:12px;font-size:14px}
    th,td{padding:10px;border-bottom:1px solid #f3f6fb;text-align:left}
    th{font-weight:800}
    tr.clickable:hover{background:linear-gradient(90deg, rgba(75,108,255,0.03), rgba(34,199,216,0.02));cursor:pointer}

    /* modal */
    .modal-back{position:fixed;inset:0;background:rgba(5,9,14,0.45);display:none;align-items:center;justify-content:center;padding:20px;z-index:999}
    .modal{background:#fff;border-radius:10px;padding:18px;max-width:900px;width:100%;max-height:85vh;overflow:auto;border:1px solid #e6eef6}
    .modal .close{float:right;background:transparent;border:0;font-weight:800;cursor:pointer}

    /* small screens */
    @media (max-width:900px){
      .layout{grid-template-columns:1fr; padding:10px}
      .grid{grid-template-columns:repeat(2,1fr)}
    }
    @media (max-width:560px){
      .grid{grid-template-columns:1fr}
    }

    /* subtle animation */
    .pop{transition:transform .18s ease, box-shadow .18s ease}
    .pop:hover{transform:translateY(-6px);box-shadow:0 22px 50px rgba(11,20,26,0.06)}
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <div class="brand">
        <div class="brand-badge">PL</div>
        <div>
          <div class="brand-title">PLANERA</div>
          <div class="brand-sub">Plan smarter with PLANERA.</div>
        </div>
      </div>
      <div class="controls">
        <div style="font-weight:700;color:#0b1220;margin-right:8px">Signed in as <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
        <button class="btn ghost" onclick="location.href='logout.php'">Logout</button>
        <button class="btn" id="refreshCountsBtn" title="Refresh counts">Refresh</button>
      </div>
    </header>

    <div class="layout">
      <aside class="sidebar">
        <nav class="nav" aria-label="Main">
          <a href="index.php">Dashboard</a>
          <a href="generate.php">Generate Timetable</a>
          <a href="batches.php">Batches</a>
          <a href="subjects.php">Subjects</a>
          <a href="faculties.php">Faculties</a>
          <a href="classrooms.php">Classrooms</a>
          <a href="slots.php">Slots</a>
          <a href="fixed_classes.php">Fixed Classes</a>
          <a href="viewer_option.php">View Timetables</a>
        </nav>

        <div style="margin-top:12px">
          <div style="font-weight:800;color:var(--muted);margin-bottom:8px">Quick Search</div>
          <div class="search">
            <input id="searchInput" placeholder="Search batch or subject...">
            <button class="btn ghost" id="searchBtn">Go</button>
          </div>
        </div>
      </aside>

      <main class="content">
        <h2 style="margin:0 0 10px;font-family:'Montserrat'">Overview</h2>

        <div class="grid">
          <div class="stat pop" id="stat_batches"><div class="k">Batches</div><div class="v" id="v_batches"><?php echo $counts['batches']; ?></div></div>
          <div class="stat pop" id="stat_subjects"><div class="k">Subjects</div><div class="v" id="v_subjects"><?php echo $counts['subjects']; ?></div></div>
          <div class="stat pop" id="stat_faculties"><div class="k">Faculties</div><div class="v" id="v_faculties"><?php echo $counts['faculties']; ?></div></div>

          <div class="stat pop" id="stat_rooms"><div class="k">Classrooms</div><div class="v" id="v_classrooms"><?php echo $counts['classrooms']; ?></div></div>
          <div class="stat pop" id="stat_slots"><div class="k">Slots</div><div class="v" id="v_slots"><?php echo $counts['timetable_slots']; ?></div></div>
          <div class="stat pop" id="stat_fixed"><div class="k">Fixed Classes</div><div class="v" id="v_fixed"><?php echo $counts['fixed_classes']; ?></div></div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;">
          <h3 style="margin:0;font-family:'Montserrat'">Recent Timetable Options</h3>
          <div>
            <button class="btn ghost" id="reloadOptions">Reload</button>
            <button class="btn" id="openGenerate" onclick="location.href='generate.php'">Generate Timetable</button>
          </div>
        </div>

        <table id="optionsTable" aria-live="polite">
          <thead><tr><th>Option</th><th>Batch</th><th>Score</th><th>Created</th></tr></thead>
          <tbody>
            <?php if(empty($opts)): ?>
              <tr><td colspan="4" style="padding:10px;color:var(--muted)">No options found.</td></tr>
            <?php else: foreach($opts as $r): ?>
              <tr class="clickable" data-id="<?php echo (int)$r['id']; ?>">
                <td>#<?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['batch_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars(number_format($r['score'],0)); ?></td>
                <td><?php echo htmlspecialchars(substr($r['created_at'] ?? '',0,16)); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>

      </main>
    </div>
  </div>

  <!-- modal -->
  <div class="modal-back" id="modalBack" role="dialog" aria-hidden="true">
    <div class="modal" id="modalContent">
      <button class="close" id="modalClose">Close ✕</button>
      <div id="modalBody" style="margin-top:10px"></div>
    </div>
  </div>

  <script>
    // helper fetch wrapper
    async function api(path){
      const res = await fetch(path, { credentials: 'same-origin' });
      if(!res.ok) throw new Error('Network error: ' + res.status);
      const ct = res.headers.get('content-type') || '';
      if(ct.indexOf('application/json') !== -1) return res.json();
      return res.text();
    }

    // Refresh counts
    async function refreshCounts(){
      try{
        const data = await api('?action=counts');
        document.getElementById('v_batches').textContent = data.batches || 0;
        document.getElementById('v_subjects').textContent = data.subjects || 0;
        document.getElementById('v_faculties').textContent = data.faculties || 0;
        document.getElementById('v_classrooms').textContent = data.classrooms || 0;
        document.getElementById('v_slots').textContent = data.timetable_slots || 0;
        document.getElementById('v_fixed').textContent = data.fixed_classes || 0;
      }catch(e){
        console.error(e);
        alert('Could not refresh counts.');
      }
    }

    document.getElementById('refreshCountsBtn').addEventListener('click', refreshCounts);

    // Load options into table
    async function loadOptions(){
      try{
        const rows = await api('?action=list_options&limit=12');
        const tbody = document.querySelector('#optionsTable tbody');
        tbody.innerHTML = '';
        if(!rows.length){
          tbody.innerHTML = '<tr><td colspan="4" style="padding:10px;color:var(--muted)">No options found.</td></tr>';
          return;
        }
        for(const r of rows){
          const tr = document.createElement('tr');
          tr.className = 'clickable';
          tr.dataset.id = r.id;
          tr.innerHTML = `<td>#${r.id}</td><td>${(r.batch_name||'N/A')}</td><td>${Math.round(r.score||0)}</td><td>${(r.created_at||'')}</td>`;
          tbody.appendChild(tr);
        }
      }catch(e){ console.error(e); alert('Could not load options.'); }
    }
    document.getElementById('reloadOptions').addEventListener('click', loadOptions);

    // Delegated click on options table to open modal
    document.querySelector('#optionsTable tbody').addEventListener('click', async function(ev){
      const tr = ev.target.closest('tr.clickable');
      if(!tr) return;
      const id = tr.dataset.id;
      if(!id) return;
      // show modal and fetch content
      const back = document.getElementById('modalBack');
      const body = document.getElementById('modalBody');
      back.style.display = 'flex';
      back.setAttribute('aria-hidden','false');
      body.innerHTML = '<div style="padding:20px;color:var(--muted)">Loading preview…</div>';
      try{
        const html = await api('?action=option_details&id=' + encodeURIComponent(id));
        body.innerHTML = html;
      }catch(e){
        body.innerHTML = '<div style="padding:20px;color:#b23">Could not load preview.</div>';
      }
    });

    document.getElementById('modalClose').addEventListener('click', function(){
      const back = document.getElementById('modalBack');
      back.style.display = 'none';
      back.setAttribute('aria-hidden','true');
    });
    document.getElementById('modalBack').addEventListener('click', function(e){
      if(e.target === this){
        this.style.display = 'none';
        this.setAttribute('aria-hidden','true');
      }
    });

    // Search quick (client-side basic redirect)
    document.getElementById('searchBtn').addEventListener('click', function(){
      const q = document.getElementById('searchInput').value.trim();
      if(!q) return alert('Type a batch or subject name to search.');
      // naive redirect to batches.php with query param if you have search there; else open batches page
      location.href = 'batches.php?search=' + encodeURIComponent(q);
    });
    document.getElementById('searchInput').addEventListener('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); document.getElementById('searchBtn').click(); }});

    // On load: small entrance animations
    window.addEventListener('load', function(){
      document.querySelectorAll('.pop').forEach((el, i)=>{
        el.style.transform = 'translateY(12px)';
        el.style.opacity = '0';
        setTimeout(()=>{ el.style.transition = 'transform .4s cubic-bezier(.2,.9,.3,1), opacity .4s'; el.style.transform='none'; el.style.opacity='1'; }, i*80);
      });
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>
