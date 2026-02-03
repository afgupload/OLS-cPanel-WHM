#!/bin/bash

set -euo pipefail

IFS=$'\n\t'

SCRIPT_VERSION="2.0.0"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_DIR="/var/log/ols-cpanel"
LOG_FILE="${LOG_DIR}/install.log"
BACKUP_DIR="/var/backups/ols-cpanel"
CONFIG_DIR="/etc/ols-cpanel"
OLS_VERSION="1.7.17"
OLS_HOME="/usr/local/lsws"
CPANEL_HOME="/usr/local/cpanel"
TEMP_DIR="/tmp/ols-cpanel-install"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m'

readonly SCRIPT_VERSION SCRIPT_DIR PROJECT_ROOT LOG_DIR LOG_FILE BACKUP_DIR
readonly CONFIG_DIR OLS_VERSION OLS_HOME CPANEL_HOME TEMP_DIR
readonly RED GREEN YELLOW BLUE PURPLE CYAN WHITE NC

init_logging() {
    mkdir -p "${LOG_DIR}" "${BACKUP_DIR}" "${CONFIG_DIR}" "${TEMP_DIR}"
    touch "${LOG_FILE}"
    chmod 600 "${LOG_FILE}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] === OLS cPanel Installer v${SCRIPT_VERSION} ===" >> "${LOG_FILE}"
}

log() {
    local level="$1"
    local message="$2"
    local timestamp="[$(date '+%Y-%m-%d %H:%M:%S')]"
    echo "${timestamp} [${level}] ${message}" >> "${LOG_FILE}"
}

log_info() {
    local msg="$1"
    log "INFO" "${msg}"
    echo -e "${GREEN}[INFO]${NC} ${msg}"
}

log_warn() {
    local msg="$1"
    log "WARN" "${msg}"
    echo -e "${YELLOW}[WARN]${NC} ${msg}"
}

log_error() {
    local msg="$1"
    log "ERROR" "${msg}"
    echo -e "${RED}[ERROR]${NC} ${msg}"
}

log_debug() {
    local msg="$1"
    log "DEBUG" "${msg}"
    if [[ "${DEBUG:-0}" == "1" ]]; then
        echo -e "${BLUE}[DEBUG]${NC} ${msg}"
    fi
}

show_banner() {
    clear
    echo -e "${CYAN}"
    cat << 'EOF'
 _____ _     _ _______ _______  ______  _____ _______ 
   |      |____/ |_____| |_____| |     \   |   |  |  |
 __|__     |    \_     | |     | |_____/ __|__ |  |  |
                                                     
    OpenLiteSpeed Integration for cPanel/WHM
              Modern Edition v2.0.0
EOF
    echo -e "${NC}"
    echo -e "${WHITE}Installation starting at $(date)${NC}"
    echo ""
}

