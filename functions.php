<?php
/**
 * PicShare主题 - 重构版本
 * 
 * 采用模块化架构，使用命名空间和依赖注入
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义主题版本
define('PicShare_VERSION', '2.0.0');
define('PicShare_PATH', \get_template_directory());
define('PicShare_URL', \get_template_directory_uri());

// 加载自动加载器
require_once PicShare_PATH . '/inc/autoloader.php';

// 加载配置管理
require_once PicShare_PATH . '/inc/config/Config.php';

// 加载依赖注入容器
require_once PicShare_PATH . '/inc/container/Container.php';

// 加载缓存管理
require_once PicShare_PATH . '/inc/cache/CacheManager.php';

// 加载数据库优化
require_once PicShare_PATH . '/inc/database/DatabaseOptimizer.php';

// 使用命名空间
use PicShare\Config\Config;
use PicShare\Container\Container;
use PicShare\Cache\CacheManager;
use PicShare\Database\DatabaseOptimizer;

/**
 * 主题初始化
 */
function PicShare_init() {
    // 注册服务到容器
    Container::singleton('config', function() {
        return new Config();
    });
    
    Container::singleton('cache', function() {
        return new CacheManager();
    });
    
    Container::singleton('db_optimizer', function() {
        return new DatabaseOptimizer();
    });
}
add_action('after_setup_theme', 'PicShare_init');

/**
 * 加载CSS和JS资源
 */
function PicShare_enqueue_scripts() {
    $version = Config::get('cache.enable', false) ? PicShare_VERSION : time();
    
    // CSS文件
    \wp_enqueue_style('bootstrap', PicShare_URL . '/assets/css/bootstrap.min.css', [], $version);
    \wp_enqueue_style('owl-carousel', PicShare_URL . '/assets/css/owl.carousel.min.css', [], $version);
    \wp_enqueue_style('animate', PicShare_URL . '/assets/css/animate.min.css', [], $version);
    \wp_enqueue_style('bootstrap-icons', PicShare_URL . '/assets/bifont/bootstrap-icons.css', [], $version);
    \wp_enqueue_style('PicShare-style', PicShare_URL . '/style.css', [], $version);
    
    // 移除默认jQuery
    \wp_deregister_script('jquery');
    
    // JS文件
    \wp_enqueue_script('jquery', PicShare_URL . '/assets/js/jquery.min.js', [], $version, false);
    \wp_enqueue_script('bootstrap', PicShare_URL . '/assets/js/bootstrap.min.js', ['jquery'], $version, true);
    \wp_enqueue_script('owl-carousel', PicShare_URL . '/assets/js/owl.carousel.min.js', ['jquery'], $version, true);
    \wp_enqueue_script('PicShare-main', PicShare_URL . '/assets/js/js.js', ['jquery'], $version, true);
    
    // 本地化脚本
    \wp_localize_script('PicShare-main', 'PicShare_ajax', [
        'ajax_url' => \admin_url('admin-ajax.php'),
        'nonce' => \wp_create_nonce('PicShare_nonce'),
    ]);
}
\add_action('wp_enqueue_scripts', 'PicShare_enqueue_scripts');

// 加载传统模块（保持向后兼容）
require_once PicShare_PATH . '/inc/norm.php';
require_once PicShare_PATH . '/inc/comment/main.php';	//评论核心
require_once PicShare_PATH . '/inc/type/show.php';//自定义分类法show
require_once PicShare_PATH . '/inc/type/forum.php';//自定义分类法forum
require_once PicShare_PATH . '/inc/query_show.php'; //素材筛选核心
require_once PicShare_PATH . '/inc/query_field.php'; //素材筛选字段
require_once PicShare_PATH . '/pages/user/inc/setup-functions.php'; //用户中心 - 资料


/**
 * 注册导航菜单
 */
