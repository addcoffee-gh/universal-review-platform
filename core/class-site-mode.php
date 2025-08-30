<?php
/**
 * Site Mode Manager - サイト運営モード管理（簡略化版）
 * 
 * 3つのモード：ハイブリッド、店舗のみ、商品のみ
 * アフィリエイト機能は独立して管理
 */

namespace URP\Core;

if (!defined('ABSPATH')) {
    exit;
}

class URP_Site_Mode {
    
    /**
     * サイトモードの定義
     */
    const MODE_HYBRID = 'hybrid';
    const MODE_SHOP_ONLY = 'shop_only';
    const MODE_PRODUCT_ONLY = 'product_only';
    
    /**
     * 初期化
     */
    public static function init() {
        add_filter('urp_post_types', [self::class, 'filter_post_types']);
        add_action('admin_notices', [self::class, 'show_mode_notice']);
    }
    
    /**
     * 現在のサイトモードを取得
     */
    public static function get_mode() {
        return get_option('urp_site_mode', self::MODE_HYBRID);
    }
    
    /**
     * サイトモードを設定
     */
    public static function set_mode($mode) {
        $valid_modes = [
            self::MODE_HYBRID,
            self::MODE_SHOP_ONLY,
            self::MODE_PRODUCT_ONLY
        ];
        
        if (in_array($mode, $valid_modes, true)) {
            update_option('urp_site_mode', $mode);
            
            // キャッシュクリア
            wp_cache_flush();
            
            // リライトルールを再生成
            flush_rewrite_rules();
            
            return true;
        }
        return false;
    }
    
    /**
     * モードに応じた投稿タイプを取得
     */
    public static function get_post_types() {
        $mode = self::get_mode();
        
        switch ($mode) {
            case self::MODE_SHOP_ONLY:
                return ['shop_review'];
                
            case self::MODE_PRODUCT_ONLY:
                return ['product_review'];
                
            case self::MODE_HYBRID:
            default:
                return ['shop_review', 'product_review'];
        }
    }
    
    /**
     * 投稿タイプをフィルター
     */
    public static function filter_post_types($post_types) {
        $allowed = self::get_post_types();
        return array_intersect($post_types, $allowed);
    }
    
    /**
     * モード通知を表示
     */
    public static function show_mode_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // URP関連ページでのみ表示
        if (isset($_GET['page']) && strpos($_GET['page'], 'urp-') === 0) {
            $mode = self::get_mode();
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>サイトモード:</strong> 
                    <?php
                    echo match($mode) {
                        self::MODE_SHOP_ONLY => '店舗レビュー専門',
                        self::MODE_PRODUCT_ONLY => '商品レビュー専門',
                        default => 'ハイブリッド（店舗＋商品）'
                    };
                    ?>
                    | <a href="<?php echo admin_url('admin.php?page=urp-settings'); ?>">変更</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * モードのラベルを取得
     */
    public static function get_mode_label($mode = null) {
        if ($mode === null) {
            $mode = self::get_mode();
        }
        
        return match($mode) {
            self::MODE_SHOP_ONLY => '店舗レビュー専門',
            self::MODE_PRODUCT_ONLY => '商品レビュー専門',
            self::MODE_HYBRID => 'ハイブリッド（店舗＋商品）',
            default => '未設定'
        };
    }
}