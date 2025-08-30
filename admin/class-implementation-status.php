<?php
/**
 * Implementation Status Dashboard - 実装状況管理
 * 
 * どこまで作ったか一目で分かる管理画面
 * 各機能の実装状況、動作確認、TODO管理
 */

namespace URP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class URP_Implementation_Status {
    
    /**
     * 初期化
     */
    public static function init() {
        add_action('admin_menu', [self::class, 'add_menu'], 99);
        add_action('wp_ajax_urp_check_component', [self::class, 'ajax_check_component']);
    }
    
    /**
     * メニュー追加
     */
    public static function add_menu() {
        add_submenu_page(
            'urp-dashboard',
            '実装状況',
            '🔧 実装状況',
            'manage_options',
            'urp-implementation',
            [self::class, 'render_page']
        );
    }
    
    /**
     * ページ表示
     */
    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>Universal Review Platform - 実装状況ダッシュボード</h1>
            
            <div class="urp-status-grid">
                
                <!-- コア機能の状況 -->
                <div class="urp-status-section">
                    <h2>📦 コア機能（Core）</h2>
                    <?php self::render_core_status(); ?>
                </div>
                
                <!-- 差別化機能の状況 -->
                <div class="urp-status-section">
                    <h2>🌟 差別化機能（どこにも負けない）</h2>
                    <?php self::render_unique_features(); ?>
                </div>
                
                <!-- データベース状況 -->
                <div class="urp-status-section">
                    <h2>🗄️ データベース</h2>
                    <?php self::render_database_status(); ?>
                </div>
                
                <!-- 管理画面UI状況 -->
                <div class="urp-status-section">
                    <h2>🖥️ 管理画面UI</h2>
                    <?php self::render_admin_ui_status(); ?>
                </div>
                
                <!-- 専門プラグイン -->
                <div class="urp-status-section">
                    <h2>🔌 専門プラグイン</h2>
                    <?php self::render_extension_status(); ?>
                </div>
                
                <!-- TODO/次の作業 -->
                <div class="urp-status-section">
                    <h2>📝 TODO / 次の作業候補</h2>
                    <?php self::render_todo_list(); ?>
                </div>
                
            </div>
            
            <!-- リアルタイムチェック -->
            <div class="urp-realtime-check">
                <h2>🔍 リアルタイム動作確認</h2>
                <button id="urp-check-all" class="button button-primary">全機能をチェック</button>
                <div id="urp-check-results"></div>
            </div>
            