function PicShare_register_menus() {
    \register_nav_menus([
        'main' => \__('主菜单导航', 'PicShare'),
        'mob' => \__('手机导航', 'PicShare'),
        'foot1' => \__('底部菜单1', 'PicShare'),
        'foot2' => \__('底部菜单2', 'PicShare'),
        'foot3' => \__('底部菜单3', 'PicShare'),
        'foot4' => \__('底部菜单4', 'PicShare'),
        'hot_s' => \__('热门搜索', 'PicShare'),
    ]);
}
\add_action('after_setup_theme', 'PicShare_register_menus');


/**
 * 文章访问计数功能
 */
class PicShare_Post_Views {
    
    public static function init() {
        \add_action('wp_head', [__CLASS__, 'record_visitors']);
    }
    
    public static function record_visitors() {
        if (\is_singular()) {
            global $post;
            $post_ID = $post->ID;
            if ($post_ID) {
                $post_views = (int)\get_post_meta($post_ID, 'views', true);
                if (!\update_post_meta($post_ID, 'views', ($post_views + 1))) {
                    \add_post_meta($post_ID, 'views', 1, true);
                }
            }
        }
    }
    
    public static function get_post_views($before = '(点击 ', $after = ' 次)', $echo = 1) {
        global $post;
        $post_ID = $post->ID;
        $views = (int)\get_post_meta($post_ID, 'views', true);
        
        if ($echo) {
            echo $before . \number_format($views) . $after;
        } else {
            return $views;
        }
    }
}

// 初始化文章访问计数
PicShare_Post_Views::init();

// 保持向后兼容的函数
function post_views($before = '(点击 ', $after = ' 次)', $echo = 1) {
    return PicShare_Post_Views::get_post_views($before, $after, $echo);
}


/**
 * 面包屑导航功能
 * 基于 Bootstrap 4 样式
 */
class PicShare_Breadcrumbs {
    
    public static function get_breadcrumbs() {
        global $wp_query;
        
        // 首页不显示面包屑
        if (\is_home() && \is_front_page()) {
            return;
        }
        
        echo '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        echo '<li class="breadcrumb-item breadcrumb-home"><a href="' . \get_option('home') . '">首页</a></li>';
        
        if (\is_category()) {
            self::render_category_breadcrumb();
        } elseif (\is_tag()) {
            self::render_tag_breadcrumb();
        } elseif (\is_author()) {
            self::render_author_breadcrumb();
        } elseif (\is_search()) {
            self::render_search_breadcrumb();
        } elseif (\is_404()) {
            self::render_404_breadcrumb();
        } elseif (\is_tax()) {
            self::render_taxonomy_breadcrumb();
        } elseif (\is_single()) {
            self::render_single_breadcrumb();
        } elseif (\is_page()) {
            self::render_page_breadcrumb();
        }
        
        echo '</ol></nav>';
    }
    
    private static function render_category_breadcrumb() {
        $cat_title = \single_cat_title('', false);
        $cat = \get_cat_ID($cat_title);
        echo '<li class="breadcrumb-item">' . \get_category_parents($cat, true, '<em>/</em>') . '</li>';
    }
    
    private static function render_tag_breadcrumb() {
        echo '<li class="breadcrumb-item active" aria-current="page">' . \single_tag_title('', false) . '</li>';
    }
    
    private static function render_author_breadcrumb() {
        global $author;
        $userdata = \get_userdata($author);
        echo '<li class="breadcrumb-item active" aria-current="page">' . $userdata->display_name . '</li>';
    }
    
    private static function render_search_breadcrumb() {
        echo '<li class="breadcrumb-item active" aria-current="page">搜索词 [ ' . \get_search_query() . ' ] 的结果页</li>';
    }
    
    private static function render_404_breadcrumb() {
        echo '<li class="breadcrumb-item active" aria-current="page">404 Not Found</li>';
    }
    
    private static function render_taxonomy_breadcrumb() {
        echo '<li class="breadcrumb-item active" aria-current="page">' . \single_tag_title('', false) . '</li>';
    }
    
