<?php
/**
 * Universal Review Platform - Upgrade Handler
 * 
 * プラグインのバージョンアップ時の処理を管理
 * データベーススキーマの更新、データ移行等を実行
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * アップグレード処理クラス
 */
class URP_Upgrader {
    
    /**
     * 現在のバージョン
     * @var string
     */
    private static string $current_version;
    
    /**
     * 以前のバージョン
     * @var string
     */
    private static string $previous_version;
    
    /**
     * アップグレード処理を実行
     * PHP 8.0: 戻り値の型宣言
     * @return void
     */
    public static function upgrade(): void {
        self::$current_version = URP_VERSION;
        self::$previous_version = get_option('urp_version', '0.0.0');
        
        // バージョンが同じなら何もしない
        if (version_compare(self::$previous_version, self::$current_version, '>=')) {
            return;
        }
        
        // アップグレード開始
        self::start_upgrade();
        
        // バージョン別の処理を実行
        self::run_version_upgrades();
        
        // データベースのアップグレード
        self::upgrade_database();
        
        // 設定の移行
        self::migrate_settings();
        
        // キャッシュクリア
        self::clear_cache();
        
        // アップグレード完了
        self::complete_upgrade();
    }
    
    /**
     * アップグレード開始処理
     * @return void
     */
    private static function start_upgrade(): void {
        // メンテナンスモード開始
        self::enable_maintenance_mode();
        
        // アップグレード中フラグ
        update_option('urp_upgrading', true);
        update_option('urp_upgrade_started', current_time('mysql'));
        
        // バックアップポイントの作成
        self::create_backup_point();
    }
    
    /**
     * バージョン別のアップグレード処理
     * PHP 8.0: match式
     * @return void
     */
    private static function run_version_upgrades(): void {
        $upgrades = [
            '1.0.1' => 'upgrade_to_1_0_1',
            '1.1.0' => 'upgrade_to_1_1_0',
            '1.2.0' => 'upgrade_to_1_2_0',
            '2.0.0' => 'upgrade_to_2_0_0',
        ];
        
        foreach ($upgrades as $version => $method) {
            if (version_compare(self::$previous_version, $version, '<') && 
                version_compare(self::$current_version, $version, '>=')) {
                if (method_exists(__CLASS__, $method)) {
                    self::$method();
                }
            }
        }
    }
    
    /**
     * バージョン1.0.1へのアップグレード
     * @return void
     */
    private static function upgrade_to_1_0_1(): void {
        global $wpdb;
        
        // インデックスの追加
        $table = $wpdb->prefix . 'urp_review_ratings';
        $wpdb->query("ALTER TABLE $table ADD INDEX idx_rating_value (rating_value)");
        
        // 新しいオプションの追加
        add_option('urp_enable_rich_snippets', true);
        
        self::log_upgrade('1.0.1', 'Added rating value index and rich snippets option');
    }
    
    /**
     * バージョン1.1.0へのアップグレード
     * @return void
     */
    private static function upgrade_to_1_1_0(): void {
        global $wpdb;
        
        // 新しいカラムの追加
        $table = $wpdb->prefix . 'urp_reviewer_ranks';
        $wpdb->query("ALTER TABLE $table ADD COLUMN badge_color VARCHAR(7) DEFAULT '#FFD700' AFTER badges");
        
        // 既存データの移行
        self::migrate_legacy_reviews();
        
        // 新機能の有効化
        update_option('urp_enable_badge_colors', true);
        update_option('urp_enable_advanced_search', true);
        
        self::log_upgrade('1.1.0', 'Added badge colors and advanced search features');
    }
    