check_requirements() {
    log_info "Checking system requirements..."
    
    local errors=0
    
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root"
        ((errors++))
    fi
    
    if ! command -v whmapi1 &> /dev/null; then
        log_error "WHM API not found. cPanel/WHM is required."
        ((errors++))
    fi
    
    if ! command -v curl &> /dev/null; then
        log_error "curl is required but not installed"
        ((errors++))
    fi
    
    if ! command -v systemctl &> /dev/null; then
        log_error "systemctl is required but not found"
        ((errors++))
    fi
    
    # Detect OS and check version
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    else
        log_error "Cannot detect operating system"
        ((errors++))
        return 1
    fi
    
    # Check supported OS versions
    case "$OS" in
        *"AlmaLinux"*)
            if [[ "${VER%%.*}" -lt 9 ]]; then
                log_error "AlmaLinux 9+ required, found version $VER"
                ((errors++))
            fi
            ;;
        *"Rocky Linux"*)
            if [[ "${VER%%.*}" -lt 9 ]]; then
                log_error "Rocky Linux 9+ required, found version $VER"
                ((errors++))
            fi
            ;;
        *"CloudLinux"*)
            if [[ "${VER%%.*}" -lt 9 ]]; then
                log_error "CloudLinux 9+ required, found version $VER"
                ((errors++))
            fi
            ;;
        *"Ubuntu"*)
            if [[ "${VER%%.*}" -lt 22 ]]; then
                log_error "Ubuntu 22.04+ required, found version $VER"
                ((errors++))
            fi
            ;;
        *"Debian"*)
            if [[ "${VER%%.*}" -lt 12 ]]; then
                log_error "Debian 12+ required, found version $VER"
                ((errors++))
            fi
            ;;
        *)
            log_error "Unsupported operating system: $OS"
            log_error "Supported systems: AlmaLinux 9+, Rocky Linux 9+, CloudLinux 9+, Ubuntu 22.04+, Debian 12+"
            ((errors++))
            ;;
    esac
    
    # Check cPanel version
    local cpanel_version=$(whmapi1 version 2>/dev/null | grep -o '"version":"[^"]*"' | cut -d'"' -f4)
    if [[ -n "$cpanel_version" ]]; then
        local major_version=${cpanel_version%%.*}
        if [[ $major_version -lt 118 ]]; then
            log_error "cPanel/WHM version 118+ required, found version $cpanel_version"
            ((errors++))
        else
            log_info "cPanel/WHM version $cpanel_version detected ✓"
        fi
    fi
    
    # Check PHP version
    if command -v php &> /dev/null; then
        local php_version=$(php -r "echo PHP_VERSION_ID;")
        if [[ $php_version -lt 80100 ]]; then
            log_error "PHP 8.1+ required, found version $(php -v | head -n1)"
            ((errors++))
        else
            log_info "PHP $(php -v | head -n1) detected ✓"
        fi
    else
        log_error "PHP 8.1+ is required but not installed"
        ((errors++))
    fi
    
    local memory_kb=$(grep MemTotal /proc/meminfo | awk '{print $2}')
    local memory_gb=$((memory_kb / 1024 / 1024))
    if [[ $memory_gb -lt 4 ]]; then
        log_warn "System has less than 4GB RAM ($memory_gb GB). Performance may be affected."
    else
        log_info "Memory: ${memory_gb}GB ✓"
    fi
    
    local disk_space=$(df / | awk 'NR==2 {print $4}')
    local disk_gb=$((disk_space / 1024 / 1024))
    if [[ $disk_gb -lt 20 ]]; then
        log_error "Insufficient disk space. At least 20GB required, found ${disk_gb}GB"
        ((errors++))
    else
        log_info "Disk space: ${disk_gb}GB available ✓"
    fi
    
    # Check architecture
    local arch=$(uname -m)
    if [[ "$arch" != "x86_64" ]]; then
        log_warn "Architecture $arch detected. x86_64 is recommended for best performance."
    else
        log_info "Architecture: $arch ✓"
    fi
    
    if [[ $errors -gt 0 ]]; then
        log_error "Requirements check failed with $errors errors"
        exit 1
    fi
    
    log_info "All requirements passed ✓"
    log_info "System: $OS $VER ($arch)"
}

backup_existing_config() {
    log_info "Creating backup of existing configuration..."
    
    local backup_timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_path="${BACKUP_DIR}/backup_${backup_timestamp}"
    
    mkdir -p "${backup_path}"
    
    if [[ -d "${OLS_HOME}" ]]; then
        cp -r "${OLS_HOME}" "${backup_path}/" 2>/dev/null || true
    fi
    
    if systemctl is-active --quiet httpd 2>/dev/null; then
        systemctl status httpd > "${backup_path}/httpd_status.txt" 2>/dev/null || true
    fi
    
    whmapi1 getdomaininfo > "${backup_path}/cpanel_domains.txt" 2>/dev/null || true
    
    log_info "Backup created at ${backup_path}"
    echo "${backup_path}" > "${BACKUP_DIR}/last_backup.txt"
}

install_dependencies() {
    log_info "Installing system dependencies..."
    
    # Detect OS and install dependencies accordingly
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    fi
    
    case "$OS" in
        *"AlmaLinux"*|*"Rocky Linux"*|*"CloudLinux"*)
            log_info "Detected RHEL-based system: $OS $VER"
            dnf update -y
            dnf install -y curl wget unzip tar systemd which git epel-release
            dnf install -y php php-cli php-json php-curl php-mbstring php-xml
            ;;
        *"Ubuntu"*|*"Debian"*)
            log_info "Detected Debian-based system: $OS $VER"
            apt update
            apt upgrade -y
            apt install -y curl wget unzip tar systemd which git software-properties-common
            apt install -y php php-cli php-json php-curl php-mbstring php-xml php-zip
            ;;
        *)
            log_error "Unsupported operating system: $OS"
            log_error "Supported systems: AlmaLinux 9+, Rocky Linux 9+, CloudLinux 9+, Ubuntu 22.04+, Debian 12+"
            exit 1
            ;;
    esac
    
    # Install Composer if not present
    if ! command -v composer &> /dev/null; then
        log_info "Installing Composer..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
        chmod +x /usr/local/bin/composer
    fi
    
    # Install Node.js if not present
    if ! command -v node &> /dev/null; then
        log_info "Installing Node.js..."
        if [[ "$OS" == *"Ubuntu"* ]] || [[ "$OS" == *"Debian"* ]]; then
            curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
            apt install -y nodejs
        else
            curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
            dnf install -y nodejs
        fi
    fi
    
    log_info "Dependencies installed successfully"
}

