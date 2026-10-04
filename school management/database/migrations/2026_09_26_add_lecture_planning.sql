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

DROP TRIGGER IF EXISTS validate_timetable_entry_insert;
DROP TRIGGER IF EXISTS validate_timetable_entry_update;

DELIMITER //
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
DELIMITER ;
