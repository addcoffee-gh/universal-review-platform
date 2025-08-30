<?php
/**
 * Trust Score - レビュアー信頼度システム（Static版）
 * 
 * 「どこにも負けない」理由の1つ
 * レビュアーの信頼度を多角的に評価
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 */

namespace URP\Core;

if (!defined('ABSPATH')) {
    exit;
}

class URP_Trust_Score {
    
    /**
     * 信頼度計算の要素と重み
     */
    private const FACTORS = [
        'review_count' => 0.2,        // レビュー数
        'review_quality' => 0.3,      // レビューの質（文字数、写真）
        'consistency' => 0.2,         // 評価の一貫性
        'helpful_votes' => 0.2,       // 役立った票
        'verified_visits' => 0.1,     // 実際の来店確認
    ];
    
    /**
     * 初期化
     */
    public static function init() {
        // 毎日スコア更新
        add_action('urp_daily_cron', [self::class, 'update_all_scores']);
        
        // レビュー投稿時にスコア更新
        add_action('urp_review_created', [self::class, 'update_user_score'], 10, 2);
        add_action('urp_review_updated', [self::class, 'update_user_score'], 10, 2);
        
        // プロフィールページにバッジ表示
        add_action('show_user_profile', [self::class, 'display_trust_badge']);
        add_action('edit_user_profile', [self::class, 'display_trust_badge']);
    }
    
    /**
     * ユーザーの信頼スコアを計算
     * @param int $user_id
     * @return float 0-100のスコア
     */
    public static function calculate($user_id) {
        $score = 0;
        
        // レビュー数スコア（最大20点）
        $review_count = self::get_review_count($user_id);
        $score += min($review_count / 50 * 20, 20); // 50件で満点
        
        // レビュー品質スコア（最大30点）
        $quality = self::calculate_review_quality($user_id);
        $score += $quality * 30;
        
        // 一貫性スコア（最大20点）
        $consistency = self::calculate_consistency($user_id);
        $score += $consistency * 20;
        
        // 役立った票スコア（最大20点）
        $helpful = self::calculate_helpful_score($user_id);
        $score += $helpful * 20;
        
        // 実来店確認（最大10点）
        $verified = self::get_verified_ratio($user_id);
        $score += $verified * 10;
        
        // フィルターフック（専門プラグインが調整可能）
        $score = apply_filters('urp_trust_score_calculated', $score, $user_id);
        
        return round($score, 1);
    }
    
    /**
     * 信頼度バッジを取得
     * @param float $score
     * @return array
     */
    public static function get_badge($score) {
        $badges = [
            90 => [
                'level' => 'master',
                'label' => 'レビューマスター',
                'color' => '#FFD700',
                'icon' => '👑',
                'perks' => ['優先表示', 'マスター限定機能']
            ],
            70 => [
                'level' => 'expert',
                'label' => 'エキスパート',
                'color' => '#C0C0C0',
                'icon' => '🏆',
                'perks' => ['エキスパートバッジ']
            ],
            50 => [
                'level' => 'advanced',
                'label' => 'アドバンス',
                'color' => '#CD7F32',
                'icon' => '🎖️',
                'perks' => ['信頼マーク表示']
            ],
            30 => [
                'level' => 'regular',
                'label' => 'レギュラー',
                'color' => '#4CAF50',
                'icon' => '⭐',
                'perks' => []
            ],
            0 => [
                'level' => 'beginner',
                'label' => 'ビギナー',
                'color' => '#9E9E9E',
                'icon' => '🔰',
                'perks' => []
            ]
        ];
        
        foreach ($badges as $threshold => $badge) {
            if ($score >= $threshold) {
                return $badge;
            }
        }
        
        return $badges[0];
    }
    
