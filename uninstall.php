<?php
/**
 * Universal Review Platform - Uninstall
 * 
 * プラグイン削除時に実行され、すべてのデータと痕跡を完全に削除します
 * PHP 8.0以上対応
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

// WordPressから呼ばれていない場合は終了
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Direct access not permitted.');
}

/**
 * メイン削除クラス
 */
class Universal_Review_Uninstaller {
    
    /**
     * 削除処理を実行
     * PHP 8.0: 戻り値の型宣言
     * @return void
     */
    public static function uninstall(): void {
        global $wpdb;
        
        // マルチサイト対応
        if (is_multisite()) {
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                self::delete_all_data();
                restore_current_blog();
                // レビュータイプ別のクリーンアップ
        $review_type = get_option('urp_review_type', 'curry');
        
        // PHP 8.0: match式で条件分岐
        $type_specific_cleanup = match($review_type) {
            'curry' => function() use ($wpdb) {
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'curry_%'");
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_curry_%'");
            },
            'ramen' => function() use ($wpdb) {
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'ramen_%'");
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ramen_%'");
            },
            'sushi' => function() use ($wpdb) {
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'sushi_%'");
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sushi_%'");
            },
            default => function() {}
        };
        
        $type_specific_cleanup();
        } else {
            self::delete_all_data();
        }
        
        // ネットワーク全体のオプション削除（マルチサイト）
        if (is_multisite()) {
            delete_site_option('urp_network_settings');
            delete_site_option('urp_license');
        }
        
        // 削除ログを記録（オプション）
        self::log_uninstall();
    }
    
    /**
     * すべてのデータを削除
     */
    private static function delete_all_data() {
        // 1. カスタム投稿タイプの投稿をすべて削除
        self::delete_all_reviews();
        
        // 2. カスタムタクソノミーとタームを削除
        self::delete_taxonomies();
        
        // 3. カスタムテーブルを削除
        self::drop_custom_tables();
        
        // 4. オプションを削除
        self::delete_options();
        
        // 5. ユーザーメタを削除
        self::delete_user_meta();
        
        // 6. アップロードされた画像を削除
        self::delete_uploaded_files();
        
        // 7. キャッシュをクリア
        self::clear_caches();
        
        // 8. Cronジョブを削除
        self::remove_cron_jobs();
        
        // 9. カスタム権限を削除
        self::remove_capabilities();
        
        // 10. トランジェントを削除
        self::delete_transients();
    }
    
    /**
     * すべてのレビュー投稿を削除
     */
    private static function delete_all_reviews() {
        global $wpdb;
        
        // 対象の投稿タイプ
        $post_types = ['platform_review'];
        
        // レビュータイプ別の処理
        $review_type = get_option('urp_review_type', 'curry');
        
        foreach ($post_types as $post_type) {
            // 投稿IDを取得
            $post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
                $post_type
            ));
            
            foreach ($post_ids as $post_id) {
                // 添付ファイルも含めて完全削除
                $attachments = get_posts([
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'post_parent' => $post_id,
                    'fields' => 'ids'
                ]);
                
                foreach ($attachments as $attachment_id) {
                    wp_delete_attachment($attachment_id, true);
                }
                
                // 投稿を完全削除（ゴミ箱を経由しない）
                wp_delete_post($post_id, true);
            }
            