    private static function render_single_breadcrumb() {
        $category = \get_the_category();
        if (!empty($category)) {
            $category_id = \get_cat_ID($category[0]->cat_name);
            echo '<li class="breadcrumb-item">' . \get_category_parents($category_id, true, '<em>/</em>') . '</li>';
        }
        echo '<li class="breadcrumb-item active" aria-current="page">' . \the_title('', '', false) . '</li>';
    }
    
    private static function render_page_breadcrumb() {
        echo '<li class="breadcrumb-item active" aria-current="page">' . \the_title('', '', false) . '</li>';
    }
}

// 保持向后兼容的函数
function get_breadcrumbs() {
    PicShare_Breadcrumbs::get_breadcrumbs();
}





/**
 * Gravatar头像加速 - 使用中国服务器
 */
class PicShare_Avatar {
    
    public static function init() {
        \add_filter('get_avatar', [__CLASS__, 'get_ssl_avatar']);
    }
    
    public static function get_ssl_avatar($avatar) {
        $avatar = \str_replace([
            '//gravatar.com/',
            '//secure.gravatar.com/',
            '//www.gravatar.com/',
            '//0.gravatar.com/',
            '//1.gravatar.com/',
            '//2.gravatar.com/',
            '//cn.gravatar.com/'
        ], '//cravatar.cn/', $avatar);
        return $avatar;
    }
}

// 初始化头像加速
PicShare_Avatar::init();


/**
 * 文章点赞功能
 */
class PicShare_Post_Like {
    
    public static function init() {
        \add_action('wp_ajax_nopriv_specs_zan', [__CLASS__, 'handle_like']);
        \add_action('wp_ajax_specs_zan', [__CLASS__, 'handle_like']);
    }
    
    public static function handle_like() {
        // 验证nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'PicShare_nonce')) {
            \wp_die('Security check failed');
        }
        
        $id = \intval($_POST['um_id'] ?? 0);
        $action = \sanitize_text_field($_POST['um_action'] ?? '');
        
        if ($action === 'ding' && $id > 0) {
            $specs_raters = (int)\get_post_meta($id, 'specs_zan', true);
            $expire = \time() + 99999999;
            $domain = ($_SERVER['HTTP_HOST'] !== 'localhost') ? $_SERVER['HTTP_HOST'] : false;
            
            \setcookie('specs_zan_' . $id, $id, $expire, '/', $domain, false);
            
            if (!$specs_raters || !\is_numeric($specs_raters)) {
                \update_post_meta($id, 'specs_zan', 1);
            } else {
                \update_post_meta($id, 'specs_zan', ($specs_raters + 1));
            }
            
            echo \get_post_meta($id, 'specs_zan', true);
        }
        
        \wp_die();
    }
    
    public static function get_post_likes($post_id = null) {
        if (!$post_id) {
            global $post;
            $post_id = $post->ID;
        }
        return (int)\get_post_meta($post_id, 'specs_zan', true);
    }
}

// 初始化点赞功能
PicShare_Post_Like::init();

/**
 * 媒体上传支持
 */
class PicShare_Media_Support {
    
    public static function init() {
        \add_filter('upload_mimes', [__CLASS__, 'add_mime_types']);
        \add_filter('wp_check_filetype_and_ext', [__CLASS__, 'fix_svg_mime_type'], 10, 4);
    }
    
    public static function add_mime_types($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['webp'] = 'image/webp';
        return $mimes;
    }
    
    public static function fix_svg_mime_type($data, $file, $filename, $mimes) {
        $filetype = \wp_check_filetype($filename, $mimes);
        
        if ($filetype['ext'] === 'svg') {
            $data['ext'] = 'svg';
            $data['type'] = 'image/svg+xml';
        }
        
        return $data;
    }
}

// 初始化媒体支持
PicShare_Media_Support::init();