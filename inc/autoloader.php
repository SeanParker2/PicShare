<?php
/**
 * PicShare主题自动加载器
 * 
 * 实现PSR-4自动加载标准
 */

class PicShareAutoloader {
    /**
     * 命名空间前缀到目录的映射
     */
    private static $prefixes = [];
    
    /**
     * 注册自动加载器
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'loadClass']);
        
        // 注册命名空间
        self::addNamespace('PicShare\\', \get_template_directory() . '/inc/');
    }
    
    /**
     * 添加命名空间
     * 
     * @param string $prefix 命名空间前缀
     * @param string $base_dir 基础目录
     * @param bool $prepend 是否前置
     */
    public static function addNamespace($prefix, $base_dir, $prepend = false) {
        // 规范化命名空间前缀
        $prefix = trim($prefix, '\\') . '\\';
        
        // 规范化基础目录
        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . '/';
        
        // 初始化命名空间前缀数组
        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }
        
        // 保留或前置基础目录
        if ($prepend) {
            array_unshift(self::$prefixes[$prefix], $base_dir);
        } else {
            array_push(self::$prefixes[$prefix], $base_dir);
        }
    }
    
    /**
     * 加载类文件
     * 
     * @param string $class 完全限定类名
     * @return mixed 成功时返回映射文件名，失败时返回false
     */
    public static function loadClass($class) {
        // 当前命名空间前缀
        $prefix = $class;
        
        // 向后遍历完全限定类名中的命名空间名称，寻找映射文件名
        while (false !== $pos = strrpos($prefix, '\\')) {
            // 保留命名空间前缀中的尾部分隔符
            $prefix = substr($class, 0, $pos + 1);
            
            // 其余的是相对类名
            $relative_class = substr($class, $pos + 1);
            
            // 尝试为前缀和相对类加载映射文件
            $mapped_file = self::loadMappedFile($prefix, $relative_class);
            if ($mapped_file) {
                return $mapped_file;
            }
            
            // 删除命名空间前缀的尾部分隔符，以便下一次strrpos()迭代
            $prefix = rtrim($prefix, '\\');
        }
        
        // 找不到映射文件
        return false;
    }
    
    /**
     * 为命名空间前缀和相对类加载映射文件
     * 
     * @param string $prefix 命名空间前缀
     * @param string $relative_class 相对类名
     * @return mixed 成功时返回映射文件名，失败时返回false
     */
    protected static function loadMappedFile($prefix, $relative_class) {
        // 命名空间前缀是否有任何基础目录？
        if (!isset(self::$prefixes[$prefix])) {
            return false;
        }
        
        // 遍历命名空间前缀的基础目录
        foreach (self::$prefixes[$prefix] as $base_dir) {
            // 将命名空间前缀替换为基础目录，
            // 将命名空间分隔符替换为目录分隔符，
            // 并在相对类名后追加.php
            $file = $base_dir
                  . str_replace('\\', '/', $relative_class)
                  . '.php';
            
            // 如果映射文件存在，则加载它
            if (self::requireFile($file)) {
                return $file;
            }
        }
        
        // 找不到映射文件
        return false;
    }
    
    /**
     * 如果文件存在，则从文件系统加载它
     * 
     * @param string $file 要加载的文件
     * @return bool 如果文件存在则为true，否则为false
     */
    protected static function requireFile($file) {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        
        return false;
    }
}

// 注册自动加载器
PicShareAutoloader::register();