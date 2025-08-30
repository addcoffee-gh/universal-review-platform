<?php
/**
 * Plugin Name: Universal Review Platform
 * Plugin URI: https://github.com/addcoffee-gh/universal-review-platform
 * Description: エンタープライズレベルの汎用レビュープラットフォーム。専門プラグインと連携して業態特化も可能
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Your Company
 * License: GPL v2 or later
 * Text Domain: universal-review
 */

if (!defined('ABSPATH')) {
    exit;
}

// メインファイルのパスのみを定義（これだけは必要）
define('URP_MAIN_FILE', __FILE__);

// 定数ファイルを読み込み（すべての定数はここで管理）
require_once dirname(__FILE__) . '/includes/constants.php';

// オートローダー
if (file_exists(URP_PLUGIN_DIR . 'core/class-loader.php')) {
    require_once URP_PLUGIN_DIR . 'core/class-loader.php';
    if (class_exists('URP_Loader')) {
        // spl_autoload_register(['URP_Loader', 'autoload']);
        URP_Loader::register();
    }
}
        
        // 実装状況管理ダッシュボード
        if (file_exists(URP_PLUGIN_DIR . 'admin/class-implementation-status.php')) {
            require_once URP_PLUGIN_DIR . 'admin/class-implementation-status.php';
        }

/**
 * メインプラグインクラス
 */
class Universal_Review_Platform {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * コンポーネント格納
     */
    private $components = [];
    
    /**
     * サイトモード
     */
    private $site_mode = 'hybrid';
    
    /**
     * 依存ファイル読み込み済みフラグ
     */
    private $dependencies_loaded = false;
    
    /**
     * シングルトンインスタンス取得
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->load_site_mode();
    }
    
    /**
     * 依存ファイル読み込み
     */
    private function load_dependencies() {
        if ($this->dependencies_loaded) {
            return;
        }
        
        // ヘルパー関数
        if (file_exists(URP_PLUGIN_DIR . 'includes/helpers.php')) {
            require_once URP_PLUGIN_DIR . 'includes/helpers.php';
        }
        
        // フック定義
        if (file_exists(URP_PLUGIN_DIR . 'includes/hooks.php')) {
            require_once URP_PLUGIN_DIR . 'includes/hooks.php';
        }
        
        // Cronジョブ
        if (file_exists(URP_PLUGIN_DIR . 'includes/cron-jobs.php')) {
            require_once URP_PLUGIN_DIR . 'includes/cron-jobs.php';
        }

        $this->dependencies_loaded = true;
    }
    
