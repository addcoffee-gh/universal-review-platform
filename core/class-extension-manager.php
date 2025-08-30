<?php
/**
 * Extension Manager - 専門プラグイン管理
 * 
 * 専門プラグインの登録と切り替えを管理
 */

namespace URP\Core;

if (!defined('ABSPATH')) {
    exit;
}

class URP_Extension_Manager {
    
    /**
     * 登録された専門プラグイン
     */
    private static $extensions = [];
    
    /**
     * 初期化
     */
    public static function init() {
        // 専門プラグインの自動検出
        add_action('plugins_loaded', [self::class, 'detect_extensions'], 20);
        
        // 拡張用フックポイント
        add_action('urp_register_extension', [self::class, 'register_extension'], 10, 2);
    }
    
    /**
     * 専門プラグインを登録
     * 各専門プラグインが自分自身を登録する
     */
    public static function register_extension($extension_id, $config) {
        self::$extensions[$extension_id] = wp_parse_args($config, [
            'name' => '',
            'version' => '1.0.0',
            'review_types' => ['shop', 'product'],
            'fields' => [],
            'hooks' => [],
            'tables' => [],
            'file' => '',
        ]);
        
        // 評価項目を登録
        if (!empty($config['fields'])) {
            add_filter('urp_rating_fields', function($fields) use ($extension_id, $config) {
                $fields[$extension_id] = $config['fields'];
                return $fields;
            });
        }
        
        // レビュータイプを登録
        add_filter('urp_review_types', function($types) use ($extension_id, $config) {
            $types[$extension_id] = $config['review_types'];
            return $types;
        });
        
        // 拡張テーブルを登録
        if (!empty($config['tables'])) {
            add_filter('urp_extension_tables', function($tables) use ($config) {
                return array_merge($tables, $config['tables']);
            });
        }
    }
    
    /**
     * インストール済み専門プラグインを検出
     */
    public static function detect_extensions() {
        $active_plugins = get_option('active_plugins', []);
        
        foreach ($active_plugins as $plugin) {
            // universal-review-xxxxx パターンを検出
            if (preg_match('/universal-review-(.+?)\//', $plugin, $matches)) {
                $extension_type = $matches[1];
                
                // curry, ramen, salon などの専門プラグイン
                if ($extension_type !== 'platform') {
                    do_action('urp_extension_detected', $extension_type, $plugin);
                }
            }
        }
    }
    
    /**
     * アクティブな専門プラグインを取得
     */
    public static function get_active_extension() {
        // 設定から取得
        $active = get_option('urp_active_extension', 'generic');
        
        // 登録されているか確認
        if (isset(self::$extensions[$active])) {
            return $active;
        }
        
        // 最初に見つかった専門プラグインを使用
        if (!empty(self::$extensions)) {
            return array_key_first(self::$extensions);
        }
        
        return 'generic';
    }
    
    /**
     * 専門プラグインの設定を取得
     */
    public static function get_extension_config($extension_id = null) {
        if (!$extension_id) {
            $extension_id = self::get_active_extension();
        }
        
        return self::$extensions[$extension_id] ?? [
            'name' => 'Generic',
            'review_types' => ['shop' => '店舗', 'product' => '商品'],
        ];
    }
    
    /**
     * レビュータイプを取得
     */
    public static function get_review_types() {
        $extension = self::get_active_extension();
        $config = self::get_extension_config($extension);
        
        return apply_filters('urp_review_types_' . $extension, $config['review_types']);
    }
    
    /**
     * 拡張用フックポイントを提供
     */
    public static function get_extension_hooks() {
        return [
            // フォーム関連
            'urp_before_review_form' => 'レビューフォーム前',
            'urp_review_form_fields' => '評価項目追加',
            'urp_after_review_form' => 'レビューフォーム後',
            
            // 表示関連
            'urp_before_review_display' => 'レビュー表示前',
            'urp_review_display_fields' => '表示項目追加',
            'urp_after_review_display' => 'レビュー表示後',
            
            // データ処理
            'urp_review_validation' => 'バリデーション追加',
            'urp_before_review_save' => '保存前処理',
            'urp_after_review_save' => '保存後処理',
            
            // ランキング・集計
            'urp_ranking_algorithm' => 'ランキング計算',
            'urp_aggregate_ratings' => '評価集計',
            
            // 管理画面
            'urp_admin_review_columns' => '管理画面カラム追加',
            'urp_admin_review_filters' => '管理画面フィルター追加',
        ];
    }
    
    /**
     * 拡張テーブルのプレフィックスを取得
     */
    public static function get_table_prefix($extension_id = null) {
        global $wpdb;
        
        if (!$extension_id) {
            $extension_id = self::get_active_extension();
        }
        
        return $wpdb->prefix . 'urp_' . $extension_id . '_';
    }
    
    /**
     * 登録済み専門プラグインのリストを取得
     */
    public static function get_registered_extensions() {
        return self::$extensions;
    }
    
    /**
     * 専門プラグインが有効か確認
     */
    public static function is_extension_active($extension_id) {
        return isset(self::$extensions[$extension_id]);
    }
    
    /**
     * 管理画面で専門プラグイン切り替え
     */
    public static function render_extension_selector() {
        $current = self::get_active_extension();
        ?>
        <select name="urp_active_extension" id="urp_active_extension">
            <option value="generic" <?php selected($current, 'generic'); ?>>
                汎用（Generic）
            </option>
            <?php foreach (self::$extensions as $id => $config) : ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($current, $id); ?>>
                    <?php echo esc_html($config['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}

// 初期化
add_action('init', ['URP\Core\URP_Extension_Manager', 'init']);