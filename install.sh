#!/bin/bash
set -e

# 基础配置（PHP8.3，适配Ubuntu24.04官方源，ARM/x86全兼容）
INSTALL_DIR="/opt/xboard-mini"
WEB_PORT="8080"
PHP_VERSION="8.3"
# 替换为你的GitHub仓库RAW地址（保持不变即可）
REPO_RAW_URL="https://raw.githubusercontent.com/HYT-1840/xboard-mini/main"

# 颜色输出
info() { echo -e "\033[36m[INFO] $1\033[0m"; }
error() { echo -e "\033[31m[ERROR] $1\033[0m"; exit 1; }

# 仅支持Ubuntu/Debian系统
if [[ ! -x /usr/bin/apt ]]; then
    error "仅支持 Ubuntu/Debian 系统，请更换系统后重新安装"
fi

# 仅更新系统官方源，安装基础工具
info "更新系统官方源，安装基础依赖"
apt update -y
apt install -y curl wget lsb-release ca-certificates --no-install-recommends

# 安装核心依赖（纯官方源，无第三方，避免所有签名/418错误）
info "安装 Nginx + PHP$PHP_VERSION + SQLite3 核心组件"
apt install -y nginx php${PHP_VERSION}-fpm php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring sqlite3 --no-install-recommends

# 创建安装目录并设置权限
info "创建面板安装目录：$INSTALL_DIR"
mkdir -p $INSTALL_DIR/{public,pages,storage}
chown -R www-data:www-data $INSTALL_DIR
chmod 755 $INSTALL_DIR

# 从GitHub拉取面板源码
info "从GitHub拉取Xboard-Mini源码文件"
curl -fsSL $REPO_RAW_URL/src/public/index.php -o $INSTALL_DIR/public/index.php
curl -fsSL $REPO_RAW_URL/src/pages/login.php -o $INSTALL_DIR/pages/login.php
curl -fsSL $REPO_RAW_URL/src/pages/admin.php -o $INSTALL_DIR/pages/admin.php
curl -fsSL $REPO_RAW_URL/src/pages/user.php -o $INSTALL_DIR/pages/user.php
curl -fsSL $REPO_RAW_URL/src/pages/node.php -o $INSTALL_DIR/pages/node.php
curl -fsSL $REPO_RAW_URL/src/database.sql -o $INSTALL_DIR/database.sql

# 1核1G专用：极致优化PHP-FPM配置（最低进程，最低内存占用）
PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
sed -i 's/^pm.max_children.*/pm.max_children = 2/' $PHP_FPM_CONF
sed -i 's/^pm.start_servers.*/pm.start_servers = 1/' $PHP_FPM_CONF
sed -i 's/^pm.min_spare_servers.*/pm.min_spare_servers = 1/' $PHP_FPM_CONF
sed -i 's/^pm.max_spare_servers.*/pm.max_spare_servers = 1/' $PHP_FPM_CONF
sed -i 's/^;pm.process_idle_timeout.*/pm.process_idle_timeout = 10s/' $PHP_FPM_CONF
sed -i 's/^;request_terminate_timeout.*/request_terminate_timeout = 30s/' $PHP_FPM_CONF

# 极简Nginx配置（关闭冗余日志，降低资源占用）
info "配置Nginx站点（适配Xboard-Mini）"
cat > /etc/nginx/sites-enabled/xboard-mini.conf << EOF
server {
    listen $WEB_PORT;
    server_name _;
    root $INSTALL_DIR/public;
    index index.php;
    access_log off;
    error_log /var/log/nginx/xboard-mini-error.log crit;
    client_max_body_size 1M;

    location / {
        try_files \$uri \$uri/ /index.php;
        expires -1;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_connect_timeout 5s;
        fastcgi_read_timeout 10s;
    }

    # 禁止访问敏感文件
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
EOF
# 删除Nginx默认配置，避免端口冲突
rm -f /etc/nginx/sites-enabled/default
# 重启Nginx+PHP-FPM使配置生效
systemctl restart nginx php${PHP_VERSION}-fpm
# 设置开机自启（服务器重启后自动运行）
systemctl enable nginx php${PHP_VERSION}-fpm

# 初始化SQLite数据库（单文件，备份迁移便捷）
info "初始化SQLite数据库，创建核心表结构"
sqlite3 $INSTALL_DIR/database.db < $INSTALL_DIR/database.sql
chown www-data:www-data $INSTALL_DIR/database.db
chmod 600 $INSTALL_DIR/database.db  # 严格权限，防止敏感数据泄露

# 安装并适配服务控制命令（同步PHP8.3版本）
info "安装xboard-mini服务控制命令"
curl -fsSL $REPO_RAW_URL/xboard-mini -o /usr/local/bin/xboard-mini
# 自动替换服务脚本中的PHP版本，避免手动修改
sed -i "s/PHP_VERSION=\"[0-9.]*\"/PHP_VERSION=\"${PHP_VERSION}\"/" /usr/local/bin/xboard-mini
chmod +x /usr/local/bin/xboard-mini

# 交互式初始化管理员账号（密码加密存储，无明文）
echo -e "\n\033[33m--- 初始化Xboard-Mini管理员账号 ---\033[0m"
read -p "请设置管理员用户名: " ADMIN_USER
# 隐藏密码输入，提升安全性
read -s -p "请设置管理员密码（建议8位以上）: " ADMIN_PASS
echo
# 密码加密（PHP原生加密，不可逆）
PWD_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
# 插入/忽略管理员账号（避免重复创建）
sqlite3 $INSTALL_DIR/database.db "INSERT OR IGNORE INTO admin (username,password) VALUES ('$ADMIN_USER','$PWD_HASH');"

# 自动放行面板端口（适配ufw防火墙，主流轻量服务器默认）
if [[ -x /usr/sbin/ufw ]]; then
    info "自动放行$WEB_PORT端口，允许外部访问"
    ufw allow $WEB_PORT/tcp > /dev/null 2>&1
    ufw reload > /dev/null 2>&1
fi

# 部署完成，输出核心信息
SERVER_IP=$(curl -s ip.sb)
echo -e "\n\033[32m============================================="
echo -e "✅ Xboard-Mini 超精简版 部署完成！"
echo -e "🌐 访问地址：http://${SERVER_IP}:${WEB_PORT}"
echo -e "⚙️  核心命令：xboard-mini start|stop|restart|status|logs"
echo -e "💾 数据备份：cp $INSTALL_DIR/database.db 你的备份路径"
echo -e "=============================================\033[0m"
