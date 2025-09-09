<?php
// generate.php - generate timetable and display it immediately in a tabular grid
session_start();
require 'config.php';
if(!isset($_SESSION['user'])){ header('Location: login.php'); exit; }

$conn = db_connect();
$batches = $conn->query('SELECT id,name FROM batches');

$errorMsg = null;
$info = null;
$gridData = null;   // will hold data for the table view
$slotOrders = [];  // columns

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'])){
    $batch_id = intval($_POST['batch_id']);
    if($batch_id <= 0){
        $errorMsg = 'Invalid batch selected.';
    } else {
        // ensure batch exists
        $stmt = $conn->prepare('SELECT id, department_id, name FROM batches WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $batch_id);
        $stmt->execute();
        $batch = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if(!$batch){
            $errorMsg = 'Batch not found.';
        } else {
            // Start generation
            $generated_by = $_SESSION['user']['id'] ?? null;
            $notes = 'Auto-generated (single-file generator)';
            // Create timetable_options row
            $insOpt = $conn->prepare('INSERT INTO timetable_options (batch_id, generated_by, score, notes) VALUES (?,?,?,?)');
            if(!$insOpt){
                $errorMsg = 'DB prepare failed for options: ' . $conn->error;
            } else {
                $initialScore = 0.0;
                $insOpt->bind_param('iids', $batch_id, $generated_by, $initialScore, $notes); // int,int,double,string
                if(!$insOpt->execute()){
                    $errorMsg = 'Could not create timetable option: ' . $insOpt->error;
                    $insOpt->close();
                } else {
                    $option_id = $insOpt->insert_id;
                    $insOpt->close();

                    // Copy fixed_classes -> timetable_entries (is_fixed=1)
                    $selFixed = $conn->prepare('SELECT subject_id, slot_id, classroom_id, faculty_id FROM fixed_classes WHERE batch_id = ?');
                    $selFixed->bind_param('i', $batch_id);
                    $selFixed->execute();
                    $fixedRes = $selFixed->get_result();
                    $fixed = $fixedRes ? $fixedRes->fetch_all(MYSQLI_ASSOC) : [];
                    $selFixed->close();

                    // Prepare helpers
                    $slotDayStmt = $conn->prepare('SELECT day FROM timetable_slots WHERE id = ? LIMIT 1');
                    $insertStmt = $conn->prepare('INSERT INTO timetable_entries (option_id, day, slot_id, batch_id, subject_id, faculty_id, classroom_id, duration_slots, is_fixed) VALUES (?,?,?,?,?,?,?,?,?)');
                    if(!$insertStmt){
                        $errorMsg = 'Prepare failed for inserting entries: ' . $conn->error;
                    } else {
                        // Insert fixed entries
                        foreach($fixed as $f){
                            $slot_id = intval($f['slot_id']);
                            $slotDayStmt->bind_param('i', $slot_id);
                            $slotDayStmt->execute();
                            $sd = $slotDayStmt->get_result()->fetch_assoc();
                            $day = $sd['day'] ?? 'Monday';

                            $subject_id = intval($f['subject_id']);
                            // ensure variables (may be null)
                            $faculty_id = (isset($f['faculty_id']) && $f['faculty_id'] !== null) ? intval($f['faculty_id']) : null;
                            $classroom_id = (isset($f['classroom_id']) && $f['classroom_id'] !== null) ? intval($f['classroom_id']) : null;
                            $duration = 1;
                            $is_fixed = 1;

                            // bind variables (no expressions)
                            $opt_var = $option_id;
                            $day_var = $day;
                            $slot_var = $slot_id;
                            $batch_var = $batch_id;
                            $sub_var = $subject_id;
                            $fac_var = $faculty_id;
                            $room_var = $classroom_id;
                            $dur_var = $duration;
                            $fix_var = $is_fixed;

                            // types: i s i i i i i i i
                            $insertStmt->bind_param('isiiiiiii', $opt_var, $day_var, $slot_var, $batch_var, $sub_var, $fac_var, $room_var, $dur_var, $fix_var);
                            @ $insertStmt->execute(); // ignore execute errors for fixed entries (e.g., duplicates)
                        }

                        // Now greedy fill remaining slots
                        // All slots ordered
                        $slotsRes = $conn->query('SELECT id, day, slot_order FROM timetable_slots ORDER BY FIELD(day, "Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), slot_order');
                        $slots = $slotsRes ? $slotsRes->fetch_all(MYSQLI_ASSOC) : [];

                        // Subjects for this batch's department
                        $subStmt = $conn->prepare('SELECT id FROM subjects WHERE department_id = ?');
                        $subStmt->bind_param('i', $batch['department_id']);
                        $subStmt->execute();
                        $subRes = $subStmt->get_result();
                        $subjects = $subRes ? $subRes->fetch_all(MYSQLI_ASSOC) : [];
                        $subStmt->close();
                        if(empty($subjects)){
                            // fallback: any subjects
                            $sres = $conn->query('SELECT id FROM subjects LIMIT 50');
                            $subjects = $sres ? $sres->fetch_all(MYSQLI_ASSOC) : [];
                        }
                        $subjectIds = array_column($subjects, 'id');

                        if(empty($subjectIds)){
                            $errorMsg = 'No subjects found to schedule. Add subjects first.';
                        } else {
                            // Classrooms list
                            $crs = $conn->query('SELECT id FROM classrooms WHERE is_active=1 ORDER BY capacity DESC');
                            $classrooms = $crs ? array_column($crs->fetch_all(MYSQLI_ASSOC), 'id') : [];

                            // Prepare check if slot filled for this option+batch
                            $checkStmt = $conn->prepare('SELECT COUNT(*) as cnt FROM timetable_entries WHERE option_id=? AND batch_id=? AND slot_id=?');
                            // Prepare faculty selector for a subject
                            $facultyStmt = $conn->prepare('SELECT f.id FROM faculty_subjects fs JOIN faculties f ON f.id=fs.faculty_id WHERE fs.subject_id = ? AND f.is_active=1 LIMIT 1');
                            // Prepare classroom check
                            $roomCheckStmt = $conn->prepare('SELECT COUNT(*) as rcnt FROM timetable_entries WHERE option_id=? AND slot_id=? AND classroom_id=?');

                            // Loop each slot and try to fill
                            foreach($slots as $slot){
                                $slot_id = intval($slot['id']);

                                // skip if slot already has an entry for this batch in this option
                                $checkStmt->bind_param('iii', $option_id, $batch_id, $slot_id);
                                $checkStmt->execute();
                                $c = $checkStmt->get_result()->fetch_assoc();
                                $cnt = intval($c['cnt'] ?? 0);
                                if($cnt > 0) continue;

                                // pick a subject (random)
                                $sub_id = $subjectIds[array_rand($subjectIds)];
                                $sub_var = intval($sub_id); // <-- make a real variable for bind_param

                                // pick a faculty if exists
                                $facultyStmt->bind_param('i', $sub_var);
                                $facultyStmt->execute();
                                $frow = $facultyStmt->get_result()->fetch_assoc();
                                $faculty_id = $frow['id'] ?? null;
                                $fac_var = $faculty_id; // variable to bind (may be null)

                                // pick classroom not already used in this option for this slot
                                $chosen_classroom = null;
                                foreach($classrooms as $crid){
                                    $roomCheckStmt->bind_param('iii', $option_id, $slot_id, $crid);
                                    $roomCheckStmt->execute();
                                    $rr = $roomCheckStmt->get_result()->fetch_assoc();
                                    $rcnt = intval($rr['rcnt'] ?? 0);
                                    if($rcnt === 0){
                                        $chosen_classroom = $crid;
                                        break;
                                    }
                                }
                                $room_var = $chosen_classroom; // may be null

                                // day string
                                $day_var = $slot['day'];
                                $dur_var = 1;
                                $fix_var = 0;

                                // variables for bind
                                $opt_var = $option_id;
                                $slot_var = $slot_id;
                                $batch_var = $batch_id;

                                // Insert entry (use variables only)
                                $insertStmt->bind_param('isiiiiiii', $opt_var, $day_var, $slot_var, $batch_var, $sub_var, $fac_var, $room_var, $dur_var, $fix_var);
                                @ $insertStmt->execute(); // ignore expected non-fatal errors
                            } // end slots loop

                            // Close helper stmts
                            $checkStmt->close();
                            $facultyStmt->close();
                            $roomCheckStmt->close();
                        } // end else subjects non-empty

                        // Close prepared stmts used for insert & slot day
                        $insertStmt->close();
                        $slotDayStmt->close();

                        // 5) compute score as number of entries created for this option
                        $cntStmt = $conn->prepare('SELECT COUNT(*) as c FROM timetable_entries WHERE option_id = ?');
                        $cntStmt->bind_param('i', $option_id);
                        $cntStmt->execute();
                        $cRes = $cntStmt->get_result()->fetch_assoc();
                        $countEntries = intval($cRes['c'] ?? 0);
                        $cntStmt->close();

                        $score = floatval($countEntries);
                        $upd = $conn->prepare('UPDATE timetable_options SET score = ? WHERE id = ?');
                        $upd->bind_param('di', $score, $option_id);
                        $upd->execute();
                        $upd->close();

                        // Build info
                        $info = [
                            'option_id' => $option_id,
                            'batch_id' => $batch_id,
                            'batch_name' => $batch['name'],
                            'score' => $score,
                            'entries_created' => $countEntries
                        ];

                        // Now fetch entries for this option to render grid immediately
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

                        // Build slot template (slot_order => label)
                        $slotsTpl = [];
                        $slotStmt = $conn->query('SELECT slot_order, MIN(start_time) AS start_time, MAX(end_time) AS end_time FROM timetable_slots GROUP BY slot_order ORDER BY slot_order');
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

                        // Days present in timetable_slots (canonical order)
                        $all_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                        $daysRes = $conn->query('SELECT DISTINCT day FROM timetable_slots');
                        $daysList = [];
                        if($daysRes){
                            $drows = $daysRes->fetch_all(MYSQLI_ASSOC);
                            $allowedDays = array_map(function($r){ return $r['day']; }, $drows);
                            foreach($all_days as $d) if(in_array($d, $allowedDays)) $daysList[] = $d;
                        } else {
                            $daysList = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
                        }

                        // build grid map day -> slot_order -> entry
                        $grid = [];
                        foreach($daysList as $d) $grid[$d] = [];
                        foreach($entries as $e){
                            $d = $e['day'] ?? 'Monday';
                            $order = intval($e['slot_order'] ?? 0);
                            if(!isset($grid[$d])) $grid[$d] = [];
                            $grid[$d][$order] = $e;
                        }

                        $gridData = [
                            'option' => $info,
                            'slot_template' => $slotsTpl,
                            'days' => $daysList,
                            'grid' => $grid,
                            'slot_orders' => array_keys($slotsTpl)
                        ];
                    } // end else insertstmt ok
                } // end else created option
            } // end else insOpt prepared
        } // end else batch exists
    } // end else batch valid
} // end POST

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Generate Timetable</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    pre { background:#f6f8fa;padding:10px;border-radius:6px; }
    .notice{padding:8px;border-radius:6px;margin-bottom:8px}
    .notice.error{background:#ffecec;border:1px solid #ffb7b7}
    .notice.ok{background:#e6ffed;border:1px solid #b6f2c6}
    table.tt { width:100%; border-collapse: collapse; margin-top:12px }
    table.tt th, table.tt td { border:1px solid #e6e9ef; padding:8px; vertical-align: top; text-align:left }
    table.tt th { background:#f0f6fb }
    .subject { font-weight:600; display:block }
    .meta { font-size:13px; color:#333; display:block; margin-top:4px }
    .fixed-badge { display:inline-block; background:#ffd; color:#666; border:1px solid #f0e6b6; padding:2px 6px; border-radius:4px; font-size:12px; margin-left:6px }
    .empty { color:#999 }
  </style>
</head>
<body>
<header><a href="index.php">← Back</a> | Generate Timetable</header>
<main class="container"><section class="content">
  <h2>Generate Timetable (single-file)</h2>

  <form method="post">
    <select name="batch_id" required>
      <option value="">-- Select batch --</option>
      <?php while($b = $batches->fetch_assoc()): ?>
        <option value="<?php echo $b['id'];?>" <?php if(isset($batch_id) && $batch_id == $b['id']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($b['name']);?>
        </option>
      <?php endwhile; ?>
    </select>
    <button type="submit">Generate</button>
  </form>

  <?php if($errorMsg): ?>
    <div class="notice error"><strong>Error:</strong> <?php echo htmlspecialchars($errorMsg); ?></div>
  <?php endif; ?>

  <?php if($gridData): ?>
    <div class="notice ok">
      Timetable option created: <strong><?php echo (int)$gridData['option']['option_id']; ?></strong>
      for batch <strong><?php echo htmlspecialchars($gridData['option']['batch_name']); ?></strong>.
      Entries created: <strong><?php echo (int)$gridData['option']['entries_created']; ?></strong>.
      Score: <strong><?php echo htmlspecialchars($gridData['option']['score']); ?></strong>.
    </div>

    <table class="tt">
      <thead>
        <tr>
          <th style="width:160px">Day / Slot</th>
          <?php
            $orders = $gridData['slot_orders'];
            sort($orders, SORT_NUMERIC);
            foreach($orders as $so):
              $lbl = $gridData['slot_template'][$so]['label'] ?? "Slot {$so}";
          ?>
            <th><?php echo 'Slot '.(int)$so; ?><br><small><?php echo htmlspecialchars($lbl); ?></small></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($gridData['days'] as $day): ?>
          <tr>
            <td style="font-weight:700"><?php echo htmlspecialchars($day); ?></td>
            <?php foreach($orders as $so):
                $cell = $gridData['grid'][$day][$so] ?? null;
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
  <?php elseif($info): ?>
    <!-- generation succeeded but no entries (edge case) -->
    <div class="notice ok">Timetable option created (ID <?php echo (int)$info['option_id']; ?>) but no entries were generated.</div>
  <?php endif; ?>

</section></main>
</body>
</html>
<?php $conn->close(); ?>
