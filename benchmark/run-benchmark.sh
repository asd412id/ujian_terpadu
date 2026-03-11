#!/bin/bash
# =============================================================================
# Benchmark Runner — Ujian Terpadu
# Jalankan dari root project: ./benchmark/run-benchmark.sh [skenario]
# =============================================================================

set -e

BASE_URL="${BASE_URL:-http://localhost:8000}"
RESULTS_DIR="benchmark/results"
CONTAINER_APP="ujian_app"
CONTAINER_MYSQL="ujian_mysql"
CONTAINER_REDIS="ujian_redis"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

print_header() {
    echo -e "\n${CYAN}======================================${NC}"
    echo -e "${CYAN}  $1${NC}"
    echo -e "${CYAN}======================================${NC}\n"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Collect system stats
collect_stats() {
    local prefix=$1
    echo -e "\n--- Docker Stats ---"
    docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.PIDs}}" 2>/dev/null | tee "${RESULTS_DIR}/${prefix}-docker-stats.txt"

    echo -e "\n--- MySQL Processlist ---"
    docker exec ${CONTAINER_MYSQL} mysql -u root -p"$(docker exec ${CONTAINER_MYSQL} printenv MYSQL_ROOT_PASSWORD 2>/dev/null)" -e "SHOW PROCESSLIST;" 2>/dev/null | tail -20 | tee "${RESULTS_DIR}/${prefix}-mysql-processlist.txt" || true

    echo -e "\n--- MySQL Status ---"
    docker exec ${CONTAINER_MYSQL} mysql -u root -p"$(docker exec ${CONTAINER_MYSQL} printenv MYSQL_ROOT_PASSWORD 2>/dev/null)" -e "SHOW GLOBAL STATUS WHERE Variable_name IN ('Connections','Threads_connected','Threads_running','Slow_queries','Questions');" 2>/dev/null | tee "${RESULTS_DIR}/${prefix}-mysql-status.txt" || true

    echo -e "\n--- Redis Info ---"
    docker exec ${CONTAINER_REDIS} redis-cli INFO memory 2>/dev/null | grep -E "used_memory_human|maxmemory_human|mem_fragmentation" | tee "${RESULTS_DIR}/${prefix}-redis-memory.txt" || true
    docker exec ${CONTAINER_REDIS} redis-cli INFO clients 2>/dev/null | grep "connected_clients" | tee -a "${RESULTS_DIR}/${prefix}-redis-memory.txt" || true
}

# Monitor docker stats continuously in background
start_monitor() {
    local prefix=$1
    local monitor_file="${RESULTS_DIR}/${prefix}-monitor.csv"
    echo "timestamp,container,cpu,mem_usage,mem_limit,mem_pct,pids" > "$monitor_file"

    while true; do
        docker stats --no-stream --format "{{.Name}},{{.CPUPerc}},{{.MemUsage}},{{.MemPerc}},{{.PIDs}}" 2>/dev/null | while read line; do
            echo "$(date +%H:%M:%S),${line}" >> "$monitor_file"
        done
        sleep 5
    done &
    MONITOR_PID=$!
    echo $MONITOR_PID
}

stop_monitor() {
    if [ -n "$1" ]; then
        kill $1 2>/dev/null || true
        wait $1 2>/dev/null || true
    fi
}

# Verify prerequisites
check_prereqs() {
    print_header "Checking Prerequisites"

    if ! command -v k6 &>/dev/null; then
        print_error "k6 not installed. Install: https://grafana.com/docs/k6/latest/set-up/install-k6/"
        exit 1
    fi
    print_success "k6 $(k6 version 2>&1 | head -1)"

    if [ ! -f "benchmark/tokens.json" ]; then
        print_error "benchmark/tokens.json not found!"
        echo "  Run: docker exec ${CONTAINER_APP} php artisan benchmark:seed"
        echo "  Then: docker exec ${CONTAINER_APP} php artisan benchmark:export-tokens"
        exit 1
    fi
    local token_count=$(python3 -c "import json; print(json.load(open('benchmark/tokens.json'))['total'])" 2>/dev/null || echo "?")
    print_success "tokens.json found (${token_count} tokens)"

    # Health check
    local health=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/up" 2>/dev/null || echo "000")
    if [ "$health" = "200" ]; then
        print_success "Server healthy at ${BASE_URL}"
    else
        print_error "Server not responding at ${BASE_URL} (status: ${health})"
        exit 1
    fi

    mkdir -p "$RESULTS_DIR"
    print_success "Results directory ready"
}

