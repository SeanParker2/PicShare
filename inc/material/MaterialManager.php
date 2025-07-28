<?php
/**
 * PicShare素材管理器
 * 
 * 负责素材的上传、分类、筛选、下载等功能
 */

namespace PicShare\Material;

use PicShare\Config\Config;
use PicShare\Cache\CacheManager;

class MaterialManager {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    /**
     * 初始化素材管理
     */
    public function init() {
        // AJAX处理
        \add_action('wp_ajax_material_upload', [$this, 'handle_upload']);
        \add_action('wp_ajax_material_download', [$this, 'handle_download']);
        \add_action('wp_ajax_nopriv_material_download', [$this, 'handle_download']);
        \add_action('wp_ajax_material_like', [$this, 'handle_like']);
        \add_action('wp_ajax_nopriv_material_like', [$this, 'handle_like']);
        \add_action('wp_ajax_material_filter', [$this, 'handle_filter']);
        \add_action('wp_ajax_nopriv_material_filter', [$this, 'handle_filter']);
        
        // 文章钩子
        \add_action('save_post', [$this, 'on_save_material']);
        \add_action('delete_post', [$this, 'on_delete_material']);
        
        // 自定义字段
        \add_action('add_meta_boxes', [$this, 'add_material_meta_boxes']);
        \add_action('save_post', [$this, 'save_material_meta']);
        
        // 前端显示
        \add_filter('the_content', [$this, 'add_download_button']);
        \add_action('wp_head', [$this, 'add_material_schema']);
    }
    
    /**
     * 处理素材上传
     */
    public function handle_upload() {
        // 验证用户权限
        if (!\is_user_logged_in()) {
            \wp_send_json_error('请先登录');
        }
        
        // 验证nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'PicShare_nonce')) {
            \wp_send_json_error('安全验证失败');
        }
        
        $title = \sanitize_text_field($_POST['title'] ?? '');
        $description = \sanitize_textarea_field($_POST['description'] ?? '');
        $category = \intval($_POST['category'] ?? 0);
        $tags = \sanitize_text_field($_POST['tags'] ?? '');
        $material_type = \sanitize_text_field($_POST['material_type'] ?? '');
        $format = \sanitize_text_field($_POST['format'] ?? '');
        $usage = \sanitize_text_field($_POST['usage'] ?? '');
        $industry = \sanitize_text_field($_POST['industry'] ?? '');
        
        // 验证输入
        if (empty($title) || empty($_FILES['material_file'])) {
            \wp_send_json_error('标题和文件不能为空');
        }
        
        // 处理文件上传
        $upload = \wp_handle_upload($_FILES['material_file'], ['test_form' => false]);
        
        if (isset($upload['error'])) {
            \wp_send_json_error($upload['error']);
        }
        
