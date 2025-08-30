<?php
/**
 * Universal Review Platform - API Router
 * 
 * REST APIのルーティングとエンドポイント管理
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

namespace URP\Core;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APIルータークラス
 */
class URP_API_Router {
    
    /**
     * API名前空間
     * @var string
     */
    private const NAMESPACE = 'urp/v1';
    
    /**
     * APIバージョン
     * @var string
     */
    private const VERSION = '1.0';
    
    /**
     * レート制限設定
     * @var array<string, int>
     */
    private array $rate_limits = [
        'default' => 100,      // 1時間あたり
        'search' => 30,        // 1分あたり
        'create' => 10,        // 1時間あたり
        'update' => 20,        // 1時間あたり
        'delete' => 5,         // 1時間あたり
    ];
    
    /**
     * エンドポイントキャッシュ時間
     * @var array<string, int>
     */
    private array $cache_times = [
        'reviews' => 300,      // 5分
        'review' => 600,       // 10分
        'rankings' => 3600,    // 1時間
        'statistics' => 3600,  // 1時間
        'reviewers' => 1800,   // 30分
    ];
    
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
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 10, 2);
        add_filter('rest_authentication_errors', [$this, 'check_authentication']);
    }
    
    /**
     * ルート登録
     * @return void
     */
    public function register_routes(): void {
        // レビューエンドポイント
        $this->register_review_routes();
        
        // レビュアーエンドポイント
        $this->register_reviewer_routes();
        
        // ランキングエンドポイント
        $this->register_ranking_routes();
        
        // 検索エンドポイント
        $this->register_search_routes();
        
        // 統計エンドポイント
        $this->register_statistics_routes();
        
        // カテゴリエンドポイント
        $this->register_category_routes();
        
        // 設定エンドポイント
        $this->register_settings_routes();
        
        // 画像アップロードエンドポイント
        $this->register_upload_routes();
    }
    
    /**
     * レビューエンドポイント登録
     * @return void
     */
    private function register_review_routes(): void {
        // レビューコレクション
        register_rest_route(self::NAMESPACE, '/reviews', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_reviews'],
                'permission_callback' => '__return_true',
                'args' => $this->get_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_review'],
                'permission_callback' => [$this, 'create_review_permission'],
                'args' => $this->get_review_params(),
            ],
        ]);
        
        // 単一レビュー
        register_rest_route(self::NAMESPACE, '/reviews/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_review'],
                'permission_callback' => '__return_true',
                'args' => [
                    'id' => [
                        'validate_callback' => fn($param) => is_numeric($param),
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_review'],
                'permission_callback' => [$this, 'update_review_permission'],
                'args' => $this->get_review_params(),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_review'],
                'permission_callback' => [$this, 'delete_review_permission'],
            ],
        ]);
        
        // レビュー評価
        register_rest_route(self::NAMESPACE, '/reviews/(?P<id>[\d]+)/rating', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'add_rating'],
            'permission_callback' => [$this, 'rating_permission'],
            'args' => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param),
                    'sanitize_callback' => 'absint',
                ],
                'rating' => [
                    'required' => true,
                    'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 5,
                    'sanitize_callback' => fn($param) => round(floatval($param), 1),
                ],
            ],
        ]);
        
        // レビュー投票
        register_rest_route(self::NAMESPACE, '/reviews/(?P<id>[\d]+)/vote', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'vote_review'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param),
                    'sanitize_callback' => 'absint',
                ],
                'type' => [
                    'required' => true,
                    'validate_callback' => fn($param) => in_array($param, ['helpful', 'not_helpful'], true),
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }
    
    /**
     * レビュアーエンドポイント登録
     * @return void
     */
    private function register_reviewer_routes(): void {
        // レビュアーリスト
        register_rest_route(self::NAMESPACE, '/reviewers', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_reviewers'],
            'permission_callback' => '__return_true',
            'args' => [
                'orderby' => [
                    'default' => 'review_count',
                    'validate_callback' => fn($param) => in_array($param, ['review_count', 'rating', 'trust_score'], true),
                ],
                'order' => [
                    'default' => 'DESC',
                    'validate_callback' => fn($param) => in_array($param, ['ASC', 'DESC'], true),
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 100,
                ],
            ],
        ]);
        
        // 単一レビュアー
        register_rest_route(self::NAMESPACE, '/reviewers/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_reviewer'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        // レビュアーのレビュー
        register_rest_route(self::NAMESPACE, '/reviewers/(?P<id>[\d]+)/reviews', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_reviewer_reviews'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param),
                    'sanitize_callback' => 'absint',
                ],
            ] + $this->get_collection_params(),
        ]);
    }
    
    /**
     * ランキングエンドポイント登録
     * @return void
     */
    private function register_ranking_routes(): void {
        register_rest_route(self::NAMESPACE, '/rankings', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_rankings'],
            'permission_callback' => '__return_true',
            'args' => [
                'type' => [
                    'default' => 'rating',
                    'validate_callback' => fn($param) => in_array($param, ['rating', 'views', 'recent', 'popular'], true),
                    'sanitize_callback' => 'sanitize_key',
                ],
                'period' => [
                    'default' => 'all',
                    'validate_callback' => fn($param) => in_array($param, ['all', 'year', 'month', 'week', 'day'], true),
                    'sanitize_callback' => 'sanitize_key',
                ],
                'limit' => [
                    'default' => 10,
                    'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 50,
                    'sanitize_callback' => 'absint',
                ],
                'category' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }
    
    /**
     * 検索エンドポイント登録
     * @return void
     */
    private function register_search_routes(): void {
        register_rest_route(self::NAMESPACE, '/search', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'search_reviews'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'validate_callback' => fn($param) => strlen($param) >= 2,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type' => [
                    'default' => 'all',
                    'validate_callback' => fn($param) => in_array($param, ['all', 'title', 'content', 'author'], true),
                    'sanitize_callback' => 'sanitize_key',
                ],
            ] + $this->get_collection_params(),
        ]);
        
        // オートコンプリート
        register_rest_route(self::NAMESPACE, '/search/autocomplete', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'autocomplete'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'validate_callback' => fn($param) => strlen($param) >= 1,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'default' => 5,
                    'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 10,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }
    
    /**
     * 統計エンドポイント登録
     * @return void
     */
    private function register_statistics_routes(): void {
        register_rest_route(self::NAMESPACE, '/statistics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_statistics'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/statistics/overview', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_overview'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/statistics/trends', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_trends'],
            'permission_callback' => [$this, 'view_analytics_permission'],
            'args' => [
                'period' => [
                    'default' => 'month',
                    'validate_callback' => fn($param) => in_array($param, ['week', 'month', 'year'], true),
                ],
                'metric' => [
                    'default' => 'reviews',
                    'validate_callback' => fn($param) => in_array($param, ['reviews', 'users', 'ratings', 'views'], true),
                ],
            ],
        ]);
    }
    
    /**
     * カテゴリエンドポイント登録
     * @return void
     */
    private function register_category_routes(): void {
        register_rest_route(self::NAMESPACE, '/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_categories'],
            'permission_callback' => '__return_true',
            'args' => [
                'hide_empty' => [
                    'default' => false,
                    'validate_callback' => fn($param) => is_bool($param),
                ],
                'parent' => [
                    'default' => 0,
                    'validate_callback' => fn($param) => is_numeric($param),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/categories/(?P<id>[\d]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_category'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }
    
    /**
     * 設定エンドポイント登録
     * @return void
     */
    private function register_settings_routes(): void {
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'manage_settings_permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'manage_settings_permission'],
                'args' => $this->get_settings_params(),
            ],
        ]);
    }
    
    /**
     * アップロードエンドポイント登録
     * @return void
     */
    private function register_upload_routes(): void {
        register_rest_route(self::NAMESPACE, '/upload/image', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'upload_image'],
            'permission_callback' => [$this, 'upload_permission'],
        ]);
    }
    
    /**
     * レビュー一覧取得
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_reviews(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // レート制限チェック
        if (!$this->check_rate_limit('search')) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded', 'universal-review'), ['status' => 429]);
        }
        
        // キャッシュチェック
        $cache_key = 'api_reviews_' . md5(serialize($request->get_params()));
        $cached = wp_cache_get($cache_key, URP_CACHE_GROUP);
        
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }
        
        $params = $request->get_params();
        
        $args = [
            'post_type' => URP_POST_TYPE,
            'posts_per_page' => $params['per_page'] ?? 10,
            'paged' => $params['page'] ?? 1,
            'orderby' => $params['orderby'] ?? 'date',
            'order' => $params['order'] ?? 'DESC',
        ];
        
        // カテゴリフィルター
        if (!empty($params['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => URP_CATEGORY_TAXONOMY,
                    'field' => 'slug',
                    'terms' => $params['category'],
                ]
            ];
        }
        
        // 評価フィルター
        if (!empty($params['min_rating'])) {
            $args['meta_query'] = [
                [
                    'key' => URP_META_OVERALL_RATING,
                    'value' => $params['min_rating'],
                    'compare' => '>=',
                    'type' => 'DECIMAL',
                ]
            ];
        }
        
        $query = new \WP_Query($args);
        
        $reviews = [];
        foreach ($query->posts as $post) {
            $reviews[] = $this->prepare_review_response($post);
        }
        
        $response = [
            'reviews' => $reviews,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $args['paged'],
        ];
        
        // キャッシュ保存
        wp_cache_set($cache_key, $response, URP_CACHE_GROUP, $this->cache_times['reviews']);
        
        return rest_ensure_response($response);
    }
    
    /**
     * 単一レビュー取得
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = $request->get_param('id');
        $post = get_post($id);
        
        if (!$post || $post->post_type !== URP_POST_TYPE) {
            return new WP_Error('not_found', __('Review not found', 'universal-review'), ['status' => 404]);
        }
        
        // ビューカウント増加
        $this->increment_view_count($id);
        
        $response = $this->prepare_review_response($post, true);
        
        return rest_ensure_response($response);
    }
    
    /**
     * レビュー作成
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // レート制限チェック
        if (!$this->check_rate_limit('create')) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded', 'universal-review'), ['status' => 429]);
        }
        
        $params = $request->get_params();
        
        $review_data = [
            'title' => $params['title'],
            'content' => $params['content'],
            'rating' => $params['rating'] ?? 0,
            'category' => $params['category'] ?? '',
            'meta' => $params['meta'] ?? [],
        ];
        
        $review_manager = new URP_Review_Manager();
        $review_id = $review_manager->create_review($review_data);
        
        if (is_wp_error($review_id)) {
            return $review_id;
        }
        
        $post = get_post($review_id);
        $response = $this->prepare_review_response($post);
        
        return rest_ensure_response([
            'success' => true,
            'review' => $response,
            'message' => __('Review created successfully', 'universal-review'),
        ]);
    }
    
    /**
     * レビュー更新
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // レート制限チェック
        if (!$this->check_rate_limit('update')) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded', 'universal-review'), ['status' => 429]);
        }
        
        $id = $request->get_param('id');
        $params = $request->get_params();
        
        $review_manager = new URP_Review_Manager();
        $result = $review_manager->update_review($id, $params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $post = get_post($id);
        $response = $this->prepare_review_response($post);
        
        return rest_ensure_response([
            'success' => true,
            'review' => $response,
            'message' => __('Review updated successfully', 'universal-review'),
        ]);
    }
    
    /**
     * レビュー削除
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // レート制限チェック
        if (!$this->check_rate_limit('delete')) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded', 'universal-review'), ['status' => 429]);
        }
        
        $id = $request->get_param('id');
        
        $review_manager = new URP_Review_Manager();
        $result = $review_manager->delete_review($id, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Review deleted successfully', 'universal-review'),
        ]);
    }
    
    /**
     * レビューレスポンス準備
     * @param \WP_Post $post
     * @param bool $full
     * @return array<string, mixed>
     */
    private function prepare_review_response(\WP_Post $post, bool $full = false): array {
        $response = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'excerpt' => get_the_excerpt($post),
            'url' => get_permalink($post),
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'status' => $post->post_status,
            'author' => [
                'id' => $post->post_author,
                'name' => get_the_author_meta('display_name', $post->post_author),
                'avatar' => get_avatar_url($post->post_author),
            ],
            'rating' => get_post_meta($post->ID, URP_META_OVERALL_RATING, true),
            'featured_image' => get_the_post_thumbnail_url($post, 'medium'),
        ];
        
        if ($full) {
            $response['content'] = $post->post_content;
            $response['categories'] = wp_get_post_terms($post->ID, URP_CATEGORY_TAXONOMY, ['fields' => 'names']);
            $response['meta'] = get_post_meta($post->ID);
            $response['comments_count'] = get_comments_number($post);
            $response['view_count'] = get_post_meta($post->ID, URP_META_VIEW_COUNT, true) ?: 0;
        }
        
        return $response;
    }
    
    /**
     * コレクションパラメータ取得
     * @return array<string, array<string, mixed>>
     */
    private function get_collection_params(): array {
        return [
            'page' => [
                'default' => 1,
                'validate_callback' => fn($param) => is_numeric($param) && $param >= 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => 10,
                'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 100,
                'sanitize_callback' => 'absint',
            ],
            'orderby' => [
                'default' => 'date',
                'validate_callback' => fn($param) => in_array($param, ['date', 'title', 'rating', 'popular'], true),
                'sanitize_callback' => 'sanitize_key',
            ],
            'order' => [
                'default' => 'DESC',
                'validate_callback' => fn($param) => in_array($param, ['ASC', 'DESC'], true),
                'sanitize_callback' => fn($param) => strtoupper($param),
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'min_rating' => [
                'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 5,
                'sanitize_callback' => 'floatval',
            ],
        ];
    }
    
    /**
     * レビューパラメータ取得
     * @return array<string, array<string, mixed>>
     */
    private function get_review_params(): array {
        return [
            'title' => [
                'required' => true,
                'validate_callback' => fn($param) => strlen($param) >= 3,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'content' => [
                'required' => true,
                'validate_callback' => fn($param) => strlen($param) >= 50,
                'sanitize_callback' => 'wp_kses_post',
            ],
            'rating' => [
                'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 5,
                'sanitize_callback' => 'floatval',
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'meta' => [
                'validate_callback' => fn($param) => is_array($param),
                'sanitize_callback' => fn($param) => array_map('sanitize_text_field', $param),
            ],
        ];
    }
    
    /**
     * 設定パラメータ取得
     * @return array<string, array<string, mixed>>
     */
    private function get_settings_params(): array {
        return [
            'review_type' => [
                'validate_callback' => fn($param) => in_array($param, ['curry', 'ramen', 'sushi'], true),
                'sanitize_callback' => 'sanitize_key',
            ],
            'items_per_page' => [
                'validate_callback' => fn($param) => is_numeric($param) && $param >= 1 && $param <= 50,
                'sanitize_callback' => 'absint',
            ],
            'require_approval' => [
                'validate_callback' => fn($param) => is_bool($param),
                'sanitize_callback' => fn($param) => (bool)$param,
            ],
            'enable_maps' => [
                'validate_callback' => fn($param) => is_bool($param),
                'sanitize_callback' => fn($param) => (bool)$param,
            ],
            'google_maps_api_key' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
    
    /**
     * 権限チェック：レビュー作成
     * @return bool
     */
    public function create_review_permission(): bool {
        return current_user_can(URP_CAP_EDIT_REVIEWS);
    }
    
    /**
     * 権限チェック：レビュー更新
     * @param WP_REST_Request $request
     * @return bool
     */
    public function update_review_permission(WP_REST_Request $request): bool {
        $id = $request->get_param('id');
        return urp_can_edit_review($id);
    }
    
    /**
     * 権限チェック：レビュー削除
     * @param WP_REST_Request $request
     * @return bool
     */
    public function delete_review_permission(WP_REST_Request $request): bool {
        $id = $request->get_param('id');
        return current_user_can('delete_post', $id);
    }
    
    /**
     * 権限チェック：評価
     * @return bool
     */
    public function rating_permission(): bool {
        return is_user_logged_in();
    }
    
    /**
     * 権限チェック：アップロード
     * @return bool
     */
    public function upload_permission(): bool {
        return current_user_can('upload_files');
    }
    
    /**
     * 権限チェック：設定管理
     * @return bool
     */
    public function manage_settings_permission(): bool {
        return current_user_can(URP_CAP_MANAGE_SETTINGS);
    }
    
    /**
     * 権限チェック：アナリティクス表示
     * @return bool
     */
    public function view_analytics_permission(): bool {
        return current_user_can(URP_CAP_VIEW_ANALYTICS);
    }
    
    /**
     * レート制限チェック
     * @param string $action
     * @return bool
     */
    private function check_rate_limit(string $action = 'default'): bool {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $key = $user_id ? "user_{$user_id}" : "ip_{$ip}";
        $cache_key = "rate_limit_{$action}_{$key}";
        
        $count = wp_cache_get($cache_key, URP_CACHE_GROUP) ?: 0;
        $limit = $this->rate_limits[$action] ?? $this->rate_limits['default'];
        
        if ($count >= $limit) {
            return false;
        }
        
        wp_cache_set($cache_key, $count + 1, URP_CACHE_GROUP, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * ビューカウント増加
     * @param int $review_id
     * @return void
     */
    private function increment_view_count(int $review_id): void {
        $current = get_post_meta($review_id, URP_META_VIEW_COUNT, true) ?: 0;
        update_post_meta($review_id, URP_META_VIEW_COUNT, $current + 1);
    }
    
    /**
     * CORS ヘッダー追加
     * @param bool $served
     * @param WP_REST_Response $result
     * @return bool
     */
    public function add_cors_headers(bool $served, WP_REST_Response $result): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // 許可するオリジンを設定
        $allowed_origins = apply_filters('urp_api_allowed_origins', [
            home_url(),
            'http://localhost:3000', // 開発環境
        ]);
        
        if (in_array($origin, $allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Credentials: true');
        }
        
        return $served;
    }
    
    /**
     * 認証チェック
     * @param mixed $result
     * @return mixed
     */
    public function check_authentication(mixed $result): mixed {
        // すでにエラーがある場合はそのまま返す
        if (!empty($result)) {
            return $result;
        }
        
        // JWTトークン認証（オプション）
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (str_starts_with($auth_header, 'Bearer ')) {
            $token = substr($auth_header, 7);
            
            // トークン検証処理
            $user_id = $this->verify_jwt_token($token);
            
            if ($user_id) {
                wp_set_current_user($user_id);
                return true;
            }
        }
        
        return $result;
    }
    
    /**
     * JWTトークン検証（簡易実装）
     * @param string $token
     * @return int|false
     */
    private function verify_jwt_token(string $token): int|false {
        // 実際の実装では適切なJWTライブラリを使用
        // ここでは簡易的な実装
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        // トークンからユーザーIDを取得する処理
        // 実際の実装が必要
        
        return false;
    }
}