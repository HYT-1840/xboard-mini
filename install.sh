#!/bin/bash
set -e

# ================================= 核心优化1：管道执行检测（脚本最开头） =================================
check_exec_mode() {
    if [[ ! -t 0 ]]; then
        echo -e "\033[31m[ERROR] 检测到管道（curl | bash）执行，不支持交互式输入\033[0m"
        echo -e "\033[36m=============================================\033[0m"
        echo -e "📌 请使用以下方式执行（支持正常交互）："
        echo -e "方式1（推荐）：分步本地执行"
        echo -e "curl -fsSL https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install.sh -o install.sh && chmod +x install.sh && ./install.sh"
        echo -e "\n方式2：一键交互式执行"
        echo -e "bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install.sh)\""
        echo -e "\033[36m=============================================\033[0m"
        exit 1
    fi
}
# 执行管道检测
check_exec_mode

# ================================= 全局配置与函数定义 =================================
# 基础默认配置（可通过交互修改）
PHP_VERSION="8.3"
REPO_RAW_URL="https://raw.githubusercontent.com/HYT-1840/xboard-mini/main"
WEB_PORT_DEFAULT=8080
INSTALL_DIR_DEFAULT="/opt/xboard-mini"
WEB_PORT=$WEB_PORT_DEFAULT
INSTALL_DIR=$INSTALL_DIR_DEFAULT

# 颜色输出函数
info() { echo -e "\033[36m[INFO] $1\033[0m"; }
error() { echo -e "\033[31m[ERROR] $1\033[0m"; exit 1; }
warn() { echo -e "\033[33m[WARN] $1\033[0m"; }
success() { echo -e "\033[32m[SUCCESS] $1\033[0m"; }

