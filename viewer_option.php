<?php
// viewer_option.php - display a saved timetable option as a tabular grid
session_start();
require_once 'config.php';
if(!isset($_SESSION['user'])){ header('Location: login.php'); exit; }

$conn = db_connect();

// helper days ordering
$all_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

// fetch available timetable options for selection dropdown
$optRes = $conn->query('SELECT topts.id, topts.batch_id, topts.score, topts.created_at, b.name AS batch_name
                        FROM timetable_options topts
                        LEFT JOIN batches b ON topts.batch_id = b.id
                        ORDER BY topts.created_at DESC LIMIT 200');
$options = $optRes ? $optRes->fetch_all(MYSQLI_ASSOC) : [];

// determine option_id (GET or POST)
$option_id = isset($_REQUEST['option_id']) ? intval($_REQUEST['option_id']) : 0;
$data = null;
$error = null;

if($option_id > 0){
    // fetch option and batch
    $stmt = $conn->prepare('SELECT topts.id, topts.batch_id, topts.score, topts.created_at, b.name AS batch_name
                            FROM timetable_options topts
                            LEFT JOIN batches b ON topts.batch_id = b.id
                            WHERE topts.id = ? LIMIT 1');
    $stmt->bind_param('i', $option_id);
    $stmt->execute();
    $opt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if(!$opt){
        $error = "Timetable option with id {$option_id} not found.";
    } else {
        // get slot template: distinct slot_order with time labels (ordered)
        $slotsTpl = [];
        $slotStmt = $conn->query('SELECT slot_order, MIN(start_time) AS start_time, MAX(end_time) AS end_time
                                 FROM timetable_slots
                                 GROUP BY slot_order
                                 ORDER BY slot_order');
        if($slotStmt){
            $slotRows = $slotStmt->fetch_all(MYSQLI_ASSOC);
            foreach($slotRows as $s){
                $order = intval($s['slot_order']);
                $slotsTpl[$order] = [
                    'slot_order' => $order,
                    'label' => (isset($s['start_time']) ? substr($s['start_time'],0,5) : '') . ' - ' . (isset($s['end_time']) ? substr($s['end_time'],0,5) : '')
                ];
            }
        }

        // find which days actually have slots defined
        $daysRes = $conn->query('SELECT DISTINCT day FROM timetable_slots');
        $daysList = [];
        if($daysRes){
            $drows = $daysRes->fetch_all(MYSQLI_ASSOC);
            $allowedDays = array_map(function($r){ return $r['day']; }, $drows);
            // keep canonical order but only those present
            foreach($all_days as $d) if(in_array($d, $allowedDays)) $daysList[] = $d;
        } else {
            // fallback to Monday..Friday
            $daysList = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
        }

        // fetch all entries for this option (join subject, faculty, classroom, slot info)
        $q = "SELECT te.id, te.day, te.slot_id, ts.slot_order, ts.slot_code, ts.start_time, ts.end_time,
                     te.batch_id, te.subject_id, s.code AS subject_code, s.name AS subject_name,
                     te.faculty_id, f.full_name AS faculty_name,
                     te.classroom_id, c.code AS classroom_code,
                     te.is_fixed
              FROM timetable_entries te
              LEFT JOIN timetable_slots ts ON te.slot_id = ts.id
              LEFT JOIN subjects s ON te.subject_id = s.id
              LEFT JOIN faculties f ON te.faculty_id = f.id
              LEFT JOIN classrooms c ON te.classroom_id = c.id
              WHERE te.option_id = ?
              ORDER BY ts.slot_order, te.day";
        $stmt = $conn->prepare($q);
        $stmt->bind_param('i', $option_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $entries = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        // build grid: grid[day][slot_order] = entry-array
        $grid = [];
        foreach($daysList as $d) $grid[$d] = [];
        foreach($entries as $e){
            $d = $e['day'] ?? 'Monday';
            $order = intval($e['slot_order'] ?? 0);
            if(!isset($grid[$d])) $grid[$d] = [];
            $grid[$d][$order] = $e;
        }

        // ensure we have slot columns sorted
        $slotOrders = array_keys($slotsTpl);
        sort($slotOrders, SORT_NUMERIC);

        // Prepare final data
        $data = [
            'option' => $opt,
            'slot_template' => $slotsTpl,
            'days' => $daysList,
            'grid' => $grid,
            'slot_orders' => $slotOrders
        ];
    }
}

$conn->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>View Timetable Option</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    table.tt { width:100%; border-collapse: collapse; margin-top:12px }
    table.tt th, table.tt td { border:1px solid #e6e9ef; padding:8px; vertical-align: top; text-align:left }
    table.tt th { background:#f0f6fb }
    .subject { font-weight:600; display:block }
    .meta { font-size:13px; color:#333; display:block; margin-top:4px }
    .fixed-badge { display:inline-block; background:#ffd; color:#666; border:1px solid #f0e6b6; padding:2px 6px; border-radius:4px; font-size:12px; margin-left:6px }
    .empty { color:#999 }
    .controls { margin:10px 0 }
  </style>
</head>
<body>
<header><a href="index.php">← Back</a> | View Timetable Option</header>
<main class="container">
  <section class="content">
    <h2>View Timetable</h2>

    <div class="controls">
      <form method="get" style="display:inline">
        <label>Select option:</label>
        <select name="option_id" onchange="this.form.submit()">
          <option value="">-- choose generated option --</option>
          <?php foreach($options as $o): ?>
            <option value="<?php echo (int)$o['id'];?>" <?php if(isset($option_id) && $option_id == $o['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars("ID {$o['id']} — Batch: ".($o['batch_name'] ?? 'N/A')." — score: {$o['score']} — ".substr($o['created_at'],0,19)); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if($error): ?>
      <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if($data): ?>
      <h3>Option #<?php echo (int)$data['option']['id']; ?> — Batch: <?php echo htmlspecialchars($data['option']['batch_name']); ?></h3>
      <p>Score: <strong><?php echo htmlspecialchars($data['option']['score']); ?></strong> — Created: <?php echo htmlspecialchars($data['option']['created_at']); ?></p>

      <table class="tt">
        <thead>
          <tr>
            <th style="width:160px">Day / Slot</th>
            <?php foreach($data['slot_orders'] as $so): 
                $lbl = $data['slot_template'][$so]['label'] ?? "Slot {$so}";
            ?>
              <th><?php echo 'Slot '.(int)$so; ?><br><small><?php echo htmlspecialchars($lbl); ?></small></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach($data['days'] as $day): ?>
            <tr>
              <td style="font-weight:700"><?php echo htmlspecialchars($day); ?></td>
              <?php foreach($data['slot_orders'] as $so): 
                  $cell = $data['grid'][$day][$so] ?? null;
                  if(!$cell){
                     echo '<td class="empty">-</td>';
                  } else {
                     $sub = htmlspecialchars(($cell['subject_code'] ? $cell['subject_code'].' - ' : '').($cell['subject_name'] ?? ''));
                     $fac = htmlspecialchars($cell['faculty_name'] ?? '');
                     $room = htmlspecialchars($cell['classroom_code'] ?? '');
                     $fixed = intval($cell['is_fixed']) ? '<span class="fixed-badge">Fixed</span>' : '';
                     echo '<td>';
                     echo '<span class="subject">'.$sub.'</span>';
                     if($fac) echo '<span class="meta">Faculty: '.$fac.'</span>';
                     if($room) echo '<span class="meta">Room: '.$room.'</span>';
                     echo $fixed;
                     echo '</td>';
                  }
                endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    <?php else: ?>
      <p class="empty">No option selected. Choose a generated timetable option from the dropdown above.</p>
    <?php endif; ?>

  </section>
</main>
</body>
</html>
