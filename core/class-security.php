<?php
/**
 * Universal Review Platform - Security Manager
 * 
 * セキュリティ処理とバリデーション
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

namespace URP\Core;

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * セキュリティ管理クラス
 */
class URP_Security {
    
    /**
     * 許可されたHTMLタグ
     * @var array<string, array<string, bool>>
     */
    private array $allowed_html = [];
    
    /**
     * CSRFトークンの有効期限
     * @var int
     */
    private const TOKEN_EXPIRY = 3600; // 1時間
    
    /**
     * ブルートフォース試行制限
     * @var int
     */
    private const MAX_ATTEMPTS = 5;
    
    /**
     * ブロック時間（秒）
     * @var int
     */
    private const BLOCK_DURATION = 900; // 15分
    
    /**
     * セキュリティログ
     * @var array<array<string, mixed>>
     */
    private array $security_log = [];
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * 初期化
     * @return void
     */
    private function init(): void {
        $this->setup_allowed_html();
        $this->register_hooks();
        $this->init_security_headers();
    }
    
    /**
     * 許可されたHTMLタグ設定
     * @return void
     */
    private function setup_allowed_html(): void {
        $this->allowed_html = [
            'a' => [
                'href' => true,
                'title' => true,
                'target' => true,
                'rel' => true,
                'class' => true,
                'id' => true,
            ],
            'b' => ['class' => true],
            'strong' => ['class' => true],
            'em' => ['class' => true],
            'i' => ['class' => true],
            'u' => ['class' => true],
            'p' => ['class' => true, 'id' => true],
            'br' => [],
            'div' => ['class' => true, 'id' => true],
            'span' => ['class' => true, 'id' => true],
            'img' => [
                'src' => true,
                'alt' => true,
                'title' => true,
                'width' => true,
                'height' => true,
                'class' => true,
                'loading' => true,
            ],
            'ul' => ['class' => true],
            'ol' => ['class' => true],
            'li' => ['class' => true],
            'blockquote' => ['cite' => true, 'class' => true],
            'h1' => ['class' => true, 'id' => true],
            'h2' => ['class' => true, 'id' => true],
            'h3' => ['class' => true, 'id' => true],
            'h4' => ['class' => true, 'id' => true],
            'h5' => ['class' => true, 'id' => true],
            'h6' => ['class' => true, 'id' => true],
            'pre' => ['class' => true],
            'code' => ['class' => true],
            'table' => ['class' => true],
            'thead' => [],
            'tbody' => [],
            'tfoot' => [],
            'tr' => [],
            'th' => ['colspan' => true, 'rowspan' => true],
            'td' => ['colspan' => true, 'rowspan' => true],
        ];
    }
    
    /**
     * サニタイズ：テキスト
     * @param string $text
     * @return string
     */
    public function sanitize_text(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = sanitize_text_field($text);
        return $this->remove_dangerous_characters($text);
    }
    
    /**
     * サニタイズ：HTML
     * @param string $html
     * @param array<string, array<string, bool>>|null $allowed_html
     * @return string
     */
    public function sanitize_html(string $html, ?array $allowed_html = null): string {
        $allowed_html = $allowed_html ?? $this->allowed_html;
        
        // スクリプトタグを完全に除去
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        
        // イベントハンドラを除去
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        
        // JavaScriptプロトコルを除去
        $html = preg_replace('/javascript:/i', '', $html);
        
        return wp_kses($html, $allowed_html);
    }
    
    /**
     * サニタイズ：メールアドレス
     * @param string $email
     * @return string
     */
    public function sanitize_email(string $email): string {
        return sanitize_email($email);
    }
    
    /**
     * サニタイズ：URL
     * @param string $url
     * @param array<string>|null $protocols
     * @return string
     */
    public function sanitize_url(string $url, ?array $protocols = null): string {
        return esc_url_raw($url, $protocols);
    }
    
    /**
     * サニタイズ：ファイル名
     * @param string $filename
     * @return string
     */
    public function sanitize_filename(string $filename): string {
        $filename = sanitize_file_name($filename);
        
        // 追加のセキュリティ対策
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // 二重拡張子を防ぐ
        $filename = preg_replace('/\.+/', '.', $filename);
        
        return $filename;
    }
    
