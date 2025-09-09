
-- Dummy data for Smart Classroom & Timetable Scheduler
-- Import this into your `smart_scheduler` database in phpMyAdmin
SET FOREIGN_KEY_CHECKS=0;

USE smart_scheduler;

-- Roles
INSERT IGNORE INTO roles (id, name, description) VALUES
(1,'admin','System administrator'),
(2,'timetabler','Person who generates timetables'),
(3,'faculty','Teaching faculty'),
(4,'reviewer','Approving authority');

-- Departments
INSERT INTO departments (id, code, name, description) VALUES
(1,'CSE','Computer Science and Engineering','CSE Department'),
(2,'ECE','Electronics and Communication Engineering','ECE Department'),
(3,'ME','Mechanical Engineering','ME Department'),
(4,'IT','Information Technology','IT Department');

-- Batches
INSERT INTO batches (id, department_id, name, shift, year, max_classes_per_day) VALUES
(1,1,'CSE - B.Tech - 2025 - A','Morning',2025,6),
(2,1,'CSE - B.Tech - 2025 - B','Afternoon',2025,6),
(3,2,'ECE - B.Tech - 2025 - A','Morning',2025,6),
(4,4,'IT - B.Tech - 2025 - A','Morning',2025,6);

-- Classrooms
INSERT INTO classrooms (id, code, capacity, location, features, is_active) VALUES
(1,'R101',60,'Block A - First Floor','projector,whiteboard',1),
(2,'R102',45,'Block A - First Floor','projector,whiteboard',1),
(3,'LAB_CSE_1',30,'Block B - Lab Floor','computers,lab',1),
(4,'LAB_ECE_1',28,'Block B - Lab Floor','electronics lab,oscilloscope',1),
(5,'R201',40,'Block A - Second Floor','projector',1);

-- Subjects (few for each dept)
INSERT INTO subjects (id, department_id, code, name, lecture_hours_per_week, practical_hours_per_week, required_classrooms, is_elective) VALUES
(1,1,'CSE101','Data Structures',3,2,'lab',0),
(2,1,'CSE102','Discrete Mathematics',3,0,'theory',0),
(3,1,'CSE103','Programming in C',3,2,'lab',0),
(4,2,'ECE101','Circuit Theory',3,1,'lab',0),
(5,2,'ECE102','Signals and Systems',3,0,'theory',0),
(6,3,'ME101','Engineering Mechanics',3,0,'theory',0),
(7,4,'IT101','Database Systems',3,2,'lab',0),
(8,4,'IT102','Web Technologies',3,2,'lab',0);

-- Faculties
INSERT INTO faculties (id, user_id, employee_code, full_name, email, phone, max_classes_per_day, avg_leaves_per_month, is_active) VALUES
(1,NULL,'F001','Dr. Alice Smith','alice.smith@example.com','9000000001',5,1.0,1),
(2,NULL,'F002','Mr. Bob Johnson','bob.johnson@example.com','9000000002',5,0.5,1),
(3,NULL,'F003','Ms. Carol Lee','carol.lee@example.com','9000000003',4,0.8,1),
(4,NULL,'F004','Dr. David Kim','david.kim@example.com','9000000004',5,1.2,1),
(5,NULL,'F005','Prof. Emma Davis','emma.davis@example.com','9000000005',4,0.6,1);

-- faculty_subjects mapping (who can teach what)
INSERT INTO faculty_subjects (faculty_id, subject_id, preference_score) VALUES
(1,1,9),
(1,3,8),
(2,2,7),
(2,1,6),
(3,4,9),
(3,5,7),
(4,6,8),
(5,7,9),
(5,8,8);

