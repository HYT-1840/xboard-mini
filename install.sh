#!/bin/bash
set -e

# 基础配置（1核2G ARM64 Ubuntu24.04 专用）
INSTALL_DIR="/opt/xboard-mini"
WEB_PORT="8080"
PHP_VERSION="8.3"
REPO_RAW_URL="https://raw.githubusercontent.com/HYT-1840/xboard-mini/main"

# 颜色输出
info() { echo -e "\033[36m[INFO] $1\033[0m"; }
error() { echo -e "\033[31m[ERROR] $1\033[0m"; exit 1; }
warn() { echo -e "\033[33m[WARN] $1\033[0m"; }
success() { echo -e "\033[32m[SUCCESS] $1\033[0m"; }

# 系统检测
if [[ ! -x /usr/bin/apt ]]; then
    error "仅支持 Ubuntu/Debian 系统，请更换系统后重新安装"
fi

# 强制交互式获取管理员账号密码（核心修改：不能为空+二次确认）
get_admin_info() {
    echo -e "\n\033[33m============================================="
    echo -e "🔧 请配置Xboard-Mini管理员账号（不能为空）"
    echo -e "=============================================\033[0m"
    # 获取用户名，不能为空
    while true; do
        read -p "请输入管理员用户名: " ADMIN_USER
        if [[ -n "$ADMIN_USER" ]]; then
            break
        else
            error "用户名不能为空，请重新输入！"
        fi
    done
    # 获取密码，不能为空+二次确认
    while true; do
        read -s -p "请输入管理员密码（建议8位以上）: " ADMIN_PASS
        echo
        if [[ -n "$ADMIN_PASS" ]]; then
            read -s -p "请再次输入管理员密码: " ADMIN_PASS_CONFIRM
            echo
            if [[ "$ADMIN_PASS" == "$ADMIN_PASS_CONFIRM" ]]; then
                break
            else
                error "两次输入的密码不一致，请重新输入！"
            fi
        else
            error "密码不能为空，请重新输入！"
        fi
    done
    success "账号密码配置完成！"
}

# 提前获取账号密码（安装前交互，避免安装完再输入）
get_admin_info

# 更新官方源，安装基础工具
info "更新系统官方源，安装基础依赖"
apt update -y
apt install -y curl wget lsb-release ca-certificates --no-install-recommends

# 安装官方源原生组件，无第三方sury源，杜绝418/签名错误
info "安装 Nginx + PHP${PHP_VERSION} + SQLite3 核心组件"
apt install -y nginx \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    sqlite3 --no-install-recommends

# 创建目录并授权
info "创建面板安装目录：${INSTALL_DIR}"
mkdir -p ${INSTALL_DIR}/{public,pages,storage}
chown -R www-data:www-data ${INSTALL_DIR}
chmod 755 ${INSTALL_DIR}

# 拉取完整源码
info "从GitHub拉取Xboard-Mini源码文件"
curl -fsSL ${REPO_RAW_URL}/src/public/index.php -o ${INSTALL_DIR}/public/index.php
curl -fsSL ${REPO_RAW_URL}/src/pages/login.php -o ${INSTALL_DIR}/pages/login.php
curl -fsSL ${REPO_RAW_URL}/src/pages/admin.php -o ${INSTALL_DIR}/pages/admin.php
curl -fsSL ${REPO_RAW_URL}/src/pages/user.php -o ${INSTALL_DIR}/pages/user.php
curl -fsSL ${REPO_RAW_URL}/src/pages/node.php -o ${INSTALL_DIR}/pages/node.php
curl -fsSL ${REPO_RAW_URL}/src/database.sql -o ${INSTALL_DIR}/database.sql

# 1核2G 专属PHP-FPM优化配置
PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
sed -i 's/^pm.max_children.*/pm.max_children = 6/' ${PHP_FPM_CONF}
sed -i 's/^pm.start_servers.*/pm.start_servers = 2/' ${PHP_FPM_CONF}
sed -i 's/^pm.min_spare_servers.*/pm.min_spare_servers = 2/' ${PHP_FPM_CONF}
sed -i 's/^pm.max_spare_servers.*/pm.max_spare_servers = 4/' ${PHP_FPM_CONF}
sed -i 's/^;pm.process_idle_timeout.*/pm.process_idle_timeout = 20s/' ${PHP_FPM_CONF}
sed -i 's/^;request_terminate_timeout.*/request_terminate_timeout = 60s/' ${PHP_FPM_CONF}

# PHP运行参数优化（内存、超时）
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i 's/^max_execution_time.*/max_execution_time = 60/' ${PHP_INI}
sed -i 's/^max_input_time.*/max_input_time = 60/' ${PHP_INI}
sed -i 's/^memory_limit.*/memory_limit = 256M/' ${PHP_INI}
sed -i 's/^post_max_size.*/post_max_size = 8M/' ${PHP_INI}
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 8M/' ${PHP_INI}
sed -i 's/^display_errors.*/display_errors = Off/' ${PHP_INI}
sed -i 's/^error_reporting.*/error_reporting = E_ALL \& ~E_NOTICE \& ~E_WARNING/' ${PHP_INI}

# Nginx站点配置（优化超时，解决502/空响应）
info "配置Nginx站点"
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
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
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

# 初始化数据库
info "初始化SQLite数据库"
sqlite3 ${INSTALL_DIR}/database.db < ${INSTALL_DIR}/database.sql
chown www-data:www-data ${INSTALL_DIR}/database.db
chmod 600 ${INSTALL_DIR}/database.db

# 写入管理员账号密码（加密存储，不可逆）
info "写入管理员账号密码到数据库"
PWD_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_DEFAULT);")
# 先清空原有管理员（避免重复），再插入新账号
sqlite3 ${INSTALL_DIR}/database.db "DELETE FROM admin;"
sqlite3 ${INSTALL_DIR}/database.db "INSERT INTO admin (username,password) VALUES ('${ADMIN_USER}','${PWD_HASH}');"
chown www-data:www-data ${INSTALL_DIR}/database.db

# 安装服务控制脚本并同步版本
info "安装xboard-mini服务控制命令"
curl -fsSL ${REPO_RAW_URL}/xboard-mini -o /usr/local/bin/xboard-mini
sed -i "s/PHP_VERSION=\"[0-9.]*\"/PHP_VERSION=\"${PHP_VERSION}\"/" /usr/local/bin/xboard-mini
chmod +x /usr/local/bin/xboard-mini

# 放行端口
if [[ -x /usr/sbin/ufw ]]; then
    info "放行端口 ${WEB_PORT}"
    ufw allow ${WEB_PORT}/tcp >/dev/null 2>&1
    ufw reload >/dev/null 2>&1
fi

# 完成输出（显示配置的用户名，密码不显示）
SERVER_IP=$(curl -s ip.sb)
echo -e "\n\033[32m============================================="
echo -e "✅ Xboard-Mini 部署完成（1核2G优化版）"
echo -e "🌐 访问地址：http://${SERVER_IP}:${WEB_PORT}"
echo -e "👤 管理员用户名：${ADMIN_USER}"
echo -e "⚙️ 管理命令：xboard-mini start|stop|restart|status|logs"
echo -e "💾 数据备份：cp ${INSTALL_DIR}/database.db 备份路径"
echo -e "=============================================\033[0m"
