#!/bin/bash
clear
echo "========================================================"
echo "          Xboard-Mini 控制面板 - 全自动安装脚本"
echo "          系统支持: Debian 9+/Ubuntu 18+/CentOS 7+"
echo "          项目地址: https://github.com/HYT-1840/xboard-mini"
echo "========================================================"
echo ""

if [ "$(id -u)" != "0" ]; then
    echo -e "\033[31m错误：必须使用 root 用户运行！\033[0m"
    exit 1
fi

echo -e "\033[32m=== 面板配置信息（回车使用默认值）===\033[0m"

# 1. 面板域名/IP（默认自动获取公网IP）
read -p "面板域名/公网IP (默认: 本机公网IP自动获取): " PANEL_DOMAIN
if [ -z "${PANEL_DOMAIN}" ]; then
    PANEL_DOMAIN=$(curl -s ip.sb || echo "127.0.0.1")
    echo -e "\033[32m使用默认IP: ${PANEL_DOMAIN}\033[0m"
fi

# 2. MySQL root 密码（默认16位随机）
read -p "MySQL root 密码 (默认: 随机16位): " MYSQL_ROOT_PWD
if [ -z "${MYSQL_ROOT_PWD}" ]; then
    MYSQL_ROOT_PWD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
    echo -e "\033[32m使用随机root密码: ${MYSQL_ROOT_PWD}\033[0m"
fi

# 3. 数据库名（默认xboard_mini）
read -p "数据库名 (默认: xboard_mini): " DB_NAME
DB_NAME=${DB_NAME:-xboard_mini}

# 4. 数据库用户（默认xboard_user）
read -p "数据库用户 (默认: xboard_user): " DB_USER
DB_USER=${DB_USER:-xboard_user}

# 5. 数据库用户密码（随机+可选，默认使用随机）
RANDOM_DB_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
echo -e "\033[33m随机数据库用户密码: ${RANDOM_DB_PASS}\033[0m"
read -p "是否使用该随机密码? [y/n] (默认y): " USE_RANDOM
USE_RANDOM=${USE_RANDOM:-y}
if [[ "${USE_RANDOM}" == "y" || "${USE_RANDOM}" == "Y" ]]; then
    DB_PASS=${RANDOM_DB_PASS}
    echo -e "\033[32m已使用随机数据库密码\033[0m"
else
    read -p "请输入自定义数据库用户密码 (无默认): " DB_PASS
    if [ -z "${DB_PASS}" ]; then
        echo -e "\033[31m数据库用户密码不能为空\033[0m"
        exit 1
    fi
fi

# 6. 网站根目录（默认/var/www/xboard-mini）
read -p "网站根目录 (默认: /var/www/xboard-mini): " WEB_ROOT
WEB_ROOT=${WEB_ROOT:-/var/www/xboard-mini}

# 7. 管理员账号（默认admin）
read -p "管理员账号 (默认: admin): " ADMIN_USER
ADMIN_USER=${ADMIN_USER:-admin}

# 8. 管理员密码（默认16位随机，仅记录明文，不提前生成哈希）
read -p "管理员密码 (默认: 随机16位): " ADMIN_PASS
if [ -z "${ADMIN_PASS}" ]; then
    ADMIN_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
    echo -e "\033[32m使用随机管理员密码: ${ADMIN_PASS}\033[0m"
fi

# -------------------------- 环境安装阶段 --------------------------
echo -e "\033[32m[1/7] 系统更新与基础工具安装\033[0m"
if [ -f /etc/debian_version ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt update -y
    apt install -y curl wget git unzip gnupg2 ca-certificates lsb-release
elif [ -f /etc/redhat-release ]; then
    yum install -y epel-release
    yum update -y
    yum install -y curl wget git unzip
else
    echo -e "\033[31m不支持当前操作系统\033[0m"
    exit 1
fi

echo -e "\033[32m[2/7] 安装 Nginx\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y nginx
else
    yum install -y nginx
fi
systemctl enable --now nginx

echo -e "\033[32m[3/7] 安装 PHP 7.4 及必需扩展\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y php7.4-fpm php7.4-mysql php7.4-curl php7.4-mbstring php7.4-xml
else
    yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
    yum-config-manager --enable remi-php74
    yum install -y php php-fpm php-mysqlnd php-curl php-mbstring php-xml
fi
systemctl enable --now php7.4-fpm 2>/dev/null || systemctl enable --now php-fpm

# ✅ 关键修复：PHP安装完成后，再生成管理员密码哈希（此时php命令可正常执行）
ADMIN_PASS_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_DEFAULT);" 2>/dev/null)
if [ -z "${ADMIN_PASS_HASH}" ]; then
    echo -e "\033[31m密码哈希生成失败，PHP环境异常！\033[0m"
    exit 1
