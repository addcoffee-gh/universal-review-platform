<?php
/**
 * Universal Review Platform - Review Manager
 * 
 * レビューの作成、更新、削除、検索等の管理
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

namespace URP\Core;

use WP_Post;
use WP_Query;
use WP_Error;

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * レビュー管理クラス
 */
class URP_Review_Manager {
    
    /**
     * 投稿タイプ
     * @var string
     */
    private const POST_TYPE = 'platform_review';
    
    /**
     * レビュータイプ
     * @var string
     */
    private string $review_type;
    
    /**
     * キャッシュグループ
     * @var string
     */
    private const CACHE_GROUP = 'urp_reviews';
    
    /**
     * データベースインスタンス
     * @var ?URP_Database
     */
    private ?URP_Database $db = null;
    
    /**
     * バリデーションルール
     * @var array<string, mixed>
     */
    private array $validation_rules = [];
    
    /**
     * コンストラクタ
     * @param string $review_type
     */
    public function __construct(string $review_type = 'curry') {
        $this->review_type = $review_type;
        $this->init();
    }
    
    /**
     * 初期化
     * @return void
     */
    private function init(): void {
        // データベースインスタンス取得
        if (class_exists('URP\Core\URP_Database')) {
            $this->db = new URP_Database();
        }
        
        // バリデーションルール読み込み
        $this->load_validation_rules();
        
        // フック登録
        $this->register_hooks();
    }
    
    /**
     * レビューを作成
     * PHP 8.0: 名前付き引数、Union Types
     * @param array<string, mixed> $data
     * @return int|WP_Error
     */
    public function create_review(array $data): int|WP_Error {
        // バリデーション
        $validation = $this->validate_review_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // デフォルト値設定
        $defaults = [
            'post_type' => self::POST_TYPE,
            'post_status' => get_option('urp_require_approval') ? 'pending' : 'publish',
            'post_author' => get_current_user_id() ?: 0,
            'comment_status' => get_option('urp_enable_comments') ? 'open' : 'closed',
        ];
        
        $post_data = array_merge($defaults, [
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
        ]);
        
        // 投稿作成
        $review_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($review_id)) {
            return $review_id;
        }
        
        // メタデータ保存
        $this->save_review_meta($review_id, $data);
        
        // タクソノミー設定
        $this->set_review_taxonomies($review_id, $data);
        
        // 評価データ保存
        if (isset($data['rating'])) {
            $this->save_review_rating($review_id, $data['rating']);
        }
        
        // 画像保存
        if (!empty($data['images'])) {
            $this->save_review_images($review_id, $data['images']);
        }
        
        // カスタムフィールド保存
        if (!empty($data['custom_fields'])) {
            $this->save_custom_fields($review_id, $data['custom_fields']);
        }
        
        // キャッシュクリア
        $this->clear_cache();
        
        // フック実行
        do_action('urp_review_created', $review_id, $data);
        
        // アナリティクス記録
        $this->track_event('review_created', ['review_id' => $review_id]);
        
