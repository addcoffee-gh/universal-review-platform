<?php
/**
 * Affiliate Manager - アフィリエイト機能管理
 * 
 * Amazon、楽天、Yahoo等のアフィリエイトを統合管理
 * 全サイトモードで利用可能
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 */

namespace URP\Core;

if (!defined('ABSPATH')) {
    exit;
}

class URP_Affiliate_Manager {
    
    /**
     * 対応アフィリエイトプラットフォーム
     */
    const PLATFORMS = [
        'amazon' => [
            'name' => 'Amazon',
            'api_endpoint' => 'https://webservices.amazon.co.jp/paapi5/',
            'link_format' => 'https://www.amazon.co.jp/dp/%s?tag=%s'
        ],
        'rakuten' => [
            'name' => '楽天市場',
            'api_endpoint' => 'https://app.rakuten.co.jp/services/api/',
            'link_format' => 'https://hb.afl.rakuten.co.jp/hgc/%s/?pc=%s'
        ],
        'yahoo' => [
            'name' => 'Yahoo!ショッピング',
            'api_endpoint' => 'https://shopping.yahooapis.jp/',
            'link_format' => 'https://ck.jp.ap.valuecommerce.com/servlet/referral?sid=%s&pid=%s&vc_url=%s'
        ]
    ];
    
    /**
     * アフィリエイト機能が設定されているか確認
     * @return bool
     */
    public static function is_configured() {
        $amazon_tag = get_option('urp_amazon_partner_tag');
        $rakuten_id = get_option('urp_rakuten_affiliate_id');
        $yahoo_sid = get_option('urp_yahoo_sid');
        
        return !empty($amazon_tag) || !empty($rakuten_id) || !empty($yahoo_sid);
    }
    
    /**
     * 特定プラットフォームが設定されているか確認
     * @param string $platform amazon|rakuten|yahoo
     * @return bool
     */
    public static function is_platform_configured($platform) {
        switch ($platform) {
            case 'amazon':
                $access_key = get_option('urp_amazon_access_key');
                $secret_key = get_option('urp_amazon_secret_key');
                $partner_tag = get_option('urp_amazon_partner_tag');
                return !empty($access_key) && !empty($secret_key) && !empty($partner_tag);
                
            case 'rakuten':
                $app_id = get_option('urp_rakuten_app_id');
                return !empty($app_id);
                
            case 'yahoo':
                $sid = get_option('urp_yahoo_sid');
                $pid = get_option('urp_yahoo_pid');
                return !empty($sid) && !empty($pid);
                
            default:
                return false;
        }
    }
    