fi

echo -e "\033[32m[4/7] 安装 MariaDB 数据库\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y mariadb-server mariadb-client
else
    yum install -y mariadb-server
fi
systemctl enable --now mariadb

# -------------------------- 数据库与配置阶段 --------------------------
echo -e "\033[32m[5/7] 数据库初始化与权限配置\033[0m"
mysql -uroot <<EOF
SET PASSWORD FOR 'root'@'localhost' = PASSWORD('${MYSQL_ROOT_PWD}');
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
EOF

echo -e "\033[32m[6/7] 拉取项目源码并生成配置\033[0m"
mkdir -p ${WEB_ROOT}
git clone https://github.com/HYT-1840/xboard-mini.git ${WEB_ROOT}
chown -R www-data:www-data ${WEB_ROOT} 2>/dev/null || chown -R nginx:nginx ${WEB_ROOT}

# 生成项目配置文件
cat >${WEB_ROOT}/config.php <<EOF
<?php
session_start();
define('DB_HOST', '127.0.0.1');
define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASS}');

function getDB() {
    try {
        return new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (Exception \$e) {
        die("数据库连接失败");
    }
}
EOF

echo -e "\033[32m[7/7] 建表、初始化管理员、配置Nginx\033[0m"
# 建表并插入管理员账号（使用已生成的哈希密码）
mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} <<EOF
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    traffic_quota BIGINT NOT NULL DEFAULT 1024,
    traffic_used BIGINT NOT NULL DEFAULT 0,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS nodes;
CREATE TABLE nodes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL,
    host VARCHAR(128) NOT NULL,
    port INT NOT NULL,
    protocol VARCHAR(32) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

REPLACE INTO users (username, password, role)
VALUES ('${ADMIN_USER}', '${ADMIN_PASS_HASH}', 'admin');
EOF

# Nginx站点配置（适配Debian/Ubuntu/CentOS）
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
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

# 启用Nginx站点（仅Debian/Ubuntu）
if [ -f /etc/debian_version ]; then
    ln -sf /etc/nginx/sites-available/xboard.conf /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
fi

# 防火墙放行80/443端口
echo -e "\033[32m放行 80/443 端口\033[0m"
if [ -f /etc/debian_version ]; then
    ufw allow 80/tcp >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
else
    firewall-cmd --permanent --add-port=80/tcp >/dev/null 2>&1
    firewall-cmd --permanent --add-port=443/tcp >/dev/null 2>&1
    firewall-cmd --reload >/dev/null 2>&1
fi

# 重启所有服务
systemctl restart nginx >/dev/null 2>&1
systemctl restart php7.4-fpm 2>/dev/null || systemctl restart php-fpm >/dev/null 2>&1
systemctl restart mariadb >/dev/null 2>&1

# 安装完成提示
clear
echo "========================================================"
echo -e "\033[32m           安装全部完成，无任何后续操作！\033[0m"
echo "========================================================"
echo "访问地址：http://${PANEL_DOMAIN}"
echo "管理员账号：${ADMIN_USER}"
echo "管理员密码：${ADMIN_PASS}"
echo ""
echo "MySQL root 密码：${MYSQL_ROOT_PWD}"
echo ""
echo "数据库信息（多节点远程连接使用）："
echo "数据库地址：${PANEL_DOMAIN}"
echo "数据库名：${DB_NAME}"
echo "数据库用户：${DB_USER}"
echo "数据库密码：${DB_PASS}"
echo "========================================================"
echo "说明：访问域名/IP直接进入统一登录页，管理员/用户共用同一入口"
echo "========================================================"
