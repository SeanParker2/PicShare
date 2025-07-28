<?php
/**
 * PicCool用户管理器
 * 
 * 负责用户注册、登录、个人资料管理等功能
 */

namespace PicCool\User;

use PicCool\Config\Config;
use PicCool\Cache\CacheManager;

class UserManager {
    
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
     * 初始化用户管理
     */
    public function init() {
        // AJAX处理
        \add_action('wp_ajax_nopriv_user_register', [$this, 'handle_register']);
        \add_action('wp_ajax_nopriv_user_login', [$this, 'handle_login']);
        \add_action('wp_ajax_user_update_profile', [$this, 'handle_update_profile']);
        \add_action('wp_ajax_user_upload_avatar', [$this, 'handle_upload_avatar']);
        
        // 用户钩子
        \add_action('user_register', [$this, 'on_user_register']);
        \add_action('wp_login', [$this, 'on_user_login'], 10, 2);
        \add_action('wp_logout', [$this, 'on_user_logout']);
        
        // 自定义用户字段
        \add_action('show_user_profile', [$this, 'add_custom_user_fields']);
        \add_action('edit_user_profile', [$this, 'add_custom_user_fields']);
        \add_action('personal_options_update', [$this, 'save_custom_user_fields']);
        \add_action('edit_user_profile_update', [$this, 'save_custom_user_fields']);
    }
    
    /**
     * 处理用户注册
     */
    public function handle_register() {
        // 验证nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'piccool_nonce')) {
            \wp_send_json_error('安全验证失败');
        }
        
        $username = \sanitize_user($_POST['username'] ?? '');
        $email = \sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // 验证输入
        $validation = $this->validate_registration($username, $email, $password, $confirm_password);
        if (\is_wp_error($validation)) {
            \wp_send_json_error($validation->get_error_message());
        }
        
        // 创建用户
        $user_id = \wp_create_user($username, $password, $email);
        
        if (\is_wp_error($user_id)) {
            \wp_send_json_error($user_id->get_error_message());
        }
        
        // 设置用户元数据
        \update_user_meta($user_id, 'registration_date', \current_time('mysql'));
        \update_user_meta($user_id, 'last_login', \current_time('mysql'));
        
        // 发送欢迎邮件
        $this->send_welcome_email($user_id);
        
