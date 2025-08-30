<?php
/**
 * Universal Review Platform - Class Loader with Namespace Support
 * 
 * PSR-4準拠のオートローダー実装
 * namespace対応で将来的な拡張性を確保
 */

if (!defined('ABSPATH')) {
    exit;
}

class URP_Loader {
    
    /**
     * namespace マッピング
     * PSR-4準拠のディレクトリ構造
     */
    private static $namespaces = [
        'URP\\Core\\'      => 'core/',
        'URP\\Admin\\'     => 'admin/',
        'URP\\Public\\'    => 'public/',
        'URP\\API\\'       => 'api/',
        'URP\\Extensions\\' => 'extensions/',
        'URP\\Includes\\'  => 'includes/',
    ];
    
    /**
     * クラスマップ（後方互換用）
     * 既存のnamespace無しクラスも動作させる
     */
    private static $classmap = [
        // コア機能
        'URP_Database'          => 'core/class-database.php',
        'URP_Review_Manager'    => 'core/class-review-manager.php',
        'URP_Security'          => 'core/class-security.php',
        'URP_API_Router'        => 'core/class-api-router.php',
        'URP_Cache_Manager'     => 'core/class-cache-manager.php',
        
        // namespace付きクラス（エイリアス）
        'URP_Site_Mode'         => 'core/class-site-mode.php',
        'URP_Extension_Manager' => 'core/class-extension-manager.php',
        'URP_Rating_Fields'     => 'core/class-rating-fields.php',
        'URP_Trust_Score'       => 'core/class-trust-score.php',
        'URP_Affiliate_Manager' => 'core/class-affiliate-manager.php',
        
        // 管理画面
        'URP_Implementation_Status' => 'admin/class-implementation-status.php',
        
        // アクティベーション/デアクティベーション
        'URP_Activator'         => 'activate.php',
        'URP_Deactivator'       => 'deactivate.php',
    ];
    
    /**
     * 実装優先度（将来の開発指針）
     */
    private static $priorities = [
        1 => 'URP_Gamification',        // ゲーミフィケーション
        2 => 'URP_Social_Proof',        // 社会的証明
        3 => 'URP_ML_Spam_Detector',    // 機械学習スパム検出
        4 => 'URP_Realtime_Update',     // リアルタイム更新
        5 => 'URP_AI_Summary',          // AI要約
    ];
    
    /**
     * オートローダー登録
     */
    public static function register() {
        spl_autoload_register([self::class, 'autoload']);
    }
    
    /**
     * メインのオートロード処理
     * 
     * @param string $class 完全修飾クラス名
     */
    public static function autoload($class) {
        // namespace付きクラスの処理
        if (strpos($class, 'URP\\') === 0) {
            self::load_namespaced_class($class);
            return;
        }
        
        // 従来のURP_プレフィックスクラス
        if (strpos($class, 'URP_') === 0) {
            self::load_legacy_class($class);
            return;
        }
    }
    