# 安装步骤进度函数
INSTALL_STEPS=("更新系统源" "安装核心组件" "创建安装目录" "拉取面板源码" "优化PHP配置" "配置Nginx" "初始化数据库" "写入管理员账号" "安装控制脚本")
CURRENT_STEP=0
TOTAL_STEPS=${#INSTALL_STEPS[*]}

step_start() {
    CURRENT_STEP=$((CURRENT_STEP + 1))
    echo -e "\n\033[36m=============================================\033[0m"
    echo -e "\033[36m[STEP $CURRENT_STEP/$TOTAL_STEPS] 开始执行：$1\033[0m"
    echo -e "\033[33m[提示] 该步骤可能耗时几秒，请勿中断脚本\033[0m"
}

step_end() {
    echo -e "\033[32m[STEP $CURRENT_STEP/$TOTAL_STEPS] 执行完成：$1\033[0m"
}

# 密码强度检测函数
check_pwd_strength() {
    local PWD=$1
    if [[ ${#PWD} -ge 8 && "$PWD" =~ [0-9] && "$PWD" =~ [a-zA-Z] ]]; then
        return 0  # 强密码
    else
        return 1  # 弱密码
    fi
}

# ================================= 核心优化2：个性化配置交互 =================================
custom_config() {
    echo -e "\n\033[36m============================================="
    echo -e "⚙️  面板个性化配置（默认值直接回车即可）"
    echo -e "=============================================\033[0m"
    # 自定义端口
    read -p "请输入面板访问端口 [默认$WEB_PORT_DEFAULT]: " CUSTOM_PORT
    WEB_PORT=${CUSTOM_PORT:-$WEB_PORT_DEFAULT}
    if ! [[ "$WEB_PORT" =~ ^[0-9]+$ && "$WEB_PORT" -ge 1 && "$WEB_PORT" -le 65535 ]]; then
        warn "端口必须是1-65535的数字，使用默认端口$WEB_PORT_DEFAULT"
        WEB_PORT=$WEB_PORT_DEFAULT
    fi
    # 自定义安装目录
    read -p "请输入面板安装目录 [默认$INSTALL_DIR_DEFAULT]: " CUSTOM_DIR
    INSTALL_DIR=${CUSTOM_DIR:-$INSTALL_DIR_DEFAULT}
    if [[ ! "$INSTALL_DIR" =~ ^/ ]]; then
        warn "安装目录必须是绝对路径，使用默认目录$INSTALL_DIR_DEFAULT"
        INSTALL_DIR=$INSTALL_DIR_DEFAULT
    fi
    # 配置确认
    echo -e "\033[33m📌 最终配置：端口=$WEB_PORT | 安装目录=$INSTALL_DIR\033[0m"
    read -p "确认配置并继续安装？[Y/n] " CONFIRM
    CONFIRM=${CONFIRM:-Y}
    if [[ "$CONFIRM" != "Y" && "$CONFIRM" != "y" ]]; then
        error "用户取消安装，脚本退出"
    fi
}

# ================================= 核心优化3：前置环境检测交互 =================================
env_check() {
    echo -e "\n\033[36m============================================="
    echo -e "🔍 前置环境检测（避免安装失败）"
    echo -e "=============================================\033[0m"
    # 安装依赖ss命令
    if ! command -v ss &> /dev/null; then
        apt update -y &> /dev/null && apt install -y iproute2 &> /dev/null
    fi
    # 内存检测
    local TOTAL_MEM_MB=$(free -m | awk '/Mem:/ {print $2}')
    if [[ "$TOTAL_MEM_MB" -lt 1024 ]]; then
        warn "检测到服务器内存不足1G，可能导致面板运行卡顿/崩溃"
        read -p "是否继续安装？[y/N] " MEM_CONFIRM
        if [[ "$MEM_CONFIRM" != "Y" && "$MEM_CONFIRM" != "y" ]]; then
            error "用户因内存不足取消安装"
        fi
    fi
    # 端口占用检测+自动释放
    local PORT_OCCUPIED=$(ss -tulpn | grep -c ":$WEB_PORT ")
    if [[ "$PORT_OCCUPIED" -gt 0 ]]; then
        error "端口$WEB_PORT已被占用"
        read -p "是否自动杀死占用进程并释放端口？[Y/n] " PORT_KILL
        PORT_KILL=${PORT_KILL:-Y}
        if [[ "$PORT_KILL" == "Y" || "$PORT_KILL" == "y" ]]; then
            ss -tulpn | grep ":$WEB_PORT " | awk '{print $NF}' | sed -r 's/.*\(([0-9]+)\).*/\1/' | xargs -r kill -9 &> /dev/null
            success "已自动释放端口$WEB_PORT"
        else
            error "端口被占用，用户取消安装"
        fi
    fi
    # 其他面板检测
    local OTHER_PANEL=$(ps -ef | grep -c -E "bt-panel|1panel|aaPanel|宝塔" 2>/dev/null)
    if [[ "$OTHER_PANEL" -gt 1 ]]; then
        warn "检测到服务器存在其他面板，可能导致端口/环境冲突"
        read -p "是否继续安装？[y/N] " PANEL_CONFIRM
        if [[ "$PANEL_CONFIRM" != "Y" && "$PANEL_CONFIRM" != "y" ]]; then
            error "用户因存在其他面板取消安装"
        fi
    fi
    # 系统检测
    if [[ ! -x /usr/bin/apt ]]; then
        error "仅支持 Ubuntu/Debian 系（APT包管理器）系统，脚本退出"
    fi
    success "前置环境检测通过，即将开始核心安装"
}

# ================================= 核心优化4：强交互管理员账号密码配置 =================================
get_admin_info() {
    echo -e "\n\033[36m============================================="
    echo -e "🔧 配置Xboard-Mini管理员账号（不能为空）"
    echo -e "=============================================\033[0m"
    # 用户名非空校验
    while true; do
        read -p "请输入管理员用户名: " ADMIN_USER
        if [[ -n "$ADMIN_USER" ]]; then
            break
        else
            error "用户名不能为空，请重新输入！"
        fi
    done
    # 密码配置（非空+强度+二次确认）
    while true; do
        read -s -p "请输入管理员密码（建议≥8位，含数字+字母）: " ADMIN_PASS
        echo
        if [[ -n "$ADMIN_PASS" ]]; then
            # 密码强度检测
            if ! check_pwd_strength "$ADMIN_PASS"; then
                warn "密码为弱密码（未满足≥8位+数字+字母要求）"
                read -p "是否继续使用该弱密码？[y/N] " PWD_CONFIRM
                if [[ "$PWD_CONFIRM" != "Y" && "$PWD_CONFIRM" != "y" ]]; then
                    continue
                fi
            fi
            # 二次确认
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
    success "管理员账号密码配置完成！"
}

# ================================= 核心优化5：源码拉取异常重试函数 =================================
pull_source() {
    curl -fsSL ${REPO_RAW_URL}/src/public/index.php -o ${INSTALL_DIR}/public/index.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/login.php -o ${INSTALL_DIR}/pages/login.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/admin.php -o ${INSTALL_DIR}/pages/admin.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/user.php -o ${INSTALL_DIR}/pages/user.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/node.php -o ${INSTALL_DIR}/pages/node.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/database.sql -o ${INSTALL_DIR}/database.sql || return 1
    return 0
}

# ================================= 核心优化6：安装完成一站式交互提示 =================================
install_complete() {
    local SERVER_IP=$(curl -s ip.sb || echo "请手动替换为服务器公网IP")
    echo -e "\n\033[32m============================================="
    echo -e "✅ Xboard-Mini 安装完成（1核2G优化版）"
    echo -e "=============================================\033[0m"
    # 核心访问信息
    echo -e "\033[36m📌 核心访问信息\033[0m"
    echo -e "外网访问地址：http://$SERVER_IP:$WEB_PORT"
    echo -e "管理员用户名：$ADMIN_USER"
    echo -e "🔐 密码：为你配置的密文密码（无明文存储）"
    # 常用管理命令
    echo -e "\n\033[36m⚙️  常用管理命令\033[0m"
    echo -e "启动面板：xboard-mini start"
    echo -e "停止面板：xboard-mini stop"
    echo -e "重启面板：xboard-mini restart"
    echo -e "查看状态：xboard-mini status"
    echo -e "查看日志：xboard-mini logs"
    # 数据备份与密码重置
    echo -e "\n\033[36m💾 数据管理命令\033[0m"
    echo -e "一键备份：cp $INSTALL_DIR/database.db /root/xboard-backup-$(date +%Y%m%d).db"
    echo -e "重置密码：bash <(curl -fsSL ${REPO_RAW_URL}/reset_pwd.sh)"
    # 重要注意事项
    echo -e "\n\033[31m⚠️  重要注意事项\033[0m"
    echo -e "1. 请确保云服务器安全组已放行 $WEB_PORT/TCP 端口（甲骨文云需手动配置）"
    echo -e "2. 请勿在该服务器安装其他面板，避免端口/环境冲突"
    echo -e "3. 核心数据存储在 $INSTALL_DIR/database.db，建议定期备份"
    echo -e "4. 若无法访问，优先检查安全组规则和服务器防火墙"
    # 交互式外网访问验证
    read -p "是否立即验证面板外网访问？[Y/n] " CHECK_ACCESS
    CHECK_ACCESS=${CHECK_ACCESS:-Y}
    if [[ "$CHECK_ACCESS" == "Y" || "$CHECK_ACCESS" == "y" ]]; then
        echo -e "\033[33m[检测中] 正在验证外网访问，请稍候...\033[0m"
        local ACCESS_CODE=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "http://$SERVER_IP:$WEB_PORT")
        if [[ "$ACCESS_CODE" == "200" ]]; then
            success "面板外网访问正常，可直接打开浏览器登录！"
        else
            warn "面板外网访问失败，原因可能：1.安全组未放行$WEB_PORT端口 2.公网IP获取错误"
        fi
    fi
    echo -e "\n\033[32m🎉 面板已部署完成，感谢使用！\033[0m"
}

# ================================= 执行交互流程（按顺序调用） =================================
custom_config
env_check
get_admin_info

# ================================= 核心安装步骤（带进度+异常处理） =================================
# 步骤1：更新系统源
step_start "更新系统源"
apt update -y &> /dev/null
step_end "更新系统源"

# 步骤2：安装核心组件
step_start "安装核心组件"
apt install -y nginx \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    sqlite3 curl wget lsb-release ca-certificates --no-install-recommends -y &> /dev/null
step_end "安装核心组件"

# 步骤3：创建安装目录（含目录存在处理）
step_start "创建安装目录"
if [[ -d "$INSTALL_DIR" ]]; then
    warn "检测到安装目录$INSTALL_DIR已存在，将删除原有数据重新安装"
    rm -rf "$INSTALL_DIR" &> /dev/null
fi
mkdir -p ${INSTALL_DIR}/{public,pages,storage} &> /dev/null
chown -R www-data:www-data ${INSTALL_DIR} &> /dev/null
chmod 755 ${INSTALL_DIR} &> /dev/null
step_end "创建安装目录"

# 步骤4：拉取面板源码（带异常重试）
step_start "拉取面板源码"
while true; do
    if pull_source; then
        chown -R www-data:www-data ${INSTALL_DIR} &> /dev/null
        break
    else
        error "源码拉取失败，可能是网络问题或仓库地址错误"
        read -p "请选择：1-重新拉取 2-手动处理 3-退出脚本 [1] " PULL_CHOICE
        PULL_CHOICE=${PULL_CHOICE:-1}
        case "$PULL_CHOICE" in
            1) continue ;;
            2) error "请手动拉取源码后重新执行脚本" ;;
            3) error "用户退出脚本" ;;
            *) error "输入错误，脚本退出" ;;
        esac
    fi
