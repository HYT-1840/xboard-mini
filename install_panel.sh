#!/bin/bash
set -e
clear
echo "========================================================"
echo "          Xboard-Mini 控制面板 - 全自动安装脚本"
echo "          系统支持: Debian 9+/Ubuntu 18+/CentOS 7+"
echo "          适配架构: x86_64 + arm64（甲骨文ARM）"
echo "          项目地址: https://github.com/HYT-1840/xboard-mini"
echo "========================================================"
echo ""

# 检查是否为root用户
if [ "$(id -u)" != "0" ]; then
    echo -e "\033[31m错误：必须使用 root 用户运行！\033[0m"
    exit 1
fi

# -------------------------- 第一步：系统版本+架构检测 + 原生源PHP最高版本自动探测 --------------------------
echo -e "\033[32m[前置检测] 检测系统发行版、架构及原生源PHP可用版本...\033[0m"
OS_TYPE=""
OS_VERSION=""
SYS_ARCH=$(dpkg --print-architecture)
PHP_VERSION=""
PHP_FPM_SERVICE=""
PHP_FPM_SOCK=""
# 定义PHP候选版本（从高到低，8.3适配甲骨文ARM64 ports源，兼容x86_64）
PHP_CANDIDATES=("8.3" "8.2" "8.1" "8.0" "7.4" "7.3" "7.2")

# 检测Debian/Ubuntu系统
if [ -f /etc/debian_version ]; then
    OS_TYPE="DEBIAN"
    if [ -f /etc/lsb-release ]; then
        . /etc/lsb-release
        OS_VERSION=${DISTRIB_RELEASE%.*}
    else
        OS_VERSION=$(cat /etc/debian_version | cut -d '.' -f1)
    fi
    # 强制更新apt缓存（适配arm64 ports源）
    echo -e "\033[36m正在更新apt缓存（适配${SYS_ARCH}架构）...\033[0m"
    apt update -y >/dev/null 2>&1
    # 探测原生源PHP最高版本
    for php_ver in "${PHP_CANDIDATES[@]}"; do
        if apt-cache show "php${php_ver}-fpm" >/dev/null 2>&1; then
            PHP_VERSION=${php_ver}
            break
        fi
    done
    if [ -z "${PHP_VERSION}" ]; then
        echo -e "\033[31m系统原生源中未找到PHP7.2及以上可用版本！\033[0m"
        exit 1
    fi
    PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
    PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
    echo -e "\033[36m检测到 ${DISTRIB_ID:-Debian} ${OS_VERSION} (${SYS_ARCH})，原生源PHP：${PHP_VERSION}\033[0m"

# 检测CentOS7系统
elif [ -f /etc/redhat-release ] && grep -q "CentOS Linux release 7" /etc/redhat-release; then
    OS_TYPE="CENTOS"
    OS_VERSION="7"
    SYS_ARCH=$(uname -m)
    PHP_VERSION="7.2"
    PHP_FPM_SERVICE="php-fpm"
    PHP_FPM_SOCK="/var/run/php-fpm/php-fpm.sock"
    echo -e "\033[36m检测到 CentOS 7 (${SYS_ARCH})，EPEL源PHP：${PHP_VERSION}\033[0m"
else
    echo -e "\033[31m不支持当前操作系统！仅支持Debian9+/Ubuntu18+/CentOS7+\033[0m"
    exit 1
fi

# -------------------------- 第二步：面板配置交互（保留原有逻辑） --------------------------
echo -e "\033[32m=== 面板配置信息（回车使用默认值）===\033[0m"
read -p "面板域名/公网IP (默认: 本机公网IP自动获取): " PANEL_DOMAIN
if [ -z "${PANEL_DOMAIN}" ]; then
    PANEL_DOMAIN=$(curl -s ip.sb || echo "127.0.0.1")
    echo -e "\033[32m使用默认IP: ${PANEL_DOMAIN}\033[0m"
