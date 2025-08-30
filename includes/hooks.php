<?php
/**
 * Universal Review Platform - Hooks Registration
 * 
 * WordPressフックの登録と管理
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
 * =============================================================================
 * アクションフック
 * =============================================================================
 */

/**
 * プラグイン初期化
 */
add_action('plugins_loaded', function(): void {
    // テキストドメイン読み込み
    load_plugin_textdomain(
        URP_TEXT_DOMAIN,
        false,
        dirname(URP_PLUGIN_BASENAME) . '/languages'
    );
    
    // 初期化フック実行
    do_action('urp_loaded');
}, 10);

/**
 * WordPress初期化
 */
add_action('init', function(): void {
    // セッション開始（必要な場合）
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    
    // リライトルール追加
    add_rewrite_rule(
        'reviews/([^/]+)/page/([0-9]+)/?$',
        'index.php?review_category=$matches[1]&paged=$matches[2]',
        'top'
    );
    
    // カスタムエンドポイント追加
    add_rewrite_endpoint('reviewer', EP_PAGES);
    add_rewrite_endpoint('review-map', EP_ROOT);
    
    // フラッシュメッセージ処理
    if (isset($_SESSION['urp_flash_message'])) {
        add_action('wp_footer', function(): void {
            $message = $_SESSION['urp_flash_message'];
            unset($_SESSION['urp_flash_message']);
            echo '<div class="urp-flash-message">' . esc_html($message) . '</div>';
        });
    }
}, 0);

/**
 * 管理画面初期化
 */
add_action('admin_init', function(): void {
    // 管理者以外の管理画面アクセス制限
    if (!current_user_can('manage_options') && !wp_doing_ajax()) {
        $allowed_pages = ['profile.php', 'admin-ajax.php'];
        $current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
        
        if (!in_array($current_page, $allowed_pages, true)) {
            wp_redirect(home_url());
            exit;
        }
    }
    
    // アップデートチェック
    if (get_option('urp_version') !== URP_VERSION) {
        do_action('urp_upgrade_needed');
    }
});

/**
 * 管理メニュー追加
 */
add_action('admin_menu', function(): void {
    // メインメニュー
/*
    add_menu_page(
        __('Reviews', 'universal-review'),
        __('Reviews', 'universal-review'),
        URP_CAP_MANAGE_REVIEWS,
        'urp-dashboard',
        'urp_render_dashboard_page',
        'dashicons-star-filled',
        25
    );
    
    // サブメニュー
    add_submenu_page(
        'urp-dashboard',
        __('All Reviews', 'universal-review'),
        __('All Reviews', 'universal-review'),
        URP_CAP_EDIT_REVIEWS,
        'edit.php?post_type=' . URP_POST_TYPE
    );
    
    add_submenu_page(
        'urp-dashboard',
        __('Add New Review', 'universal-review'),
        __('Add New', 'universal-review'),
        URP_CAP_EDIT_REVIEWS,
        'post-new.php?post_type=' . URP_POST_TYPE
    );
    
    add_submenu_page(
        'urp-dashboard',
        __('Categories', 'universal-review'),
        __('Categories', 'universal-review'),
        URP_CAP_MANAGE_REVIEWS,
        'edit-tags.php?taxonomy=' . URP_CATEGORY_TAXONOMY . '&post_type=' . URP_POST_TYPE
    );
    
    add_submenu_page(
        'urp-dashboard',
        __('Settings', 'universal-review'),
        __('Settings', 'universal-review'),
        URP_CAP_MANAGE_SETTINGS,
        'urp-settings',
        'urp_render_settings_page'
    );
    
    add_submenu_page(
        'urp-dashboard',
        __('Analytics', 'universal-review'),
        __('Analytics', 'universal-review'),
        URP_CAP_VIEW_ANALYTICS,
        'urp-analytics',
        'urp_render_analytics_page'
    );
*/
});

/**
 * スクリプト/スタイル登録（管理画面）
 */
