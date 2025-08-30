<?php
/**
 * Universal Review Platform - Helper Functions
 * 
 * プラグイン全体で使用するヘルパー関数
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
 * プラグインインスタンス取得
 * @return Universal_Review_Platform
 */
if (!function_exists('urp')) {
    function urp(): Universal_Review_Platform {
        // URP()関数が存在すればそれを使用（後方互換性）
        if (function_exists('URP')) {
            return URP();
        }
        return Universal_Review_Platform::instance();
    }
}

/**
 * レビューマネージャー取得
 * @return URP\Core\URP_Review_Manager|null
 */
function urp_reviews(): ?URP\Core\URP_Review_Manager {
    return urp()->get_component('review_manager');
}

/**
 * データベースマネージャー取得
 * @return URP\Core\URP_Database|null
 */
function urp_db(): ?URP\Core\URP_Database {
    return urp()->get_component('database');
}

/**
 * 設定値取得
 * PHP 8.0: mixed型
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function urp_get_option(string $key, mixed $default = null): mixed {
    return get_option(URP_OPTION_PREFIX . $key, $default);
}

/**
 * 設定値保存
 * @param string $key
 * @param mixed $value
 * @return bool
 */
function urp_update_option(string $key, mixed $value): bool {
    return update_option(URP_OPTION_PREFIX . $key, $value);
}

/**
 * 設定値削除
 * @param string $key
 * @return bool
 */
function urp_delete_option(string $key): bool {
    return delete_option(URP_OPTION_PREFIX . $key);
}

/**
 * レビュータイプ取得
 * @return string
 */
function urp_get_review_type(): string {
    return urp_get_option('review_type', URP_REVIEW_TYPE_DEFAULT);
}

/**
 * レビュータイプ判定
 * @param string ...$types
 * @return bool
 */
function urp_is_review_type(string ...$types): bool {
    return in_array(urp_get_review_type(), $types, true);
}

/**
 * テンプレートパス取得
 * @param string $template
 * @param string $type
 * @return string
 */
function urp_get_template_path(string $template, string $type = ''): string {
    // テーマ内のテンプレートを優先
    $theme_template = get_stylesheet_directory() . '/urp-templates/' . $template;
    if (file_exists($theme_template)) {
        return $theme_template;
    }
    
    // プラグイン内のテンプレート
    if ($type) {
        $plugin_template = URP_PLUGIN_DIR . "public/templates/{$type}/{$template}";
    } else {
        $plugin_template = URP_PLUGIN_DIR . "public/templates/{$template}";
    }
    
    return $plugin_template;
}

/**
 * テンプレート読み込み
 * @param string $template
 * @param array<string, mixed> $args
 * @param bool $echo
 * @return string  // void を削除し、string のみに変更
 */
function urp_get_template(string $template, array $args = [], bool $echo = true): string {
    $template_path = urp_get_template_path($template);
    
    if (!file_exists($template_path)) {
        if (URP_DEBUG) {
            error_log("URP Template not found: {$template_path}");
        }
        return '';  // 空文字列を返す
    }
    
    extract($args);
    
    if (!$echo) {
        ob_start();
    }
    
    include $template_path;
    
    if (!$echo) {
        return ob_get_clean();
    }
    
    return '';  // echo の場合も空文字列を返す
}

/**
 * アセットURL取得
 * @param string $file
 * @param string $type
 * @return string
 */
function urp_asset_url(string $file, string $type = ''): string {
    $base_url = match($type) {
        'admin' => URP_ADMIN_ASSETS_URL,
        'public' => URP_PUBLIC_ASSETS_URL,
        'images' => URP_IMAGES_URL,
        'css' => URP_CSS_URL,
        'js' => URP_JS_URL,
        default => URP_ASSETS_URL
    };
    
    return $base_url . $file;
}

/**
 * 評価の星HTML生成
 * PHP 8.0: match式
 * @param float $rating
 * @param bool $show_number
 * @return string
 */
