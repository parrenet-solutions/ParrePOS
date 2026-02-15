SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pos_sales'
      AND CONSTRAINT_NAME = 'fk_pos_sales_session'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @fk_ref_table := (
    SELECT COALESCE(REFERENCED_TABLE_NAME, '')
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pos_sales'
      AND CONSTRAINT_NAME = 'fk_pos_sales_session'
      AND COLUMN_NAME = 'cash_session_id'
    LIMIT 1
);

SET @drop_fk_sql := IF(
    @fk_exists = 1 AND @fk_ref_table <> 'pos_cash_sessions',
    'ALTER TABLE pos_sales DROP FOREIGN KEY fk_pos_sales_session',
    'SELECT 1'
);
PREPARE drop_fk_stmt FROM @drop_fk_sql;
EXECUTE drop_fk_stmt;
DEALLOCATE PREPARE drop_fk_stmt;

SET @fk_exists_after := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pos_sales'
      AND CONSTRAINT_NAME = 'fk_pos_sales_session'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @add_fk_sql := IF(
    @fk_exists_after = 0,
    'ALTER TABLE pos_sales ADD CONSTRAINT fk_pos_sales_session FOREIGN KEY (cash_session_id) REFERENCES pos_cash_sessions(id)',
    'SELECT 1'
);
PREPARE add_fk_stmt FROM @add_fk_sql;
EXECUTE add_fk_stmt;
DEALLOCATE PREPARE add_fk_stmt;
