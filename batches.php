<?php
// batches.php - PLANERA styled batches manager (single-file)
// Place in project root. Uses config.php -> db_connect()

session_start();
require_once 'config.php';
if(!isset($_SESSION['user'])){ header('Location: login.php'); exit; }

$conn = db_connect();

// --- AJAX JSON helper
function json_out($data){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// If AJAX actions requested, handle and exit early
$action = $_REQUEST['action'] ?? null;

if($action === 'list_batch_options' && isset($_GET['batch_id'])){
    $batch_id = intval($_GET['batch_id']);
    $stmt = $conn->prepare('SELECT id, score, created_at, notes FROM timetable_options WHERE batch_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    json_out(['ok'=>true,'rows'=>$rows]);
}

if($action === 'get_batch' && isset($_GET['id'])){
    $id = intval($_GET['id']);
    $stmt = $conn->prepare('SELECT id, department_id, name, shift, year, max_classes_per_day, notes FROM batches WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    json_out(['ok'=>true,'batch'=>$row ?: null]);
}

if($action === 'create_batch' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $department_id = intval($_POST['department_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $shift = $_POST['shift'] ?? 'Morning';
    $year = intval($_POST['year'] ?? 0);
    $max_classes = intval($_POST['max_classes'] ?? 6);
    $notes = trim($_POST['notes'] ?? '');

    if($department_id <= 0 || $name === ''){
        json_out(['ok'=>false,'error'=>'Department and batch name are required.']);
    }
    $stmt = $conn->prepare('INSERT INTO batches (department_id,name,shift,year,max_classes_per_day,notes,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->bind_param('issiis', $department_id, $name, $shift, $year, $max_classes, $notes);
    if($stmt->execute()){
        $id = $stmt->insert_id;
        $stmt->close();
        json_out(['ok'=>true,'id'=>$id]);
    } else {
        $err = $stmt->error; $stmt->close();
        json_out(['ok'=>false,'error'=>$err]);
    }
}

if($action === 'update_batch' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $id = intval($_POST['id'] ?? 0);
    $department_id = intval($_POST['department_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $shift = $_POST['shift'] ?? 'Morning';
    $year = intval($_POST['year'] ?? 0);
    $max_classes = intval($_POST['max_classes'] ?? 6);
    $notes = trim($_POST['notes'] ?? '');

    if($id <= 0 || $department_id <= 0 || $name === ''){
        json_out(['ok'=>false,'error'=>'Invalid input']);
    }
    $stmt = $conn->prepare('UPDATE batches SET department_id=?, name=?, shift=?, year=?, max_classes_per_day=?, notes=? WHERE id=?');
    $stmt->bind_param('issiiis', $department_id, $name, $shift, $year, $max_classes, $notes, $id);
    if($stmt->execute()){
        $stmt->close();
        json_out(['ok'=>true]);
    } else {
        $err = $stmt->error; $stmt->close();
        json_out(['ok'=>false,'error'=>$err]);
    }
}

if($action === 'delete_batch' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $id = intval($_POST['id'] ?? 0);
    if($id <= 0) json_out(['ok'=>false,'error'=>'Invalid id']);
    // Optional: check dependencies here (subjects, options, entries) before deleting in production
    $stmt = $conn->prepare('DELETE FROM batches WHERE id = ?');
    $stmt->bind_param('i', $id);
    if($stmt->execute()){
        $stmt->close();
        json_out(['ok'=>true]);
    } else {
        $err = $stmt->error; $stmt->close();
        json_out(['ok'=>false,'error'=>$err]);
    }
}

// If not AJAX, handle classic form submissions (progressive enhancement)
$errors = [];
$success = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_REQUEST['action'])){
    // this branch supports those using the old non-AJAX form: use the same fields as your original file
    $post_action = $_POST['action'] ?? '';
    if($post_action === 'create'){
        $department_id = intval($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $shift = $_POST['shift'] ?? 'Morning';
        $year = intval($_POST['year'] ?? 0);
        $max_classes = intval($_POST['max_classes'] ?? 6);
        if($department_id <= 0) $errors[] = 'Select department';
        if($name === '') $errors[] = 'Batch name required';
        if(empty($errors)){
            $stmt = $conn->prepare('INSERT INTO batches (department_id,name,shift,year,max_classes_per_day,created_at) VALUES (?,?,?,?,?,NOW())');
            $stmt->bind_param('issii', $department_id, $name, $shift, $year, $max_classes);
            if(!$stmt->execute()) $errors[] = 'Insert failed: '.$stmt->error;
            else $success = 'Batch added';
            $stmt->close();
        }
    } elseif($post_action === 'update'){
        $id = intval($_POST['id'] ?? 0);
        $department_id = intval($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $shift = $_POST['shift'] ?? 'Morning';
        $year = intval($_POST['year'] ?? 0);
        $max_classes = intval($_POST['max_classes'] ?? 6);
        if($id <= 0) $errors[] = 'Invalid id';
        if(empty($errors)){
            $stmt = $conn->prepare('UPDATE batches SET department_id=?, name=?, shift=?, year=?, max_classes_per_day=? WHERE id=?');
            $stmt->bind_param('issiii', $department_id, $name, $shift, $year, $max_classes, $id);
            if(!$stmt->execute()) $errors[] = 'Update failed: '.$stmt->error;
            else $success = 'Batch updated';
            $stmt->close();
        }
    }
}

// Classic delete via GET fallback (keep but prefer AJAX)
if(isset($_GET['delete']) && !isset($_REQUEST['action'])){
    $del = intval($_GET['delete']);
    if($del > 0){
        $stmt = $conn->prepare('DELETE FROM batches WHERE id=?');
        $stmt->bind_param('i', $del);
        if(!$stmt->execute()) $errors[] = 'Delete failed: '.$stmt->error;
        else $success = 'Batch deleted';
        $stmt->close();
    }
}

// Fetch departments & batches for initial render
$depts = $conn->query('SELECT id, code, name FROM departments ORDER BY name');
$batches = $conn->query('SELECT b.id,b.name,b.shift,b.year,b.max_classes_per_day,d.code as dept_code,d.name as dept_name FROM batches b JOIN departments d ON b.department_id = d.id ORDER BY b.id DESC');

// Close DB for now; AJAX endpoints reopened earlier will re-open connection when called.
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PLANERA — Batches</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f6f9fb; --card:#fff; --muted:#64748b; --text:#0b1724;
  --accent-a:#4b6cff; --accent-b:#22c7d8; --accent-grad:linear-gradient(90deg,var(--accent-a),var(--accent-b));
  --glass-border:1px solid #e6eef6; --radius:12px;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Inter',system-ui,Arial;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
.container{max-width:1100px;margin:18px auto;padding:18px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.brand{display:flex;align-items:center;gap:12px}
.brand-badge{width:46px;height:46px;border-radius:10px;background:var(--accent-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-family:'Montserrat'}
.brand-title{font-weight:800;font-family:'Montserrat'}
.content{background:var(--card);border:var(--glass-border);padding:16px;border-radius:12px;box-shadow:0 10px 30px rgba(11,20,26,0.04)}
.controls{display:flex;gap:8px;align-items:center}
.btn{padding:8px 12px;border-radius:10px;border:0;background:var(--accent-grad);color:#021019;font-weight:800;cursor:pointer}
.btn.ghost{background:transparent;border:1px solid #e6eef6;color:var(--text);box-shadow:none}
.grid{display:grid;grid-template-columns:1fr 340px;gap:14px}
.panel{background:#fff;border-radius:10px;padding:12px;border:1px solid #eef6fb}
.list{width:100%;border-collapse:collapse}
.list th,.list td{padding:10px;border-bottom:1px solid #f3f6fb;text-align:left}
.arrow{background:transparent;border:0;font-size:16px;cursor:pointer;padding:6px;border-radius:6px}
.muted{color:var(--muted);font-weight:700}
.toast{position:fixed;right:18px;bottom:18px;background:#0b1220;color:#fff;padding:12px 16px;border-radius:10px;box-shadow:0 10px 30px rgba(2,6,23,0.3);display:none;z-index:9999}
.subpanel{background:#fbfeff;border-radius:8px;padding:8px;border:1px solid #eef6fb;margin-top:8px}
.form-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
label{font-weight:700;color:var(--muted);font-size:13px}
input[type="text"],textarea,select{padding:10px;border-radius:8px;border:1px solid #e6eef6;width:100%;font-weight:700}
.small{font-size:13px;color:var(--muted)}
.modal-back{position:fixed;inset:0;background:rgba(5,9,14,0.45);display:none;align-items:center;justify-content:center;padding:20px;z-index:2000}
.modal{background:#fff;border-radius:10px;padding:18px;max-width:640px;width:100%;box-shadow:0 30px 80px rgba(2,6,23,0.6)}
@media (max-width:920px){ .grid{grid-template-columns:1fr} }
</style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="brand">
        <div class="brand-badge">PL</div>
        <div>
          <div class="brand-title">PLANERA</div>
          <div class="small">Manage Batches</div>
        </div>
      </div>

      <div class="controls">
        <a class="btn ghost" href="index.php">← Dashboard</a>
        <button id="openAdd" class="btn">+ Add Batch</button>
        <a class="btn ghost" href="logout.php">Logout</a>
      </div>
    </div>

    <div class="content">
      <?php if(!empty($success)): ?>
        <div style="padding:12px;border-radius:10px;background:linear-gradient(90deg,rgba(124,92,255,0.05),rgba(77,208,225,0.02));border:1px solid rgba(124,92,255,0.06);margin-bottom:12px;"><?php echo htmlspecialchars($success);?></div>
      <?php endif; ?>
      <?php if(!empty($errors)): ?>
        <div style="padding:12px;border-radius:10px;background:#fff5f5;border:1px solid #ffd0d0;color:#7b1f1f;margin-bottom:12px;"><?php echo htmlspecialchars(implode(' | ',$errors));?></div>
      <?php endif; ?>

      <div class="grid">
        <div class="panel">
          <h3 style="margin-top:0">Batches</h3>
          <div class="small" style="margin-bottom:8px">Click the arrow to view generated timetables for that batch (loads inline).</div>

          <table class="list" id="batchesList" aria-live="polite">
            <thead><tr><th style="width:44px"></th><th>Name</th><th style="width:160px">Actions</th></tr></thead>
            <tbody>
              <?php if($batches && $batches->num_rows): while($r = $batches->fetch_assoc()): ?>
                <tr data-id="<?php echo (int)$r['id']; ?>">
                  <td><button class="arrow" data-id="<?php echo (int)$r['id']; ?>">▶</button></td>
                  <td><?php echo htmlspecialchars($r['dept_code'].' — '.$r['name']); ?></td>
                  <td>
                    <button class="btn ghost edit" data-id="<?php echo (int)$r['id']; ?>">Edit</button>
                    <button class="btn ghost del" data-id="<?php echo (int)$r['id']; ?>">Delete</button>
                  </td>
                </tr>
                <tr class="sub-row-holder" data-for="<?php echo (int)$r['id']; ?>" style="display:none">
                  <td colspan="3"><div class="subpanel" data-loaded="0" data-batch="<?php echo (int)$r['id']; ?>">Loading...</div></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="3" class="muted">No batches found. Add one to get started.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <aside class="panel" id="rightPanel">
          <h4 style="margin-top:0">Batch Preview</h4>
          <div id="batchPreview" class="small muted">Select a batch to see quick actions here.</div>
          <div style="margin-top:12px">
            <button id="refreshBtn" class="btn ghost">Refresh list</button>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <div id="toast" class="toast"></div>

  <!-- modal -->
  <div id="modalBack" class="modal-back" aria-hidden="true">
    <div class="modal" role="document">
      <button id="modalClose" style="float:right;border:0;background:transparent;font-weight:800;cursor:pointer">Close ✕</button>
      <h3 id="modalTitle">Add Batch</h3>
      <form id="batchForm">
        <input type="hidden" id="batch_id" name="id" value="0">
        <div style="margin-top:10px">
          <label>Department</label>
          <select id="department_select" name="department_id" required>
            <option value="">-- Select Department --</option>
            <?php if($depts): while($d = $depts->fetch_assoc()): ?>
              <option value="<?php echo (int)$d['id'];?>"><?php echo htmlspecialchars($d['code'].' — '.$d['name']);?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>

        <div style="margin-top:10px">
          <label>Batch name</label>
          <input id="batch_name" name="name" type="text" placeholder="e.g., CSE-2025-A" required>
        </div>

        <div style="margin-top:10px;display:flex;gap:8px">
          <div style="flex:1">
            <label>Shift</label>
            <select id="batch_shift" name="shift">
              <option>Morning</option><option>Afternoon</option><option>Evening</option>
            </select>
          </div>
          <div style="width:120px">
            <label>Year</label>
            <input id="batch_year" name="year" type="text" placeholder="2025">
          </div>
          <div style="width:160px">
            <label>Max classes/day</label>
            <input id="batch_max" name="max_classes" type="text" value="6">
          </div>
        </div>

        <div style="margin-top:10px">
          <label>Notes (optional)</label>
          <textarea id="batch_notes" name="notes" rows="3" placeholder="Optional notes"></textarea>
        </div>

        <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:8px">
          <button type="button" id="cancelModal" class="btn ghost">Cancel</button>
          <button type="submit" id="saveBatch" class="btn">Save</button>
        </div>
      </form>
    </div>
  </div>

<script>
// helpers
function $q(sel, ctx){ return (ctx||document).querySelector(sel); }
function $qs(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }
function showToast(msg, t=2200){ const el = $q('#toast'); el.textContent = msg; el.style.display='block'; setTimeout(()=>el.style.display='none', t); }
function api(path, opts){ return fetch(path, Object.assign({ credentials:'same-origin' }, opts||{})).then(r=>{ if(!r.ok) throw new Error('Network '+r.status); const ct=r.headers.get('content-type')||''; return ct.indexOf('application/json')!==-1? r.json(): r.text(); }); }

// open modal
function openModal(mode='add', batch=null){
  $q('#modalBack').style.display='flex'; $q('#modalBack').setAttribute('aria-hidden','false');
  $q('#modalTitle').textContent = mode==='edit' ? 'Edit Batch' : 'Add Batch';
  if(mode==='edit' && batch){
    $q('#batch_id').value = batch.id;
    $q('#department_select').value = batch.department_id || '';
    $q('#batch_name').value = batch.name || '';
    $q('#batch_shift').value = batch.shift || 'Morning';
    $q('#batch_year').value = batch.year || '';
    $q('#batch_max').value = batch.max_classes_per_day || '6';
    $q('#batch_notes').value = batch.notes || '';
  } else {
    $q('#batchForm').reset(); $q('#batch_id').value = 0;
  }
}

// close modal
function closeModal(){ $q('#modalBack').style.display='none'; $q('#modalBack').setAttribute('aria-hidden','true'); }

// load batches (AJAX)
async function loadBatches(){
  const tbody = $q('#batchesList tbody');
  tbody.innerHTML = '<tr><td colspan="3" class="small muted">Loading...</td></tr>';
  try{
    const res = await api('?action=list_batches');
    if(res && res.length){
      tbody.innerHTML = '';
      res.forEach(r=>{
        const tr = document.createElement('tr');
        tr.dataset.id = r.id;
        tr.innerHTML = `<td><button class="arrow" data-id="${r.id}">▶</button></td><td>${escapeHtml((r.dept_code||'') + ' — ' + (r.name||''))}</td>
          <td><button class="btn ghost edit" data-id="${r.id}">Edit</button> <button class="btn ghost del" data-id="${r.id}">Delete</button></td>`;
        tbody.appendChild(tr);
        const sub = document.createElement('tr');
        sub.className = 'sub-row-holder';
        sub.dataset.for = r.id;
        sub.style.display = 'none';
        sub.innerHTML = `<td colspan="3"><div class="subpanel" data-loaded="0" data-batch="${r.id}">Loading...</div></td>`;
        tbody.appendChild(sub);
      });
    } else {
      tbody.innerHTML = '<tr><td colspan="3" class="small muted">No batches yet.</td></tr>';
    }
  }catch(err){
    console.error(err);
    tbody.innerHTML = '<tr><td colspan="3" class="small muted">Failed to load batches</td></tr>';
  }
}

// escape
function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

// initial non-AJAX rows are server-rendered; but prefer AJAX refresh
document.addEventListener('click', async function(e){
  // open add modal
  if(e.target.matches('#openAdd')){ openModal('add'); return; }
  if(e.target.matches('#cancelModal') || e.target.matches('#modalClose')){ closeModal(); return; }
  if(e.target.matches('#refreshBtn')){ await loadBatches(); showToast('List refreshed'); return; }

  // delegated: edit
  if(e.target.closest('.edit')){
    const id = e.target.closest('.edit').dataset.id;
    try{
      const res = await api('?action=get_batch&id=' + encodeURIComponent(id));
      if(res.ok && res.batch){
        openModal('edit', res.batch);
      } else {
        alert('Could not load batch details');
      }
    }catch(err){ console.error(err); alert('Network error'); }
    return;
  }

  // delegated: delete
  if(e.target.closest('.del')){
    const id = parseInt(e.target.closest('.del').dataset.id,10);
    if(!confirm('Delete this batch? This will remove the batch record.')) return;
    try{
      const form = new FormData(); form.append('id', id);
      const res = await api('?action=delete_batch', { method:'POST', body: form });
      if(res.ok){ showToast('Batch deleted'); await loadBatches(); $q('#batchPreview').textContent = 'Select a batch to see quick actions here.'; }
      else alert('Delete failed: ' + (res.error || 'unknown'));
    }catch(err){ console.error(err); alert('Delete request failed'); }
    return;
  }

  // delegated: arrow expand list of options for batch
  if(e.target.closest('.arrow')){
    const btn = e.target.closest('.arrow');
    const batchId = btn.dataset.id;
    const holder = document.querySelector('.sub-row-holder[data-for="'+batchId+'"]');
    if(!holder) return;
    const container = holder.querySelector('.subpanel');
    const isOpen = holder.style.display !== 'none' && holder.style.display !== '';
    if(isOpen){ holder.style.display = 'none'; btn.textContent = '▶'; return; }
    holder.style.display = '';
    btn.textContent = '▼';
    if(container.dataset.loaded === '1') return;
    container.innerHTML = '<div class="small muted">Loading...</div>';
    try{
      const res = await api('?action=list_batch_options&batch_id=' + encodeURIComponent(batchId));
      if(res.ok){
        const rows = res.rows;
        if(!rows.length){
          container.innerHTML = '<div class="small muted">No timetables generated for this batch.</div>';
        } else {
          let html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><strong>Generated Timetables</strong><span class="small muted">'+rows.length+' items</span></div>';
          html += '<table style="width:100%;border-collapse:collapse"><thead><tr><th style="padding:6px;text-align:left">Option</th><th style="padding:6px">Score</th><th style="padding:6px">Created</th><th style="padding:6px">Action</th></tr></thead><tbody>';
          rows.forEach(r=>{
            html += '<tr><td style="padding:6px">#'+(r.id||'')+'</td><td style="padding:6px">'+(r.score||'0')+'</td><td style="padding:6px">'+(r.created_at? r.created_at.substr(0,16) : '-')+'</td>';
            html += '<td style="padding:6px"><button class="btn ghost viewOption" data-id="'+r.id+'">View</button></td></tr>';
          });
          html += '</tbody></table>';
          container.innerHTML = html;
        }
        container.dataset.loaded = '1';
      } else {
        container.innerHTML = '<div class="small muted">Failed to load.</div>';
      }
    }catch(err){ console.error(err); container.innerHTML = '<div class="small muted">Failed to load.</div>'; }
    return;
  }

  // delegated: view option (opens viewer in new tab)
  if(e.target.closest('.viewOption')){
    const id = e.target.closest('.viewOption').dataset.id;
    window.open('viewer_option.php?option_id=' + encodeURIComponent(id), '_blank');
    return;
  }

  // clicking server-rendered row to preview in right panel
  if(e.target.closest('tr[data-id]') && !e.target.closest('.edit') && !e.target.closest('.del') && !e.target.closest('.arrow')){
    const id = e.target.closest('tr[data-id]').dataset.id;
    try{
      const res = await api('?action=get_batch&id=' + encodeURIComponent(id));
      if(res.ok && res.batch){
        const b = res.batch;
        let html = `<div style="font-weight:800">${escapeHtml(b.name)}</div>`;
        html += `<div class="small" style="margin-top:6px">Shift: ${escapeHtml(b.shift || '-')}, Year: ${escapeHtml(b.year||'-')}</div>`;
        html += `<div style="margin-top:8px"><button class="btn ghost" onclick="location.href='generate.php?batch_id=${b.id}'">Generate Timetable</button></div>`;
        $q('#batchPreview').innerHTML = html;
      } else $q('#batchPreview').textContent = 'Not found';
    }catch(err){ console.error(err); $q('#batchPreview').textContent = 'Failed to load'; }
    return;
  }
});

// form submit - use AJAX to create/update
$q('#batchForm')?.addEventListener('submit', async function(e){
  e.preventDefault();
  const id = parseInt($q('#batch_id').value || 0, 10);
  const department_id = parseInt($q('#department_select').value || 0, 10);
  const name = $q('#batch_name').value.trim();
  const shift = $q('#batch_shift').value;
  const year = parseInt($q('#batch_year').value || 0, 10);
  const max_classes = parseInt($q('#batch_max').value || 6, 10);
  const notes = $q('#batch_notes').value.trim();
  if(!department_id || !name){ alert('Department and name are required'); return; }
  $q('#saveBatch').disabled = true;
  try{
    const form = new FormData();
    form.append('department_id', department_id);
    form.append('name', name);
    form.append('shift', shift);
    form.append('year', year);
    form.append('max_classes', max_classes);
    form.append('notes', notes);
    let res;
    if(id > 0){
      form.append('id', id);
      res = await api('?action=update_batch', { method:'POST', body: form });
    } else {
      res = await api('?action=create_batch', { method:'POST', body: form });
    }
    if(res.ok){
      showToast(id>0 ? 'Updated' : 'Created');
      closeModal();
      await loadBatches();
    } else {
      alert('Failed: ' + (res.error || 'unknown'));
    }
  }catch(err){ console.error(err); alert('Network error'); }
  $q('#saveBatch').disabled = false;
});

// open add modal btn
$q('#openAdd')?.addEventListener('click', ()=>openModal('add') );
$q('#modalClose')?.addEventListener('click', closeModal);
$q('#cancelModal')?.addEventListener('click', closeModal);

// wire view initial server-rendered edit buttons (in case JS loaded)
$q('.edit').forEach(btn => btn.addEventListener('click', function(){ btn.click(); }));

// load initial batches via AJAX - if you prefer server-rendered, comment this out
(async ()=>{ try{ await loadBatches(); }catch(e){ console.warn(e); } })();

</script>
</body>
</html>
