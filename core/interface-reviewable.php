<?php
/**
 * Universal Review Platform - Reviewable Interface
 * 
 * レビュー可能なオブジェクトのインターフェース定義
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
 * レビュー可能インターフェース
 */
interface Reviewable {
    
    /**
     * レビューIDを取得
     * @return int
     */
    public function get_id(): int;
    
    /**
     * レビュータイトルを取得
     * @return string
     */
    public function get_title(): string;
    
    /**
     * レビュータイトルを設定
     * @param string $title
     * @return self
     */
    public function set_title(string $title): self;
    
    /**
     * レビュー内容を取得
     * @return string
     */
    public function get_content(): string;
    
    /**
     * レビュー内容を設定
     * @param string $content
     * @return self
     */
    public function set_content(string $content): self;
    
    /**
     * レビュー評価を取得
     * @return float
     */
    public function get_rating(): float;
    
    /**
     * レビュー評価を設定
     * @param float $rating
     * @return self
     */
    public function set_rating(float $rating): self;
    
    /**
     * レビューステータスを取得
     * @return string
     */
    public function get_status(): string;
    
    /**
     * レビューステータスを設定
     * @param string $status
     * @return self
     */
    public function set_status(string $status): self;
    
    /**
     * レビュー作成日時を取得
     * @return string
     */
    public function get_created_date(): string;
    
    /**
     * レビュー更新日時を取得
     * @return string
     */
    public function get_modified_date(): string;
    
    /**
     * レビュー投稿者IDを取得
     * @return int
     */
    public function get_author_id(): int;
    
    /**
     * レビュー投稿者IDを設定
     * @param int $author_id
     * @return self
     */
    public function set_author_id(int $author_id): self;
    
    /**
     * レビューメタデータを取得
     * @param string|null $key
     * @return mixed
     */
    public function get_meta(?string $key = null): mixed;
    
    /**
     * レビューメタデータを設定
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function set_meta(string $key, mixed $value): self;
    
    /**
     * レビューを保存
     * @return bool
     */
    public function save(): bool;
    
    /**
     * レビューを削除
     * @param bool $force_delete
     * @return bool
     */
    public function delete(bool $force_delete = false): bool;
    
    /**
     * レビューを公開
     * @return bool
     */
    public function publish(): bool;
    
    /**
     * レビューを下書きに戻す
     * @return bool
     */
    public function draft(): bool;
    
    /**
     * レビューバリデーション
     * @return bool
     */
    public function validate(): bool;
    
    /**
     * レビューデータを配列で取得
     * @return array<string, mixed>
     */
    public function to_array(): array;
    
    /**
     * JSONエンコード用データ取得
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}