#!/usr/bin/env bash
# Monitoreo basico - ejecutar via cron

DOMAIN=$(grep "^DOMAIN=" /opt/api-transferencias/.env.vps | cut -d= -f2)
MAILTO="info@comremit.com"

# Health check
HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" "http://localhost/health/ready" 2>/dev/null)

if [ "$HTTP_CODE" != "200" ]; then
    echo "ALERTA: API no responde (HTTP $HTTP_CODE) en $DOMAIN" | \
        mail -s "ALERTA API $DOMAIN" "$MAILTO" 2>/dev/null || \
        echo "[$(date)] ALERTA: API no responde (HTTP $HTTP_CODE)" >> /var/log/api-monitor.log
fi

# Verificar espacio en disco (alerta si >85%)
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | tr -d '%')
if [ "$DISK_USAGE" -gt 85 ]; then
    echo "ALERTA: Disco al ${DISK_USAGE}% en $DOMAIN" | \
        mail -s "ALERTA DISCO $DOMAIN" "$MAILTO" 2>/dev/null || \
        echo "[$(date)] ALERTA: Disco al ${DISK_USAGE}%" >> /var/log/api-monitor.log
fi

# Verificar RAM (alerta si >90%)
MEM_USAGE=$(free | awk '/Mem/{printf("%.0f"), $3/$2 * 100}')
if [ "$MEM_USAGE" -gt 90 ]; then
    echo "ALERTA: RAM al ${MEM_USAGE}% en $DOMAIN" | \
        mail -s "ALERTA RAM $DOMAIN" "$MAILTO" 2>/dev/null || \
        echo "[$(date)] ALERTA: RAM al ${MEM_USAGE}%" >> /var/log/api-monitor.log
fi
