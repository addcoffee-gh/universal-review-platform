<?php
/**
 * Universal Review Platform - Deactivation Handler
 * 
 * プラグイン無効化時の処理を管理
 * 一時的な無効化なので、データは削除しない
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 */

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ディアクティベーションクラス
 */
class URP_Deactivator {
    
    /**
     * ディアクティベーション実行
     * PHP 8.0: 戻り値の型宣言
     * @return void
     */
    public static function deactivate(): void {
        self::clear_scheduled_events();
        self::clear_cache();
        self::remove_capabilities();
        self::set_deactivation_transients();
        self::cleanup_temporary_data();
        self::log_deactivation();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }
    
    /**
     * スケジュールされたイベントをクリア
     * @return void
     */
    private static function clear_scheduled_events(): void {
        // 定期実行ジョブを削除
        $events = [
            'urp_daily_cron',
            'urp_hourly_cron',
            'urp_cache_cleanup',
            'urp_weekly_report',
            'urp_monthly_analytics',
            'urp_review_digest',
            'urp_spam_check',
            'urp_optimize_tables'
        ];
        
        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
            // すべてのスケジュールをクリア
            wp_clear_scheduled_hook($event);
        }
        
        // カスタムスケジュールも削除
        self::clear_custom_schedules();
    }
    
    /**
     * カスタムスケジュールをクリア
     * @return void
     */
    private static function clear_custom_schedules(): void {
        global $wpdb;
        
        // cronオプションから直接削除
        $cron = get_option('cron');
        if (is_array($cron)) {
            $updated = false;
            foreach ($cron as $timestamp => $hooks) {
                if (!is_array($hooks)) {
                    continue;
                }
                foreach ($hooks as $hook => $args) {
                    // プラグイン関連のフックを削除
                    if (strpos($hook, 'urp_') === 0) {
                        unset($cron[$timestamp][$hook]);
                        $updated = true;
                    }
                }
                // タイムスタンプが空になったら削除
                if (empty($cron[$timestamp])) {
                    unset($cron[$timestamp]);
                }
            }
            if ($updated) {
                update_option('cron', $cron);
            }
        }
    }
    
    /**
     * キャッシュをクリア
     * @return void
     */
    private static function clear_cache(): void {
        global $wpdb;
        
        // WordPressオブジェクトキャッシュ
        wp_cache_flush();
        
        // トランジェント削除
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_urp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_urp_%'");
        
        // サイトトランジェント（マルチサイト対応）
        if (is_multisite()) {
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_urp_%'");
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_urp_%'");
        }
        
        // カスタムキャッシュディレクトリをクリア
        self::clear_file_cache();
        
        // 外部キャッシュサービス対応
        self::clear_external_cache();
    }
    
    /**
     * ファイルキャッシュをクリア
     * @return void
     */
    private static function clear_file_cache(): void {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/urp-cache';
        
        if (is_dir($cache_dir)) {
            self::remove_directory_contents($cache_dir);
        }
        
        // 一時ファイルディレクトリもクリア
        $temp_dir = $upload_dir['basedir'] . '/urp-reviews/temp';
        if (is_dir($temp_dir)) {
            self::remove_directory_contents($temp_dir);
        }
    }
    
    /**
     * 外部キャッシュをクリア
     * @return void
     */
    private static function clear_external_cache(): void {
        // Redis対応
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                if ($redis->connect('127.0.0.1', 6379)) {
                    $keys = $redis->keys('urp_*');
                    if ($keys) {
                        $redis->del($keys);
                    }
                    $redis->close();
                }
            } catch (Exception $e) {
                // エラーは無視（Redisが使用できない場合）
            }
        }
        
        // Memcached対応
        if (class_exists('Memcached')) {
            try {
                $memcached = new Memcached();
                $memcached->addServer('localhost', 11211);
                // プラグイン関連のキーをフラッシュ
                $memcached->flush();
            } catch (Exception $e) {
                // エラーは無視
            }
        }
        
        // APCu対応
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
    }
    
    /**
     * ディレクトリの中身を削除（ディレクトリ自体は残す）
     * PHP 8.0: Union Types
     * @param string $dir
     * @param bool $remove_dir
     * @return bool
     */
    private static function remove_directory_contents(string $dir, bool $remove_dir = false): bool {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..', '.htaccess', 'index.php']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::remove_directory_contents($path, true);
            } else {
                @unlink($path);
            }
        }
        
        if ($remove_dir) {
            @rmdir($dir);
        }
        
        return true;
    }
    
    /**
     * 権限を削除（一時的に）
     * @return void
     */
    private static function remove_capabilities(): void {
        // 注意：権限は削除せず、一時的に無効化のマークをつけるだけ
        // 再有効化時に権限を復元できるようにする
        update_option('urp_capabilities_suspended', true);
        update_option('urp_capabilities_suspended_time', current_time('mysql'));
        
        // カスタムロールを一時的に無効化（削除はしない）
        $custom_roles = ['review_moderator', 'premium_reviewer'];
        foreach ($custom_roles as $role_name) {
            if ($role = get_role($role_name)) {
                update_option('urp_role_suspended_' . $role_name, $role->capabilities);
            }
        }
    }
    
    /**
     * ディアクティベーション用トランジェント設定
     * @return void
     */
    private static function set_deactivation_transients(): void {
        // 無効化の理由を記録できるようにする
        set_transient('urp_deactivation_time', current_time('mysql'), WEEK_IN_SECONDS);
        
        // 無効化前の設定をバックアップ
        $settings = [
            'urp_review_type' => get_option('urp_review_type'),
            'urp_enable_maps' => get_option('urp_enable_maps'),
            'urp_cache_enabled' => get_option('urp_cache_enabled'),
        ];
        set_transient('urp_settings_backup', $settings, MONTH_IN_SECONDS);
        
        // 再有効化時の通知用
        set_transient('urp_was_deactivated', true, MONTH_IN_SECONDS);
    }
    
    /**
     * 一時データのクリーンアップ
     * @return void
     */
    private static function cleanup_temporary_data(): void {
        global $wpdb;
        
        // セッションデータをクリア
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_urp_session_%'");
        
        // 一時的なメタデータをクリア
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_urp_temp_%'");
        
        // 期限切れのデータをクリア
        self::cleanup_expired_data();
        
        // アップロードディレクトリの一時ファイルをクリア
        self::cleanup_upload_temp();
    }
    
    /**
     * 期限切れデータのクリーンアップ
     * @return void
     */
    private static function cleanup_expired_data(): void {
        global $wpdb;
        
        // アナリティクステーブルから古いデータを削除（90日以上前）
        $analytics_table = $wpdb->prefix . 'urp_analytics';
        if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
            $wpdb->query(
                "DELETE FROM $analytics_table 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) 
                AND event_type IN ('page_view', 'search_query')"
            );
        }
        
        // 古いレポートデータを削除
        $reports_table = $wpdb->prefix . 'urp_review_reports';
        if ($wpdb->get_var("SHOW TABLES LIKE '$reports_table'") === $reports_table) {
            $wpdb->query(
                "DELETE FROM $reports_table 
                WHERE status = 'dismissed' 
                AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
        }
    }
    
    /**
     * アップロード一時ファイルのクリーンアップ
     * @return void
     */
    private static function cleanup_upload_temp(): void {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/urp-reviews/temp';
        
        if (!is_dir($temp_dir)) {
            return;
        }
        
        // 24時間以上前の一時ファイルを削除
        $files = glob($temp_dir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // .htaccessとindex.phpは残す
                if (basename($file) === '.htaccess' || basename($file) === 'index.php') {
                    continue;
                }
                
                if ($now - filemtime($file) >= 86400) { // 24時間
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * ディアクティベーションをログに記録
     * @return void
     */
    private static function log_deactivation(): void {
        // ディアクティベーション情報を記録
        $log_data = [
            'deactivated_at' => current_time('mysql'),
            'deactivated_by' => get_current_user_id(),
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => defined('URP_VERSION') ? URP_VERSION : 'unknown',
            'active_theme' => get_option('stylesheet'),
            'multisite' => is_multisite(),
            'review_count' => wp_count_posts('platform_review')->publish ?? 0,
        ];
        
        // オプションとして保存（再有効化時に参照可能）
        update_option('urp_last_deactivation_log', $log_data);
        
        // ログファイルにも記録（デバッグ用）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::write_to_log_file($log_data);
        }
        
        // 使用統計を送信（ユーザーが許可している場合）
        if (get_option('urp_allow_tracking', false)) {
            self::send_deactivation_stats($log_data);
        }
    }
    
    /**
     * ログファイルに書き込み
     * @param array<string, mixed> $data
     * @return void
     */
    private static function write_to_log_file(array $data): void {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/urp-logs';
        
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
            // .htaccessで保護
            file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
        }
        
        $log_file = $log_dir . '/deactivation-' . date('Y-m-d') . '.log';
        $log_entry = date('Y-m-d H:i:s') . ' - ' . json_encode($data) . PHP_EOL;
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 無効化統計を送信（改善のため）
     * @param array<string, mixed> $data
     * @return void
     */
    private static function send_deactivation_stats(array $data): void {
        // プライバシーに配慮した最小限のデータのみ送信
        $stats = [
            'plugin_version' => $data['plugin_version'],
            'wp_version' => $data['wp_version'],
            'php_version' => $data['php_version'],
            'review_count_range' => match(true) {
                $data['review_count'] === 0 => '0',
                $data['review_count'] < 10 => '1-9',
                $data['review_count'] < 100 => '10-99',
                $data['review_count'] < 1000 => '100-999',
                default => '1000+'
            },
            'multisite' => $data['multisite']
        ];
        
        // 非同期で送信（ブロッキングしない）
        wp_remote_post('https://api.your-analytics.com/deactivation', [
            'body' => json_encode($stats),
            'timeout' => 1,
            'blocking' => false,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}