done
step_end "拉取面板源码"

# 步骤5：优化PHP-FPM配置（1核2G专属）
step_start "优化PHP配置"
PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
# FPM进程优化
sed -i 's/^pm.max_children.*/pm.max_children = 6/' $PHP_FPM_CONF &> /dev/null
sed -i 's/^pm.start_servers.*/pm.start_servers = 2/' $PHP_FPM_CONF &> /dev/null
sed -i 's/^pm.min_spare_servers.*/pm.min_spare_servers = 2/' $PHP_FPM_CONF &> /dev/null
sed -i 's/^pm.max_spare_servers.*/pm.max_spare_servers = 4/' $PHP_FPM_CONF &> /dev/null
sed -i 's/^;pm.process_idle_timeout.*/pm.process_idle_timeout = 20s/' $PHP_FPM_CONF &> /dev/null
sed -i 's/^;request_terminate_timeout.*/request_terminate_timeout = 60s/' $PHP_FPM_CONF &> /dev/null
# PHP运行参数优化
sed -i 's/^max_execution_time.*/max_execution_time = 60/' $PHP_INI &> /dev/null
sed -i 's/^max_input_time.*/max_input_time = 60/' $PHP_INI &> /dev/null
sed -i 's/^memory_limit.*/memory_limit = 256M/' $PHP_INI &> /dev/null
sed -i 's/^post_max_size.*/post_max_size = 8M/' $PHP_INI &> /dev/null
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 8M/' $PHP_INI &> /dev/null
sed -i 's/^display_errors.*/display_errors = Off/' $PHP_INI &> /dev/null
sed -i 's/^error_reporting.*/error_reporting = E_ALL \& ~E_NOTICE \& ~E_WARNING/' $PHP_INI &> /dev/null
# 重启PHP生效
systemctl restart php${PHP_VERSION}-fpm &> /dev/null
step_end "优化PHP配置"