    /**
     * namespace付きクラスの読み込み
     * PSR-4準拠
     * 
     * @param string $class 例: URP\Core\URP_Site_Mode
     */
    private static function load_namespaced_class($class) {
        // namespaceとクラス名を分離
        $parts = explode('\\', $class);
        
        // URP\Core\URP_Site_Mode -> URP\Core\ と URP_Site_Mode
        $class_name = array_pop($parts);
        $namespace = implode('\\', $parts) . '\\';
        
        // namespace マッピングから探す
        foreach (self::$namespaces as $ns => $dir) {
            if (strpos($namespace, $ns) === 0) {
                // ファイル名を構築
                // URP_Site_Mode -> class-site-mode.php
                $filename = self::get_filename_from_class($class_name);
                
                $file = URP_PLUGIN_DIR . $dir . $filename;
                
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
                
                // クラス名そのままのファイル名も試す
                // 例: SomeClass.php
                $alt_file = URP_PLUGIN_DIR . $dir . $class_name . '.php';
                if (file_exists($alt_file)) {
                    require_once $alt_file;
                    return;
                }
            }
        }
        
        // 見つからない場合はデバッグ情報を出力（開発時のみ）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("URP Autoloader: Could not find class {$class}");
        }
    }
    
    /**
     * 従来のクラス読み込み（後方互換）
     * 
     * @param string $class 例: URP_Site_Mode
     */
    private static function load_legacy_class($class) {
        // クラスマップから探す
        if (isset(self::$classmap[$class])) {
            $file = URP_PLUGIN_DIR . self::$classmap[$class];
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        
        // 動的にファイル名を生成して探す
        $filename = self::get_filename_from_class($class);
        
        // 検索ディレクトリ
        $directories = [
            'core/',
            'admin/',
            'public/',
            'includes/',
            'api/',
        ];
        
        foreach ($directories as $dir) {
            $file = URP_PLUGIN_DIR . $dir . $filename;
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        
        // インターフェース・トレイトも探す
        self::load_interface_trait($class);
    }
    
    /**
     * クラス名からファイル名を生成
     * URP_Site_Mode -> class-site-mode.php
     * 
     * @param string $class
     * @return string
     */
    private static function get_filename_from_class($class) {
        // URP_プレフィックスを除去
        $class_name = str_replace('URP_', '', $class);
        
        // アンダースコアをハイフンに変換
        $class_name = str_replace('_', '-', $class_name);
        
        // 小文字に変換
        $class_name = strtolower($class_name);
        
        // class-プレフィックスを付与
        return 'class-' . $class_name . '.php';
    }
    
    /**
     * インターフェース・トレイトの読み込み
     * 
     * @param string $class
     */
    private static function load_interface_trait($class) {
        $class_lower = strtolower(str_replace('URP_', '', $class));
        
        $files = [
            URP_PLUGIN_DIR . 'core/interface-' . str_replace('_', '-', $class_lower) . '.php',
            URP_PLUGIN_DIR . 'core/trait-' . str_replace('_', '-', $class_lower) . '.php',
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
    
    /**
     * 専門プラグインからの拡張登録
     * 
     * @param string $extension_id
     * @param array $classes
     */
    public static function register_extension($extension_id, $classes) {
        // 専門プラグインのクラスマップに追加
        foreach ($classes as $class => $file) {
            self::$classmap[$class] = 'extensions/' . $extension_id . '/' . $file;
        }
        
        // namespace も登録
        $namespace = 'URP\\Extensions\\' . ucfirst($extension_id) . '\\';
        self::$namespaces[$namespace] = 'extensions/' . $extension_id . '/';
    }
    
    /**
     * 実装状況を取得（デバッグ用）
     * 
     * @return array
     */
    public static function get_implementation_status() {
        $status = [];
        
        foreach (self::$classmap as $class => $file) {
            $full_path = URP_PLUGIN_DIR . $file;
            $exists = file_exists($full_path);
            
            // namespaceバージョンも確認
            $ns_exists = class_exists('URP\\Core\\' . $class) || 
                        class_exists('URP\\Admin\\' . $class);
            
            $priority = array_search($class, self::$priorities);
            
            $status[$class] = [
                'file' => $file,
                'file_exists' => $exists,
                'class_exists' => class_exists($class) || $ns_exists,
                'priority' => $priority ?: 999,
                'status' => $exists ? '✅ 実装済み' : '🔲 未実装'
            ];
        }
        
        // 優先度順にソート
        uasort($status, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $status;
    }
    
    /**
     * namespace付きクラスの互換性ブリッジ
     * 古いコードがnamespace無しで呼び出しても動作するように
     */
    public static function create_compatibility_aliases() {
        $namespace_classes = [
            'URP\\Core\\URP_Site_Mode' => 'URP_Site_Mode',
            'URP\\Core\\URP_Extension_Manager' => 'URP_Extension_Manager',
            'URP\\Core\\URP_Rating_Fields' => 'URP_Rating_Fields',
            'URP\\Core\\URP_Trust_Score' => 'URP_Trust_Score',
            'URP\\Core\\URP_Affiliate_Manager' => 'URP_Affiliate_Manager',
            'URP\\Admin\\URP_Implementation_Status' => 'URP_Implementation_Status',
        ];
        
        foreach ($namespace_classes as $ns_class => $alias) {
            if (class_exists($ns_class) && !class_exists($alias)) {
                class_alias($ns_class, $alias);
            }
        }
    }
    
    /**
     * デバッグ情報出力
     */
    public static function debug_info() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        echo "<!-- URP Loader Debug Info\n";
        echo "Registered Namespaces:\n";
        foreach (self::$namespaces as $ns => $dir) {
            echo "  {$ns} => {$dir}\n";
        }
        
        echo "\nClassmap entries: " . count(self::$classmap) . "\n";
        
        $status = self::get_implementation_status();
        $implemented = array_filter($status, function($s) {
            return $s['file_exists'];
        });
        
        echo "Implemented: " . count($implemented) . "/" . count($status) . "\n";
        echo "-->\n";
    }
}

// デバッグモードの場合、フッターに情報出力
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', ['URP_Loader', 'debug_info']);
    add_action('admin_footer', ['URP_Loader', 'debug_info']);
}

// 互換性エイリアスを作成（プラグイン読み込み後）
add_action('plugins_loaded', ['URP_Loader', 'create_compatibility_aliases'], 1);