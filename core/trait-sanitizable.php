<?php
/**
 * Universal Review Platform - Sanitizable Trait
 * 
 * サニタイズ処理を提供するトレイト
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

namespace URP\Core\Traits;

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * サニタイズ可能トレイト
 */
trait Sanitizable {
    
    /**
     * サニタイズルール
     * @var array<string, string|array<string, mixed>>
     */
    protected array $sanitize_rules = [];
    
    /**
     * サニタイズ済みデータ
     * @var array<string, mixed>
     */
    protected array $sanitized_data = [];
    
    /**
     * データをサニタイズ
     * @param array<string, mixed> $data
     * @param array<string, string|array<string, mixed>>|null $rules
     * @return array<string, mixed>
     */
    public function sanitize_data(array $data, ?array $rules = null): array {
        $rules = $rules ?? $this->sanitize_rules;
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (!isset($rules[$key])) {
                // ルールがない場合はデフォルトサニタイズ
                $sanitized[$key] = $this->default_sanitize($value);
                continue;
            }
            
            $rule = $rules[$key];
            
            // PHP 8.0: match式でサニタイズ処理
            $sanitized[$key] = is_string($rule) 
                ? $this->apply_sanitize_rule($value, $rule)
                : $this->apply_complex_sanitize_rule($value, $rule);
        }
        
        $this->sanitized_data = $sanitized;
        
