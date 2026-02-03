#!/bin/bash

# Function to check OS compatibility
check_os() {
    os=$(grep -oP '(?<=^NAME=).*' /etc/os-release | tr -d '"')
    compatible=('AlmaLinux' 'Rocky Linux' 'CentOS')
    for c in "${compatible[@]}"; do
        if [[ "$os" == *"$c"* ]]; then
            echo "OS Compatibility Check: Passed ($os)"
            return 0
        fi
    done
    echo "OS Compatibility Check: Failed ($os)" >&2
    return 1
}

# Function to check cPanel version
check_cp_version() {
    if [ -f "/usr/local/cpanel/version" ]; then
        version=$(cat /usr/local/cpanel/version)
        echo "cPanel Version: $version"
    else
        echo "cPanel version file not found" >&2
    fi
}

# Function to check API availability
check_api_availability() {
    if curl -s http://localhost:2087/ | grep -q "cPanel"; then
        echo "cPanel API is available"
    else
        echo "cPanel API is not available" >&2
    fi
}

# Function to check SELinux status
check_selinux() {
    sel_status=$(sestatus | grep 'SELinux status' | awk '{print $3}')
    echo "SELinux Status: $sel_status"
}

# Function to check PHP versions
check_php_versions() {
    ea_php_version=$(php -v | grep 'PHP' | head -n 1)
    alt_php_version=$(command -v php-alt && php-alt -v | grep 'PHP' | head -n 1)
    echo "EA-PHP Version: $ea_php_version"
    echo "Alt-PHP Version: $alt_php_version"
}

# Function to check systemd availability
check_systemd() {
    if command -v systemctl >/dev/null 2>&1; then
        echo "systemd is available"
    else
        echo "systemd is not available" >&2
    fi
}

# Function to check user/group existence
check_user_group() {
    critical_users=("root" "nobody")
    echo "Checking critical user and group existence..."
    for user in "${critical_users[@]}"; do
        if id "$user" &>/dev/null; then
            echo "User exists: $user"
        else
            echo "User does not exist: $user" >&2
        fi
    done
}

# Function to check package manager
check_package_manager() {
    if command -v yum >/dev/null 2>&1; then
        echo "Package Manager: yum"
    elif command -v apt >/dev/null 2>&1; then
        echo "Package Manager: apt"
    else
        echo "No recognized package manager found" >&2
    fi
}

# Function to check network connectivity and proxy detection
check_network() {
    if curl -s --head http://google.com | grep "200 OK" >/dev/null; then
        echo "Network Connectivity: OK"
    else
        echo "Network Connectivity: Failed" >&2
    fi

    if [[ ! -z "$http_proxy" ]]; then
        echo "Proxy detected: $http_proxy"
    else
        echo "No proxy detected"
    fi
}

# Main script execution
check_os
check_cp_version
check_api_availability
check_selinux
check_php_versions
check_systemd
check_user_group
check_package_manager
check_network