    /**
     * サニタイズ：SQL
     * @param string $sql
     * @param array<mixed> $args
     * @return string
     */
    public function sanitize_sql(string $sql, array $args = []): string {
        global $wpdb;
        
        if (empty($args)) {
            return $sql;
        }
        
        return $wpdb->prepare($sql, ...$args);
    }
    
    /**
     * バリデーション：メールアドレス
     * @param string $email
     * @return bool
     */
    public function validate_email(string $email): bool {
        return is_email($email) !== false;
    }
    
    /**
     * バリデーション：URL
     * @param string $url
     * @return bool
     */
    public function validate_url(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * バリデーション：IP アドレス
     * @param string $ip
     * @param int $flags
     * @return bool
     */
    public function validate_ip(string $ip, int $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }
    
    /**
     * バリデーション：電話番号（日本）
     * @param string $phone
     * @return bool
     */
    public function validate_phone_jp(string $phone): bool {
        // ハイフンなしも許可
        $phone = str_replace(['-', ' ', '(', ')'], '', $phone);
        
        // 日本の電話番号パターン
        $patterns = [
            '/^0[0-9]{9,10}$/',     // 固定電話・携帯
            '/^\+81[0-9]{9,10}$/',  // 国際番号
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * XSS対策
     * @param mixed $data
     * @return mixed
     */
    public function prevent_xss(mixed $data): mixed {
        if (is_array($data)) {
            return array_map([$this, 'prevent_xss'], $data);
        }
        
        if (is_object($data)) {
            $vars = get_object_vars($data);
            foreach ($vars as $key => $value) {
                $data->$key = $this->prevent_xss($value);
            }
            return $data;
        }
        
        if (is_string($data)) {
            return $this->sanitize_text($data);
        }
        
        return $data;
    }
    
    /**
     * SQLインジェクション対策
     * @param string $input
     * @return string
     */
    public function prevent_sql_injection(string $input): string {
        global $wpdb;
        return esc_sql($input);
    }
    
    /**
     * CSRFトークン生成
     * @param string $action
     * @return string
     */
    public function generate_csrf_token(string $action = 'urp_action'): string {
        $token = wp_create_nonce($action);
        
        // セッションに保存
        if (!session_id()) {
            session_start();
        }
        
        $_SESSION['urp_csrf_tokens'][$action] = [
            'token' => $token,
            'expiry' => time() + self::TOKEN_EXPIRY,
        ];
        
        return $token;
    }
    
    /**
     * CSRFトークン検証
     * @param string $token
     * @param string $action
     * @return bool
     */
    public function verify_csrf_token(string $token, string $action = 'urp_action'): bool {
        // WordPress nonce検証
        if (!wp_verify_nonce($token, $action)) {
            $this->log_security_event('csrf_failed', ['action' => $action]);
            return false;
        }
        
        // セッションチェック
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['urp_csrf_tokens'][$action])) {
            return false;
        }
        
        $stored = $_SESSION['urp_csrf_tokens'][$action];
        
        // 有効期限チェック
        if ($stored['expiry'] < time()) {
            unset($_SESSION['urp_csrf_tokens'][$action]);
            return false;
        }
        
        return $stored['token'] === $token;
    }
    
    /**
     * ブルートフォース対策
     * @param string $action
     * @param string|null $identifier
     * @return bool
     */
    public function check_brute_force(string $action, ?string $identifier = null): bool {
        $identifier = $identifier ?? $this->get_client_ip();
        $cache_key = 'brute_force_' . $action . '_' . md5($identifier);
        
        $attempts = get_transient($cache_key) ?: 0;
        
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->log_security_event('brute_force_blocked', [
                'action' => $action,
                'identifier' => $identifier,
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * ブルートフォース試行記録
     * @param string $action
     * @param string|null $identifier
     * @return void
     */
    public function record_failed_attempt(string $action, ?string $identifier = null): void {
        $identifier = $identifier ?? $this->get_client_ip();
        $cache_key = 'brute_force_' . $action . '_' . md5($identifier);
        
        $attempts = get_transient($cache_key) ?: 0;
        $attempts++;
        
        set_transient($cache_key, $attempts, self::BLOCK_DURATION);
        
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->log_security_event('brute_force_limit_reached', [
                'action' => $action,
                'identifier' => $identifier,
                'attempts' => $attempts,
            ]);
        }
    }
    
    /**
     * ブルートフォース試行リセット
     * @param string $action
     * @param string|null $identifier
     * @return void
     */
    public function reset_failed_attempts(string $action, ?string $identifier = null): void {
        $identifier = $identifier ?? $this->get_client_ip();
        $cache_key = 'brute_force_' . $action . '_' . md5($identifier);
        
        delete_transient($cache_key);
    }
    
    /**
     * ファイルアップロード検証
     * @param array<string, mixed> $file
     * @param array<string> $allowed_types
     * @param int $max_size
     * @return true|\WP_Error
     */
    public function validate_file_upload(
        array $file,
        array $allowed_types = [],
        int $max_size = 5242880 // 5MB
    ): true|\WP_Error {
        // エラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', __('File upload failed', 'universal-review'));
        }
        
        // サイズチェック
        if ($file['size'] > $max_size) {
            return new \WP_Error('file_too_large', sprintf(
                __('File size must not exceed %s', 'universal-review'),
                size_format($max_size)
            ));
        }
        
        // MIMEタイプチェック
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        
        if (!$filetype['ext'] || !$filetype['type']) {
            return new \WP_Error('invalid_file_type', __('Invalid file type', 'universal-review'));
        }
        
        // 許可された拡張子チェック
        if (!empty($allowed_types)) {
            $ext = strtolower($filetype['ext']);
            if (!in_array($ext, $allowed_types, true)) {
                return new \WP_Error('disallowed_file_type', sprintf(
                    __('File type %s is not allowed', 'universal-review'),
                    $ext
                ));
            }
        }
        
        // 実際のMIMEタイプ検証（マジックナンバー）
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime !== $filetype['type']) {
            return new \WP_Error('mime_mismatch', __('File type mismatch detected', 'universal-review'));
        }
        
        // ウイルススキャン（ClamAV等が利用可能な場合）
        if ($this->has_virus_scanner()) {
            $scan_result = $this->scan_file_for_virus($file['tmp_name']);
            if (is_wp_error($scan_result)) {
                return $scan_result;
            }
        }
        
        return true;
    }
    
    /**
     * 入力データの検証
     * PHP 8.0: match式
     * @param mixed $value
     * @param array<string, mixed> $rules
     * @return true|string
     */
    public function validate_input(mixed $value, array $rules): true|string {
        foreach ($rules as $rule => $params) {
            $result = match($rule) {
                'required' => $this->validate_required($value, $params),
                'email' => $this->validate_email($value),
                'url' => $this->validate_url($value),
                'min_length' => $this->validate_min_length($value, $params),
                'max_length' => $this->validate_max_length($value, $params),
                'min' => $this->validate_min($value, $params),
                'max' => $this->validate_max($value, $params),
                'pattern' => $this->validate_pattern($value, $params),
                'in' => $this->validate_in($value, $params),
                'date' => $this->validate_date($value, $params),
                'numeric' => is_numeric($value),
                'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
                'boolean' => is_bool($value),
                'array' => is_array($value),
                default => true
            };
            
            if ($result !== true) {
                return is_string($result) ? $result : $this->get_validation_message($rule, $params);
            }
        }
        
        return true;
    }
    
    /**
     * パスワード強度チェック
     * @param string $password
     * @return array{strength: string, score: int, suggestions: array<string>}
     */
    public function check_password_strength(string $password): array {
        $score = 0;
        $suggestions = [];
        
        // 長さチェック
        $length = strlen($password);
        if ($length >= 8) {
            $score += 20;
        } else {
            $suggestions[] = __('Use at least 8 characters', 'universal-review');
        }
        
        if ($length >= 12) {
            $score += 10;
        }
        
        // 大文字チェック
        if (preg_match('/[A-Z]/', $password)) {
            $score += 20;
        } else {
            $suggestions[] = __('Include uppercase letters', 'universal-review');
        }
        
        // 小文字チェック
        if (preg_match('/[a-z]/', $password)) {
            $score += 20;
        } else {
            $suggestions[] = __('Include lowercase letters', 'universal-review');
        }
        
        // 数字チェック
        if (preg_match('/[0-9]/', $password)) {
            $score += 20;
        } else {
            $suggestions[] = __('Include numbers', 'universal-review');
        }
        
        // 特殊文字チェック
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 20;
        } else {
            $suggestions[] = __('Include special characters', 'universal-review');
        }
        
        // 強度判定
        $strength = match(true) {
            $score >= 80 => 'strong',
            $score >= 60 => 'medium',
            $score >= 40 => 'weak',
            default => 'very_weak'
        };
        
        return [
            'strength' => $strength,
            'score' => $score,
            'suggestions' => $suggestions,
        ];
    }
    