add_action('admin_enqueue_scripts', function(string $hook): void {
    // グローバルスタイル
    wp_enqueue_style(
        'urp-admin-global',
        urp_asset_url('admin-global.css', 'admin'),
        [],
        URP_VERSION
    );
    
    // ページ固有のアセット
    if (str_contains($hook, 'urp-')) {
        wp_enqueue_style(
            'urp-admin',
            urp_asset_url('admin.css', 'admin'),
            [],
            URP_VERSION
        );
        
        wp_enqueue_script(
            'urp-admin',
            urp_asset_url('admin.js', 'admin'),
            ['jquery', 'wp-api', 'wp-i18n'],
            URP_VERSION,
            true
        );
        
        wp_localize_script('urp-admin', 'urpAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => home_url('/wp-json/' . URP_API_NAMESPACE . '/'),
            'nonce' => wp_create_nonce(URP_NONCE_ACTION),
            'reviewType' => urp_get_review_type(),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this review?', 'universal-review'),
                'saving' => __('Saving...', 'universal-review'),
                'saved' => __('Saved', 'universal-review'),
                'error' => __('An error occurred', 'universal-review'),
            ]
        ]);
    }
    
    // レビュー投稿タイプページ
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        global $post;
        if ($post && $post->post_type === URP_POST_TYPE) {
            wp_enqueue_media();
            wp_enqueue_script(
                'urp-review-editor',
                urp_asset_url('review-editor.js', 'admin'),
                ['jquery', 'wp-api'],
                URP_VERSION,
                true
            );
        }
    }
});

/**
 * スクリプト/スタイル登録（フロントエンド）
 */
add_action('wp_enqueue_scripts', function(): void {
    // メインスタイル
    wp_enqueue_style(
        'urp-public',
        urp_asset_url('style.css', 'public'),
        [],
        URP_VERSION
    );
    
    // レスポンシブスタイル
    wp_enqueue_style(
        'urp-responsive',
        urp_asset_url('responsive.css', 'public'),
        ['urp-public'],
        URP_VERSION,
        'screen and (max-width: 768px)'
    );
    
    // メインスクリプト
    wp_enqueue_script(
        'urp-public',
        urp_asset_url('main.js', 'public'),
        ['jquery'],
        URP_VERSION,
        true
    );
    
    // ローカライズ
    wp_localize_script('urp-public', 'urpPublic', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'apiUrl' => home_url('/wp-json/' . URP_API_NAMESPACE . '/'),
        'nonce' => wp_create_nonce(URP_NONCE_ACTION),
        'reviewType' => urp_get_review_type(),
        'isLoggedIn' => is_user_logged_in(),
        'userId' => get_current_user_id(),
        'i18n' => [
            'loading' => __('Loading...', 'universal-review'),
            'loadMore' => __('Load More', 'universal-review'),
            'noMore' => __('No more reviews', 'universal-review'),
            'error' => __('An error occurred', 'universal-review'),
        ]
    ]);
    
    // レビュー詳細ページ
    if (is_singular(URP_POST_TYPE)) {
        wp_enqueue_script(
            'urp-review-single',
            urp_asset_url('review-single.js', 'public'),
            ['urp-public'],
            URP_VERSION,
            true
        );
        
        // 構造化データ
        add_action('wp_head', 'urp_output_structured_data');
    }
    
    // レビュー投稿フォーム
    if (is_page(urp_get_option('page_submit_form'))) {
        wp_enqueue_script(
            'urp-review-form',
            urp_asset_url('review-form.js', 'public'),
            ['jquery', 'wp-api'],
            URP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'urp-review-form',
            urp_asset_url('review-form.css', 'public'),
            ['urp-public'],
            URP_VERSION
        );
    }
    
    // Google Maps（設定で有効な場合）
    if (urp_get_option('enable_maps') && urp_get_option('google_maps_api_key')) {
        wp_enqueue_script(
            'google-maps',
            URP_GOOGLE_MAPS_API_URL . 'js?key=' . urp_get_option('google_maps_api_key'),
            [],
            null,
            true
        );
        
        wp_enqueue_script(
            'urp-map',
            urp_asset_url('map.js', 'public'),
            ['google-maps', 'urp-public'],
            URP_VERSION,
            true
        );
    }
});

/**
 * AJAX処理登録
 */
