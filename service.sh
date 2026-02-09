#!/bin/bash

# Configuration
SOCKET_PATH="/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock"
PROCESSOR_PATH="/var/www/html/kapjus.kaponline.com.br/src/python/processor.py"
LOG_FILE="/var/www/html/kapjus.kaponline.com.br/storage/processor.log"
HEAL_LOG="/var/www/html/kapjus.kaponline.com.br/storage/heal.log"
USER="www-data"

# Count running instances
count_instances() {
    ps aux | grep -c "[p]rocessor.py" 2>/dev/null || echo "0"
}

# Check socket responsiveness (returns 0 if HTTP 200, 1 otherwise)
check_socket() {
    if [ -S "$SOCKET_PATH" ]; then
        http_code=$(curl --unix-socket "$SOCKET_PATH" -s -o /dev/null -w "%{http_code}" "http://localhost/health" 2>/dev/null)
        if [ "$http_code" = "200" ]; then
            return 0
        fi
    fi
    return 1
}

# Start the processor
do_start() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting processor..." >> "$LOG_FILE"
    
    # Kill any existing process
    pkill -f "processor.py" 2>/dev/null
    sleep 1
    
    # Remove old socket
    rm -f "$SOCKET_PATH" 2>/dev/null
    
    # Start processor as www-data
    su - "$USER" -c "cd /var/www/html/kapjus.kaponline.com.br && nohup python3 $PROCESSOR_PATH >> $LOG_FILE 2>&1 &"
    
    # Wait for socket to be created
    for i in {1..10}; do
        if [ -S "$SOCKET_PATH" ]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] Processor started successfully" >> "$LOG_FILE"
            return 0
        fi
        sleep 1
    done
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: Socket not created" >> "$LOG_FILE"
    return 1
}

# Stop the processor
do_stop() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Stopping processor..." >> "$LOG_FILE"
    pkill -f "processor.py" 2>/dev/null
    rm -f "$SOCKET_PATH" 2>/dev/null
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Processor stopped" >> "$LOG_FILE"
}

# Restart the processor
do_restart() {
    do_stop
    sleep 2
    do_start
}

# Check status
do_status() {
    count=$(count_instances)
    if [ "$count" -eq 1 ]; then
        echo "online"
    elif [ "$count" -eq 0 ]; then
        echo "offline"
    else
        echo "multiple"
    fi
}

# Health check and auto-recovery
do_heal() {
    # Get current timestamp in ISO format (UTC-3 for Brasilia)
    timestamp=$(date -d '+3 hours' '+%Y-%m-%dT%H:%M:%S%z' 2>/dev/null || date '+%Y-%m-%dT%H:%M:%S%z')
    
    # Count instances
    count=$(count_instances)
    
    # Check socket existence
    if [ -S "$SOCKET_PATH" ]; then
        socket_exists="yes"
    else
        socket_exists="no"
    fi
    
    # Check socket responsiveness
    socket_responsive=$(check_socket 2>/dev/null && echo "yes" || echo "no")
    
    # Determine health check result
    if [ "$count" -eq 1 ] && [ "$socket_responsive" = "yes" ]; then
        health_check="pass"
    else
        health_check="fail"
    fi
    
    # Determine status
    if [ "$count" -eq 1 ]; then
        status="online"
    elif [ "$count" -eq 0 ]; then
        status="offline"
    else
        status="multiple"
    fi
    
    # Output detailed diagnostics
    echo "=== KAPJUS HEAL CHECK ==="
    echo "Timestamp: $timestamp"
    echo "Instances: $count"
    echo "Socket exists: $socket_exists"
    echo "Socket responsive: $socket_responsive"
    echo "Health check: $health_check"
    echo "Status: $status"
    
    # Log to heal.log
    {
        echo "=== KAPJUS HEAL CHECK ==="
        echo "Timestamp: $timestamp"
        echo "Instances: $count"
        echo "Socket exists: $socket_exists"
        echo "Socket responsive: $socket_responsive"
        echo "Health check: $health_check"
        echo "Status: $status"
    } >> "$HEAL_LOG"
    
    action="none"
    
    # Perform healing actions if needed
    if [ "$count" -eq 0 ]; then
        echo "Action taken: restarting socket"
        echo "Action taken: restarting socket" >> "$HEAL_LOG"
        do_start >> "$HEAL_LOG" 2>&1
        action="restarted socket"
    elif [ "$count" -ge 2 ]; then
        echo "Action taken: restarting process"
        echo "Action taken: restarting process" >> "$HEAL_LOG"
        pkill -f "processor.py" 2>/dev/null
        sleep 2
        do_start >> "HEAL_LOG" 2>&1
        action="restarted process"
    elif [ "$count" -eq 1 ] && [ "$socket_responsive" != "yes" ]; then
        echo "Action taken: restarting process"
        echo "Action taken: restarting process" >> "$HEAL_LOG"
        do_stop >> "$HEAL_LOG" 2>&1
        sleep 2
        do_start >> "$HEAL_LOG" 2>&1
        action="restarted process"
    else
        echo "Action taken: none needed"
        echo "Action taken: none needed" >> "$HEAL_LOG"
        action="none needed"
    fi
    
    echo "========================="
    echo "=========================" >> "$HEAL_LOG"
}

# Main command handler
case "$1" in
    --start)
        do_start
        ;;
    --stop)
        do_stop
        ;;
    --restart)
        do_restart
        ;;
    --status)
        do_status
        ;;
    --heal)
        do_heal
        ;;
    *)
        echo "Usage: $0 {--start|--stop|--restart|--status|--heal}"
        exit 1
        ;;
esac

exit 0
