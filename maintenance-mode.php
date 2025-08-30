<?php
/**
 * Universal Review Platform - Maintenance Mode
 * 
 * メンテナンス中の表示とアクセス制御
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
 * メンテナンスモードクラス
 */
class URP_Maintenance_Mode {
    
    /**
     * メンテナンス状態
     * @var bool
     */
    private static bool $is_maintenance = false;
    
    /**
     * 許可されたIPアドレス
     * @var array<string>
     */
    private static array $allowed_ips = [];
    
    /**
     * メンテナンス終了予定時刻
     * @var ?string
     */
    private static ?string $end_time = null;
    
    /**
     * 初期化
     * @return void
     */
    public static function init(): void {
        // メンテナンス状態を確認
        self::check_maintenance_status();
        
        // フックを登録
        if (self::$is_maintenance) {
            add_action('init', [__CLASS__, 'handle_maintenance'], 1);
            add_action('admin_notices', [__CLASS__, 'admin_notice']);
            add_filter('wp_die_handler', [__CLASS__, 'get_die_handler']);
        }
    }
    
    /**
     * メンテナンス状態を確認
     * @return void
     */
    private static function check_maintenance_status(): void {
        // オプションから状態を取得
        $maintenance_data = get_option('urp_maintenance_mode', []);
        
        if (!empty($maintenance_data['enabled'])) {
            self::$is_maintenance = true;
            self::$allowed_ips = $maintenance_data['allowed_ips'] ?? [];
            self::$end_time = $maintenance_data['end_time'] ?? null;
        }
        
        // アップグレード中の場合
        if (get_option('urp_upgrading', false)) {
            self::$is_maintenance = true;
        }
        
        // .maintenanceファイルの存在確認
        if (file_exists(ABSPATH . '.maintenance')) {
            self::$is_maintenance = true;
        }
    }
    
    /**
     * メンテナンスモードを有効化
     * PHP 8.0: 名前付き引数
     * @param array<string, mixed> $args
     * @return bool
     */
    public static function enable(array $args = []): bool {
        $defaults = [
            'message' => __('サイトは現在メンテナンス中です。しばらくお待ちください。', 'universal-review'),
            'allowed_ips' => [],
            'duration' => 3600, // 1時間
            'allow_admins' => true,
            'allow_logged_in' => false,
        ];
        
        $settings = array_merge($defaults, $args);
        
        // メンテナンスデータを保存
        $maintenance_data = [
            'enabled' => true,
            'message' => sanitize_textarea_field($settings['message']),
            'allowed_ips' => array_map('sanitize_text_field', $settings['allowed_ips']),
            'start_time' => current_time('mysql'),
            'end_time' => date('Y-m-d H:i:s', time() + $settings['duration']),
            'allow_admins' => (bool)$settings['allow_admins'],
            'allow_logged_in' => (bool)$settings['allow_logged_in'],
        ];
        
        update_option('urp_maintenance_mode', $maintenance_data);
        
        // .maintenanceファイルを作成
        self::create_maintenance_file();
        
        // キャッシュをクリア
        wp_cache_flush();
        
        return true;
    }
    
    /**
     * メンテナンスモードを無効化
     * @return bool
     */
    public static function disable(): bool {
        // オプションを削除
        delete_option('urp_maintenance_mode');
        
        // .maintenanceファイルを削除
        self::remove_maintenance_file();
        
        // キャッシュをクリア
        wp_cache_flush();
        
        // ログに記録
        self::log_maintenance_action('disabled');
        
        return true;
    }
    
    /**
     * メンテナンス処理
     * @return void
     */
    public static function handle_maintenance(): void {
        // 管理画面は除外
        if (is_admin()) {
            return;
        }
        
        // アクセス許可を確認
        if (self::is_access_allowed()) {
            return;
        }
        
        // メンテナンスページを表示
        self::show_maintenance_page();
    }
    