    /**
     * 商品情報を自動取得（Amazon PA-API v5）
     */
    public static function fetch_amazon_product($asin) {
        $access_key = get_option('urp_amazon_access_key');
        $secret_key = get_option('urp_amazon_secret_key');
        $partner_tag = get_option('urp_amazon_partner_tag');
        
        if (!$access_key || !$secret_key || !$partner_tag) {
            return new \WP_Error('missing_credentials', 'Amazon API認証情報が設定されていません');
        }
        
        // PA-API v5リクエスト構築
        $payload = [
            'ItemIds' => [$asin],
            'Resources' => [
                'ItemInfo.Title',
                'ItemInfo.Features',
                'ItemInfo.ManufactureInfo',
                'Offers.Listings.Price',
                'Images.Primary.Large',
                'BrowseNodeInfo.BrowseNodes'
            ],
            'PartnerTag' => $partner_tag,
            'PartnerType' => 'Associates',
            'Marketplace' => 'www.amazon.co.jp'
        ];
        
        // APIリクエスト（簡略化版）
        $response = self::call_amazon_api('GetItems', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // 商品データ整形
        $item = $response['ItemsResult']['Items'][0] ?? null;
        
        if (!$item) {
            return new \WP_Error('product_not_found', '商品が見つかりません');
        }
        
        return [
            'asin' => $asin,
            'title' => $item['ItemInfo']['Title']['DisplayValue'] ?? '',
            'price' => $item['Offers']['Listings'][0]['Price']['Amount'] ?? 0,
            'currency' => $item['Offers']['Listings'][0]['Price']['Currency'] ?? 'JPY',
            'image' => $item['Images']['Primary']['Large']['URL'] ?? '',
            'features' => $item['ItemInfo']['Features']['DisplayValues'] ?? [],
            'manufacturer' => $item['ItemInfo']['ManufactureInfo']['DisplayValue'] ?? '',
            'url' => self::generate_affiliate_link('amazon', $asin),
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * 楽天商品情報取得
     */
    public static function fetch_rakuten_product($item_code) {
        $app_id = get_option('urp_rakuten_app_id');
        $affiliate_id = get_option('urp_rakuten_affiliate_id');
        
        if (!$app_id) {
            return new \WP_Error('missing_credentials', '楽天API認証情報が設定されていません');
        }
        
        $endpoint = 'https://app.rakuten.co.jp/services/api/IchibaItem/Search/20170706';
        
        $params = [
            'applicationId' => $app_id,
            'itemCode' => $item_code,
            'affiliateId' => $affiliate_id
        ];
        
        $response = wp_remote_get(add_query_arg($params, $endpoint));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $item = $data['Items'][0]['Item'] ?? null;
        
        if (!$item) {
            return new \WP_Error('product_not_found', '商品が見つかりません');
        }
        
        return [
            'item_code' => $item_code,
            'title' => $item['itemName'],
            'price' => $item['itemPrice'],
            'image' => $item['mediumImageUrls'][0]['imageUrl'] ?? '',
            'shop_name' => $item['shopName'],
            'description' => $item['itemCaption'],
            'url' => $item['affiliateUrl'] ?? $item['itemUrl'],
            'review_average' => $item['reviewAverage'],
            'review_count' => $item['reviewCount'],
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * アフィリエイトリンク生成
     */
    public static function generate_affiliate_link($platform, $product_id, $additional_params = []) {
        switch ($platform) {
            case 'amazon':
                $tag = get_option('urp_amazon_partner_tag');
                return sprintf(self::PLATFORMS['amazon']['link_format'], $product_id, $tag);
                
            case 'rakuten':
                $affiliate_id = get_option('urp_rakuten_affiliate_id');
                return sprintf(self::PLATFORMS['rakuten']['link_format'], $affiliate_id, $product_id);
                
            case 'yahoo':
                $sid = get_option('urp_yahoo_sid');
                $pid = get_option('urp_yahoo_pid');
                $url = urlencode($additional_params['url'] ?? '');
                return sprintf(self::PLATFORMS['yahoo']['link_format'], $sid, $pid, $url);
                
            default:
                return '';
        }
    }
    
    /**
     * アフィリエイトリンクを出力（テンプレート用）
     * @param int $post_id
     * @param string $class CSSクラス
     * @return string
     */
    public static function get_affiliate_button($post_id, $class = 'urp-affiliate-button') {
        if (!self::is_configured()) {
            return '';
        }
        
        $html = '<div class="urp-affiliate-buttons">';
        
        // Amazon
        $asin = get_post_meta($post_id, '_asin', true);
        if ($asin && self::is_platform_configured('amazon')) {
            $url = self::generate_affiliate_link('amazon', $asin);
            $html .= sprintf(
                '<a href="%s" class="%s amazon" target="_blank" rel="nofollow">Amazonで見る</a>',
                esc_url($url),
                esc_attr($class)
            );
        }
        
        // 楽天
        $rakuten_code = get_post_meta($post_id, '_rakuten_item_code', true);
        if ($rakuten_code && self::is_platform_configured('rakuten')) {
            $url = self::generate_affiliate_link('rakuten', $rakuten_code);
            $html .= sprintf(
                '<a href="%s" class="%s rakuten" target="_blank" rel="nofollow">楽天で見る</a>',
                esc_url($url),
                esc_attr($class)
            );
        }
        
        // Yahoo
        $yahoo_url = get_post_meta($post_id, '_yahoo_url', true);
        if ($yahoo_url && self::is_platform_configured('yahoo')) {
            $url = self::generate_affiliate_link('yahoo', '', ['url' => $yahoo_url]);
            $html .= sprintf(
                '<a href="%s" class="%s yahoo" target="_blank" rel="nofollow">Yahoo!で見る</a>',
                esc_url($url),
                esc_attr($class)
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * クリックトラッキング
     */
    public static function track_click($product_id, $platform, $post_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'urp_affiliate_clicks';
        
        // クリック記録
        $data = [
            'product_id' => $product_id,
            'platform' => $platform,
            'post_id' => $post_id,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'clicked_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table, $data);
        
        // 統計更新
        self::update_click_stats($product_id, $platform);
        
        return $wpdb->insert_id;
    }
    
    /**
     * クリック統計更新
     */
    private static function update_click_stats($product_id, $platform) {
        // 日次統計
        $daily_key = 'urp_clicks_' . date('Y-m-d') . '_' . $platform;
        $daily = get_option($daily_key, []);
        $daily[$product_id] = ($daily[$product_id] ?? 0) + 1;
        update_option($daily_key, $daily);
        
        // 月次統計
        $monthly_key = 'urp_clicks_' . date('Y-m') . '_' . $platform;
        $monthly = get_option($monthly_key, []);
        $monthly[$product_id] = ($monthly[$product_id] ?? 0) + 1;
        update_option($monthly_key, $monthly);
    }
    
    /**
     * 価格自動更新（Cronジョブ）
     */
    public static function update_all_prices() {
        $products = get_posts([
            'post_type' => 'product_review',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_asin',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => '_rakuten_item_code',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        foreach ($products as $product) {
            self::update_product_price($product->ID);
        }
    }
    
    /**
     * 個別商品の価格更新
     */
    public static function update_product_price($post_id) {
        $asin = get_post_meta($post_id, '_asin', true);
        $rakuten_code = get_post_meta($post_id, '_rakuten_item_code', true);
        
        $updated = false;
        
        // Amazon価格更新
        if ($asin) {
            $amazon_data = self::fetch_amazon_product($asin);
            if (!is_wp_error($amazon_data)) {
                update_post_meta($post_id, '_amazon_price', $amazon_data['price']);
                update_post_meta($post_id, '_amazon_last_updated', $amazon_data['last_updated']);
                $updated = true;
                
                // 価格履歴保存
                self::save_price_history($post_id, 'amazon', $amazon_data['price']);
            }
        }
        
        // 楽天価格更新
        if ($rakuten_code) {
            $rakuten_data = self::fetch_rakuten_product($rakuten_code);
            if (!is_wp_error($rakuten_data)) {
                update_post_meta($post_id, '_rakuten_price', $rakuten_data['price']);
                update_post_meta($post_id, '_rakuten_last_updated', $rakuten_data['last_updated']);
                $updated = true;
                
                // 価格履歴保存
                self::save_price_history($post_id, 'rakuten', $rakuten_data['price']);
            }
        }
        
        if ($updated) {
            // 最安値を更新
            self::update_best_price($post_id);
        }
        
        return $updated;
    }
    
    /**
     * 価格履歴保存
     */
    private static function save_price_history($post_id, $platform, $price) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'urp_price_history';
        
        // テーブルが存在しない場合はスキップ
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }
        
        $wpdb->insert($table, [
            'post_id' => $post_id,
            'platform' => $platform,
            'price' => $price,
            'recorded_at' => current_time('mysql')
        ]);
        
        // 古いデータを削除（1年以上前）
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND post_id = %d",
            $post_id
        ));
    }
    
    /**
     * 最安値更新
     */
    private static function update_best_price($post_id) {
        $prices = [];
        
        $amazon_price = get_post_meta($post_id, '_amazon_price', true);
        if ($amazon_price) {
            $prices['amazon'] = $amazon_price;
        }
        
        $rakuten_price = get_post_meta($post_id, '_rakuten_price', true);
        if ($rakuten_price) {
            $prices['rakuten'] = $rakuten_price;
        }
        
        if (!empty($prices)) {
            $min_price = min($prices);
            $min_platform = array_search($min_price, $prices);
            
            update_post_meta($post_id, '_best_price', $min_price);
            update_post_meta($post_id, '_best_price_platform', $min_platform);
        }
    }
    
    /**
     * コンバージョン率計算
     */
    public static function get_conversion_rate($platform = null, $period = 'month') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'urp_affiliate_clicks';
        
        // 期間設定
        $date_condition = match($period) {
            'day' => "DATE(clicked_at) = CURDATE()",
            'week' => "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "1=1"
        };
        
        $platform_condition = $platform ? $wpdb->prepare(" AND platform = %s", $platform) : "";
        
        // クリック数取得
        $clicks = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE $date_condition $platform_condition"
        );
        
        // コンバージョン数（外部から取得する必要あり）
        $conversions = self::get_conversions($platform, $period);
        
        if ($clicks == 0) {
            return 0;
        }
        
        return round(($conversions / $clicks) * 100, 2);
    }
    
    /**
     * 収益レポート生成
     */
    public static function generate_revenue_report($period = 'month') {
        $report = [
            'period' => $period,
            'platforms' => [],
            'total_clicks' => 0,
            'total_conversions' => 0,
            'total_revenue' => 0,
            'top_products' => []
        ];
        
        foreach (self::PLATFORMS as $platform => $config) {
            $clicks = self::get_platform_clicks($platform, $period);
            $conversions = self::get_conversions($platform, $period);
            $revenue = self::get_revenue($platform, $period);
            
            $report['platforms'][$platform] = [
                'name' => $config['name'],
                'clicks' => $clicks,
                'conversions' => $conversions,
                'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
                'revenue' => $revenue
            ];
            
            $report['total_clicks'] += $clicks;
            $report['total_conversions'] += $conversions;
            $report['total_revenue'] += $revenue;
        }
        
        // トップ商品取得
        $report['top_products'] = self::get_top_products($period, 10);
        
        return $report;
    }
    
    /**
     * プラットフォーム別クリック数取得
     */
    private static function get_platform_clicks($platform, $period) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'urp_affiliate_clicks';
        
        $date_condition = match($period) {
            'day' => "DATE(clicked_at) = CURDATE()",
            'week' => "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            default => "1=1"
        };
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE platform = %s AND $date_condition",
            $platform
        ));
    }
    
    /**
     * コンバージョン数取得（ダミー実装）
     */
    private static function get_conversions($platform, $period) {
        // 実際には各アフィリエイトプログラムのAPIから取得
        // ここではダミー実装
        return rand(10, 100);
    }
    
    /**
     * 収益取得（ダミー実装）
     */
    private static function get_revenue($platform, $period) {
        // 実際には各アフィリエイトプログラムのAPIから取得
        // ここではダミー実装
        return rand(10000, 100000);
    }
    
    /**
     * トップ商品取得
     */
    private static function get_top_products($period, $limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'urp_affiliate_clicks';
        
        $date_condition = match($period) {
            'day' => "DATE(clicked_at) = CURDATE()",
            'week' => "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            default => "1=1"
        };
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, platform, COUNT(*) as click_count 
             FROM $table 
             WHERE $date_condition 
             GROUP BY product_id, platform 
             ORDER BY click_count DESC 
             LIMIT %d",
            $limit
        ));
        
        $products = [];
        foreach ($results as $row) {
            $post = get_post($row->product_id);
            if ($post) {
                $products[] = [
                    'id' => $row->product_id,
                    'title' => $post->post_title,
                    'platform' => $row->platform,
                    'clicks' => $row->click_count,
                    'url' => get_permalink($row->product_id)
                ];
            }
        }
        
        return $products;
    }
    
    /**
     * Amazon API呼び出し（簡略化版）
     */
    private static function call_amazon_api($operation, $payload) {
        // 実際のPA-API v5実装には署名等が必要
        // ここでは概要のみ
        
        $endpoint = 'https://webservices.amazon.co.jp/paapi5/' . strtolower($operation);
        
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $operation
            ],
            'body' => json_encode($payload)
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}