    /**
     * バージョン1.2.0へのアップグレード
     * @return void
     */
    private static function upgrade_to_1_2_0(): void {
        // AI機能の初期設定
        add_option('urp_ai_enabled', false);
        add_option('urp_ai_api_key', '');
        add_option('urp_ai_model', 'gpt-3.5-turbo');
        
        // 新しいユーザーロールの追加
        self::add_new_roles();
        
        // パフォーマンス最適化
        self::optimize_database_tables();
        
        self::log_upgrade('1.2.0', 'Added AI features and performance optimizations');
    }
    
    /**
     * バージョン2.0.0へのアップグレード（メジャーアップデート）
     * @return void
     */
    private static function upgrade_to_2_0_0(): void {
        // 大規模な構造変更
        self::restructure_database();
        
        // 設定の完全移行
        self::migrate_all_settings();
        
        // 新しいAPIエンドポイントの登録
        self::register_new_api_endpoints();
        
        // レガシーコードの削除
        self::remove_deprecated_features();
        
        self::log_upgrade('2.0.0', 'Major update with database restructuring');
    }
    
    /**
     * データベースのアップグレード
     * @return void
     */
    private static function upgrade_database(): void {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 現在のDBバージョン取得
        $current_db_version = get_option('urp_db_version', '1.0.0');
        
        // DBバージョンが古い場合のみ実行
        if (version_compare($current_db_version, URP_DB_VERSION, '<')) {
            // テーブル構造の更新
            self::update_table_structures();
            
            // インデックスの最適化
            self::optimize_indexes();
            
            // DBバージョン更新
            update_option('urp_db_version', URP_DB_VERSION);
        }
    }
    
    /**
     * テーブル構造の更新
     * @return void
     */
    private static function update_table_structures(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 各テーブルの構造を確認して必要に応じて更新
        $tables = [
            'urp_review_ratings' => self::get_ratings_table_schema(),
            'urp_review_criteria' => self::get_criteria_table_schema(),
            'urp_review_values' => self::get_values_table_schema(),
            'urp_analytics' => self::get_analytics_table_schema(),
        ];
        
        foreach ($tables as $table_name => $schema) {
            $full_table_name = $wpdb->prefix . $table_name;
            $sql = "CREATE TABLE $full_table_name $schema $charset_collate;";
            dbDelta($sql);
        }
    }
    
