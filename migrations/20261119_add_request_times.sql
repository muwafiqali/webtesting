ALTER TABLE leave_requests
    ADD COLUMN start_time TIME NOT NULL DEFAULT '00:00:00' AFTER end_date,
    ADD COLUMN end_time TIME NOT NULL DEFAULT '00:00:00' AFTER start_time;
