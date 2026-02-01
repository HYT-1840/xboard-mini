#!/bin/bash
set -e

# ================================= 全局基础配置 =================================
# 基础默认配置
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

# ================================= 核心优化：脚本入口交互式功能菜单（最开头） =================================
show_main_menu() {
    clear
    echo -e "\033[32m============================================="
    echo -e "        Xboard-Mini 超精简版 管理菜单"
    echo -e "=============================================\033[0m"
    echo -e "  \033[36m1\033[0m. 全新安装面板（含个性化配置/环境检测）"
    echo -e "  \033[36m2\033[0m. 彻底卸载面板（删除所有配置/数据/组件）"
    echo -e "  \033[36m3\033[0m. 启动面板服务（Nginx+PHP-FPM）"
    echo -e "  \033[36m4\033[0m. 停止面板服务（Nginx+PHP-FPM）"
    echo -e "  \033[36m5\033[0m. 重启面板服务（Nginx+PHP-FPM）"
    echo -e "  \033[36m6\033[0m. 查看面板服务运行状态"
    echo -e "  \033[31m0\033[0m. 退出管理菜单"
    echo -e "\033[32m=============================================\033[0m"
    read -p "  请输入你的选择 [0-6]: " MENU_CHOICE
    case "$MENU_CHOICE" in
        1) 
            success "你选择了【全新安装面板】，即将进入安装流程..."
            sleep 1
            start_install ;; # 执行全新安装流程
        2) 
            read -p "⚠️  警告：卸载将删除所有面板数据，是否确认卸载？[y/N] " UNINSTALL_CONFIRM
            if [[ "$UNINSTALL_CONFIRM" == "Y" || "$UNINSTALL_CONFIRM" == "y" ]]; then
                uninstall_panel # 执行彻底卸载流程
            else
                warn "用户取消卸载，返回主菜单..."
                sleep 1
                show_main_menu
            fi ;;
        3) start_panel ;; # 启动面板
        4) stop_panel ;;  # 停止面板
        5) restart_panel ;; # 重启面板
        6) check_panel_status ;; # 查看状态
        0) 
            success "感谢使用Xboard-Mini，再见！"
            exit 0 ;;
        *) 
            error "输入错误，请输入0-6之间的数字！"
            sleep 1
            show_main_menu ;;
    esac
}

# ================================= 菜单配套基础功能函数（启动/停止/重启/状态/卸载） =================================
# 启动面板服务
start_panel() {
    echo -e "\n\033[36m=============================================\033[0m"
    echo -e "📌 正在启动Xboard-Mini服务（Nginx+PHP-FPM）"
    systemctl start nginx php${PHP_VERSION}-fpm &> /dev/null
    sleep 2
    local NGINX_STATUS=$(systemctl is-active nginx)
    local PHP_STATUS=$(systemctl is-active php${PHP_VERSION}-fpm)
    if [[ "$NGINX_STATUS" == "active" && "$PHP_STATUS" == "active" ]]; then
        success "面板服务启动成功！"
    else
        error "面板服务启动失败，建议执行【6.查看状态】排查问题"
    fi
    read -p "按回车键返回主菜单..."
    show_main_menu
}

# 停止面板服务
stop_panel() {
    echo -e "\n\033[36m=============================================\033[0m"
    echo -e "📌 正在停止Xboard-Mini服务（Nginx+PHP-FPM）"
    systemctl stop nginx php${PHP_VERSION}-fpm &> /dev/null
    sleep 2
    local NGINX_STATUS=$(systemctl is-active nginx)
    local PHP_STATUS=$(systemctl is-active php${PHP_VERSION}-fpm)
    if [[ "$NGINX_STATUS" == "inactive" && "$PHP_STATUS" == "inactive" ]]; then
        success "面板服务停止成功！"
    else
        warn "面板服务未完全停止，可手动执行 systemctl stop nginx php${PHP_VERSION}-fpm"
    fi
    read -p "按回车键返回主菜单..."
    show_main_menu
}

