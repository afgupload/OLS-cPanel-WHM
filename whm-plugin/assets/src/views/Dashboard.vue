<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <h1>Dashboard</h1>
      <p class="dashboard-subtitle">OpenLiteSpeed Server Overview</p>
    </div>

    <el-row :gutter="24" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6" v-for="stat in serverStats" :key="stat.key">
        <el-card class="stat-card" :class="stat.type">
          <div class="stat-content">
            <div class="stat-icon">
              <el-icon :size="32">
                <component :is="stat.icon" />
              </el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stat.value }}</div>
              <div class="stat-label">{{ stat.label }}</div>
              <div class="stat-change" :class="stat.changeType">
                <el-icon><ArrowUp v-if="stat.changeType === 'positive'" /><ArrowDown v-else /></el-icon>
                {{ stat.change }}
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="24" class="charts-row">
      <el-col :xs="24" :lg="16">
        <el-card class="chart-card">
          <template #header>
            <div class="card-header">
              <h3>Performance Metrics</h3>
              <el-select v-model="selectedTimeRange" size="small" style="width: 120px">
                <el-option label="1 Hour" value="1h" />
                <el-option label="6 Hours" value="6h" />
                <el-option label="24 Hours" value="24h" />
                <el-option label="7 Days" value="7d" />
              </el-select>
            </div>
          </template>
          <v-chart class="chart" :option="performanceChartOption" autoresize />
        </el-card>
      </el-col>

      <el-col :xs="24" :lg="8">
        <el-card class="chart-card">
          <template #header>
            <h3>Resource Usage</h3>
          </template>
          <v-chart class="chart" :option="resourceChartOption" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="24" class="info-row">
      <el-col :xs="24" :lg="12">
        <el-card class="info-card">
          <template #header>
            <div class="card-header">
              <h3>Server Information</h3>
              <el-button type="primary" size="small" @click="refreshServerInfo">
                <el-icon><Refresh /></el-icon>
                Refresh
              </el-button>
            </div>
          </template>
          <div class="server-info">
            <div class="info-item">
              <span class="info-label">Version:</span>
              <span class="info-value">{{ serverInfo.version }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Uptime:</span>
              <span class="info-value">{{ serverInfo.uptime }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Status:</span>
              <el-tag :type="serverInfo.status === 'running' ? 'success' : 'danger'">
                {{ serverInfo.status }}
              </el-tag>
            </div>
            <div class="info-item">
              <span class="info-label">Last Restart:</span>
              <span class="info-value">{{ serverInfo.lastRestart }}</span>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="24" :lg="12">
        <el-card class="info-card">
          <template #header>
            <div class="card-header">
              <h3>Recent Activity</h3>
              <el-button type="text" size="small" @click="viewAllLogs">
                View All
              </el-button>
            </div>
          </template>
          <div class="activity-list">
            <div v-for="activity in recentActivities" :key="activity.id" class="activity-item">
              <div class="activity-icon">
                <el-icon :color="activity.color">
                  <component :is="activity.icon" />
                </el-icon>
              </div>
              <div class="activity-content">
                <div class="activity-title">{{ activity.title }}</div>
                <div class="activity-time">{{ activity.time }}</div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="24" class="domains-row">
      <el-col :span="24">
        <el-card class="domains-card">
          <template #header>
            <div class="card-header">
              <h3>Domain Overview</h3>
              <div class="header-actions">
                <el-button type="text" @click="viewAllDomains">
                  View All Domains
                </el-button>
                <el-button type="primary" @click="addDomain">
                  <el-icon><Plus /></el-icon>
                  Add Domain
                </el-button>
              </div>
            </div>
          </template>
          <el-table :data="domainOverview" style="width: 100%">
            <el-table-column prop="domain" label="Domain" min-width="200">
              <template #default="{ row }">
                <div class="domain-cell">
                  <el-icon class="domain-icon" :color="row.statusColor">
                    <component :is="row.statusIcon" />
                  </el-icon>
                  {{ row.domain }}
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="user" label="User" width="120" />
            <el-table-column prop="phpVersion" label="PHP" width="80" />
            <el-table-column prop="sslStatus" label="SSL" width="100">
              <template #default="{ row }">
                <el-tag :type="row.sslType" size="small">
                  {{ row.sslStatus }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="status" label="Status" width="100">
              <template #default="{ row }">
                <el-tag :type="row.statusType" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="lastUpdated" label="Last Updated" width="150" />
            <el-table-column label="Actions" width="120">
              <template #default="{ row }">
                <el-button type="text" size="small" @click="editDomain(row)">
                  Edit
                </el-button>
                <el-button type="text" size="small" @click="viewDomain(row)">
                  View
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElNotification } from 'element-plus'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, PieChart } from 'echarts/charts'
import {
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent
} from 'echarts/components'
import {
  Monitor,
  Globe,
  Lock,
  ArrowUp,
  ArrowDown,
  Refresh,
  Plus,
  Check,
  Warning,
  Close
} from '@element-plus/icons-vue'

use([
  CanvasRenderer,
  LineChart,
  PieChart,
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent
])

const router = useRouter()

const selectedTimeRange = ref('24h')

const serverStats = reactive([
  {
    key: 'domains',
    label: 'Total Domains',
    value: '156',
    change: '+12',
    changeType: 'positive',
    icon: Globe,
    type: 'primary'
  },
  {
    key: 'ssl',
    label: 'SSL Certificates',
    value: '142',
    change: '+8',
    changeType: 'positive',
    icon: Lock,
    type: 'success'
  },
  {
    key: 'connections',
    label: 'Active Connections',
    value: '1,247',
    change: '+5.2%',
    changeType: 'positive',
    icon: Monitor,
    type: 'warning'
  },
  {
    key: 'uptime',
    label: 'Server Uptime',
    value: '99.9%',
    change: '+0.1%',
    changeType: 'positive',
    icon: Monitor,
    type: 'info'
  }
])

const serverInfo = reactive({
  version: '1.7.17',
  uptime: '15 days, 7 hours',
  status: 'running',
  lastRestart: '2024-01-15 10:30:00'
})

const recentActivities = reactive([
  {
    id: 1,
    title: 'SSL certificate renewed for example.com',
    time: '2 minutes ago',
    icon: Lock,
    color: '#67C23A'
  },
  {
    id: 2,
    title: 'New domain added: newdomain.com',
    time: '15 minutes ago',
    icon: Globe,
    color: '#409EFF'
  },
  {
    id: 3,
    title: 'Server configuration updated',
    time: '1 hour ago',
    icon: Monitor,
    color: '#E6A23C'
  },
  {
    id: 4,
    title: 'PHP version updated for test.com',
    time: '2 hours ago',
    icon: Check,
    color: '#67C23A'
  },
  {
    id: 5,
    title: 'Failed SSL renewal for expired.com',
    time: '3 hours ago',
    icon: Warning,
    color: '#E6A23C'
  }
])

const domainOverview = reactive([
  {
    domain: 'example.com',
    user: 'user1',
    phpVersion: '8.1',
    sslStatus: 'Valid',
    sslType: 'success',
    status: 'Active',
    statusType: 'success',
    statusIcon: Check,
    statusColor: '#67C23A',
    lastUpdated: '2024-01-20 14:30'
  },
  {
    domain: 'test.org',
    user: 'user2',
    phpVersion: '8.0',
    sslStatus: 'Expiring',
    sslType: 'warning',
    status: 'Active',
    statusType: 'success',
    statusIcon: Check,
    statusColor: '#67C23A',
    lastUpdated: '2024-01-20 13:15'
  },
  {
    domain: 'expired.net',
    user: 'user3',
    phpVersion: '7.4',
    sslStatus: 'Expired',
    sslType: 'danger',
    status: 'Active',
    statusType: 'warning',
    statusIcon: Warning,
    statusColor: '#E6A23C',
    lastUpdated: '2024-01-20 12:00'
  },
  {
    domain: 'suspended.com',
    user: 'user4',
    phpVersion: '8.2',
    sslStatus: 'None',
    sslType: 'info',
    status: 'Suspended',
    statusType: 'danger',
    statusIcon: Close,
    statusColor: '#F56C6C',
    lastUpdated: '2024-01-19 18:45'
  }
])

const performanceChartOption = reactive({
  title: {
    text: 'Server Performance',
    left: 'center',
    textStyle: {
      fontSize: 14,
      fontWeight: 'normal'
    }
  },
  tooltip: {
    trigger: 'axis',
    axisPointer: {
      type: 'cross'
    }
  },
  legend: {
    data: ['CPU Usage', 'Memory Usage', 'Connections'],
    bottom: 0
  },
  grid: {
    left: '3%',
    right: '4%',
    bottom: '10%',
    containLabel: true
  },
  xAxis: {
    type: 'category',
    boundaryGap: false,
    data: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '24:00']
  },
  yAxis: {
    type: 'value',
    max: 100,
    axisLabel: {
      formatter: '{value}%'
    }
  },
  series: [
    {
      name: 'CPU Usage',
      type: 'line',
      smooth: true,
      data: [30, 25, 45, 60, 55, 40, 35],
      itemStyle: {
        color: '#409EFF'
      }
    },
    {
      name: 'Memory Usage',
      type: 'line',
      smooth: true,
      data: [45, 40, 50, 65, 60, 55, 50],
      itemStyle: {
        color: '#67C23A'
      }
    },
    {
      name: 'Connections',
      type: 'line',
      smooth: true,
      data: [800, 600, 1200, 1800, 1500, 1000, 900],
      itemStyle: {
        color: '#E6A23C'
      }
    }
  ]
})

