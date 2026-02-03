#!/bin/bash

# Logging function
log() {
    local message="${1}"
    echo "$(date +'%Y-%m-%d %H:%M:%S') - ${message}"
}

# Function for colored output
colorEcho() {
    local color="${1}"
    shift
    echo -e "\033[${color}m$*\033[0m"
}

# Error handling function
handle_error() {
    local exit_code="${1}"
    local message="${2}"
    if [ ${exit_code} -ne 0 ]; then
        colorEcho "31" "Error: ${message}"
        exit ${exit_code}
    fi
}

# System check function
check_system() {
    log "Checking system requirements..."
    # Example checks (e.g., checking if certain commands exist)
    for command in curl wget git; do
        command -v ${command} >/dev/null 2>&1 || handle_error 1 "${command} command not found"
    done
    log "All system checks passed."
}

# Example usage of the logging and error handling functions
log "Script started."
check_system()
log "Script completed successfully."