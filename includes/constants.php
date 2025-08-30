<?php
/**
 * Universal Review Platform - Constants
 * 
 * プラグイン全体で使用する定数を定義
 * すべての定数はこのファイルで一元管理
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// メインファイルのパスが定義されていることを確認
if (!defined('URP_MAIN_FILE')) {
    die('Error: URP_MAIN_FILE is not defined. This file must be included from the main plugin file.');
}

/**
 * 基本パス定義（URP_MAIN_FILEを基準に構築）
 */
if (!defined('URP_PLUGIN_FILE')) {
    define('URP_PLUGIN_FILE', URP_MAIN_FILE);
}

if (!defined('URP_PLUGIN_DIR')) {
    define('URP_PLUGIN_DIR', plugin_dir_path(URP_PLUGIN_FILE));
}

if (!defined('URP_PLUGIN_URL')) {
    define('URP_PLUGIN_URL', plugin_dir_url(URP_PLUGIN_FILE));
}

if (!defined('URP_PLUGIN_BASENAME')) {
    define('URP_PLUGIN_BASENAME', plugin_basename(URP_PLUGIN_FILE));
}

/**
 * バージョン情報
 */
if (!defined('URP_VERSION')) {
    define('URP_VERSION', '1.0.0');
}

if (!defined('URP_DB_VERSION')) {
    define('URP_DB_VERSION', '1.0.0');
}

if (!defined('URP_MINIMUM_WP_VERSION')) {
    define('URP_MINIMUM_WP_VERSION', '6.0');
}

if (!defined('URP_MINIMUM_PHP_VERSION')) {
    define('URP_MINIMUM_PHP_VERSION', '8.0');
}

/**
 * テキストドメイン
 */
if (!defined('URP_TEXT_DOMAIN')) {
    define('URP_TEXT_DOMAIN', 'universal-review');
}

/**
 * デバッグ設定（最初に定義）
 */
