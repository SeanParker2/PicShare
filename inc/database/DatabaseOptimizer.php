<?php
/**
 * PicShare主题数据库优化类
 * 
 * 为自定义字段添加数据库索引，提升筛选查询性能
 */

namespace PicShare\Database;

class DatabaseOptimizer {
    /**
     * 需要添加索引的自定义字段
     */
    private static $indexed_fields = [
        'show_use',      // 素材用途
        'show_industry', // 素材行业
        'show_type',     // 素材类型
        'show_format',   // 素材格式
        'views',         // 浏览量
        'likes',         // 点赞数
        'downloads',     // 下载数
    ];
    
    /**
     * 初始化数据库优化
     */
    public static function init() {
        // 在WordPress初始化后添加索引
        \add_action('init', [__CLASS__, 'addIndexes']);
        
        // 在主题激活时创建索引
        \add_action('after_switch_theme', [__CLASS__, 'createIndexes']);
        
        // 优化查询
        \add_action('pre_get_posts', [__CLASS__, 'optimizeQueries']);
    }
    
    /**
     * 添加数据库索引
     */
    public static function addIndexes() {
        // 检查是否已经添加过索引
        if (\get_option('PicShare_indexes_added', false)) {
            return;
        }
        
        self::createIndexes();
        
        // 标记索引已添加
        \update_option('PicShare_indexes_added', true);
    }
    
    /**
     * 创建数据库索引
     */
    public static function createIndexes() {
        global $wpdb;
        
        // 为postmeta表添加复合索引
        $indexes = [
            // 为meta_key和meta_value添加复合索引
            "ALTER TABLE {$wpdb->postmeta} ADD INDEX idx_PicShare_meta_key_value (meta_key(20), meta_value(20))",
            
            // 为post_id和meta_key添加复合索引（如果不存在）
            "ALTER TABLE {$wpdb->postmeta} ADD INDEX idx_PicShare_post_meta (post_id, meta_key(20))",
            
            // 为posts表的post_type和post_status添加复合索引
            "ALTER TABLE {$wpdb->posts} ADD INDEX idx_PicShare_type_status (post_type(20), post_status(20))",
            
            // 为posts表的post_date添加索引
            "ALTER TABLE {$wpdb->posts} ADD INDEX idx_PicShare_post_date (post_date)",
        ];
        
        foreach ($indexes as $sql) {
            // 检查索引是否已存在
            $index_name = self::extractIndexName($sql);
            if (!self::indexExists($wpdb->postmeta, $index_name) && !self::indexExists($wpdb->posts, $index_name)) {
                $wpdb->query($sql);
            }
        }
        
        // 为特定的meta_key添加专门的索引
        foreach (self::$indexed_fields as $field) {
            self::createFieldIndex($field);
        }
    }
    
    /**
     * 为特定字段创建索引
     * 
     * @param string $field_name 字段名
     */
    private static function createFieldIndex($field_name) {
        global $wpdb;
        
        $index_name = "idx_PicShare_{$field_name}";
        
        // 检查索引是否已存在
        if (self::indexExists($wpdb->postmeta, $index_name)) {
            return;
        }
        
        $sql = "ALTER TABLE {$wpdb->postmeta} ADD INDEX {$index_name} (meta_key(20), meta_value(50)) 
                WHERE meta_key = '{$field_name}'";
        
        $wpdb->query($sql);
    }
    
    /**
     * 检查索引是否存在
     * 
     * @param string $table_name 表名
     * @param string $index_name 索引名
     * @return bool 是否存在
     */
    private static function indexExists($table_name, $index_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
            $index_name
        ));
        
        return !empty($result);
    }
    
    /**
     * 从SQL语句中提取索引名
     * 
     * @param string $sql SQL语句
     * @return string 索引名
     */
    private static function extractIndexName($sql) {
        if (preg_match('/ADD INDEX (\w+)/', $sql, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * 优化查询
     * 
     * @param \WP_Query $query 查询对象
     */
    public static function optimizeQueries($query) {
        // 只在主查询和前端优化
        if (\is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // 优化素材查询
        if ($query->get('post_type') === 'show') {
            self::optimizeShowQuery($query);
        }
        
        // 优化社区查询
        if ($query->get('post_type') === 'forum') {
            self::optimizeForumQuery($query);
        }
    }
    
    /**
     * 优化素材查询
     * 
     * @param \WP_Query $query 查询对象
     */
    private static function optimizeShowQuery($query) {
        // 设置合理的每页数量
        if (!$query->get('posts_per_page')) {
            $query->set('posts_per_page', 12);
        }
        
        // 优化排序
        $orderby = $query->get('orderby');
        if (empty($orderby)) {
            // 默认按发布时间排序
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        }
        
        // 优化meta查询
        $meta_query = $query->get('meta_query');
        if (!empty($meta_query)) {
            // 确保使用索引友好的查询
            $query->set('meta_query', self::optimizeMetaQuery($meta_query));
        }
    }
    
    /**
     * 优化社区查询
     * 
     * @param \WP_Query $query 查询对象
     */
    private static function optimizeForumQuery($query) {
        // 设置合理的每页数量
        if (!$query->get('posts_per_page')) {
            $query->set('posts_per_page', 10);
        }
        
        // 默认按发布时间排序
        if (!$query->get('orderby')) {
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        }
    }
    
    /**
     * 优化meta查询
     * 
     * @param array $meta_query meta查询数组
     * @return array 优化后的meta查询
     */
    private static function optimizeMetaQuery($meta_query) {
        foreach ($meta_query as &$query) {
            if (is_array($query) && isset($query['key'])) {
                // 确保使用精确匹配而不是LIKE查询
                if (!isset($query['compare'])) {
                    $query['compare'] = '=';
                }
                
                // 为数值字段设置正确的类型
                if (in_array($query['key'], ['views', 'likes', 'downloads'])) {
                    $query['type'] = 'NUMERIC';
                }
            }
        }
        
        return $meta_query;
    }
    
    /**
     * 获取数据库统计信息
     * 
     * @return array 统计信息
     */
    public static function getStats() {
        global $wpdb;
        
        $stats = [];
        
        // 获取表大小
        $result = $wpdb->get_results(
            "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
             FROM information_schema.TABLES 
             WHERE table_schema = '{$wpdb->dbname}' 
             AND table_name IN ('{$wpdb->posts}', '{$wpdb->postmeta}')"
        );
        
        foreach ($result as $row) {
            $stats['table_sizes'][$row->table_name] = $row->size_mb . ' MB';
        }
        
        // 获取索引信息
        $indexes = $wpdb->get_results(
            "SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name LIKE 'idx_PicShare%'"
        );
        
        $stats['indexes'] = array_map(function($index) {
            return $index->Key_name;
        }, $indexes);
        
        return $stats;
    }
    
    /**
     * 清理数据库
     */
    public static function cleanup() {
        global $wpdb;
        
        // 清理孤立的meta数据
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm 
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.ID IS NULL"
        );
        
        // 清理自动保存和修订版本的meta数据
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_status IN ('auto-draft', 'revision')"
        );
        
        // 优化表
        $wpdb->query("OPTIMIZE TABLE {$wpdb->posts}");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->postmeta}");
    }
}

// 初始化数据库优化
DatabaseOptimizer::init();