// ログインユーザー用
add_action('wp_ajax_urp_search_reviews', 'urp_ajax_search_reviews');
add_action('wp_ajax_urp_load_more_reviews', 'urp_ajax_load_more_reviews');
add_action('wp_ajax_urp_submit_review', 'urp_ajax_submit_review');
add_action('wp_ajax_urp_update_rating', 'urp_ajax_update_rating');
add_action('wp_ajax_urp_vote_review', 'urp_ajax_vote_review');
add_action('wp_ajax_urp_report_review', 'urp_ajax_report_review');
add_action('wp_ajax_urp_upload_image', 'urp_ajax_upload_image');

// 非ログインユーザー用
add_action('wp_ajax_nopriv_urp_search_reviews', 'urp_ajax_search_reviews');
add_action('wp_ajax_nopriv_urp_load_more_reviews', 'urp_ajax_load_more_reviews');
add_action('wp_ajax_nopriv_urp_vote_review', 'urp_ajax_vote_review');

/**
 * REST API初期化
 */
add_action('rest_api_init', function(): void {
    // APIルート登録
    register_rest_route(URP_API_NAMESPACE, '/reviews', [
        [
            'methods' => 'GET',
            'callback' => 'urp_api_get_reviews',
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
                'category' => [
                    'type' => 'string',
                ],
                'rating' => [
                    'type' => 'number',
                ],
            ],
        ],
        [
            'methods' => 'POST',
            'callback' => 'urp_api_create_review',
            'permission_callback' => fn() => current_user_can(URP_CAP_EDIT_REVIEWS),
        ],
    ]);
    
    register_rest_route(URP_API_NAMESPACE, '/reviews/(?P<id>\d+)', [
        [
            'methods' => 'GET',
            'callback' => 'urp_api_get_review',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => 'PUT',
            'callback' => 'urp_api_update_review',
            'permission_callback' => fn($request) => urp_can_edit_review($request['id']),
        ],
        [
            'methods' => 'DELETE',
            'callback' => 'urp_api_delete_review',
            'permission_callback' => fn($request) => urp_can_edit_review($request['id']),
        ],
    ]);
    
    register_rest_route(URP_API_NAMESPACE, '/rankings', [
        'methods' => 'GET',
        'callback' => 'urp_api_get_rankings',
        'permission_callback' => '__return_true',
        'args' => [
            'type' => [
                'type' => 'string',
                'default' => 'rating',
                'enum' => ['rating', 'views', 'recent'],
            ],
            'limit' => [
                'type' => 'integer',
                'default' => 10,
            ],
        ],
    ]);
});

/**
 * Cronジョブ登録
 */
add_action('urp_daily_cron', function(): void {
    // レビュー統計更新
    urp_update_review_statistics();
    
    // 期限切れデータクリーンアップ
    urp_cleanup_expired_data();
    
    // レビュアーランク更新
    urp_update_reviewer_ranks();
    
    // キャッシュクリア
    urp_cache_flush();
});

add_action('urp_hourly_cron', function(): void {
    // スパムチェック
    urp_check_spam_reviews();
    
    // 一時ファイルクリーンアップ
    urp_cleanup_temp_files();
});

/**
 * =============================================================================
 * フィルターフック
 * =============================================================================
 */

/**
 * タイトルカスタマイズ
 */
add_filter('the_title', function(string $title, int $id): string {
    if (get_post_type($id) !== URP_POST_TYPE) {
        return $title;
    }
    
    // レビュータイプアイコン追加
    $icon = match(urp_get_review_type()) {
        'curry' => '🍛',
        'ramen' => '🍜',
        'sushi' => '🍣',
        default => '⭐'
    };
    
    return $icon . ' ' . $title;
}, 10, 2);

/**
 * コンテンツフィルター
 */
add_filter('the_content', function(string $content): string {
    if (get_post_type() !== URP_POST_TYPE || !is_singular()) {
        return $content;
    }
    
    // レビュー情報を追加
    ob_start();
    urp_get_template('review-meta.php');
    $meta_html = ob_get_clean();
    
    return $meta_html . $content;
}, 10);

/**
 * 抜粋の長さ
 */
add_filter('excerpt_length', function(int $length): int {
    if (get_post_type() === URP_POST_TYPE) {
        return 30;
    }
    return $length;
}, 10);

/**
 * クエリ変更
 */
