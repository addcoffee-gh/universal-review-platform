<?php
/**
 * Universal Review Platform - 重要定数定義
 * 
 * このファイルがすべての定数の真実の源
 * 他のファイルはこのファイルの定数を使用する
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 */

// プラグイン基本定数
define('URP_VERSION', '1.0.0');
define('URP_DB_VERSION', '1.0.0');
define('URP_MIN_PHP_VERSION', '8.0');
define('URP_MIN_WP_VERSION', '6.0');
define('URP_TEXT_DOMAIN', 'universal-review');

// ディレクトリ定数（universal-review-platform.phpで設定済みを参照）
// URP_PLUGIN_FILE - メインプラグインファイル
// URP_PLUGIN_DIR - プラグインディレクトリ
// URP_PLUGIN_URL - プラグインURL
// URP_PLUGIN_BASENAME - プラグインベースネーム

// サブディレクトリ
define('URP_ADMIN_DIR', URP_PLUGIN_DIR . 'admin/');
define('URP_PUBLIC_DIR', URP_PLUGIN_DIR . 'public/');
define('URP_INCLUDES_DIR', URP_PLUGIN_DIR . 'includes/');
define('URP_CORE_DIR', URP_PLUGIN_DIR . 'core/');
define('URP_TEMPLATES_DIR', URP_PLUGIN_DIR . 'templates/');
define('URP_ASSETS_DIR', URP_PLUGIN_DIR . 'assets/');
define('URP_LANGUAGES_DIR', URP_PLUGIN_DIR . 'languages/');
define('URP_REACT_DIR', URP_PLUGIN_DIR . 'react-frontend/');

// URL定数
define('URP_ADMIN_URL', URP_PLUGIN_URL . 'admin/');
define('URP_PUBLIC_URL', URP_PLUGIN_URL . 'public/');
define('URP_ASSETS_URL', URP_PLUGIN_URL . 'assets/');
define('URP_REACT_URL', URP_PLUGIN_URL . 'react-frontend/build/');

// データベーステーブル定数
global $wpdb;
define('URP_DB_TABLE_REVIEWS', $wpdb->prefix . 'urp_reviews');
define('URP_DB_TABLE_RATINGS', $wpdb->prefix . 'urp_ratings');
define('URP_DB_TABLE_META', $wpdb->prefix . 'urp_review_meta');
define('URP_DB_TABLE_IMAGES', $wpdb->prefix . 'urp_review_images');
define('URP_DB_TABLE_VOTES', $wpdb->prefix . 'urp_review_votes');
define('URP_DB_TABLE_FLAGS', $wpdb->prefix . 'urp_review_flags');
define('URP_DB_TABLE_REVIEWERS', $wpdb->prefix . 'urp_reviewers');
define('URP_DB_TABLE_ANALYTICS', $wpdb->prefix . 'urp_analytics');
define('URP_DB_TABLE_CATEGORIES', $wpdb->prefix . 'urp_categories');
define('URP_DB_TABLE_TAGS', $wpdb->prefix . 'urp_tags');

// REST APIエンドポイント
define('URP_API_NAMESPACE', 'urp/v1');
define('URP_API_V2_NAMESPACE', 'urp/v2');

// キャッシュキー接頭辞
define('URP_CACHE_PREFIX', 'urp_cache_');
define('URP_CACHE_GROUP', 'urp_cache');
define('URP_CACHE_EXPIRATION', 3600); // 1時間

// オプション名
define('URP_OPTION_PREFIX', 'urp_');
define('URP_OPTION_SETTINGS', 'urp_settings');
define('URP_OPTION_VERSION', 'urp_version');
define('URP_OPTION_DB_VERSION', 'urp_db_version');

// 権限
define('URP_CAP_MANAGE_REVIEWS', 'urp_manage_reviews');
define('URP_CAP_MODERATE_REVIEWS', 'urp_moderate_reviews');
define('URP_CAP_DELETE_REVIEWS', 'urp_delete_reviews');
define('URP_CAP_EDIT_SETTINGS', 'urp_edit_settings');

// レビューステータス
define('URP_STATUS_PENDING', 'pending');
define('URP_STATUS_APPROVED', 'approved');
define('URP_STATUS_REJECTED', 'rejected');
define('URP_STATUS_SPAM', 'spam');
define('URP_STATUS_TRASH', 'trash');

// 評価タイプ
define('URP_RATING_OVERALL', 'overall');
define('URP_RATING_TASTE', 'taste');
define('URP_RATING_SERVICE', 'service');
define('URP_RATING_ATMOSPHERE', 'atmosphere');
define('URP_RATING_PRICE', 'price');
define('URP_RATING_CLEANLINESS', 'cleanliness');

// 画像設定
define('URP_MAX_IMAGE_SIZE', 5242880); // 5MB
define('URP_MAX_IMAGES_PER_REVIEW', 10);
define('URP_ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ページネーション
define('URP_REVIEWS_PER_PAGE', 10);
define('URP_ADMIN_REVIEWS_PER_PAGE', 20);

// セキュリティ
define('URP_NONCE_KEY', 'urp_nonce');
define('URP_NONCE_ACTION', 'urp_action');

// デバッグ
define('URP_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
define('URP_LOG_ENABLED', URP_DEBUG);

// 差別化機能用定数
define('URP_TRUST_SCORE_ENABLED', true);
define('URP_ML_DETECTION_ENABLED', true);
define('URP_REALTIME_UPDATE_ENABLED', true);
define('URP_ADVANCED_ALGO_VERSION', '2.0');

// Redis設定（パフォーマンス最適化用）
define('URP_REDIS_ENABLED', false); // 後でtrueに変更
define('URP_REDIS_HOST', '127.0.0.1');
define('URP_REDIS_PORT', 6379);

// 外部サービス統合
define('URP_GOOGLE_MAPS_ENABLED', true);
define('URP_SOCIAL_SHARE_ENABLED', true);
define('URP_PAYMENT_INTEGRATION_ENABLED', false);

// React設定
define('URP_REACT_DEV_MODE', URP_DEBUG);
define('URP_REACT_BUILD_PATH', URP_REACT_DIR . 'build/');

// レート制限
define('URP_RATE_LIMIT_REVIEWS', 3); // 1時間あたり
define('URP_RATE_LIMIT_VOTES', 10); // 1時間あたり
define('URP_RATE_LIMIT_API', 100); // 1時間あたり