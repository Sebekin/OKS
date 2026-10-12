<?php
declare(strict_types=1);

/**
 * Схема базы данных «Учёт ОКС» (п.6.7 ТЗ).
 * Двойная запись: MySQL (боевой хостинг) и SQLite (разработка/тесты).
 * Методы createMySql()/createSqlite() идемпотентны (CREATE TABLE IF NOT EXISTS).
 */
class Schema
{
    /** Таблицы в порядке создания (для drop при откате). */
    public const TABLES = [
        'users', 'user_rights', 'queues', 'queue_members',
        'objects', 'doc_types', 'documents', 'document_files',
        'letters', 'invoices', 'settings', 'audit_log', 'login_log',
        'backup_codes', 'chat_threads', 'chat_participants', 'chat_messages',
        'object_seq',
    ];

    /* ============================ MYSQL ============================ */

    public static function createMySql(PDO $pdo): void
    {
        foreach (self::mysqlStatements() as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function mysqlStatements(): array
    {
        $eng = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                login VARCHAR(50) NOT NULL UNIQUE,
                pass_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(150) DEFAULT '',
                role VARCHAR(20) NOT NULL DEFAULT 'viewer',
                is_active TINYINT NOT NULL DEFAULT 1,
                twofa_enabled TINYINT NOT NULL DEFAULT 0,
                twofa_secret VARCHAR(64) DEFAULT NULL,
                ip_only TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                last_login_at DATETIME DEFAULT NULL
            ) $eng",

            "CREATE TABLE IF NOT EXISTS user_rights (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                level VARCHAR(10) NOT NULL DEFAULT 'hidden',
                UNIQUE KEY uq_user_action (user_id, action),
                CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS queues (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                color VARCHAR(9) NOT NULL DEFAULT '#ffd54f',
                rotation_pos INT NOT NULL DEFAULT 0,
                sort INT NOT NULL DEFAULT 0,
                is_subobject_color TINYINT NOT NULL DEFAULT 0
            ) $eng",

            "CREATE TABLE IF NOT EXISTS queue_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                queue_id INT NOT NULL,
                user_id INT NOT NULL,
                sort INT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_queue_user (queue_id, user_id),
                CONSTRAINT fk_qm_queue FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE,
                CONSTRAINT fk_qm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS objects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT DEFAULT NULL,
                number_in_year INT NOT NULL DEFAULT 0,
                year INT NOT NULL,
                queue_id INT DEFAULT NULL,
                letter_number VARCHAR(100) DEFAULT '',
                letter_date DATE DEFAULT NULL,
                letter_file_id INT DEFAULT NULL,
                address TEXT,
                customer VARCHAR(255) DEFAULT '',
                contract_number VARCHAR(100) DEFAULT '',
                contract_date DATE DEFAULT NULL,
                cost DECIMAL(14,2) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'none',
                pto_mark TINYINT NOT NULL DEFAULT 0,
                pto_date DATE DEFAULT NULL,
                pto_by INT DEFAULT NULL,
                act_mark TINYINT NOT NULL DEFAULT 0,
                act_date DATE DEFAULT NULL,
                act_by INT DEFAULT NULL,
                engineer_id INT DEFAULT NULL,
                note MEDIUMTEXT,
                sub_no INT DEFAULT NULL,
                is_archived TINYINT NOT NULL DEFAULT 0,
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                KEY idx_parent (parent_id),
                KEY idx_customer (customer),
                KEY idx_engineer (engineer_id),
                KEY idx_year_num (year, number_in_year),
                CONSTRAINT fk_obj_parent FOREIGN KEY (parent_id) REFERENCES objects(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS doc_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                multi_file TINYINT NOT NULL DEFAULT 0,
                sort INT NOT NULL DEFAULT 0,
                is_active TINYINT NOT NULL DEFAULT 1
            ) $eng",

            "CREATE TABLE IF NOT EXISTS documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                object_id INT NOT NULL,
                type_id INT DEFAULT NULL,
                comment TEXT,
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_doc_obj (object_id),
                CONSTRAINT fk_doc_obj FOREIGN KEY (object_id) REFERENCES objects(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS document_files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                document_id INT DEFAULT NULL,
                invoice_id INT DEFAULT NULL,
                letter_id INT DEFAULT NULL,
                stored_name VARCHAR(255) NOT NULL,
                orig_name VARCHAR(255) NOT NULL,
                size_bytes BIGINT NOT NULL DEFAULT 0,
                mime VARCHAR(100) DEFAULT '',
                created_at DATETIME NOT NULL,
                KEY idx_df_doc (document_id),
                CONSTRAINT fk_df_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS letters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                object_id INT NOT NULL,
                direction VARCHAR(10) NOT NULL DEFAULT 'in',
                number VARCHAR(100) DEFAULT '',
                ldate DATE DEFAULT NULL,
                counterparty VARCHAR(255) DEFAULT '',
                descr TEXT,
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_let_obj (object_id),
                CONSTRAINT fk_let_obj FOREIGN KEY (object_id) REFERENCES objects(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                object_id INT NOT NULL,
                number VARCHAR(100) NOT NULL DEFAULT '',
                idate DATE DEFAULT NULL,
                amount DECIMAL(14,2) DEFAULT NULL,
                paid TINYINT NOT NULL DEFAULT 0,
                pay_date DATE DEFAULT NULL,
                pay_amount DECIMAL(14,2) DEFAULT NULL,
                pay_comment VARCHAR(255) DEFAULT '',
                file_id INT DEFAULT NULL,
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_inv_obj (object_id),
                CONSTRAINT fk_inv_obj FOREIGN KEY (object_id) REFERENCES objects(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS settings (
                k VARCHAR(50) PRIMARY KEY,
                v TEXT
            ) $eng",

            "CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL,
                user_id INT DEFAULT NULL,
                ip VARCHAR(45) DEFAULT '',
                type VARCHAR(20) NOT NULL,
                action VARCHAR(255) NOT NULL,
                entity VARCHAR(50) DEFAULT '',
                field VARCHAR(50) DEFAULT NULL,
                old_value TEXT,
                new_value TEXT,
                KEY idx_audit_time (created_at),
                KEY idx_audit_entity (entity)
            ) $eng",

            "CREATE TABLE IF NOT EXISTS login_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL,
                login VARCHAR(50) DEFAULT '',
                ip VARCHAR(45) DEFAULT '',
                success TINYINT NOT NULL DEFAULT 0,
                info VARCHAR(255) DEFAULT ''
            ) $eng",

            "CREATE TABLE IF NOT EXISTS backup_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code VARCHAR(64) NOT NULL,
                used TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_bc_user (user_id),
                CONSTRAINT fk_bc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS chat_threads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kind VARCHAR(10) NOT NULL DEFAULT 'dept',
                title VARCHAR(150) DEFAULT '',
                created_at DATETIME NOT NULL
            ) $eng",

            "CREATE TABLE IF NOT EXISTS chat_participants (
                thread_id INT NOT NULL,
                user_id INT NOT NULL,
                last_read_msg_id INT NOT NULL DEFAULT 0,
                PRIMARY KEY (thread_id, user_id),
                CONSTRAINT fk_cp_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
                CONSTRAINT fk_cp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                thread_id INT NOT NULL,
                user_id INT NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_cm_thread (thread_id, id),
                CONSTRAINT fk_cm_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
                CONSTRAINT fk_cm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $eng",

            "CREATE TABLE IF NOT EXISTS object_seq (
                year INT PRIMARY KEY,
                last_num INT NOT NULL DEFAULT 0
            ) $eng",
        ];
    }

    /* ============================ SQLITE ============================ */

    public static function createSqlite(PDO $pdo): void
    {
        foreach (self::sqliteStatements() as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function sqliteStatements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                login TEXT NOT NULL UNIQUE,
                pass_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                email TEXT DEFAULT '',
                role TEXT NOT NULL DEFAULT 'viewer',
                is_active INTEGER NOT NULL DEFAULT 1,
                twofa_enabled INTEGER NOT NULL DEFAULT 0,
                twofa_secret TEXT DEFAULT NULL,
                ip_only TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                last_login_at TEXT DEFAULT NULL
            )",
            "CREATE TABLE IF NOT EXISTS user_rights (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                action TEXT NOT NULL,
                level TEXT NOT NULL DEFAULT 'hidden',
                UNIQUE (user_id, action)
            )",
            "CREATE TABLE IF NOT EXISTS queues (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                color TEXT NOT NULL DEFAULT '#ffd54f',
                rotation_pos INTEGER NOT NULL DEFAULT 0,
                sort INTEGER NOT NULL DEFAULT 0,
                is_subobject_color INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS queue_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue_id INTEGER NOT NULL REFERENCES queues(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                sort INTEGER NOT NULL DEFAULT 0,
                UNIQUE (queue_id, user_id)
            )",
            "CREATE TABLE IF NOT EXISTS objects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER REFERENCES objects(id) ON DELETE CASCADE,
                number_in_year INTEGER NOT NULL DEFAULT 0,
                year INTEGER NOT NULL,
                queue_id INTEGER,
                letter_number TEXT DEFAULT '',
                letter_date TEXT DEFAULT NULL,
                letter_file_id INTEGER DEFAULT NULL,
                address TEXT,
                customer TEXT DEFAULT '',
                contract_number TEXT DEFAULT '',
                contract_date TEXT DEFAULT NULL,
                cost REAL DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'none',
                pto_mark INTEGER NOT NULL DEFAULT 0,
                pto_date TEXT DEFAULT NULL,
                pto_by INTEGER DEFAULT NULL,
                act_mark INTEGER NOT NULL DEFAULT 0,
                act_date TEXT DEFAULT NULL,
                act_by INTEGER DEFAULT NULL,
                engineer_id INTEGER,
                note TEXT,
                sub_no INTEGER DEFAULT NULL,
                is_archived INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER,
                created_at TEXT NOT NULL,
                updated_at TEXT DEFAULT NULL
            )",
            "CREATE TABLE IF NOT EXISTS doc_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                multi_file INTEGER NOT NULL DEFAULT 0,
                sort INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1
            )",
            "CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                object_id INTEGER NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
                type_id INTEGER,
                comment TEXT,
                created_by INTEGER,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS document_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_id INTEGER REFERENCES documents(id) ON DELETE CASCADE,
                invoice_id INTEGER,
                letter_id INTEGER,
                stored_name TEXT NOT NULL,
                orig_name TEXT NOT NULL,
                size_bytes INTEGER NOT NULL DEFAULT 0,
                mime TEXT DEFAULT '',
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS letters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                object_id INTEGER NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
                direction TEXT NOT NULL DEFAULT 'in',
                number TEXT DEFAULT '',
                ldate TEXT DEFAULT NULL,
                counterparty TEXT DEFAULT '',
                descr TEXT,
                created_by INTEGER,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                object_id INTEGER NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
                number TEXT NOT NULL DEFAULT '',
                idate TEXT DEFAULT NULL,
                amount REAL DEFAULT NULL,
                paid INTEGER NOT NULL DEFAULT 0,
                pay_date TEXT DEFAULT NULL,
                pay_amount REAL DEFAULT NULL,
                pay_comment TEXT DEFAULT '',
                file_id INTEGER,
                created_by INTEGER,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)",
            "CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at TEXT NOT NULL,
                user_id INTEGER,
                ip TEXT DEFAULT '',
                type TEXT NOT NULL,
                action TEXT NOT NULL,
                entity TEXT DEFAULT '',
                field TEXT,
                old_value TEXT,
                new_value TEXT
            )",
            "CREATE TABLE IF NOT EXISTS login_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at TEXT NOT NULL,
                login TEXT DEFAULT '',
                ip TEXT DEFAULT '',
                success INTEGER NOT NULL DEFAULT 0,
                info TEXT DEFAULT ''
            )",
            "CREATE TABLE IF NOT EXISTS backup_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                code TEXT NOT NULL,
                used INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS chat_threads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kind TEXT NOT NULL DEFAULT 'dept',
                title TEXT DEFAULT '',
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS chat_participants (
                thread_id INTEGER NOT NULL REFERENCES chat_threads(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                last_read_msg_id INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (thread_id, user_id)
            )",
            "CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                thread_id INTEGER NOT NULL REFERENCES chat_threads(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS object_seq (year INTEGER PRIMARY KEY, last_num INTEGER NOT NULL DEFAULT 0)",
        ];
    }
}