add_filter('pre_get_posts', function(WP_Query $query): void {
    if (!$query->is_main_query() || is_admin()) {
        return;
    }
    
    // レビューアーカイブページ
    if ($query->is_post_type_archive(URP_POST_TYPE)) {
        $query->set('posts_per_page', urp_get_option('items_per_page', 10));
        
        // デフォルトソート
        if (!$query->get('orderby')) {
            $default_sort = urp_get_option('default_sort', 'date');
            
            switch ($default_sort) {
                case 'rating':
                    $query->set('meta_key', URP_META_OVERALL_RATING);
                    $query->set('orderby', 'meta_value_num');
                    $query->set('order', 'DESC');
                    break;
                    
                case 'popular':
                    $query->set('meta_key', URP_META_VIEW_COUNT);
                    $query->set('orderby', 'meta_value_num');
                    $query->set('order', 'DESC');
                    break;
            }
        }
        
        // フィルター適用
        if (isset($_GET['category'])) {
            $query->set('tax_query', [
                [
                    'taxonomy' => URP_CATEGORY_TAXONOMY,
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['category']),
                ]
            ]);
        }
        
        if (isset($_GET['min_rating'])) {
            $query->set('meta_query', [
                [
                    'key' => URP_META_OVERALL_RATING,
                    'value' => floatval($_GET['min_rating']),
                    'compare' => '>=',
                    'type' => 'DECIMAL',
                ]
            ]);
        }
    }
});

/**
 * 投稿クラス追加
 */
add_filter('post_class', function(array $classes, array $class, int $post_id): array {
    if (get_post_type($post_id) === URP_POST_TYPE) {
        $classes[] = 'urp-review';
        $classes[] = 'urp-review-' . urp_get_review_type();
        
        $rating = get_post_meta($post_id, URP_META_OVERALL_RATING, true);
        if ($rating) {
            $classes[] = 'urp-rating-' . round($rating);
        }
    }
    
    return $classes;
}, 10, 3);

/**
 * ボディクラス追加
 */
add_filter('body_class', function(array $classes): array {
    if (is_singular(URP_POST_TYPE) || is_post_type_archive(URP_POST_TYPE)) {
        $classes[] = 'urp-page';
        $classes[] = 'urp-' . urp_get_review_type();
    }
    
    return $classes;
});

/**
 * 管理画面カラム追加
 */
add_filter('manage_' . URP_POST_TYPE . '_posts_columns', function(array $columns): array {
    $new_columns = [];
    
    foreach ($columns as $key => $title) {
        if ($key === 'title') {
            $new_columns[$key] = $title;
            $new_columns['rating'] = __('Rating', 'universal-review');
            $new_columns['review_type'] = __('Type', 'universal-review');
        } elseif ($key === 'date') {
            $new_columns['views'] = __('Views', 'universal-review');
            $new_columns[$key] = $title;
        } else {
            $new_columns[$key] = $title;
        }
    }
    
    return $new_columns;
});

/**
 * 管理画面カラムデータ表示
 */
add_action('manage_' . URP_POST_TYPE . '_posts_custom_column', function(string $column, int $post_id): void {
    switch ($column) {
        case 'rating':
            $rating = get_post_meta($post_id, URP_META_OVERALL_RATING, true);
            if ($rating) {
                echo urp_rating_stars(floatval($rating), true);
            } else {
                echo '—';
            }
            break;
            
        case 'review_type':
            $type = get_post_meta($post_id, URP_META_REVIEW_TYPE, true);
            echo match($type) {
                'curry' => '🍛 ' . __('Curry', 'universal-review'),
                'ramen' => '🍜 ' . __('Ramen', 'universal-review'),
                'sushi' => '🍣 ' . __('Sushi', 'universal-review'),
                default => '⭐ ' . ucfirst($type)
            };
            break;
            
        case 'views':
            $views = get_post_meta($post_id, URP_META_VIEW_COUNT, true) ?: 0;
            echo number_format($views);
            break;
    }
}, 10, 2);

/**
 * ソート可能カラム設定
 */
add_filter('manage_edit-' . URP_POST_TYPE . '_sortable_columns', function(array $columns): array {
    $columns['rating'] = 'rating';
    $columns['views'] = 'views';
    $columns['review_type'] = 'review_type';
    
    return $columns;
});

/**
 * カスタムソート実装
 */