# 重启面板服务
restart_panel() {
    echo -e "\n\033[36m=============================================\033[0m"
    echo -e "📌 正在重启Xboard-Mini服务（Nginx+PHP-FPM）"
    systemctl restart nginx php${PHP_VERSION}-fpm &> /dev/null
    sleep 2
    local NGINX_STATUS=$(systemctl is-active nginx)
    local PHP_STATUS=$(systemctl is-active php${PHP_VERSION}-fpm)
    if [[ "$NGINX_STATUS" == "active" && "$PHP_STATUS" == "active" ]]; then
        success "面板服务重启成功！"
    else
        error "面板服务重启失败，建议执行【6.查看状态】排查问题"
    fi
    read -p "按回车键返回主菜单..."
    show_main_menu
}

# 查看面板运行状态
check_panel_status() {
    echo -e "\n\033[36m=============================================\033[0m"
    echo -e "📌 Xboard-Mini服务运行状态详情"
    echo -e "=============================================\033[0m"
    # 检查组件是否安装
    if ! command -v nginx &> /dev/null || ! command -v php-fpm${PHP_VERSION} &> /dev/null; then
        error "面板核心组件未安装，请先执行【1.全新安装面板】"
    fi
    # 输出服务状态
    echo -e "Nginx 状态：\033[33m$(systemctl is-active nginx)\033[0m | 开机自启：\033[33m$(systemctl is-enabled nginx)\033[0m"
    echo -e "PHP-FPM 状态：\033[33m$(systemctl is-active php${PHP_VERSION}-fpm)\033[0m | 开机自启：\033[33m$(systemctl is-enabled php${PHP_VERSION}-fpm)\033[0m"
    # 输出端口监听
    if command -v ss &> /dev/null; then
        local PORT=$(grep -oP 'listen\s+\K\d+' /etc/nginx/sites-enabled/xboard-mini.conf 2>/dev/null || echo $WEB_PORT_DEFAULT)
        echo -e "面板监听端口：\033[33m$PORT\033[0m | 监听状态：\033[33m$(ss -tulpn | grep -q ":$PORT " && echo "正常" || echo "未监听")\033[0m"
    fi
    # 输出安装目录
    local INSTALL_DIR=$(grep -oP 'root\s+\K/.+' /etc/nginx/sites-enabled/xboard-mini.conf 2>/dev/null | awk '{print $1}' || echo $INSTALL_DIR_DEFAULT)
    echo -e "面板安装目录：\033[33m$INSTALL_DIR\033[0m"
    echo -e "\033[36m=============================================\033[0m"
    read -p "按回车键返回主菜单..."
    show_main_menu
}

# 彻底卸载面板
uninstall_panel() {
    echo -e "\n\033[36m=============================================\033[0m"
    echo -e "📌 正在彻底卸载Xboard-Mini面板（所有数据将被删除）"
    echo -e "=============================================\033[0m"
    # 1. 停止服务
    systemctl stop nginx php${PHP_VERSION}-fpm &> /dev/null
    # 2. 删除安装目录
    local INSTALL_DIR=$(grep -oP 'root\s+\K/.+' /etc/nginx/sites-enabled/xboard-mini.conf 2>/dev/null | awk '{print $1}' || echo $INSTALL_DIR_DEFAULT)
    if [[ -d "$INSTALL_DIR" ]]; then
        rm -rf "$INSTALL_DIR" &> /dev/null
        success "已删除安装目录：$INSTALL_DIR"
    fi
    # 3. 删除配置文件
    rm -f /etc/nginx/sites-enabled/xboard-mini.conf /etc/nginx/sites-enabled/default &> /dev/null
    rm -f /usr/local/bin/xboard-mini &> /dev/null
    success "已删除所有面板配置文件"
    # 4. 卸载核心组件
    apt remove -y nginx php${PHP_VERSION}-fpm php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring sqlite3 &> /dev/null
    apt autoremove -y &> /dev/null
    success "已卸载面板所有核心组件"
    # 5. 清理残留
    rm -rf /var/log/nginx/xboard-mini-error.log &> /dev/null
    success "面板彻底卸载完成，服务器已恢复初始状态！"
    read -p "按回车键退出脚本..."
    exit 0
}