        return $sanitized;
    }
    
    /**
     * 単一の値をサニタイズ
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public function sanitize_value(mixed $value, string $type): mixed {
        return $this->apply_sanitize_rule($value, $type);
    }
    
    /**
     * サニタイズルールを適用
     * @param mixed $value
     * @param string $rule
     * @return mixed
     */
    protected function apply_sanitize_rule(mixed $value, string $rule): mixed {
        // PHP 8.0: match式
        return match($rule) {
            'text' => $this->sanitize_text($value),
            'textarea' => $this->sanitize_textarea($value),
            'html' => $this->sanitize_html($value),
            'email' => $this->sanitize_email($value),
            'url' => $this->sanitize_url($value),
            'key' => $this->sanitize_key($value),
            'title' => $this->sanitize_title($value),
            'filename' => $this->sanitize_filename($value),
            'int', 'integer' => $this->sanitize_int($value),
            'float', 'double' => $this->sanitize_float($value),
            'bool', 'boolean' => $this->sanitize_bool($value),
            'array' => $this->sanitize_array($value),
            'json' => $this->sanitize_json($value),
            'date' => $this->sanitize_date($value),
            'time' => $this->sanitize_time($value),
            'datetime' => $this->sanitize_datetime($value),
            'phone' => $this->sanitize_phone($value),
            'postcode' => $this->sanitize_postcode($value),
            'hex_color' => $this->sanitize_hex_color($value),
            'sql' => $this->sanitize_sql($value),
            'javascript' => $this->sanitize_javascript($value),
            'css' => $this->sanitize_css($value),
            'none', 'raw' => $value,
            default => $this->default_sanitize($value)
        };
    }
    
    /**
     * 複雑なサニタイズルールを適用
     * @param mixed $value
     * @param array<string, mixed> $rule
     * @return mixed
     */
    protected function apply_complex_sanitize_rule(mixed $value, array $rule): mixed {
        // タイプ指定
        if (isset($rule['type'])) {
            $value = $this->apply_sanitize_rule($value, $rule['type']);
        }
        
        // カスタムコールバック
        if (isset($rule['callback']) && is_callable($rule['callback'])) {
            $value = call_user_func($rule['callback'], $value);
        }
        
        // 許可された値のみ
        if (isset($rule['allowed']) && is_array($rule['allowed'])) {
            if (!in_array($value, $rule['allowed'], true)) {
                $value = $rule['default'] ?? null;
            }
        }
        
        // 最小値・最大値
        if (is_numeric($value)) {
            if (isset($rule['min']) && $value < $rule['min']) {
                $value = $rule['min'];
            }
            if (isset($rule['max']) && $value > $rule['max']) {
                $value = $rule['max'];
            }
        }
        
        // 文字列長制限
        if (is_string($value)) {
            if (isset($rule['max_length'])) {
                $value = substr($value, 0, $rule['max_length']);
            }
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $value = $rule['default'] ?? '';
            }
        }
        
        // 正規表現パターン
        if (isset($rule['pattern']) && is_string($value)) {
            if (!preg_match($rule['pattern'], $value)) {
                $value = $rule['default'] ?? '';
            }
        }
        
        // 必須チェック
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $value = $rule['default'] ?? '';
        }
        
        return $value;
    }
    
    /**
     * テキストサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_text(mixed $value): string {
        return sanitize_text_field((string)$value);
    }
    
    /**
     * テキストエリアサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_textarea(mixed $value): string {
        return sanitize_textarea_field((string)$value);
    }
    
    /**
     * HTMLサニタイズ
     * @param mixed $value
     * @param array<string, array<string, bool>>|null $allowed_html
     * @return string
     */
    protected function sanitize_html(mixed $value, ?array $allowed_html = null): string {
        if ($allowed_html === null) {
            $allowed_html = wp_kses_allowed_html('post');
        }
        
        return wp_kses((string)$value, $allowed_html);
    }
    
    /**
     * メールアドレスサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_email(mixed $value): string {
        return sanitize_email((string)$value);
    }
    
    /**
     * URLサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_url(mixed $value): string {
        return esc_url_raw((string)$value);
    }
    
    /**
     * キーサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_key(mixed $value): string {
        return sanitize_key((string)$value);
    }
    
    /**
     * タイトルサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_title(mixed $value): string {
        return sanitize_title((string)$value);
    }
    
    /**
     * ファイル名サニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_filename(mixed $value): string {
        return sanitize_file_name((string)$value);
    }
    
    /**
     * 整数サニタイズ
     * @param mixed $value
     * @return int
     */
    protected function sanitize_int(mixed $value): int {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * 浮動小数点数サニタイズ
     * @param mixed $value
     * @return float
     */
    protected function sanitize_float(mixed $value): float {
        return (float) filter_var(
            $value,
            FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION
        );
    }
    
    /**
     * 真偽値サニタイズ
     * @param mixed $value
     * @return bool
     */
    protected function sanitize_bool(mixed $value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * 配列サニタイズ
     * @param mixed $value
     * @param string $item_type
     * @return array<mixed>
     */
    protected function sanitize_array(mixed $value, string $item_type = 'text'): array {
        if (!is_array($value)) {
            return [];
        }
        
        return array_map(
            fn($item) => $this->apply_sanitize_rule($item, $item_type),
            $value
        );
    }
    
    /**
     * JSONサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_json(mixed $value): string {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded);
            }
        }
        
        return json_encode($value);
    }
    
    /**
     * 日付サニタイズ
     * @param mixed $value
     * @param string $format
     * @return string
     */
    protected function sanitize_date(mixed $value, string $format = 'Y-m-d'): string {
        $date = strtotime((string)$value);
        
        if ($date === false) {
            return '';
        }
        
        return date($format, $date);
    }
    
    /**
     * 時刻サニタイズ
     * @param mixed $value
     * @param string $format
     * @return string
     */
    protected function sanitize_time(mixed $value, string $format = 'H:i:s'): string {
        return $this->sanitize_date($value, $format);
    }
    
    /**
     * 日時サニタイズ
     * @param mixed $value
     * @param string $format
     * @return string
     */
    protected function sanitize_datetime(mixed $value, string $format = 'Y-m-d H:i:s'): string {
        return $this->sanitize_date($value, $format);
    }
    
    /**
     * 電話番号サニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_phone(mixed $value): string {
        // 数字とハイフン、プラス記号のみ残す
        return preg_replace('/[^0-9\-\+\(\) ]/', '', (string)$value);
    }
    
    /**
     * 郵便番号サニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_postcode(mixed $value): string {
        // 数字とハイフンのみ残す
        return preg_replace('/[^0-9\-]/', '', (string)$value);
    }
    
    /**
     * 16進カラーコードサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_hex_color(mixed $value): string {
        $color = sanitize_hex_color((string)$value);
        
        if (empty($color)) {
            $color = sanitize_hex_color_no_hash((string)$value);
            if (!empty($color)) {
                $color = '#' . $color;
            }
        }
        
        return $color;
    }
    
    /**
     * SQLサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_sql(mixed $value): string {
        global $wpdb;
        return $wpdb->_real_escape((string)$value);
    }
    
    /**
     * JavaScriptサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_javascript(mixed $value): string {
        return esc_js((string)$value);
    }
    
    /**
     * CSSサニタイズ
     * @param mixed $value
     * @return string
     */
    protected function sanitize_css(mixed $value): string {
        return wp_strip_all_tags((string)$value);
    }
    
    /**
     * デフォルトサニタイズ
     * @param mixed $value
     * @return mixed
     */
    protected function default_sanitize(mixed $value): mixed {
        if (is_string($value)) {
            return $this->sanitize_text($value);
        }
        
        if (is_array($value)) {
            return array_map([$this, 'default_sanitize'], $value);
        }
        
        if (is_bool($value)) {
            return $this->sanitize_bool($value);
        }
        
        if (is_int($value)) {
            return $this->sanitize_int($value);
        }
        
        if (is_float($value)) {
            return $this->sanitize_float($value);
        }
        
        return $value;
    }
    
    /**
     * サニタイズルール設定
     * @param array<string, string|array<string, mixed>> $rules
     * @return void
     */
    public function set_sanitize_rules(array $rules): void {
        $this->sanitize_rules = $rules;
    }
    
    /**
     * サニタイズルール追加
     * @param string $key
     * @param string|array<string, mixed> $rule
     * @return void
     */
    public function add_sanitize_rule(string $key, string|array $rule): void {
        $this->sanitize_rules[$key] = $rule;
    }
    
    /**
     * サニタイズ済みデータ取得
     * @param string|null $key
     * @return mixed
     */
    public function get_sanitized(string $key = null): mixed {
        if ($key === null) {
            return $this->sanitized_data;
        }
        
        return $this->sanitized_data[$key] ?? null;
    }
    
    /**
     * バリデーションエラーメッセージ取得用
     * @param string $field
     * @param string $rule
     * @return string
     */
    protected function get_sanitize_error_message(string $field, string $rule): string {
        $messages = [
            'email' => __('%s must be a valid email address', 'universal-review'),
            'url' => __('%s must be a valid URL', 'universal-review'),
            'int' => __('%s must be an integer', 'universal-review'),
            'float' => __('%s must be a number', 'universal-review'),
            'date' => __('%s must be a valid date', 'universal-review'),
            'phone' => __('%s must be a valid phone number', 'universal-review'),
        ];
        
        $message = $messages[$rule] ?? __('%s is invalid', 'universal-review');
        
        return sprintf($message, $field);
    }
}