add_action('pre_get_posts', function(WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('post_type') !== URP_POST_TYPE) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    switch ($orderby) {
        case 'rating':
            $query->set('meta_key', URP_META_OVERALL_RATING);
            $query->set('orderby', 'meta_value_num');
            break;
            
        case 'views':
            $query->set('meta_key', URP_META_VIEW_COUNT);
            $query->set('orderby', 'meta_value_num');
            break;
            
        case 'review_type':
            $query->set('meta_key', URP_META_REVIEW_TYPE);
            $query->set('orderby', 'meta_value');
            break;
    }
});

/**
 * 管理画面フィルター追加
 */
add_action('restrict_manage_posts', function(string $post_type): void {
    if ($post_type !== URP_POST_TYPE) {
        return;
    }
    
    // レビュータイプフィルター
    $review_types = [
        'curry' => __('Curry', 'universal-review'),
        'ramen' => __('Ramen', 'universal-review'),
        'sushi' => __('Sushi', 'universal-review'),
    ];
    
    $current = $_GET['review_type'] ?? '';
    
    echo '<select name="review_type">';
    echo '<option value="">' . __('All Types', 'universal-review') . '</option>';
    
    foreach ($review_types as $value => $label) {
        $selected = selected($current, $value, false);
        echo "<option value='{$value}' {$selected}>{$label}</option>";
    }
    
    echo '</select>';
    
    // 評価フィルター
    $current_rating = $_GET['min_rating'] ?? '';
    
    echo '<select name="min_rating">';
    echo '<option value="">' . __('All Ratings', 'universal-review') . '</option>';
    
    for ($i = 5; $i >= 1; $i--) {
        $selected = selected($current_rating, $i, false);
        echo "<option value='{$i}' {$selected}>★{$i}以上</option>";
    }
    
    echo '</select>';
});

/**
 * 管理画面フィルタークエリ適用
 */
add_filter('parse_query', function(WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('post_type') !== URP_POST_TYPE) {
        return;
    }
    
    // レビュータイプフィルター
    if (!empty($_GET['review_type'])) {
        $query->set('meta_key', URP_META_REVIEW_TYPE);
        $query->set('meta_value', sanitize_text_field($_GET['review_type']));
    }
    
    // 最小評価フィルター
    if (!empty($_GET['min_rating'])) {
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key' => URP_META_OVERALL_RATING,
            'value' => intval($_GET['min_rating']),
            'compare' => '>=',
            'type' => 'NUMERIC',
        ];
        $query->set('meta_query', $meta_query);
    }
});

/**
 * 投稿保存時の処理
 */
add_action('save_post_' . URP_POST_TYPE, function(int $post_id, WP_Post $post, bool $update): void {
    // 自動保存時はスキップ
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Nonce検証
    if (!isset($_POST[URP_NONCE_KEY]) || !urp_verify_nonce($_POST[URP_NONCE_KEY])) {
        return;
    }
    
    // メタデータ保存
    if (isset($_POST['urp_overall_rating'])) {
        update_post_meta($post_id, URP_META_OVERALL_RATING, floatval($_POST['urp_overall_rating']));
    }
    
    if (isset($_POST['urp_price'])) {
        update_post_meta($post_id, URP_META_PRICE, sanitize_text_field($_POST['urp_price']));
    }
    
    if (isset($_POST['urp_location'])) {
        update_post_meta($post_id, URP_META_LOCATION, sanitize_text_field($_POST['urp_location']));
    }
    
    // レビュータイプ別の処理
    $review_type = urp_get_review_type();
    
    if ($review_type === 'curry' && isset($_POST['urp_spiciness'])) {
        update_post_meta($post_id, 'urp_spiciness', intval($_POST['urp_spiciness']));
    }
    
    // キャッシュクリア
    urp_cache_delete('review_' . $post_id);
    
    // フック実行
    do_action('urp_review_saved', $post_id, $post, $update);
}, 10, 3);

/**
 * 投稿削除時の処理
 */
add_action('before_delete_post', function(int $post_id): void {
    if (get_post_type($post_id) !== URP_POST_TYPE) {
        return;
    }
    
    // 関連データ削除
    global $wpdb;
    
    // 評価データ削除
    $wpdb->delete(
        $wpdb->prefix . URP_TABLE_RATINGS,
        ['review_id' => $post_id],
        ['%d']
    );
    
    // 画像データ削除
    $wpdb->delete(
        $wpdb->prefix . URP_TABLE_IMAGES,
        ['review_id' => $post_id],
        ['%d']
    );
    
    // キャッシュクリア
    urp_cache_delete('review_' . $post_id);
    
    // フック実行
    do_action('urp_review_deleted', $post_id);
});

