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

# -------------------------- 第一步：系统版本检测 + 原生源PHP最高版本自动探测（核心修正） --------------------------
echo -e "\033[32m[前置检测] 检测系统发行版及原生源PHP可用版本...\033[0m"
OS_TYPE=""
OS_VERSION=""
PHP_VERSION=""
PHP_FPM_SERVICE=""
PHP_FPM_SOCK=""
# 定义PHP候选版本（从高到低，优先选最高），兼容面板所需的PHP7.2+
PHP_CANDIDATES=("8.2" "8.1" "8.0" "7.4" "7.3" "7.2")

# 检测Debian/Ubuntu系统
if [ -f /etc/debian_version ]; then
    OS_TYPE="DEBIAN"
    # 获取系统主版本（Ubuntu18/20/22/24，Debian9/10/11/12）
    if [ -f /etc/lsb-release ]; then
        . /etc/lsb-release
        OS_VERSION=${DISTRIB_RELEASE%.*}
    else
        OS_VERSION=$(cat /etc/debian_version | cut -d '.' -f1)
    fi

    # ✅ 核心修正：强制更新apt缓存，确保读取原生源最新包信息（解决Ubuntu24探测为7.4的问题）
    echo -e "\033[36m正在更新apt缓存，确保探测到原生源最新PHP版本...\033[0m"
    apt update -y >/dev/null 2>&1

    # 核心逻辑：从高到低探测原生源中存在的PHP最高版本
    for php_ver in "${PHP_CANDIDATES[@]}"; do
        if apt-cache show "php${php_ver}-fpm" >/dev/null 2>&1; then
            PHP_VERSION=${php_ver}
            break
        fi
    done

    # 验证是否探测到可用PHP版本（原生源至少含7.2+）
    if [ -z "${PHP_VERSION}" ]; then
        echo -e "\033[31m系统原生源中未找到PHP7.2及以上可用版本，不支持安装！\033[0m"
        exit 1
    fi

    # 适配Debian/Ubuntu的PHP服务名和Sock路径
    PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
    PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
    echo -e "\033[36m检测到 ${DISTRIB_ID:-Debian} ${OS_VERSION} 系统，原生源最高可用PHP：${PHP_VERSION}\033[0m"

# 检测CentOS7系统
elif [ -f /etc/redhat-release ] && grep -q "CentOS Linux release 7" /etc/redhat-release; then
    OS_TYPE="CENTOS"
    OS_VERSION="7"
    # CentOS7原生EPEL源最高稳定可用PHP为7.2，直接指定
    PHP_VERSION="7.2"
    PHP_FPM_SERVICE="php-fpm"
    PHP_FPM_SOCK="/var/run/php-fpm/php-fpm.sock"
    echo -e "\033[36m检测到 CentOS 7 系统，原生EPEL源最高可用PHP：${PHP_VERSION}\033[0m"

# 不支持的系统
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

# -------------------------- 第三步：环境安装阶段（纯原生源，无第三方） --------------------------
echo -e "\033[32m[1/6] 清理第三方PHP源 + 系统更新 + 基础工具安装\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    export DEBIAN_FRONTEND=noninteractive
    # 彻底清理所有第三方PHP源残留（配置+密钥）
    rm -rf /etc/apt/sources.list.d/php* /usr/share/keyrings/php* /etc/apt/trusted.gpg.d/php* 2>/dev/null
    # 系统更新 + 原生源基础工具（缓存已前置更新，此处快速执行）
    apt update -y
    apt install -y curl wget git unzip ca-certificates apt-transport-https
elif [ "${OS_TYPE}" = "CENTOS" ]; then
    # 清理CentOS第三方PHP源
    rm -rf /etc/yum.repos.d/remi* /etc/yum.repos.d/php* 2>/dev/null
    # 原生EPEL源（官方维护）
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

echo -e "\033[32m[3/6] 安装 PHP ${PHP_VERSION} 及必需扩展（原生源最高版本）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    # 安装探测到的PHP最高版本及扩展（原生源包，动态适配版本号）
    apt install -y \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml
elif [ "${OS_TYPE}" = "CENTOS" ]; then
    # CentOS7原生EPEL源PHP7.2及扩展
    yum install -y \
        php \
        php-fpm \
        php-mysqlnd \
        php-curl \
        php-mbstring \
        php-xml
fi
# 启动PHP-FPM（动态适配探测到的服务名）
systemctl enable --now ${PHP_FPM_SERVICE} >/dev/null 2>&1

# 密码哈希生成（原生PHP环境，兼容所有7.2+版本）
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

# -------------------------- 第四步：数据库与配置阶段（全动态适配） --------------------------
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

# 生成项目配置文件（保留原有逻辑，兼容所有PHP版本）
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

# 建表并插入管理员账号（哈希密码，兼容所有PHP7.2+的password_hash格式）
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

# Nginx站点配置（✅ 动态适配探测到的PHP-FPM Sock路径，无需手动修改）
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

# 重启所有服务（动态适配PHP服务名）
systemctl restart nginx >/dev/null 2>&1
systemctl restart ${PHP_FPM_SERVICE} >/dev/null 2>&1
systemctl restart mariadb >/dev/null 2>&1

# -------------------------- 安装完成提示 --------------------------
clear
echo "========================================================"
echo -e "\033[32m           安装全部完成，纯系统原生源环境！\033[0m"
echo "========================================================"
echo "📌 系统环境：${DISTRIB_ID:-CentOS} ${OS_VERSION}"
echo "📌 运行环境：Nginx + 原生源最高可用PHP ${PHP_VERSION} + MariaDB"
echo "🌐 面板访问地址：http://${PANEL_DOMAIN}"
echo "🔑 管理员账号：${ADMIN_USER}"
echo "🔑 管理员密码：${ADMIN_PASS}"
echo ""
echo "🗄️ MySQL root 密码：${MYSQL_ROOT_PWD}"
echo ""
echo "🗄️ 数据库远程连接信息："
echo "   地址：${PANEL_DOMAIN}"
echo "   库名：${DB_NAME}"
echo "   账号：${DB_USER}"
echo "   密码：${DB_PASS}"
echo "========================================================"
echo "💡 说明：全程使用系统原生源/EPEL源，自动适配最高可用PHP版本，无任何第三方依赖"
echo "========================================================"
