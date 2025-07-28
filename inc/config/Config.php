<?php
/**
 * PicCool主题配置管理类
 * 
 * 集中管理主题的所有配置项
 */

namespace PicCool\Config;

class Config {
    /**
     * 配置项存储
     */
    private static $config = [];
    
    /**
     * 默认配置
     */
    private static $defaults = [
        // 站点基本信息
        'site' => [
            'name' => 'PicCool',
            'description' => '专业的素材资源分享平台',
            'keywords' => '素材,图片,设计,资源',
            'logo' => '',
            'favicon' => '',
        ],
        
        // SEO配置
        'seo' => [
            'enable' => true,
            'title_separator' => ' - ',
            'home_title' => '',
            'home_keywords' => '',
            'home_description' => '',
            'og_image' => '',
            'enable_sitemap' => true,
        ],
        
        // 素材配置
        'material' => [
            'per_page' => 12,
            'max_upload_size' => 10485760, // 10MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip'],
            'thumbnail_size' => [300, 300],
            'default_thumbnail' => '',
            'download_need_login' => true,
            'enable_watermark' => false,
            'watermark_position' => 'bottom-right',
        ],
        
        // 社区配置
        'forum' => [
            'per_page' => 10,
            'enable_comments' => true,
            'enable_voting' => true,
            'enable_tags' => true,
        ],
        
        // 用户配置
        'user' => [
            'enable_registration' => true,
            'require_email_verification' => true,
            'default_role' => 'subscriber',
            'default_avatar' => '',
            'enable_social_login' => false,
            'upload_limit' => 5, // 每日上传限制
            'download_limit' => 20, // 每日下载限制
        ],
        
        // 订单配置
        'order' => [
            'currency' => 'CNY',
            'currency_symbol' => '¥',
            'enable_payment' => false,
        ],
        
        // 缓存配置
        'cache' => [
            'enable' => true,
            'driver' => 'wordpress', // wordpress, redis, memcached
            'expire' => 3600,
            'prefix' => 'piccool_',
        ],
        
        // API配置
        'api' => [
            'enable' => true,
            'rate_limit' => 100, // 每小时请求限制
            'require_auth' => false,
        ],
        
        // 安全配置
        'security' => [
            'enable_captcha' => true,
            'max_login_attempts' => 5,
            'lockout_duration' => 1800, // 30分钟
            'enable_two_factor' => false,
        ],
        
        // 性能配置
        'performance' => [
            'enable_gzip' => true,
            'enable_minify' => true,
            'enable_lazy_load' => true,
            'cdn_url' => '',
        ],
        
        // 社交配置
        'social' => [
            'weibo' => '',
            'wechat' => '',
            'qq' => '',
            'email' => '',
        ],
    ];
    
    /**
     * 初始化配置
     */
    public static function init() {
        // 加载默认配置
        self::$config = self::$defaults;
        
        // 加载数据库中的配置
        self::loadFromDatabase();
        
        // 应用过滤器，允许其他代码修改配置
        self::$config = \apply_filters('piccool_config', self::$config);
    }
    
    /**
     * 从数据库加载配置
     */
    private static function loadFromDatabase() {
        // 从WordPress选项表加载配置
        $db_config = \get_option('piccool_config', []);
        
        if (is_array($db_config)) {
            // 递归合并配置，保留默认值
            self::$config = self::arrayMergeRecursive(self::$config, $db_config);
        }
    }
    
    /**
     * 递归合并数组
     */
    private static function arrayMergeRecursive($array1, $array2) {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::arrayMergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        
        return $array1;
    }
    