    /**
     * スコアを保存
     * @param int $user_id
     * @param float $score
     */
    public static function save_score($user_id, $score) {
        global $wpdb;
        $table = $wpdb->prefix . 'urp_reviewer_ranks';
        
        $badge = self::get_badge($score);
        
        $wpdb->replace(
            $table,
            [
                'user_id' => $user_id,
                'trust_score' => $score,
                'rank_level' => $badge['level'],
                'badges' => json_encode($badge),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%f', '%s', '%s', '%s']
        );
        
        // ユーザーメタにも保存（高速アクセス用）
        update_user_meta($user_id, 'urp_trust_score', $score);
        update_user_meta($user_id, 'urp_trust_badge', $badge);
        
        // スコア更新イベント
        do_action('urp_trust_score_updated', $user_id, $score, $badge);
    }
    
    /**
     * 全ユーザーのスコアを更新（Cronで実行）
     */
    public static function update_all_scores() {
        $users = get_users([
            'role__in' => ['subscriber', 'contributor', 'author', 'premium_reviewer'],
            'meta_key' => 'urp_trust_score',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ]);
        
        foreach ($users as $user) {
            $score = self::calculate($user->ID);
            self::save_score($user->ID, $score);
        }
        
        // ランキング更新
        self::update_rankings();
    }
    
    /**
     * 特定ユーザーのスコア更新
     */
    public static function update_user_score($review_id, $data = []) {
        $review = get_post($review_id);
        if ($review && $review->post_author) {
            $score = self::calculate($review->post_author);
            self::save_score($review->post_author, $score);
        }
    }
    
    /**
     * ランキング更新
     */
    private static function update_rankings() {
        global $wpdb;
        
        // トップレビュアーを取得
        $top_reviewers = $wpdb->get_results(
            "SELECT user_id, trust_score, rank_level 
             FROM {$wpdb->prefix}urp_reviewer_ranks 
             ORDER BY trust_score DESC 
             LIMIT 100"
        );
        
        // ランキングをキャッシュ
        set_transient('urp_top_reviewers', $top_reviewers, DAY_IN_SECONDS);
    }
    
    /**
     * レビュー数取得
     */
    private static function get_review_count($user_id) {
        // 両方の投稿タイプをカウント
        $shop_count = count_user_posts($user_id, 'shop_review');
        $product_count = count_user_posts($user_id, 'product_review');
        
        return $shop_count + $product_count;
    }
    
    /**
     * レビュー品質計算
     */
    private static function calculate_review_quality($user_id) {
        global $wpdb;
        
        // すべてのレビューを取得
        $reviews = get_posts([
            'author' => $user_id,
            'post_type' => ['shop_review', 'product_review'],
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        if (empty($reviews)) return 0;
        
        $total_quality = 0;
        
        foreach ($reviews as $review) {
            $quality = 0;
            
            // 文字数（500文字以上で高評価）
            $content_length = mb_strlen(strip_tags($review->post_content));
            if ($content_length >= 1000) {
                $quality += 0.3;
            } elseif ($content_length >= 500) {
                $quality += 0.2;
            } elseif ($content_length >= 200) {
                $quality += 0.1;
            }
            
            // 写真の有無と枚数
            $images = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}urp_review_images 
                 WHERE review_id = %d",
                $review->ID
            ));
            
            if ($images >= 3) {
                $quality += 0.3;
            } elseif ($images >= 1) {
                $quality += 0.2;
            }
            
            // 構造化されたレビュー（評価項目を埋めているか）
            $filled_fields = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}urp_rating_values 
                 WHERE post_id = %d",
                $review->ID
            ));
            
            if ($filled_fields >= 5) {
                $quality += 0.4;
            } elseif ($filled_fields >= 3) {
                $quality += 0.2;
            }
            
            $total_quality += $quality;
        }
        
        return min($total_quality / count($reviews), 1);
    }
    
    /**
     * 評価の一貫性計算
     */
    private static function calculate_consistency($user_id) {
        global $wpdb;
        
        // 総合評価を取得
        $ratings = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_value 
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_author = %d 
             AND p.post_type IN ('shop_review', 'product_review')
             AND p.post_status = 'publish'
             AND pm.meta_key = 'urp_overall_rating'",
            $user_id
        ));
        
        if (count($ratings) < 3) return 0.5; // データ不足時は中間値
        
        // 標準偏差を計算
        $ratings = array_map('floatval', $ratings);
        $mean = array_sum($ratings) / count($ratings);
        $variance = 0;
        
        foreach ($ratings as $rating) {
            $variance += pow($rating - $mean, 2);
        }
        
        $std_dev = sqrt($variance / count($ratings));
        
        // 標準偏差が小さいほど高スコア（最大2.5で0点）
        return max(0, 1 - ($std_dev / 2.5));
    }
    
    /**
     * 役立った票スコア計算
     */
    private static function calculate_helpful_score($user_id) {
        global $wpdb;
        
        // 投稿したレビューが獲得した「役立った」票
        $helpful = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}urp_review_votes v
             JOIN {$wpdb->posts} p ON v.review_id = p.ID
             WHERE p.post_author = %d 
             AND v.vote_type = 'helpful'",
            $user_id
        ));
        
        $review_count = self::get_review_count($user_id);
        
        if ($review_count == 0) return 0;
        
        // 1レビューあたり平均5票で満点
        return min($helpful / ($review_count * 5), 1);
    }
    
    /**
     * 実来店確認率
     */
    private static function get_verified_ratio($user_id) {
        global $wpdb;
        
        // GPS確認やレシート確認されたレビュー数
        $verified = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_author = %d 
             AND pm.meta_key = 'urp_verified_visit' 
             AND pm.meta_value = '1'",
            $user_id
        ));
        
        $total = self::get_review_count($user_id);
        
        if ($total == 0) return 0;
        
        return $verified / $total;
    }
    
    /**
     * プロフィールページにバッジ表示
     */
    public static function display_trust_badge($user) {
        $score = get_user_meta($user->ID, 'urp_trust_score', true);
        $badge = get_user_meta($user->ID, 'urp_trust_badge', true);
        
        if (!$score) {
            $score = self::calculate($user->ID);
            $badge = self::get_badge($score);
        }
        ?>
        <h2>レビュアー信頼度</h2>
        <table class="form-table">
            <tr>
                <th>信頼スコア</th>
                <td>
                    <div class="urp-trust-badge" style="background: <?php echo esc_attr($badge['color']); ?>">
                        <span class="icon"><?php echo esc_html($badge['icon']); ?></span>
                        <span class="label"><?php echo esc_html($badge['label']); ?></span>
                        <span class="score"><?php echo esc_html($score); ?>/100</span>
                    </div>
                    <?php if (!empty($badge['perks'])): ?>
                        <p>特典: <?php echo implode(', ', $badge['perks']); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * トップレビュアー取得
     */
    public static function get_top_reviewers($limit = 10) {
        $cached = get_transient('urp_top_reviewers');
        
        if ($cached) {
            return array_slice($cached, 0, $limit);
        }
        
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, r.trust_score, r.rank_level, r.badges
             FROM {$wpdb->prefix}urp_reviewer_ranks r
             JOIN {$wpdb->users} u ON r.user_id = u.ID
             ORDER BY r.trust_score DESC
             LIMIT %d",
            $limit
        ));
    }
}