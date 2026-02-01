# Xboard-Mini 超精简版
面向小团体、1核1G低配置服务器，保留**多节点管理+用户+流量配额**，无Redis无Docker无冗余组件，基于Nginx+PHP+SQLite原生运行。

## 系统要求
- Ubuntu 20.04+ / Debian 11+
- 1核1G内存及以上
- 开放8080端口

## 一键安装
```bash
curl -fsSL https://raw.githubusercontent.com/HYT-1840/xboard-mini/main/install.sh | bash
