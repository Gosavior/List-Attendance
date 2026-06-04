<?php

if (!function_exists('db_schema_table_name_safe')) {
    function db_schema_table_name_safe($table) {
        return is_string($table) && preg_match('/^[A-Za-z0-9_]+$/', $table) === 1;
    }
}

if (!function_exists('db_table_columns')) {
    function db_table_columns($pdo, $table) {
        static $cache = [];

        if (!db_schema_table_name_safe($table)) {
            return [];
        }

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $columns = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['Field'])) {
                    $columns[] = $row['Field'];
                }
            }
        } catch (Exception $e) {
            
            $columns = [];
        }

        $cache[$table] = $columns;
        return $columns;
    }
}

if (!function_exists('db_table_has_column')) {
    function db_table_has_column($pdo, $table, $column) {
        if (!is_string($column) || $column === '') {
            return false;
        }
        $columns = db_table_columns($pdo, $table);
        return in_array($column, $columns, true);
    }
}

if (!function_exists('db_first_existing_column')) {
    function db_first_existing_column($pdo, $table, $candidates) {
        if (!is_array($candidates) || empty($candidates)) {
            return null;
        }

        $columns = db_table_columns($pdo, $table);
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('db_table_exists')) {
    function db_table_exists($pdo, $table) {
        if (!db_schema_table_name_safe($table)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }
}
