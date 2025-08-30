<?php
/**
* Universal Review Platform - Activation Handler
* 
* プラグイン有効化時の処理を管理
* 店舗/物販テーブル分離、評価項目動的管理対応
* 
* @package UniversalReviewPlatform
* @since 1.0.0
*/

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
   exit;
}

/**
* アクティベーションクラス
*/
class URP_Activator {
   
   /**
    * アクティベーション実行
    */
   public static function activate(): void {
       self::check_requirements();
       self::create_database_tables();
       self::set_default_options();
       self::create_default_content();
       self::setup_roles_and_capabilities();
       self::schedule_events();
       self::create_upload_directories();
       self::set_activation_transients();
       
       // アクティベーション完了フラグ
       update_option('urp_activation_time', current_time('mysql'));
       update_option('urp_version', URP_VERSION);
       
       // リライトルールをフラッシュ
       flush_rewrite_rules();
   }
   
   /**
    * 要件チェック
    */
   private static function check_requirements(): void {
       // PHP バージョンチェック
       if (version_compare(PHP_VERSION, URP_MINIMUM_PHP_VERSION, '<')) {
           deactivate_plugins(plugin_basename(URP_PLUGIN_DIR . 'universal-review-platform.php'));
           wp_die(sprintf(
               'Universal Review Platform requires PHP %s or higher. Your server is running PHP %s.',
               URP_MINIMUM_PHP_VERSION,
               PHP_VERSION
           ));
       }
       
       // WordPress バージョンチェック
       if (version_compare(get_bloginfo('version'), URP_MINIMUM_WP_VERSION, '<')) {
           deactivate_plugins(plugin_basename(URP_PLUGIN_DIR . 'universal-review-platform.php'));
           wp_die(sprintf(
               'Universal Review Platform requires WordPress %s or higher. You are running WordPress %s.',
               URP_MINIMUM_WP_VERSION,
               get_bloginfo('version')
           ));
       }
       
       // 必要な拡張機能チェック
       $required_extensions = ['json', 'mysqli', 'mbstring'];
       $missing_extensions = [];
       
       foreach ($required_extensions as $ext) {
           if (!extension_loaded($ext)) {
               $missing_extensions[] = $ext;
           }
       }
       
       if (!empty($missing_extensions)) {
           deactivate_plugins(plugin_basename(URP_PLUGIN_DIR . 'universal-review-platform.php'));
           wp_die(sprintf(
               'Universal Review Platform requires the following PHP extensions: %s',
               implode(', ', $missing_extensions)
           ));
       }
   }
   
   /**
    * データベーステーブル作成
    */
   private static function create_database_tables(): void {
       global $wpdb;
       
       $charset_collate = $wpdb->get_charset_collate();
       require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
       
       // ========================================
       // 基本テーブル（汎用）
       // ========================================
       
       // 1. レビュー評価テーブル（汎用評価）
       $table_ratings = $wpdb->prefix . 'urp_review_ratings';
       $sql_ratings = "CREATE TABLE IF NOT EXISTS $table_ratings (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           review_id bigint(20) UNSIGNED NOT NULL,
           user_id bigint(20) UNSIGNED DEFAULT NULL,
           rating_type varchar(50) NOT NULL,
           rating_value decimal(3,2) NOT NULL,
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           KEY idx_review_id (review_id),
           KEY idx_user_id (user_id),
           KEY idx_rating_type (rating_type),
           KEY idx_created_at (created_at)
       ) $charset_collate;";
       dbDelta($sql_ratings);
       