        // 创建文章
        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => 'show',
            'post_author' => \get_current_user_id(),
        ];
        
        $post_id = \wp_insert_post($post_data);
        
        if (\is_wp_error($post_id)) {
            \wp_send_json_error($post_id->get_error_message());
        }
        
        // 保存自定义字段
        \update_post_meta($post_id, 'material_file', $upload['url']);
        \update_post_meta($post_id, 'material_type', $material_type);
        \update_post_meta($post_id, 'material_format', $format);
        \update_post_meta($post_id, 'material_usage', $usage);
        \update_post_meta($post_id, 'material_industry', $industry);
        \update_post_meta($post_id, 'download_count', 0);
        \update_post_meta($post_id, 'like_count', 0);
        \update_post_meta($post_id, 'file_size', filesize($upload['file']));
        
        // 设置分类
        if ($category) {
            \wp_set_post_terms($post_id, [$category], 'show_cat');
        }
        
        // 设置标签
        if (!empty($tags)) {
            \wp_set_post_terms($post_id, explode(',', $tags), 'show_tag');
        }
        
        // 清除相关缓存
        $this->clear_material_cache();
        
        \wp_send_json_success([
            'message' => '素材上传成功！',
            'post_id' => $post_id,
            'edit_url' => \get_edit_post_link($post_id)
        ]);
    }
    
    /**
     * 处理素材下载
     */
    public function handle_download() {
        $post_id = \intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || \get_post_type($post_id) !== 'show') {
            \wp_send_json_error('无效的素材ID');
        }
        
        // 检查下载权限
        if (!$this->can_download($post_id)) {
            \wp_send_json_error('您没有下载权限');
        }
        
        $file_url = \get_post_meta($post_id, 'material_file', true);
        
        if (empty($file_url)) {
            \wp_send_json_error('文件不存在');
        }
        
        // 更新下载计数
        $download_count = (int)\get_post_meta($post_id, 'download_count', true);
        \update_post_meta($post_id, 'download_count', $download_count + 1);
        
        // 记录下载日志
        $this->log_download($post_id);
        
        // 清除缓存
        CacheManager::delete('material_stats_' . $post_id);
        
        \wp_send_json_success([
            'download_url' => $file_url,
            'filename' => basename($file_url)
        ]);
    }
    
    /**
     * 处理素材点赞
     */
    public function handle_like() {
        $post_id = \intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || \get_post_type($post_id) !== 'show') {
            \wp_send_json_error('无效的素材ID');
        }
        
        // 检查是否已点赞
        $user_id = \get_current_user_id();
        $liked_posts = \get_user_meta($user_id, 'liked_posts', true) ?: [];
        
        if (in_array($post_id, $liked_posts)) {
            \wp_send_json_error('您已经点过赞了');
        }
        
        // 更新点赞计数
        $like_count = (int)\get_post_meta($post_id, 'like_count', true);
        \update_post_meta($post_id, 'like_count', $like_count + 1);
        
        // 记录用户点赞
        $liked_posts[] = $post_id;
        \update_user_meta($user_id, 'liked_posts', $liked_posts);
        
        // 清除缓存
        CacheManager::delete('material_stats_' . $post_id);
        
        \wp_send_json_success([
            'like_count' => $like_count + 1,
            'message' => '点赞成功！'
        ]);
    }
    
    /**
     * 处理素材筛选
     */
    public function handle_filter() {
        $filters = [
            'material_type' => \sanitize_text_field($_POST['material_type'] ?? ''),
            'material_format' => \sanitize_text_field($_POST['material_format'] ?? ''),
            'material_usage' => \sanitize_text_field($_POST['material_usage'] ?? ''),
            'material_industry' => \sanitize_text_field($_POST['material_industry'] ?? ''),
            'category' => \intval($_POST['category'] ?? 0),
            'orderby' => \sanitize_text_field($_POST['orderby'] ?? 'date'),
            'order' => \sanitize_text_field($_POST['order'] ?? 'DESC'),
            'posts_per_page' => \intval($_POST['posts_per_page'] ?? 12),
            'paged' => \intval($_POST['paged'] ?? 1),
        ];
        
        $materials = $this->get_filtered_materials($filters);
        
        \wp_send_json_success($materials);
    }
    
    /**
     * 获取筛选后的素材
     */
    public function get_filtered_materials($filters = []) {
        $cache_key = 'filtered_materials_' . md5(serialize($filters));
        $result = CacheManager::get($cache_key);
        
        if ($result === false) {
            $args = [
                'post_type' => 'show',
                'post_status' => 'publish',
                'posts_per_page' => $filters['posts_per_page'] ?? 12,
                'paged' => $filters['paged'] ?? 1,
                'orderby' => $filters['orderby'] ?? 'date',
                'order' => $filters['order'] ?? 'DESC',
                'meta_query' => [],
            ];
            
            // 添加元查询条件
            foreach (['material_type', 'material_format', 'material_usage', 'material_industry'] as $meta_key) {
                if (!empty($filters[$meta_key])) {
                    $args['meta_query'][] = [
                        'key' => $meta_key,
                        'value' => $filters[$meta_key],
                        'compare' => '='
                    ];
                }
            }
            
            // 添加分类查询
            if (!empty($filters['category'])) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'show_cat',
                        'field' => 'term_id',
                        'terms' => $filters['category']
                    ]
                ];
            }
            
            $query = new \WP_Query($args);
            
            $materials = [];
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $materials[] = $this->format_material_data(\get_the_ID());
                }
                \wp_reset_postdata();
            }
            
            $result = [
                'materials' => $materials,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
                'current_page' => $filters['paged'] ?? 1
            ];
            
            CacheManager::set($cache_key, $result, 1800); // 30分钟缓存
        }
        
        return $result;
    }
    
    /**
     * 格式化素材数据
     */
    private function format_material_data($post_id) {
        return [
            'id' => $post_id,
            'title' => \get_the_title($post_id),
            'excerpt' => \get_the_excerpt($post_id),
            'thumbnail' => \get_the_post_thumbnail_url($post_id, 'medium'),
            'author' => \get_the_author_meta('display_name', \get_post_field('post_author', $post_id)),
            'date' => \get_the_date('Y-m-d', $post_id),
            'permalink' => \get_permalink($post_id),
            'download_count' => (int)\get_post_meta($post_id, 'download_count', true),
            'like_count' => (int)\get_post_meta($post_id, 'like_count', true),
            'view_count' => (int)\get_post_meta($post_id, 'views', true),
            'material_type' => \get_post_meta($post_id, 'material_type', true),
            'material_format' => \get_post_meta($post_id, 'material_format', true),
            'file_size' => $this->format_file_size(\get_post_meta($post_id, 'file_size', true)),
        ];
    }
    
    /**
     * 检查下载权限
     */
    private function can_download($post_id) {
        // 如果用户已登录，允许下载
        if (\is_user_logged_in()) {
            return true;
        }
        
        // 检查是否为免费素材
        $is_free = \get_post_meta($post_id, 'is_free', true);
        if ($is_free) {
            return true;
        }
        
        // 其他权限检查逻辑
        return false;
    }
    
    /**
     * 记录下载日志
     */
    private function log_download($post_id) {
        $user_id = \get_current_user_id();
        $ip = $this->get_client_ip();
        
        $log_data = [
            'post_id' => $post_id,
            'user_id' => $user_id,
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'download_time' => \current_time('mysql')
        ];
        
        // 这里可以保存到自定义表或使用其他日志系统
        \update_post_meta($post_id, 'last_download', $log_data);
    }
    
    /**
     * 获取客户端IP
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * 格式化文件大小
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    
    /**
     * 清除素材相关缓存
     */
    private function clear_material_cache() {
        CacheManager::delete_by_pattern('filtered_materials_*');
        CacheManager::delete_by_pattern('popular_materials_*');
        CacheManager::delete('material_categories');
    }
    
    /**
     * 保存素材时的操作
     */
    public function on_save_material($post_id) {
        if (\get_post_type($post_id) !== 'show') {
            return;
        }
        
        // 清除相关缓存
        $this->clear_material_cache();
        CacheManager::delete('material_stats_' . $post_id);
    }
    
    /**
     * 删除素材时的操作
     */
    public function on_delete_material($post_id) {
        if (\get_post_type($post_id) !== 'show') {
            return;
        }
        
        // 删除关联文件
        $file_url = \get_post_meta($post_id, 'material_file', true);
        if ($file_url) {
            $file_path = str_replace(\wp_upload_dir()['baseurl'], \wp_upload_dir()['basedir'], $file_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // 清除缓存
        $this->clear_material_cache();
        CacheManager::delete('material_stats_' . $post_id);
    }
    
    /**
     * 添加素材元框
     */
    public function add_material_meta_boxes() {
        \add_meta_box(
            'material_details',
            '素材详情',
            [$this, 'render_material_meta_box'],
            'show',
            'normal',
            'high'
        );
    }
    
    /**
     * 渲染素材元框
     */
    public function render_material_meta_box($post) {
        \wp_nonce_field('save_material_meta', 'material_meta_nonce');
        
        $material_type = \get_post_meta($post->ID, 'material_type', true);
        $material_format = \get_post_meta($post->ID, 'material_format', true);
        $material_usage = \get_post_meta($post->ID, 'material_usage', true);
        $material_industry = \get_post_meta($post->ID, 'material_industry', true);
        $is_free = \get_post_meta($post->ID, 'is_free', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="material_type">素材类型</label></th>
                <td>
                    <select name="material_type" id="material_type">
                        <option value="">请选择</option>
                        <option value="图片" <?php selected($material_type, '图片'); ?>>图片</option>
                        <option value="视频" <?php selected($material_type, '视频'); ?>>视频</option>
                        <option value="音频" <?php selected($material_type, '音频'); ?>>音频</option>
                        <option value="文档" <?php selected($material_type, '文档'); ?>>文档</option>
                        <option value="字体" <?php selected($material_type, '字体'); ?>>字体</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="material_format">文件格式</label></th>
                <td><input type="text" name="material_format" id="material_format" value="<?php echo esc_attr($material_format); ?>" /></td>
            </tr>
            <tr>
                <th><label for="material_usage">使用场景</label></th>
                <td><input type="text" name="material_usage" id="material_usage" value="<?php echo esc_attr($material_usage); ?>" /></td>
            </tr>
            <tr>
                <th><label for="material_industry">适用行业</label></th>
                <td><input type="text" name="material_industry" id="material_industry" value="<?php echo esc_attr($material_industry); ?>" /></td>
            </tr>
            <tr>
                <th><label for="is_free">免费素材</label></th>
                <td><input type="checkbox" name="is_free" id="is_free" value="1" <?php checked($is_free, '1'); ?> /> 是</td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * 保存素材元数据
     */
    public function save_material_meta($post_id) {
        if (!isset($_POST['material_meta_nonce']) || !\wp_verify_nonce($_POST['material_meta_nonce'], 'save_material_meta')) {
            return;
        }
        
        if (\get_post_type($post_id) !== 'show') {
            return;
        }
        
        $fields = ['material_type', 'material_format', 'material_usage', 'material_industry'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                \update_post_meta($post_id, $field, \sanitize_text_field($_POST[$field]));
            }
        }
        
        \update_post_meta($post_id, 'is_free', isset($_POST['is_free']) ? '1' : '0');
    }
    
    /**
     * 添加下载按钮到内容
     */
    public function add_download_button($content) {
        if (\is_single() && \get_post_type() === 'show') {
            $post_id = \get_the_ID();
            $download_count = \get_post_meta($post_id, 'download_count', true);
            $like_count = \get_post_meta($post_id, 'like_count', true);
            
            $button_html = '<div class="material-actions">';
            $button_html .= '<button class="btn btn-primary download-btn" data-post-id="' . $post_id . '">下载素材 (' . $download_count . ')</button>';
            $button_html .= '<button class="btn btn-outline-danger like-btn" data-post-id="' . $post_id . '">点赞 (' . $like_count . ')</button>';
            $button_html .= '</div>';
            
            $content .= $button_html;
        }
        
        return $content;
    }
    
    /**
     * 添加结构化数据
     */
    public function add_material_schema() {
        if (\is_single() && \get_post_type() === 'show') {
            $post_id = \get_the_ID();
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'CreativeWork',
                'name' => \get_the_title(),
                'description' => \get_the_excerpt(),
                'author' => [
                    '@type' => 'Person',
                    'name' => \get_the_author()
                ],
                'datePublished' => \get_the_date('c'),
                'url' => \get_permalink(),
                'image' => \get_the_post_thumbnail_url($post_id, 'large')
            ];
            
            echo '<script type="application/ld+json">' . json_encode($schema) . '</script>';
        }
    }
}

// 初始化素材管理器
MaterialManager::getInstance();