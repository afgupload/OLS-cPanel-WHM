# Plan: Create a Robust Installer for OLS-cPanel-WHM

## Goal
To completely overhaul the `installer/install.sh` script, making it a robust, self-contained, and reliable tool for both automatic (remote) and manual (local) installations. This plan addresses critical failures in path detection, dependency management, and error handling.

## Key Decisions
1.  **Dual-Mode Execution:** The installer will support both a one-liner `curl | bash` installation and a local execution from a cloned repository.
2.  **Bootstrapper for Remote Install:** For `curl` installations, the script will first download the entire repository to a temporary directory and then re-launch itself from there to ensure all paths are valid.
3.  **Pre-built Frontend Assets:** The installation will **not** build the frontend on the target server. It will use pre-compiled assets from a `dist/` directory committed to the repository, eliminating `node` and `npm` as server dependencies.
4.  **Automatic Rollback on Failure:** The installer will implement a comprehensive, state-aware rollback mechanism. If any step fails, the script will automatically undo all changes to return the system to its pre-installation state.

## Implementation Plan

### Part 1: Prepare Frontend Assets (To be done by implementation agent first)
1.  Navigate to the `whm-plugin/assets/` directory.
2.  Run `npm install` to install developer dependencies.
3.  Run `npm run build` to generate the compiled assets in a `dist/` folder.
4.  Add, commit, and push the `whm-plugin/assets/dist/` directory to the `main` branch of the repository.

### Part 2: Rewrite `installer/install.sh`
The `install.sh` script will be refactored into a stateful, two-stage installer.

**Stage 1: Bootstrapper Logic**
1.  At the very beginning of the script, detect if it is running in a non-interactive shell (indicative of `curl | bash`).
    ```bash
    if ! [ -t 0 ]; then
        echo "Bootstrapping remote installation..."
        REPO_URL="https://github.com/afgupload/OLS-cPanel-WHM.git"
        TMP_DIR="/tmp/ols-cpanel-installer-$$"
        # Ensure git is installed or install it
        if ! command -v git &> /dev/null; then
            # Simple detection for yum/dnf or apt
            if command -v dnf &> /dev/null; then
                dnf install -y git
            elif command -v apt-get &> /dev/null; then
                apt-get update && apt-get install -y git
            else
                echo "Error: git is not installed and could not be installed automatically. Please install git and try again." >&2
                exit 1
            fi
        fi
        
        git clone --depth 1 "$REPO_URL" "$TMP_DIR"
        cd "$TMP_DIR"
        # Re-execute the script from the local clone
        exec bash ./installer/install.sh "$@"
    fi
    ```
2.  The rest of the script will now only execute in a context where all project files are present locally.

**Stage 2: Main Installer & Rollback Logic**
1.  **Error Handling and State:**
    *   Use `set -euo pipefail`.
    *   Define a state file, e.g., `INSTALL_STATE_FILE="/etc/ols-cpanel/install.state"`.
    *   Implement a `trap 'rollback' ERR` command at the start.
    *   The `rollback` function will read the `$INSTALL_STATE_FILE` and undo steps in reverse. For example:
        *   If state was `APACHE_STOPPED`, it will restart and re-enable Apache.
        *   If state was `OLS_INSTALLED`, it will run the OLS uninstaller.
        *   It will restore all backups.
    *   Create helper functions like `update_state "DEPS_INSTALLED"` to write to the state file after each successful major step.

2.  **Installation Steps:**
    *   **`main()` function:** Orchestrate the entire flow.
    *   **`check_requirements()`:** Verify root access and cPanel/WHM presence.
    *   **`install_dependencies()`:** Install `curl`, `php`, `composer` (if not present) based on detected OS. **DO NOT** install `node` or `npm`.
    *   **`backup_system()`:** Create a full backup of Apache configs and other critical files. After this succeeds, `update_state "BACKUP_COMPLETE"`.
    *   **`stop_apache()`:** Stop and disable the Apache service. `update_state "APACHE_STOPPED"`.
    *   **`install_openlitespeed()`:** Download and install OLS. `update_state "OLS_INSTALLED"`.
    *   **`install_whm_plugin()`:**
        *   Copy files from `whm-plugin/` to the cPanel directory.
        *   Crucially, copy the pre-built assets from `whm-plugin/assets/dist/` into the correct location.
        *   Run `composer install --no-dev` inside the plugin directory.
        *   `update_state "PLUGIN_INSTALLED"`.
    *   **`finalize_installation()`:** Restart services, verify installation. `update_state "COMPLETED"`.
    *   **`cleanup()`:** Remove temporary files and the state file upon successful completion.

### Part 3: Update Documentation
1.  **`README.md` & `docs/installation.md`:**
    *   **Quick Install:** The `curl ... | bash` command remains the same, as it will now work correctly.
    *   **Manual Install:** Update the instructions to be simple:
        ```markdown
        ### Manual Installation
        1. Clone the repository:
           \`\`\`bash
           git clone https://github.com/afgupload/OLS-cPanel-WHM.git
           \`\`\`
        2. Navigate into the directory:
           \`\`\`bash
           cd OLS-cPanel-WHM
           \`\`\`
        3. Run the installer as root:
           \`\`\`bash
           sudo ./installer/install.sh
           \`\`\`
        ```
    *   Remove any instructions telling the user to run `composer install` or `npm install` themselves.

## Validation Plan
The implementation agent must:
1.  Verify that the `whm-plugin/assets/dist/` directory exists in the repo.
2.  Test the remote installation using the `curl | bash` one-liner on a clean cPanel server.
3.  Test the manual installation by cloning the repo and running the script.
4.  Test the rollback functionality by inserting an `exit 1` command into the `install.sh` script at various stages and verifying that the system is restored to its original state each time.