# ================================= 原有优化函数保留（管道检测/配置/环境检测/账号等） =================================
# 管道执行检测
check_exec_mode() {
    if [[ ! -t 0 ]]; then
        echo -e "\033[31m[ERROR] 检测到管道（curl | bash）执行，不支持交互式菜单\033[0m"
        echo -e "\033[36m=============================================\033[0m"
        echo -e "📌 请使用以下方式执行（支持正常交互）："
        echo -e "方式1（推荐）：分步本地执行"
        echo -e "curl -fsSL ${REPO_RAW_URL}/install.sh -o install.sh && chmod +x install.sh && ./install.sh"
        echo -e "\n方式2：一键交互式执行"
        echo -e "bash -c \"\$(curl -fsSL ${REPO_RAW_URL}/install.sh)\""
        echo -e "\033[36m=============================================\033[0m"
        exit 1
    fi
}

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

# 密码强度检测
check_pwd_strength() {
    local PWD=$1
    if [[ ${#PWD} -ge 8 && "$PWD" =~ [0-9] && "$PWD" =~ [a-zA-Z] ]]; then
        return 0
    else
        return 1
    fi
}

# 个性化配置交互
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
        error "用户取消安装，返回主菜单"
        show_main_menu
    fi
}

# 前置环境检测
env_check() {
    echo -e "\n\033[36m============================================="
    echo -e "🔍 前置环境检测（避免安装失败）"
    echo -e "=============================================\033[0m"
    # 安装ss依赖
    if ! command -v ss &> /dev/null; then
        apt update -y &> /dev/null && apt install -y iproute2 &> /dev/null
    fi
    # 内存检测
    local TOTAL_MEM_MB=$(free -m | awk '/Mem:/ {print $2}')
    if [[ "$TOTAL_MEM_MB" -lt 1024 ]]; then
        warn "检测到服务器内存不足1G，可能导致面板运行卡顿"
        read -p "是否继续安装？[y/N] " MEM_CONFIRM
        if [[ "$MEM_CONFIRM" != "Y" && "$MEM_CONFIRM" != "y" ]]; then
            error "用户因内存不足取消安装，返回主菜单"
            show_main_menu
        fi
    fi
    # 端口占用检测
    local PORT_OCCUPIED=$(ss -tulpn | grep -c ":$WEB_PORT ")
    if [[ "$PORT_OCCUPIED" -gt 0 ]]; then
        warn "端口$WEB_PORT已被占用"
        read -p "是否自动杀死占用进程并释放端口？[Y/n] " PORT_KILL
        PORT_KILL=${PORT_KILL:-Y}
        if [[ "$PORT_KILL" == "Y" || "$PORT_KILL" == "y" ]]; then
            ss -tulpn | grep ":$WEB_PORT " | awk '{print $NF}' | sed -r 's/.*\(([0-9]+)\).*/\1/' | xargs -r kill -9 &> /dev/null
            success "已自动释放端口$WEB_PORT"
        else
            error "端口被占用，用户取消安装，返回主菜单"
            show_main_menu
        fi
    fi
    # 其他面板检测
    local OTHER_PANEL=$(ps -ef | grep -c -E "bt-panel|1panel|aaPanel" 2>/dev/null)
    if [[ "$OTHER_PANEL" -gt 1 ]]; then
        warn "检测到服务器存在其他面板，可能导致端口冲突"
        read -p "是否继续安装？[y/N] " PANEL_CONFIRM
        if [[ "$PANEL_CONFIRM" != "Y" && "$PANEL_CONFIRM" != "y" ]]; then
            error "用户因存在其他面板取消安装，返回主菜单"
            show_main_menu
        fi
    fi
    # 系统检测
    if [[ ! -x /usr/bin/apt ]]; then
        error "仅支持Ubuntu/Debian系（APT包管理器）系统"
    fi
    success "前置环境检测通过，即将开始核心安装"
}

# 管理员账号密码配置
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
            if ! check_pwd_strength "$ADMIN_PASS"; then
                warn "密码为弱密码（未满足≥8位+数字+字母要求）"
                read -p "是否继续使用该弱密码？[y/N] " PWD_CONFIRM
                if [[ "$PWD_CONFIRM" != "Y" && "$PWD_CONFIRM" != "y" ]]; then
                    continue
                fi
            fi
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

# 源码拉取异常重试
pull_source() {
    curl -fsSL ${REPO_RAW_URL}/src/public/index.php -o ${INSTALL_DIR}/public/index.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/login.php -o ${INSTALL_DIR}/pages/login.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/admin.php -o ${INSTALL_DIR}/pages/admin.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/user.php -o ${INSTALL_DIR}/pages/user.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/pages/node.php -o ${INSTALL_DIR}/pages/node.php || return 1
    curl -fsSL ${REPO_RAW_URL}/src/database.sql -o ${INSTALL_DIR}/database.sql || return 1
    return 0
}

# 安装完成一站式提示
install_complete() {
    local SERVER_IP=$(curl -s ip.sb || echo "请手动替换为服务器公网IP")
    echo -e "\n\033[32m============================================="
    echo -e "✅ Xboard-Mini 安装完成（1核2G优化版）"
    echo -e "=============================================\033[0m"
    echo -e "\033[36m📌 核心访问信息\033[0m"
    echo -e "外网访问地址：http://$SERVER_IP:$WEB_PORT"
    echo -e "管理员用户名：$ADMIN_USER"
    echo -e "🔐 密码：为你配置的密文密码（无明文存储）"
    echo -e "\n\033[36m⚙️  面板管理方式\033[0m"
    echo -e "1. 执行脚本进入管理菜单：./install.sh"
    echo -e "2. 直接使用命令：xboard-mini start/stop/restart/status/logs"
    echo -e "\n\033[36m💾 数据管理命令\033[0m"
    echo -e "一键备份：cp $INSTALL_DIR/database.db /root/xboard-backup-$(date +%Y%m%d).db"
    echo -e "重置密码：bash <(curl -fsSL ${REPO_RAW_URL}/reset_pwd.sh)"
    echo -e "\n\033[31m⚠️  重要注意事项\033[0m"
    echo -e "1. 请确保云服务器安全组已放行 $WEB_PORT/TCP 端口（甲骨文云需手动配置）"
    echo -e "2. 核心数据存储在 $INSTALL_DIR/database.db，建议定期备份"
    echo -e "3. 后续可直接执行脚本，通过【管理菜单】操作面板"
    # 交互式验证
    read -p "是否立即验证面板外网访问？[Y/n] " CHECK_ACCESS
    CHECK_ACCESS=${CHECK_ACCESS:-Y}
    if [[ "$CHECK_ACCESS" == "Y" || "$CHECK_ACCESS" == "y" ]]; then
        echo -e "\033[33m[检测中] 正在验证外网访问，请稍候...\033[0m"
        local ACCESS_CODE=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "http://$SERVER_IP:$WEB_PORT")
        if [[ "$ACCESS_CODE" == "200" ]]; then
            success "面板外网访问正常，可直接打开浏览器登录！"
        else
            warn "面板外网访问失败，请优先检查云服务器安全组规则"
        fi
    fi
    echo -e "\n\033[32m🎉 面板部署完成，感谢使用！\033[0m"
    read -p "按回车键返回【管理菜单】..."
    show_main_menu
}

# ================================= 全新安装主流程入口 =================================
start_install() {
    # 执行原有安装前检测与配置
    check_exec_mode
    custom_config
    env_check
    get_admin_info

    # 初始化步骤计数
    CURRENT_STEP=0

    # 步骤1：更新系统源
    step_start "更新系统源"
    apt update -y &> /dev/null
    step_end "更新系统源"

    # 步骤2：安装核心组件
    step_start "安装核心组件"
    apt install -y nginx php${PHP_VERSION}-fpm php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring sqlite3 curl wget lsb-release ca-certificates --no-install-recommends -y &> /dev/null
    step_end "安装核心组件"

    # 步骤3：创建安装目录
    step_start "创建安装目录"
    if [[ -d "$INSTALL_DIR" ]]; then
        rm -rf "$INSTALL_DIR" &> /dev/null
    fi
    mkdir -p ${INSTALL_DIR}/{public,pages,storage} &> /dev/null
    chown -R www-data:www-data ${INSTALL_DIR} &> /dev/null
    chmod 755 ${INSTALL_DIR} &> /dev/null
    step_end "创建安装目录"

    # 步骤4：拉取面板源码
    step_start "拉取面板源码"
    while true; do
        if pull_source; then
            chown -R www-data:www-data ${INSTALL_DIR} &> /dev/null
            break
        else
            error "源码拉取失败，网络问题或仓库地址错误"
            read -p "1-重新拉取 2-返回主菜单 [1] " PULL_CHOICE
            PULL_CHOICE=${PULL_CHOICE:-1}
            case "$PULL_CHOICE" in
                1) continue ;;
                2) show_main_menu ;;
                *) error "输入错误，返回主菜单" && show_main_menu ;;
            esac
        fi
    done
    step_end "拉取面板源码"

    # 步骤5：优化PHP配置
    step_start "优化PHP配置"
    PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
    PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
    sed -i 's/^pm.max_children.*/pm.max_children = 6/' $PHP_FPM_CONF &> /dev/null
    sed -i 's/^pm.start_servers.*/pm.start_servers = 2/' $PHP_FPM_CONF &> /dev/null
    sed -i 's/^pm.min_spare_servers.*/pm.min_spare_servers = 2/' $PHP_FPM_CONF &> /dev/null
    sed -i 's/^pm.max_spare_servers.*/pm.max_spare_servers = 4/' $PHP_FPM_CONF &> /dev/null
    sed -i 's/^;pm.process_idle_timeout.*/pm.process_idle_timeout = 20s/' $PHP_FPM_CONF &> /dev/null
    sed -i 's/^;request_terminate_timeout.*/request_terminate_timeout = 60s/' $PHP_FPM_CONF &> /dev/null
    sed -i 's/^max_execution_time.*/max_execution_time = 60/' $PHP_INI &> /dev/null
    sed -i 's/^max_input_time.*/max_input_time = 60/' $PHP_INI &> /dev/null
    sed -i 's/^memory_limit.*/memory_limit = 256M/' $PHP_INI &> /dev/null
    sed -i 's/^post_max_size.*/post_max_size = 8M/' $PHP_INI &> /dev/null
    sed -i 's/^upload_max_filesize.*/upload_max_filesize = 8M/' $PHP_INI &> /dev/null
    sed -i 's/^display_errors.*/display_errors = Off/' $PHP_INI &> /dev/null
    sed -i 's/^error_reporting.*/error_reporting = E_ALL \& ~E_NOTICE \& ~E_WARNING/' $PHP_INI &> /dev/null
    systemctl restart php${PHP_VERSION}-fpm &> /dev/null
    step_end "优化PHP配置"

    # 步骤6：配置Nginx
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

    # 步骤8：写入管理员账号
    step_start "写入管理员账号"
    PWD_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_DEFAULT);")
    sqlite3 ${INSTALL_DIR}/database.db "DELETE FROM admin;" &> /dev/null
    sqlite3 ${INSTALL_DIR}/database.db "INSERT INTO admin (username,password) VALUES ('${ADMIN_USER}','${PWD_HASH}');" &> /dev/null
    chown www-data:www-data ${INSTALL_DIR}/database.db &> /dev/null
    step_end "写入管理员账号"

    # 步骤9：安装控制脚本
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

    # 安装完成提示
    install_complete
}

# ================================= 脚本主入口：启动交互菜单 =================================
check_exec_mode # 先检测执行方式，再显示菜单
show_main_menu