        return $review_id;
    }
    
    /**
     * レビューを更新
     * @param int $review_id
     * @param array<string, mixed> $data
     * @return bool|WP_Error
     */
    public function update_review(int $review_id, array $data): bool|WP_Error {
        // 存在確認
        if (!$this->review_exists($review_id)) {
            return new WP_Error('review_not_found', __('Review not found', 'universal-review'));
        }
        
        // 権限確認
        if (!$this->can_edit_review($review_id)) {
            return new WP_Error('permission_denied', __('Permission denied', 'universal-review'));
        }
        
        // バリデーション
        $validation = $this->validate_review_data($data, true);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // 投稿データ更新
        $post_data = ['ID' => $review_id];
        
        if (isset($data['title'])) {
            $post_data['post_title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['content'])) {
            $post_data['post_content'] = wp_kses_post($data['content']);
        }
        
        if (isset($data['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($data['excerpt']);
        }
        
        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // メタデータ更新
        $this->save_review_meta($review_id, $data);
        
        // キャッシュクリア
        wp_cache_delete($review_id, self::CACHE_GROUP);
        
        // フック実行
        do_action('urp_review_updated', $review_id, $data);
        
        return true;
    }
    
    /**
     * レビューを削除
     * PHP 8.0: match式
     * @param int $review_id
     * @param bool $force_delete
     * @return bool|WP_Error
     */
    public function delete_review(int $review_id, bool $force_delete = false): bool|WP_Error {
        // 存在確認
        if (!$this->review_exists($review_id)) {
            return new WP_Error('review_not_found', __('Review not found', 'universal-review'));
        }
        
        // 権限確認
        if (!$this->can_delete_review($review_id)) {
            return new WP_Error('permission_denied', __('Permission denied', 'universal-review'));
        }
        
        // 関連データ削除
        if ($force_delete) {
            $this->delete_review_data($review_id);
        }
        
        // 投稿削除
        $result = wp_delete_post($review_id, $force_delete);
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete review', 'universal-review'));
        }
        
        // キャッシュクリア
        wp_cache_delete($review_id, self::CACHE_GROUP);
        
        // フック実行
        do_action('urp_review_deleted', $review_id, $force_delete);
        
        return true;
    }
    
    /**
     * レビューを取得
     * @param int $review_id
     * @param bool $include_meta
     * @return array<string, mixed>|null
     */
    public function get_review(int $review_id, bool $include_meta = true): ?array {
        // キャッシュから取得
        $cache_key = $review_id . '_' . ($include_meta ? 'full' : 'basic');
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // 投稿取得
        $post = get_post($review_id);
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }
        
        // 基本データ
        $review = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'author_id' => $post->post_author,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'url' => get_permalink($post->ID),
        ];
        
        // メタデータ含める場合
        if ($include_meta) {
            $review['meta'] = $this->get_review_meta($review_id);
            $review['rating'] = $this->get_review_rating($review_id);
            $review['images'] = $this->get_review_images($review_id);
            $review['categories'] = $this->get_review_categories($review_id);
            $review['tags'] = $this->get_review_tags($review_id);
            $review['custom_fields'] = $this->get_custom_fields($review_id);
        }
        
        // 著者情報
        $review['author'] = $this->get_author_info($post->post_author);
        
        // キャッシュに保存
        wp_cache_set($cache_key, $review, self::CACHE_GROUP, HOUR_IN_SECONDS);
        
        return $review;
    }
    
    /**
     * レビューリストを取得
     * PHP 8.0: 名前付き引数
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function get_reviews(array $args = []): array {
        $defaults = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => get_option('urp_items_per_page', 10),
            'paged' => 1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $query_args = array_merge($defaults, $args);
        
        // レビュータイプでフィルタ
        if ($this->review_type) {
            $query_args['meta_query'] = $query_args['meta_query'] ?? [];
            $query_args['meta_query'][] = [
                'key' => 'urp_review_type',
                'value' => $this->review_type,
                'compare' => '='
            ];
        }
        
        $query = new WP_Query($query_args);
        
        $reviews = [];
        foreach ($query->posts as $post) {
            $reviews[] = $this->get_review($post->ID);
        }
        
        return [
            'reviews' => $reviews,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $query_args['paged'],
        ];
    }
    
    /**
     * レビューを検索
     * PHP 8.0: match式、Arrow functions
     * @param string $keyword
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function search_reviews(string $keyword = '', array $filters = []): array {
        $args = [
            's' => $keyword,
            'post_type' => self::POST_TYPE,
            'posts_per_page' => $filters['limit'] ?? 10,
            'paged' => $filters['page'] ?? 1,
        ];
        
        // カテゴリーフィルタ
        if (!empty($filters['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'review_category',
                    'field' => 'slug',
                    'terms' => $filters['category'],
                ]
            ];
        }
        
        // 地域フィルタ
        if (!empty($filters['region'])) {
            $args['tax_query'] = $args['tax_query'] ?? [];
            $args['tax_query'][] = [
                'taxonomy' => 'review_region',
                'field' => 'slug',
                'terms' => $filters['region'],
            ];
        }
        
        // 評価フィルタ
        if (!empty($filters['min_rating'])) {
            $args['meta_query'] = [
                [
                    'key' => 'urp_overall_rating',
                    'value' => $filters['min_rating'],
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ]
            ];
        }
        
        // 価格帯フィルタ
        if (!empty($filters['price_range'])) {
            $args['meta_query'] = $args['meta_query'] ?? [];
            $args['meta_query'][] = [
                'key' => 'urp_price_range',
                'value' => $filters['price_range'],
                'compare' => '='
            ];
        }
        
        // ソート処理（PHP 8.0: match式）
        $sort_by = $filters['sort_by'] ?? 'date';
        list($args['orderby'], $args['order']) = match($sort_by) {
            'rating' => ['meta_value_num', 'DESC'],
            'price_low' => ['meta_value_num', 'ASC'],
            'price_high' => ['meta_value_num', 'DESC'],
            'popular' => ['comment_count', 'DESC'],
            'title' => ['title', 'ASC'],
            default => ['date', 'DESC']
        };
        
        if (in_array($sort_by, ['rating', 'price_low', 'price_high'])) {
            $args['meta_key'] = match($sort_by) {
                'rating' => 'urp_overall_rating',
                'price_low', 'price_high' => 'urp_price',
                default => ''
            };
        }
        
        return $this->get_reviews($args);
    }
    
    /**
     * ランキングを取得
     * @param string $type
     * @param int $limit
     * @return array<array<string, mixed>>
     */
    public function get_rankings(string $type = 'rating', int $limit = 10): array {
        global $wpdb;
        
        // PHP 8.0: match式でクエリ生成
        $query = match($type) {
            'rating' => $wpdb->prepare(
                "SELECT p.ID, p.post_title, 
                        AVG(CAST(pm.meta_value AS DECIMAL(3,2))) as average_rating,
                        COUNT(pm.meta_id) as rating_count
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = %s 
                   AND p.post_status = 'publish'
                   AND pm.meta_key = 'urp_overall_rating'
                 GROUP BY p.ID
                 ORDER BY average_rating DESC, rating_count DESC
                 LIMIT %d",
                self::POST_TYPE,
                $limit
            ),
            
            'views' => $wpdb->prepare(
                "SELECT p.ID, p.post_title,
                        CAST(pm.meta_value AS UNSIGNED) as view_count
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = %s
                   AND p.post_status = 'publish'
                   AND pm.meta_key = 'urp_view_count'
                 ORDER BY view_count DESC
                 LIMIT %d",
                self::POST_TYPE,
                $limit
            ),
            
            'recent' => $wpdb->prepare(
                "SELECT ID, post_title, post_date
                 FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_status = 'publish'
                 ORDER BY post_date DESC
                 LIMIT %d",
                self::POST_TYPE,
                $limit
            ),
            
            default => ''
        };
        
        if (!$query) {
            return [];
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // 詳細情報を付加
        $rankings = [];
        foreach ($results as $index => $result) {
            $review = $this->get_review((int)$result['ID'], false);
            if ($review) {
                $review['rank'] = $index + 1;
                $review['ranking_score'] = $result['average_rating'] ?? $result['view_count'] ?? null;
                $rankings[] = $review;
            }
        }
        
        return $rankings;
    }
    
    /**
     * レビューメタデータ保存
     * @param int $review_id
     * @param array<string, mixed> $data
     * @return void
     */
    private function save_review_meta(int $review_id, array $data): void {
        // レビュータイプ
        update_post_meta($review_id, 'urp_review_type', $this->review_type);
        
        // 基本メタフィールド
        $meta_fields = [
            'price' => 'urp_price',
            'location' => 'urp_location',
            'address' => 'urp_address',
            'phone' => 'urp_phone',
            'website' => 'urp_website',
            'hours' => 'urp_hours',
        ];
        
        foreach ($meta_fields as $field => $meta_key) {
            if (isset($data[$field])) {
                update_post_meta($review_id, $meta_key, sanitize_text_field($data[$field]));
            }
        }
        
        // レビュータイプ別メタ（PHP 8.0: match式）
        $type_specific_meta = match($this->review_type) {
            'curry' => [
                'spiciness' => 'urp_spiciness',
                'curry_type' => 'urp_curry_type',
                'portion_size' => 'urp_portion_size',
            ],
            'ramen' => [
                'soup_type' => 'urp_soup_type',
                'noodle_hardness' => 'urp_noodle_hardness',
                'toppings' => 'urp_toppings',
            ],
            default => []
        };
        
        foreach ($type_specific_meta as $field => $meta_key) {
            if (isset($data[$field])) {
                update_post_meta($review_id, $meta_key, sanitize_text_field($data[$field]));
            }
        }
    }
    
    /**
     * レビュー評価保存
     * @param int $review_id
     * @param float|array<string, float> $rating
     * @return void
     */
    private function save_review_rating(int $review_id, float|array $rating): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'urp_review_ratings';
        
        // 単一評価の場合
        if (is_numeric($rating)) {
            $wpdb->replace(
                $table,
                [
                    'review_id' => $review_id,
                    'user_id' => get_current_user_id(),
                    'rating_type' => 'overall',
                    'rating_value' => $rating,
                ],
                ['%d', '%d', '%s', '%f']
            );
            
            update_post_meta($review_id, 'urp_overall_rating', $rating);
            return;
        }
        
        // 複数評価の場合
        if (is_array($rating)) {
            foreach ($rating as $type => $value) {
                $wpdb->replace(
                    $table,
                    [
                        'review_id' => $review_id,
                        'user_id' => get_current_user_id(),
                        'rating_type' => sanitize_key($type),
                        'rating_value' => floatval($value),
                    ],
                    ['%d', '%d', '%s', '%f']
                );
            }
            
            // 総合評価を計算
            $overall = array_sum($rating) / count($rating);
            update_post_meta($review_id, 'urp_overall_rating', $overall);
        }
    }
    
    /**
     * レビュー画像保存
     * @param int $review_id
     * @param array<mixed> $images
     * @return void
     */
    private function save_review_images(int $review_id, array $images): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'urp_review_images';
        
        foreach ($images as $index => $image) {
            // 画像アップロード処理
            if (isset($image['tmp_name'])) {
                $attachment_id = $this->upload_image($image);
                if ($attachment_id) {
                    $image_url = wp_get_attachment_url($attachment_id);
                    $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                }
            } else {
                $image_url = esc_url_raw($image['url'] ?? '');
                $thumb_url = esc_url_raw($image['thumbnail'] ?? '');
                $attachment_id = $image['attachment_id'] ?? null;
            }
            
            if ($image_url) {
                $wpdb->insert(
                    $table,
                    [
                        'review_id' => $review_id,
                        'attachment_id' => $attachment_id,
                        'image_url' => $image_url,
                        'thumbnail_url' => $thumb_url,
                        'caption' => sanitize_text_field($image['caption'] ?? ''),
                        'is_primary' => $index === 0 ? 1 : 0,
                        'sort_order' => $index,
                    ],
                    ['%d', '%d', '%s', '%s', '%s', '%d', '%d']
                );
            }
        }
    }
    
    /**
     * カスタムフィールド保存
     * @param int $review_id
     * @param array<string, mixed> $fields
     * @return void
     */
    private function save_custom_fields(int $review_id, array $fields): void {
        global $wpdb;
        
        $criteria_table = $wpdb->prefix . 'urp_review_criteria';
        $values_table = $wpdb->prefix . 'urp_review_values';
        
        foreach ($fields as $key => $value) {
            // 条件ID取得
            $criteria_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $criteria_table 
                 WHERE review_type = %s AND criteria_key = %s",
                $this->review_type,
                $key
            ));
            
            if ($criteria_id) {
                $wpdb->replace(
                    $values_table,
                    [
                        'review_id' => $review_id,
                        'criteria_id' => $criteria_id,
                        'value' => maybe_serialize($value),
                        'score' => is_numeric($value) ? floatval($value) : null,
                    ],
                    ['%d', '%d', '%s', '%f']
                );
            }
        }
    }
    
    /**
     * バリデーションルール読み込み
     * @return void
     */
    private function load_validation_rules(): void {
        $config_file = URP_PLUGIN_DIR . 'review-types/configurations/' . $this->review_type . '.json';
        
        if (file_exists($config_file)) {
            $config = json_decode(file_get_contents($config_file), true);
            $this->validation_rules = $config['validation'] ?? [];
        }
    }
    
    /**
     * レビューデータのバリデーション
     * @param array<string, mixed> $data
     * @param bool $is_update
     * @return true|WP_Error
     */
    private function validate_review_data(array $data, bool $is_update = false): true|WP_Error {
        $errors = [];
        
        // タイトル検証
        if (!$is_update && empty($data['title'])) {
            $errors[] = __('Title is required', 'universal-review');
        }
        
        // コンテンツ検証
        if (isset($data['content'])) {
            $word_count = str_word_count(strip_tags($data['content']));
            $min_words = get_option('urp_minimum_word_count', 50);
            $max_words = get_option('urp_maximum_word_count', 5000);
            
            if ($word_count < $min_words) {
                $errors[] = sprintf(__('Content must be at least %d words', 'universal-review'), $min_words);
            }
            
            if ($word_count > $max_words) {
                $errors[] = sprintf(__('Content must not exceed %d words', 'universal-review'), $max_words);
            }
        }
        
        // 評価検証
        if (isset($data['rating'])) {
            $rating = is_array($data['rating']) ? $data['rating'] : ['overall' => $data['rating']];
            
            foreach ($rating as $type => $value) {
                if (!is_numeric($value) || $value < 1 || $value > 5) {
                    $errors[] = sprintf(__('Invalid rating value for %s', 'universal-review'), $type);
                }
            }
        }
        
        // カスタムバリデーション
        if (!empty($this->validation_rules)) {
            foreach ($this->validation_rules as $field => $rules) {
                if (isset($data[$field])) {
                    $validation_result = $this->validate_field($data[$field], $rules);
                    if ($validation_result !== true) {
                        $errors[] = $validation_result;
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * フィールドバリデーション
     * @param mixed $value
     * @param array<string, mixed> $rules
     * @return true|string
     */
    private function validate_field(mixed $value, array $rules): true|string {
        // 必須チェック
        if (!empty($rules['required']) && empty($value)) {
            return $rules['message'] ?? __('This field is required', 'universal-review');
        }
        
        // 型チェック
        if (isset($rules['type'])) {
            $type_valid = match($rules['type']) {
                'email' => is_email($value),
                'url' => filter_var($value, FILTER_VALIDATE_URL),
                'number' => is_numeric($value),
                'integer' => filter_var($value, FILTER_VALIDATE_INT),
                default => true
            };
            
            if (!$type_valid) {
                return $rules['message'] ?? __('Invalid field type', 'universal-review');
            }
        }
        
        // 長さチェック
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            return sprintf(__('Minimum length is %d characters', 'universal-review'), $rules['min_length']);
        }
        
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            return sprintf(__('Maximum length is %d characters', 'universal-review'), $rules['max_length']);
        }
        
        return true;
    }
    
    /**
     * レビューが存在するか確認
     * @param int $review_id
     * @return bool
     */
    private function review_exists(int $review_id): bool {
        $post = get_post($review_id);
        return $post && $post->post_type === self::POST_TYPE;
    }
    
    /**
     * レビュー編集権限確認
     * @param int $review_id
     * @return bool
     */
    private function can_edit_review(int $review_id): bool {
        return current_user_can('edit_post', $review_id);
    }
    
    /**
     * レビュー削除権限確認
     * @param int $review_id
     * @return bool
     */
    private function can_delete_review(int $review_id): bool {
        return current_user_can('delete_post', $review_id);
    }
    
    /**
     * レビュー関連データ削除
     * @param int $review_id
     * @return void
     */
    private function delete_review_data(int $review_id): void {
        global $wpdb;
        
        // テーブル名
        $tables = [
            'urp_review_ratings',
            'urp_review_values',
            'urp_review_images',
            'urp_review_votes',
            'urp_review_reports',
        ];
        
        foreach ($tables as $table) {
            $wpdb->delete(
                $wpdb->prefix . $table,
                ['review_id' => $review_id],
                ['%d']
            );
        }
        
        // メタデータ削除
        $wpdb->delete(
            $wpdb->postmeta,
            ['post_id' => $review_id],
            ['%d']
        );
    }
    
    /**
     * キャッシュクリア
     * @return void
     */
    private function clear_cache(): void {
        wp_cache_delete_group(self::CACHE_GROUP);
        
        // ページキャッシュもクリア
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * イベントトラッキング
     * @param string $event
     * @param array<string, mixed> $data
     * @return void
     */
    private function track_event(string $event, array $data = []): void {
        if (!get_option('urp_enable_analytics', true)) {
            return;
        }
        
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'urp_analytics',
            [
                'event_type' => $event,
                'event_data' => json_encode($data),
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }
    
    /**
     * フック登録
     * @return void
     */
    private function register_hooks(): void {
        // ビューカウント
        add_action('wp_head', [$this, 'count_review_views']);
        
        // キャッシュクリア
        add_action('save_post_' . self::POST_TYPE, [$this, 'clear_cache']);
        add_action('delete_post', [$this, 'clear_cache']);
        
        // メタボックス
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_box_data']);
    }
    
    /**
     * その他のヘルパーメソッド
     */
    
    private function get_review_meta(int $review_id): array {
        return get_post_meta($review_id);
    }
    
    private function get_review_rating(int $review_id): ?float {
        return get_post_meta($review_id, 'urp_overall_rating', true) ?: null;
    }
    
    private function get_review_images(int $review_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}urp_review_images 
             WHERE review_id = %d ORDER BY sort_order ASC",
            $review_id
        ), ARRAY_A);
    }
    
    private function get_review_categories(int $review_id): array {
        return wp_get_post_terms($review_id, 'review_category', ['fields' => 'names']);
    }
    
    private function get_review_tags(int $review_id): array {
        return wp_get_post_terms($review_id, 'review_tag', ['fields' => 'names']);
    }
    
    private function get_custom_fields(int $review_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.criteria_key, v.value, v.score 
             FROM {$wpdb->prefix}urp_review_values v
             JOIN {$wpdb->prefix}urp_review_criteria c ON v.criteria_id = c.id
             WHERE v.review_id = %d",
            $review_id
        ), ARRAY_A);
    }
    
    private function get_author_info(int $author_id): array {
        $user = get_userdata($author_id);
        
        if (!$user) {
            return [];
        }
        
        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'avatar' => get_avatar_url($user->ID),
            'url' => get_author_posts_url($user->ID),
        ];
    }
    
    private function set_review_taxonomies(int $review_id, array $data): void {
        if (!empty($data['categories'])) {
            wp_set_post_terms($review_id, $data['categories'], 'review_category');
        }
        
        if (!empty($data['region'])) {
            wp_set_post_terms($review_id, $data['region'], 'review_region');
        }
        
        if (!empty($data['tags'])) {
            wp_set_post_terms($review_id, $data['tags'], 'review_tag');
        }
    }
    
    private function upload_image(array $file): ?int {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $upload = wp_handle_upload($file, ['test_form' => false]);
        
        if (isset($upload['error'])) {
            return null;
        }
        
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (!is_wp_error($attachment_id)) {
            $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $metadata);
            return $attachment_id;
        }
        
        return null;
    }
}