download_openlitespeed() {
    log_info "Downloading OpenLiteSpeed ${OLS_VERSION}..."
    
    cd "${TEMP_DIR}"
    
    local ols_url="https://openlitespeed.org/packages/openlitespeed-${OLS_VERSION}.tgz"
    
    if ! curl -fsSL "${ols_url}" -o "openlitespeed-${OLS_VERSION}.tgz"; then
        log_error "Failed to download OpenLiteSpeed"
        exit 1
    fi
    
    if ! tar -xzf "openlitespeed-${OLS_VERSION}.tgz"; then
        log_error "Failed to extract OpenLiteSpeed"
        exit 1
    fi
    
    log_info "OpenLiteSpeed downloaded and extracted successfully"
}

install_openlitespeed() {
    log_info "Installing OpenLiteSpeed..."
    
    cd "${TEMP_DIR}/openlitespeed-${OLS_VERSION}"
    
    if [[ -f "${PROJECT_ROOT}/config/ols-install.conf" ]]; then
        cp "${PROJECT_ROOT}/config/ols-install.conf" ./install.conf
    fi
    
    ./install.sh --batch-mode
    
    if [[ $? -ne 0 ]]; then
        log_error "OpenLiteSpeed installation failed"
        exit 1
    fi
    
    systemctl enable lshttpd
    systemctl start lshttpd
    
    if ! systemctl is-active --quiet lshttpd; then
        log_error "OpenLiteSpeed failed to start"
        exit 1
    fi
    
    log_info "OpenLiteSpeed installed successfully ✓"
}

setup_php_integration() {
    log_info "Setting up PHP integration..."
    
    local php_versions=$(whmapi1 php_get_installed_versions | grep -E 'version:' | awk '{print $2}' | tr -d "'" || true)
    
    for version in $php_versions; do
        if [[ -n "$version" ]]; then
            log_info "Configuring PHP $version for OpenLiteSpeed..."
            
            local php_handler="/usr/local/cpanel/bin/php_handler"
            if [[ -x "$php_handler" ]]; then
                "$php_handler" --add "$version" --type=lsapi
            fi
        fi
    done
}

