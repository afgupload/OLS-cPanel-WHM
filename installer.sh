#!/bin/bash
set -euo pipefail

SCRIPT_VERSION="1.0.0"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="/var/log/ols-cpanel"
LOG_FILE="${LOG_DIR}/install.log"
BACKUP_DIR="/var/backups/ols-cpanel"
OLS_VERSION="1.7.17"
OLS_HOME="/usr/local/lsws"
CPANEL_HOME="/usr/local/cpanel"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

readonly SCRIPT_VERSION LOG_DIR LOG_FILE BACKUP_DIR OLS_VERSION OLS_HOME CPANEL_HOME
readonly RED GREEN YELLOW BLUE NC

init_logging() {
    mkdir -p "${LOG_DIR}" "${BACKUP_DIR}"
    touch "${LOG_FILE}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] === OpenLiteSpeed cPanel Installer v${SCRIPT_VERSION} ===" >> "${LOG_FILE}"
}

log_info() {
    local msg="$1"
    echo -e "${GREEN}[INFO]${NC} ${msg}" | tee -a "${LOG_FILE}"
}

log_warn() {
    local msg="$1"
    echo -e "${YELLOW}[WARN]${NC} ${msg}" | tee -a "${LOG_FILE}"
}

log_error() {
    local msg="$1"
    echo -e "${RED}[ERROR]${NC} ${msg}" | tee -a "${LOG_FILE}"
}

log_debug() {
    local msg="$1"
    echo "[DEBUG] ${msg}" >> "${LOG_FILE}"
}

fatal_error() {
    local msg="$1"
    log_error "Fatal Error: ${msg}"
    exit 1
}

check_root() {
    if [[ ${EUID} -ne 0 ]]; then
        fatal_error "This script must be run as root"
    fi
}

detect_os_and_distro() {
    log_info "Detecting OS and distribution..."
    
    if [[ ! -f /etc/os-release ]]; then
        fatal_error "Cannot detect OS: /etc/os-release not found"
    fi
    
    source /etc/os-release
    OS_NAME="${ID}"
    OS_VERSION="${VERSION_ID}"
    
    case "${OS_NAME}" in
        rhel|centos|almalinux|rocky)
            PKG_MANAGER="yum"
            ;;
        ubuntu|debian)
            PKG_MANAGER="apt-get"
            ;;
        *)
            fatal_error "Unsupported OS: ${OS_NAME}"
            ;;
    esac
    
    log_info "Detected OS: ${OS_NAME} ${OS_VERSION}"
    log_debug "Package Manager: ${PKG_MANAGER}"
}

detect_cpanel() {
    log_info "Detecting cPanel/WHM installation..."
    
    if [[ ! -d ${CPANEL_HOME} ]]; then
        fatal_error "cPanel not detected at ${CPANEL_HOME}"
    fi
    
    if [[ ! -f ${CPANEL_HOME}/bin/cpanel ]]; then
        fatal_error "cPanel binary not found"
    fi
    
    CPANEL_VERSION=$(${CPANEL_HOME}/bin/cpanel -v 2>/dev/null | awk '{print $NF}' || echo "unknown")
    log_info "cPanel version: ${CPANEL_VERSION}"
    log_debug "cPanel home: ${CPANEL_HOME}"
}

detect_cloudlinux() {
    log_info "Checking for CloudLinux..."
    
    if [[ -f /etc/cloudlinux-release ]]; then
        IS_CLOUDLINUX=1
        CLOUDLINUX_VERSION=$(cat /etc/cloudlinux-release | awk '{print $NF}')
        log_info "CloudLinux detected: ${CLOUDLINUX_VERSION}"
        log_debug "CloudLinux features enabled"
    else
        IS_CLOUDLINUX=0
        log_info "CloudLinux not detected - using standard cPanel"
    fi
}