function urp_rating_stars(float $rating, bool $show_number = false): string {
    $rating = max(0, min(5, $rating));
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5 ? 1 : 0;
    $empty_stars = 5 - $full_stars - $half_star;
    
    $html = '<span class="urp-rating-stars" data-rating="' . esc_attr($rating) . '">';
    
    // 満点の星
    for ($i = 0; $i < $full_stars; $i++) {
        $html .= '<span class="urp-star urp-star-full">★</span>';
    }
    
    // 半分の星
    if ($half_star) {
        $html .= '<span class="urp-star urp-star-half">★</span>';
    }
    
    // 空の星
    for ($i = 0; $i < $empty_stars; $i++) {
        $html .= '<span class="urp-star urp-star-empty">☆</span>';
    }
    
    if ($show_number) {
        $html .= '<span class="urp-rating-number">' . number_format($rating, 1) . '</span>';
    }
    
    $html .= '</span>';
    
    return $html;
}

/**
 * 価格表示フォーマット
 * @param int|float $price
 * @param string $currency
 * @return string
 */
function urp_format_price(int|float $price, string $currency = '円'): string {
    return number_format($price) . $currency;
}

/**
 * 価格帯表示
 * PHP 8.0: match式
 * @param string $range
 * @return string
 */
function urp_format_price_range(string $range): string {
    return match($range) {
        URP_PRICE_RANGE_BUDGET => '〜1,000円',
        URP_PRICE_RANGE_STANDARD => '1,000〜1,500円',
        URP_PRICE_RANGE_PREMIUM => '1,500〜2,000円',
        URP_PRICE_RANGE_LUXURY => '2,000円〜',
        default => '不明'
    };
}

/**
 * 辛さレベル表示
 * @param int $level
 * @return string
 */
function urp_format_spice_level(int $level): string {
    $icons = str_repeat('🌶', min(5, ceil($level / 2)));
    
    $label = match(true) {
        $level <= 1 => '甘口',
        $level <= 3 => '中辛',
        $level <= 5 => '辛口',
        $level <= 7 => '激辛',
        $level <= 10 => '超激辛',
        default => '測定不能'
    };
    
    return sprintf('%s %s', $icons, $label);
}

/**
 * 日付フォーマット
 * @param string $date
 * @param string $format
 * @return string
 */
function urp_format_date(string $date, string $format = ''): string {
    if (!$format) {
        $format = get_option('date_format');
    }
    
    return date_i18n($format, strtotime($date));
}

/**
 * 相対時間表示
 * @param string $date
 * @return string
 */
function urp_time_ago(string $date): string {
    return human_time_diff(strtotime($date), current_time('timestamp')) . '前';
}

/**
 * サニタイズ：テキストフィールド配列
 * @param array<mixed> $array
 * @return array<string>
 */
function urp_sanitize_text_array(array $array): array {
    return array_map('sanitize_text_field', $array);
}

/**
 * サニタイズ：メールアドレス
 * @param string $email
 * @return string
 */
function urp_sanitize_email(string $email): string {
    return sanitize_email($email);
}

/**
 * サニタイズ：URL
 * @param string $url
 * @return string
 */
function urp_sanitize_url(string $url): string {
    return esc_url_raw($url);
}

/**
 * バリデーション：メールアドレス
 * @param string $email
 * @return bool
 */
function urp_validate_email(string $email): bool {
    return is_email($email) !== false;
}

/**
 * バリデーション：URL
 * @param string $url
 * @return bool
 */
