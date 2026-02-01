#!/bin/bash
set -e

# 基础配置
INSTALL_DIR="/opt/xboard-mini"
WEB_PORT="8080"
PHP_VERSION="8.2"
REPO_RAW_URL="https://raw.githubusercontent.com/HYT-1840/xboard-mini/main"

# 颜色输出
info() { echo -e "\033[36m[INFO] $1\033[0m"; }
error() { echo -e "\033[31m[ERROR] $1\033[0m"; exit 1; }

# 系统检测
if [[ ! -x /usr/bin/apt ]]; then
    error "仅支持 Ubuntu/Debian 系统"
fi

# 安装依赖
info "更新源并安装 Nginx PHP$PHP_VERSION SQLite3"
apt update -y
apt install -y nginx php${PHP_VERSION}-fpm php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring sqlite3

# 创建目录
info "创建安装目录 $INSTALL_DIR"
mkdir -p $INSTALL_DIR/{public,pages,storage}
chown -R www-data:www-data $INSTALL_DIR

# 拉取远端源码文件
info "从GitHub拉取面板源码"
curl -fsSL $REPO_RAW_URL/src/public/index.php -o $INSTALL_DIR/public/index.php
curl -fsSL $REPO_RAW_URL/src/pages/login.php -o $INSTALL_DIR/pages/login.php
curl -fsSL $REPO_RAW_URL/src/pages/admin.php -o $INSTALL_DIR/pages/admin.php
curl -fsSL $REPO_RAW_URL/src/pages/user.php -o $INSTALL_DIR/pages/user.php
curl -fsSL $REPO_RAW_URL/src/pages/node.php -o $INSTALL_DIR/pages/node.php
curl -fsSL $REPO_RAW_URL/src/database.sql -o $INSTALL_DIR/database.sql

# 优化PHP-FPM(1核1G专用)
PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
sed -i 's/^pm.max_children.*/pm.max_children = 2/' $PHP_FPM_CONF
sed -i 's/^pm.start_servers.*/pm.start_servers = 1/' $PHP_FPM_CONF
sed -i 's/^pm.min_spare_servers.*/pm.min_spare_servers = 1/' $PHP_FPM_CONF
sed -i 's/^pm.max_spare_servers.*/pm.max_spare_servers = 1/' $PHP_FPM_CONF
sed -i 's/^;pm.process_idle_timeout.*/pm.process_idle_timeout = 10s/' $PHP_FPM_CONF

# Nginx极简配置
info "配置Nginx"
cat > /etc/nginx/sites-enabled/xboard-mini.conf << EOF
server {
    listen $WEB_PORT;
    server_name _;
    root $INSTALL_DIR/public;
    index index.php;
    access_log off;
    error_log /var/log/nginx/xboard-mini-error.log crit;

    location / {
        try_files \$uri \$uri/ /index.php;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
EOF
rm -f /etc/nginx/sites-enabled/default
systemctl restart nginx php${PHP_VERSION}-fpm

# 初始化数据库
info "初始化SQLite数据库"
sqlite3 $INSTALL_DIR/database.db < $INSTALL_DIR/database.sql
chown www-data:www-data $INSTALL_DIR/database.db
chmod 600 $INSTALL_DIR/database.db

# 安装服务控制命令
info "安装xboard-mini控制命令"
curl -fsSL $REPO_RAW_URL/xboard-mini -o /usr/local/bin/xboard-mini
chmod +x /usr/local/bin/xboard-mini

# 创建管理员
echo -e "\n--- 初始化管理员账号 ---"
read -p "设置管理员用户名: " ADMIN_USER
read -s -p "设置管理员密码: " ADMIN_PASS
echo
PWD_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
sqlite3 $INSTALL_DIR/database.db "INSERT OR IGNORE INTO admin (username,password) VALUES ('$ADMIN_USER','$PWD_HASH');"

# 防火墙放行
info "放行端口$WEB_PORT"
if [[ -x /usr/sbin/ufw ]]; then
    ufw allow $WEB_PORT/tcp > /dev/null 2>&1
fi

# 完成提示
SERVER_IP=$(curl -s ip.sb)
echo -e "\033[32m
================================
部署完成！
访问地址：http://$SERVER_IP:$WEB_PORT
控制命令：xboard-mini start|stop|restart|status|logs
数据备份：cp $INSTALL_DIR/database.db 备份路径
================================
\033[0m"
