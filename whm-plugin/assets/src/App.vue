<template>
  <div id="app">
    <el-container class="app-container">
      <el-header class="app-header">
        <div class="header-content">
          <div class="logo-section">
            <img src="/static/icons/logo.svg" alt="OLS cPanel" class="logo" />
            <h1 class="app-title">OLS Manager</h1>
          </div>
          <div class="header-actions">
            <el-switch
              v-model="isDark"
              class="dark-mode-switch"
              inline-prompt
              :active-icon="Moon"
              :inactive-icon="Sunny"
              @change="toggleDarkMode"
            />
            <el-button type="primary" :icon="Refresh" @click="refreshData">
              Refresh
            </el-button>
          </div>
        </div>
      </el-header>

      <el-container>
        <el-aside width="250px" class="sidebar">
          <el-menu
            :default-active="$route.path"
            router
            class="sidebar-menu"
            :collapse="isCollapsed"
            :collapse-transition="false"
          >
            <el-menu-item index="/dashboard">
              <el-icon><Monitor /></el-icon>
              <span>Dashboard</span>
            </el-menu-item>

            <el-sub-menu index="domains">
              <template #title>
                <el-icon><Globe /></el-icon>
                <span>Domains</span>
              </template>
              <el-menu-item index="/domains/list">
                <el-icon><List /></el-icon>
                <span>All Domains</span>
              </el-menu-item>
              <el-menu-item index="/domains/ssl">
                <el-icon><Lock /></el-icon>
                <span>SSL Certificates</span>
              </el-menu-item>
            </el-sub-menu>

            <el-sub-menu index="server">
              <template #title>
                <el-icon><Setting /></el-icon>
                <span>Server</span>
              </template>
              <el-menu-item index="/server/status">
                <el-icon><Cpu /></el-icon>
                <span>Status</span>
              </el-menu-item>
              <el-menu-item index="/server/performance">
                <el-icon><TrendCharts /></el-icon>
                <span>Performance</span>
              </el-menu-item>
              <el-menu-item index="/server/logs">
                <el-icon><Document /></el-icon>
                <span>Logs</span>
              </el-menu-item>
            </el-sub-menu>

            <el-sub-menu index="configuration">
              <template #title>
                <el-icon><Tools /></el-icon>
                <span>Configuration</span>
              </template>
              <el-menu-item index="/config/server">
                <el-icon><Server /></el-icon>
                <span>Server Settings</span>
              </el-menu-item>
              <el-menu-item index="/config/performance">
                <el-icon><Speed /></el-icon>
                <span>Performance</span>
              </el-menu-item>
              <el-menu-item index="/config/security">
                <el-icon><Shield /></el-icon>
                <span>Security</span>
              </el-menu-item>
              <el-menu-item index="/config/php">
                <el-icon><DocumentCopy /></el-icon>
                <span>PHP Settings</span>
              </el-menu-item>
            </el-sub-menu>

            <el-menu-item index="/backup">
              <el-icon><FolderOpened /></el-icon>
              <span>Backup & Restore</span>
            </el-menu-item>

            <el-menu-item index="/tools">
              <el-icon><Operation /></el-icon>
              <span>Tools</span>
            </el-menu-item>
          </el-menu>

          <div class="sidebar-footer">
            <el-button
              :icon="isCollapsed ? Expand : Fold"
              @click="toggleSidebar"
              text
              size="small"
            >
              {{ isCollapsed ? 'Expand' : 'Collapse' }}
            </el-button>
          </div>
        </el-aside>

        <el-main class="main-content">
          <div class="breadcrumb-container">
            <el-breadcrumb separator="/">
              <el-breadcrumb-item :to="{ path: '/' }">Home</el-breadcrumb-item>
              <el-breadcrumb-item
                v-for="item in breadcrumbs"
                :key="item.path"
                :to="item.path"
              >
                {{ item.title }}
              </el-breadcrumb-item>
            </el-breadcrumb>
          </div>

          <div class="page-container">
            <router-view v-slot="{ Component, route }">
              <transition name="fade" mode="out-in">
                <component :is="Component" :key="route.path" />
              </transition>
            </router-view>
          </div>
        </el-main>
      </el-container>
    </el-container>

    <el-backtop :right="20" :bottom="20" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  Monitor,
  Globe,
  List,
  Lock,
  Setting,
  Cpu,
  TrendCharts,
  Document,
  Tools,
  Server,
  Speed,
  Shield,
  DocumentCopy,
  FolderOpened,
  Operation,
  Refresh,
  Moon,
  Sunny,
  Expand,
  Fold
} from '@element-plus/icons-vue'
import { ElNotification } from 'element-plus'

const route = useRoute()

const isDark = ref(false)
const isCollapsed = ref(false)

const breadcrumbs = computed(() => {
  const pathSegments = route.path.split('/').filter(Boolean)
  const crumbs = []
  
  let currentPath = ''
  pathSegments.forEach(segment => {
    currentPath += `/${segment}`
    crumbs.push({
      path: currentPath,
      title: segment.charAt(0).toUpperCase() + segment.slice(1)
    })
  })
  
  return crumbs
})

const toggleDarkMode = () => {
  document.documentElement.classList.toggle('dark', isDark.value)
  localStorage.setItem('dark-mode', isDark.value)
}

const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value
  localStorage.setItem('sidebar-collapsed', isCollapsed.value)
}

const refreshData = () => {
  ElNotification({
    title: 'Refreshing',
    message: 'Updating dashboard data...',
    type: 'info'
  })
  
  window.location.reload()
}

onMounted(() => {
  const savedDarkMode = localStorage.getItem('dark-mode') === 'true'
  const savedCollapsed = localStorage.getItem('sidebar-collapsed') === 'true'
  
  isDark.value = savedDarkMode
  isCollapsed.value = savedCollapsed
  
  document.documentElement.classList.toggle('dark', savedDarkMode)
})
</script>

<style lang="scss" scoped>
.app-container {
  height: 100vh;
  background-color: var(--el-bg-color);
}

.app-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0;
  border-bottom: 1px solid var(--el-border-color);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 100%;
  padding: 0 20px;
}

.logo-section {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo {
  width: 32px;
  height: 32px;
}

.app-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.dark-mode-switch {
  --el-switch-on-color: #2d3748;
  --el-switch-off-color: #f7fafc;
}

.sidebar {
  background-color: var(--el-menu-bg-color);
  border-right: 1px solid var(--el-border-color);
  display: flex;
  flex-direction: column;
}

.sidebar-menu {
  flex: 1;
  border-right: none;
}

.sidebar-footer {
  padding: 12px;
  border-top: 1px solid var(--el-border-color);
  text-align: center;
}

.main-content {
  padding: 0;
  background-color: var(--el-bg-color-page);
}

.breadcrumb-container {
  padding: 16px 24px;
  background-color: var(--el-bg-color);
  border-bottom: 1px solid var(--el-border-color);
}

.page-container {
  padding: 24px;
  min-height: calc(100vh - 120px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .header-content {
    padding: 0 12px;
  }
  
  .app-title {
    font-size: 16px;
  }
  
  .page-container {
    padding: 16px;
  }
}
</style>
