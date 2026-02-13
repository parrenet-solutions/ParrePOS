-- Compatibilidad para esquemas previos sin applied_at en sync_events
ALTER TABLE sync_events
    ADD COLUMN IF NOT EXISTS applied_at DATETIME NULL AFTER error_message;
