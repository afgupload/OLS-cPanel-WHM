import axios from 'axios'

// Mocked cPanel API service
const api = axios.create({
  baseURL: '/api', // This would normally point to your actual WHM plugin backend
  timeout: 5000
})

// Add a mock adapter or interceptor for development if needed, 
// or just return mocked promises here directly for the prototype.

export const fetchServerStats = async () => {
  // Simulating an API call
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve([
        {
          key: 'domains',
          label: 'Total Domains',
          value: '156',
          change: '+12',
          changeType: 'positive',
          icon: 'Globe',
          type: 'primary'
        },
        {
          key: 'ssl',
          label: 'SSL Certificates',
          value: '142',
          change: '+8',
          changeType: 'positive',
          icon: 'Lock',
          type: 'success'
        },
        {
          key: 'connections',
          label: 'Active Connections',
          value: '1,247',
          change: '+5.2%',
          changeType: 'positive',
          icon: 'Monitor',
          type: 'warning'
        },
        {
          key: 'uptime',
          label: 'Server Uptime',
          value: '99.9%',
          change: '+0.1%',
          changeType: 'positive',
          icon: 'Monitor',
          type: 'info'
        }
      ])
    }, 500)
  })
}

export const fetchServerInfo = async () => {
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve({
        version: '1.7.17',
        uptime: '15 days, 7 hours',
        status: 'running',
        lastRestart: '2024-01-15 10:30:00'
      })
    }, 500)
  })
}

export const fetchRecentActivities = async () => {
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve([
        {
          id: 1,
          title: 'SSL certificate renewed for example.com',
          time: '2 minutes ago',
          icon: 'Lock',
          color: '#67C23A'
        },
        {
          id: 2,
          title: 'New domain added: newdomain.com',
          time: '15 minutes ago',
          icon: 'Globe',
          color: '#409EFF'
        },
        {
          id: 3,
          title: 'Server configuration updated',
          time: '1 hour ago',
          icon: 'Monitor',
          color: '#E6A23C'
        },
        {
          id: 4,
          title: 'PHP version updated for test.com',
          time: '2 hours ago',
          icon: 'Check',
          color: '#67C23A'
        },
        {
          id: 5,
          title: 'Failed SSL renewal for expired.com',
          time: '3 hours ago',
          icon: 'Warning',
          color: '#E6A23C'
        }
      ])
    }, 500)
  })
}

export const fetchDomainOverview = async () => {
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve([
        {
          domain: 'example.com',
          user: 'user1',
          phpVersion: '8.1',
          sslStatus: 'Valid',
          sslType: 'success',
          status: 'Active',
          statusType: 'success',
          statusIcon: 'Check',
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
          statusIcon: 'Check',
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
          statusIcon: 'Warning',
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
          statusIcon: 'Close',
          statusColor: '#F56C6C',
          lastUpdated: '2024-01-19 18:45'
        }
      ])
    }, 500)
  })
}
