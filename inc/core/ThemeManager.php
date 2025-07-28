<?php
/**
 * PicShare主题管理器
 * 
 * 负责主题的核心功能管理和初始化
 */

namespace PicShare\Core;

use PicShare\Config\Config;
use PicShare\Container\Container;
use PicShare\Cache\CacheManager;
use PicShare\Database\DatabaseOptimizer;

class ThemeManager {
    
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
     * 初始化主题
     */
    public function init() {
        // 添加主题支持
        \add_action('after_setup_theme', [$this, 'theme_setup']);
        
        // 初始化钩子
        \add_action('init', [$this, 'init_hooks']);
        
        // 管理员初始化
        \add_action('admin_init', [$this, 'admin_init']);
        
        // 激活主题时的操作
        \add_action('after_switch_theme', [$this, 'theme_activation']);
    }
    
    /**
     * 主题设置
     */
    public function theme_setup() {
        // 添加主题支持
        \add_theme_support('post-thumbnails');
        \add_theme_support('custom-logo');
        \add_theme_support('custom-header');
        \add_theme_support('custom-background');
        \add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
        ]);
        
        // 设置图片尺寸
        \set_post_thumbnail_size(300, 200, true);
        \add_image_size('PicShare-large', 800, 600, true);
        \add_image_size('PicShare-medium', 400, 300, true);
        \add_image_size('PicShare-small', 150, 150, true);
        
        // 加载文本域
        \load_theme_textdomain('PicShare', \get_template_directory() . '/languages');
    }
    
    /**
     * 初始化钩子
     */
    public function init_hooks() {
        // 移除不需要的WordPress功能
        $this->remove_unwanted_features();
        
        // 优化WordPress
        $this->optimize_wordpress();
        
        // 注册自定义文章类型和分类法
        $this->register_custom_post_types();
        $this->register_custom_taxonomies();
    }
    
    /**
     * 管理员初始化
     */
    public function admin_init() {
        // 移除不需要的管理员功能
        $this->remove_admin_features();
        
        // 添加自定义管理员样式
        \add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
    }
    
    /**
     * 主题激活时的操作
     */
    public function theme_activation() {
        // 创建数据库索引
        $db_optimizer = Container::resolve('db_optimizer');
        if ($db_optimizer) {
            $db_optimizer->create_indexes();
        }
        
        // 刷新重写规则
        \flush_rewrite_rules();
        
        // 设置默认配置
        $this->set_default_options();
    }
    
    /**
     * 移除不需要的WordPress功能
     */
    private function remove_unwanted_features() {
        // 移除WordPress版本信息
        \remove_action('wp_head', 'wp_generator');
        
        // 移除RSD链接
        \remove_action('wp_head', 'rsd_link');
        
        // 移除wlwmanifest链接
        \remove_action('wp_head', 'wlwmanifest_link');
        
        // 移除短链接
        \remove_action('wp_head', 'wp_shortlink_wp_head');
        
        // 移除feed链接
        \remove_action('wp_head', 'feed_links', 2);
        \remove_action('wp_head', 'feed_links_extra', 3);
        
        // 移除emoji支持
        \remove_action('wp_head', 'print_emoji_detection_script', 7);
        \remove_action('wp_print_styles', 'print_emoji_styles');
    }
    
    /**
     * 优化WordPress
     */
    private function optimize_wordpress() {
        // 禁用XML-RPC
        \add_filter('xmlrpc_enabled', '__return_false');
        
        // 移除REST API链接
        \remove_action('wp_head', 'rest_output_link_wp_head');
        
        // 禁用文件编辑
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
        
        // 限制修订版本数量
        if (!defined('WP_POST_REVISIONS')) {
            define('WP_POST_REVISIONS', 3);
        }
    }
    
    /**
     * 注册自定义文章类型
     */
    private function register_custom_post_types() {
        // 素材文章类型
        \register_post_type('show', [
            'labels' => [
                'name' => '素材',
                'singular_name' => '素材',
                'add_new' => '添加素材',
                'add_new_item' => '添加新素材',
                'edit_item' => '编辑素材',
                'new_item' => '新素材',
                'view_item' => '查看素材',
                'search_items' => '搜索素材',
                'not_found' => '未找到素材',
                'not_found_in_trash' => '回收站中未找到素材',
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'comments', 'custom-fields'],
            'menu_icon' => 'dashicons-format-gallery',
            'rewrite' => ['slug' => 'show'],
        ]);
        
        // 社区文章类型
        \register_post_type('forum', [
            'labels' => [
                'name' => '社区',
                'singular_name' => '社区文章',
                'add_new' => '添加文章',
                'add_new_item' => '添加新文章',
                'edit_item' => '编辑文章',
                'new_item' => '新文章',
                'view_item' => '查看文章',
                'search_items' => '搜索文章',
                'not_found' => '未找到文章',
                'not_found_in_trash' => '回收站中未找到文章',
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'comments', 'custom-fields'],
            'menu_icon' => 'dashicons-groups',
            'rewrite' => ['slug' => 'forum'],
        ]);
    }
    
    /**
     * 注册自定义分类法
     */
    private function register_custom_taxonomies() {
        // 素材分类
        \register_taxonomy('show_cat', 'show', [
            'labels' => [
                'name' => '素材分类',
                'singular_name' => '素材分类',
                'search_items' => '搜索分类',
                'all_items' => '所有分类',
                'parent_item' => '父级分类',
                'parent_item_colon' => '父级分类:',
                'edit_item' => '编辑分类',
                'update_item' => '更新分类',
                'add_new_item' => '添加新分类',
                'new_item_name' => '新分类名称',
                'menu_name' => '分类',
            ],
            'hierarchical' => true,
            'public' => true,
            'rewrite' => ['slug' => 'show-category'],
        ]);
        
        // 素材标签
        \register_taxonomy('show_tag', 'show', [
            'labels' => [
                'name' => '素材标签',
                'singular_name' => '素材标签',
                'search_items' => '搜索标签',
                'popular_items' => '热门标签',
                'all_items' => '所有标签',
                'edit_item' => '编辑标签',
                'update_item' => '更新标签',
                'add_new_item' => '添加新标签',
                'new_item_name' => '新标签名称',
                'menu_name' => '标签',
            ],
            'hierarchical' => false,
            'public' => true,
            'rewrite' => ['slug' => 'show-tag'],
        ]);
    }
    
    /**
     * 移除管理员功能
     */
    private function remove_admin_features() {
        // 移除WordPress更新通知（非管理员）
        if (!\current_user_can('update_core')) {
            \add_action('init', function() {
                \remove_action('init', 'wp_version_check');
            }, 2);
            \add_filter('pre_option_update_core', '__return_null');
        }
    }
    
    /**
     * 管理员脚本
     */
    public function admin_scripts() {
        \wp_enqueue_style('PicShare-admin', \get_template_directory_uri() . '/assets/css/admin.css', [], PicShare_VERSION);
        \wp_enqueue_script('PicShare-admin', \get_template_directory_uri() . '/assets/js/admin.js', ['jquery'], PicShare_VERSION, true);
    }
    
    /**
     * 设置默认选项
     */
    private function set_default_options() {
        $defaults = [
            'site.name' => \get_bloginfo('name'),
            'site.description' => \get_bloginfo('description'),
            'cache.enable' => false,
            'cache.expire' => 3600,
            'seo.enable' => true,
            'material.per_page' => 12,
            'user.registration' => true,
        ];
        
        foreach ($defaults as $key => $value) {
            if (!Config::has($key)) {
                Config::set($key, $value);
            }
        }
    }
}

// 初始化主题管理器
ThemeManager::getInstance();