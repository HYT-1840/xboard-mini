#!/bin/bash
set -e

# ==================== 基础配置 ====================
INSTALL_DIR="/opt/xboard-mini"
WEB_PORT="8080"
REPO_RAW_URL="https://raw.githubusercontent.com/HYT-1840/xboard-mini/main"
# ==================================================

# ==================== 颜色输出 ====================
info() { echo -e "\033[36m[INFO] $1\033[0m"; }
warn() { echo -e "\033[33m[WARN] $1\033[0m"; }
error() { echo -e "\033[31m[ERROR] $1\033[0m"; exit 1; }
ok() { echo -e "\033[32m[OK] $1\033[0m"; }
# ==================================================

# ==================== 系统检查 ====================
if [[ ! -x /usr/bin/apt ]]; then
    error "仅支持 Ubuntu / Debian 系 apt 包管理器的 Linux 系统"
fi

# 获取发行版代号
OS_CODENAME=$(lsb_release -cs 2>/dev/null || echo "unknown")
OS_ID=$(awk -F= '/^ID=/ {print $2}' /etc/os-release | tr -d '"')
OS_VER=$(awk -F= '/^VERSION_ID=/ {print $2}' /etc/os-release | tr -d '"')
info "检测系统：$OS_ID $OS_VER ($OS_CODENAME)"

# 自动选择 PHP 版本
if [[ "$OS_ID" = "ubuntu" ]]; then
    if [[ "$OS_VER" == "24.04" ]]; then
        PHP_VERSION="8.3"
    elif [[ "$OS_VER" == "22.04" ]]; then
        PHP_VERSION="8.1"
    else
        PHP_VERSION="8.2"
    fi
elif [[ "$OS_ID" = "debian" ]]; then
    if [[ "$OS_VER" == "12" ]]; then
        PHP_VERSION="8.2"
    elif [[ "$OS_VER" == "11" ]]; then
        PHP_VERSION="7.4"
    else
        PHP_VERSION="8.2"
    fi
else
    # 默认 fallback
    PHP_VERSION="8.2"
fi
info "自动匹配 PHP 版本：$PHP_VERSION"
# ==================================================

# ==================== 更新并安装依赖 ====================
info "更新系统源"
apt update -y
apt install -y curl wget lsb-release ca-certificates nginx --no-install-recommends

# 安装对应版本 PHP 扩展
PHP_PACKAGES="php${PHP_VERSION}-fpm php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring"
apt install -y ${PHP_PACKAGES} sqlite3 --no-install-recommends
# =========================================================

# ==================== 创建目录与权限 ====================
info "创建目录：$INSTALL_DIR"
mkdir -p ${INSTALL_DIR}/{public,pages,storage}
chown -R www-data:www-data ${INSTALL_DIR}
chmod -R 755 ${INSTALL_DIR}
# =========================================================

# ==================== 拉取源码 ====================
info "拉取面板源码"
curl -fsSL ${REPO_RAW_URL}/src/public/index.php -o ${INSTALL_DIR}/public/index.php
curl -fsSL ${REPO_RAW_URL}/src/pages/login.php    -o ${INSTALL_DIR}/pages/login.php
curl -fsSL ${REPO_RAW_URL}/src/pages/admin.php   -o ${INSTALL_DIR}/pages/admin.php
curl -fsSL ${REPO_RAW_URL}/src/pages/user.php    -o ${INSTALL_DIR}/pages/user.php
curl -fsSL ${REPO_RAW_URL}/src/pages/node.php    -o ${INSTALL_DIR}/pages/node.php
curl -fsSL ${REPO_RAW_URL}/src/database.sql     -o ${INSTALL_DIR}/database.sql
# ====================================================

# ==================== 自动内存规格判断，设置FPM ====================
TOTAL_MEM_MB=$(free -m | awk '/Mem:/ {print $2}')
info "检测内存：${TOTAL_MEM_MB}MB"

if [[ ${TOTAL_MEM_MB} -lt 1200 ]]; then
    # 1核1G
    FPM_MAX_CHILDREN=3
    FPM_START=1
    FPM_MIN=1
    FPM_MAX=2
    MEM_LIMIT="128M"
elif [[ ${TOTAL_MEM_MB} -lt 2400 ]]; then
    # 1核2G
    FPM_MAX_CHILDREN=6
    FPM_START=2
    FPM_MIN=2
    FPM_MAX=4
    MEM_LIMIT="256M"