        \wp_send_json_success([
            'message' => '注册成功！',
            'user_id' => $user_id
        ]);
    }
    
    /**
     * 处理用户登录
     */
    public function handle_login() {
        // 验证nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'piccool_nonce')) {
            \wp_send_json_error('安全验证失败');
        }
        
        $username = \sanitize_user($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // 验证输入
        if (empty($username) || empty($password)) {
            \wp_send_json_error('用户名和密码不能为空');
        }
        
        // 尝试登录
        $user = \wp_authenticate($username, $password);
        
        if (\is_wp_error($user)) {
            \wp_send_json_error($user->get_error_message());
        }
        
        // 设置登录状态
        \wp_set_current_user($user->ID);
        \wp_set_auth_cookie($user->ID, $remember);
        
        // 更新最后登录时间
        \update_user_meta($user->ID, 'last_login', \current_time('mysql'));
        
        \wp_send_json_success([
            'message' => '登录成功！',
            'redirect_url' => \home_url('/user/')
        ]);
    }
    
    /**
     * 处理个人资料更新
     */
    public function handle_update_profile() {
        // 验证用户登录
        if (!\is_user_logged_in()) {
            \wp_send_json_error('请先登录');
        }
        
        // 验证nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'piccool_nonce')) {
            \wp_send_json_error('安全验证失败');
        }
        
        $user_id = \get_current_user_id();
        $display_name = \sanitize_text_field($_POST['display_name'] ?? '');
        $bio = \sanitize_textarea_field($_POST['bio'] ?? '');
        $website = \esc_url_raw($_POST['website'] ?? '');
        $social_links = $this->sanitize_social_links($_POST['social_links'] ?? []);
        
        // 更新用户信息
        \wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'description' => $bio,
            'user_url' => $website
        ]);
        
        // 更新自定义字段
        \update_user_meta($user_id, 'social_links', $social_links);
        
        \wp_send_json_success('个人资料更新成功！');
    }
    
    /**
     * 处理头像上传
     */
    public function handle_upload_avatar() {
        // 验证用户登录
        if (!\is_user_logged_in()) {
            \wp_send_json_error('请先登录');
        }
        
        // 验证nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'piccool_nonce')) {
            \wp_send_json_error('安全验证失败');
        }
        
        if (empty($_FILES['avatar'])) {
            \wp_send_json_error('请选择头像文件');
        }
        
        $user_id = \get_current_user_id();
        
        // 处理文件上传
        $upload = \wp_handle_upload($_FILES['avatar'], ['test_form' => false]);
        
        if (isset($upload['error'])) {
            \wp_send_json_error($upload['error']);
        }
        
        // 保存头像URL
        \update_user_meta($user_id, 'custom_avatar', $upload['url']);
        
        \wp_send_json_success([
            'message' => '头像上传成功！',
            'avatar_url' => $upload['url']
        ]);
    }
    
    /**
     * 用户注册时的操作
     */
    public function on_user_register($user_id) {
        // 设置默认角色
        $user = new \WP_User($user_id);
        $user->set_role('subscriber');
        
        // 初始化用户统计
        \update_user_meta($user_id, 'post_count', 0);
        \update_user_meta($user_id, 'comment_count', 0);
        \update_user_meta($user_id, 'like_count', 0);
        \update_user_meta($user_id, 'download_count', 0);
        
        // 清除用户缓存
        CacheManager::delete('user_stats_' . $user_id);
    }
    
    /**
     * 用户登录时的操作
     */
    public function on_user_login($user_login, $user) {
        // 更新登录统计
        $login_count = (int)\get_user_meta($user->ID, 'login_count', true);
        \update_user_meta($user->ID, 'login_count', $login_count + 1);
        \update_user_meta($user->ID, 'last_login', \current_time('mysql'));
        \update_user_meta($user->ID, 'last_login_ip', $this->get_client_ip());
    }
    
    /**
     * 用户登出时的操作
     */
    public function on_user_logout() {
        $user_id = \get_current_user_id();
        if ($user_id) {
            \update_user_meta($user_id, 'last_logout', \current_time('mysql'));
        }
    }
    
    /**
     * 添加自定义用户字段
     */
    public function add_custom_user_fields($user) {
        $social_links = \get_user_meta($user->ID, 'social_links', true) ?: [];
        ?>
        <h3>社交链接</h3>
        <table class="form-table">
            <tr>
                <th><label for="weibo">微博</label></th>
                <td><input type="url" name="social_links[weibo]" id="weibo" value="<?php echo esc_attr($social_links['weibo'] ?? ''); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="wechat">微信</label></th>
                <td><input type="text" name="social_links[wechat]" id="wechat" value="<?php echo esc_attr($social_links['wechat'] ?? ''); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="qq">QQ</label></th>
                <td><input type="text" name="social_links[qq]" id="qq" value="<?php echo esc_attr($social_links['qq'] ?? ''); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="github">GitHub</label></th>
                <td><input type="url" name="social_links[github]" id="github" value="<?php echo esc_attr($social_links['github'] ?? ''); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * 保存自定义用户字段
     */
    public function save_custom_user_fields($user_id) {
        if (!\current_user_can('edit_user', $user_id)) {
            return;
        }
        
        $social_links = $this->sanitize_social_links($_POST['social_links'] ?? []);
        \update_user_meta($user_id, 'social_links', $social_links);
    }
    
    /**
     * 验证注册信息
     */
    private function validate_registration($username, $email, $password, $confirm_password) {
        if (empty($username)) {
            return new \WP_Error('empty_username', '用户名不能为空');
        }
        
        if (\username_exists($username)) {
            return new \WP_Error('username_exists', '用户名已存在');
        }
        
        if (empty($email) || !\is_email($email)) {
            return new \WP_Error('invalid_email', '请输入有效的邮箱地址');
        }
        
        if (\email_exists($email)) {
            return new \WP_Error('email_exists', '邮箱已被注册');
        }
        
        if (empty($password) || strlen($password) < 6) {
            return new \WP_Error('weak_password', '密码长度至少6位');
        }
        
        if ($password !== $confirm_password) {
            return new \WP_Error('password_mismatch', '两次输入的密码不一致');
        }
        
        return true;
    }
    
    /**
     * 发送欢迎邮件
     */
    private function send_welcome_email($user_id) {
        $user = \get_userdata($user_id);
        $site_name = \get_bloginfo('name');
        
        $subject = sprintf('欢迎加入%s！', $site_name);
        $message = sprintf(
            "亲爱的 %s，\n\n欢迎加入%s！\n\n您的账户已成功创建，现在可以开始探索我们的素材资源了。\n\n祝您使用愉快！\n\n%s 团队",
            $user->display_name,
            $site_name,
            $site_name
        );
        
        \wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * 清理社交链接
     */
    private function sanitize_social_links($links) {
        $sanitized = [];
        
        foreach ($links as $platform => $url) {
            $platform = \sanitize_key($platform);
            if (in_array($platform, ['weibo', 'github'])) {
                $sanitized[$platform] = \esc_url_raw($url);
            } else {
                $sanitized[$platform] = \sanitize_text_field($url);
            }
        }
        
        return $sanitized;
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
     * 获取用户统计信息
     */
    public function get_user_stats($user_id) {
        $cache_key = 'user_stats_' . $user_id;
        $stats = CacheManager::get($cache_key);
        
        if ($stats === false) {
            $stats = [
                'post_count' => (int)\get_user_meta($user_id, 'post_count', true),
                'comment_count' => (int)\get_user_meta($user_id, 'comment_count', true),
                'like_count' => (int)\get_user_meta($user_id, 'like_count', true),
                'download_count' => (int)\get_user_meta($user_id, 'download_count', true),
                'login_count' => (int)\get_user_meta($user_id, 'login_count', true),
                'last_login' => \get_user_meta($user_id, 'last_login', true),
                'registration_date' => \get_user_meta($user_id, 'registration_date', true),
            ];
            
            CacheManager::set($cache_key, $stats, 3600);
        }
        
        return $stats;
    }
}

// 初始化用户管理器
UserManager::getInstance();