if (!defined('URP_DEBUG')) {
    define('URP_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
}

if (!defined('URP_DEBUG_LOG')) {
    define('URP_DEBUG_LOG', URP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG);
}

if (!defined('URP_DEBUG_DISPLAY')) {
    define('URP_DEBUG_DISPLAY', URP_DEBUG && defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY);
}

/**
 * ディレクトリパス
 */
if (!defined('URP_CORE_DIR')) {
    define('URP_CORE_DIR', URP_PLUGIN_DIR . 'core/');
}
if (!defined('URP_ADMIN_DIR')) {
    define('URP_ADMIN_DIR', URP_PLUGIN_DIR . 'admin/');
}
if (!defined('URP_PUBLIC_DIR')) {
    define('URP_PUBLIC_DIR', URP_PLUGIN_DIR . 'public/');
}
if (!defined('URP_INCLUDES_DIR')) {
    define('URP_INCLUDES_DIR', URP_PLUGIN_DIR . 'includes/');
}
if (!defined('URP_API_DIR')) {
    define('URP_API_DIR', URP_PLUGIN_DIR . 'api/');
}
if (!defined('URP_TEMPLATES_DIR')) {
    define('URP_TEMPLATES_DIR', URP_PLUGIN_DIR . 'templates/');
}
if (!defined('URP_LANGUAGES_DIR')) {
    define('URP_LANGUAGES_DIR', URP_PLUGIN_DIR . 'languages/');
}
if (!defined('URP_ASSETS_DIR')) {
    define('URP_ASSETS_DIR', URP_PLUGIN_DIR . 'assets/');
}
if (!defined('URP_REVIEW_TYPES_DIR')) {
    define('URP_REVIEW_TYPES_DIR', URP_PLUGIN_DIR . 'review-types/');
}
if (!defined('URP_DATABASE_DIR')) {
    define('URP_DATABASE_DIR', URP_PLUGIN_DIR . 'database/');
}
if (!defined('URP_INTEGRATIONS_DIR')) {
    define('URP_INTEGRATIONS_DIR', URP_PLUGIN_DIR . 'integrations/');
}

/**
 * URL定義
 */
if (!defined('URP_ASSETS_URL')) {
    define('URP_ASSETS_URL', URP_PLUGIN_URL . 'assets/');
}
if (!defined('URP_ADMIN_ASSETS_URL')) {
    define('URP_ADMIN_ASSETS_URL', URP_PLUGIN_URL . 'admin/assets/');
}
if (!defined('URP_PUBLIC_ASSETS_URL')) {
    define('URP_PUBLIC_ASSETS_URL', URP_PLUGIN_URL . 'public/assets/');
}
if (!defined('URP_IMAGES_URL')) {
    define('URP_IMAGES_URL', URP_ASSETS_URL . 'images/');
}
if (!defined('URP_CSS_URL')) {
    define('URP_CSS_URL', URP_ASSETS_URL . 'css/');
}
if (!defined('URP_JS_URL')) {
    define('URP_JS_URL', URP_ASSETS_URL . 'js/');
}

/**
 * 投稿タイプ・タクソノミー
 */
if (!defined('URP_POST_TYPE')) {
    define('URP_POST_TYPE', 'platform_review');
}
if (!defined('URP_CATEGORY_TAXONOMY')) {
    define('URP_CATEGORY_TAXONOMY', 'review_category');
}
if (!defined('URP_REGION_TAXONOMY')) {
    define('URP_REGION_TAXONOMY', 'review_region');
}
if (!defined('URP_TAG_TAXONOMY')) {
    define('URP_TAG_TAXONOMY', 'review_tag');
}

/**
 * ユーザーロール
 */
if (!defined('URP_ROLE_MODERATOR')) {
    define('URP_ROLE_MODERATOR', 'review_moderator');
}
if (!defined('URP_ROLE_PREMIUM')) {
    define('URP_ROLE_PREMIUM', 'premium_reviewer');
}
if (!defined('URP_ROLE_ANALYST')) {
    define('URP_ROLE_ANALYST', 'review_analyst');
}

/**
 * 権限（Capabilities）
 */
if (!defined('URP_CAP_MANAGE_REVIEWS')) {
    define('URP_CAP_MANAGE_REVIEWS', 'manage_reviews');
}
if (!defined('URP_CAP_EDIT_REVIEWS')) {
    define('URP_CAP_EDIT_REVIEWS', 'edit_reviews');
}
if (!defined('URP_CAP_DELETE_REVIEWS')) {
    define('URP_CAP_DELETE_REVIEWS', 'delete_reviews');
}
if (!defined('URP_CAP_PUBLISH_REVIEWS')) {
    define('URP_CAP_PUBLISH_REVIEWS', 'publish_reviews');
}
if (!defined('URP_CAP_MODERATE_REVIEWS')) {
    define('URP_CAP_MODERATE_REVIEWS', 'moderate_review_comments');
}
if (!defined('URP_CAP_VIEW_ANALYTICS')) {
    define('URP_CAP_VIEW_ANALYTICS', 'view_review_analytics');
}
if (!defined('URP_CAP_MANAGE_SETTINGS')) {
    define('URP_CAP_MANAGE_SETTINGS', 'manage_review_settings');
}

/**
 * データベーステーブル名（プレフィックスなし）
 */
if (!defined('URP_TABLE_RATINGS')) {
    define('URP_TABLE_RATINGS', 'urp_review_ratings');
}
if (!defined('URP_TABLE_CRITERIA')) {
    define('URP_TABLE_CRITERIA', 'urp_review_criteria');
}
if (!defined('URP_TABLE_VALUES')) {
    define('URP_TABLE_VALUES', 'urp_review_values');
}
if (!defined('URP_TABLE_VOTES')) {
    define('URP_TABLE_VOTES', 'urp_review_votes');
}
if (!defined('URP_TABLE_RANKS')) {
    define('URP_TABLE_RANKS', 'urp_reviewer_ranks');
}
if (!defined('URP_TABLE_IMAGES')) {
    define('URP_TABLE_IMAGES', 'urp_review_images');
}
if (!defined('URP_TABLE_REPORTS')) {
    define('URP_TABLE_REPORTS', 'urp_review_reports');
}
if (!defined('URP_TABLE_ANALYTICS')) {
    define('URP_TABLE_ANALYTICS', 'urp_analytics');
}

/**
 * キャッシュキー
 */
if (!defined('URP_CACHE_GROUP')) {
    define('URP_CACHE_GROUP', 'urp_cache');
}
if (!defined('URP_CACHE_REVIEWS')) {
    define('URP_CACHE_REVIEWS', 'urp_reviews');
}
if (!defined('URP_CACHE_RANKINGS')) {
    define('URP_CACHE_RANKINGS', 'urp_rankings');
}
if (!defined('URP_CACHE_ANALYTICS')) {
    define('URP_CACHE_ANALYTICS', 'urp_analytics');
}
if (!defined('URP_CACHE_SETTINGS')) {
    define('URP_CACHE_SETTINGS', 'urp_settings');
}

/**
 * トランジェントキー
 */
if (!defined('URP_TRANSIENT_PREFIX')) {
    define('URP_TRANSIENT_PREFIX', 'urp_');
}
if (!defined('URP_TRANSIENT_CLASS_MAP')) {
    define('URP_TRANSIENT_CLASS_MAP', 'urp_class_map');
}
if (!defined('URP_TRANSIENT_REVIEW_COUNT')) {
    define('URP_TRANSIENT_REVIEW_COUNT', 'urp_review_count');
}
if (!defined('URP_TRANSIENT_TOP_REVIEWS')) {
    define('URP_TRANSIENT_TOP_REVIEWS', 'urp_top_reviews');
}
if (!defined('URP_TRANSIENT_RECENT_REVIEWS')) {
    define('URP_TRANSIENT_RECENT_REVIEWS', 'urp_recent_reviews');
}

/**
 * オプションキー
 */
if (!defined('URP_OPTION_PREFIX')) {
    define('URP_OPTION_PREFIX', 'urp_');
}
if (!defined('URP_OPTION_VERSION')) {
    define('URP_OPTION_VERSION', 'urp_version');
}
if (!defined('URP_OPTION_DB_VERSION')) {
    define('URP_OPTION_DB_VERSION', 'urp_db_version');
}
if (!defined('URP_OPTION_REVIEW_TYPE')) {
    define('URP_OPTION_REVIEW_TYPE', 'urp_review_type');
}
if (!defined('URP_OPTION_SETTINGS')) {
    define('URP_OPTION_SETTINGS', 'urp_settings');
}
if (!defined('URP_OPTION_GOOGLE_MAPS_KEY')) {
    define('URP_OPTION_GOOGLE_MAPS_KEY', 'urp_google_maps_api_key');
}

/**
 * メタキー
 */
if (!defined('URP_META_PREFIX')) {
    define('URP_META_PREFIX', 'urp_');
}
if (!defined('URP_META_REVIEW_TYPE')) {
    define('URP_META_REVIEW_TYPE', 'urp_review_type');
}
if (!defined('URP_META_OVERALL_RATING')) {
    define('URP_META_OVERALL_RATING', 'urp_overall_rating');
}
if (!defined('URP_META_PRICE')) {
    define('URP_META_PRICE', 'urp_price');
}
if (!defined('URP_META_LOCATION')) {
    define('URP_META_LOCATION', 'urp_location');
}
if (!defined('URP_META_VIEW_COUNT')) {
    define('URP_META_VIEW_COUNT', 'urp_view_count');
}

/**
 * デフォルト値
 */
if (!defined('URP_DEFAULT_ITEMS_PER_PAGE')) {
    define('URP_DEFAULT_ITEMS_PER_PAGE', 10);
}
if (!defined('URP_DEFAULT_CACHE_EXPIRY')) {
    define('URP_DEFAULT_CACHE_EXPIRY', 3600); // 1時間
}
if (!defined('URP_DEFAULT_IMAGE_QUALITY')) {
    define('URP_DEFAULT_IMAGE_QUALITY', 85);
}
if (!defined('URP_DEFAULT_THUMBNAIL_SIZE')) {
    define('URP_DEFAULT_THUMBNAIL_SIZE', [300, 300]);
}
if (!defined('URP_DEFAULT_MEDIUM_SIZE')) {
    define('URP_DEFAULT_MEDIUM_SIZE', [600, 600]);
}
if (!defined('URP_DEFAULT_LARGE_SIZE')) {
    define('URP_DEFAULT_LARGE_SIZE', [1200, 1200]);
}
if (!defined('URP_DEFAULT_MAX_UPLOAD_SIZE')) {
    define('URP_DEFAULT_MAX_UPLOAD_SIZE', 5242880); // 5MB
}
if (!defined('URP_DEFAULT_MIN_WORD_COUNT')) {
    define('URP_DEFAULT_MIN_WORD_COUNT', 50);
}
if (!defined('URP_DEFAULT_MAX_WORD_COUNT')) {
    define('URP_DEFAULT_MAX_WORD_COUNT', 5000);
}

/**
 * 評価関連
 */
if (!defined('URP_RATING_MIN')) {
    define('URP_RATING_MIN', 1);
}
if (!defined('URP_RATING_MAX')) {
    define('URP_RATING_MAX', 5);
}
if (!defined('URP_RATING_STEP')) {
    define('URP_RATING_STEP', 0.5);
}

/**
 * レビュータイプ
 */
if (!defined('URP_REVIEW_TYPE_CURRY')) {
    define('URP_REVIEW_TYPE_CURRY', 'curry');
}
if (!defined('URP_REVIEW_TYPE_RAMEN')) {
    define('URP_REVIEW_TYPE_RAMEN', 'ramen');
}
if (!defined('URP_REVIEW_TYPE_SUSHI')) {
    define('URP_REVIEW_TYPE_SUSHI', 'sushi');
}
if (!defined('URP_REVIEW_TYPE_CAFE')) {
    define('URP_REVIEW_TYPE_CAFE', 'cafe');
}
if (!defined('URP_REVIEW_TYPE_DEFAULT')) {
    define('URP_REVIEW_TYPE_DEFAULT', 'curry');
}

/**
 * レビューステータス
 */
if (!defined('URP_STATUS_DRAFT')) {
    define('URP_STATUS_DRAFT', 'draft');
}
if (!defined('URP_STATUS_PENDING')) {
    define('URP_STATUS_PENDING', 'pending');
}
if (!defined('URP_STATUS_PUBLISHED')) {
    define('URP_STATUS_PUBLISHED', 'publish');
}
if (!defined('URP_STATUS_ARCHIVED')) {
    define('URP_STATUS_ARCHIVED', 'archived');
}
if (!defined('URP_STATUS_SPAM')) {
    define('URP_STATUS_SPAM', 'spam');
}

/**
 * ソート順
 */
if (!defined('URP_SORT_DATE_DESC')) {
    define('URP_SORT_DATE_DESC', 'date_desc');
}
if (!defined('URP_SORT_DATE_ASC')) {
    define('URP_SORT_DATE_ASC', 'date_asc');
}
if (!defined('URP_SORT_RATING_DESC')) {
    define('URP_SORT_RATING_DESC', 'rating_desc');
}
if (!defined('URP_SORT_RATING_ASC')) {
    define('URP_SORT_RATING_ASC', 'rating_asc');
}
if (!defined('URP_SORT_POPULAR')) {
    define('URP_SORT_POPULAR', 'popular');
}
if (!defined('URP_SORT_VIEWS')) {
    define('URP_SORT_VIEWS', 'views');
}
if (!defined('URP_SORT_TITLE')) {
    define('URP_SORT_TITLE', 'title');
}

/**
 * 価格帯（カレー用）
 */
if (!defined('URP_PRICE_RANGE_BUDGET')) {
    define('URP_PRICE_RANGE_BUDGET', 'budget'); // ~1000円
}
if (!defined('URP_PRICE_RANGE_STANDARD')) {
    define('URP_PRICE_RANGE_STANDARD', 'standard'); // 1000-1500円
}
if (!defined('URP_PRICE_RANGE_PREMIUM')) {
    define('URP_PRICE_RANGE_PREMIUM', 'premium'); // 1500-2000円
}
if (!defined('URP_PRICE_RANGE_LUXURY')) {
    define('URP_PRICE_RANGE_LUXURY', 'luxury'); // 2000円~
}

/**
 * 辛さレベル（カレー用）
 */
if (!defined('URP_SPICE_LEVEL_MILD')) {
    define('URP_SPICE_LEVEL_MILD', 1); // 甘口
}
if (!defined('URP_SPICE_LEVEL_MEDIUM')) {
    define('URP_SPICE_LEVEL_MEDIUM', 3); // 中辛
}
if (!defined('URP_SPICE_LEVEL_HOT')) {
    define('URP_SPICE_LEVEL_HOT', 5); // 辛口
}
if (!defined('URP_SPICE_LEVEL_VERY_HOT')) {
    define('URP_SPICE_LEVEL_VERY_HOT', 7); // 激辛
}
if (!defined('URP_SPICE_LEVEL_EXTREME')) {
    define('URP_SPICE_LEVEL_EXTREME', 10); // 超激辛
}

/**
 * API関連
 */
if (!defined('URP_API_NAMESPACE')) {
    define('URP_API_NAMESPACE', 'urp/v1');
}
if (!defined('URP_API_VERSION')) {
    define('URP_API_VERSION', '1.0');
}
if (!defined('URP_API_TIMEOUT')) {
    define('URP_API_TIMEOUT', 30);
}
if (!defined('URP_API_RATE_LIMIT')) {
    define('URP_API_RATE_LIMIT', 100); // リクエスト/時間
}

/**
 * 外部サービス
 */
if (!defined('URP_GOOGLE_MAPS_API_URL')) {
    define('URP_GOOGLE_MAPS_API_URL', 'https://maps.googleapis.com/maps/api/');
}
if (!defined('URP_GEOCODING_API_URL')) {
    define('URP_GEOCODING_API_URL', 'https://maps.googleapis.com/maps/api/geocode/json');
}
if (!defined('URP_PLACES_API_URL')) {
    define('URP_PLACES_API_URL', 'https://maps.googleapis.com/maps/api/place/');
}

/**
 * セキュリティ
 */
if (!defined('URP_NONCE_KEY')) {
    define('URP_NONCE_KEY', 'urp_nonce');
}
if (!defined('URP_NONCE_ACTION')) {
    define('URP_NONCE_ACTION', 'urp_action');
}
if (!defined('URP_SESSION_KEY')) {
    define('URP_SESSION_KEY', 'urp_session');
}
if (!defined('URP_CSRF_TOKEN_NAME')) {
    define('URP_CSRF_TOKEN_NAME', 'urp_csrf_token');
}

/**
 * ファイルタイプ
 */
if (!defined('URP_ALLOWED_IMAGE_TYPES')) {
    define('URP_ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}
if (!defined('URP_ALLOWED_DOCUMENT_TYPES')) {
    define('URP_ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);
}
if (!defined('URP_ALLOWED_VIDEO_TYPES')) {
    define('URP_ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'ogg']);
}

/**
 * 環境判定
 */
if (!defined('URP_IS_ADMIN')) {
    define('URP_IS_ADMIN', is_admin());
}
if (!defined('URP_IS_AJAX')) {
    define('URP_IS_AJAX', defined('DOING_AJAX') && DOING_AJAX);
}
if (!defined('URP_IS_CRON')) {
    define('URP_IS_CRON', defined('DOING_CRON') && DOING_CRON);
}
if (!defined('URP_IS_CLI')) {
    define('URP_IS_CLI', defined('WP_CLI') && WP_CLI);
}
if (!defined('URP_IS_REST')) {
    define('URP_IS_REST', defined('REST_REQUEST') && REST_REQUEST);
}
if (!defined('URP_IS_MULTISITE')) {
    define('URP_IS_MULTISITE', is_multisite());
}

/**
 * 時間定数
 */
if (!defined('URP_MINUTE_IN_SECONDS')) {
    define('URP_MINUTE_IN_SECONDS', 60);
}
if (!defined('URP_HOUR_IN_SECONDS')) {
    define('URP_HOUR_IN_SECONDS', 3600);
}
if (!defined('URP_DAY_IN_SECONDS')) {
    define('URP_DAY_IN_SECONDS', 86400);
}
if (!defined('URP_WEEK_IN_SECONDS')) {
    define('URP_WEEK_IN_SECONDS', 604800);
}
if (!defined('URP_MONTH_IN_SECONDS')) {
    define('URP_MONTH_IN_SECONDS', 2592000);
}
if (!defined('URP_YEAR_IN_SECONDS')) {
    define('URP_YEAR_IN_SECONDS', 31536000);
}

/**
 * レビュアーランク
 */
if (!defined('URP_RANK_BEGINNER')) {
    define('URP_RANK_BEGINNER', 'beginner');
}
if (!defined('URP_RANK_REGULAR')) {
    define('URP_RANK_REGULAR', 'regular');
}
if (!defined('URP_RANK_EXPERT')) {
    define('URP_RANK_EXPERT', 'expert');
}
if (!defined('URP_RANK_MASTER')) {
    define('URP_RANK_MASTER', 'master');
}
if (!defined('URP_RANK_LEGEND')) {
    define('URP_RANK_LEGEND', 'legend');
}

/**
 * バッジ
 */
if (!defined('URP_BADGE_FIRST_REVIEW')) {
    define('URP_BADGE_FIRST_REVIEW', 'first_review');
}
if (!defined('URP_BADGE_10_REVIEWS')) {
    define('URP_BADGE_10_REVIEWS', '10_reviews');
}
if (!defined('URP_BADGE_50_REVIEWS')) {
    define('URP_BADGE_50_REVIEWS', '50_reviews');
}
if (!defined('URP_BADGE_100_REVIEWS')) {
    define('URP_BADGE_100_REVIEWS', '100_reviews');
}
if (!defined('URP_BADGE_SPICY_LOVER')) {
    define('URP_BADGE_SPICY_LOVER', 'spicy_lover');
}
if (!defined('URP_BADGE_CURRY_MASTER')) {
    define('URP_BADGE_CURRY_MASTER', 'curry_master');
}

/**
 * イベントタイプ（アナリティクス）
 */
if (!defined('URP_EVENT_REVIEW_CREATED')) {
    define('URP_EVENT_REVIEW_CREATED', 'review_created');
}
if (!defined('URP_EVENT_REVIEW_UPDATED')) {
    define('URP_EVENT_REVIEW_UPDATED', 'review_updated');
}
if (!defined('URP_EVENT_REVIEW_DELETED')) {
    define('URP_EVENT_REVIEW_DELETED', 'review_deleted');
}
if (!defined('URP_EVENT_REVIEW_VIEWED')) {
    define('URP_EVENT_REVIEW_VIEWED', 'review_viewed');
}
if (!defined('URP_EVENT_REVIEW_LIKED')) {
    define('URP_EVENT_REVIEW_LIKED', 'review_liked');
}
if (!defined('URP_EVENT_REVIEW_SHARED')) {
    define('URP_EVENT_REVIEW_SHARED', 'review_shared');
}
if (!defined('URP_EVENT_SEARCH')) {
    define('URP_EVENT_SEARCH', 'search');
}
if (!defined('URP_EVENT_FILTER')) {
    define('URP_EVENT_FILTER', 'filter');
}

/**
 * レポートタイプ
 */
if (!defined('URP_REPORT_SPAM')) {
    define('URP_REPORT_SPAM', 'spam');
}
if (!defined('URP_REPORT_INAPPROPRIATE')) {
    define('URP_REPORT_INAPPROPRIATE', 'inappropriate');
}
if (!defined('URP_REPORT_FAKE')) {
    define('URP_REPORT_FAKE', 'fake');
}
if (!defined('URP_REPORT_COPYRIGHT')) {
    define('URP_REPORT_COPYRIGHT', 'copyright');
}
if (!defined('URP_REPORT_OTHER')) {
    define('URP_REPORT_OTHER', 'other');
}

/**
 * 通知タイプ
 */
if (!defined('URP_NOTIFY_NEW_REVIEW')) {
    define('URP_NOTIFY_NEW_REVIEW', 'new_review');
}
if (!defined('URP_NOTIFY_REVIEW_APPROVED')) {
    define('URP_NOTIFY_REVIEW_APPROVED', 'review_approved');
}
if (!defined('URP_NOTIFY_REVIEW_REJECTED')) {
    define('URP_NOTIFY_REVIEW_REJECTED', 'review_rejected');
}
if (!defined('URP_NOTIFY_NEW_COMMENT')) {
    define('URP_NOTIFY_NEW_COMMENT', 'new_comment');
}
if (!defined('URP_NOTIFY_MENTION')) {
    define('URP_NOTIFY_MENTION', 'mention');
}

/**
 * エラーコード
 */
if (!defined('URP_ERROR_INVALID_DATA')) {
    define('URP_ERROR_INVALID_DATA', 'invalid_data');
}
if (!defined('URP_ERROR_PERMISSION_DENIED')) {
    define('URP_ERROR_PERMISSION_DENIED', 'permission_denied');
}
if (!defined('URP_ERROR_NOT_FOUND')) {
    define('URP_ERROR_NOT_FOUND', 'not_found');
}
if (!defined('URP_ERROR_DUPLICATE')) {
    define('URP_ERROR_DUPLICATE', 'duplicate');
}
if (!defined('URP_ERROR_LIMIT_EXCEEDED')) {
    define('URP_ERROR_LIMIT_EXCEEDED', 'limit_exceeded');
}
if (!defined('URP_ERROR_SERVER')) {
    define('URP_ERROR_SERVER', 'server_error');
}

/**
 * 成功メッセージ
 */
if (!defined('URP_SUCCESS_SAVED')) {
    define('URP_SUCCESS_SAVED', 'saved_successfully');
}
if (!defined('URP_SUCCESS_DELETED')) {
    define('URP_SUCCESS_DELETED', 'deleted_successfully');
}
if (!defined('URP_SUCCESS_UPDATED')) {
    define('URP_SUCCESS_UPDATED', 'updated_successfully');
}
if (!defined('URP_SUCCESS_PUBLISHED')) {
    define('URP_SUCCESS_PUBLISHED', 'published_successfully');
}