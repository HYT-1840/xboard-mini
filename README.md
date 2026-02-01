## Xboard-Mini 超精简版
轻量、高效、跨架构、跨系统的面板精简版本，专为低配 VPS、ARM 服务器优化，无冗余组件，一键部署，稳定运行。

项目特性
- 极致轻量化，内存占用低，适合 1 核 1G/1 核 2G 入门级服务器
- 原生支持 x86_64/ARM64(aarch64) 双架构，兼容甲骨文 ARM 等云服务器
- 自动适配系统版本、内存规格，智能调配 PHP-FPM 参数
- Nginx+PHP-FPM+SQLite3 极简架构，无需数据库服务
- 一键安装、一键管理，部署流程全自动
- 无冗余代码、无多余依赖，运行稳定无报错

支持系统与架构
- Ubuntu 20.04 / 22.04 / 24.04 (推荐)
- Debian 11 (Bullseye) / 12 (Bookworm)
- 支持所有基于 APT 包管理器的 Debian 系发行版

安装方式 1
- curl -fsSL https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install.sh -o install.sh && chmod +x install.sh && ./install.sh
安装方式 2
- bash -c "$(curl -fsSL https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install.sh)"

面板管理命令
安装完成后，可使用以下命令管理面板服务：
# 启动面板
- xboard-mini start

# 停止面板
- xboard-mini stop

# 重启面板（修改配置、出现异常时使用）
- xboard-mini restart

# 查看服务运行状态
- xboard-mini status

# 查看面板错误日志
- xboard-mini logs

访问地址
- 默认访问端口：8080
- 访问格式：http://你的服务器IP:8080

目录与数据说明
- 面板安装目录：/opt/xboard-mini
- 数据库文件：/opt/xboard-mini/database.db（所有用户、节点、配置数据存储于此）
- Nginx 配置文件：/etc/nginx/sites-enabled/xboard-mini.conf
- PHP 配置目录：/etc/php/对应版本/fpm/
- 错误日志：/var/log/nginx/xboard-mini-error.log

数据备份
- 仅需备份数据库文件即可完成全量数据备份：
- cp /opt/xboard-mini/database.db /root/backup.db

常见错误与解决方案
1. 安装时报：E: Unable to locate package phpxxx
- 原因：系统版本过旧，无对应 PHP 原生包，脚本已自动匹配兼容版本，无需手动修改。
- 解决：使用脚本自带的系统适配逻辑，更换为 Ubuntu 22.04/24.04 或 Debian 12 可彻底避免。

2. 安装时报：418 I'm a teapot / 仓库未签名
- 原因：老旧脚本依赖第三方 sury PHP 源，当前版本已完全移除第三方源，仅使用系统官方源。
- 解决：使用最新版一键脚本重新部署，无需手动处理源配置。

3. 访问出现 502 Bad Gateway
- 原因：Nginx 无法连接 PHP-FPM，进程未启动、Socket 路径错误、权限异常。
- 解决：
# 重启PHP与Nginx服务
- xboard-mini restart

# 修复文件权限
- chown -R www-data:www-data /opt/xboard-mini
- chmod -R 755 /opt/xboard-mini

4. 访问出现 ERR_EMPTY_RESPONSE 空白响应
- 原因：PHP 无执行权限、源码文件缺失、内存不足导致进程崩溃、扩展未安装完整。
- 解决：
# 重新拉取完整源码
- curl -fsSL https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install.sh | bash

# 重新安装PHP依赖扩展
- apt install -y --reinstall php-fpm php-sqlite3 php-curl php-mbstring

5. 浏览器无法访问，提示连接超时
- 原因：服务器防火墙未放行 8080 端口、安全组未放行、Nginx 未正常启动。
- 解决：
# 放行防火墙端口
- ufw allow 8080/tcp
- ufw reload

# 检查端口占用
- netstat -tulpn | grep 8080

# 重启Nginx
- systemctl restart nginx

6. ARM 架构服务器安装失败
- 原因：旧脚本未适配 ARM，当前版本原生支持 ARM64，无额外修改。
- 解决：直接使用最新一键脚本，系统自动安装 ARM 架构兼容包，无需调整参数。

7. PHP-FPM 进程频繁崩溃
- 原因：服务器内存不足，进程参数过高。
- 解决：脚本已自动根据内存配置参数，1 核 1G 服务器建议升级至 1 核 2G，或执行优化：
- echo 1 > /proc/sys/vm/drop_caches
- xboard-mini restart

多系统适配说明
- Ubuntu 系列
- 24.04：自动使用 PHP8.3，兼容性最佳，推荐部署
- 22.04：自动使用 PHP8.1，稳定无兼容问题
- 20.04：自动适配低版本 PHP，可正常运行
- Debian 系列
- Debian 12：自动使用 PHP8.2，性能均衡
- Debian 11：自动使用 PHP7.4，兼容所有功能

架构适配
- x86_64：主流服务器默认架构，全功能支持
- ARM64：甲骨文、华为云、AWS ARM 机型完美兼容，性能优于同配置 x86

更新与重装
- 如需更新面板或重新部署，直接执行一键安装命令即可，脚本会保留原有数据库，不会丢失数据：
- curl -fsSL https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install.sh | bash

卸载面板
- 如需完全卸载面板及相关配置，执行以下命令：
# 停止服务
- xboard-mini stop

# 删除安装目录与配置文件
- rm -rf /opt/xboard-mini
- rm -rf /etc/nginx/sites-enabled/xboard-mini.conf
- rm -rf /usr/local/bin/xboard-mini

# 重启Nginx
- systemctl restart nginx

# 注意事项
- 请勿在安装了宝塔、1Panel 等其他面板的服务器上部署，避免端口、环境冲突
- 建议使用纯净系统部署，减少异常问题
- 数据库文件为核心数据，定期备份防止丢失
- 请勿修改脚本内的路径、版本参数，以免导致部署失败
- 低配服务器建议关闭无关系统服务，释放内存资源
