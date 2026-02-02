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

echo -e "\033[32m=== 面板配置信息 ===\033[0m"
read -p "面板域名/公网IP (如: 1.2.3.4 或 panel.xxx.com): " PANEL_DOMAIN
read -p "设置 MySQL root 密码: " MYSQL_ROOT_PWD
read -p "数据库名 (默认 xboard_mini): " DB_NAME
DB_NAME=${DB_NAME:-xboard_mini}
read -p "数据库用户 (默认 xboard_user): " DB_USER
DB_USER=${DB_USER:-xboard_user}
read -p "数据库密码: " DB_PASS
read -p "网站根目录 (默认 /var/www/xboard-mini): " WEB_ROOT
WEB_ROOT=${WEB_ROOT:-/var/www/xboard-mini}

read -p "设置管理员账号 (默认 admin): " ADMIN_USER
ADMIN_USER=${ADMIN_USER:-admin}
read -p "设置管理员密码: " ADMIN_PASS
if [ -z "$ADMIN_PASS" ]; then
    echo -e "\033[31m管理员密码不能为空\033[0m"
    exit 1
fi
ADMIN_PASS_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")

echo -e "\033[32m[1/7] 系统更新与基础工具\033[0m"
if [ -f /etc/debian_version ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt update -y
    apt install -y curl wget git unzip gnupg2 ca-certificates lsb-release
elif [ -f /etc/redhat-release ]; then
    yum install -y epel-release
    yum update -y
    yum install -y curl wget git unzip
else
    echo -e "\033[31m不支持此系统\033[0m"
    exit 1
fi

echo -e "\033[32m[2/7] 安装 Nginx\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y nginx
else
    yum install -y nginx
fi
systemctl enable --now nginx

echo -e "\033[32m[3/7] 安装 PHP 7.4 及扩展\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y php7.4-fpm php7.4-mysql php7.4-curl php7.4-mbstring php7.4-xml
else
    yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
    yum-config-manager --enable remi-php74
    yum install -y php php-fpm php-mysqlnd php-curl php-mbstring php-xml
fi
systemctl enable --now php7.4-fpm 2>/dev/null || systemctl enable --now php-fpm

echo -e "\033[32m[4/7] 安装 MariaDB\033[0m"
if [ -f /etc/debian_version ]; then
    apt install -y mariadb-server mariadb-client
else
    yum install -y mariadb-server
fi
systemctl enable --now mariadb

echo -e "\033[32m[5/7] 初始化数据库与权限\033[0m"
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

echo -e "\033[32m[6/7] 拉取源码并生成配置\033[0m"
mkdir -p ${WEB_ROOT}
git clone https://github.com/HYT-1840/xboard-mini.git ${WEB_ROOT}
chown -R www-data:www-data ${WEB_ROOT} 2>/dev/null || chown -R nginx:nginx ${WEB_ROOT}

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
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return \$pdo;
    } catch (PDOException \$e) {
        die("数据库连接失败：" . \$e->getMessage());
    }
}

function e(\$s) {
    return htmlspecialchars(\$s, ENT_QUUES, 'UTF-8');
}
EOF

echo -e "\033[32m[7/7] 自动建表并插入管理员\033[0m"
mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} <<EOF
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL UNIQUE,
    traffic_quota BIGINT NOT NULL DEFAULT 0,
    traffic_used BIGINT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS nodes;
CREATE TABLE nodes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL,
    host VARCHAR(128) NOT NULL,
    port INT NOT NULL,
    protocol VARCHAR(32) NOT NULL,
    remark TEXT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

REPLACE INTO admins (username, password) VALUES ('${ADMIN_USER}', '${ADMIN_PASS_HASH}');
EOF

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

if [ -f /etc/debian_version ]; then
    ln -sf /etc/nginx/sites-available/xboard.conf /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
fi

if [ -f /etc/debian_version ]; then
    ufw allow 80/tcp
    ufw allow 443/tcp
else
    firewall-cmd --permanent --add-port=80/tcp
    firewall-cmd --permanent --add-port=443/tcp
    firewall-cmd --reload
fi

systemctl restart nginx
systemctl restart php7.4-fpm 2>/dev/null || systemctl restart php-fpm
systemctl restart mariadb

clear
echo "========================================================"
echo -e "\033[32m           安装全部完成，无后续操作！\033[0m"
echo "========================================================"
echo "访问地址：http://${PANEL_DOMAIN}"
echo "后台地址：http://${PANEL_DOMAIN}/admin.php"
echo "管理员账号：${ADMIN_USER}"
echo "管理员密码：${ADMIN_PASS}"
echo ""
echo "数据库信息（多节点远程使用）："
echo "主机：${PANEL_DOMAIN}"
echo "库名：${DB_NAME}"
echo "用户：${DB_USER}"
echo "密码：${DB_PASS}"
echo "========================================================"
