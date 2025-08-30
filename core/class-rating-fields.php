<?php
/**
 * Rating Fields Manager - 評価項目の動的管理
 * 
 * 専門プラグインが独自の評価項目を追加可能
 * 店舗/物販で異なる項目を管理
 * 
 * @package UniversalReviewPlatform
 * @since 1.0.0
 */

namespace URP\Core;

if (!defined('ABSPATH')) {
    exit;
}

class URP_Rating_Fields {
    
    /**
     * フィールドタイプ定義
     */
    const FIELD_TYPES = [
        'rating' => '評価（星）',
        'select' => '単一選択',
        'multiselect' => '複数選択',
        'checkbox' => 'チェックボックス',
        'radio' => 'ラジオボタン',
        'text' => 'テキスト',
        'textarea' => 'テキストエリア',
        'number' => '数値',
        'date' => '日付',
        'range' => 'スライダー'
    ];
    
    /**
     * 評価項目を登録（専門プラグインから呼ばれる）
     * 
     * @param string $extension_id 拡張ID（curry, ramen等）
     * @param string $review_type レビュータイプ（shop, product）
     * @param array $field フィールド定義
     * @return int|false
     */
    public static function register_field($extension_id, $review_type, $field) {
        global $wpdb;
        $table = $wpdb->prefix . 'urp_rating_fields';
        
        // 既存チェック
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table 
             WHERE extension_id = %s 
             AND review_type = %s 
             AND field_key = %s",
            $extension_id,
            $review_type,
            $field['key']
        ));
        
        if ($existing) {
            // 更新
            return $wpdb->update(
                $table,
                [
                    'field_label' => $field['label'],
                    'field_type' => $field['type'],
                    'field_options' => json_encode($field['options'] ?? null, JSON_UNESCAPED_UNICODE),
                    'allow_multiple' => $field['allow_multiple'] ?? false,
                    'required' => $field['required'] ?? false,
                    'sort_order' => $field['order'] ?? 0,
                    'is_active' => $field['active'] ?? true
                ],
                ['id' => $existing]
            );
        } else {
            // 新規登録
            return $wpdb->insert($table, [
                'extension_id' => $extension_id,
                'review_type' => $review_type,
                'field_key' => $field['key'],
                'field_label' => $field['label'],
                'field_type' => $field['type'],
                'field_options' => json_encode($field['options'] ?? null, JSON_UNESCAPED_UNICODE),
                'allow_multiple' => $field['allow_multiple'] ?? false,
                'required' => $field['required'] ?? false,
                'sort_order' => $field['order'] ?? 0,
                'is_active' => $field['active'] ?? true
            ]);
        }
    }
    
    /**
     * 複数の評価項目を一括登録
     */
    public static function register_fields($extension_id, $review_type, $fields) {
        $results = [];
        foreach ($fields as $field) {
            $results[$field['key']] = self::register_field($extension_id, $review_type, $field);
        }
        return $results;
    }
    
    /**
     * レビューフォーム用のフィールドを取得
     */
    public static function get_form_fields($extension_id = null, $review_type = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'urp_rating_fields';
        
        $where = ['is_active = 1'];
        $params = [];
        
        if ($extension_id) {
            $where[] = 'extension_id = %s';
            $params[] = $extension_id;
        }
        
        if ($review_type) {
            $where[] = 'review_type = %s';
            $params[] = $review_type;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY sort_order ASC";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * フィールドHTMLを生成
     */
    public static function render_field($field, $value = null) {
        $field_name = 'urp_field_' . $field->field_key;
        $field_id = 'urp_field_' . $field->id;
        
        $html = '<div class="urp-field-wrapper urp-field-type-' . $field->field_type . '">';
        $html .= '<label for="' . $field_id . '">';
        $html .= esc_html($field->field_label);
        
        if ($field->required) {
            $html .= ' <span class="required">*</span>';
        }
        
        $html .= '</label>';
        
        // フィールドタイプ別のHTML生成
        switch ($field->field_type) {
            case 'rating':
                $html .= self::create_star_rating($field, $field_name, $field_id, $value);
                break;
                
            case 'select':
                $html .= self::create_select($field, $field_name, $field_id, $value);
                break;
                
            case 'multiselect':
                $html .= self::create_multiselect($field, $field_name, $field_id, $value);
                break;
                
            case 'checkbox':
                $html .= self::create_checkbox_group($field, $field_name, $value);
                break;
                
            case 'radio':
                $html .= self::create_radio_group($field, $field_name, $value);
                break;
                
            case 'text':
                $html .= '<input type="text" name="' . $field_name . '" id="' . $field_id . '" 
                          value="' . esc_attr($value) . '" ' . ($field->required ? 'required' : '') . '>';
                break;
                
            case 'textarea':
                $html .= '<textarea name="' . $field_name . '" id="' . $field_id . '" 
                          rows="4" ' . ($field->required ? 'required' : '') . '>' . esc_textarea($value) . '</textarea>';
                break;
                
            case 'number':
                $options = json_decode($field->field_options, true) ?: [];
                $html .= '<input type="number" name="' . $field_name . '" id="' . $field_id . '" 
                          value="' . esc_attr($value) . '"';
                if (isset($options['min'])) $html .= ' min="' . $options['min'] . '"';
                if (isset($options['max'])) $html .= ' max="' . $options['max'] . '"';
                if (isset($options['step'])) $html .= ' step="' . $options['step'] . '"';
                $html .= ($field->required ? ' required' : '') . '>';
                break;
                
            case 'range':
                $options = json_decode($field->field_options, true) ?: [];
                $min = $options['min'] ?? 0;
                $max = $options['max'] ?? 100;
                $step = $options['step'] ?? 1;
                $current = $value ?: $min;
                
                $html .= '<div class="urp-range-wrapper">';
                $html .= '<input type="range" name="' . $field_name . '" id="' . $field_id . '" 
                          value="' . esc_attr($current) . '" min="' . $min . '" max="' . $max . '" 
                          step="' . $step . '" oninput="this.nextElementSibling.value = this.value">';
                $html .= '<output>' . $current . '</output>';
                $html .= '</div>';
                break;
                
            case 'date':
                $html .= '<input type="date" name="' . $field_name . '" id="' . $field_id . '" 
                          value="' . esc_attr($value) . '" ' . ($field->required ? 'required' : '') . '>';
                break;
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 星評価HTML生成
     */
    private static function create_star_rating($field, $name, $id, $value = null) {
        $options = json_decode($field->field_options, true) ?: [];
        $max = $options['max'] ?? 5;
        $current = $value ?: 0;
        
        $html = '<div class="urp-star-rating" data-max="' . $max . '">';
        $html .= '<input type="hidden" name="' . $name . '" id="' . $id . '" value="' . $current . '">';
        
        for ($i = 1; $i <= $max; $i++) {
            $checked = ($i <= $current) ? 'checked' : '';
            $html .= '<span class="star ' . $checked . '" data-value="' . $i . '">★</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 単一選択ドロップダウン
     */
    private static function create_select($field, $name, $id, $value = null) {
        $options = json_decode($field->field_options, true) ?: [];
        
        $html = '<select name="' . $name . '" id="' . $id . '" ' . ($field->required ? 'required' : '') . '>';
        $html .= '<option value="">選択してください</option>';
        
        foreach ($options as $opt_value => $opt_label) {
            $selected = ($value == $opt_value) ? 'selected' : '';
            $html .= '<option value="' . esc_attr($opt_value) . '" ' . $selected . '>';
            $html .= esc_html($opt_label) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }
    
    /**
     * 複数選択ドロップダウン
     */
    private static function create_multiselect($field, $name, $id, $value = null) {
        $options = json_decode($field->field_options, true) ?: [];
        $selected_values = is_array($value) ? $value : (json_decode($value, true) ?: []);
        
        $html = '<select name="' . $name . '[]" id="' . $id . '" multiple class="urp-multiselect" ';
        $html .= ($field->required ? 'required' : '') . '>';
        
        foreach ($options as $opt_value => $opt_label) {
            $selected = in_array($opt_value, $selected_values) ? 'selected' : '';
            $html .= '<option value="' . esc_attr($opt_value) . '" ' . $selected . '>';
            $html .= esc_html($opt_label) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }
    
    /**
     * チェックボックスグループ
     */
    private static function create_checkbox_group($field, $name, $value = null) {
        $options = json_decode($field->field_options, true) ?: [];
        $selected_values = is_array($value) ? $value : (json_decode($value, true) ?: []);
        
        $html = '<div class="urp-checkbox-group">';
        
        foreach ($options as $opt_value => $opt_label) {
            $checked = in_array($opt_value, $selected_values) ? 'checked' : '';
            $field_id = $name . '_' . $opt_value;
            
            $html .= '<label for="' . $field_id . '" class="urp-checkbox">';
            $html .= '<input type="checkbox" name="' . $name . '[]" id="' . $field_id . '" 
                      value="' . esc_attr($opt_value) . '" ' . $checked . '>';
            $html .= ' ' . esc_html($opt_label);
            $html .= '</label>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * ラジオボタングループ
     */
    private static function create_radio_group($field, $name, $value = null) {
        $options = json_decode($field->field_options, true) ?: [];
        
        $html = '<div class="urp-radio-group">';
        
        foreach ($options as $opt_value => $opt_label) {
            $checked = ($value == $opt_value) ? 'checked' : '';
            $field_id = $name . '_' . $opt_value;
            
            $html .= '<label for="' . $field_id . '" class="urp-radio">';
            $html .= '<input type="radio" name="' . $name . '" id="' . $field_id . '" 
                      value="' . esc_attr($opt_value) . '" ' . $checked . ' ';
            $html .= ($field->required ? 'required' : '') . '>';
            $html .= ' ' . esc_html($opt_label);
            $html .= '</label>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 評価値を保存
     */
    public static function save_values($post_id, $field_values) {
        global $wpdb;
        $table = $wpdb->prefix . 'urp_rating_values';
        
        foreach ($field_values as $field_id => $value) {
            $data = [
                'post_id' => $post_id,
                'field_id' => $field_id
            ];
            
            // データ型に応じて適切なカラムに保存
            if (is_array($value)) {
                $data['value_json'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_numeric($value)) {
                if (strpos($value, '.') !== false) {
                    $data['value_rating'] = $value;
                } else {
                    $data['value_number'] = $value;
                }
            } else {
                $data['value_text'] = $value;
            }
            
            // UPSERT処理
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE post_id = %d AND field_id = %d",
                $post_id,
                $field_id
            ));
            
            if ($existing) {
                $wpdb->update($table, $data, ['id' => $existing]);
            } else {
                $wpdb->insert($table, $data);
            }
        }
    }
    
    /**
     * 評価値を取得
     */
    public static function get_values($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'urp_rating_values';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT rv.*, rf.field_key, rf.field_type 
             FROM $table rv
             JOIN {$wpdb->prefix}urp_rating_fields rf ON rv.field_id = rf.id
             WHERE rv.post_id = %d",
            $post_id
        ));
        
        $values = [];
        foreach ($results as $row) {
            // データ型に応じて適切な値を取得
            if ($row->value_json) {
                $value = json_decode($row->value_json, true);
            } elseif ($row->value_rating) {
                $value = $row->value_rating;
            } elseif ($row->value_number) {
                $value = $row->value_number;
            } else {
                $value = $row->value_text;
            }
            
            $values[$row->field_key] = $value;
        }
        
        return $values;
    }
    
    /**
     * フィールドを削除
     */
    public static function delete_field($field_id) {
        global $wpdb;
        
        // 関連する値も削除
        $wpdb->delete(
            $wpdb->prefix . 'urp_rating_values',
            ['field_id' => $field_id]
        );
        
        // フィールド自体を削除
        return $wpdb->delete(
            $wpdb->prefix . 'urp_rating_fields',
            ['id' => $field_id]
        );
    }
}