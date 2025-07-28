# PicCool 主题 - 重构版本 2.0

一个功能完整、设计精美的WordPress素材资源分享平台主题，采用现代化架构重构。

## 🚀 主要改进

### 代码结构重构
- **模块化架构**: 采用PSR-4自动加载标准，使用命名空间组织代码
- **依赖注入**: 引入依赖注入容器，降低模块间耦合度
- **配置管理**: 统一的配置管理系统，集中管理所有配置项
- **面向对象**: 将原有的函数式代码重构为面向对象的类结构

### 数据库优化
- **索引优化**: 为自定义字段添加数据库索引，提升筛选查询性能
- **缓存策略**: 实现Redis/Memcached缓存，缓存热门素材和分类数据
- **查询优化**: 优化数据库查询，减少不必要的查询次数

### 性能提升
- **缓存系统**: 多层缓存策略，支持对象缓存、页面缓存
- **资源优化**: 优化CSS/JS加载，支持版本控制
- **图片优化**: 支持WebP格式，多种图片尺寸

### 安全增强
- **输入验证**: 严格的输入验证和数据清理
- **权限控制**: 完善的用户权限管理系统
- **安全防护**: 移除不必要的WordPress功能，减少攻击面

## 📁 项目结构

```
PicCool/
├── assets/                 # 静态资源
│   ├── css/               # 样式文件
│   ├── js/                # JavaScript文件
│   ├── img/               # 图片资源
│   └── bifont/            # 图标字体
├── inc/                   # 核心功能模块
│   ├── autoloader.php     # PSR-4自动加载器
│   ├── cache/             # 缓存管理
│   │   └── CacheManager.php
│   ├── config/            # 配置管理
│   │   └── Config.php
│   ├── container/         # 依赖注入容器
│   │   └── Container.php
│   ├── core/              # 核心功能
│   │   └── ThemeManager.php
│   ├── database/          # 数据库优化
│   │   └── DatabaseOptimizer.php
│   ├── material/          # 素材管理
│   │   └── MaterialManager.php
│   ├── user/              # 用户管理
│   │   └── UserManager.php
│   ├── comment/           # 评论系统
│   ├── meta/              # ACF字段管理
│   ├── query_show.php     # 素材筛选核心
│   ├── query_field.php    # 素材筛选字段
│   ├── norm.php           # 基础功能
│   └── type/              # 自定义文章类型
├── pages/                 # 页面模板
│   └── user/              # 用户中心页面
├── acf-json/              # ACF字段配置
├── functions.php          # 主题函数文件
├── style.css              # 主样式文件
└── *.php                  # 模板文件
```

## 🔧 核心功能模块

### 1. 配置管理 (Config)
- 统一管理主题配置项
- 支持点号分隔的键名访问
- 数据库持久化存储
- 默认值支持

### 2. 依赖注入容器 (Container)
- 服务绑定和解析
- 单例模式支持
- 自动依赖注入
- 接口绑定

### 3. 缓存管理 (CacheManager)
- 多种缓存后端支持 (Redis, Memcached, WordPress)
- 缓存标签和分组
- 自动缓存失效
- 热门数据缓存

### 4. 数据库优化 (DatabaseOptimizer)
- 自动创建索引
- 查询性能监控
- 数据库清理
- 统计信息收集

### 5. 素材管理 (MaterialManager)
- 素材上传和管理
- 高级筛选功能
- 下载统计
- 点赞系统

### 6. 用户管理 (UserManager)
- 用户注册和登录
- 个人资料管理
- 社交链接
- 用户统计

## 🎨 主要功能

### 素材系统
- **多格式支持**: 图片、视频、音频、文档、字体等
- **智能分类**: 按用途、行业、类型、格式分类
- **高级筛选**: 多维度筛选和排序
- **批量操作**: 批量上传、编辑、删除

### 用户系统
- **会员注册**: 邮箱验证、社交登录
- **个人中心**: 资料管理、作品管理、下载历史
- **权限管理**: 角色权限、VIP会员
- **积分系统**: 上传奖励、下载消耗

### 社区功能
- **论坛系统**: 分类讨论、话题管理
- **评论互动**: 嵌套评论、点赞回复
- **消息通知**: 站内信、邮件通知
- **用户关注**: 关注作者、收藏作品