# Run individual scenarios
run_healthcheck() {
    print_header "Skenario A: Healthcheck Throughput"
    collect_stats "pre-healthcheck"

    local MPID=$(start_monitor "healthcheck")
    k6 run --env BASE_URL="${BASE_URL}" benchmark/k6-healthcheck.js 2>&1 | tee "${RESULTS_DIR}/healthcheck-output.txt"
    stop_monitor $MPID

    collect_stats "post-healthcheck"
    print_success "Healthcheck benchmark selesai"
}

run_sync_jawaban() {
    print_header "Skenario B: Sync Jawaban Concurrent"
    collect_stats "pre-sync"

    local MPID=$(start_monitor "sync")
    k6 run --env BASE_URL="${BASE_URL}" benchmark/k6-sync-jawaban.js 2>&1 | tee "${RESULTS_DIR}/sync-jawaban-output.txt"
    stop_monitor $MPID

    collect_stats "post-sync"
    print_success "Sync jawaban benchmark selesai"
}

run_realistic() {
    print_header "Skenario C: Realistic Flow"
    print_warning "Skenario ini memakan waktu ~7 menit"
    collect_stats "pre-realistic"

    local MPID=$(start_monitor "realistic")
    k6 run --env BASE_URL="${BASE_URL}" benchmark/k6-realistic-flow.js 2>&1 | tee "${RESULTS_DIR}/realistic-flow-output.txt"
    stop_monitor $MPID

    collect_stats "post-realistic"
    print_success "Realistic flow benchmark selesai"
}

run_spike() {
    print_header "Skenario D: Spike Test"
    print_warning "Test ini akan memberikan beban sangat tinggi!"
    collect_stats "pre-spike"

    local MPID=$(start_monitor "spike")
    k6 run --env BASE_URL="${BASE_URL}" benchmark/k6-spike-test.js 2>&1 | tee "${RESULTS_DIR}/spike-test-output.txt"
    stop_monitor $MPID

    collect_stats "post-spike"
    print_success "Spike test benchmark selesai"
}

# Main
case "${1:-all}" in
    healthcheck|a)
        check_prereqs
        run_healthcheck
        ;;
    sync|b)
        check_prereqs
        run_sync_jawaban
        ;;
    realistic|c)
        check_prereqs
        run_realistic
        ;;
    spike|d)
        check_prereqs
        run_spike
        ;;
    all)
        check_prereqs
        print_header "Running ALL Benchmark Scenarios"
        echo -e "Start time: $(date)\n"

        run_healthcheck
        echo -e "\n${YELLOW}Cooling down 30s before next scenario...${NC}\n"
        sleep 30

        run_sync_jawaban
        echo -e "\n${YELLOW}Cooling down 30s before next scenario...${NC}\n"
        sleep 30

        run_realistic
        echo -e "\n${YELLOW}Cooling down 30s before next scenario...${NC}\n"
        sleep 30

        run_spike

        print_header "ALL BENCHMARKS COMPLETE"
        echo -e "End time: $(date)"
        echo -e "Results saved in: ${RESULTS_DIR}/"
        echo -e "\nFiles:"
        ls -la "${RESULTS_DIR}/" 2>/dev/null
        ;;
    *)
        echo "Usage: $0 [healthcheck|sync|realistic|spike|all]"
        echo "  healthcheck (a) - Raw throughput test"
        echo "  sync (b)        - Concurrent sync jawaban (main test)"
        echo "  realistic (c)   - Full user flow simulation"
        echo "  spike (d)       - Spike/stress test"
        echo "  all             - Run all scenarios sequentially"
        exit 1
        ;;
esac
