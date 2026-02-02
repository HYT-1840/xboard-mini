#!/bin/bash
set -e
clear
echo "========================================================"
echo "          Xboard-Mini 控制面板 - 全自动安装脚本"
echo "          系统支持: Debian 9+/Ubuntu 18+/CentOS 7+"
echo "          项目地址: https://github.com/HYT-1840/xboard-mini"
echo "========================================================"
echo ""

# 检查是否为root用户
if [ "$(id -u)" != "0" ]; then
    echo -e "\033[31m错误：必须使用 root 用户运行！\033[0m"
    exit 1
fi

# -------------------------- 第一步：系统版本检测（核心） --------------------------
echo -e "\033[32m[前置检测] 检测系统发行版及版本...\033[0m"
OS_TYPE=""
OS_VERSION=""
PHP_VERSION=""
PHP_FPM_SERVICE=""
# 检测Debian/Ubuntu
if [ -f /etc/debian_version ]; then
    OS_TYPE="DEBIAN"
    # 获取Ubuntu版本（如18.04/20.04）或Debian主版本（如9/10）
    if [ -f /etc/lsb-release ]; then
        . /etc/lsb-release
        OS_VERSION=${DISTRIB_RELEASE%.*} # 取主版本：18/20/22
    else
        OS_VERSION=$(cat /etc/debian_version | cut -d '.' -f1) # Debian9/10
    fi
    # Debian9+/Ubuntu18+ 原生源默认PHP7.4（无则降级7.3，均为系统原生）
    if apt-cache show php7.4-fpm >/dev/null 2>&1; then
        PHP_VERSION="7.4"
    else
        PHP_VERSION="7.3"
    fi
    PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
    echo -e "\033[36m检测到 ${DISTRIB_ID:-Debian} ${OS_VERSION} 系统，选用原生源PHP ${PHP_VERSION}\033[0m"
# 检测CentOS7
elif [ -f /etc/redhat-release ] && grep -q "CentOS Linux release 7" /etc/redhat-release; then
    OS_TYPE="CENTOS"
    OS_VERSION="7"
    PHP_VERSION="7.2" # CentOS7 原生EPEL源默认PHP7.2，无第三方依赖
    PHP_FPM_SERVICE="php-fpm"
    echo -e "\033[36m检测到 CentOS 7 系统，选用原生EPEL源PHP 7.2\033[0m"
else
    echo -e "\033[31m不支持当前操作系统！仅支持Debian9+/Ubuntu18+/CentOS7+\033[0m"
    exit 1
fi

# -------------------------- 第二步：面板配置交互（保留所有原有逻辑） --------------------------
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

# 8. 管理员密码（默认16位随机，仅记录明文）
read -p "管理员密码 (默认: 随机16位): " ADMIN_PASS
if [ -z "${ADMIN_PASS}" ]; then
    ADMIN_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
    echo -e "\033[32m使用随机管理员密码: ${ADMIN_PASS}\033[0m"
fi

# -------------------------- 第三步：环境安装阶段（系统原生源，无第三方） --------------------------
echo -e "\033[32m[1/6] 清理第三方PHP源 + 系统更新 + 基础工具安装\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    export DEBIAN_FRONTEND=noninteractive
    # ✅ 核心修复：彻底清理所有第三方PHP源（解决418/签名错误）
    rm -rf /etc/apt/sources.list.d/php* /usr/share/keyrings/php* /etc/apt/trusted.gpg.d/php* 2>/dev/null
    # 系统更新 + 原生源基础工具（仅系统原生源，无第三方）
    apt update -y
    apt install -y curl wget git unzip ca-certificates apt-transport-https
elif [ "${OS_TYPE}" = "CENTOS" ]; then
    # 清理CentOS第三方PHP源（如remi/php等）
    rm -rf /etc/yum.repos.d/remi* /etc/yum.repos.d/php* 2>/dev/null
    # CentOS7 原生EPEL源（官方维护，非第三方）
    yum install -y epel-release
    yum clean all && yum makecache fast
    yum update -y
    yum install -y curl wget git unzip
fi

echo -e "\033[32m[2/6] 安装 Nginx（系统原生源）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    apt install -y nginx
else
    yum install -y nginx