const resourceChartOption = reactive({
  title: {
    text: 'Resource Distribution',
    left: 'center',
    textStyle: {
      fontSize: 14,
      fontWeight: 'normal'
    }
  },
  tooltip: {
    trigger: 'item',
    formatter: '{a} <br/>{b}: {c} ({d}%)'
  },
  legend: {
    orient: 'vertical',
    left: 'left',
    bottom: 'center'
  },
  series: [
    {
      name: 'Resource Usage',
      type: 'pie',
      radius: ['40%', '70%'],
      avoidLabelOverlap: false,
      label: {
        show: false,
        position: 'center'
      },
      emphasis: {
        label: {
          show: true,
          fontSize: 16,
          fontWeight: 'bold'
        }
      },
      labelLine: {
        show: false
      },
      data: [
        { value: 1048, name: 'Static Files' },
        { value: 735, name: 'PHP Requests' },
        { value: 580, name: 'Database' },
        { value: 484, name: 'API Calls' },
        { value: 300, name: 'Other' }
      ]
    }
  ]
})

const refreshServerInfo = () => {
  ElNotification({
    title: 'Refreshing',
    message: 'Server information updated',
    type: 'success'
  })
}

const viewAllLogs = () => {
  router.push('/server/logs')
}

const viewAllDomains = () => {
  router.push('/domains/list')
}