       // 2. レビュー投票テーブル
       $table_votes = $wpdb->prefix . 'urp_review_votes';
       $sql_votes = "CREATE TABLE IF NOT EXISTS $table_votes (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           review_id bigint(20) UNSIGNED NOT NULL,
           user_id bigint(20) UNSIGNED DEFAULT NULL,
           ip_address varchar(45) DEFAULT NULL,
           vote_type enum('helpful','not_helpful','spam') DEFAULT 'helpful',
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           UNIQUE KEY idx_user_review_vote (user_id, review_id, vote_type),
           KEY idx_review_id (review_id),
           KEY idx_vote_type (vote_type)
       ) $charset_collate;";
       dbDelta($sql_votes);
       
       // 3. レビュアーランクテーブル
       $table_ranks = $wpdb->prefix . 'urp_reviewer_ranks';
       $sql_ranks = "CREATE TABLE IF NOT EXISTS $table_ranks (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           user_id bigint(20) UNSIGNED NOT NULL,
           review_count int(11) DEFAULT 0,
           average_rating decimal(3,2) DEFAULT NULL,
           trust_score decimal(5,2) DEFAULT 0,
           rank_level varchar(50) DEFAULT 'beginner',
           badges longtext DEFAULT NULL,
           achievements longtext DEFAULT NULL,
           last_review_date datetime DEFAULT NULL,
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           UNIQUE KEY idx_user_id (user_id),
           KEY idx_rank_level (rank_level),
           KEY idx_trust_score (trust_score)
       ) $charset_collate;";
       dbDelta($sql_ranks);
       
       // 4. レビュー画像テーブル
       $table_images = $wpdb->prefix . 'urp_review_images';
       $sql_images = "CREATE TABLE IF NOT EXISTS $table_images (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           review_id bigint(20) UNSIGNED NOT NULL,
           attachment_id bigint(20) UNSIGNED DEFAULT NULL,
           image_url varchar(500) NOT NULL,
           thumbnail_url varchar(500) DEFAULT NULL,
           caption text DEFAULT NULL,
           is_primary tinyint(1) DEFAULT 0,
           sort_order int(11) DEFAULT 0,
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           KEY idx_review_id (review_id),
           KEY idx_is_primary (is_primary)
       ) $charset_collate;";
       dbDelta($sql_images);
       
       // 5. レビューレポートテーブル
       $table_reports = $wpdb->prefix . 'urp_review_reports';
       $sql_reports = "CREATE TABLE IF NOT EXISTS $table_reports (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           review_id bigint(20) UNSIGNED NOT NULL,
           reporter_id bigint(20) UNSIGNED DEFAULT NULL,
           report_type varchar(50) NOT NULL,
           report_reason text DEFAULT NULL,
           status enum('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
           admin_notes text DEFAULT NULL,
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           resolved_at datetime DEFAULT NULL,
           PRIMARY KEY (id),
           KEY idx_review_id (review_id),
           KEY idx_status (status),
           KEY idx_report_type (report_type)
       ) $charset_collate;";
       dbDelta($sql_reports);
       
       // 6. アナリティクステーブル
       $table_analytics = $wpdb->prefix . 'urp_analytics';
       $sql_analytics = "CREATE TABLE IF NOT EXISTS $table_analytics (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           event_type varchar(50) NOT NULL,
           event_data longtext DEFAULT NULL,
           user_id bigint(20) UNSIGNED DEFAULT NULL,
           review_id bigint(20) UNSIGNED DEFAULT NULL,
           ip_address varchar(45) DEFAULT NULL,
           user_agent text DEFAULT NULL,
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           KEY idx_event_type (event_type),
           KEY idx_user_id (user_id),
           KEY idx_review_id (review_id),
           KEY idx_created_at (created_at)
       ) $charset_collate;";
       dbDelta($sql_analytics);
       
       // ========================================
       // 店舗レビュー用テーブル
       // ========================================
       
       $table_shop_reviews = $wpdb->prefix . 'urp_shop_reviews';
       $sql_shop = "CREATE TABLE IF NOT EXISTS $table_shop_reviews (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           post_id bigint(20) UNSIGNED NOT NULL,
           shop_name varchar(255),
           address text,
           lat decimal(10,8),
           lng decimal(11,8),
           phone varchar(50),
           hours text,
           holiday text,
           seats int,
           parking tinyint(1) DEFAULT 0,
           credit_card tinyint(1) DEFAULT 0,
           electronic_money tinyint(1) DEFAULT 0,
           wifi tinyint(1) DEFAULT 0,
           smoking varchar(50),
           website varchar(255),
           overall_rating decimal(3,2),
           service_rating decimal(3,2),
           atmosphere_rating decimal(3,2),
           cleanliness_rating decimal(3,2),
           access_rating decimal(3,2),
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           UNIQUE KEY idx_post_id (post_id),
           KEY idx_shop_name (shop_name),
           KEY idx_overall_rating (overall_rating),
           KEY idx_location (lat, lng)
       ) $charset_collate;";
       dbDelta($sql_shop);
       
       // ========================================
       // 物販レビュー用テーブル
       // ========================================
       
       $table_product_reviews = $wpdb->prefix . 'urp_product_reviews';
       $sql_product = "CREATE TABLE IF NOT EXISTS $table_product_reviews (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           post_id bigint(20) UNSIGNED NOT NULL,
           product_name varchar(255),
           manufacturer varchar(255),
           brand varchar(100),
           model_number varchar(100),
           jan_code varchar(20),
           asin varchar(20),
           rakuten_id varchar(50),
           yahoo_id varchar(50),
           regular_price int,
           sale_price int,
           currency varchar(3) DEFAULT 'JPY',
           weight int,
           dimensions varchar(100),
           release_date date,
           overall_rating decimal(3,2),
           quality_rating decimal(3,2),
           value_rating decimal(3,2),
           package_rating decimal(3,2),
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           UNIQUE KEY idx_post_id (post_id),
           KEY idx_product_name (product_name),
           KEY idx_manufacturer (manufacturer),
           KEY idx_jan_code (jan_code),
           KEY idx_asin (asin),
           KEY idx_overall_rating (overall_rating)
       ) $charset_collate;";
       dbDelta($sql_product);
       
       // ========================================
       // 評価項目動的管理テーブル
       // ========================================
       
       // 評価項目定義テーブル
       $table_rating_fields = $wpdb->prefix . 'urp_rating_fields';
       $sql_fields = "CREATE TABLE IF NOT EXISTS $table_rating_fields (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           extension_id varchar(50),
           review_type varchar(20),
           field_key varchar(50),
           field_label varchar(100),
           field_type varchar(20),
           field_options text,
           allow_multiple tinyint(1) DEFAULT 0,
           required tinyint(1) DEFAULT 0,
           sort_order int DEFAULT 0,
           is_active tinyint(1) DEFAULT 1,
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           UNIQUE KEY idx_unique_field (extension_id, review_type, field_key),
           KEY idx_extension (extension_id),
           KEY idx_type (review_type),
           KEY idx_active (is_active)
       ) $charset_collate;";
       dbDelta($sql_fields);
       
       // 評価値保存テーブル
       $table_rating_values = $wpdb->prefix . 'urp_rating_values';
       $sql_values = "CREATE TABLE IF NOT EXISTS $table_rating_values (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           post_id bigint(20) UNSIGNED NOT NULL,
           field_id bigint(20) UNSIGNED NOT NULL,
           value_text text,
           value_number decimal(10,2),
           value_rating decimal(3,2),
           value_json text,
           created_at datetime DEFAULT CURRENT_TIMESTAMP,
           updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           UNIQUE KEY idx_unique_value (post_id, field_id),
           KEY idx_post (post_id),
           KEY idx_field (field_id)
       ) $charset_collate;";
       dbDelta($sql_values);
       
       // ========================================
       // アフィリエイト関連テーブル（修正版）
       // ========================================
       
       // アフィリエイトクリックテーブル
       $table_affiliate_clicks = $wpdb->prefix . 'urp_affiliate_clicks';
       $sql_clicks = "CREATE TABLE IF NOT EXISTS $table_affiliate_clicks (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           product_id bigint(20) UNSIGNED NOT NULL,
           platform varchar(50) NOT NULL,
           post_id bigint(20) UNSIGNED DEFAULT NULL,
           user_id bigint(20) UNSIGNED DEFAULT NULL,
           ip_address varchar(45),
           user_agent text,
           referrer text,
           clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           KEY idx_product (product_id),
           KEY idx_platform (platform),
           KEY idx_post (post_id),
           KEY idx_user (user_id),
           KEY idx_date (clicked_at)
       ) $charset_collate;";
       dbDelta($sql_clicks);

       // アフィリエイトコンバージョンテーブル
       $table_affiliate_conversions = $wpdb->prefix . 'urp_affiliate_conversions';
       $sql_conversions = "CREATE TABLE IF NOT EXISTS $table_affiliate_conversions (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           click_id bigint(20) UNSIGNED,
           product_id bigint(20) UNSIGNED NOT NULL,
           platform varchar(50),
           commission decimal(10,2),
           currency varchar(3) DEFAULT 'JPY',
           status varchar(20),
           converted_at datetime DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           KEY idx_click (click_id),
           KEY idx_product (product_id),
           KEY idx_converted_at (converted_at)
       ) $charset_collate;";
       dbDelta($sql_conversions);

       // 価格履歴テーブル
       $table_price_history = $wpdb->prefix . 'urp_price_history';
       $sql_price_history = "CREATE TABLE IF NOT EXISTS $table_price_history (
           id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
           post_id bigint(20) UNSIGNED NOT NULL,
           platform varchar(50) NOT NULL,
           price decimal(10,2) NOT NULL,
           currency varchar(10) DEFAULT 'JPY',
           recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (id),
           KEY idx_post (post_id),
           KEY idx_platform (platform),
           KEY idx_date (recorded_at)
       ) $charset_collate;";
       dbDelta($sql_price_history);
              
       // データベースバージョンを保存
       update_option('urp_db_version', URP_DB_VERSION);
   }
   
   /**
    * デフォルトオプション設定
    */
   private static function set_default_options(): void {
       $default_settings = [
           // サイトモード設定
           'urp_site_mode' => 'hybrid',           // hybrid, shop_only, product_only
           'urp_affiliate_mode' => false,         // アフィリエイト機能
           'urp_affiliate_primary' => false,      // アフィリエイトメイン
           
           // 基本設定
           'urp_review_type' => 'generic',        // generic, curry, ramen等
           'urp_enable_ratings' => true,
           'urp_enable_comments' => true,
           'urp_require_approval' => true,
           'urp_enable_maps' => false,
           'urp_google_maps_api_key' => '',
           'urp_items_per_page' => 10,
           'urp_enable_rich_snippets' => true,
           
           // キャッシュ設定
           'urp_cache_enabled' => true,
           'urp_cache_expiry' => 3600,
           
           // ソーシャル機能
           'urp_enable_social_share' => true,
           'urp_enable_ajax_loading' => true,
           'urp_enable_lazy_load' => true,
           
           // 画像設定
           'urp_image_quality' => 85,
           'urp_thumbnail_size' => [300, 300],
           'urp_medium_size' => [600, 600],
           'urp_large_size' => [1200, 1200],
           'urp_max_upload_size' => 5242880,
           'urp_allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
           
           // 通知設定
           'urp_email_notifications' => true,
           'urp_admin_email' => get_option('admin_email'),
           'urp_new_review_notification' => true,
           'urp_review_approved_notification' => true,
           
           // バリデーション設定
           'urp_minimum_word_count' => 50,
           'urp_maximum_word_count' => 5000,
           
           // セキュリティ設定
           'urp_enable_spam_protection' => true,
           'urp_recaptcha_site_key' => '',
           'urp_recaptcha_secret_key' => '',
           
           // 信頼度システム
           'urp_trust_score_algorithm' => 'weighted',
           'urp_default_reviewer_rank' => 'beginner',
           
           // アフィリエイト設定
           'urp_amazon_affiliate_id' => '',
           'urp_rakuten_affiliate_id' => '',
           'urp_yahoo_affiliate_id' => '',
           'urp_amazon_api_key' => '',
           'urp_amazon_api_secret' => '',
       ];
       
       foreach ($default_settings as $key => $value) {
           if (get_option($key) === false) {
               update_option($key, $value);
           }
       }
       
       // 汎用評価項目を設定
       self::set_default_rating_fields();
   }
   
   /**
    * デフォルト評価項目設定
    */
   private static function set_default_rating_fields(): void {
       global $wpdb;
       $table_fields = $wpdb->prefix . 'urp_rating_fields';
       
       // 汎用店舗評価項目
       $generic_shop_fields = [
           [
               'extension_id' => 'generic',
               'review_type' => 'shop',
               'field_key' => 'overall',
               'field_label' => '総合評価',
               'field_type' => 'rating',
               'field_options' => json_encode(['max' => 5]),
               'required' => 1,
               'sort_order' => 1
           ],
           [
               'extension_id' => 'generic',
               'review_type' => 'shop',
               'field_key' => 'service',
               'field_label' => 'サービス',
               'field_type' => 'rating',
               'field_options' => json_encode(['max' => 5]),
               'required' => 0,
               'sort_order' => 2
           ],
           [
               'extension_id' => 'generic',
               'review_type' => 'shop',
               'field_key' => 'atmosphere',
               'field_label' => '雰囲気',
               'field_type' => 'rating',
               'field_options' => json_encode(['max' => 5]),
               'required' => 0,
               'sort_order' => 3
           ]
       ];
       
       // 汎用商品評価項目
       $generic_product_fields = [
           [
               'extension_id' => 'generic',
               'review_type' => 'product',
               'field_key' => 'overall',
               'field_label' => '総合評価',
               'field_type' => 'rating',
               'field_options' => json_encode(['max' => 5]),
               'required' => 1,
               'sort_order' => 1
           ],
           [
               'extension_id' => 'generic',
               'review_type' => 'product',
               'field_key' => 'quality',
               'field_label' => '品質',
               'field_type' => 'rating',
               'field_options' => json_encode(['max' => 5]),
               'required' => 0,
               'sort_order' => 2
           ],
           [
               'extension_id' => 'generic',
               'review_type' => 'product',
               'field_key' => 'value',
               'field_label' => 'コスパ',
               'field_type' => 'rating',
               'field_options' => json_encode(['max' => 5]),
               'required' => 0,
               'sort_order' => 3
           ]
       ];
       
       // 既存データがなければ挿入
       $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_fields WHERE extension_id = 'generic'");
       if ($existing == 0) {
           foreach ($generic_shop_fields as $field) {
               $wpdb->insert($table_fields, $field);
           }
           foreach ($generic_product_fields as $field) {
               $wpdb->insert($table_fields, $field);
           }
       }
   }
   
   /**
    * デフォルトコンテンツ作成
    */
   private static function create_default_content(): void {
       $site_mode = get_option('urp_site_mode', 'hybrid');
       
       // サイトモードに応じたページ作成
       $pages = [];
       
       if ($site_mode === 'hybrid' || $site_mode === 'shop_only') {
           $pages[] = [
               'post_title' => '店舗レビュー',
               'post_name' => 'shop-reviews',
               'post_content' => '[urp_shop_reviews]',
               'meta_key' => '_urp_page_type',
               'meta_value' => 'shop_archive'
           ];
       }
       
       if ($site_mode === 'hybrid' || $site_mode === 'product_only') {
           $pages[] = [
               'post_title' => '商品レビュー',
               'post_name' => 'product-reviews',
               'post_content' => '[urp_product_reviews]',
               'meta_key' => '_urp_page_type',
               'meta_value' => 'product_archive'
           ];
       }
       
       // 共通ページ
       $pages[] = [
           'post_title' => 'レビュー投稿',
           'post_name' => 'submit-review',
           'post_content' => '[urp_review_form]',
           'meta_key' => '_urp_page_type',
           'meta_value' => 'submit_form'
       ];
       
       $pages[] = [
           'post_title' => 'ランキング',
           'post_name' => 'rankings',
           'post_content' => '[urp_rankings]',
           'meta_key' => '_urp_page_type',
           'meta_value' => 'rankings'
       ];
       
       foreach ($pages as $page) {
           $existing = get_page_by_path($page['post_name']);
           if (!$existing) {
               $page_id = wp_insert_post([
                   'post_title' => $page['post_title'],
                   'post_name' => $page['post_name'],
                   'post_content' => $page['post_content'],
                   'post_status' => 'publish',
                   'post_type' => 'page',
                   'post_author' => get_current_user_id()
               ]);
               
               if ($page_id && !is_wp_error($page_id)) {
                   update_post_meta($page_id, $page['meta_key'], $page['meta_value']);
                   update_option('urp_page_' . $page['meta_value'], $page_id);
               }
           }
       }
       
       // デフォルトカテゴリー作成
       self::create_default_categories();
   }
   
   /**
    * デフォルトカテゴリー作成
    */
   private static function create_default_categories(): void {
       $site_mode = get_option('urp_site_mode', 'hybrid');
       
       // 店舗カテゴリー
       if ($site_mode === 'hybrid' || $site_mode === 'shop_only') {
           $shop_categories = [
               '飲食店' => ['restaurant', '飲食店全般'],
               '小売店' => ['retail', '小売店全般'],
               'サービス' => ['service', 'サービス業全般']
           ];
           
           foreach ($shop_categories as $name => $data) {
               if (!term_exists($name, 'shop_category')) {
                   wp_insert_term($name, 'shop_category', [
                       'slug' => $data[0],
                       'description' => $data[1]
                   ]);
               }
           }
       }
       
       // 商品カテゴリー
       if ($site_mode === 'hybrid' || $site_mode === 'product_only') {
           $product_categories = [
               '食品' => ['food', '食品全般'],
               '日用品' => ['daily', '日用品全般'],
               '家電' => ['electronics', '家電製品']
           ];
           
           foreach ($product_categories as $name => $data) {
               if (!term_exists($name, 'product_category')) {
                   wp_insert_term($name, 'product_category', [
                       'slug' => $data[0],
                       'description' => $data[1]
                   ]);
               }
           }
       }
       
       // 地域カテゴリー（店舗がある場合のみ）
       if ($site_mode === 'hybrid' || $site_mode === 'shop_only') {
           $regions = [
               '北海道・東北' => ['hokkaido-tohoku', '北海道、東北地方'],
               '関東' => ['kanto', '関東地方'],
               '中部' => ['chubu', '中部地方'],
               '近畿' => ['kinki', '近畿地方'],
               '中国・四国' => ['chugoku-shikoku', '中国、四国地方'],
               '九州・沖縄' => ['kyushu-okinawa', '九州、沖縄地方']
           ];
           
           foreach ($regions as $name => $data) {
               if (!term_exists($name, 'review_region')) {
                   wp_insert_term($name, 'review_region', [
                       'slug' => $data[0],
                       'description' => $data[1]
                   ]);
               }
           }
       }
   }
   
   /**
    * ロールと権限の設定
    */
   private static function setup_roles_and_capabilities(): void {
       // 管理者に権限追加
       $admin_role = get_role('administrator');
       if ($admin_role) {
           $admin_caps = [
               'manage_reviews',
               'edit_reviews',
               'edit_others_reviews',
               'publish_reviews',
               'read_private_reviews',
               'delete_reviews',
               'delete_private_reviews',
               'delete_published_reviews',
               'delete_others_reviews',
               'edit_private_reviews',
               'edit_published_reviews',
               'manage_review_categories',
               'manage_review_settings',
               'view_review_analytics',
               'moderate_review_comments',
               'manage_review_users'
           ];
           
           foreach ($admin_caps as $cap) {
               $admin_role->add_cap($cap);
           }
       }
       
       // レビューモデレーターロール作成
       if (!get_role('review_moderator')) {
           add_role('review_moderator', 'レビューモデレーター', [
               'read' => true,
               'edit_reviews' => true,
               'edit_others_reviews' => true,
               'publish_reviews' => true,
               'delete_reviews' => true,
               'moderate_review_comments' => true,
               'upload_files' => true
           ]);
       }
       
       // プレミアムレビュアーロール作成
       if (!get_role('premium_reviewer')) {
           add_role('premium_reviewer', 'プレミアムレビュアー', [
               'read' => true,
               'edit_reviews' => true,
               'publish_reviews' => true,
               'upload_files' => true,
               'delete_reviews' => true
           ]);
       }
       
       // 一般ユーザーにレビュー投稿権限追加
       $subscriber_role = get_role('subscriber');
       if ($subscriber_role) {
           $subscriber_role->add_cap('edit_reviews');
           $subscriber_role->add_cap('delete_reviews');
       }
   }
   
   /**
    * Cronイベントのスケジュール
    */
   private static function schedule_events(): void {
       // 日次Cronジョブ
       if (!wp_next_scheduled('urp_daily_cron')) {
           wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'urp_daily_cron');
       }
       
       // 時間毎Cronジョブ
       if (!wp_next_scheduled('urp_hourly_cron')) {
           wp_schedule_event(time() + 3600, 'hourly', 'urp_hourly_cron');
       }
   }
   
   /**
    * アップロードディレクトリ作成
    */
   private static function create_upload_directories(): void {
       $upload_dir = wp_upload_dir();
       $base_dir = $upload_dir['basedir'];
       
       $directories = [
           $base_dir . '/urp-reviews',
           $base_dir . '/urp-reviews/shops',
           $base_dir . '/urp-reviews/products',
           $base_dir . '/urp-reviews/thumbnails',
           $base_dir . '/urp-reviews/temp',
           $base_dir . '/urp-cache',
           $base_dir . '/urp-exports',
           $base_dir . '/urp-imports'
       ];
       
       foreach ($directories as $dir) {
           if (!file_exists($dir)) {
               wp_mkdir_p($dir);
               
               // セキュリティ強化
               if (strpos($dir, 'temp') !== false || strpos($dir, 'cache') !== false) {
                   $htaccess = $dir . '/.htaccess';
                   if (!file_exists($htaccess)) {
                       file_put_contents($htaccess, "Deny from all\n");
                   }
               }
           }
       }
       
       // インデックスファイルを作成
       foreach ($directories as $dir) {
           $index = $dir . '/index.php';
           if (!file_exists($index)) {
               file_put_contents($index, "<?php\n// Silence is golden\n");
           }
       }
   }
   
   /**
    * アクティベーショントランジェント設定
    */
   private static function set_activation_transients(): void {
       // ウェルカムメッセージ表示用
       set_transient('urp_activation_redirect', true, 30);
       
       // 初回セットアップ通知
       set_transient('urp_show_setup_notice', true, WEEK_IN_SECONDS);
       
       // プラグインが新規インストールか更新かを判定
       $previous_version = get_option('urp_version', '0.0.0');
       if (version_compare($previous_version, URP_VERSION, '<')) {
           if ($previous_version === '0.0.0') {
               set_transient('urp_fresh_install', true, 30);
           } else {
               set_transient('urp_updated_from', $previous_version, 30);
           }
       }
   }
}