<?php
/**
 * Universal Review Platform - Cacheable Interface
 * 
 * キャッシュ可能なオブジェクトのインターフェース定義
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 * @requires PHP 8.0
 */

namespace URP\Core\Interfaces;

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * キャッシュ可能インターフェース
 */
interface Cacheable {
    
    /**
     * キャッシュキーを取得
     * @return string
     */
    public function get_cache_key(): string;
    
    /**
     * キャッシュグループを取得
     * @return string
     */
    public function get_cache_group(): string;
    
    /**
     * キャッシュ有効期限を取得（秒）
     * @return int
     */
    public function get_cache_expiry(): int;
    
    /**
     * キャッシュ可能かどうかを判定
     * @return bool
     */
    public function is_cacheable(): bool;
    
    /**
     * キャッシュデータを取得
     * @return mixed
     */
    public function get_cache_data(): mixed;
    
    /**
     * キャッシュデータを設定
     * @param mixed $data
     * @return void
     */
    public function set_cache_data(mixed $data): void;
    
    /**
     * キャッシュをクリア
     * @return bool
     */
    public function clear_cache(): bool;
    
    /**
     * キャッシュを更新
     * @return bool
     */
    public function refresh_cache(): bool;
    
    /**
     * キャッシュタグを取得
     * @return array<string>
     */
    public function get_cache_tags(): array;
    
    /**
     * キャッシュバージョンを取得
     * @return string
     */
    public function get_cache_version(): string;
}