# 步骤6：配置Nginx站点
step_start "配置Nginx"
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
rm -f /etc/nginx/sites-enabled/default &> /dev/null
systemctl restart nginx &> /dev/null
systemctl enable nginx php${PHP_VERSION}-fpm &> /dev/null
step_end "配置Nginx"

# 步骤7：初始化数据库
step_start "初始化数据库"
sqlite3 ${INSTALL_DIR}/database.db < ${INSTALL_DIR}/database.sql &> /dev/null
chown www-data:www-data ${INSTALL_DIR}/database.db &> /dev/null
chmod 600 ${INSTALL_DIR}/database.db &> /dev/null
step_end "初始化数据库"

# 步骤8：写入管理员账号密码
step_start "写入管理员账号"
PWD_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_DEFAULT);")
sqlite3 ${INSTALL_DIR}/database.db "DELETE FROM admin;" &> /dev/null
sqlite3 ${INSTALL_DIR}/database.db "INSERT INTO admin (username,password) VALUES ('${ADMIN_USER}','${PWD_HASH}');" &> /dev/null
chown www-data:www-data ${INSTALL_DIR}/database.db &> /dev/null
step_end "写入管理员账号"

# 步骤9：安装服务控制脚本
step_start "安装控制脚本"
curl -fsSL ${REPO_RAW_URL}/xboard-mini -o /usr/local/bin/xboard-mini &> /dev/null
sed -i "s/PHP_VERSION=\"[0-9.]*\"/PHP_VERSION=\"${PHP_VERSION}\"/" /usr/local/bin/xboard-mini &> /dev/null
chmod +x /usr/local/bin/xboard-mini &> /dev/null
# 放行端口
if [[ -x /usr/sbin/ufw ]]; then
    ufw allow ${WEB_PORT}/tcp &> /dev/null
    ufw reload &> /dev/null
fi
step_end "安装控制脚本"

# ================================= 安装完成一站式提示 =================================
install_complete
