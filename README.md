## Xboard-Mini 精简版
- 面向个人与小团队、低配置服务器
- 保留多节点管理+用户+流量配额
- 无Redis无Docker无冗余组件
- 基于Nginx+PHP+MYsql原生运行。

# 系统要求
- Ubuntu 20.04+ / Debian 11+
- 1核1G内存及以上

# 安装方式
- 面板端
- bash -c "$(curl -L https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/script/xray_node_deploy.sh)"
- 节点端
- bash -c "$(curl -L https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install_panel.sh)"

# Xboard-Mini
轻量、极简、可直接上线的 Xray 面板，支持多节点统一认证、用户管理、流量统计、标准分享链接/二维码、批量操作，适配新手「一行命令一键部署」，无需懂 Nginx/PHP/MySQL。

## 🌟 功能特性
- 「一体化一键安装」：面板端一行命令，自动部署全部环境（Nginx+PHP+MySQL+源码）
- 用户管理：搜索、批量启用/禁用、批量流量重置，操作简洁高效
- 节点管理：多节点统一对接，支持 VMess/VLESS/Trojan 协议
- 连接配置：自动生成标准分享链接（兼容主流客户端）+ 二维码，复制即导入
- 流量统计：实时上报、自动累计，面板直观展示使用率
- 安全简洁：管理员密码自定义、数据库权限隔离，无冗余功能，轻量不占资源
- 客户端兼容：NekoBox / Clash / V2RayN / Shadowrocket 等全部支持

## 📋 系统要求
### 面板端（服务器要求）
- 系统：Debian 9+ / Ubuntu 18.04+ / CentOS 7+
- 配置：1核1G 及以上（最低支持 512M 内存，适合轻量部署）
- 网络：开放 80 端口（如需 HTTPS 可后续扩展）
- 权限：必须使用 root 用户（脚本需要自动配置系统环境）

### 节点端（服务器要求）
- 系统：与面板端一致（Debian/Ubuntu/CentOS）
- 网络：开放自定义节点端口，能访问面板服务器公网IP
- 权限：root 用户（一键脚本自动安装 Xray）

## 🚀 一键部署教程（新手首选）
### 第一步：部署面板端（核心，只需要1行命令）
登录面板服务器（root 用户），复制下面**一行命令**粘贴运行，全程交互式引导，无需手动操作：
```bash
bash -c "$(curl -L https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install_panel.sh)"
