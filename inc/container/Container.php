<?php
/**
 * PicCool主题依赖注入容器
 * 
 * 管理类的依赖关系，降低模块间耦合度
 */

namespace PicCool\Container;

class Container {
    /**
     * 服务绑定
     */
    private static $bindings = [];
    
    /**
     * 单例实例
     */
    private static $instances = [];
    
    /**
     * 绑定服务
     * 
     * @param string $abstract 抽象名称
     * @param mixed $concrete 具体实现
     * @param bool $singleton 是否为单例
     */
    public static function bind($abstract, $concrete = null, $singleton = false) {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        
        self::$bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
        ];
    }
    
    /**
     * 绑定单例服务
     * 
     * @param string $abstract 抽象名称
     * @param mixed $concrete 具体实现
     */
    public static function singleton($abstract, $concrete = null) {
        self::bind($abstract, $concrete, true);
    }
    
    /**
     * 绑定实例
     * 
     * @param string $abstract 抽象名称
     * @param mixed $instance 实例
     */
    public static function instance($abstract, $instance) {
        self::$instances[$abstract] = $instance;
    }
    
    /**
     * 解析服务
     * 
     * @param string $abstract 抽象名称
     * @param array $parameters 构造参数
     * @return mixed 服务实例
     */
    public static function make($abstract, $parameters = []) {
        // 如果已有实例，直接返回
        if (isset(self::$instances[$abstract])) {
            return self::$instances[$abstract];
        }
        
        // 获取绑定信息
        $binding = self::getBinding($abstract);
        
        // 创建实例
        $instance = self::build($binding['concrete'], $parameters);
        
        // 如果是单例，保存实例
        if ($binding['singleton']) {
            self::$instances[$abstract] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * 获取绑定信息
     * 
     * @param string $abstract 抽象名称
     * @return array 绑定信息
     */
    private static function getBinding($abstract) {
        if (isset(self::$bindings[$abstract])) {
            return self::$bindings[$abstract];
        }
        
        // 如果没有绑定，默认返回类名本身
        return [
            'concrete' => $abstract,
            'singleton' => false,
        ];
    }
    
    /**
     * 构建实例
     * 
     * @param mixed $concrete 具体实现
     * @param array $parameters 构造参数
     * @return mixed 实例
     */
    private static function build($concrete, $parameters = []) {
        // 如果是闭包，直接调用
        if ($concrete instanceof \Closure) {
            return $concrete(self::class, $parameters);
        }
        
        // 如果是字符串，使用反射创建实例
        if (is_string($concrete)) {
            return self::buildClass($concrete, $parameters);
        }
        
        // 其他情况直接返回
        return $concrete;
    }
    
    /**
     * 使用反射构建类实例
     * 
     * @param string $className 类名
     * @param array $parameters 构造参数
     * @return object 类实例
     */
    private static function buildClass($className, $parameters = []) {
        try {
            $reflector = new \ReflectionClass($className);
            
            // 如果类不能实例化，抛出异常
            if (!$reflector->isInstantiable()) {
                throw new \Exception("Class {$className} is not instantiable");
            }
            
            $constructor = $reflector->getConstructor();
            
            // 如果没有构造函数，直接创建实例
            if (is_null($constructor)) {
                return new $className;
            }
            
            // 解析构造函数依赖
            $dependencies = self::resolveDependencies($constructor->getParameters(), $parameters);
            
            return $reflector->newInstanceArgs($dependencies);
        } catch (\Exception $e) {
            throw new \Exception("Error building class {$className}: " . $e->getMessage());
        }
    }
    
    /**
     * 解析依赖
     * 
     * @param array $parameters 参数信息
     * @param array $primitives 原始参数
     * @return array 依赖数组
     */
    private static function resolveDependencies($parameters, $primitives = []) {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();
            
            // 如果参数有类型提示
            if ($dependency) {
                $dependencies[] = self::make($dependency->name);
            } elseif (array_key_exists($parameter->name, $primitives)) {
                // 如果提供了原始参数
                $dependencies[] = $primitives[$parameter->name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                // 如果有默认值
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve dependency {$parameter->name}");
            }
        }
        
        return $dependencies;
    }
    
    /**
     * 检查是否已绑定
     * 
     * @param string $abstract 抽象名称
     * @return bool 是否已绑定
     */
    public static function bound($abstract) {
        return isset(self::$bindings[$abstract]) || isset(self::$instances[$abstract]);
    }
    
    /**
     * 清除绑定
     * 
     * @param string $abstract 抽象名称
     */
    public static function forget($abstract) {
        unset(self::$bindings[$abstract], self::$instances[$abstract]);
    }
    
    /**
     * 清除所有绑定
     */
    public static function flush() {
        self::$bindings = [];
        self::$instances = [];
    }
}