fi
systemctl enable --now nginx >/dev/null 2>&1

echo -e "\033[32m[3/6] 安装 PHP ${PHP_VERSION} 及必需扩展（系统原生源）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    # Debian/Ubuntu 纯原生源PHP扩展（匹配检测到的版本，无任何第三方）
    apt install -y \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml
elif [ "${OS_TYPE}" = "CENTOS" ]; then
    # CentOS7 纯原生EPEL源PHP7.2及扩展（官方维护，非第三方）
    yum install -y \
        php \
        php-fpm \
        php-mysqlnd \
        php-curl \
        php-mbstring \
        php-xml
fi
# 启动PHP-FPM（适配检测到的原生服务名）
systemctl enable --now ${PHP_FPM_SERVICE} >/dev/null 2>&1

# 密码哈希生成（纯原生PHP环境，无第三方干扰）
ADMIN_PASS_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_DEFAULT);" 2>/dev/null)
if [ -z "${ADMIN_PASS_HASH}" ]; then
    echo -e "\033[31m密码哈希生成失败，系统原生PHP环境异常！\033[0m"
    exit 1
fi

echo -e "\033[32m[4/6] 安装 MariaDB 数据库（系统原生源）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    apt install -y mariadb-server mariadb-client
else
    yum install -y mariadb-server
fi
systemctl enable --now mariadb >/dev/null 2>&1

# -------------------------- 第四步：数据库与配置阶段 --------------------------
echo -e "\033[32m[5/6] 数据库初始化与权限配置\033[0m"
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

echo -e "\033[32m[6/6] 拉取项目源码、生成配置、适配Nginx\033[0m"
# 拉取源码并设置权限
mkdir -p ${WEB_ROOT}
git clone https://github.com/HYT-1840/xboard-mini.git ${WEB_ROOT} >/dev/null 2>&1
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
        die("数据库连接失败：" . \$e->getMessage());
    }
}
EOF

# 建表并插入管理员账号（哈希密码）
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

# Nginx站点配置（适配PHP版本的FPM sock文件，纯原生路径）
PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
# CentOS7 原生PHP7.2 sock文件路径适配
if [ "${OS_TYPE}" = "CENTOS" ]; then
    PHP_FPM_SOCK="/var/run/php-fpm/php-fpm.sock"
fi
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
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

# 启用Nginx站点（仅Debian/Ubuntu）
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    ln -sf /etc/nginx/sites-available/xboard.conf /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
fi

# 防火墙放行80/443端口（系统原生防火墙）
echo -e "\033[32m放行 80/443 端口（系统原生防火墙）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    ufw allow 80/tcp >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
else
    firewall-cmd --permanent --add-port=80/tcp >/dev/null 2>&1
    firewall-cmd --permanent --add-port=443/tcp >/dev/null 2>&1
    firewall-cmd --reload >/dev/null 2>&1
fi

# 重启所有服务（适配PHP原生服务名）
systemctl restart nginx >/dev/null 2>&1
systemctl restart ${PHP_FPM_SERVICE} >/dev/null 2>&1
systemctl restart mariadb >/dev/null 2>&1

# -------------------------- 安装完成提示 --------------------------
clear
echo "========================================================"
echo -e "\033[32m           安装全部完成，无任何第三方依赖！\033[0m"
echo "========================================================"
echo "📌 系统环境：${DISTRIB_ID:-CentOS} ${OS_VERSION} + 纯原生源PHP ${PHP_VERSION}"
echo "🌐 访问地址：http://${PANEL_DOMAIN}"
echo "🔑 管理员账号：${ADMIN_USER}"
echo "🔑 管理员密码：${ADMIN_PASS}"
echo ""
echo "🗄️ MySQL root 密码：${MYSQL_ROOT_PWD}"
echo ""
echo "🗄️ 数据库信息（多节点远程连接）："
echo "   地址：${PANEL_DOMAIN}"
echo "   库名：${DB_NAME}"
echo "   账号：${DB_USER}"
echo "   密码：${DB_PASS}"
echo "========================================================"
echo "💡 说明：全程使用系统原生源/EPEL源，无任何第三方源，稳定无兼容问题"
echo "========================================================"