else
    # 2核4G+
    FPM_MAX_CHILDREN=12
    FPM_START=4
    FPM_MIN=3
    FPM_MAX=6
    MEM_LIMIT="384M"
fi

PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"

# 应用 FPM 配置
sed -i "s/^pm.max_children.*/pm.max_children = $FPM_MAX_CHILDREN/" ${PHP_FPM_CONF}
sed -i "s/^pm.start_servers.*/pm.start_servers = $FPM_START/" ${PHP_FPM_CONF}
sed -i "s/^pm.min_spare_servers.*/pm.min_spare_servers = $FPM_MIN/" ${PHP_FPM_CONF}
sed -i "s/^pm.max_spare_servers.*/pm.max_spare_servers = $FPM_MAX/" ${PHP_FPM_CONF}
sed -i "s/^;pm.process_idle_timeout.*/pm.process_idle_timeout = 20s/" ${PHP_FPM_CONF}
sed -i "s/^;request_terminate_timeout.*/request_terminate_timeout = 60s/" ${PHP_FPM_CONF}

# PHP INI
sed -i "s/^max_execution_time.*/max_execution_time = 60/" ${PHP_INI}
sed -i "s/^max_input_time.*/max_input_time = 60/" ${PHP_INI}
sed -i "s/^memory_limit.*/memory_limit = $MEM_LIMIT/" ${PHP_INI}
sed -i "s/^post_max_size.*/post_max_size = 8M/" ${PHP_INI}
sed -i "s/^upload_max_filesize.*/upload_max_filesize = 8M/" ${PHP_INI}
sed -i "s/^display_errors.*/display_errors = Off/" ${PHP_INI}
sed -i "s/^error_reporting.*/error_reporting = E_ALL \& ~E_NOTICE \& ~E_WARNING/" ${PHP_INI}
# ==================================================================

# ==================== Nginx 配置（自动socket） ====================
info "配置 Nginx"
FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

cat > /etc/nginx/sites-enabled/xboard-mini.conf << EOF
server {
    listen ${WEB_PORT};
    server_name _;
    root ${INSTALL_DIR}/public;
    index index.php;
    access_log off;
    error_log /var/log/nginx/xboard-mini-error.log crit;
    client_max_body_size 8M;

    location / {
        try_files \$uri \$uri/ /index.php;
        expires -1;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:${FPM_SOCK};
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_connect_timeout 10s;
        fastcgi_send_timeout 30s;
        fastcgi_read_timeout 30s;
    }

    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
EOF

rm -f /etc/nginx/sites-enabled/default
systemctl restart nginx php${PHP_VERSION}-fpm
systemctl enable nginx php${PHP_VERSION}-fpm
# ==================================================================

# ==================== 初始化数据库 ====================
info "初始化数据库"
sqlite3 ${INSTALL_DIR}/database.db < ${INSTALL_DIR}/database.sql
chown www-data:www-data ${INSTALL_DIR}/database.db
chmod 600 ${INSTALL_DIR}/database.db
# ======================================================

# ==================== 安装控制脚本 ====================
info "安装面板控制命令"
curl -fsSL ${REPO_RAW_URL}/xboard-mini -o /usr/local/bin/xboard-mini
sed -i "s/PHP_VERSION=\"[0-9.]*\"/PHP_VERSION=\"${PHP_VERSION}\"/" /usr/local/bin/xboard-mini
chmod +x /usr/local/bin/xboard-mini
# ======================================================

# ==================== 创建管理员 ====================
echo -e "\n\033[33m--- 初始化管理员 ---\033[0m"
read -p "设置管理员用户名: " ADMIN_USER
read -s -p "设置管理员密码: " ADMIN_PASS
echo
PWD_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_DEFAULT);")
sqlite3 ${INSTALL_DIR}/database.db "INSERT OR IGNORE INTO admin (username,password) VALUES ('${ADMIN_USER}','${PWD_HASH}');"
# ====================================================

# ==================== 防火墙 ====================
if [[ -x /usr/sbin/ufw ]]; then
    info "放行端口 ${WEB_PORT}"
    ufw allow ${WEB_PORT}/tcp >/dev/null 2>&1
    ufw reload >/dev/null 2>&1
fi
# ==================================================

# ==================== 完成 ====================
SERVER_IP=$(curl -s ip.sb || echo "服务器IP")
echo -e "\n\033[32m========================================"
echo -e "✅ 部署完成"
echo -e "🌐 访问：http://${SERVER_IP}:${WEB_PORT}"
echo -e "🔧 命令：xboard-mini start|stop|restart|status|logs"
echo -e "========================================\033[0m"