function urp_validate_url(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * バリデーション：電話番号（日本）
 * @param string $phone
 * @return bool
 */
function urp_validate_phone(string $phone): bool {
    $pattern = '/^[0-9]{2,4}-?[0-9]{2,4}-?[0-9]{3,4}$/';
    return preg_match($pattern, $phone) === 1;
}

/**
 * 権限チェック
 * @param string $capability
 * @param int|null $user_id
 * @return bool
 */
function urp_user_can(string $capability, ?int $user_id = null): bool {
    if ($user_id === null) {
        return current_user_can($capability);
    }
    
    $user = get_userdata($user_id);
    return $user && user_can($user, $capability);
}

/**
 * レビュー投稿権限チェック
 * @param int|null $user_id
 * @return bool
 */
function urp_can_submit_review(?int $user_id = null): bool {
    if (!is_user_logged_in() && urp_get_option('require_login', true)) {
        return false;
    }
    
    return urp_user_can(URP_CAP_EDIT_REVIEWS, $user_id);
}

/**
 * レビュー編集権限チェック
 * @param int $review_id
 * @param int|null $user_id
 * @return bool
 */
function urp_can_edit_review(int $review_id, ?int $user_id = null): bool {
    $user_id = $user_id ?? get_current_user_id();
    
    // 管理者は常に編集可能
    if (urp_user_can(URP_CAP_MANAGE_REVIEWS, $user_id)) {
        return true;
    }
    
    // 投稿者本人かチェック
    $post = get_post($review_id);
    if ($post && $post->post_author == $user_id) {
        return urp_user_can(URP_CAP_EDIT_REVIEWS, $user_id);
    }
    
    return false;
}

/**
 * Nonceフィールド生成
 * @param string $action
 * @param string $name
 * @param bool $referer
 * @param bool $echo
 * @return string
 */
function urp_nonce_field(
    string $action = URP_NONCE_ACTION,
    string $name = URP_NONCE_KEY,
    bool $referer = true,
    bool $echo = true
): string {
    $field = wp_nonce_field($action, $name, $referer, false);
    
    if ($echo) {
        echo $field;
    }
    
    return $field;
}

/**
 * Nonce検証
 * @param string $nonce
 * @param string $action
 * @return bool
 */
function urp_verify_nonce(string $nonce, string $action = URP_NONCE_ACTION): bool {
    return wp_verify_nonce($nonce, $action) !== false;
}

/**
 * AJAX Nonceチェック
 * @param string $action
 * @return bool
 */
function urp_check_ajax_nonce(string $action = URP_NONCE_ACTION): bool {
    $nonce = $_REQUEST[URP_NONCE_KEY] ?? '';
    
    if (!urp_verify_nonce($nonce, $action)) {
        wp_send_json_error(['message' => __('Security check failed', 'universal-review')]);
        return false;
    }
    
    return true;
}

/**
 * ページネーションHTML生成
 * @param int $total_pages
 * @param int $current_page
 * @param string $base_url
 * @return string
 */
function urp_pagination(int $total_pages, int $current_page = 1, string $base_url = ''): string {
    if ($total_pages <= 1) {
        return '';
    }
    
    $args = [
        'base' => $base_url ?: get_pagenum_link(1) . '%_%',
        'format' => 'page/%#%/',
        'total' => $total_pages,
        'current' => $current_page,
        'show_all' => false,
        'end_size' => 1,
        'mid_size' => 2,
        'prev_next' => true,
        'prev_text' => __('&laquo; Previous', 'universal-review'),
        'next_text' => __('Next &raquo;', 'universal-review'),
        'type' => 'plain',
        'add_args' => false,
        'add_fragment' => '',
        'before_page_number' => '',
        'after_page_number' => ''
    ];
    
    return paginate_links($args);
}

/**
 * 画像URL取得（サイズ指定）
 * @param int $attachment_id
 * @param string|array<int> $size
 * @return string
 */
function urp_get_image_url(int $attachment_id, string|array $size = 'full'): string {
    return wp_get_attachment_image_url($attachment_id, $size) ?: '';
}

/**
 * デフォルト画像URL取得
 * @param string $type
 * @return string
 */
function urp_get_default_image(string $type = 'review'): string {
    $images = [
        'review' => 'default-review.jpg',
        'user' => 'default-avatar.jpg',
        'curry' => 'default-curry.jpg',
        'ramen' => 'default-ramen.jpg',
    ];
    
    $filename = $images[$type] ?? 'default.jpg';
    
    return URP_IMAGES_URL . $filename;
}

/**
 * ファイルアップロード処理
 * @param array<string, mixed> $file
 * @param array<string> $allowed_types
 * @return int|WP_Error
 */
function urp_handle_upload(array $file, array $allowed_types = []): int|WP_Error {
    if (!$allowed_types) {
        $allowed_types = URP_ALLOWED_IMAGE_TYPES;
    }
    
    $filetype = wp_check_filetype($file['name']);
    
    if (!in_array($filetype['ext'], $allowed_types, true)) {
        return new WP_Error('invalid_file_type', __('Invalid file type', 'universal-review'));
    }
    
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $upload = wp_handle_upload($file, ['test_form' => false]);
    
    if (isset($upload['error'])) {
        return new WP_Error('upload_failed', $upload['error']);
    }
    
    $attachment = [
        'post_mime_type' => $upload['type'],
        'post_title' => sanitize_file_name($file['name']),
        'post_content' => '',
        'post_status' => 'inherit'
    ];
    
    $attachment_id = wp_insert_attachment($attachment, $upload['file']);
    
    if (is_wp_error($attachment_id)) {
        return $attachment_id;
    }
    
    $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    wp_update_attachment_metadata($attachment_id, $metadata);
    
    return $attachment_id;
}

/**
 * CSVエクスポート
 * @param array<array<string, mixed>> $data
 * @param string $filename
 * @return void
 */
function urp_export_csv(array $data, string $filename = 'export.csv'): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM追加（Excel対応）
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    if (empty($data)) {
        fclose($output);
        return;
    }
    
    // ヘッダー行
    fputcsv($output, array_keys(reset($data)));
    
    // データ行
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * デバッグログ出力
 * @param mixed $data
 * @param string $label
 * @return void
 */
function urp_log(mixed $data, string $label = ''): void {
    if (!URP_DEBUG || !URP_DEBUG_LOG) {
        return;
    }
    
    $message = $label ? "[URP {$label}] " : '[URP] ';
    
    if (is_array($data) || is_object($data)) {
        $message .= print_r($data, true);
    } else {
        $message .= $data;
    }
    
    error_log($message);
}

/**
 * メール送信
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param array<string> $headers
 * @return bool
 */
function urp_send_email(
    string $to,
    string $subject,
    string $message,
    array $headers = []
): bool {
    $default_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>',
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * レビューURL取得
 * @param int $review_id
 * @return string
 */
function urp_get_review_url(int $review_id): string {
    return get_permalink($review_id) ?: '';
}

/**
 * レビューアーURL取得
 * @param int $user_id
 * @return string
 */
function urp_get_reviewer_url(int $user_id): string {
    return add_query_arg('reviewer', $user_id, urp_get_reviews_page_url());
}

/**
 * レビュー一覧ページURL取得
 * @return string
 */
function urp_get_reviews_page_url(): string {
    $page_id = urp_get_option('page_reviews_archive');
    
    if ($page_id) {
        return get_permalink($page_id) ?: home_url('/reviews/');
    }
    
    return get_post_type_archive_link(URP_POST_TYPE) ?: home_url('/reviews/');
}

/**
 * レビュー投稿ページURL取得
 * @return string
 */
function urp_get_submit_review_url(): string {
    $page_id = urp_get_option('page_submit_form');
    
    if ($page_id) {
        return get_permalink($page_id) ?: home_url('/submit-review/');
    }
    
    return home_url('/submit-review/');
}

/**
 * キャッシュ取得
 * @param string $key
 * @param string $group
 * @return mixed
 */
function urp_cache_get(string $key, string $group = URP_CACHE_GROUP): mixed {
    return wp_cache_get($key, $group);
}

/**
 * キャッシュ保存
 * @param string $key
 * @param mixed $data
 * @param string $group
 * @param int $expire
 * @return bool
 */
function urp_cache_set(
    string $key,
    mixed $data,
    string $group = URP_CACHE_GROUP,
    int $expire = URP_DEFAULT_CACHE_EXPIRY
): bool {
    return wp_cache_set($key, $data, $group, $expire);
}

/**
 * キャッシュ削除
 * @param string $key
 * @param string $group
 * @return bool
 */
function urp_cache_delete(string $key, string $group = URP_CACHE_GROUP): bool {
    return wp_cache_delete($key, $group);
}

/**
 * キャッシュフラッシュ
 * @return bool
 */
function urp_cache_flush(): bool {
    return wp_cache_flush();
}