/**
 * ユーザー登録時の処理
 */
add_action('user_register', function(int $user_id): void {
    // レビュー権限付与
    $user = new WP_User($user_id);
    $user->add_cap(URP_CAP_EDIT_REVIEWS);
    
    // 初期ランク設定
    update_user_meta($user_id, 'urp_reviewer_rank', URP_RANK_BEGINNER);
    update_user_meta($user_id, 'urp_review_count', 0);
    update_user_meta($user_id, 'urp_trust_score', 0);
    
    // ウェルカムメール送信
    if (urp_get_option('send_welcome_email', true)) {
        $user_data = get_userdata($user_id);
        $subject = sprintf(__('Welcome to %s Reviews!', 'universal-review'), get_bloginfo('name'));
        $message = sprintf(
            __('Hi %s, welcome to our review platform! Start sharing your reviews today.', 'universal-review'),
            $user_data->display_name
        );
        
        urp_send_email($user_data->user_email, $subject, $message);
    }
});

/**
 * ログイン時の処理
 */
add_action('wp_login', function(string $user_login, WP_User $user): void {
    // 最終ログイン日時を記録
    update_user_meta($user->ID, 'urp_last_login', current_time('mysql'));
    
    // ログインボーナス（デイリー）
    $last_bonus = get_user_meta($user->ID, 'urp_last_login_bonus', true);
    
    if (!$last_bonus || date('Y-m-d', strtotime($last_bonus)) !== date('Y-m-d')) {
        $trust_score = get_user_meta($user->ID, 'urp_trust_score', true) ?: 0;
        update_user_meta($user->ID, 'urp_trust_score', $trust_score + 1);
        update_user_meta($user->ID, 'urp_last_login_bonus', current_time('mysql'));
    }
}, 10, 2);

/**
 * コメント投稿時の処理
 */
add_action('comment_post', function(int $comment_id, int|string $comment_approved, array $commentdata): void {
    $post_id = $commentdata['comment_post_ID'];
    
    if (get_post_type($post_id) !== URP_POST_TYPE) {
        return;
    }
    
    // レビューへのコメント通知
    if ($comment_approved === 1) {
        $post = get_post($post_id);
        $author = get_userdata($post->post_author);
        
        if ($author && $author->ID !== $commentdata['user_id']) {
            $subject = sprintf(
                __('New comment on your review: %s', 'universal-review'),
                $post->post_title
            );
            
            $message = sprintf(
                __('%s commented on your review. Check it out: %s', 'universal-review'),
                $commentdata['comment_author'],
                get_permalink($post_id) . '#comment-' . $comment_id
            );
            
            urp_send_email($author->user_email, $subject, $message);
        }
    }
}, 10, 3);

/**
 * =============================================================================
 * カスタムフック定義
 * =============================================================================
 */

/**
 * レビュー表示前
 */
do_action('urp_before_review_display', get_the_ID());

/**
 * レビュー表示後
 */
do_action('urp_after_review_display', get_the_ID());

/**
 * レビューフォーム前
 */
do_action('urp_before_review_form');

/**
 * レビューフォーム後
 */
do_action('urp_after_review_form');

/**
 * ランキング表示前
 */
do_action('urp_before_rankings');

/**
 * ランキング表示後
 */
do_action('urp_after_rankings');

/**
 * =============================================================================
 * ショートコード登録
 * =============================================================================
 */

/**
 * レビューリストショートコード
 */