    /**
     * 評価テーブルのスキーマ取得
     * @return string
     */
    private static function get_ratings_table_schema(): string {
        return "(
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            review_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            rating_type varchar(50) NOT NULL,
            rating_value decimal(3,2) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_review_id (review_id),
            KEY idx_user_id (user_id),
            KEY idx_rating_type (rating_type),
            KEY idx_rating_value (rating_value),
            KEY idx_created_at (created_at)
        )";
    }
    
    /**
     * 条件テーブルのスキーマ取得
     * @return string
     */
    private static function get_criteria_table_schema(): string {
        return "(
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            review_type varchar(50) NOT NULL,
            criteria_name varchar(100) NOT NULL,
            criteria_key varchar(50) NOT NULL,
            criteria_type enum('rating','text','select','multi','number','date','boolean') DEFAULT 'rating',
            options longtext DEFAULT NULL,
            validation_rules longtext DEFAULT NULL,
            is_required tinyint(1) DEFAULT 0,
            is_searchable tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_type_key (review_type, criteria_key),
            KEY idx_review_type (review_type),
            KEY idx_is_active (is_active)
        )";
    }
    
    /**
     * 値テーブルのスキーマ取得
     * @return string
     */
    private static function get_values_table_schema(): string {
        return "(
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            review_id bigint(20) UNSIGNED NOT NULL,
            criteria_id bigint(20) UNSIGNED NOT NULL,
            value longtext DEFAULT NULL,
            score decimal(3,2) DEFAULT NULL,
            normalized_value varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_review_criteria (review_id, criteria_id),
            KEY idx_criteria_id (criteria_id),
            KEY idx_normalized_value (normalized_value)
        )";
    }
    
    /**
     * アナリティクステーブルのスキーマ取得
     * @return string
     */
    private static function get_analytics_table_schema(): string {
        return "(
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_action varchar(50) DEFAULT NULL,
            event_label varchar(255) DEFAULT NULL,
            event_value int(11) DEFAULT NULL,
            event_data longtext DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            review_id bigint(20) UNSIGNED DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referrer text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_event_action (event_action),
            KEY idx_user_id (user_id),
            KEY idx_review_id (review_id),
            KEY idx_session_id (session_id),
            KEY idx_created_at (created_at)
        )";
    }
    
    /**
     * インデックスの最適化
     * @return void
     */
    private static function optimize_indexes(): void {
        global $wpdb;
        
        // 不要なインデックスの削除と新しいインデックスの追加
        $optimizations = [
            // 複合インデックスの追加
            "ALTER TABLE {$wpdb->prefix}urp_review_ratings 
             ADD INDEX idx_review_user_type (review_id, user_id, rating_type)",
            
            // パフォーマンス向上のためのインデックス
            "ALTER TABLE {$wpdb->prefix}urp_analytics 
             ADD INDEX idx_event_date (event_type, created_at)",
        ];
        
        foreach ($optimizations as $query) {
            // エラーを無視して実行（既にインデックスが存在する場合）
            $wpdb->query($query);
        }
    }
    
    /**
     * 設定の移行
     * PHP 8.0: match式
     * @return void
     */
    private static function migrate_settings(): void {
        // 旧設定名から新設定名へのマッピング
        $migrations = [
            'review_platform_type' => 'urp_review_type',
            'enable_review_ratings' => 'urp_enable_ratings',
            'reviews_per_page' => 'urp_items_per_page',
            'google_maps_key' => 'urp_google_maps_api_key',
        ];
        
        foreach ($migrations as $old_key => $new_key) {
            $old_value = get_option($old_key);
            if ($old_value !== false) {
                update_option($new_key, $old_value);
                delete_option($old_key);
            }
        }
        
        // 設定値の変換
        self::convert_setting_values();
    }
    
    /**
     * 設定値の変換
     * @return void
     */
    private static function convert_setting_values(): void {
        // レビュータイプの変換
        $review_type = get_option('urp_review_type');
        
        // PHP 8.0: match式で変換
        $converted_type = match($review_type) {
            'curry_shop', 'curry_store' => 'curry',
            'ramen_shop', 'ramen_store' => 'ramen',
            'sushi_shop', 'sushi_store' => 'sushi',
            default => $review_type ?: 'curry'
        };
        
        if ($converted_type !== $review_type) {
            update_option('urp_review_type', $converted_type);
        }
    }
    
    /**
     * レガシーレビューの移行
     * @return void
     */
    private static function migrate_legacy_reviews(): void {
        global $wpdb;
        
        // 旧形式のメタデータを新形式に変換
        $legacy_meta_keys = [
            '_review_rating' => 'urp_overall_rating',
            '_review_spiciness' => 'urp_spiciness',
            '_review_price' => 'urp_price_range',
        ];
        
        foreach ($legacy_meta_keys as $old_key => $new_key) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta} 
                SET meta_key = %s 
                WHERE meta_key = %s",
                $new_key,
                $old_key
            ));
        }
    }
    
    /**
     * キャッシュクリア
     * @return void
     */
    private static function clear_cache(): void {
        // WordPressキャッシュ
        wp_cache_flush();
        
        // トランジェント削除
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_urp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_urp_%'");
        
        // オブジェクトキャッシュ
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('urp_reviews');
            wp_cache_delete_group('urp_settings');
        }
    }
    
    /**
     * データベース構造の再構築（メジャーアップデート用）
     * @return void
     */
    private static function restructure_database(): void {
        // 大規模な変更の場合の処理
        // 実装は必要に応じて追加
    }
    
    /**
     * すべての設定を移行
     * @return void
     */
    private static function migrate_all_settings(): void {
        // すべての設定を新形式に移行
        // 実装は必要に応じて追加
    }
    
    /**
     * 新しいAPIエンドポイントを登録
     * @return void
     */
    private static function register_new_api_endpoints(): void {
        // 新バージョンのAPIエンドポイント登録
        // 実装は必要に応じて追加
    }
    
    /**
     * 非推奨機能の削除
     * @return void
     */
    private static function remove_deprecated_features(): void {
        // 古い機能の削除
        delete_option('urp_legacy_mode');
        delete_option('urp_old_api_enabled');
    }
    
    /**
     * 新しいロールの追加
     * @return void
     */
    private static function add_new_roles(): void {
        // 新しいユーザーロールを追加
        if (!get_role('review_analyst')) {
            add_role('review_analyst', __('Review Analyst', 'universal-review'), [
                'read' => true,
                'view_review_analytics' => true,
                'export_review_data' => true,
            ]);
        }
    }
    
    /**
     * データベーステーブルの最適化
     * @return void
     */
    private static function optimize_database_tables(): void {
        global $wpdb;
        
        $tables = [
            'urp_review_ratings',
            'urp_review_criteria',
            'urp_review_values',
            'urp_analytics'
        ];
        
        foreach ($tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $wpdb->query("OPTIMIZE TABLE $full_table");
        }
    }
    
    /**
     * バックアップポイントの作成
     * @return void
     */
    private static function create_backup_point(): void {
        // 重要な設定をバックアップ
        $backup_data = [
            'version' => self::$previous_version,
            'settings' => self::get_all_settings(),
            'timestamp' => current_time('mysql'),
        ];
        
        update_option('urp_upgrade_backup_' . self::$previous_version, $backup_data);
    }
    
    /**
     * すべての設定を取得
     * @return array<string, mixed>
     */
    private static function get_all_settings(): array {
        global $wpdb;
        
        $settings = [];
        $results = $wpdb->get_results(
            "SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'urp_%'",
            ARRAY_A
        );
        
        foreach ($results as $row) {
            $settings[$row['option_name']] = maybe_unserialize($row['option_value']);
        }
        
        return $settings;
    }
    
    /**
     * メンテナンスモードを有効化
     * @return void
     */
    private static function enable_maintenance_mode(): void {
        if (!file_exists(ABSPATH . '.maintenance')) {
            $maintenance_content = '<?php $upgrading = ' . time() . '; ?>';
            file_put_contents(ABSPATH . '.maintenance', $maintenance_content);
        }
    }
    
    /**
     * メンテナンスモードを無効化
     * @return void
     */
    private static function disable_maintenance_mode(): void {
        if (file_exists(ABSPATH . '.maintenance')) {
            @unlink(ABSPATH . '.maintenance');
        }
    }
    
    /**
     * アップグレード完了処理
     * @return void
     */
    private static function complete_upgrade(): void {
        // バージョン更新
        update_option('urp_version', self::$current_version);
        update_option('urp_last_upgrade', current_time('mysql'));
        
        // アップグレード中フラグを削除
        delete_option('urp_upgrading');
        
        // メンテナンスモード解除
        self::disable_maintenance_mode();
        
        // 成功通知を設定
        set_transient('urp_upgrade_success', true, 300);
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // 最終ログ
        self::log_upgrade(self::$current_version, 'Upgrade completed successfully');
    }
    
    /**
     * アップグレードログを記録
     * @param string $version
     * @param string $message
     * @return void
     */
    private static function log_upgrade(string $version, string $message): void {
        $log_entry = [
            'version' => $version,
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'from_version' => self::$previous_version,
        ];
        
        // ログを保存
        $logs = get_option('urp_upgrade_logs', []);
        $logs[] = $log_entry;
        
        // 最新100件のみ保持
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('urp_upgrade_logs', $logs);
    }
}

// アップグレード処理を実行
add_action('admin_init', function() {
    URP_Upgrader::upgrade();
});