    /**
     * IPアドレス取得
     * @return string
     */
    public function get_client_ip(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // カンマ区切りの場合は最初のIPを取得
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                if ($this->validate_ip($ip)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * セキュリティヘッダー設定
     * @return void
     */
    private function init_security_headers(): void {
        add_action('send_headers', function(): void {
            // Content Security Policy
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://maps.googleapis.com; style-src 'self' 'unsafe-inline';");
            
            // XSS Protection
            header("X-XSS-Protection: 1; mode=block");
            
            // Content Type Options
            header("X-Content-Type-Options: nosniff");
            
            // Frame Options
            header("X-Frame-Options: SAMEORIGIN");
            
            // Referrer Policy
            header("Referrer-Policy: strict-origin-when-cross-origin");
            
            // Permissions Policy
            header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        });
    }
    
    /**
     * データ暗号化
     * @param string $data
     * @param string|null $key
     * @return string
     */
    public function encrypt(string $data, ?string $key = null): string {
        $key = $key ?? wp_salt('auth');
        $cipher = 'AES-256-CBC';
        
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * データ復号化
     * @param string $data
     * @param string|null $key
     * @return string|false
     */
    public function decrypt(string $data, ?string $key = null): string|false {
        $key = $key ?? wp_salt('auth');
        $cipher = 'AES-256-CBC';
        
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        
        return openssl_decrypt($encrypted_data, $cipher, $key, 0, $iv);
    }
    
    /**
     * セキュリティイベントログ
     * @param string $event
     * @param array<string, mixed> $context
     * @return void
     */
    public function log_security_event(string $event, array $context = []): void {
        $log_entry = [
            'event' => $event,
            'context' => $context,
            'ip' => $this->get_client_ip(),
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        
        $this->security_log[] = $log_entry;
        
        // データベースに保存
        if (urp_get_option('log_security_events', true)) {
            global $wpdb;
            
            $wpdb->insert(
                $wpdb->prefix . URP_TABLE_ANALYTICS,
                [
                    'event_type' => 'security_' . $event,
                    'event_data' => json_encode($context),
                    'user_id' => $log_entry['user_id'],
                    'ip_address' => $log_entry['ip'],
                    'user_agent' => $log_entry['user_agent'],
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
        }
        
        // 重大なイベントは管理者に通知
        if (in_array($event, ['brute_force_blocked', 'sql_injection_attempt', 'xss_attempt'], true)) {
            $this->notify_admin($event, $context);
        }
    }
    
    /**
     * 管理者通知
     * @param string $event
     * @param array<string, mixed> $context
     * @return void
     */
    private function notify_admin(string $event, array $context): void {
        $admin_email = get_option('admin_email');
        $subject = sprintf('[%s] Security Alert: %s', get_bloginfo('name'), $event);
        
        $message = sprintf(
            "Security event detected on your website:\n\n" .
            "Event: %s\n" .
            "Time: %s\n" .
            "IP: %s\n" .
            "User: %s\n" .
            "Details: %s\n",
            $event,
            current_time('mysql'),
            $this->get_client_ip(),
            get_current_user_id() ? get_userdata(get_current_user_id())->user_login : 'Guest',
            json_encode($context, JSON_PRETTY_PRINT)
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    // プライベートヘルパーメソッド
    
    private function remove_dangerous_characters(string $text): string {
        // Null バイト除去
        $text = str_replace("\0", '', $text);
        
        // 制御文字除去
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return $text;
    }
    
    private function validate_required(mixed $value, bool $required): bool {
        if (!$required) {
            return true;
        }
        
        return !empty($value);
    }
    
    private function validate_min_length(string $value, int $min): bool {
        return strlen($value) >= $min;
    }
    
    private function validate_max_length(string $value, int $max): bool {
        return strlen($value) <= $max;
    }
    
    private function validate_min(mixed $value, mixed $min): bool {
        return $value >= $min;
    }
    
    private function validate_max(mixed $value, mixed $max): bool {
        return $value <= $max;
    }
    
    private function validate_pattern(string $value, string $pattern): bool {
        return preg_match($pattern, $value) === 1;
    }
    
    private function validate_in(mixed $value, array $options): bool {
        return in_array($value, $options, true);
    }
    
    private function validate_date(string $value, string $format = 'Y-m-d'): bool {
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }
    
    private function get_validation_message(string $rule, mixed $params): string {
        return match($rule) {
            'required' => __('This field is required', 'universal-review'),
            'email' => __('Please enter a valid email address', 'universal-review'),
            'url' => __('Please enter a valid URL', 'universal-review'),
            'min_length' => sprintf(__('Minimum %d characters required', 'universal-review'), $params),
            'max_length' => sprintf(__('Maximum %d characters allowed', 'universal-review'), $params),
            'min' => sprintf(__('Value must be at least %s', 'universal-review'), $params),
            'max' => sprintf(__('Value must not exceed %s', 'universal-review'), $params),
            'pattern' => __('Invalid format', 'universal-review'),
            'in' => __('Invalid selection', 'universal-review'),
            'date' => __('Invalid date format', 'universal-review'),
            'numeric' => __('Must be a number', 'universal-review'),
            'integer' => __('Must be an integer', 'universal-review'),
            default => __('Invalid value', 'universal-review')
        };
    }
    
    private function has_virus_scanner(): bool {
        // ClamAV等のウイルススキャナーが利用可能かチェック
        return function_exists('clamav_scan_file') || file_exists('/usr/bin/clamscan');
    }
    
    private function scan_file_for_virus(string $filepath): true|\WP_Error {
        // ウイルススキャンの実装（環境依存）
        if (function_exists('clamav_scan_file')) {
            $result = clamav_scan_file($filepath);
            if ($result !== true) {
                return new \WP_Error('virus_detected', __('Virus detected in uploaded file', 'universal-review'));
            }
        }
        
        return true;
    }
    
    /**
     * フック登録
     * @return void
     */
    private function register_hooks(): void {
        // ログイン試行の監視
        add_action('wp_login_failed', [$this, 'handle_login_failed']);
        add_action('wp_login', [$this, 'handle_login_success'], 10, 2);
        
        // アップロードフィルター
        add_filter('wp_handle_upload_prefilter', [$this, 'pre_upload_security_check']);
        
        // コメントフィルター
        add_filter('preprocess_comment', [$this, 'sanitize_comment']);
    }
    
    /**
     * ログイン失敗処理
     * @param string $username
     * @return void
     */
    public function handle_login_failed(string $username): void {
        $this->record_failed_attempt('login', $username);
        $this->log_security_event('login_failed', ['username' => $username]);
    }
    
    /**
     * ログイン成功処理
     * @param string $user_login
     * @param \WP_User $user
     * @return void
     */
    public function handle_login_success(string $user_login, \WP_User $user): void {
        $this->reset_failed_attempts('login', $user_login);
        $this->log_security_event('login_success', ['user_id' => $user->ID]);
    }
    
    /**
     * アップロード前セキュリティチェック
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function pre_upload_security_check(array $file): array {
        $validation = $this->validate_file_upload($file, URP_ALLOWED_IMAGE_TYPES);
        
        if (is_wp_error($validation)) {
            $file['error'] = $validation->get_error_message();
        }
        
        return $file;
    }
    
    /**
     * コメントサニタイズ
     * @param array<string, mixed> $commentdata
     * @return array<string, mixed>
     */
    public function sanitize_comment(array $commentdata): array {
        $commentdata['comment_content'] = $this->sanitize_html($commentdata['comment_content']);
        $commentdata['comment_author'] = $this->sanitize_text($commentdata['comment_author']);
        $commentdata['comment_author_email'] = $this->sanitize_email($commentdata['comment_author_email']);
        $commentdata['comment_author_url'] = $this->sanitize_url($commentdata['comment_author_url']);
        
        return $commentdata;
    }
}