fi
read -p "MySQL root 密码 (默认: 随机16位): " MYSQL_ROOT_PWD
if [ -z "${MYSQL_ROOT_PWD}" ]; then
    MYSQL_ROOT_PWD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
    echo -e "\033[32m使用随机root密码: ${MYSQL_ROOT_PWD}\033[0m"
fi
read -p "数据库名 (默认: xboard_mini): " DB_NAME
DB_NAME=${DB_NAME:-xboard_mini}
read -p "数据库用户 (默认: xboard_user): " DB_USER
DB_USER=${DB_USER:-xboard_user}
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
read -p "网站根目录 (默认: /var/www/xboard-mini): " WEB_ROOT
WEB_ROOT=${WEB_ROOT:-/var/www/xboard-mini}
read -p "管理员账号 (默认: admin): " ADMIN_USER
ADMIN_USER=${ADMIN_USER:-admin}
read -p "管理员密码 (默认: 随机16位): " ADMIN_PASS
if [ -z "${ADMIN_PASS}" ]; then
    ADMIN_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
    echo -e "\033[32m使用随机管理员密码: ${ADMIN_PASS}\033[0m"
fi

# -------------------------- 第三步：环境安装阶段（纯原生源，适配ARM64） --------------------------
echo -e "\033[32m[1/6] 清理第三方PHP源 + 系统更新 + 基础工具安装\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    export DEBIAN_FRONTEND=noninteractive
    rm -rf /etc/apt/sources.list.d/php* /usr/share/keyrings/php* /etc/apt/trusted.gpg.d/php* 2>/dev/null
    apt update -y
    apt install -y curl wget git unzip ca-certificates apt-transport-https
elif [ "${OS_TYPE}" = "CENTOS" ]; then
    rm -rf /etc/yum.repos.d/remi* /etc/yum.repos.d/php* 2>/dev/null
    yum install -y epel-release
    yum clean all && yum makecache fast
    yum update -y
    yum install -y curl wget git unzip
fi

echo -e "\033[32m[2/6] 安装 Nginx（系统原生源，适配${SYS_ARCH}）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    apt install -y nginx
else
    yum install -y nginx
fi
systemctl enable --now nginx >/dev/null 2>&1

echo -e "\033[32m[3/6] 安装 PHP ${PHP_VERSION} 及扩展（原生源最高版本）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    apt install -y php${PHP_VERSION}-fpm php${PHP_VERSION}-mysql php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml
elif [ "${OS_TYPE}" = "CENTOS" ]; then
    yum install -y php php-fpm php-mysqlnd php-curl php-mbstring php-xml
fi
systemctl enable --now ${PHP_FPM_SERVICE} >/dev/null 2>&1

# 密码哈希生成
ADMIN_PASS_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_DEFAULT);" 2>/dev/null)
if [ -z "${ADMIN_PASS_HASH}" ]; then
    echo -e "\033[31m密码哈希生成失败，PHP环境异常！\033[0m"
    exit 1
fi

echo -e "\033[32m[4/6] 安装 MariaDB 数据库（系统原生源）\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    apt install -y mariadb-server mariadb-client
else
    yum install -y mariadb-server
fi
systemctl enable --now mariadb >/dev/null 2>&1

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