const addDomain = () => {
  router.push('/domains/add')
}

const editDomain = (domain) => {
  router.push(`/domains/edit/${domain.domain}`)
}

const viewDomain = (domain) => {
  router.push(`/domains/view/${domain.domain}`)
}

onMounted(() => {
  ElNotification({
    title: 'Welcome',
    message: 'Dashboard loaded successfully',
    type: 'success',
    duration: 2000
  })
})
</script>

<style lang="scss" scoped>
.dashboard {
  .dashboard-header {
    margin-bottom: 24px;
    
    h1 {
      margin: 0 0 8px 0;
      font-size: 28px;
      font-weight: 600;
      color: var(--el-text-color-primary);
    }
    
    .dashboard-subtitle {
      margin: 0;
      color: var(--el-text-color-regular);
      font-size: 14px;
    }
  }

  .stats-row {
    margin-bottom: 24px;
  }

  .stat-card {
    height: 120px;
    
    &.primary {
      border-left: 4px solid #409EFF;
    }
    
    &.success {
      border-left: 4px solid #67C23A;
    }
    
    &.warning {
      border-left: 4px solid #E6A23C;
    }
    
    &.info {
      border-left: 4px solid #909399;
    }
  }

  .stat-content {
    display: flex;
    align-items: center;
    height: 100%;
    gap: 16px;
  }

  .stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background-color: var(--el-bg-color-page);
    color: var(--el-color-primary);
  }

  .stat-info {
    flex: 1;
  }

  .stat-value {
    font-size: 24px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    line-height: 1;
  }

  .stat-label {
    font-size: 14px;
    color: var(--el-text-color-regular);
    margin: 4px 0;
  }

  .stat-change {
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 2px;
    
    &.positive {
      color: var(--el-color-success);
    }
    
    &.negative {
      color: var(--el-color-danger);
    }
  }

  .charts-row {
    margin-bottom: 24px;
  }

  .chart-card {
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      
      h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
      }
    }
    
    .chart {
      height: 300px;
    }
  }

  .info-row {
    margin-bottom: 24px;
  }

  .info-card {
    height: 400px;
    
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      
      h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
      }
    }
  }

  .server-info {
    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid var(--el-border-color-lighter);
      
      &:last-child {
        border-bottom: none;
      }
    }
    
    .info-label {
      font-weight: 500;
      color: var(--el-text-color-regular);
    }
    
    .info-value {
      color: var(--el-text-color-primary);
    }
  }

  .activity-list {
    max-height: 300px;
    overflow-y: auto;
  }

  .activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
    
    &:last-child {
      border-bottom: none;
    }
  }

  .activity-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: var(--el-bg-color-page);
  }

  .activity-content {
    flex: 1;
  }

  .activity-title {
    font-size: 14px;
    color: var(--el-text-color-primary);
    margin-bottom: 4px;
  }

  .activity-time {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
  }

  .domains-card {
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      
      h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
      }
      
      .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
      }
    }
  }

  .domain-cell {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .domain-icon {
    font-size: 16px;
  }
}

@media (max-width: 768px) {
  .dashboard {
    .stat-card {
      height: 100px;
      margin-bottom: 16px;
    }
    
    .stat-value {
      font-size: 20px;
    }
    
    .chart {
      height: 250px;
    }
    
    .info-card {
      height: auto;
      margin-bottom: 16px;
    }
  }
}
</style>