add_shortcode('urp_reviews', function(array $atts = []): string {
    $atts = shortcode_atts([
        'count' => 10,
        'category' => '',
        'type' => urp_get_review_type(),
        'sort' => 'date',
        'template' => 'review-list.php',
    ], $atts);
    
    $args = [
        'post_type' => URP_POST_TYPE,
        'posts_per_page' => intval($atts['count']),
        'meta_key' => URP_META_REVIEW_TYPE,
        'meta_value' => sanitize_text_field($atts['type']),
    ];
    
    if ($atts['category']) {
        $args['tax_query'] = [
            [
                'taxonomy' => URP_CATEGORY_TAXONOMY,
                'field' => 'slug',
                'terms' => sanitize_text_field($atts['category']),
            ]
        ];
    }
    
    // ソート設定
    switch ($atts['sort']) {
        case 'rating':
            $args['meta_key'] = URP_META_OVERALL_RATING;
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
            break;
            
        case 'popular':
            $args['meta_key'] = URP_META_VIEW_COUNT;
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
            break;
            
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
    }
    
    $query = new WP_Query($args);
    
    ob_start();
    
    if ($query->have_posts()) {
        urp_get_template($atts['template'], ['query' => $query], false);
    } else {
        echo '<p>' . __('No reviews found.', 'universal-review') . '</p>';
    }
    
    wp_reset_postdata();
    
    return ob_get_clean();
});

/**
 * レビューフォームショートコード
 */
add_shortcode('urp_review_form', function(array $atts = []): string {
    if (!urp_can_submit_review()) {
        return '<p>' . __('You must be logged in to submit a review.', 'universal-review') . '</p>';
    }
    
    $atts = shortcode_atts([
        'type' => urp_get_review_type(),
        'category' => '',
        'template' => 'review-form.php',
    ], $atts);
    
    ob_start();
    urp_get_template($atts['template'], ['atts' => $atts], false);
    return ob_get_clean();
});

/**
 * ランキングショートコード
 */
add_shortcode('urp_rankings', function(array $atts = []): string {
    $atts = shortcode_atts([
        'type' => 'rating',
        'count' => 10,
        'category' => '',
        'template' => 'rankings.php',
    ], $atts);
    
    if (!urp_reviews()) {
        return '';
    }
    
    $rankings = urp_reviews()->get_rankings(
        sanitize_text_field($atts['type']),
        intval($atts['count'])
    );
    
    ob_start();
    urp_get_template($atts['template'], ['rankings' => $rankings, 'atts' => $atts], false);
    return ob_get_clean();
});

/**
 * レビューマップショートコード
 */
add_shortcode('urp_review_map', function(array $atts = []): string {
    if (!urp_get_option('enable_maps') || !urp_get_option('google_maps_api_key')) {
        return '<p>' . __('Map is not available.', 'universal-review') . '</p>';
    }
    
    $atts = shortcode_atts([
        'height' => '400px',
        'zoom' => 12,
        'center' => '35.6762,139.6503', // Tokyo
        'template' => 'review-map.php',
    ], $atts);
    
    ob_start();
    urp_get_template($atts['template'], ['atts' => $atts], false);
    return ob_get_clean();
});

/**
 * レビュー検索ショートコード
 */
add_shortcode('urp_search', function(array $atts = []): string {
    $atts = shortcode_atts([
        'placeholder' => __('Search reviews...', 'universal-review'),
        'button_text' => __('Search', 'universal-review'),
        'template' => 'search-form.php',
    ], $atts);
    
    ob_start();
    urp_get_template($atts['template'], ['atts' => $atts], false);
    return ob_get_clean();
});

/**
 * レビュアーリストショートコード
 */
add_shortcode('urp_reviewers', function(array $atts = []): string {
    $atts = shortcode_atts([
        'count' => 10,
        'orderby' => 'review_count',
        'order' => 'DESC',
        'template' => 'reviewer-list.php',
    ], $atts);
    
    global $wpdb;
    
    $query = $wpdb->prepare(
        "SELECT u.ID, u.display_name, u.user_email,
                COUNT(p.ID) as review_count,
                AVG(pm.meta_value) as avg_rating
         FROM {$wpdb->users} u
         LEFT JOIN {$wpdb->posts} p ON u.ID = p.post_author AND p.post_type = %s
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
         GROUP BY u.ID
         HAVING review_count > 0
         ORDER BY {$atts['orderby']} {$atts['order']}
         LIMIT %d",
        URP_POST_TYPE,
        URP_META_OVERALL_RATING,
        intval($atts['count'])
    );
    
    $reviewers = $wpdb->get_results($query);
    
    ob_start();
    urp_get_template($atts['template'], ['reviewers' => $reviewers, 'atts' => $atts], false);
    return ob_get_clean();
});