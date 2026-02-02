#!/bin/bash
clear
echo "========================================================"
echo "          Xboard-Mini 控制面板 - 全自动安装脚本"
echo "          系统支持: Debian 9+/Ubuntu 18+/CentOS 7+"
echo "          项目地址: https://github.com/HYT-1840/xboard-mini"
echo "========================================================"
echo ""

# 检查 root
if [ "$(id -u)" != "0" ]; then
    echo -e "\033[31m错误：必须使用 root 用户运行此脚本！\033[0m"
    exit 1
fi

# 交互配置
echo -e "\033[32m=== 面板配置信息（请按提示输入）===\033[0m"
read -p "面板网站域名/公网IP (例如: 1.2.3.4 或 panel.xxx.com): " PANEL_DOMAIN
read -p "设置 MySQL root 密码 (请记住,仅本次安装使用): " MYSQL_ROOT_PWD
read -p "设置 Xboard 专用数据库名 (默认 xboard_mini): " DB_NAME
DB_NAME=${DB_NAME:-xboard_mini}
read -p "设置 Xboard 数据库用户名 (默认 xboard_user): " DB_USER
DB_USER=${DB_USER:-xboard_user}
read -p "设置 Xboard 数据库密码: " DB_PASS
read -p "网站根目录 (默认 /var/www/xboard-mini): " WEB_ROOT
WEB_ROOT=${WEB_ROOT:-/var/www/xboard-mini}
echo ""

# 系统更新与基础依赖
echo -e "\033[32m[1/8] 系统更新与安装基础工具\033[0m"
if [ -f /etc/debian_version ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt update -y
    apt install -y curl wget git unzip gnupg2 ca-certificates lsb-release debian-archive-keyring
elif [ -f /etc/redhat-release ]; then
    yum install -y epel-release
    yum update -y
    yum install -y curl wget git unzip
else
    echo -e "\033[31m不支持当前操作系统\033[0m"
    exit 1
fi

# 安装 Nginx
echo -e "\033[32m[2/8] 安装 Nginx\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y nginx
elif [ -f /etc/redhat-release ]; then
    yum install -y nginx
fi
systemctl enable --now nginx

# 安装 PHP
echo -e "\033[32m[3/8] 安装 PHP 7.4 及扩展\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y php7.4-fpm php7.4-mysql php7.4-curl php7.4-gd php7.4-mbstring php7.4-xml
elif [ -f /etc/redhat-release ]; then
    yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
    yum-config-manager --enable remi-php74
    yum install -y php php-fpm php-mysqlnd php-curl php-gd php-mbstring php-xml
fi
systemctl enable --now php7.4-fpm 2>/dev/null || systemctl enable --now php-fpm

# 安装 MySQL / MariaDB
echo -e "\033[32m[4/8] 安装 MySQL/MariaDB\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y mariadb-server mariadb-client
elif [ -f /etc/redhat-release ]; then
    yum install -y mariadb-server mariadb
fi
systemctl enable --now mariadb

# 初始化数据库
echo -e "\033[32m[5/8] 配置数据库权限与账号\033[0m"
mysql -uroot <<EOF
SET PASSWORD FOR 'root'@'localhost' = PASSWORD('${MYSQL_ROOT_PWD}');
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
EOF

# 拉取 GitHub 代码
echo -e "\033[32m[6/8] 拉取 Xboard-Mini 源码\033[0m"
mkdir -p ${WEB_ROOT}
git clone https://github.com/HYT-1840/xboard-mini.git ${WEB_ROOT}
chown -R www-data:www-data ${WEB_ROOT} 2>/dev/null || chown -R nginx:nginx ${WEB_ROOT}

# 生成 config.php
echo -e "\033[32m[7/8] 生成面板数据库配置文件\033[0m"
cat >${WEB_ROOT}/config.php <<EOF
<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASS}');

session_start();

function checkAdmin() {
    if (!isset(\$_SESSION['admin_login'])) {
        header("Location: index.php");
        exit;
    }
}

function getDB() {
    try {
        \$pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return \$pdo;
    } catch (PDOException \$e) {
        die("数据库连接失败: " . \$e->getMessage());
    }
}

function e(\$str) {
    return htmlspecialchars(\$str, ENT_QUOTES, 'UTF-8');
}
EOF

# Nginx 配置
echo -e "\033[32m[8/8] 生成 Nginx 站点配置\033[0m"
cat >/etc/nginx/sites-available/xboard.conf 2>/dev/null || cat >/etc/nginx/conf.d/xboard.conf <<EOF
server {
    listen 80;
    server_name ${PANEL_DOMAIN};
    root ${WEB_ROOT};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

if [ -f /etc/debian_version ]; then
    ln -sf /etc/nginx/sites-available/xboard.conf /etc/nginx/sites-enabled/
    rm -rf /etc/nginx/sites-enabled/default
fi

# 重启服务
systemctl restart nginx
systemctl restart php7.4-fpm 2>/dev/null || systemctl restart php-fpm
systemctl restart mariadb

# 防火墙
echo -e "\033[32m配置防火墙放行 80 端口\033[0m"
if [ -f /etc/debian_version ]; then
    ufw allow 80/tcp
    ufw allow 443/tcp
elif [ -f /etc/redhat-release ]; then
    firewall-cmd --permanent --add-port=80/tcp
    firewall-cmd --permanent --add-port=443/tcp
    firewall-cmd --reload
fi

clear
echo "========================================================"
echo -e "\033[32m              面板安装完成！\033[0m"
echo "========================================================"
echo "访问地址: http://${PANEL_DOMAIN}"
echo "后台入口: http://${PANEL_DOMAIN}/admin.php"
echo "安装入口: http://${PANEL_DOMAIN}/install.php"
echo ""
echo "数据库信息"
echo "主机: 127.0.0.1"
echo "库名: ${DB_NAME}"
echo "用户: ${DB_USER}"
echo "密码: ${DB_PASS}"
echo ""
echo -e "\033[33m重要：安装完成后请立即访问 install.php 完成初始化，\033[0m"
echo -e "\033[33m并删除 install.php 文件！\033[0m"
echo "默认管理员: admin / admin123456"
echo "========================================================"