    /**
     * アクセスが許可されているか確認
     * PHP 8.0: match式
     * @return bool
     */
    private static function is_access_allowed(): bool {
        $maintenance_data = get_option('urp_maintenance_mode', []);
        
        // 管理者の場合
        if (!empty($maintenance_data['allow_admins']) && current_user_can('manage_options')) {
            return true;
        }
        
        // ログインユーザーの場合
        if (!empty($maintenance_data['allow_logged_in']) && is_user_logged_in()) {
            return true;
        }
        
        // 許可されたIPアドレス
        $user_ip = self::get_user_ip();
        if (in_array($user_ip, self::$allowed_ips, true)) {
            return true;
        }
        
        // 特定のURLパターンを除外
        $allowed_patterns = [
            'wp-login.php',
            'wp-admin',
            'admin-ajax.php',
            'wp-cron.php',
        ];
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        foreach ($allowed_patterns as $pattern) {
            if (strpos($request_uri, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * メンテナンスページを表示
     * @return void
     */
    private static function show_maintenance_page(): void {
        $maintenance_data = get_option('urp_maintenance_mode', []);
        
        // HTTPステータスコード
        header('HTTP/1.1 503 Service Unavailable');
        header('Status: 503 Service Unavailable');
        header('Retry-After: 3600');
        
        // カスタムテンプレートが存在する場合
        $custom_template = get_stylesheet_directory() . '/maintenance.php';
        if (file_exists($custom_template)) {
            include $custom_template;
            exit;
        }
        
        // デフォルトのメンテナンスページ
        self::render_default_page($maintenance_data);
        exit;
    }
    
    /**
     * デフォルトのメンテナンスページをレンダリング
     * @param array<string, mixed> $data
     * @return void
     */
    private static function render_default_page(array $data): void {
        $title = get_bloginfo('name') . ' - メンテナンス中';
        $message = $data['message'] ?? 'サイトは現在メンテナンス中です。';
        $end_time = $data['end_time'] ?? null;
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($title); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #ffffff;
                    padding: 20px;
                }
                
                .maintenance-container {
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border-radius: 20px;
                    padding: 60px 40px;
                    text-align: center;
                    max-width: 600px;
                    width: 100%;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
                    animation: fadeIn 0.8s ease;
                }
                
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .maintenance-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 30px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                }
                
                h1 {
                    font-size: 32px;
                    margin-bottom: 20px;
                    font-weight: 700;
                    letter-spacing: -0.5px;
                }
                
                .message {
                    font-size: 18px;
                    line-height: 1.6;
                    margin-bottom: 30px;
                    opacity: 0.95;
                }
                
                .countdown {
                    background: rgba(255, 255, 255, 0.15);
                    border-radius: 10px;
                    padding: 20px;
                    margin-top: 30px;
                }
                
                .countdown-label {
                    font-size: 14px;
                    opacity: 0.8;
                    margin-bottom: 10px;
                }
                
                .countdown-time {
                    font-size: 24px;
                    font-weight: 600;
                    font-variant-numeric: tabular-nums;
                }
                
                .progress-bar {
                    width: 100%;
                    height: 4px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 2px;
                    margin-top: 40px;
                    overflow: hidden;
                }
                
                .progress-bar-fill {
                    height: 100%;
                    background: #ffffff;
                    width: 30%;
                    animation: progress 2s ease-in-out infinite;
                }
                
                @keyframes progress {
                    0% { width: 0; }
                    50% { width: 70%; }
                    100% { width: 100%; }
                }
                
                .contact {
                    margin-top: 40px;
                    font-size: 14px;
                    opacity: 0.8;
                }
                
                .contact a {
                    color: #ffffff;
                    text-decoration: underline;
                }
                
                @media (max-width: 600px) {
                    .maintenance-container {
                        padding: 40px 20px;
                    }
                    
                    h1 {
                        font-size: 24px;
                    }
                    
                    .message {
                        font-size: 16px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="maintenance-icon">🔧</div>
                <h1>メンテナンス中</h1>
                <p class="message"><?php echo esc_html($message); ?></p>
                
                <?php if ($end_time): ?>
                <div class="countdown" id="countdown">
                    <div class="countdown-label">メンテナンス終了予定時刻</div>
                    <div class="countdown-time" id="countdown-time">
                        <?php echo esc_html(date_i18n('Y年n月j日 H:i', strtotime($end_time))); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="progress-bar">
                    <div class="progress-bar-fill"></div>
                </div>
                
                <div class="contact">
                    <p>お急ぎの場合は <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>">お問い合わせ</a> ください。</p>
                </div>
            </div>
            
            <?php if ($end_time): ?>
            <script>
                // カウントダウンタイマー
                (function() {
                    const endTime = new Date('<?php echo esc_js($end_time); ?>').getTime();
                    const countdownEl = document.getElementById('countdown-time');
                    
                    function updateCountdown() {
                        const now = new Date().getTime();
                        const distance = endTime - now;
                        
                        if (distance < 0) {
                            countdownEl.innerHTML = 'まもなく再開します';
                            // 自動リロード
                            setTimeout(() => location.reload(), 30000);
                            return;
                        }
                        
                        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                        
                        countdownEl.innerHTML = `あと ${hours}時間 ${minutes}分 ${seconds}秒`;
                    }
                    
                    updateCountdown();
                    setInterval(updateCountdown, 1000);
                })();
            </script>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * 管理画面通知
     * @return void
     */
    public static function admin_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $maintenance_data = get_option('urp_maintenance_mode', []);
        $message = $maintenance_data['message'] ?? 'メンテナンス中';
        $end_time = $maintenance_data['end_time'] ?? null;
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>⚠️ メンテナンスモード有効</strong></p>
            <p><?php echo esc_html($message); ?></p>
            <?php if ($end_time): ?>
            <p>終了予定: <?php echo esc_html(date_i18n('Y年n月j日 H:i', strtotime($end_time))); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=urp-maintenance')); ?>" class="button">
                    メンテナンス設定
                </a>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=urp_disable_maintenance'), 'urp_maintenance')); ?>" 
                   class="button button-primary"
                   onclick="return confirm('メンテナンスモードを解除しますか？');">
                    メンテナンスモード解除
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * ユーザーのIPアドレスを取得
     * @return string
     */
    private static function get_user_ip(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // カンマ区切りの場合は最初のIPを取得
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * .maintenanceファイルを作成
     * @return void
     */
    private static function create_maintenance_file(): void {
        $maintenance_file = ABSPATH . '.maintenance';
        if (!file_exists($maintenance_file)) {
            $content = '<?php $upgrading = ' . time() . '; ?>';
            file_put_contents($maintenance_file, $content);
        }
    }
    
    /**
     * .maintenanceファイルを削除
     * @return void
     */
    private static function remove_maintenance_file(): void {
        $maintenance_file = ABSPATH . '.maintenance';
        if (file_exists($maintenance_file)) {
            @unlink($maintenance_file);
        }
    }
    
    /**
     * メンテナンスアクションをログに記録
     * @param string $action
     * @return void
     */
    private static function log_maintenance_action(string $action): void {
        $log_entry = [
            'action' => $action,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_user_ip(),
        ];
        
        $logs = get_option('urp_maintenance_logs', []);
        $logs[] = $log_entry;
        
        // 最新50件のみ保持
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('urp_maintenance_logs', $logs);
    }
    
    /**
     * wp_dieハンドラーを取得
     * @return callable
     */
    public static function get_die_handler(): callable {
        return [__CLASS__, 'wp_die_handler'];
    }
    
    /**
     * カスタムwp_dieハンドラー
     * @param string $message
     * @param string $title
     * @param array<string, mixed> $args
     * @return void
     */
    public static function wp_die_handler(string $message = '', string $title = '', array $args = []): void {
        $maintenance_data = get_option('urp_maintenance_mode', []);
        self::render_default_page($maintenance_data);
    }
    
    /**
     * メンテナンス状態を確認
     * @return bool
     */
    public static function is_maintenance(): bool {
        return self::$is_maintenance;
    }
}

// 初期化
add_action('plugins_loaded', ['URP_Maintenance_Mode', 'init'], 1);

// 管理画面でメンテナンスモード解除処理
add_action('admin_post_urp_disable_maintenance', function() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません');
    }
    
    check_admin_referer('urp_maintenance');
    
    URP_Maintenance_Mode::disable();
    
    wp_redirect(admin_url('admin.php?page=urp-dashboard&maintenance=disabled'));
    exit;
});