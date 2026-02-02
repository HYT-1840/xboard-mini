#!/bin/bash
clear
echo "============================================="
echo "  Xray 节点全自动部署脚本 - 对接 Xboard-Mini 面板"
echo "  系统支持: Debian/Ubuntu/CentOS"
echo "  功能: 安装Xray、配置API、流量上报脚本、定时任务"
echo "============================================="
echo ""

# 检查root
if [ "$(id -u)" != "0" ]; then
    echo "错误: 请使用 root 用户运行此脚本!"
    exit 1
fi

# 1. 系统更新&基础依赖
echo -e "\033[32m[1/6] 安装依赖、更新系统\033[0m"
if [ -f /etc/debian_version ]; then
    apt update -y
    apt install -y curl wget cron socat jq qrencode
elif [ -f /etc/redhat-release ]; then
    yum install -y epel-release
    yum update -y
    yum install -y curl wget cronie socat jq qrencode
else
    echo "不支持的系统"
    exit 1
fi

# 2. 安装Xray
echo -e "\033[32m[2/6] 安装最新 Xray-core\033[0m"
bash -c "$(curl -L https://github.com/XTLS/Xray-install/raw/main/install-release.sh)" @ install

# 3. 交互输入配置
echo -e "\033[32m[3/6] 输入节点对接面板所需信息\033[0m"
read -p "请输入面板公网IP/域名 (如: 1.2.3.4 或 https://xxx.com): " PANEL_HOST
read -p "请输入节点监听端口 (1-65535): " NODE_PORT
read -p "请输入节点名称 (备注用,如 新加坡-01): " NODE_NAME
read -p "流量上报间隔(分钟,默认1): " REPORT_MINUTE
REPORT_MINUTE=${REPORT_MINUTE:-1}

# 校验端口
if ! [[ "$NODE_PORT" =~ ^[0-9]+$ && "$NODE_PORT" -ge 1 && "$NODE_PORT" -le 65535 ]]; then
    echo "端口格式错误"
    exit 1
fi

# 4. 写入 Xray config.json
echo -e "\033[32m[4/6] 生成 Xray 配置文件\033[0m"
cat >/usr/local/etc/xray/config.json <<EOF
{
    "log": {
        "loglevel": "warning",
        "access": "/var/log/xray/access.log",
        "error": "/var/log/xray/error.log"
    },
    "api": {
        "tag": "api",
        "services": ["HandlerService","LoggerService","StatsService"]
    },
    "stats": {},
    "policy": {
        "levels": {
            "0": {
                "statsUserUplink": true,
                "statsUserDownlink": true
            }
        },
        "system": {
            "statsInboundUplink": true,
            "statsInboundDownlink": true,
            "statsOutboundUplink": true,
            "statsOutboundDownlink": true
        }
    },
    "inbounds": [
        {
            "tag": "inbound-vmess",
            "port": ${NODE_PORT},
            "listen": "0.0.0.0",
            "protocol": "vmess",
            "settings": { "clients": [] },
            "streamSettings": {
                "network": "ws",
                "security": "none",
                "wsSettings": {
                    "path": "/",
                    "headers": {}
                }
            }
        }
    ],
    "outbounds": [
        { "protocol": "freedom", "settings": {} },
        { "protocol": "blackhole", "settings": {}, "tag": "blocked" }
    ],
    "routing": {
        "rules": [],
        "domainStrategy": "IPIfNonMatch"
    }
}
EOF

# 5. 生成流量上报脚本
echo -e "\033[32m[5/6] 生成流量上报脚本\033[0m"
cat >/opt/xray_traffic_report.sh <<EOF
#!/bin/bash
PANEL_HOST="${PANEL_HOST}"
TRAFFIC_URL="\${PANEL_HOST}/xray_traffic.php"

xray api stats --server=127.0.0.1:8080 | grep -E 'user>>.*>>traffic' | while read -r line; do
    USERNAME=\$(echo "\${line}" | awk -F '>>|=' '{print \$2}')
    VAL=\$(echo "\${line}" | awk -F '=' '{print \$2}' | awk '{print \$1}')
    if [[ -z "\${USERNAME}" || -z "\${VAL}" || \${VAL} -le 0 ]]; then
        continue
    fi
    curl -s --connect-timeout 5 -m 10 "\${TRAFFIC_URL}?user=\${USERNAME}&up=\${VAL}&down=0"
done
xray api stats --server=127.0.0.1:8080 rm all >/dev/null 2>&1
EOF

chmod +x /opt/xray_traffic_report.sh

# 6. 配置crontab
echo -e "\033[32m[6/6] 配置定时上报任务\033[0m"
crontab -l 2>/dev/null | grep -v "xray_traffic_report" | crontab -
echo "*/${REPORT_MINUTE} * * * * /opt/xray_traffic_report.sh >> /var/log/xray_traffic.log 2>&1" | crontab -

# 启动服务
systemctl daemon-reload
systemctl enable xray
systemctl restart xray
systemctl enable cron
systemctl restart cron

# 防火墙放行端口
if [ -f /etc/debian_version ]; then
    ufw allow "${NODE_PORT}"/tcp >/dev/null 2>&1
elif [ -f /etc/redhat-release ]; then
    firewall-cmd --permanent --add-port="${NODE_PORT}"/tcp >/dev/null 2>&1
    firewall-cmd --reload >/dev/null 2>&1
fi

clear
echo "============================================="
echo -e "\033[32m部署完成！节点信息如下：\033[0m"
echo "面板地址: $PANEL_HOST"
echo "节点端口: $NODE_PORT"
echo "节点备注: $NODE_NAME"
echo "上报间隔: ${REPORT_MINUTE} 分钟"
echo "Xray配置: /usr/local/etc/xray/config.json"
echo "上报脚本: /opt/xray_traffic_report.sh"
echo "日志文件: /var/log/xray/ /var/log/xray_traffic.log"
echo "============================================="
echo "请在面板【节点管理】中添加该节点："
echo "地址: $(curl -s ip.sb)"
echo "端口: $NODE_PORT"
echo "协议: vmess"
echo "传输: ws"
echo "路径: /"
echo "============================================="
