ALTER TABLE enrollments
    ADD COLUMN end_date DATE NULL AFTER enrollment_date,
    ADD CONSTRAINT chk_enrollment_dates CHECK (end_date IS NULL OR end_date >= enrollment_date);

CREATE TABLE lecture_occurrences (
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

CREATE TABLE lecture_changes (
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
    active_occurrence_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN lecture_occurrence_id ELSE NULL END) STORED,
    UNIQUE KEY uq_lecture_changes_active (active_occurrence_id),
    KEY idx_lecture_changes_teacher (replacement_teacher_id),
    CONSTRAINT fk_lecture_change_occurrence FOREIGN KEY (lecture_occurrence_id) REFERENCES lecture_occurrences(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_teacher FOREIGN KEY (replacement_teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_subject FOREIGN KEY (replacement_subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_supersedes FOREIGN KEY (supersedes_change_id) REFERENCES lecture_changes(id) ON DELETE SET NULL,
    CONSTRAINT fk_lecture_change_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lecture_change_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_lecture_change_subject CHECK ((change_type = 'proxy' AND replacement_subject_id IS NULL AND counts_towards_subject = FALSE) OR (change_type = 'adjustment' AND replacement_subject_id IS NOT NULL AND counts_towards_subject = TRUE))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_sessions (
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

CREATE TABLE attendance_records (
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
    CONSTRAINT chk_attendance_minutes CHECK ((status = 'late' AND minutes_late IS NOT NULL AND minutes_late > 0) OR (status <> 'late' AND minutes_late IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_record_history (
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

DELIMITER //
CREATE TRIGGER validate_attendance_record_insert BEFORE INSERT ON attendance_records
FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM attendance_sessions ats
        INNER JOIN lecture_occurrences lo ON lo.id = ats.lecture_occurrence_id
        INNER JOIN enrollments e ON e.id = NEW.enrollment_id
        WHERE ats.id = NEW.attendance_session_id AND e.batch_id = lo.batch_id
          AND e.enrollment_date <= lo.lecture_date AND (e.end_date IS NULL OR e.end_date >= lo.lecture_date)
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
        WHERE ats.id = NEW.attendance_session_id AND e.batch_id = lo.batch_id
          AND e.enrollment_date <= lo.lecture_date AND (e.end_date IS NULL OR e.end_date >= lo.lecture_date)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student was not enrolled in this batch on the lecture date';
    END IF;
END//
DELIMITER ;
