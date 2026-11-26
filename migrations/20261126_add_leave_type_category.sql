ALTER TABLE leave_types
    ADD COLUMN category ENUM('dinas', 'non_dinas') NULL AFTER name;

UPDATE leave_types
SET category = CASE
    WHEN LOWER(TRIM(name)) = 'dinas' THEN 'dinas'
    ELSE 'non_dinas'
END;

ALTER TABLE leave_types
    MODIFY COLUMN category ENUM('dinas', 'non_dinas') NOT NULL;