# -------------------------- 第六步：核心优化 - 拉取源码+配置（解决卡住问题） --------------------------
echo -e "\033[32m[6/6] 拉取项目源码、生成配置、适配Nginx\033[0m"
# 优化1：强制创建目录并赋予755权限，解决权限阻塞
mkdir -p ${WEB_ROOT} && chmod -R 755 /var/www >/dev/null 2>&1
# 优化2：git克隆增加超时（10秒）+ 静默模式 + 仅拉取最新版本（浅克隆），大幅减少网络传输
echo -e "\033[36m正在拉取项目源码（适配甲骨文网络，浅克隆+超时保护）...\033[0m"
git clone --depth 1 --timeout 10 https://github.com/HYT-1840/xboard-mini.git ${WEB_ROOT} >/dev/null 2>&1
# 优化3：增加拉取失败容错 - 改用wget下载压缩包（备用方案，解决git完全连不上的情况）
if [ $? -ne 0 ]; then
    echo -e "\033[33mgit拉取超时，切换为wget压缩包下载（备用方案）...\033[0m"
    rm -rf ${WEB_ROOT}/* >/dev/null 2>&1
    wget -q --timeout 10 https://github.com/HYT-1840/xboard-mini/archive/refs/heads/main.zip -O /tmp/xboard-mini.zip
    unzip -q /tmp/xboard-mini.zip -d /tmp && cp -r /tmp/xboard-mini-main/* ${WEB_ROOT}/ >/dev/null 2>&1
    rm -rf /tmp/xboard-mini* >/dev/null 2>&1
fi
# 优化4：统一设置目录归属，兼容Debian/Ubuntu/CentOS
chown -R www-data:www-data ${WEB_ROOT} 2>/dev/null || chown -R nginx:nginx ${WEB_ROOT} >/dev/null 2>&1

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
        return new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception \$e) {
        die("数据库连接失败：" . \$e->getMessage());
    }
}
EOF

# 建表并插入管理员账号
mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} <<EOF
DROP TABLE IF EXISTS users;
CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT,username VARCHAR(64) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,traffic_quota BIGINT NOT NULL DEFAULT 1024,traffic_used BIGINT NOT NULL DEFAULT 0,role ENUM('admin','user') NOT NULL DEFAULT 'user',status TINYINT NOT NULL DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
DROP TABLE IF EXISTS nodes;
CREATE TABLE nodes (id INT PRIMARY KEY AUTO_INCREMENT,name VARCHAR(64) NOT NULL,host VARCHAR(128) NOT NULL,port INT NOT NULL,protocol VARCHAR(32) NOT NULL,status TINYINT NOT NULL DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
REPLACE INTO users (username, password, role) VALUES ('${ADMIN_USER}', '${ADMIN_PASS_HASH}', 'admin');
EOF

# Nginx站点配置（动态适配PHP-FPM Sock）
cat >/etc/nginx/sites-available/xboard.conf 2>/dev/null || cat >/etc/nginx/conf.d/xboard.conf <<EOF
server {
    listen 80;
    server_name ${PANEL_DOMAIN};
    root ${WEB_ROOT};
    index index.php index.html;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
    location ~ /\. { deny all; }
}
EOF

# 启用Nginx站点
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    ln -sf /etc/nginx/sites-available/xboard.conf /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
fi

# 防火墙放行+服务重启
echo -e "\033[32m放行 80/443 端口并重启所有服务...\033[0m"
if [ "${OS_TYPE}" = "DEBIAN" ]; then
    ufw allow 80/tcp >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
else
    firewall-cmd --permanent --add-port=80/tcp >/dev/null 2>&1
    firewall-cmd --permanent --add-port=443/tcp >/dev/null 2>&1
    firewall-cmd --reload >/dev/null 2>&1
fi
systemctl restart nginx ${PHP_FPM_SERVICE} mariadb >/dev/null 2>&1

# 安装完成提示
clear
echo "========================================================"
echo -e "\033[32m           安装全部完成，纯系统原生源环境！\033[0m"
echo "========================================================"
echo "📌 系统环境：${DISTRIB_ID:-CentOS} ${OS_VERSION} (${SYS_ARCH}架构)"
echo "📌 运行环境：Nginx + PHP ${PHP_VERSION} + MariaDB（原生源）"
echo "🌐 面板访问地址：http://${PANEL_DOMAIN}"
echo "🔑 管理员账号：${ADMIN_USER}"
echo "🔑 管理员密码：${ADMIN_PASS}"
echo ""
echo "🗄️ MySQL root 密码：${MYSQL_ROOT_PWD}"
echo ""
echo "🗄️ 数据库远程连接信息："
echo "   地址：${PANEL_DOMAIN} | 库名：${DB_NAME} | 账号：${DB_USER}"
echo "   密码：${DB_PASS}"
echo "========================================================"
echo "💡 说明：适配甲骨文ARM64，解决网络拉取卡住问题，无任何第三方依赖"
echo "========================================================"