    /**
     * 获取配置项
     * 
     * @param string $key 配置键名，使用点号分隔层级，如 'site.name'
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public static function get($key, $default = null) {
        $keys = explode('.', $key);
        $config = self::$config;
        
        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                return $default;
            }
            
            $config = $config[$key];
        }
        
        return \apply_filters('piccool_config_get', $config, $key);
    }
    
    /**
     * 设置配置项
     * 
     * @param string $key 配置键名，使用点号分隔层级，如 'site.name'
     * @param mixed $value 配置值
     * @param bool $save 是否保存到数据库
     * @return bool 是否设置成功
     */
    public static function set($key, $value, $save = true) {
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $config[$key] = $value;
            } else {
                if (!isset($config[$key]) || !is_array($config[$key])) {
                    $config[$key] = [];
                }
                
                $config = &$config[$key];
            }
        }
        
        if ($save) {
            return self::save();
        }
        
        return true;
    }
    
    /**
     * 保存配置到数据库
     * 
     * @return bool 是否保存成功
     */
    public static function save() {
        return \update_option('piccool_config', self::$config);
    }
    
    /**
     * 重置配置为默认值
     * 
     * @param bool $save 是否保存到数据库
     * @return bool 是否重置成功
     */
    public static function reset($save = true) {
        self::$config = self::$defaults;
        
        if ($save) {
            return self::save();
        }
        
        return true;
    }
    
    /**
     * 检查配置键是否存在
     * 
     * @param string $key 配置键名
     * @return bool
     */
    public static function has($key) {
        $keys = explode('.', $key);
        $config = self::$config;
        
        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                return false;
            }
            $config = $config[$key];
        }
        
        return true;
    }
    
    /**
     * 删除配置项
     * 
     * @param string $key 配置键名
     * @param bool $save 是否保存到数据库
     */
    public static function delete($key, $save = true) {
        $keys = explode('.', $key);
        $config = &self::$config;
        
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($config[$keys[$i]]) || !is_array($config[$keys[$i]])) {
                return;
            }
            $config = &$config[$keys[$i]];
        }
        
        $lastKey = end($keys);
        if (isset($config[$lastKey])) {
            unset($config[$lastKey]);
            if ($save) {
                self::save();
            }
        }
    }
    
    /**
     * 获取所有配置
     * 
     * @return array
     */
    public static function all() {
        return self::$config;
    }
    
    /**
     * 批量设置配置
     * 
     * @param array $configs 配置数组
     * @param bool $save 是否保存到数据库
     */
    public static function setMany(array $configs, $save = true) {
        foreach ($configs as $key => $value) {
            self::set($key, $value, false);
        }
        
        if ($save) {
            self::save();
        }
    }
    
    /**
     * 验证配置值
     * 
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return bool
     */
    public static function validate($key, $value) {
        switch ($key) {
            case 'material.per_page':
                return is_int($value) && $value > 0 && $value <= 100;
                
            case 'material.max_upload_size':
                return is_int($value) && $value > 0;
                
            case 'cache.expire':
                return is_int($value) && $value >= 0;
                
            case 'user.upload_limit':
            case 'user.download_limit':
                return is_int($value) && $value >= 0;
                
            case 'api.rate_limit':
                return is_int($value) && $value > 0;
                
            case 'security.max_login_attempts':
                return is_int($value) && $value > 0;
                
            case 'security.lockout_duration':
                return is_int($value) && $value >= 0;
                
            default:
                return true;
        }
    }
    
    /**
     * 获取环境相关的配置
     * 
     * @param string $env 环境名称 (development, staging, production)
     * @return array
     */
    public static function getEnvironmentConfig($env = 'production') {
        $configs = [
            'development' => [
                'cache.enable' => false,
                'performance.enable_minify' => false,
                'security.enable_captcha' => false,
            ],
            'staging' => [
                'cache.enable' => true,
                'cache.expire' => 1800,
                'performance.enable_minify' => true,
            ],
            'production' => [
                'cache.enable' => true,
                'cache.expire' => 3600,
                'performance.enable_minify' => true,
                'security.enable_captcha' => true,
            ],
        ];
        
        return isset($configs[$env]) ? $configs[$env] : $configs['production'];
    }
    
    /**
     * 应用环境配置
     * 
     * @param string $env 环境名称
     */
    public static function applyEnvironment($env) {
        $envConfig = self::getEnvironmentConfig($env);
        self::setMany($envConfig);
    }
    
    /**
     * 获取配置的JSON格式
     * 
     * @param string|null $key 配置键名，为null时返回所有配置
     * @return string
     */
    public static function toJson($key = null) {
        $data = $key ? self::get($key) : self::all();
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * 从JSON导入配置
     * 
     * @param string $json JSON字符串
     * @param string|null $key 要导入到的配置键，为null时导入到根级别
     * @return bool
     */
    public static function fromJson($json, $key = null) {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        if ($key) {
            self::set($key, $data);
        } else {
            self::$config = self::arrayMergeRecursive(self::$config, $data);
            self::save();
        }
        
        return true;
    }
}

// 初始化配置
Config::init();