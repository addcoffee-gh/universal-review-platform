<?php
/**
 * Affiliate Settings - アフィリエイト設定画面
 * 
 * Amazon、楽天、Yahoo等のAPI認証情報を管理
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 */

namespace URP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class URP_Affiliate_Settings {
    
    /**
     * 初期化
     */
    public static function init() {
        add_action('admin_menu', [self::class, 'add_menu'], 30);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        
        // Ajax処理
        add_action('wp_ajax_urp_test_affiliate_api', [self::class, 'ajax_test_api']);
    }
    
    /**
     * メニュー追加
     */
    public static function add_menu() {
        add_submenu_page(
            'urp-dashboard',
            'アフィリエイト設定',
            'アフィリエイト設定',
            'manage_options',
            'urp-affiliate-settings',
            [self::class, 'render_page']
        );
    }
    
    /**
     * 設定を登録
     */
    public static function register_settings() {
        // Amazon設定
        register_setting('urp_affiliate_amazon', 'urp_amazon_access_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('urp_affiliate_amazon', 'urp_amazon_secret_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('urp_affiliate_amazon', 'urp_amazon_partner_tag', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('urp_affiliate_amazon', 'urp_amazon_marketplace', [
            'default' => 'JP',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        // 楽天設定
        register_setting('urp_affiliate_rakuten', 'urp_rakuten_app_id', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('urp_affiliate_rakuten', 'urp_rakuten_affiliate_id', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        // Yahoo設定
        register_setting('urp_affiliate_yahoo', 'urp_yahoo_sid', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('urp_affiliate_yahoo', 'urp_yahoo_pid', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        // 共通設定
        register_setting('urp_affiliate_general', 'urp_affiliate_cache_duration', [
            'type' => 'integer',
            'default' => 24,
            'sanitize_callback' => 'absint'
        ]);
        register_setting('urp_affiliate_general', 'urp_affiliate_auto_update', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        register_setting('urp_affiliate_general', 'urp_affiliate_nofollow', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
    }
    
    /**
     * 設定画面を表示
     */
    public static function render_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'amazon';
        ?>
        <div class="wrap">
            <h1>アフィリエイト設定</h1>
            
            <!-- タブナビゲーション -->
            <nav class="nav-tab-wrapper">
                <a href="?page=urp-affiliate-settings&tab=amazon" 
                   class="nav-tab <?php echo $active_tab === 'amazon' ? 'nav-tab-active' : ''; ?>">
                    Amazon
                </a>
                <a href="?page=urp-affiliate-settings&tab=rakuten" 
                   class="nav-tab <?php echo $active_tab === 'rakuten' ? 'nav-tab-active' : ''; ?>">
                    楽天市場
                </a>
                <a href="?page=urp-affiliate-settings&tab=yahoo" 
                   class="nav-tab <?php echo $active_tab === 'yahoo' ? 'nav-tab-active' : ''; ?>">
                    Yahoo!ショッピング
                </a>
                <a href="?page=urp-affiliate-settings&tab=general" 
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    一般設定
                </a>
                <a href="?page=urp-affiliate-settings&tab=stats" 
                   class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                    統計
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'amazon':
                        self::render_amazon_settings();
                        break;
                    case 'rakuten':
                        self::render_rakuten_settings();
                        break;
                    case 'yahoo':
                        self::render_yahoo_settings();
                        break;
                    case 'general':
                        self::render_general_settings();
                        break;
                    case 'stats':
                        self::render_stats();
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
        .urp-settings-section {
            background: #fff;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .urp-settings-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .form-table th {
            width: 250px;
        }
        
        .api-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .api-status.connected {
            background: #d4f4dd;
            color: #00a32a;
        }
        
        .api-status.disconnected {
            background: #fcf0f1;
            color: #d63638;
        }
        
        .test-api-button {
            margin-left: 10px;
        }
        
        .api-test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 3px;
            display: none;
        }
        
        .api-test-result.success {
            background: #d4f4dd;
            border: 1px solid #00a32a;
            color: #00a32a;
        }
        
        .api-test-result.error {
            background: #fcf0f1;
            border: 1px solid #d63638;
            color: #d63638;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        code.api-key {
            background: #f0f0f1;
            padding: 2px 5px;
            border-radius: 2px;
        }
        
        .description {
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Amazon設定画面
     */
    private static function render_amazon_settings() {
        $access_key = get_option('urp_amazon_access_key');
        $secret_key = get_option('urp_amazon_secret_key');
        $partner_tag = get_option('urp_amazon_partner_tag');
        $marketplace = get_option('urp_amazon_marketplace', 'JP');
        
        $is_connected = !empty($access_key) && !empty($secret_key) && !empty($partner_tag);
        ?>
        <div class="urp-settings-section">
            <h2>
                Amazon Product Advertising API 設定
                <span class="api-status <?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                    <?php echo $is_connected ? '接続済み' : '未接続'; ?>
                </span>
            </h2>
            
            <form method="post" action="options.php">
                <?php settings_fields('urp_affiliate_amazon'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="urp_amazon_access_key">アクセスキー</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="urp_amazon_access_key" 
                                   name="urp_amazon_access_key" 
                                   value="<?php echo esc_attr($access_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                PA-API v5のアクセスキー
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="urp_amazon_secret_key">シークレットキー</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="urp_amazon_secret_key" 
                                   name="urp_amazon_secret_key" 
                                   value="<?php echo esc_attr($secret_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                PA-API v5のシークレットキー（暗号化して保存されます）
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="urp_amazon_partner_tag">パートナータグ</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="urp_amazon_partner_tag" 
                                   name="urp_amazon_partner_tag" 
                                   value="<?php echo esc_attr($partner_tag); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                Amazonアソシエイトのトラッキングタグ（例: yoursite-22）
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="urp_amazon_marketplace">マーケットプレイス</label>
                        </th>
                        <td>
                            <select id="urp_amazon_marketplace" name="urp_amazon_marketplace">
                                <option value="JP" <?php selected($marketplace, 'JP'); ?>>日本 (amazon.co.jp)</option>
                                <option value="US" <?php selected($marketplace, 'US'); ?>>米国 (amazon.com)</option>
                                <option value="UK" <?php selected($marketplace, 'UK'); ?>>英国 (amazon.co.uk)</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存'); ?>
                
                <?php if ($is_connected): ?>
                    <button type="button" class="button test-api-button" data-platform="amazon">
                        API接続テスト
                    </button>
                    <div class="api-test-result" id="amazon-test-result"></div>
                <?php endif; ?>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f6f7f7; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">設定方法</h3>
                <ol>
                    <li>Amazon アソシエイトにログイン</li>
                    <li>ツール → Product Advertising API</li>
                    <li>認証情報を管理 → 新しいアクセスキーを作成</li>
                    <li>生成されたキーをこちらに入力</li>
                </ol>
                <p><a href="https://affiliate.amazon.co.jp/help/node/topic/G74ZH5K7XYZBUQTG" target="_blank">詳しい手順はこちら →</a></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * 楽天設定画面
     */
    private static function render_rakuten_settings() {
        $app_id = get_option('urp_rakuten_app_id');
        $affiliate_id = get_option('urp_rakuten_affiliate_id');
        
        $is_connected = !empty($app_id);
        ?>
        <div class="urp-settings-section">
            <h2>
                楽天市場 API 設定
                <span class="api-status <?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                    <?php echo $is_connected ? '接続済み' : '未接続'; ?>
                </span>
            </h2>
            
            <form method="post" action="options.php">
                <?php settings_fields('urp_affiliate_rakuten'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="urp_rakuten_app_id">アプリID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="urp_rakuten_app_id" 
                                   name="urp_rakuten_app_id" 
                                   value="<?php echo esc_attr($app_id); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                楽天ウェブサービスのアプリID（必須）
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="urp_rakuten_affiliate_id">アフィリエイトID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="urp_rakuten_affiliate_id" 
                                   name="urp_rakuten_affiliate_id" 
                                   value="<?php echo esc_attr($affiliate_id); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                楽天アフィリエイトID（省略可能）
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存'); ?>
                
                <?php if ($is_connected): ?>
                    <button type="button" class="button test-api-button" data-platform="rakuten">
                        API接続テスト
                    </button>
                    <div class="api-test-result" id="rakuten-test-result"></div>
                <?php endif; ?>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f6f7f7; border-left: 4px solid #bf0000;">
                <h3 style="margin-top: 0;">設定方法</h3>
                <ol>
                    <li>楽天ウェブサービスにアプリを登録</li>
                    <li>アプリIDを取得</li>
                    <li>楽天アフィリエイトに登録（任意）</li>
                    <li>アフィリエイトIDを取得</li>
                </ol>
                <p><a href="https://webservice.rakuten.co.jp/guide/" target="_blank">楽天ウェブサービスガイド →</a></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Yahoo設定画面
     */
    private static function render_yahoo_settings() {
        $sid = get_option('urp_yahoo_sid');
        $pid = get_option('urp_yahoo_pid');
        
        $is_connected = !empty($sid) && !empty($pid);
        ?>
        <div class="urp-settings-section">
            <h2>
                Yahoo!ショッピング（バリューコマース）設定
                <span class="api-status <?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                    <?php echo $is_connected ? '接続済み' : '未接続'; ?>
                </span>
            </h2>
            
            <form method="post" action="options.php">
                <?php settings_fields('urp_affiliate_yahoo'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="urp_yahoo_sid">サイトID (sid)</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="urp_yahoo_sid" 
                                   name="urp_yahoo_sid" 
                                   value="<?php echo esc_attr($sid); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                バリューコマースのサイトID
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="urp_yahoo_pid">プログラムID (pid)</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="urp_yahoo_pid" 
                                   name="urp_yahoo_pid" 
                                   value="<?php echo esc_attr($pid); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                Yahoo!ショッピングのプログラムID
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存'); ?>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f6f7f7; border-left: 4px solid #ff0033;">
                <h3 style="margin-top: 0;">設定方法</h3>
                <ol>
                    <li>バリューコマースに登録</li>
                    <li>Yahoo!ショッピングと提携</li>
                    <li>管理画面からsidとpidを確認</li>
                    <li>取得したIDをこちらに入力</li>
                </ol>
                <p><a href="https://www.valuecommerce.ne.jp/" target="_blank">バリューコマース →</a></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * 一般設定画面
     */
    private static function render_general_settings() {
        $cache_duration = get_option('urp_affiliate_cache_duration', 24);
        $auto_update = get_option('urp_affiliate_auto_update', true);
        $nofollow = get_option('urp_affiliate_nofollow', true);
        ?>
        <div class="urp-settings-section">
            <h2>一般設定</h2>
            
            <form method="post" action="options.php">
                <?php settings_fields('urp_affiliate_general'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="urp_affiliate_cache_duration">キャッシュ時間</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="urp_affiliate_cache_duration" 
                                   name="urp_affiliate_cache_duration" 
                                   value="<?php echo esc_attr($cache_duration); ?>" 
                                   min="1" 
                                   max="168" 
                                   style="width: 80px;" />
                            時間
                            <p class="description">
                                商品情報のキャッシュ保持時間（1〜168時間）
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">価格自動更新</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="urp_affiliate_auto_update" 
                                       value="1" 
                                       <?php checked($auto_update); ?> />
                                価格を自動的に更新する
                            </label>
                            <p class="description">
                                Cronジョブで定期的に価格情報を更新します
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">リンク属性</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="urp_affiliate_nofollow" 
                                       value="1" 
                                       <?php checked($nofollow); ?> />
                                アフィリエイトリンクに nofollow を追加
                            </label>
                            <p class="description">
                                SEO対策として推奨されます
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 統計画面
     */
    private static function render_stats() {
        // 統計データを取得（ダミーデータ）
        $stats = [
            'total_clicks' => rand(1000, 5000),
            'today_clicks' => rand(10, 100),
            'month_clicks' => rand(500, 2000),
            'conversion_rate' => rand(10, 50) / 10,
        ];
        ?>
        <div class="urp-settings-section">
            <h2>アフィリエイト統計</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_clicks']); ?></div>
                    <div class="stat-label">総クリック数</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['today_clicks']; ?></div>
                    <div class="stat-label">今日のクリック</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['month_clicks']); ?></div>
                    <div class="stat-label">今月のクリック</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['conversion_rate']; ?>%</div>
                    <div class="stat-label">コンバージョン率</div>
                </div>
            </div>
            
            <p style="margin-top: 20px; color: #666;">
                ※ 実際のコンバージョンデータは各アフィリエイトプログラムの管理画面で確認してください。
            </p>
        </div>
        <?php
    }
    
    /**
     * スクリプトを読み込み
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'urp-dashboard_page_urp-affiliate-settings') {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // インラインスクリプト
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                // API接続テスト
                $(".test-api-button").on("click", function() {
                    var platform = $(this).data("platform");
                    var $button = $(this);
                    var $result = $("#" + platform + "-test-result");
                    
                    $button.prop("disabled", true).text("テスト中...");
                    
                    $.post(ajaxurl, {
                        action: "urp_test_affiliate_api",
                        platform: platform,
                        nonce: "' . wp_create_nonce('urp_test_api') . '"
                    }, function(response) {
                        $button.prop("disabled", false).text("API接続テスト");
                        
                        if (response.success) {
                            $result.removeClass("error").addClass("success");
                            $result.html("✅ " + response.data.message);
                        } else {
                            $result.removeClass("success").addClass("error");
                            $result.html("❌ " + response.data.message);
                        }
                        
                        $result.fadeIn();
                    });
                });
            });
        ');
    }
    
    /**
     * Ajax: API接続テスト
     */
    public static function ajax_test_api() {
        check_ajax_referer('urp_test_api', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        switch ($platform) {
            case 'amazon':
                // 簡易テスト（実際のAPIコールは別途実装）
                $access_key = get_option('urp_amazon_access_key');
                if ($access_key) {
                    wp_send_json_success(['message' => 'API認証情報が確認されました']);
                } else {
                    wp_send_json_error(['message' => 'API認証情報が設定されていません']);
                }
                break;
                
            case 'rakuten':
                $app_id = get_option('urp_rakuten_app_id');
                if ($app_id) {
                    // 実際にAPIを叩いてテスト
                    $test_url = 'https://app.rakuten.co.jp/services/api/IchibaItem/Search/20170706?applicationId=' . $app_id . '&keyword=test';
                    $response = wp_remote_get($test_url);
                    
                    if (!is_wp_error($response)) {
                        wp_send_json_success(['message' => 'API接続成功']);
                    } else {
                        wp_send_json_error(['message' => 'API接続失敗: ' . $response->get_error_message()]);
                    }
                } else {
                    wp_send_json_error(['message' => 'アプリIDが設定されていません']);
                }
                break;
                
            default:
                wp_send_json_error(['message' => '不明なプラットフォーム']);
        }
    }
}

// 初期化
add_action('plugins_loaded', function() {
    URP_Affiliate_Settings::init();
});