            // 孤立したメタデータを削除
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} 
                WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})"
            ));
        }
    }
    
    /**
     * カスタムタクソノミーを削除
     */
    private static function delete_taxonomies() {
        global $wpdb;
        
        $taxonomies = [
            'review_category',
            'review_region',
            'curry_type',
            'spice_level',
            'price_range'
        ];
        
        foreach ($taxonomies as $taxonomy) {
            // タームを取得して削除
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids'
            ]);
            
            if (!is_wp_error($terms)) {
                foreach ($terms as $term_id) {
                    wp_delete_term($term_id, $taxonomy);
                }
            }
            
            // タクソノミーの登録解除
            unregister_taxonomy($taxonomy);
        }
        
        // term_relationshipsテーブルのクリーンアップ
        $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id NOT IN (SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy})");
    }
    
    /**
     * カスタムテーブルを削除
     */
    private static function drop_custom_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'urp_review_ratings',
            $wpdb->prefix . 'urp_review_criteria',
            $wpdb->prefix . 'urp_review_values',
            $wpdb->prefix . 'urp_review_votes',
            $wpdb->prefix . 'urp_reviewer_ranks',
            $wpdb->prefix . 'urp_review_images',
            $wpdb->prefix . 'urp_review_reports',
            $wpdb->prefix . 'urp_analytics'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
    
    /**
     * オプションを削除
     */
    private static function delete_options() {
        global $wpdb;
        
        // 個別オプション削除（新しいプレフィックスに対応）
        $options = [
            'urp_settings',
            'urp_review_type',
            'urp_version',
            'urp_db_version',
            'urp_activated',
            'urp_google_maps_api',
            'urp_cache_settings',
            'urp_enable_ratings',
            'urp_enable_comments',
            'urp_require_approval',
            'urp_enable_maps',
            'urp_items_per_page',
            'urp_cache_enabled',
            'urp_cache_expiry'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // プレフィックスで一括削除
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'urp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_urp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_urp_%'");
    }
    
    /**
     * ユーザーメタを削除
     */
    private static function delete_user_meta() {
        global $wpdb;
        
        $meta_keys = [
            'review_count',
            'reviewer_rank',
            'trust_score',
            'favorite_curries',
            'review_preferences',
            'reviewer_badges',
            'last_review_date'
        ];
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key]);
        }
        
        // プレフィックスで一括削除
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'review_%'");
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'curry_%'");
    }
    
    /**
     * アップロードファイルを削除
     */
    private static function delete_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $review_dirs = [
            $upload_dir['basedir'] . '/reviews',
            $upload_dir['basedir'] . '/curry-reviews',
            $upload_dir['basedir'] . '/review-images'
        ];
        
        foreach ($review_dirs as $dir) {
            if (is_dir($dir)) {
                self::delete_directory($dir);
            }
        }
    }
    
    /**
     * ディレクトリを再帰的に削除
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
    
    /**
     * キャッシュをクリア
     */
    private static function clear_caches() {
        // WordPressキャッシュ
        wp_cache_flush();
        
        // オブジェクトキャッシュ
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('reviews');
            wp_cache_delete_group('curry_reviews');
        }
        
        // Redisキャッシュ（使用している場合）
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                if ($redis->connect('127.0.0.1', 6379)) {
                    $keys = $redis->keys('review_*');
                    if ($keys) {
                        $redis->del($keys);
                    }
                }
            } catch (Exception $e) {
                // Redisが使用できない場合は無視
            }
        }
    }
    
    /**
     * Cronジョブを削除
     */
    private static function remove_cron_jobs() {
        $hooks = [
            'review_daily_aggregation',
            'review_weekly_ranking',
            'review_cleanup_spam',
            'curry_review_digest',
            'review_cache_purge'
        ];
        
        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            
            // すべてのスケジュールをクリア
            wp_clear_scheduled_hook($hook);
        }
    }
    
    /**
     * カスタム権限を削除
     */
    private static function remove_capabilities() {
        global $wp_roles;
        
        $capabilities = [
            'manage_reviews',
            'edit_reviews',
            'delete_reviews',
            'publish_reviews',
            'moderate_reviews',
            'view_review_analytics'
        ];
        
        $roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        
        // カスタムロールを削除
        remove_role('review_moderator');
        remove_role('premium_reviewer');
    }
    
    /**
     * トランジェントを削除
     */
    private static function delete_transients() {
        global $wpdb;
        
        // トランジェントを削除
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_review%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_review%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_curry%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_curry%'");
        
        // サイトトランジェント（マルチサイト）
        if (is_multisite()) {
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_review%'");
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_review%'");
        }
    }
    
    /**
     * アンインストールログを記録
     */
    private static function log_uninstall() {
        $log_data = [
            'uninstalled_at' => current_time('mysql'),
            'uninstalled_by' => get_current_user_id(),
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        ];
        
        // 一時的にログを保存（30日後に自動削除）
        set_transient('review_platform_uninstall_log', $log_data, 30 * DAY_IN_SECONDS);
    }
}

// アンインストール実行
Universal_Review_Uninstaller::uninstall();