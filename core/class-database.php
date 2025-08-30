<?php
/**
 * Universal Review Platform - Database Manager
 * 
 * データベース操作を管理するクラス
 * クエリビルダー、マイグレーション、最適化等を提供
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

namespace URP\Core;

use wpdb;

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * データベース管理クラス
 */
class URP_Database {
    
    /**
     * WordPressデータベースインスタンス
     * @var wpdb
     */
    private wpdb $wpdb;
    
    /**
     * テーブルプレフィックス
     * @var string
     */
    private string $prefix;
    
    /**
     * カスタムテーブル名
     * @var array<string, string>
     */
    private array $tables = [];
    
    /**
     * クエリログ
     * @var array<array<string, mixed>>
     */
    private array $query_log = [];
    
    /**
     * トランザクション状態
     * @var bool
     */
    private bool $in_transaction = false;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        global $wpdb;
        
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'urp_';
        
        $this->init_tables();
        $this->register_hooks();
    }
    
    /**
     * テーブル初期化
     * @return void
     */
    private function init_tables(): void {
        $this->tables = [
            'ratings' => $this->prefix . 'review_ratings',
            'criteria' => $this->prefix . 'review_criteria',
            'values' => $this->prefix . 'review_values',
            'votes' => $this->prefix . 'review_votes',
            'ranks' => $this->prefix . 'reviewer_ranks',
            'images' => $this->prefix . 'review_images',
            'reports' => $this->prefix . 'review_reports',
            'analytics' => $this->prefix . 'analytics',
        ];
    }
    
    /**
     * テーブル名取得
     * @param string $name
     * @return string
     */
    public function table(string $name): string {
        return $this->tables[$name] ?? $this->prefix . $name;
    }
    
    /**
     * SELECT クエリ実行
     * PHP 8.0: Union Types, 名前付き引数
     * @param string $table
     * @param array<string> $columns
     * @param array<string, mixed> $where
     * @param array<string, mixed> $options
     * @return array<array<string, mixed>>|null
     */
    public function select(
        string $table,
        array $columns = ['*'],
        array $where = [],
        array $options = []
    ): ?array {
        $table_name = $this->table($table);
        $columns_str = implode(', ', $columns);
        
        // WHERE句構築
        $where_clause = $this->build_where_clause($where);
        
        // クエリ構築
        $query = "SELECT {$columns_str} FROM {$table_name}";
        
        if ($where_clause['sql']) {
            $query .= " WHERE " . $where_clause['sql'];
        }
        
        // ORDER BY
        if (!empty($options['order_by'])) {
            $query .= " ORDER BY " . $this->sanitize_order_by($options['order_by']);
        }
        
        // LIMIT
        if (!empty($options['limit'])) {
            $query .= $this->wpdb->prepare(" LIMIT %d", $options['limit']);
            
            if (!empty($options['offset'])) {
                $query .= $this->wpdb->prepare(" OFFSET %d", $options['offset']);
            }
        }
        
        // クエリ実行
        if ($where_clause['values']) {
            $query = $this->wpdb->prepare($query, ...$where_clause['values']);
        }
        
        $this->log_query($query);
        
        return $this->wpdb->get_results($query, ARRAY_A) ?: null;
    }
    
    /**
     * 単一行取得
     * @param string $table
     * @param array<string> $columns
     * @param array<string, mixed> $where
     * @return array<string, mixed>|null
     */
    public function get(
        string $table,
        array $columns = ['*'],
        array $where = []
    ): ?array {
        $result = $this->select($table, $columns, $where, ['limit' => 1]);
        return $result[0] ?? null;
    }
    
    /**
     * INSERT 実行
     * @param string $table
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function insert(string $table, array $data): int|false {
        $table_name = $this->table($table);
        
        $result = $this->wpdb->insert(
            $table_name,
            $data,
            $this->get_format_array($data)
        );
        
        $this->log_query($this->wpdb->last_query);
        
        return $result !== false ? $this->wpdb->insert_id : false;
    }
    
    /**
     * 複数行INSERT
     * @param string $table
     * @param array<array<string, mixed>> $rows
     * @return int
     */
    public function insert_batch(string $table, array $rows): int {
        if (empty($rows)) {
            return 0;
        }
        
        $table_name = $this->table($table);
        $first_row = reset($rows);
        $columns = array_keys($first_row);
        $columns_str = implode(', ', array_map([$this, 'escape_identifier'], $columns));
        
        $values_parts = [];
        $all_values = [];
        
        foreach ($rows as $row) {
            $placeholders = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $all_values[] = $value;
                $placeholders[] = $this->get_placeholder($value);
            }
            $values_parts[] = '(' . implode(', ', $placeholders) . ')';
        }
        
        $values_str = implode(', ', $values_parts);
        $query = "INSERT INTO {$table_name} ({$columns_str}) VALUES {$values_str}";
        
        if ($all_values) {
            $query = $this->wpdb->prepare($query, ...$all_values);
        }
        
        $this->log_query($query);
        
        $result = $this->wpdb->query($query);
        
        return $result !== false ? $result : 0;
    }
    
    /**
     * UPDATE 実行
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @return int|false
     */
    public function update(
        string $table,
        array $data,
        array $where
    ): int|false {
        $table_name = $this->table($table);
        
        $result = $this->wpdb->update(
            $table_name,
            $data,
            $where,
            $this->get_format_array($data),
            $this->get_format_array($where)
        );
        
        $this->log_query($this->wpdb->last_query);
        
        return $result;
    }
    
    /**
     * UPSERT (INSERT ON DUPLICATE KEY UPDATE)
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<string> $unique_keys
     * @return int|false
     */
    public function upsert(
        string $table,
        array $data,
        array $unique_keys = []
    ): int|false {
        $table_name = $this->table($table);
        
        // INSERT部分
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_map([$this, 'get_placeholder'], $values);
        
        $columns_str = implode(', ', array_map([$this, 'escape_identifier'], $columns));
        $placeholders_str = implode(', ', $placeholders);
        
        // UPDATE部分
        $update_parts = [];
        foreach ($columns as $column) {
            if (!in_array($column, $unique_keys, true)) {
                $update_parts[] = sprintf(
                    '%s = VALUES(%s)',
                    $this->escape_identifier($column),
                    $this->escape_identifier($column)
                );
            }
        }
        $update_str = implode(', ', $update_parts);
        
        $query = "INSERT INTO {$table_name} ({$columns_str}) VALUES ({$placeholders_str})
                  ON DUPLICATE KEY UPDATE {$update_str}";
        
        $query = $this->wpdb->prepare($query, ...$values);
        
        $this->log_query($query);
        
        $result = $this->wpdb->query($query);
        
        return $result !== false ? $this->wpdb->insert_id ?: $result : false;
    }
    
    /**
     * DELETE 実行
     * @param string $table
     * @param array<string, mixed> $where
     * @return int|false
     */
    public function delete(string $table, array $where): int|false {
        $table_name = $this->table($table);
        
        $result = $this->wpdb->delete(
            $table_name,
            $where,
            $this->get_format_array($where)
        );
        
        $this->log_query($this->wpdb->last_query);
        
        return $result;
    }
    
    /**
     * カウント取得
     * @param string $table
     * @param array<string, mixed> $where
     * @return int
     */
    public function count(string $table, array $where = []): int {
        $table_name = $this->table($table);
        $where_clause = $this->build_where_clause($where);
        
        $query = "SELECT COUNT(*) FROM {$table_name}";
        
        if ($where_clause['sql']) {
            $query .= " WHERE " . $where_clause['sql'];
        }
        
        if ($where_clause['values']) {
            $query = $this->wpdb->prepare($query, ...$where_clause['values']);
        }
        
        $this->log_query($query);
        
        return (int) $this->wpdb->get_var($query);
    }
    
    /**
     * 存在確認
     * @param string $table
     * @param array<string, mixed> $where
     * @return bool
     */
    public function exists(string $table, array $where): bool {
        return $this->count($table, $where) > 0;
    }
    
    /**
     * トランザクション開始
     * @return bool
     */
    public function begin_transaction(): bool {
        if ($this->in_transaction) {
            return false;
        }
        
        $result = $this->wpdb->query('START TRANSACTION');
        
        if ($result !== false) {
            $this->in_transaction = true;
            return true;
        }
        
        return false;
    }
    
    /**
     * トランザクションコミット
     * @return bool
     */
    public function commit(): bool {
        if (!$this->in_transaction) {
            return false;
        }
        
        $result = $this->wpdb->query('COMMIT');
        
        if ($result !== false) {
            $this->in_transaction = false;
            return true;
        }
        
        return false;
    }
    
    /**
     * トランザクションロールバック
     * @return bool
     */
    public function rollback(): bool {
        if (!$this->in_transaction) {
            return false;
        }
        
        $result = $this->wpdb->query('ROLLBACK');
        
        if ($result !== false) {
            $this->in_transaction = false;
            return true;
        }
        
        return false;
    }
    
    /**
     * トランザクション実行
     * PHP 8.0: Callableの型指定
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    public function transaction(callable $callback): mixed {
        $this->begin_transaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * カスタムクエリ実行
     * @param string $query
     * @param array<mixed> $params
     * @return mixed
     */
    public function query(string $query, array $params = []): mixed {
        if ($params) {
            $query = $this->wpdb->prepare($query, ...$params);
        }
        
        $this->log_query($query);
        
        // クエリタイプを判定
        $query_type = $this->get_query_type($query);
        
        return match($query_type) {
            'SELECT' => $this->wpdb->get_results($query, ARRAY_A),
            'INSERT', 'UPDATE', 'DELETE' => $this->wpdb->query($query),
            default => $this->wpdb->query($query)
        };
    }
    
    /**
     * スキーマ取得
     * @param string $table
     * @return array<array<string, mixed>>
     */
    public function get_schema(string $table): array {
        $table_name = $this->table($table);
        
        $query = "DESCRIBE {$table_name}";
        
        return $this->wpdb->get_results($query, ARRAY_A) ?: [];
    }
    
    /**
     * インデックス情報取得
     * @param string $table
     * @return array<array<string, mixed>>
     */
    public function get_indexes(string $table): array {
        $table_name = $this->table($table);
        
        $query = "SHOW INDEX FROM {$table_name}";
        
        return $this->wpdb->get_results($query, ARRAY_A) ?: [];
    }
    
    /**
     * テーブル最適化
     * @param string $table
     * @return bool
     */
    public function optimize_table(string $table): bool {
        $table_name = $this->table($table);
        
        $result = $this->wpdb->query("OPTIMIZE TABLE {$table_name}");
        
        return $result !== false;
    }
    
    /**
     * テーブル修復
     * @param string $table
     * @return bool
     */
    public function repair_table(string $table): bool {
        $table_name = $this->table($table);
        
        $result = $this->wpdb->query("REPAIR TABLE {$table_name}");
        
        return $result !== false;
    }
    
    /**
     * テーブルが存在するか確認
     * @param string $table
     * @return bool
     */
    public function table_exists(string $table): bool {
        $table_name = $this->table($table);
        
        $query = $this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        );
        
        return $this->wpdb->get_var($query) === $table_name;
    }
    
    /**
     * カラムが存在するか確認
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function column_exists(string $table, string $column): bool {
        $table_name = $this->table($table);
        
        $query = $this->wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            $column
        );
        
        return !empty($this->wpdb->get_results($query));
    }
    
    /**
     * インデックスが存在するか確認
     * @param string $table
     * @param string $index
     * @return bool
     */
    public function index_exists(string $table, string $index): bool {
        $indexes = $this->get_indexes($table);
        
        foreach ($indexes as $idx) {
            if ($idx['Key_name'] === $index) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * カラム追加
     * @param string $table
     * @param string $column
     * @param string $definition
     * @return bool
     */
    public function add_column(string $table, string $column, string $definition): bool {
        if ($this->column_exists($table, $column)) {
            return true;
        }
        
        $table_name = $this->table($table);
        
        $query = "ALTER TABLE {$table_name} ADD COLUMN {$column} {$definition}";
        
        $result = $this->wpdb->query($query);
        
        return $result !== false;
    }
    
    /**
     * カラム変更
     * @param string $table
     * @param string $column
     * @param string $definition
     * @return bool
     */
    public function modify_column(string $table, string $column, string $definition): bool {
        $table_name = $this->table($table);
        
        $query = "ALTER TABLE {$table_name} MODIFY COLUMN {$column} {$definition}";
        
        $result = $this->wpdb->query($query);
        
        return $result !== false;
    }
    
    /**
     * カラム削除
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function drop_column(string $table, string $column): bool {
        if (!$this->column_exists($table, $column)) {
            return true;
        }
        
        $table_name = $this->table($table);
        
        $query = "ALTER TABLE {$table_name} DROP COLUMN {$column}";
        
        $result = $this->wpdb->query($query);
        
        return $result !== false;
    }
    
    /**
     * インデックス追加
     * @param string $table
     * @param string $index
     * @param array<string>|string $columns
     * @param string $type
     * @return bool
     */
    public function add_index(
        string $table,
        string $index,
        array|string $columns,
        string $type = 'INDEX'
    ): bool {
        if ($this->index_exists($table, $index)) {
            return true;
        }
        
        $table_name = $this->table($table);
        $columns_str = is_array($columns) ? implode(', ', $columns) : $columns;
        
        $query = "ALTER TABLE {$table_name} ADD {$type} {$index} ({$columns_str})";
        
        $result = $this->wpdb->query($query);
        
        return $result !== false;
    }
    
    /**
     * インデックス削除
     * @param string $table
     * @param string $index
     * @return bool
     */
    public function drop_index(string $table, string $index): bool {
        if (!$this->index_exists($table, $index)) {
            return true;
        }
        
        $table_name = $this->table($table);
        
        $query = "ALTER TABLE {$table_name} DROP INDEX {$index}";
        
        $result = $this->wpdb->query($query);
        
        return $result !== false;
    }
    
    /**
     * WHERE句構築
     * @param array<string, mixed> $conditions
     * @return array{sql: string, values: array<mixed>}
     */
    private function build_where_clause(array $conditions): array {
        if (empty($conditions)) {
            return ['sql' => '', 'values' => []];
        }
        
        $sql_parts = [];
        $values = [];
        
        foreach ($conditions as $key => $value) {
            // 演算子付きの場合
            if (preg_match('/^(.+)\s+(=|!=|<>|>|>=|<|<=|LIKE|IN|NOT IN)$/i', $key, $matches)) {
                $column = $matches[1];
                $operator = strtoupper($matches[2]);
                
                if ($operator === 'IN' || $operator === 'NOT IN') {
                    if (is_array($value)) {
                        $placeholders = array_fill(0, count($value), $this->get_placeholder($value[0]));
                        $sql_parts[] = "{$column} {$operator} (" . implode(', ', $placeholders) . ")";
                        $values = array_merge($values, $value);
                    }
                } else {
                    $sql_parts[] = "{$column} {$operator} " . $this->get_placeholder($value);
                    $values[] = $value;
                }
            } else {
                // 通常の等価比較
                if ($value === null) {
                    $sql_parts[] = "{$key} IS NULL";
                } else {
                    $sql_parts[] = "{$key} = " . $this->get_placeholder($value);
                    $values[] = $value;
                }
            }
        }
        
        return [
            'sql' => implode(' AND ', $sql_parts),
            'values' => $values
        ];
    }
    
    /**
     * データ型に応じたフォーマット配列取得
     * @param array<string, mixed> $data
     * @return array<string>
     */
    private function get_format_array(array $data): array {
        $formats = [];
        
        foreach ($data as $value) {
            $formats[] = $this->get_placeholder($value);
        }
        
        return $formats;
    }
    
    /**
     * プレースホルダー取得
     * PHP 8.0: match式
     * @param mixed $value
     * @return string
     */
    private function get_placeholder(mixed $value): string {
        return match(true) {
            is_int($value) => '%d',
            is_float($value) => '%f',
            is_null($value) => 'NULL',
            default => '%s'
        };
    }
    
    /**
     * 識別子のエスケープ
     * @param string $identifier
     * @return string
     */
    private function escape_identifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    /**
     * ORDER BY句のサニタイズ
     * @param string|array<string, string> $order_by
     * @return string
     */
    private function sanitize_order_by(string|array $order_by): string {
        if (is_string($order_by)) {
            return preg_replace('/[^a-zA-Z0-9_,.\s]/', '', $order_by);
        }
        
        $parts = [];
        foreach ($order_by as $column => $direction) {
            $column = preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $parts[] = "{$column} {$direction}";
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * クエリタイプ取得
     * @param string $query
     * @return string
     */
    private function get_query_type(string $query): string {
        $query = trim($query);
        $first_word = strtoupper(explode(' ', $query)[0]);
        
        return $first_word;
    }
    
    /**
     * クエリログ記録
     * @param string $query
     * @return void
     */
    private function log_query(string $query): void {
        if (!defined('URP_DEBUG') || !URP_DEBUG) {
            return;
        }
        
        $this->query_log[] = [
            'query' => $query,
            'time' => microtime(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        // ログファイルに書き込み
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[URP Database] ' . $query);
        }
    }
    
    /**
     * クエリログ取得
     * @return array<array<string, mixed>>
     */
    public function get_query_log(): array {
        return $this->query_log;
    }
    
    /**
     * 最後のエラー取得
     * @return string
     */
    public function get_last_error(): string {
        return $this->wpdb->last_error;
    }
    
    /**
     * フック登録
     * @return void
     */
    private function register_hooks(): void {
        // デバッグモードの場合、クエリログを出力
        if (defined('URP_DEBUG') && URP_DEBUG) {
            add_action('shutdown', [$this, 'output_query_log']);
        }
    }
    
    /**
     * クエリログ出力
     * @return void
     */
    public function output_query_log(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $total_queries = count($this->query_log);
        
        if ($total_queries > 0) {
            echo "<!-- URP Database Queries: {$total_queries} -->\n";
            
            if (isset($_GET['show_queries'])) {
                echo "<!--\n";
                foreach ($this->query_log as $index => $log) {
                    echo "Query #{$index}: {$log['query']}\n";
                }
                echo "-->\n";
            }
        }
    }
}