            <style>
            .urp-status-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            
            .urp-status-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
            }
            
            .urp-status-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #f0f0f1;
            }
            
            .status-table {
                width: 100%;
                margin-top: 15px;
            }
            
            .status-table td {
                padding: 8px 5px;
                border-bottom: 1px solid #f0f0f1;
            }
            
            .status-table td:first-child {
                font-weight: 600;
                width: 60%;
            }
            
            .status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            
            .status-complete {
                background: #00a32a;
                color: white;
            }
            
            .status-partial {
                background: #dba617;
                color: white;
            }
            
            .status-missing {
                background: #d63638;
                color: white;
            }
            
            .status-planned {
                background: #72aee6;
                color: white;
            }
            
            .progress-bar {
                width: 100%;
                height: 20px;
                background: #f0f0f1;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #00a32a, #00c637);
                transition: width 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 12px;
                font-weight: bold;
            }
            
            .todo-item {
                padding: 10px;
                margin: 5px 0;
                background: #f6f7f7;
                border-left: 4px solid #2271b1;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .todo-item:hover {
                background: #e5e5e5;
                transform: translateX(5px);
            }
            
            .priority-high {
                border-left-color: #d63638;
            }
            
            .priority-medium {
                border-left-color: #dba617;
            }
            
            .priority-low {
                border-left-color: #00a32a;
            }
            
            .urp-realtime-check {
                background: #fff;
                padding: 20px;
                margin-top: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            
            #urp-check-results {
                margin-top: 20px;
            }
            
            .check-result {
                padding: 10px;
                margin: 5px 0;
                border-radius: 3px;
            }
            
            .check-success {
                background: #d4f4dd;
                border: 1px solid #00a32a;
            }
            
            .check-error {
                background: #fcf0f1;
                border: 1px solid #d63638;
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                // 全機能チェック
                $('#urp-check-all').on('click', function() {
                    const $button = $(this);
                    const $results = $('#urp-check-results');
                    
                    $button.prop('disabled', true).text('チェック中...');
                    $results.html('');
                    
                    const components = [
                        'URP_Site_Mode',
                        'URP_Extension_Manager',
                        'URP_Rating_Fields',
                        'URP_Trust_Score',
                        'URP_Affiliate_Manager'
                    ];
                    
                    components.forEach(function(component) {
                        $.post(ajaxurl, {
                            action: 'urp_check_component',
                            component: component,
                            nonce: '<?php echo wp_create_nonce('urp_check'); ?>'
                        }, function(response) {
                            const resultClass = response.success ? 'check-success' : 'check-error';
                            const icon = response.success ? '✅' : '❌';
                            const message = response.data.message || 'チェック完了';
                            
                            $results.append(
                                '<div class="check-result ' + resultClass + '">' +
                                icon + ' ' + component + ': ' + message +
                                '</div>'
                            );
                        });
                    });
                    
                    setTimeout(function() {
                        $button.prop('disabled', false).text('全機能をチェック');
                    }, 2000);
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * 自動検出：コンポーネントの実装状況を判定
     */
    private static function detect_component_status($class, $file) {
        $plugin_dir = WP_PLUGIN_DIR . '/universal-review-platform/';
        
        // ファイルの存在チェック
        $file_exists = file_exists($plugin_dir . $file);
        
        // クラスの存在チェック（namespace対応）
        $class_exists = class_exists($class) || 
                       class_exists('URP\\Core\\' . $class) ||
                       class_exists('URP\\Admin\\' . $class);
        
        // ファイルが存在する場合、中身もチェック
        $has_content = false;
        if ($file_exists) {
            $content = file_get_contents($plugin_dir . $file);
            // クラス定義があるか確認
            $has_content = (strpos($content, 'class ' . $class) !== false) ||
                          (strpos($content, 'class URP_' . str_replace('URP_', '', $class)) !== false);
            
            // 主要メソッドの存在確認（オプション）
            $has_init = strpos($content, 'function init') !== false ||
                       strpos($content, 'function __construct') !== false;
        }
        
        // 状態判定ロジック
        if ($file_exists && $class_exists && $has_content) {
            return 'complete';
        } elseif ($file_exists && $has_content) {
            return 'partial';  // ファイルはあるがクラスがロードされていない
        } elseif ($file_exists) {
            return 'skeleton';  // ファイルはあるが中身が不完全
        } else {
            return 'missing';
        }
    }
    
    /**
     * コア機能の状況表示（自動検出版）
     */
    private static function render_core_status() {
        // コンポーネント定義（ファイルパスと説明のみ管理）
        $core_components = [
            'URP_Site_Mode' => [
                'label' => 'サイトモード管理',
                'file' => 'core/class-site-mode.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['init', 'get_mode', 'set_mode'],
                'note' => '3モード切替機能'
            ],
            'URP_Extension_Manager' => [
                'label' => '専門プラグイン管理',
                'file' => 'core/class-extension-manager.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['init', 'register_extension'],
                'note' => '拡張プラグイン連携'
            ],
            'URP_Rating_Fields' => [
                'label' => '動的評価項目',
                'file' => 'core/class-rating-fields.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['register_field', 'get_form_fields'],
                'note' => '評価項目の動的管理'
            ],
            'URP_Trust_Score' => [
                'label' => '信頼度スコア',
                'file' => 'core/class-trust-score.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['calculate', 'get_badge'],
                'note' => 'レビュアー信頼度システム'
            ],
            'URP_Affiliate_Manager' => [
                'label' => 'アフィリエイト管理',
                'file' => 'core/class-affiliate-manager.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['fetch_amazon_product', 'generate_affiliate_link'],
                'note' => 'Amazon/楽天連携'
            ],
            'URP_Database' => [
                'label' => 'データベース管理',
                'file' => 'core/class-database.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['create_tables', 'upgrade'],
                'note' => 'テーブル管理'
            ],
            'URP_Review_Manager' => [
                'label' => 'レビュー管理',
                'file' => 'core/class-review-manager.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['create', 'update', 'delete'],
                'note' => 'CRUD操作'
            ],
            'URP_Security' => [
                'label' => 'セキュリティ',
                'file' => 'core/class-security.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['sanitize', 'validate'],
                'note' => 'セキュリティ処理'
            ],
            'URP_API_Router' => [
                'label' => 'APIルーター',
                'file' => 'core/class-api-router.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['register_routes'],
                'note' => 'REST API'
            ],
            'URP_Cache_Manager' => [
                'label' => 'キャッシュ管理',
                'file' => 'core/class-cache-manager.php',
                'namespace' => 'URP\\Core',
                'required_methods' => ['get', 'set', 'delete'],
                'note' => 'キャッシュ制御'
            ]
        ];
        
        $total = count($core_components);
        $complete = 0;
        $partial = 0;
        $skeleton = 0;
        
        echo '<table class="status-table">';
        foreach ($core_components as $class => $info) {
            // 自動検出で状態を判定
            $status = self::detect_component_status($class, $info['file']);
            
            // メソッドの実装状況も確認（オプション）
            $method_status = self::check_required_methods($class, $info);
            
            // バッジの設定
            switch($status) {
                case 'complete':
                    $complete++;
                    $badge = '<span class="status-badge status-complete">実装済</span>';
                    $icon = '✅';
                    break;
                case 'partial':
                    $partial++;
                    $badge = '<span class="status-badge status-partial">一部実装</span>';
                    $icon = '⚠️';
                    break;
                case 'skeleton':
                    $skeleton++;
                    $badge = '<span class="status-badge status-planned">スケルトン</span>';
                    $icon = '📝';
                    break;
                default:
                    $badge = '<span class="status-badge status-missing">未実装</span>';
                    $icon = '❌';
            }
            
            echo '<tr>';
            echo '<td>';
            echo $icon . ' <strong>' . esc_html($info['label']) . '</strong>';
            
            // 詳細情報を表示
            echo '<br><small style="color:#666;">';
            echo esc_html($info['note']);
            
            // ファイルとクラスの状態を詳細表示
            if ($status === 'partial') {
                echo ' (ファイルあり、クラス未ロード)';
            } elseif ($status === 'skeleton') {
                echo ' (スケルトンファイル)';
            }
            
            // メソッド実装状況
            if ($method_status && $status === 'complete') {
                echo '<br>メソッド: ' . $method_status;
            }
            
            echo '</small></td>';
            echo '<td>' . $badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // 進捗バー（重み付けを調整）
        $progress = round((
            ($complete * 1.0) + 
            ($partial * 0.6) + 
            ($skeleton * 0.2)
        ) / $total * 100);
        
        echo '<div class="progress-bar">';
        echo '<div class="progress-fill" style="width:' . $progress . '%">' . $progress . '%</div>';
        echo '</div>';
        
        // サマリー
        echo '<p style="margin-top:10px; font-size:12px; color:#666;">';
        echo sprintf(
            '完成: %d / 一部実装: %d / スケルトン: %d / 未実装: %d',
            $complete,
            $partial,
            $skeleton,
            $total - $complete - $partial - $skeleton
        );
        echo '</p>';
    }
    
    /**
     * 必須メソッドの実装確認
     */
    private static function check_required_methods($class, $info) {
        if (!isset($info['required_methods']) || empty($info['required_methods'])) {
            return null;
        }
        
        // namespace付きクラス名
        $full_class = $info['namespace'] . '\\' . $class;
        
        if (!class_exists($full_class) && !class_exists($class)) {
            return null;
        }
        
        $class_name = class_exists($full_class) ? $full_class : $class;
        $reflection = new \ReflectionClass($class_name);
        
        $implemented = [];
        $missing = [];
        
        foreach ($info['required_methods'] as $method) {
            if ($reflection->hasMethod($method)) {
                $implemented[] = $method;
            } else {
                $missing[] = $method;
            }
        }
        
        if (count($missing) === 0) {
            return count($implemented) . '/' . count($info['required_methods']) . ' 実装済';
        } else {
            return count($implemented) . '/' . count($info['required_methods']) . ' (未実装: ' . implode(', ', $missing) . ')';
        }
    }
    
    /**
     * 差別化機能の状況（自動検出版）
     */
    private static function render_unique_features() {
        // 差別化機能の定義
        $features = [
            'URP_Gamification' => [
                'label' => 'ゲーミフィケーション',
                'file' => 'core/class-gamification.php',
                'priority' => 5,
                'description' => 'バッジ、レベル、ポイント'
            ],
            'URP_Social_Proof' => [
                'label' => '社会的証明',
                'file' => 'core/class-social-proof.php',
                'priority' => 7,
                'description' => '「今○人が見ています」'
            ],
            'URP_ML_Spam_Detector' => [
                'label' => '機械学習スパム検出',
                'file' => 'core/class-ml-spam-detector.php',
                'priority' => 8,
                'description' => 'AI自動判定'
            ],
            'URP_Realtime_Update' => [
                'label' => 'リアルタイム更新',
                'file' => 'core/class-realtime-update.php',
                'priority' => 9,
                'description' => 'WebSocket'
            ],
            'URP_AI_Summary' => [
                'label' => 'AI要約生成',
                'file' => 'core/class-ai-summary.php',
                'priority' => 10,
                'description' => '100件→3行要約'
            ],
            'URP_Taste_Map' => [
                'label' => '嗜好マッピング',
                'file' => 'core/class-taste-map.php',
                'priority' => 11,
                'description' => 'ユーザー好み分析'
            ],
            'URP_Photo_AI' => [
                'label' => '画像AI解析',
                'file' => 'core/class-photo-ai.php',
                'priority' => 12,
                'description' => '料理自動判定'
            ],
            'URP_Price_Tracker' => [
                'label' => '価格追跡',
                'file' => 'core/class-price-tracker.php',
                'priority' => 13,
                'description' => '価格変動アラート'
            ]
        ];
        
        $implemented = 0;
        $total = count($features);
        
        echo '<table class="status-table">';
        foreach ($features as $class => $info) {
            // 自動検出
            $status = self::detect_component_status($class, $info['file']);
            
            // アイコンとバッジ設定
            switch($status) {
                case 'complete':
                    $implemented++;
                    $badge = '<span class="status-badge status-complete">実装済</span>';
                    $icon = '✅';
                    break;
                case 'partial':
                    $badge = '<span class="status-badge status-partial">一部実装</span>';
                    $icon = '⚠️';
                    break;
                case 'skeleton':
                    $badge = '<span class="status-badge status-planned">準備中</span>';
                    $icon = '📝';
                    break;
                default:
                    $badge = '<span class="status-badge status-missing">未実装</span>';
                    $icon = '❌';
            }
            
            echo '<tr>';
            echo '<td>';
            echo $icon . ' <strong>' . esc_html($info['label']) . '</strong>';
            echo '<br><small>' . esc_html($info['description']) . ' (優先度: ' . $info['priority'] . ')</small>';
            echo '</td>';
            echo '<td>' . $badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // 実装率表示
        if ($total > 0) {
            $percentage = round(($implemented / $total) * 100);
            echo '<p style="margin-top:15px; color:#666;">';
            echo '差別化機能実装率: ' . $percentage . '% (' . $implemented . '/' . $total . ')';
            echo '</p>';
        }
    }
    
    /**
     * データベース状況（自動検出版）
     */
    private static function render_database_status() {
        global $wpdb;
        
        // テーブル定義（説明のみ管理、存在は自動検出）
        $tables = [
            'urp_rating_fields' => '評価項目定義',
            'urp_rating_values' => '評価値保存',
            'urp_reviewer_ranks' => 'レビュアーランク',
            'urp_review_images' => 'レビュー画像',
            'urp_review_votes' => '役立った票',
            'urp_affiliate_clicks' => 'アフィリクリック',
            'urp_price_history' => '価格履歴',
            'urp_review_meta' => 'レビューメタデータ',
            'urp_user_preferences' => 'ユーザー設定',
            'urp_spam_log' => 'スパムログ',
        ];
        
        $exists_count = 0;
        $total_count = count($tables);
        
        echo '<table class="status-table">';
        foreach ($tables as $table => $description) {
            $full_table = $wpdb->prefix . $table;
            
            // テーブル存在チェック（自動検出）
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            
            if ($exists) {
                $exists_count++;
                $badge = '<span class="status-badge status-complete">作成済</span>';
                $icon = '✅';
                
                // レコード数も取得
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                $extra_info = " ({$count}件)";
            } else {
                $badge = '<span class="status-badge status-missing">未作成</span>';
                $icon = '❌';
                $extra_info = '';
            }
            
            echo '<tr>';
            echo '<td>';
            echo $icon . ' ' . esc_html($description) . $extra_info;
            echo '<br><small style="color:#999;">' . esc_html($full_table) . '</small>';
            echo '</td>';
            echo '<td>' . $badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // 進捗表示
        $progress = round(($exists_count / $total_count) * 100);
        echo '<div class="progress-bar" style="margin-top:15px;">';
        echo '<div class="progress-fill" style="width:' . $progress . '%">' . $exists_count . '/' . $total_count . '</div>';
        echo '</div>';
        
        echo '<p style="margin-top:15px;">';
        echo '<button class="button" onclick="createTables()">未作成テーブルを作成</button>';
        echo '</p>';
    }
    
    /**
     * 管理画面UI状況
     */
    private static function render_admin_ui_status() {
        $ui_components = [
            'ダッシュボード' => 'complete',
            '基本設定' => 'partial',
            'アフィリエイト設定' => 'missing',
            'レビュー管理' => 'partial',
            'レビュアー管理' => 'missing',
            'アナリティクス' => 'missing',
            'インポート/エクスポート' => 'missing',
            '専門プラグイン管理' => 'partial',
        ];
        
        echo '<table class="status-table">';
        foreach ($ui_components as $component => $status) {
            $badge = match($status) {
                'complete' => '<span class="status-badge status-complete">完成</span>',
                'partial' => '<span class="status-badge status-partial">部分実装</span>',
                'missing' => '<span class="status-badge status-missing">未実装</span>',
                default => '<span class="status-badge status-planned">計画中</span>'
            };
            
            echo '<tr>';
            echo '<td>' . esc_html($component) . '</td>';
            echo '<td>' . $badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    /**
     * 専門プラグイン状況
     */
    private static function render_extension_status() {
        $extensions = [
            'カレー専門' => ['curry', 'missing', '🍛'],
            'ラーメン専門' => ['ramen', 'missing', '🍜'],
            '美容室専門' => ['beauty', 'missing', '💇'],
            '寿司専門' => ['sushi', 'missing', '🍣'],
            'カフェ専門' => ['cafe', 'missing', '☕'],
        ];
        
        echo '<table class="status-table">';
        foreach ($extensions as $name => $info) {
            $badge = $info[1] === 'complete' 
                ? '<span class="status-badge status-complete">利用可能</span>'
                : '<span class="status-badge status-missing">未作成</span>';
            
            echo '<tr>';
            echo '<td>' . $info[2] . ' ' . esc_html($name) . '</td>';
            echo '<td>' . $badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<p style="margin-top:15px;">';
        echo '<button class="button">カレー専門プラグインのサンプル作成</button>';
        echo '</p>';
    }
    
    /**
     * TODO リスト
     */
    private static function render_todo_list() {
        $todos = [
            [
                'task' => 'URP_Gamification の実装',
                'priority' => 'medium',
                'reason' => '優先度5の差別化機能'
            ],
            [
                'task' => 'カレー専門プラグインのサンプル作成',
                'priority' => 'medium',
                'reason' => 'システム全体の動作確認に必要'
            ],
            [
                'task' => 'フロントエンドテンプレート作成',
                'priority' => 'medium',
                'reason' => 'レビュー表示・投稿フォームが未実装'
            ],
        ];
        
        foreach ($todos as $todo) {
            $priority_class = 'priority-' . $todo['priority'];
            echo '<div class="todo-item ' . $priority_class . '">';
            echo '<strong>' . esc_html($todo['task']) . '</strong>';
            echo '<br><small>' . esc_html($todo['reason']) . '</small>';
            echo '</div>';
        }
    }
    
    /**
     * Ajax: コンポーネントチェック
     */
    public static function ajax_check_component() {
        check_ajax_referer('urp_check', 'nonce');
        
        $component = sanitize_text_field($_POST['component'] ?? '');
        
        // クラスの存在確認
        $exists = class_exists($component) || class_exists('URP\\Core\\' . $component);
        
        if ($exists) {
            wp_send_json_success(['message' => 'クラスが存在します']);
        } else {
            wp_send_json_error(['message' => 'クラスが見つかりません']);
        }
    }
}

// 初期化
add_action('plugins_loaded', function() {
    URP_Implementation_Status::init();
});