-- Timetable slots (6 slots per day, Monday-Saturday) - ids 1..36
INSERT INTO timetable_slots (id, slot_code, day, start_time, end_time, slot_order) VALUES
-- Monday slots 1-6
(1,'MON_09_00','Monday','09:00:00','09:50:00',1),
(2,'MON_10_00','Monday','10:00:00','10:50:00',2),
(3,'MON_11_00','Monday','11:00:00','11:50:00',3),
(4,'MON_13_00','Monday','13:00:00','13:50:00',4),
(5,'MON_14_00','Monday','14:00:00','14:50:00',5),
(6,'MON_15_00','Monday','15:00:00','15:50:00',6),
-- Tuesday slots 7-12
(7,'TUE_09_00','Tuesday','09:00:00','09:50:00',1),
(8,'TUE_10_00','Tuesday','10:00:00','10:50:00',2),
(9,'TUE_11_00','Tuesday','11:00:00','11:50:00',3),
(10,'TUE_13_00','Tuesday','13:00:00','13:50:00',4),
(11,'TUE_14_00','Tuesday','14:00:00','14:50:00',5),
(12,'TUE_15_00','Tuesday','15:00:00','15:50:00',6),
-- Wednesday slots 13-18
(13,'WED_09_00','Wednesday','09:00:00','09:50:00',1),
(14,'WED_10_00','Wednesday','10:00:00','10:50:00',2),
(15,'WED_11_00','Wednesday','11:00:00','11:50:00',3),
(16,'WED_13_00','Wednesday','13:00:00','13:50:00',4),
(17,'WED_14_00','Wednesday','14:00:00','14:50:00',5),
(18,'WED_15_00','Wednesday','15:00:00','15:50:00',6),
-- Thursday slots 19-24
(19,'THU_09_00','Thursday','09:00:00','09:50:00',1),
(20,'THU_10_00','Thursday','10:00:00','10:50:00',2),
(21,'THU_11_00','Thursday','11:00:00','11:50:00',3),
(22,'THU_13_00','Thursday','13:00:00','13:50:00',4),
(23,'THU_14_00','Thursday','14:00:00','14:50:00',5),
(24,'THU_15_00','Thursday','15:00:00','15:50:00',6),
-- Friday slots 25-30
(25,'FRI_09_00','Friday','09:00:00','09:50:00',1),
(26,'FRI_10_00','Friday','10:00:00','10:50:00',2),
(27,'FRI_11_00','Friday','11:00:00','11:50:00',3),
(28,'FRI_13_00','Friday','13:00:00','13:50:00',4),
(29,'FRI_14_00','Friday','14:00:00','14:50:00',5),
(30,'FRI_15_00','Friday','15:00:00','15:50:00',6),
-- Saturday slots 31-36 (optional)
(31,'SAT_09_00','Saturday','09:00:00','09:50:00',1),
(32,'SAT_10_00','Saturday','10:00:00','10:50:00',2),
(33,'SAT_11_00','Saturday','11:00:00','11:50:00',3),
(34,'SAT_13_00','Saturday','13:00:00','13:50:00',4),
(35,'SAT_14_00','Saturday','14:00:00','14:50:00',5),
(36,'SAT_15_00','Saturday','15:00:00','15:50:00',6);

-- Fixed classes (a few forced slots)
INSERT INTO fixed_classes (id, batch_id, subject_id, slot_id, classroom_id, faculty_id, notes, created_by) VALUES
(1,1,2,1,1,2,'Mandatory lecture - Discrete Math',NULL),
(2,1,1,3,3,1,'Lab session: Data Structures',NULL),
(3,3,4,7,4,3,'Circuit Theory practical',NULL);

-- Faculty leaves (example future dates)
INSERT INTO faculty_leaves (id, faculty_id, leave_date, leave_type, approved_by, status) VALUES
(1,1,'2025-03-15','Sick',NULL,'Approved'),
(2,2,'2025-04-02','Casual',NULL,'Approved');

-- Some settings
INSERT INTO settings (id, scope, scope_id, key_name, value_text) VALUES
(1,'global',NULL,'max_classes_per_day_default','6'),
(2,'global',NULL,'working_days','Monday,Tuesday,Wednesday,Thursday,Friday');

-- Optional: sample timetable_option & entries (empty placeholder)
INSERT INTO timetable_options (id, batch_id, generated_by, score, notes) VALUES
(1,1,NULL,0,'Sample placeholder option - run generator to fill entries');

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