## 🛠️ 技术栈

- **后端**: PHP 7.4+, WordPress 5.0+
- **前端**: Bootstrap 4, jQuery, Owl Carousel
- **缓存**: Redis, Memcached, WordPress Object Cache
- **数据库**: MySQL 5.7+
- **工具**: ACF Pro, PSR-4 Autoloader

## 📦 安装要求

- PHP 7.4 或更高版本
- WordPress 5.0 或更高版本
- MySQL 5.7 或更高版本
- 推荐: Redis 或 Memcached (可选)

## 🚀 安装步骤

1. **上传主题文件**
   ```bash
   # 将主题文件上传到 WordPress 主题目录
   wp-content/themes/piccool/
   ```

2. **激活主题**
   - 在 WordPress 后台 → 外观 → 主题 中激活 PicCool 主题

3. **配置数据库**
   - 主题激活时会自动创建必要的数据库索引
   - 如需手动优化，可在后台运行数据库优化工具

4. **配置缓存** (可选)
   ```php
   // 在 wp-config.php 中添加缓存配置
   define('WP_CACHE', true);
   define('WP_REDIS_HOST', 'localhost');
   define('WP_REDIS_PORT', 6379);
   ```

5. **导入示例数据** (可选)
   - 导入 ACF 字段配置
   - 设置菜单和小工具

## ⚙️ 配置选项

### 基本设置
```php
// 在主题自定义器或配置文件中设置
Config::set('site.name', '您的站点名称');
Config::set('site.description', '站点描述');
Config::set('cache.enable', true);
Config::set('material.per_page', 12);
```

### 缓存配置
```php
// 启用缓存
Config::set('cache.enable', true);
Config::set('cache.expire', 3600); // 1小时
Config::set('cache.driver', 'redis'); // redis, memcached, wordpress
```

### SEO配置
```php
Config::set('seo.enable', true);
Config::set('seo.title_separator', ' - ');
Config::set('seo.meta_description', '默认描述');
```

## 🔌 扩展开发

### 创建自定义模块
```php
<?php
namespace PicCool\Custom;

use PicCool\Container\Container;

class CustomModule {
    public function __construct() {
        $this->init();
    }
    
    public function init() {
        // 初始化代码
    }
}

// 注册到容器
Container::singleton('custom_module', function() {
    return new CustomModule();
});
```

### 添加自定义缓存
```php
// 使用缓存管理器
$cache = Container::resolve('cache');
$cache->set('my_key', $data, 3600);
$cached_data = $cache->get('my_key');
```

## 📊 性能优化建议

1. **启用缓存**: 配置 Redis 或 Memcached
2. **CDN加速**: 使用 CDN 加速静态资源
3. **图片优化**: 启用 WebP 格式支持
4. **数据库优化**: 定期清理和优化数据库
5. **代码优化**: 使用生产环境配置

## 🐛 常见问题

### Q: 主题激活后出现错误？
A: 检查 PHP 版本是否满足要求，确保服务器支持所需扩展。

### Q: 缓存不生效？
A: 检查缓存服务是否正常运行，配置是否正确。

### Q: 上传文件失败？
A: 检查文件权限和上传目录的写入权限。

### Q: 数据库查询慢？
A: 运行数据库优化工具，检查索引是否正确创建。

## 📝 更新日志

### v2.0.0 (2024-01-XX)
- 🎉 完全重构代码架构
- ✨ 新增依赖注入容器
- ✨ 新增统一配置管理
- ✨ 新增多层缓存系统
- ✨ 新增数据库优化功能
- 🔧 优化素材管理系统
- 🔧 优化用户管理系统
- 🛡️ 增强安全性
- 🚀 提升性能
- 🗑️ 清理不需要的文件

### v1.x.x
- 基础功能实现
- WordPress主题框架
- ACF字段集成

## 📄 许可证

本主题遵循 GPL v2 或更高版本许可证。

## 🤝 贡献

欢迎提交 Issue 和 Pull Request 来改进这个主题。

## 📞 支持

如有问题或需要技术支持，请通过以下方式联系：

- 📧 邮箱: support@piccool.com
- 🌐 官网: https://piccool.com
- 📚 文档: https://docs.piccool.com

---

**PicCool 主题** - 让素材分享更简单、更高效！