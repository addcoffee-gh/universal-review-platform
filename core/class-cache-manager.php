<?php
/**
 * Universal Review Platform - Cache Manager
 * 
 * キャッシュの管理と最適化
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
 * キャッシュ管理クラス
 */
class URP_Cache_Manager {
    
    /**
     * キャッシュグループ
     * @var string
     */
    private const CACHE_GROUP = 'urp_cache';
    
    /**
     * デフォルトの有効期限
     * @var int
     */
    private const DEFAULT_EXPIRY = 3600; // 1時間
    
    /**
     * キャッシュキープレフィックス
     * @var string
     */
    private const KEY_PREFIX = 'urp_';
    
    /**
     * キャッシュドライバー
     * @var string
     */
    private string $driver;
    
    /**
     * キャッシュ設定
     * @var array<string, mixed>
     */
    private array $config;
    
    /**
     * キャッシュ統計
     * @var array<string, int>
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];
    
    /**
     * 外部キャッシュインスタンス
     * @var mixed
     */
    private mixed $external_cache = null;
    
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
        $this->load_config();
        $this->detect_driver();
        $this->init_external_cache();
        $this->register_hooks();
    }
    
    /**
     * 設定読み込み
     * @return void
     */
    private function load_config(): void {
        $this->config = [
            'enabled' => urp_get_option('cache_enabled', true),
            'expiry' => urp_get_option('cache_expiry', self::DEFAULT_EXPIRY),
            'driver' => urp_get_option('cache_driver', 'object'),
            'redis' => [
                'host' => urp_get_option('redis_host', '127.0.0.1'),
                'port' => urp_get_option('redis_port', 6379),
                'password' => urp_get_option('redis_password', ''),
                'database' => urp_get_option('redis_database', 0),
            ],
            'memcached' => [
                'servers' => urp_get_option('memcached_servers', [['127.0.0.1', 11211]]),
            ],
        ];
    }
    
    /**
     * ドライバー検出
     * @return void
     */
    private function detect_driver(): void {
        // PHP 8.0: match式
        $this->driver = match($this->config['driver']) {
            'redis' => class_exists('Redis') ? 'redis' : 'object',
            'memcached' => class_exists('Memcached') ? 'memcached' : 'object',
            'apcu' => function_exists('apcu_fetch') ? 'apcu' : 'object',
            'file' => 'file',
            default => 'object'
        };
    }
    
    /**
     * 外部キャッシュ初期化
     * @return void
     */
    private function init_external_cache(): void {
        try {
            switch ($this->driver) {
                case 'redis':
                    $this->init_redis();
                    break;
                    
                case 'memcached':
                    $this->init_memcached();
                    break;
                    
                case 'file':
                    $this->init_file_cache();
                    break;
            }
        } catch (\Exception $e) {
            // エラーの場合はobjectキャッシュにフォールバック
            $this->driver = 'object';
            if (URP_DEBUG) {
                error_log('URP Cache: Failed to initialize ' . $this->config['driver'] . ' - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Redis初期化
     * @return void
     * @throws \Exception
     */
    private function init_redis(): void {
        $redis = new \Redis();
        
        $connected = $redis->connect(
            $this->config['redis']['host'],
            $this->config['redis']['port']
        );
        
        if (!$connected) {
            throw new \Exception('Redis connection failed');
        }
        
        if ($this->config['redis']['password']) {
            $redis->auth($this->config['redis']['password']);
        }
        
        if ($this->config['redis']['database']) {
            $redis->select($this->config['redis']['database']);
        }
        
        $this->external_cache = $redis;
    }
    
    /**
     * Memcached初期化
     * @return void
     * @throws \Exception
     */
    private function init_memcached(): void {
        $memcached = new \Memcached();
        
        foreach ($this->config['memcached']['servers'] as $server) {
            $memcached->addServer($server[0], $server[1]);
        }
        
        $stats = $memcached->getStats();
        if (empty($stats)) {
            throw new \Exception('Memcached connection failed');
        }
        
        $this->external_cache = $memcached;
    }
    
    /**
     * ファイルキャッシュ初期化
     * @return void
     */
    private function init_file_cache(): void {
        $cache_dir = WP_CONTENT_DIR . '/cache/urp/';
        
        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);
            
            // .htaccessで保護
            file_put_contents($cache_dir . '.htaccess', "Deny from all\n");
        }
        
        $this->external_cache = $cache_dir;
    }
    
    /**
     * キャッシュ取得
     * PHP 8.0: mixed型、match式
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed {
        if (!$this->config['enabled']) {
            return $default;
        }
        
        $key = $this->prepare_key($key);
        
        $value = match($this->driver) {
            'redis' => $this->get_redis($key),
            'memcached' => $this->get_memcached($key),
            'apcu' => $this->get_apcu($key),
            'file' => $this->get_file($key),
            default => wp_cache_get($key, self::CACHE_GROUP)
        };
        
        if ($value !== false) {
            $this->stats['hits']++;
            return $this->maybe_unserialize($value);
        }
        
        $this->stats['misses']++;
        return $default;
    }
    
    /**
     * キャッシュ設定
     * @param string $key
     * @param mixed $value
     * @param int|null $expiry
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $expiry = null): bool {
        if (!$this->config['enabled']) {
            return false;
        }
        
        $key = $this->prepare_key($key);
        $expiry = $expiry ?? $this->config['expiry'];
        $value = $this->maybe_serialize($value);
        
        $result = match($this->driver) {
            'redis' => $this->set_redis($key, $value, $expiry),
            'memcached' => $this->set_memcached($key, $value, $expiry),
            'apcu' => $this->set_apcu($key, $value, $expiry),
            'file' => $this->set_file($key, $value, $expiry),
            default => wp_cache_set($key, $value, self::CACHE_GROUP, $expiry)
        };
        
        if ($result) {
            $this->stats['writes']++;
        }
        
        return $result;
    }
    
    /**
     * キャッシュ削除
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool {
        $key = $this->prepare_key($key);
        
        $result = match($this->driver) {
            'redis' => $this->delete_redis($key),
            'memcached' => $this->delete_memcached($key),
            'apcu' => $this->delete_apcu($key),
            'file' => $this->delete_file($key),
            default => wp_cache_delete($key, self::CACHE_GROUP)
        };
        
        if ($result) {
            $this->stats['deletes']++;
        }
        
        return $result;
    }
    
    /**
     * キャッシュ存在確認
     * @param string $key
     * @return bool
     */
    public function exists(string $key): bool {
        if (!$this->config['enabled']) {
            return false;
        }
        
        $key = $this->prepare_key($key);
        
        return match($this->driver) {
            'redis' => $this->external_cache?->exists($key) ?? false,
            'memcached' => $this->external_cache?->get($key) !== false,
            'apcu' => apcu_exists($key),
            'file' => file_exists($this->get_file_path($key)),
            default => wp_cache_get($key, self::CACHE_GROUP) !== false
        };
    }
    
    /**
     * 複数キャッシュ取得
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function get_multiple(array $keys): array {
        if (!$this->config['enabled']) {
            return [];
        }
        
        $keys = array_map([$this, 'prepare_key'], $keys);
        
        return match($this->driver) {
            'redis' => $this->get_multiple_redis($keys),
            'memcached' => $this->get_multiple_memcached($keys),
            default => $this->get_multiple_default($keys)
        };
    }
    
    /**
     * 複数キャッシュ設定
     * @param array<string, mixed> $items
     * @param int|null $expiry
     * @return bool
     */
    public function set_multiple(array $items, ?int $expiry = null): bool {
        if (!$this->config['enabled']) {
            return false;
        }
        
        $success = true;
        
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $expiry)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * 複数キャッシュ削除
     * @param array<string> $keys
     * @return bool
     */
    public function delete_multiple(array $keys): bool {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * パターンでキャッシュ削除
     * @param string $pattern
     * @return int
     */
    public function delete_by_pattern(string $pattern): int {
        $pattern = $this->prepare_key($pattern);
        $deleted = 0;
        
        switch ($this->driver) {
            case 'redis':
                $keys = $this->external_cache->keys($pattern);
                if ($keys) {
                    $deleted = $this->external_cache->del($keys);
                }
                break;
                
            case 'file':
                $files = glob($this->external_cache . md5($pattern) . '*');
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
                break;
                
            default:
                // WordPress object cacheはパターン削除をサポートしない
                wp_cache_flush_group(self::CACHE_GROUP);
                $deleted = -1; // 不明
        }
        
        return $deleted;
    }
    
    /**
     * キャッシュフラッシュ
     * @param bool $all
     * @return bool
     */
    public function flush(bool $all = false): bool {
        if ($all) {
            return match($this->driver) {
                'redis' => $this->external_cache?->flushDB() ?? false,
                'memcached' => $this->external_cache?->flush() ?? false,
                'apcu' => apcu_clear_cache(),
                'file' => $this->flush_file_cache(),
                default => wp_cache_flush()
            };
        }
        
        // グループのみフラッシュ
        return wp_cache_flush_group(self::CACHE_GROUP);
    }
    
    /**
     * キャッシュサイズ取得
     * @return int
     */
    public function get_size(): int {
        return match($this->driver) {
            'redis' => $this->external_cache?->dbSize() ?? 0,
            'memcached' => array_sum(array_column($this->external_cache?->getStats() ?? [], 'bytes')) ?? 0,
            'apcu' => apcu_cache_info()['mem_size'] ?? 0,
            'file' => $this->get_file_cache_size(),
            default => 0
        };
    }
    
    /**
     * キャッシュ統計取得
     * @return array<string, mixed>
     */
    public function get_stats(): array {
        $stats = $this->stats;
        $stats['driver'] = $this->driver;
        $stats['size'] = $this->get_size();
        $stats['hit_rate'] = $this->calculate_hit_rate();
        
        // ドライバー固有の統計
        $stats['driver_stats'] = match($this->driver) {
            'redis' => $this->external_cache?->info() ?? [],
            'memcached' => $this->external_cache?->getStats() ?? [],
            'apcu' => apcu_cache_info() ?? [],
            default => []
        };
        
        return $stats;
    }
    
    /**
     * Remember パターン実装
     * @param string $key
     * @param callable $callback
     * @param int|null $expiry
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $expiry = null): mixed {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $expiry);
        }
        
        return $value;
    }
    
    /**
     * タグ付きキャッシュ設定
     * @param string $key
     * @param mixed $value
     * @param array<string> $tags
     * @param int|null $expiry
     * @return bool
     */
    public function tag(string $key, mixed $value, array $tags, ?int $expiry = null): bool {
        // メインキャッシュ設定
        if (!$this->set($key, $value, $expiry)) {
            return false;
        }
        
        // タグ管理
        foreach ($tags as $tag) {
            $tag_key = 'tag_' . $tag;
            $tagged_keys = $this->get($tag_key, []);
            
            if (!in_array($key, $tagged_keys, true)) {
                $tagged_keys[] = $key;
                $this->set($tag_key, $tagged_keys, 0); // タグは無期限
            }
        }
        
        return true;
    }
    
    /**
     * タグでキャッシュフラッシュ
     * @param string $tag
     * @return int
     */
    public function flush_tag(string $tag): int {
        $tag_key = 'tag_' . $tag;
        $tagged_keys = $this->get($tag_key, []);
        $deleted = 0;
        
        foreach ($tagged_keys as $key) {
            if ($this->delete($key)) {
                $deleted++;
            }
        }
        
        // タグ自体も削除
        $this->delete($tag_key);
        
        return $deleted;
    }
    
    // プライベートメソッド（各ドライバー実装）
    
    private function get_redis(string $key): mixed {
        if (!$this->external_cache) {
            return false;
        }
        
        $value = $this->external_cache->get($key);
        return $value === false ? false : $value;
    }
    
    private function set_redis(string $key, mixed $value, int $expiry): bool {
        if (!$this->external_cache) {
            return false;
        }
        
        return $this->external_cache->setex($key, $expiry, $value);
    }
    
    private function delete_redis(string $key): bool {
        if (!$this->external_cache) {
            return false;
        }
        
        return $this->external_cache->del($key) > 0;
    }
    
    private function get_memcached(string $key): mixed {
        if (!$this->external_cache) {
            return false;
        }
        
        return $this->external_cache->get($key);
    }
    
    private function set_memcached(string $key, mixed $value, int $expiry): bool {
        if (!$this->external_cache) {
            return false;
        }
        
        return $this->external_cache->set($key, $value, $expiry);
    }
    
    private function delete_memcached(string $key): bool {
        if (!$this->external_cache) {
            return false;
        }
        
        return $this->external_cache->delete($key);
    }
    
    private function get_apcu(string $key): mixed {
        $success = false;
        $value = apcu_fetch($key, $success);
        return $success ? $value : false;
    }
    
    private function set_apcu(string $key, mixed $value, int $expiry): bool {
        return apcu_store($key, $value, $expiry);
    }
    
    private function delete_apcu(string $key): bool {
        return apcu_delete($key);
    }
    
    private function get_file(string $key): mixed {
        $file_path = $this->get_file_path($key);
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file_path));
        
        // 有効期限チェック
        if ($data['expiry'] > 0 && $data['expiry'] < time()) {
            unlink($file_path);
            return false;
        }
        
        return $data['value'];
    }
    
    private function set_file(string $key, mixed $value, int $expiry): bool {
        $file_path = $this->get_file_path($key);
        
        $data = [
            'value' => $value,
            'expiry' => $expiry > 0 ? time() + $expiry : 0,
        ];
        
        return file_put_contents($file_path, serialize($data), LOCK_EX) !== false;
    }
    
    private function delete_file(string $key): bool {
        $file_path = $this->get_file_path($key);
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        
        return false;
    }
    
    private function get_file_path(string $key): string {
        return $this->external_cache . md5($key) . '.cache';
    }
    
    private function get_file_cache_size(): int {
        if (!is_string($this->external_cache)) {
            return 0;
        }
        
        $size = 0;
        $files = glob($this->external_cache . '*.cache');
        
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $size;
    }
    
    private function flush_file_cache(): bool {
        if (!is_string($this->external_cache)) {
            return false;
        }
        
        $files = glob($this->external_cache . '*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    private function get_multiple_redis(array $keys): array {
        if (!$this->external_cache) {
            return [];
        }
        
        $values = $this->external_cache->mget($keys);
        return array_combine($keys, $values) ?: [];
    }
    
    private function get_multiple_memcached(array $keys): array {
        if (!$this->external_cache) {
            return [];
        }
        
        return $this->external_cache->getMulti($keys) ?: [];
    }
    
    private function get_multiple_default(array $keys): array {
        $values = [];
        
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== null) {
                $values[$key] = $value;
            }
        }
        
        return $values;
    }
    
    private function prepare_key(string $key): string {
        return self::KEY_PREFIX . $key;
    }
    
    private function maybe_serialize(mixed $value): mixed {
        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }
        return $value;
    }
    
    private function maybe_unserialize(mixed $value): mixed {
        if (is_string($value)) {
            $unserialized = @unserialize($value);
            if ($unserialized !== false || $value === 'b:0;') {
                return $unserialized;
            }
        }
        return $value;
    }
    
    private function calculate_hit_rate(): float {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($this->stats['hits'] / $total) * 100, 2);
    }
    
    /**
     * フック登録
     * @return void
     */
    private function register_hooks(): void {
        // キャッシュクリアイベント
        add_action('urp_clear_cache', [$this, 'flush']);
        add_action('save_post_' . URP_POST_TYPE, [$this, 'flush_review_cache']);
        add_action('delete_post', [$this, 'flush_review_cache']);
        
        // 統計出力（デバッグモード）
        if (URP_DEBUG) {
            add_action('shutdown', [$this, 'output_stats']);
        }
    }
    
    /**
     * レビューキャッシュフラッシュ
     * @param int $post_id
     * @return void
     */
    public function flush_review_cache(int $post_id): void {
        $this->delete('review_' . $post_id);
        $this->flush_tag('reviews');
        $this->flush_tag('rankings');
    }
    
    /**
     * 統計出力
     * @return void
     */
    public function output_stats(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = $this->get_stats();
        
        echo "<!-- URP Cache Stats:\n";
        echo "Driver: {$stats['driver']}\n";
        echo "Hits: {$stats['hits']}\n";
        echo "Misses: {$stats['misses']}\n";
        echo "Hit Rate: {$stats['hit_rate']}%\n";
        echo "Size: " . size_format($stats['size']) . "\n";
        echo "-->\n";
    }
}