install_whm_plugin() {
    log_info "Installing WHM plugin..."
    
    local plugin_dir="/usr/local/cpanel/whostmgr/docroot/cgi/ols_cpanel"
    mkdir -p "$plugin_dir"
    
    cp -r "${PROJECT_ROOT}/whm-plugin/"* "$plugin_dir/"
    
    chmod 755 "$plugin_dir"/*.cgi
    chmod 644 "$plugin_dir"/*.php
    chmod -R 755 "$plugin_dir/api"
    
    if [[ -f "${PROJECT_ROOT}/composer.json" ]]; then
        cd "$plugin_dir"
        composer install --no-dev --optimize-autoloader
    fi
    
    whmapi1 register_app name=ols_cpanel url=/cgi/ols_cpanel/ols_cpanel.cgi displayname="OLS Manager" icon=ols_cpanel
    
    log_info "WHM plugin installed successfully ✓"
}

setup_configuration() {
    log_info "Setting up configuration..."
    
    cp "${PROJECT_ROOT}/config/httpd_config.conf" "${OLS_HOME}/conf/httpd_config.conf"
    cp "${PROJECT_ROOT}/config/ols-cpanel.yaml" "${CONFIG_DIR}/config.yaml"
    
    mkdir -p "${CONFIG_DIR}/templates"
    cp -r "${PROJECT_ROOT}/config/templates/"* "${CONFIG_DIR}/templates/"
    
    chown -R lsadm:lsadm "${CONFIG_DIR}"
    chmod 640 "${CONFIG_DIR}/config.yaml"
    
    local service_file="${PROJECT_ROOT}/config/ols-cpanel.service"
    if [[ -f "$service_file" ]]; then
        cp "$service_file" /etc/systemd/system/
        systemctl daemon-reload
        systemctl enable ols-cpanel
        systemctl start ols-cpanel
    fi
    
    log_info "Configuration setup completed ✓"
}

migrate_apache_config() {
    log_info "Migrating Apache configuration..."
    
    "${PROJECT_ROOT}/src/Utils/migrate_apache.php" --from-apache --to-ols
    
    if [[ $? -eq 0 ]]; then
        log_info "Apache migration completed successfully ✓"
    else
        log_warn "Apache migration completed with warnings"
    fi
}

stop_apache() {
    log_info "Stopping Apache web server..."
    
    if systemctl is-active --quiet httpd 2>/dev/null; then
        systemctl stop httpd
        systemctl disable httpd
        log_info "Apache stopped and disabled ✓"
    fi
    
    if systemctl is-active --quiet apache2 2>/dev/null; then
        systemctl stop apache2
        systemctl disable apache2
        log_info "Apache2 stopped and disabled ✓"
    fi
}

start_services() {
    log_info "Starting OLS cPanel services..."
    
    systemctl restart lshttpd
    
    if systemctl is-active --quiet ols-cpanel 2>/dev/null; then
        systemctl restart ols-cpanel
    fi
    
    sleep 5
    
    if systemctl is-active --quiet lshttpd; then
        log_info "OpenLiteSpeed is running ✓"
    else
        log_error "OpenLiteSpeed failed to start"
        exit 1
    fi
}

verify_installation() {
    log_info "Verifying installation..."
    
    local errors=0
    
    if ! systemctl is-active --quiet lshttpd; then
        log_error "OpenLiteSpeed is not running"
        ((errors++))
    fi
    
    if [[ ! -f "${CONFIG_DIR}/config.yaml" ]]; then
        log_error "Configuration file not found"
        ((errors++))
    fi
    
    if ! curl -s http://localhost:8088 > /dev/null; then
        log_error "OpenLiteSpeed admin interface not responding"
        ((errors++))
    fi
    
    if [[ $errors -eq 0 ]]; then
        log_info "Installation verification passed ✓"
        return 0
    else
        log_error "Installation verification failed with $errors errors"
        return 1
    fi
}

show_completion() {
    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                    INSTALLATION COMPLETED                 ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${WHITE}OLS cPanel has been successfully installed!${NC}"
    echo ""
    echo -e "${CYAN}Access Information:${NC}"
    echo -e "  • WHM Plugin: ${YELLOW}Main > Plugins > OLS Manager${NC}"
    echo -e "  • OLS Admin:  ${YELLOW}http://$(hostname):7080${NC}"
    echo -e "  • Web Server: ${YELLOW}http://$(hostname)${NC}"
    echo ""
    echo -e "${CYAN}Useful Commands:${NC}"
    echo -e "  • Status:     ${YELLOW}systemctl status lshttpd${NC}"
    echo -e "  • Restart:    ${YELLOW}systemctl restart lshttpd${NC}"
    echo -e "  • Logs:       ${YELLOW}tail -f ${LOG_FILE}${NC}"
    echo ""
    echo -e "${CYAN}Backup Location:${NC}"
    echo -e "  • ${YELLOW}$(cat "${BACKUP_DIR}/last_backup.txt" 2>/dev/null || echo "No backup found")${NC}"
    echo ""
    echo -e "${GREEN}Thank you for using OLS cPanel!${NC}"
    echo ""
}

cleanup() {
    log_info "Cleaning up temporary files..."
    rm -rf "${TEMP_DIR}"
    log_info "Cleanup completed"
}

handle_error() {
    local exit_code=$?
    log_error "Installation failed with exit code $exit_code"
    
    echo ""
    echo -e "${RED}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                    INSTALLATION FAILED                      ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${WHITE}Check the installation log for details:${NC}"
    echo -e "  ${YELLOW}tail -f ${LOG_FILE}${NC}"
    echo ""
    
    cleanup
    exit $exit_code
}

main() {
    trap handle_error ERR
    trap cleanup EXIT
    
    init_logging
    show_banner
    check_requirements
    backup_existing_config
    install_dependencies
    stop_apache
    download_openlitespeed
    install_openlitespeed
    setup_php_integration
    install_whm_plugin
    setup_configuration
    migrate_apache_config
    start_services
    
    if verify_installation; then
        show_completion
        log_info "Installation completed successfully"
    else
        handle_error
    fi
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