    /**
     * サイトモード読み込み
     */
    private function load_site_mode() {
        $this->site_mode = get_option('urp_site_mode', 'hybrid');
        
        // サイトモード管理クラスが存在すれば初期化
        if (class_exists('URP_Site_Mode')) {
            $this->components['site_mode'] = new URP_Site_Mode();
        }
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        // 基本フック
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
        
        // 管理画面
        if (is_admin()) {
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_init', [$this, 'admin_init']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        }
        
        // フロントエンド
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'public_enqueue_scripts']);
        }
        
        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // AJAX
        add_action('wp_ajax_urp_process', [$this, 'ajax_handler']);
        add_action('wp_ajax_nopriv_urp_process', [$this, 'ajax_handler']);
        
        // Cron
        add_action('urp_daily_cron', [$this, 'daily_cron']);
        add_action('urp_hourly_cron', [$this, 'hourly_cron']);
    }
    
    /**
     * 初期化処理
     */
    public function init() {
        // 多言語対応
        load_plugin_textdomain(
            URP_TEXT_DOMAIN,
            false,
            dirname(URP_PLUGIN_BASENAME) . '/languages'
        );
        
        // カスタム投稿タイプ登録
        $this->register_post_types();
        
        // タクソノミー登録
        $this->register_taxonomies();
        
        // ショートコード登録
        $this->register_shortcodes();
        
        // リライトルール
        $this->add_rewrite_rules();
        
        // コンポーネント初期化
        $this->init_components();
    }
    
    /**
     * カスタム投稿タイプ登録（サイトモード対応）
     */
    private function register_post_types() {
        // 店舗レビュー（shop_only または hybrid）
        if ($this->site_mode === 'shop_only' || $this->site_mode === 'hybrid') {
            register_post_type('shop_review', [
                'labels' => [
                    'name'                  => '店舗レビュー',
                    'singular_name'         => '店舗レビュー',
                    'menu_name'            => '店舗レビュー',
                    'add_new'              => '新規追加',
                    'add_new_item'         => '新規店舗レビューを追加',
                    'edit_item'            => '店舗レビューを編集',
                    'new_item'             => '新規店舗レビュー',
                    'view_item'            => '店舗レビューを表示',
                    'all_items'            => 'すべての店舗レビュー',
                    'search_items'         => '店舗レビューを検索',
                ],
                'public'             => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'menu_position'      => 5,
                'menu_icon'          => 'dashicons-store',
                'supports'           => ['title', 'editor', 'author', 'thumbnail', 'excerpt'],
                'has_archive'        => true,
                'rewrite'            => ['slug' => 'shops'],
                'show_in_rest'       => true,
            ]);
        }
        
        // 商品レビュー（product_only または hybrid）
        if ($this->site_mode === 'product_only' || $this->site_mode === 'hybrid') {
            // アフィリエイト専用モードの場合はラベルを変更
            $is_affiliate = get_option('urp_affiliate_primary', false);
            
            $labels = $is_affiliate ? [
                'name'                  => 'アフィリエイト商品',
                'singular_name'         => '商品',
                'menu_name'            => '商品管理',
                'add_new'              => '商品追加',
                'add_new_item'         => '新規商品を追加',
                'edit_item'            => '商品を編集',
                'new_item'             => '新規商品',
                'view_item'            => '商品を表示',
                'all_items'            => 'すべての商品',
                'search_items'         => '商品を検索',
            ] : [
                'name'                  => '商品レビュー',
                'singular_name'         => '商品レビュー',
                'menu_name'            => '商品レビュー',
                'add_new'              => '新規追加',
                'add_new_item'         => '新規商品レビューを追加',
                'edit_item'            => '商品レビューを編集',
                'new_item'             => '新規商品レビュー',
                'view_item'            => '商品レビューを表示',
                'all_items'            => 'すべての商品レビュー',
                'search_items'         => '商品レビューを検索',
            ];
            
            register_post_type('product_review', [
                'labels'             => $labels,
                'public'             => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'menu_position'      => 5,
                'menu_icon'          => 'dashicons-cart',
                'supports'           => ['title', 'editor', 'author', 'thumbnail', 'excerpt'],
                'has_archive'        => true,
                'rewrite'            => ['slug' => 'products'],
                'show_in_rest'       => true,
            ]);
        }
        
        // 後方互換用（移行期間）
        if (post_type_exists('platform_review')) {
            return;
        }
    }
    
    /**
     * タクソノミー登録
     */
    private function register_taxonomies() {
        $post_types = [];
        
        // サイトモードに応じて対象投稿タイプを決定
        if ($this->site_mode === 'shop_only' || $this->site_mode === 'hybrid') {
            $post_types[] = 'shop_review';
        }
        if ($this->site_mode === 'product_only' || $this->site_mode === 'hybrid') {
            $post_types[] = 'product_review';
        }
        
        if (empty($post_types)) {
            return;
        }
        
        // カテゴリー
        register_taxonomy('review_category', $post_types, [
            'labels' => [
                'name'              => 'カテゴリー',
                'singular_name'     => 'カテゴリー',
                'search_items'      => 'カテゴリーを検索',
                'all_items'         => 'すべてのカテゴリー',
                'parent_item'       => '親カテゴリー',
                'parent_item_colon' => '親カテゴリー:',
                'edit_item'         => 'カテゴリーを編集',
                'update_item'       => 'カテゴリーを更新',
                'add_new_item'      => '新規カテゴリーを追加',
                'new_item_name'     => '新規カテゴリー名',
                'menu_name'         => 'カテゴリー',
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'review-category'],
            'show_in_rest'      => true,
        ]);
        
        // 地域（店舗レビューのみ）
        if (in_array('shop_review', $post_types)) {
            register_taxonomy('review_region', ['shop_review'], [
                'labels' => [
                    'name'              => '地域',
                    'singular_name'     => '地域',
                    'search_items'      => '地域を検索',
                    'all_items'         => 'すべての地域',
                    'parent_item'       => '親地域',
                    'parent_item_colon' => '親地域:',
                    'edit_item'         => '地域を編集',
                    'update_item'       => '地域を更新',
                    'add_new_item'      => '新規地域を追加',
                    'new_item_name'     => '新規地域名',
                    'menu_name'         => '地域',
                ],
                'hierarchical'      => true,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => ['slug' => 'region'],
                'show_in_rest'      => true,
            ]);
        }
        
        // ブランド（商品レビューのみ）
        if (in_array('product_review', $post_types)) {
            register_taxonomy('product_brand', ['product_review'], [
                'labels' => [
                    'name'              => 'ブランド',
                    'singular_name'     => 'ブランド',
                    'menu_name'         => 'ブランド',
                ],
                'hierarchical'      => false,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => ['slug' => 'brand'],
                'show_in_rest'      => true,
            ]);
        }
    }
    
    /**
     * 管理メニュー追加
     */
    public function admin_menu() {
        // メインプラグインの責任：基本メニュー構造を作る
        add_menu_page(
            'Universal Review',
            'URP Dashboard',
            'manage_options',
            'urp-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-star-filled',
            3
        );
        
        add_submenu_page(
            'urp-dashboard',
            '設定',
            '設定',
            'manage_options',
            'urp-settings',
            [$this, 'render_settings']
        );
        
        add_submenu_page(
            'urp-dashboard',
            'アナリティクス',
            'アナリティクス',
            'manage_options',
            'urp-analytics',
            [$this, 'render_analytics']
        );
        
        // 初回セットアップ
        if (get_transient('urp_show_setup_notice')) {
            add_submenu_page(
                'urp-dashboard',
                'セットアップ',
                'セットアップ',
                'manage_options',
                'urp-setup',
                [$this, 'render_setup_wizard']
            );
        }
    }
    /**
     * ダッシュボード表示
     */
    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1>Universal Review Platform</h1>
            
            <div class="urp-dashboard-grid">
                <div class="urp-card">
                    <h2>サイトモード</h2>
                    <p><strong><?php echo $this->get_site_mode_label(); ?></strong></p>
                </div>
                
                <div class="urp-card">
                    <h2>統計</h2>
                    <ul>
                        <li>総レビュー数: <?php echo $this->get_total_reviews(); ?></li>
                        <li>今月の投稿: <?php echo $this->get_monthly_reviews(); ?></li>
                        <li>アクティブユーザー: <?php echo $this->get_active_users(); ?></li>
                    </ul>
                </div>
                
                <?php if (class_exists('URP_Extension_Manager')) : ?>
                <div class="urp-card">
                    <h2>インストール済み拡張</h2>
                    <?php $this->display_installed_extensions(); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 設定ページ表示（簡略化版）
     * universal-review-platform.php の render_settings() メソッドを置き換え
     */
    public function render_settings() {
        ?>
        <div class="wrap">
            <h1>Universal Review Platform 設定</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('urp_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">サイトモード</th>
                        <td>
                            <select name="urp_site_mode">
                                <option value="hybrid" <?php selected(get_option('urp_site_mode'), 'hybrid'); ?>>
                                    ハイブリッド（店舗＋商品）
                                </option>
                                <option value="shop_only" <?php selected(get_option('urp_site_mode'), 'shop_only'); ?>>
                                    店舗レビューのみ
                                </option>
                                <option value="product_only" <?php selected(get_option('urp_site_mode'), 'product_only'); ?>>
                                    商品レビューのみ
                                </option>
                            </select>
                            <p class="description">
                                サイトの運営モードを選択してください。
                                アフィリエイト機能は全モードで利用可能です。
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f6f7f7; border-left: 4px solid #2271b1;">
                <h3>その他の設定</h3>
                <ul>
                    <li><a href="<?php echo admin_url('admin.php?page=urp-affiliate-settings'); ?>">アフィリエイト設定</a> - Amazon、楽天などのAPI設定</li>
                    <li><a href="<?php echo admin_url('admin.php?page=urp-extensions'); ?>">専門プラグイン管理</a> - カレー、ラーメンなど業種特化プラグイン</li>
                    <li><a href="<?php echo admin_url('admin.php?page=urp-implementation'); ?>">実装状況</a> - 開発進捗の確認</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * アナリティクス表示（プレースホルダー）
     */
    public function render_analytics() {
        ?>
        <div class="wrap">
            <h1>アナリティクス</h1>
            <p>アナリティクス機能は開発中です。</p>
        </div>
        <?php
    }
    
    /**
     * セットアップウィザード表示（プレースホルダー）
     */
    public function render_setup_wizard() {
        ?>
        <div class="wrap">
            <h1>セットアップウィザード</h1>
            <p>初期設定を行います。</p>
        </div>
        <?php
    }
    
    /**
     * インストール済み拡張表示
     */
    private function display_installed_extensions() {
        $extensions = apply_filters('urp_get_installed_extensions', []);
        if (empty($extensions)) {
            echo '<p>拡張プラグインはインストールされていません。</p>';
        } else {
            echo '<ul>';
            foreach ($extensions as $extension) {
                echo '<li>' . esc_html($extension['name']) . ' v' . esc_html($extension['version']) . '</li>';
            }
            echo '</ul>';
        }
    }
    
    /**
     * コンポーネント初期化（Static版）
     */
    private function init_components() {
        // initメソッドを持つクラスの初期化
        $init_classes = [
            'URP\\Admin\\URP_Affiliate_Settings',
            'URP\\Admin\\URP_Implementation_Status',
            'URP\\Core\\URP_Extension_Manager',
            'URP\\Core\\URP_Site_Mode',
            'URP\\Core\\URP_Trust_Score',
        ];
        
        foreach ($init_classes as $class) {
            // namespace付きとnamespace無し両方を試す
            if (class_exists($class)) {
                if (method_exists($class, 'init')) {
                    $class::init();
                }
            } elseif (class_exists(str_replace('URP\\Core\\', '', $class))) {
                $legacy_class = str_replace('URP\\Core\\', '', $class);
                if (method_exists($legacy_class, 'init')) {
                    $legacy_class::init();
                }
            }
        }
        
        // 他のクラスは存在確認のみ（必要に応じて追加）
        $check_classes = [
            'URP_Database',
            'URP_Review_Manager',
            'URP_Security',
            'URP_API_Router',
            'URP_Cache_Manager',
            'URP\\Core\\URP_Rating_Fields',
            'URP\\Core\\URP_Affiliate_Manager',
        ];
        
        foreach ($check_classes as $class) {
            if (!class_exists($class) && !class_exists('URP\\Core\\' . $class)) {
                // デバッグログ出力（開発時のみ）
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("URP: Class {$class} not found");
                }
            }
        }
        
        // 拡張プラグインの登録を許可
        do_action('urp_register_extensions', $this);
    }
    
    /**
     * ショートコード登録
     */
    private function register_shortcodes() {
        add_shortcode('urp_reviews', [$this, 'shortcode_reviews']);
        add_shortcode('urp_review_form', [$this, 'shortcode_review_form']);
        add_shortcode('urp_rankings', [$this, 'shortcode_rankings']);
        add_shortcode('urp_review_map', [$this, 'shortcode_review_map']);
    }
    
    /**
     * ショートコード実装（プレースホルダー）
     */
    public function shortcode_reviews($atts) {
        return '<div class="urp-reviews">レビューリスト</div>';
    }
    
    public function shortcode_review_form($atts) {
        return '<div class="urp-review-form">レビューフォーム</div>';
    }
    
    public function shortcode_rankings($atts) {
        return '<div class="urp-rankings">ランキング</div>';
    }
    
    public function shortcode_review_map($atts) {
        return '<div class="urp-review-map">マップ</div>';
    }
    
    /**
     * リライトルール追加
     */
    private function add_rewrite_rules() {
        // カスタムエンドポイント
        add_rewrite_rule(
            'reviews/([^/]+)/?$',
            'index.php?review_type=$matches[1]',
            'top'
        );
    }
    
    /**
     * プラグイン読み込み完了時
     */
    public function on_plugins_loaded() {
        // 他のプラグインとの連携
        do_action('urp_loaded');
    }
    
    /**
     * 管理画面初期化
     */
    public function admin_init() {
        // 設定登録
        register_setting('urp_settings', 'urp_site_mode');
        // register_setting('urp_settings', 'urp_affiliate_enabled');
        // register_setting('urp_settings', 'urp_affiliate_primary');
    }
    
    /**
     * REST APIルート登録
     */
    public function register_rest_routes() {
        if (isset($this->components['api_router'])) {
            $this->components['api_router']->register_routes();
        }
    }
    
    /**
     * AJAX処理（プレースホルダー）
     */
    public function ajax_handler() {
        // セキュリティチェック
        check_ajax_referer('urp_nonce', 'nonce');
        
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        // アクションに応じた処理
        wp_send_json_success(['message' => 'OK']);
    }
    
    /**
     * 日次Cron処理（プレースホルダー）
     */
    public function daily_cron() {
        // 日次処理
    }
    
    /**
     * 時間毎Cron処理（プレースホルダー）
     */
    public function hourly_cron() {
        // 時間毎処理
    }
    
    /**
     * コンポーネント取得
     */
    public function get_component($name) {
        return $this->components[$name] ?? null;
    }
    
    /**
     * サイトモードラベル取得
     */
    private function get_site_mode_label() {
        return match($this->site_mode) {
            'shop_only' => '店舗レビュー専門',
            'product_only' => '商品レビュー専門',
            'hybrid' => 'ハイブリッド型',
            default => '未設定'
        };
    }
    
    /**
     * 統計取得メソッド（簡易版）
     */
    private function get_total_reviews() {
        $count = 0;
        if ($this->site_mode === 'shop_only' || $this->site_mode === 'hybrid') {
            $shop_posts = wp_count_posts('shop_review');
            if (isset($shop_posts->publish)) {
                $count += $shop_posts->publish;
            }
        }
        if ($this->site_mode === 'product_only' || $this->site_mode === 'hybrid') {
            $product_posts = wp_count_posts('product_review');
            if (isset($product_posts->publish)) {
                $count += $product_posts->publish;
            }
        }
        return $count;
    }
    
    private function get_monthly_reviews() {
        // 実装予定
        return 0;
    }
    
    private function get_active_users() {
        // 実装予定
        return count(get_users(['role' => 'subscriber']));
    }
    
    /**
     * スクリプト・スタイル登録
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'urp-') !== false) {
            wp_enqueue_style(
                'urp-admin',
                URP_PLUGIN_URL . 'admin/assets/css/admin.css',
                [],
                URP_VERSION
            );
            
            wp_enqueue_script(
                'urp-admin',
                URP_PLUGIN_URL . 'admin/assets/js/admin.js',
                ['jquery'],
                URP_VERSION,
                true
            );
            
            // AJAX用のnonceとURLを渡す
            wp_localize_script('urp-admin', 'urp_ajax', [
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('urp_nonce')
            ]);
        }
    }
    
    public function public_enqueue_scripts() {
        wp_enqueue_style(
            'urp-public',
            URP_PLUGIN_URL . 'public/assets/css/style.css',
            [],
            URP_VERSION
        );
        
        wp_enqueue_script(
            'urp-public',
            URP_PLUGIN_URL . 'public/assets/js/main.js',
            ['jquery'],
            URP_VERSION,
            true
        );
        
        // AJAX用の設定
        wp_localize_script('urp-public', 'urp_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('urp_nonce')
        ]);
    }
}

// アクティベーション
register_activation_hook(__FILE__, function() {
    if (file_exists(URP_PLUGIN_DIR . 'activate.php')) {
        require_once URP_PLUGIN_DIR . 'activate.php';
        if (class_exists('URP_Activator')) {
            URP_Activator::activate();
        }
    }
    flush_rewrite_rules();
});

// ディアクティベーション
register_deactivation_hook(__FILE__, function() {
    if (file_exists(URP_PLUGIN_DIR . 'deactivate.php')) {
        require_once URP_PLUGIN_DIR . 'deactivate.php';
        if (class_exists('URP_Deactivator')) {
            URP_Deactivator::deactivate();
        }
    }
    flush_rewrite_rules();
});

// プラグイン開始（関数名を変更してヘルパーとの競合を回避）
if (!function_exists('URP')) {
    function URP() {
        return Universal_Review_Platform::instance();
    }
}

// プラグイン初期化
add_action('plugins_loaded', function() {
    URP();
}, 5);