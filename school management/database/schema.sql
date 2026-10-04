CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_years (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT chk_academic_year_dates CHECK (end_date > start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS standards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    standard_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_batch (standard_id, academic_year_id, name),
    KEY idx_batches_standard (standard_id),
    KEY idx_batches_year (academic_year_id),
    CONSTRAINT fk_batches_standard FOREIGN KEY (standard_id) REFERENCES standards(id) ON DELETE RESTRICT,
    CONSTRAINT fk_batches_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS standard_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    standard_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_standard_subject (standard_id, subject_id),
    KEY idx_standard_subjects_subject (subject_id),
    CONSTRAINT fk_standard_subjects_standard FOREIGN KEY (standard_id) REFERENCES standards(id) ON DELETE CASCADE,
    CONSTRAINT fk_standard_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    admission_no VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    address TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teachers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    employee_no VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_subject (teacher_id, subject_id),
    KEY idx_teacher_subjects_subject (subject_id),
    CONSTRAINT fk_teacher_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecture_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_order SMALLINT UNSIGNED NOT NULL,
    is_teaching_slot BOOLEAN NOT NULL DEFAULT TRUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uq_lecture_slot_order (slot_order),
    CONSTRAINT chk_lecture_slot_times CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO lecture_slots (name, start_time, end_time, slot_order, is_teaching_slot, is_active) VALUES
    ('Period 1', '08:00:00', '08:45:00', 1, TRUE, TRUE),
    ('Period 2', '08:45:00', '09:30:00', 2, TRUE, TRUE),
    ('Period 3', '09:30:00', '10:15:00', 3, TRUE, TRUE),
    ('Recess',   '10:15:00', '10:35:00', 4, FALSE, TRUE),
    ('Period 4', '10:35:00', '11:20:00', 5, TRUE, TRUE),
    ('Period 5', '11:20:00', '12:05:00', 6, TRUE, TRUE),
    ('Period 6', '12:05:00', '12:50:00', 7, TRUE, TRUE)
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    enrollment_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('active', 'completed', 'withdrawn') NOT NULL DEFAULT 'active',
    promoted_from_enrollment_id BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_student_year (student_id, academic_year_id),
    UNIQUE KEY uq_enrollments_promoted_from (promoted_from_enrollment_id),
    KEY idx_enrollments_batch (batch_id),
    KEY idx_enrollments_year (academic_year_id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_enrollments_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_enrollments_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_enrollments_promoted_from FOREIGN KEY (promoted_from_enrollment_id) REFERENCES enrollments(id) ON DELETE SET NULL,
    CONSTRAINT chk_enrollment_dates CHECK (end_date IS NULL OR end_date >= enrollment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timetable_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    slot_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_timetable_class_slot (academic_year_id, batch_id, day_of_week, slot_id),
    UNIQUE KEY uq_timetable_teacher_slot (academic_year_id, teacher_id, day_of_week, slot_id),
    KEY idx_timetable_slot (slot_id),
    KEY idx_timetable_subject (subject_id),
    KEY idx_timetable_teacher_subject (teacher_id, subject_id),
    CONSTRAINT fk_timetable_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_timetable_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_timetable_slot FOREIGN KEY (slot_id) REFERENCES lecture_slots(id) ON DELETE RESTRICT,
    CONSTRAINT fk_timetable_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_timetable_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_timetable_teacher_subject FOREIGN KEY (teacher_id, subject_id) REFERENCES teacher_subjects(teacher_id, subject_id) ON DELETE RESTRICT,
    CONSTRAINT chk_timetable_day CHECK (day_of_week BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecture_occurrences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timetable_entry_id BIGINT UNSIGNED NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    lecture_date DATE NOT NULL,
    slot_id BIGINT UNSIGNED NOT NULL,
    planned_subject_id BIGINT UNSIGNED NOT NULL,
    planned_teacher_id BIGINT UNSIGNED NOT NULL,
    status ENUM('scheduled', 'conducted', 'cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lecture_occurrence_timetable_date (timetable_entry_id, lecture_date),
    UNIQUE KEY uq_lecture_occurrence_batch_slot (batch_id, lecture_date, slot_id),
    KEY idx_lecture_occurrences_teacher_date (planned_teacher_id, lecture_date),
    CONSTRAINT fk_lecture_occurrence_timetable FOREIGN KEY (timetable_entry_id) REFERENCES timetable_entries(id) ON DELETE SET NULL,
    CONSTRAINT fk_lecture_occurrence_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_occurrence_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_occurrence_slot FOREIGN KEY (slot_id) REFERENCES lecture_slots(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_occurrence_subject FOREIGN KEY (planned_subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_occurrence_teacher FOREIGN KEY (planned_teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecture_changes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lecture_occurrence_id BIGINT UNSIGNED NOT NULL,
    change_type ENUM('proxy', 'adjustment') NOT NULL,
    replacement_teacher_id BIGINT UNSIGNED NOT NULL,
    replacement_subject_id BIGINT UNSIGNED NULL,
    counts_towards_subject BOOLEAN NOT NULL,
    reason VARCHAR(500) NULL,
    status ENUM('active', 'superseded', 'cancelled') NOT NULL DEFAULT 'active',
    supersedes_change_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    approved_by_user_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    active_occurrence_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN status = 'active' THEN lecture_occurrence_id ELSE NULL END
    ) STORED,
    UNIQUE KEY uq_lecture_changes_active (active_occurrence_id),
    KEY idx_lecture_changes_teacher (replacement_teacher_id),
    CONSTRAINT fk_lecture_change_occurrence FOREIGN KEY (lecture_occurrence_id) REFERENCES lecture_occurrences(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_teacher FOREIGN KEY (replacement_teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_subject FOREIGN KEY (replacement_subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_supersedes FOREIGN KEY (supersedes_change_id) REFERENCES lecture_changes(id) ON DELETE SET NULL,
    CONSTRAINT fk_lecture_change_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_lecture_change_subject CHECK (
        (change_type = 'proxy' AND replacement_subject_id IS NULL AND counts_towards_subject = FALSE)
        OR (change_type = 'adjustment' AND replacement_subject_id IS NOT NULL AND counts_towards_subject = TRUE)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lecture_occurrence_id BIGINT UNSIGNED NOT NULL UNIQUE,
    marked_by_user_id BIGINT UNSIGNED NOT NULL,
    marked_by_teacher_id BIGINT UNSIGNED NULL,
    marking_role ENUM('teacher', 'admin') NOT NULL,
    status ENUM('draft', 'submitted', 'locked') NOT NULL DEFAULT 'draft',
    opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    submitted_by_user_id BIGINT UNSIGNED NULL,
    locked_at TIMESTAMP NULL,
    locked_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_session_occurrence FOREIGN KEY (lecture_occurrence_id) REFERENCES lecture_occurrences(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_session_marker FOREIGN KEY (marked_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_session_teacher FOREIGN KEY (marked_by_teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_session_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_session_locker FOREIGN KEY (locked_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_session_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    minutes_late SMALLINT UNSIGNED NULL,
    remarks VARCHAR(500) NULL,
    marked_by_user_id BIGINT UNSIGNED NOT NULL,
    marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_updated_by_user_id BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_record_student (attendance_session_id, enrollment_id),
    KEY idx_attendance_records_enrollment (enrollment_id),
    CONSTRAINT fk_attendance_record_session FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_record_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_record_marker FOREIGN KEY (marked_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_record_updater FOREIGN KEY (last_updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_attendance_minutes CHECK (
        (status = 'late' AND minutes_late IS NOT NULL AND minutes_late > 0)
        OR (status <> 'late' AND minutes_late IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_record_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_record_id BIGINT UNSIGNED NOT NULL,
    attendance_session_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    previous_status ENUM('present', 'absent', 'late', 'excused') NULL,
    new_status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    previous_minutes_late SMALLINT UNSIGNED NULL,
    new_minutes_late SMALLINT UNSIGNED NULL,
    previous_remarks VARCHAR(500) NULL,
    new_remarks VARCHAR(500) NULL,
    action ENUM('created', 'changed', 'corrected') NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    change_reason VARCHAR(500) NULL,
    KEY idx_attendance_history_record (attendance_record_id, changed_at),
    CONSTRAINT fk_attendance_history_record FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_history_session FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_history_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homework (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NULL,
    assigned_by_teacher_id BIGINT UNSIGNED NOT NULL,
    lecture_occurrence_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    instructions TEXT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_at DATETIME NOT NULL,
    max_marks DECIMAL(8,2) UNSIGNED NULL,
    submission_type ENUM('online', 'offline', 'both') NOT NULL DEFAULT 'online',
    allow_resubmission BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('draft', 'published', 'closed', 'cancelled') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_homework_batch_due (batch_id, due_at),
    KEY idx_homework_teacher (assigned_by_teacher_id, status),
    KEY idx_homework_subject (subject_id),
    CONSTRAINT fk_homework_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_homework_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_homework_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_homework_teacher FOREIGN KEY (assigned_by_teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_homework_lecture FOREIGN KEY (lecture_occurrence_id) REFERENCES lecture_occurrences(id) ON DELETE SET NULL,
    CONSTRAINT chk_homework_dates CHECK (due_at > assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homework_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homework_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    submitted_by_user_id BIGINT UNSIGNED NOT NULL,
    attempt_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    response_text TEXT NULL,
    status ENUM('draft', 'submitted', 'late', 'returned', 'resubmitted', 'graded', 'exempt') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    marks DECIMAL(8,2) UNSIGNED NULL,
    feedback TEXT NULL,
    graded_by_teacher_id BIGINT UNSIGNED NULL,
    graded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_homework_attempt (homework_id, student_id, attempt_no),
    KEY idx_homework_submission_student (student_id, status),
    CONSTRAINT fk_homework_submission_homework FOREIGN KEY (homework_id) REFERENCES homework(id) ON DELETE CASCADE,
    CONSTRAINT fk_homework_submission_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_homework_submission_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_homework_submission_grader FOREIGN KEY (graded_by_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS standard_fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    standard_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    total_amount_payable DECIMAL(10,2) UNSIGNED NOT NULL,
    UNIQUE KEY uq_standard_fee (standard_id, academic_year_id),
    KEY idx_standard_fees_year (academic_year_id),
    CONSTRAINT fk_standard_fees_standard FOREIGN KEY (standard_id) REFERENCES standards(id) ON DELETE RESTRICT,
    CONSTRAINT fk_standard_fees_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fee_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'card', 'UPI', 'bank_transfer') NOT NULL,
    reference_no VARCHAR(100) NULL,
    receipt_no VARCHAR(100) NOT NULL UNIQUE,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fee_payments_enrollment_date (enrollment_id, payment_date),
    CONSTRAINT fk_fee_payments_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE RESTRICT,
    CONSTRAINT chk_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name) VALUES ('admin'), ('teacher'), ('student')
ON DUPLICATE KEY UPDATE name = VALUES(name);

DROP TRIGGER IF EXISTS validate_enrollment_year_insert;
DROP TRIGGER IF EXISTS validate_enrollment_year_update;
DROP TRIGGER IF EXISTS validate_timetable_entry_insert;
DROP TRIGGER IF EXISTS validate_timetable_entry_update;
DROP TRIGGER IF EXISTS validate_attendance_record_insert;
DROP TRIGGER IF EXISTS validate_attendance_record_update;

DELIMITER //
CREATE TRIGGER validate_enrollment_year_insert BEFORE INSERT ON enrollments
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM batches WHERE id = NEW.batch_id AND academic_year_id = NEW.academic_year_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enrollment academic year must match its batch';
    END IF;
END//

CREATE TRIGGER validate_enrollment_year_update BEFORE UPDATE ON enrollments
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM batches WHERE id = NEW.batch_id AND academic_year_id = NEW.academic_year_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enrollment academic year must match its batch';
    END IF;
END//

CREATE TRIGGER validate_timetable_entry_insert BEFORE INSERT ON timetable_entries
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM batches WHERE id = NEW.batch_id AND academic_year_id = NEW.academic_year_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Timetable academic year must match its batch';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM batches b
        INNER JOIN standard_subjects ss ON ss.standard_id = b.standard_id
        WHERE b.id = NEW.batch_id AND ss.subject_id = NEW.subject_id
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The subject is not assigned to this standard';
    END IF;
END//

CREATE TRIGGER validate_timetable_entry_update BEFORE UPDATE ON timetable_entries
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM batches WHERE id = NEW.batch_id AND academic_year_id = NEW.academic_year_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Timetable academic year must match its batch';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM batches b
        INNER JOIN standard_subjects ss ON ss.standard_id = b.standard_id
        WHERE b.id = NEW.batch_id AND ss.subject_id = NEW.subject_id
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The subject is not assigned to this standard';
    END IF;
END//

CREATE TRIGGER validate_attendance_record_insert BEFORE INSERT ON attendance_records
FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM attendance_sessions ats
        INNER JOIN lecture_occurrences lo ON lo.id = ats.lecture_occurrence_id
        INNER JOIN enrollments e ON e.id = NEW.enrollment_id
        WHERE ats.id = NEW.attendance_session_id
          AND e.batch_id = lo.batch_id
          AND e.enrollment_date <= lo.lecture_date
          AND (e.end_date IS NULL OR e.end_date >= lo.lecture_date)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student was not enrolled in this batch on the lecture date';
    END IF;
END//

CREATE TRIGGER validate_attendance_record_update BEFORE UPDATE ON attendance_records
FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM attendance_sessions ats
        INNER JOIN lecture_occurrences lo ON lo.id = ats.lecture_occurrence_id
        INNER JOIN enrollments e ON e.id = NEW.enrollment_id
        WHERE ats.id = NEW.attendance_session_id
          AND e.batch_id = lo.batch_id
          AND e.enrollment_date <= lo.lecture_date
          AND (e.end_date IS NULL OR e.end_date >= lo.lecture_date)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student was not enrolled in this batch on the lecture date';
    END IF;
END//
DELIMITER ;
