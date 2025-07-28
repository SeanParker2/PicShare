# PicShare主题部署指南

## 系统要求

- PHP 7.4+
- WordPress 5.6+
- MySQL 5.7+ 或 MariaDB 10.3+

## 方法一：使用Docker部署（推荐）

使用Docker可以快速搭建一个完整的WordPress环境，无需手动配置PHP、MySQL等组件。

### 前提条件

- 安装 [Docker](https://www.docker.com/products/docker-desktop)
- 安装 [Docker Compose](https://docs.docker.com/compose/install/)

### 部署步骤

1. 在PicShare主题目录下创建`docker-compose.yml`文件，内容如下：

```yaml
version: '3'

services:
  # 数据库
  db:
    image: mysql:5.7
    volumes:
      - db_data:/var/lib/mysql
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: wordpress
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
    networks:
      - wordpress

  # WordPress
  wordpress:
    depends_on:
      - db
    image: wordpress:latest
    ports:
      - "8000:80"
    restart: always
    volumes:
      - ./:/var/www/html/wp-content/themes/PicShare
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
    networks:
      - wordpress

  # phpMyAdmin
  phpmyadmin:
    depends_on:
      - db
    image: phpmyadmin/phpmyadmin
    restart: always
    ports:
      - '8080:80'
    environment:
      PMA_HOST: db
      MYSQL_ROOT_PASSWORD: wordpress
    networks:
      - wordpress

networks:
  wordpress:

volumes:
  db_data:
```

2. 在主题目录下运行以下命令启动容器：

```bash
docker-compose up -d
```

3. 等待容器启动完成后，访问以下地址：
   - WordPress: http://localhost:8000
   - phpMyAdmin: http://localhost:8080 (用户名: root, 密码: wordpress)

4. 完成WordPress初始设置后，进入管理后台激活PicShare主题：
   - 登录WordPress管理后台
   - 进入「外观」>「主题」
   - 找到并激活「PicShare主题」

## 方法二：传统部署

### 前提条件

- 已安装WordPress
- 已配置好PHP和MySQL环境

### 部署步骤

1. 将PicShare主题文件夹复制到WordPress的`wp-content/themes/`目录下

2. 登录WordPress管理后台

3. 进入「外观」>「主题」

4. 找到并激活「PicShare主题」

## 主题配置

激活主题后，您可以通过以下步骤配置主题：

1. 进入「外观」>「自定义」设置主题外观

2. 进入「PicShare设置」配置主题特定功能

3. 创建菜单并分配到主题位置

## 故障排除

如果您在部署过程中遇到问题，请检查：

1. PHP版本是否满足要求

2. WordPress版本是否满足要求

3. 主题文件权限是否正确

4. 服务器错误日志

## 联系支持

如需进一步帮助，请联系：

- 主题作者：Next Theme
- 客服QQ：9000045
- 网站：https://www.dkewl.com