detect_php_versions() {
    log_info "Detecting installed PHP versions..."
    
    declare -gA PHP_VERSIONS
    local php_bins=()
    
    if [[ ${IS_CLOUDLINUX} -eq 1 ]]; then
        log_debug "Checking Alt-PHP versions (CloudLinux)..."
        if [[ -d /opt/alt/php ]]; then
            for php_dir in /opt/alt/php*/bin/php; do
                if [[ -f ${php_dir} ]]; then
                    php_bins+=("${php_dir}")
                fi
            done
        fi
    fi
    
    log_debug "Checking EA-PHP versions (cPanel)..."
    if [[ -d /opt/cpanel/ea-php* ]]; then
        for php_dir in /opt/cpanel/ea-php*/root/usr/bin/php; do
            if [[ -f ${php_dir} ]]; then
                php_bins+=("${php_dir}")
            fi
        done
    fi
    
    if [[ -f /usr/bin/php ]]; then
        php_bins+=("/usr/bin/php")
    fi
    
    if [[ ${#php_bins[@]} -eq 0 ]]; then
        fatal_error "No PHP installations detected"
    fi
    
    for php_bin in "${php_bins[@]}"; do
        if [[ -x ${php_bin} ]]; then
            local version=
            $(${php_bin} -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "unknown")
            PHP_VERSIONS["${php_bin}"]="${version}"
            log_info "Found PHP ${version} at ${php_bin}"
        fi
    done
    
    local has_php8=0
    for version in "${PHP_VERSIONS[@]}"; do
        if [[ ${version:0:1} -ge 8 ]]; then
            has_php8=1
            break
        fi
    done
    
    if [[ ${has_php8} -eq 0 ]]; then
        fatal_error "PHP 8.0 or higher is required but not found"
    fi
    
    log_info "PHP detection completed successfully"
}

check_system_requirements() {
    log_info "Checking system requirements..."
    
    local required_cmds=("curl" "wget" "tar" "gzip" "systemctl" "openssl")
    
    for cmd in "${required_cmds[@]}"; do
        if ! command -v "${cmd}" &> /dev/null; then
            fatal_error "Required command not found: ${cmd}"
        fi
    done
    
    log_info "All required commands are available"
    
    local min_disk_space=$((2 * 1024 * 1024))
    local available_space=$(df /usr/local | tail -1 | awk '{print $4}')
    
    if [[ ${available_space} -lt ${min_disk_space} ]]; then
        fatal_error "Insufficient disk space. Required: 2GB, Available: $((available_space / 1024 / 1024))GB"
    fi
    
    log_info "Disk space check passed"
}

check_selinux() {
    log_info "Checking SELinux status..."
    
    if command -v getenforce &> /dev/null; then
        local selinux_status=$(getenforce 2>/dev/null || echo "Disabled")
        if [[ "${selinux_status}" != "Disabled" ]]; then
            log_warn "SELinux is ${selinux_status}. This may cause issues with OpenLiteSpeed."
            log_warn "Consider disabling SELinux or configure proper policies"
        else
            log_info "SELinux is disabled"
        fi
    fi
}

check_apache_status() {
    log_info "Checking Apache status..."
    
    if systemctl is-active --quiet httpd; then
        APACHE_RUNNING=1
        log_info "Apache is running"
    else
        APACHE_RUNNING=0
        log_info "Apache is not running"
    fi
}

backup_apache_config() {
    log_info "Backing up Apache configuration..."
    
    local backup_timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_path="${BACKUP_DIR}/apache_backup_${backup_timestamp}"
    
    mkdir -p "${backup_path}"
    
    if [[ -d /etc/httpd/conf ]]; then
        cp -r /etc/httpd/conf "${backup_path}/" || fatal_error "Failed to backup /etc/httpd/conf"
    fi
    
    if [[ -d /etc/httpd/conf.d ]]; then
        cp -r /etc/httpd/conf.d "${backup_path}/" || fatal_error "Failed to backup /etc/httpd/conf.d"
    fi
    
    if [[ -d ${CPANEL_HOME}/etc/apache2/conf.d ]]; then
        cp -r ${CPANEL_HOME}/etc/apache2/conf.d "${backup_path}/" || fatal_error "Failed to backup cPanel Apache configs"
    fi
    
    log_info "Apache configuration backed up to ${backup_path}"
    echo "${backup_path}" > "${BACKUP_DIR}/latest_backup.txt"
    log_debug "Latest backup location saved"
}

download_openlitespeed() {
    log_info "Downloading OpenLiteSpeed ${OLS_VERSION}..."
    
    local ols_url="https://openlitespeed.org/packages/openlitespeed-${OLS_VERSION}.tgz"
    local ols_tarball="/tmp/openlitespeed-${OLS_VERSION}.tgz"
    
    rm -f "${ols_tarball}"
    
    if ! wget -q --show-progress "${ols_url}" -O "${ols_tarball}"; then
        fatal_error "Failed to download OpenLiteSpeed from ${ols_url}"
    fi
    
    if [[ ! -f ${ols_tarball} ]]; then
        fatal_error "Downloaded file not found: ${ols_tarball}"
    fi
    
    log_info "OpenLiteSpeed ${OLS_VERSION} downloaded successfully"
    log_debug "Archive: ${ols_tarball}"
}

verify_openlitespeed_integrity() {
    log_info "Verifying OpenLiteSpeed integrity..."
    
    local ols_tarball="/tmp/openlitespeed-${OLS_VERSION}.tgz"
    
    if ! tar -tzf "${ols_tarball}" &> /dev/null; then
        fatal_error "OpenLiteSpeed archive is corrupted"
    fi
    
    log_info "OpenLiteSpeed integrity verified"
}

install_openlitespeed() {
    log_info "Installing OpenLiteSpeed ${OLS_VERSION}..."
    
    local ols_tarball="/tmp/openlitespeed-${OLS_VERSION}.tgz"
    local extract_dir="/tmp/ols-install-$$"
    
    mkdir -p "${extract_dir}"
    tar -xzf "${ols_tarball}" -C "${extract_dir}"
    
    if [[ ! -d ${extract_dir}/openlitespeed-${OLS_VERSION} ]]; then
        fatal_error "Extracted directory structure incorrect"
    fi
    
    cd "${extract_dir}/openlitespeed-${OLS_VERSION}"
    
    log_debug "Running OpenLiteSpeed install script..."
    if ! ./install.sh &>> "${LOG_FILE}"; then
        fatal_error "OpenLiteSpeed installation failed"
    fi
    
    log_info "OpenLiteSpeed installed successfully"
    log_debug "Installation directory: ${OLS_HOME}"
    
    rm -rf "${extract_dir}"
}

configure_php_for_ols() {
    log_info "Configuring PHP for OpenLiteSpeed..."
    
    if [[ ! -d ${OLS_HOME}/conf ]]; then
        fatal_error "OpenLiteSpeed configuration directory not found"
    fi
    
    local httpd_config_file="${OLS_HOME}/conf/httpd_config.conf"
    
    if [[ ! -f ${httpd_config_file} ]]; then
        log_warn "OpenLiteSpeed httpd_config.conf not found, will be created during service start"
    fi
    
    for php_bin in "${!PHP_VERSIONS[@]}"; do
        local php_version="${PHP_VERSIONS[$php_bin]}"
        log_info "Configuring PHP ${php_version} at ${php_bin}"
        
        local lsphp_link="${OLS_HOME}/fcgi-bin/lsphp${php_version:0:1}${php_version:2:1}"
        mkdir -p "${OLS_HOME}/fcgi-bin"
        
        if [[ ! -L ${lsphp_link} ]]; then
            ln -sf "${php_bin}" "${lsphp_link}" || log_warn "Failed to create symlink for PHP ${php_version}"
        fi
    done
    
    log_info "PHP configuration for OpenLiteSpeed completed"
}

create_ols_systemd_service() {
    log_info "Creating OpenLiteSpeed systemd service..."
    
    local service_file="/etc/systemd/system/lsws.service"
    
    cat > "${service_file}" << 'EOF'
[Unit]
Description=OpenLiteSpeed Web Server
After=network.target
Documentation=https://openlitespeed.org

[Service]
Type=forking
PIDFile=/usr/local/lsws/var/run/lsws.pid
ExecStartPre=/bin/sleep 1
ExecStart=/usr/local/lsws/bin/lswsctrl start
ExecStop=/usr/local/lsws/bin/lswsctrl stop
ExecReload=/usr/local/lsws/bin/lswsctrl reload
Restart=on-failure
RestartSec=10
PrivateTmp=true
NoNewPrivileges=true
LimitNOFILE=655360

[Install]
WantedBy=multi-user.target
EOF
    
    chmod 644 "${service_file}"
    systemctl daemon-reload || fatal_error "Failed to reload systemd daemon"
    
    log_info "OpenLiteSpeed systemd service created"
    log_debug "Service file: ${service_file}"
}

stop_apache() {
    log_info "Stopping Apache..."
    
    if systemctl is-active --quiet httpd; then
        systemctl stop httpd || log_warn "Failed to stop Apache gracefully, attempting force stop"
        sleep 2
        pkill -9 httpd 2>/dev/null || true
        log_info "Apache stopped"
    else
        log_info "Apache is not running"
    fi
}

disable_apache() {
    log_info "Disabling Apache from auto-start..."
    
    systemctl disable httpd || log_warn "Failed to disable Apache from auto-start"
    log_info "Apache disabled from auto-start"
}

start_openlitespeed() {
    log_info "Starting OpenLiteSpeed..."
    
    if ! systemctl start lsws; then
        fatal_error "Failed to start OpenLiteSpeed"
    fi
    
    sleep 3
    
    if ! systemctl is-active --quiet lsws; then
        fatal_error "OpenLiteSpeed failed to start or crashed"
    fi
    
    log_info "OpenLiteSpeed started successfully"
}

enable_openlitespeed() {
    log_info "Enabling OpenLiteSpeed auto-start..."
    
    if ! systemctl enable lsws; then
        fatal_error "Failed to enable OpenLiteSpeed auto-start"
    fi
    
    log_info "OpenLiteSpeed enabled for auto-start"
}

health_check() {
    log_info "Performing health checks..."
    
    if ! systemctl is-active --quiet lsws; then
        log_error "OpenLiteSpeed service is not running"
        return 1
    fi
    
    if ! pgrep -f "openlitespeed" > /dev/null; then
        log_error "OpenLiteSpeed process not found"
        return 1
    fi
    
    if ! curl -s http://127.0.0.1:8080/ > /dev/null 2>&1; then
        log_warn "Could not connect to OpenLiteSpeed on port 8080"
    fi
    
    log_info "Health checks passed"
    return 0
}

generate_summary() {
    log_info "Installation Summary:"
    echo ""
    echo -e "${BLUE}========== Installation Summary ==========${NC}"
    echo -e "OpenLiteSpeed Version:    ${OLS_VERSION}"
    echo -e "Installation Directory:   ${OLS_HOME}"
    echo -e "cPanel Version:           ${CPANEL_VERSION}"
    echo -e "OS:                       ${OS_NAME} ${OS_VERSION}"
    echo -e "CloudLinux:               $([ ${IS_CLOUDLINUX} -eq 1 ] && echo 'Yes' || echo 'No')"
    echo -e "PHP Versions Detected:    $(for v in "${PHP_VERSIONS[@]}"; do echo -n "$v "; done)"
    echo -e "Log File:                 ${LOG_FILE}"
    echo -e "Backup Location:          ${BACKUP_DIR}"
    echo -e "${BLUE}=========================================${NC}"
    echo ""
}

main() {
    init_logging
    check_root
    
    log_info "Starting OpenLiteSpeed installation process..."
    
    detect_os_and_distro
    detect_cpanel
    detect_cloudlinux
    detect_php_versions
    check_system_requirements
    check_selinux
    check_apache_status
    
    backup_apache_config
    download_openlitespeed
    verify_openlitespeed_integrity
    install_openlitespeed
    configure_php_for_ols
    create_ols_systemd_service
    
    stop_apache
    disable_apache
    start_openlitespeed
    enable_openlitespeed
    
    if health_check; then
        generate_summary
        log_info "Installation completed successfully!"
        exit 0
    else
        log_error "Health check failed - installation may be incomplete"
        exit 1
    fi
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
}