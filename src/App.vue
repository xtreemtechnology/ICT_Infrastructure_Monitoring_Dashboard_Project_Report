<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  Activity, AlertTriangle, ArrowUpRight, Bell, CheckCircle2, ChevronDown,
  CircleHelp, Gauge, LayoutDashboard, ListFilter, LogOut, Menu, MoreHorizontal,
  Network, Plus, Search, Server, Settings, ShieldCheck, SlidersHorizontal,
  Users, X,
} from 'lucide-vue-next'

const apiBaseUrl = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')
const fetch = (path, options = {}) => window.fetch(`${apiBaseUrl}${path}`, { ...options, credentials: 'include' })

const activeView = ref('Overview')
const authenticated = ref(false)
const checkingAuth = ref(true)
const loginError = ref('')
const loginForm = ref({ email: 'test@example.com', password: 'password' })
const mobileMenuOpen = ref(false)
const filter = ref('All systems')
const search = ref('')
const toast = ref('')
const apiError = ref('')
const showDeviceForm = ref(false)
const savingDevice = ref(false)
const deviceForm = ref({ name: '', type: '', ip_address: '', latency_ms: null })
const summary = ref({ total_devices: 24, operational_devices: 21, open_alerts: 3, average_latency_ms: 42, network_uptime: 99.2 })
const alerts = ref([])
const report = ref(null)
const reportRange = ref('30d')
const groups = ref([])
const team = ref([])
const showGroupForm = ref(false)
const groupForm = ref({ name: '', description: '' })
const settings = ref({ pollingInterval: 30, notifications: true, compactMode: false })
const demoCredentials = { email: 'test@example.com', password: 'password' }
const isDemoMode = () => window.localStorage.getItem('signaldesk-demo-auth') === 'true'
const navItems = [
  { label: 'Overview', icon: LayoutDashboard }, { label: 'Devices', icon: Server, count: 'devices' },
  { label: 'Alerts', icon: Bell, count: 'alerts', alert: true }, { label: 'Reports', icon: Activity },
]
const devices = ref([
  { name: 'Core Router · Main Campus', type: 'Network gateway', ip: '10.22.0.1', status: 'online', statusLabel: 'Operational', uptime: '99.98%', latency: '12 ms', updated: 'Just now', icon: Network, tone: 'teal' },
  { name: 'LMS Production Server', type: 'Application server', ip: '10.22.4.18', status: 'online', statusLabel: 'Operational', uptime: '99.96%', latency: '24 ms', updated: '1 min ago', icon: Server, tone: 'blue' },
  { name: 'Library Wi-Fi Cluster', type: 'Wireless access point', ip: '10.22.8.0/24', status: 'warning', statusLabel: 'Degraded', uptime: '98.71%', latency: '86 ms', updated: '2 min ago', icon: Network, tone: 'amber' },
  { name: 'Admin Block Switch 02', type: 'Network switch', ip: '10.22.2.12', status: 'offline', statusLabel: 'Unreachable', uptime: '94.20%', latency: '--', updated: '4 min ago', icon: Network, tone: 'rose' },
])
const visibleDevices = computed(() => devices.value.filter((device) => {
  const matchesFilter = filter.value === 'All systems' || device.statusLabel === filter.value
  const query = search.value.toLowerCase()
  return matchesFilter && (!query || `${device.name} ${device.ip}`.toLowerCase().includes(query))
}))
async function selectView(view) {
  activeView.value = view
  mobileMenuOpen.value = false
  if (view === 'Reports') await loadReport()
  if (view === 'Device groups') await loadGroups()
  if (view === 'Team access') await loadTeam()
  if (view === 'Settings') loadSettings()
}
function loadSettings() {
  const savedSettings = window.localStorage.getItem('signaldesk-settings')
  if (savedSettings) settings.value = { ...settings.value, ...JSON.parse(savedSettings) }
  window.setTimeout(renderSettings, 0)
}
function renderSettings() {
  const panel = document.querySelector('.view-panel')
  if (!panel || activeView.value !== 'Settings') return
  panel.querySelector('.settings-content')?.remove()
  const content = document.createElement('div')
  content.className = 'settings-content'
  content.innerHTML = '<div class="settings-row"><div><strong>Polling frequency</strong><small>How often Signaldesk checks registered devices</small></div><select aria-label="Polling frequency"><option value="15">Every 15 seconds</option><option value="30">Every 30 seconds</option><option value="60">Every minute</option></select></div><div class="settings-row"><div><strong>Alert notifications</strong><small>Show visible notifications when alert counts change</small></div><input type="checkbox" aria-label="Alert notifications"></div><div class="settings-row"><div><strong>Compact layout</strong><small>Use tighter spacing for dense monitoring screens</small></div><input type="checkbox" aria-label="Compact layout"></div>'
  const polling = content.querySelector('select')
  const notifications = content.querySelectorAll('input')[0]
  const compactMode = content.querySelectorAll('input')[1]
  polling.value = settings.value.pollingInterval
  notifications.checked = settings.value.notifications
  compactMode.checked = settings.value.compactMode
  const save = () => {
    settings.value = { pollingInterval: Number(polling.value), notifications: notifications.checked, compactMode: compactMode.checked }
    window.localStorage.setItem('signaldesk-settings', JSON.stringify(settings.value))
    document.documentElement.classList.toggle('compact-mode', settings.value.compactMode)
    window.clearInterval(refreshTimer)
    refreshTimer = window.setInterval(loadDashboard, settings.value.pollingInterval * 1000)
    notify('Settings saved')
  }
  polling.addEventListener('change', save)
  notifications.addEventListener('change', save)
  compactMode.addEventListener('change', save)
  panel.appendChild(content)
}
async function loadGroups() {
  const response = await fetch('/api/device-groups')
  if (response.ok) {
    groups.value = await response.json()
    window.setTimeout(renderGroups, 0)
  }
}
function renderGroups() {
  const panel = document.querySelector('.view-panel')
  if (!panel || activeView.value !== 'Device groups') return
  const existing = panel.querySelector('.group-content')
  existing?.remove()
  const content = document.createElement('div')
  content.className = 'group-content'
  const heading = document.createElement('div')
  heading.className = 'group-toolbar'
  heading.innerHTML = '<strong>' + groups.value.length + ' device groups</strong>'
  const button = document.createElement('button')
  button.className = 'primary-btn'
  button.textContent = 'Create group'
  button.addEventListener('click', async () => {
    const name = window.prompt('Group name')
    if (!name?.trim()) return
    const response = await fetch('/api/device-groups', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ name: name.trim() }) })
    if (!response.ok) {
      notify('Group could not be created')
      return
    }
    await loadGroups()
    notify('Device group created')
  })
  heading.appendChild(button)
  content.appendChild(heading)
  const list = document.createElement('div')
  list.className = 'group-list'
  groups.value.forEach((group) => {
    const item = document.createElement('div')
    item.className = 'group-item'
    item.innerHTML = '<div><strong></strong><small></small></div><span></span>'
    item.querySelector('strong').textContent = group.name
    item.querySelector('small').textContent = group.description || 'No description provided'
    item.querySelector('span').textContent = `${group.devices_count} devices`
    list.appendChild(item)
  })
  content.appendChild(list)
  panel.appendChild(content)
}
async function loadTeam() {
  const response = await fetch('/api/team')
  if (response.ok) {
    team.value = await response.json()
    window.setTimeout(renderTeam, 0)
  }
}
function renderTeam() {
  const panel = document.querySelector('.view-panel')
  if (!panel || activeView.value !== 'Team access') return
  panel.querySelector('.team-content')?.remove()
  const content = document.createElement('div')
  content.className = 'team-content'
  const toolbar = document.createElement('div')
  toolbar.className = 'group-toolbar'
  toolbar.innerHTML = '<strong>' + team.value.length + ' team members</strong>'
  const button = document.createElement('button')
  button.className = 'primary-btn'
  button.textContent = 'Invite member'
  button.addEventListener('click', async () => {
    const name = window.prompt('Member name')
    const email = window.prompt('Member email')
    if (!name?.trim() || !email?.trim()) return
    const response = await fetch('/api/team', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ name: name.trim(), email: email.trim(), password: 'temporary-password', role: 'operator' }) })
    if (!response.ok) {
      notify('Member could not be invited')
      return
    }
    await loadTeam()
    notify('Team member added')
  })
  toolbar.appendChild(button)
  content.appendChild(toolbar)
  const list = document.createElement('div')
  list.className = 'group-list'
  team.value.forEach((member) => {
    const item = document.createElement('div')
    item.className = 'group-item'
    item.innerHTML = '<div><strong></strong><small></small></div><span></span>'
    item.querySelector('strong').textContent = member.name
    item.querySelector('small').textContent = member.email
    item.querySelector('span').textContent = member.role
    list.appendChild(item)
  })
  content.appendChild(list)
  panel.appendChild(content)
}
async function submitGroup() {
  const response = await fetch('/api/device-groups', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(groupForm.value) })
  if (!response.ok) {
    notify('Group could not be created')
    return
  }
  showGroupForm.value = false
  groupForm.value = { name: '', description: '' }
  await loadGroups()
  notify('Device group created')
}
async function loadReport() {
  const response = await fetch(`/api/reports?range=${reportRange.value}`)
  if (response.ok) {
    report.value = await response.json()
    window.setTimeout(renderReportChart, 0)
  }
}
function renderReportChart() {
  const reportPanel = document.querySelector('.report-content')
  if (!reportPanel || !report.value) return
  reportPanel.querySelector('.report-chart')?.remove()
  if (!report.value.history.length) return
  const chart = document.createElement('div')
  chart.className = 'report-chart'
  chart.innerHTML = '<div class="report-chart-heading"><div><h3>Availability trend</h3><p>Recorded polling results for the selected period</p></div><strong>' + report.value.history.length + ' checks</strong></div><div class="chart-bars"></div>'
  const bars = chart.querySelector('.chart-bars')
  report.value.history.forEach((point) => {
    const bar = document.createElement('div')
    bar.className = 'chart-bar'
    bar.title = `${point.timestamp}: ${point.availability}% available`
    bar.innerHTML = `<span style="height: ${Math.max(point.availability, 4)}%"></span>`
    bars.appendChild(bar)
  })
  reportPanel.appendChild(chart)
}
function exportReport() {
  if (!report.value) return
  const rows = [['Metric', 'Value'], ['Availability', `${report.value.summary.availability}%`], ['Average latency', `${report.value.summary.average_latency_ms} ms`], ['Open alerts', report.value.summary.open_alerts], ['Online devices', report.value.status_breakdown.online], ['Degraded devices', report.value.status_breakdown.warning], ['Offline devices', report.value.status_breakdown.offline]]
  const csv = rows.map((row) => row.join(',')).join('\n')
  const link = document.createElement('a')
  link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  link.download = `signaldesk-report-${reportRange.value}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}
async function loadDashboard() {
  try {
    await fetch('/api/devices/check', { method: 'POST' })
    const response = await fetch('/api/dashboard')
    if (!response.ok) throw new Error('Dashboard request failed')
    const data = await response.json()
    summary.value = data.summary
    alerts.value = data.alerts
    devices.value = data.devices.map((device) => ({
      ...device,
      icon: device.type.includes('server') ? Server : Network,
      tone: device.status === 'offline' ? 'rose' : device.status === 'warning' ? 'amber' : device.type.includes('server') ? 'blue' : 'teal',
    }))
    apiError.value = ''
  } catch (error) {
    const savedDevices = window.localStorage.getItem('signaldesk-demo-devices')
    if (savedDevices) {
      devices.value = JSON.parse(savedDevices).map((device) => ({
        ...device,
        icon: device.type?.toLowerCase().includes('server') ? Server : Network,
        tone: device.type?.toLowerCase().includes('server') ? 'blue' : 'teal',
      }))
    }
    apiError.value = 'Live data is unavailable. Showing the last known dashboard state.'
  }
}
async function login() {
  loginError.value = ''
  if (loginForm.value.email !== demoCredentials.email || loginForm.value.password !== demoCredentials.password) {
    loginError.value = 'Incorrect email or password.'
    return
  }
  window.localStorage.setItem('signaldesk-demo-auth', 'true')
  window.localStorage.setItem('signaldesk-demo-email', loginForm.value.email)
  authenticated.value = true
  await loadDashboard()
}
async function logout() {
  window.localStorage.removeItem('signaldesk-demo-auth')
  authenticated.value = false
}
function notify(message) { toast.value = message; window.setTimeout(() => { toast.value = '' }, 2800) }
async function acknowledgeAlert() {
  try {
    const responses = await Promise.all(alerts.value.map((alert) => fetch(`/api/alerts/${alert.id}/acknowledge`, { method: 'PATCH' })))
    if (responses.some((response) => !response.ok)) throw new Error('Alert acknowledgement failed')
    await loadDashboard()
    notify('Open alerts acknowledged')
  } catch (error) {
    notify('Alerts could not be acknowledged')
  }
}
function addDevice() { showDeviceForm.value = true }
async function submitDevice() {
  savingDevice.value = true
  try {
    if (isDemoMode()) {
      const localDevice = {
        ...deviceForm.value,
        id: `local-${Date.now()}`,
        ip: deviceForm.value.ip_address,
        status: 'online',
        statusLabel: 'Operational',
        uptime: '100.00%',
        latency: deviceForm.value.latency_ms ? `${deviceForm.value.latency_ms} ms` : '--',
        updated: 'Just now',
      }
      devices.value = [localDevice, ...devices.value]
      window.localStorage.setItem('signaldesk-demo-devices', JSON.stringify(devices.value.map(({ icon, ...device }) => device)))
      showDeviceForm.value = false
      deviceForm.value = { name: '', type: '', ip_address: '', latency_ms: null }
      notify('Device registered locally')
      return
    }
    const response = await fetch('/api/devices', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(deviceForm.value) })
    if (!response.ok) throw new Error('Device registration failed')
    showDeviceForm.value = false
    deviceForm.value = { name: '', type: '', ip_address: '', latency_ms: null }
    await loadDashboard()
    notify('Device registered successfully')
  } catch (error) {
    notify('Device could not be registered')
  } finally {
    savingDevice.value = false
  }
}
async function manageDevice(device) {
  const action = window.prompt(`Device: ${device.name}\nType "rename" or "delete"`)
  if (action === 'rename') {
    const name = window.prompt('New device name', device.name)
    if (!name?.trim()) return
    const response = await fetch(`/api/devices/${device.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ name: name.trim() }) })
    if (!response.ok) return notify('Device could not be updated')
    await loadDashboard()
    notify('Device updated')
  }
  if (action === 'delete' && window.confirm(`Delete ${device.name}?`)) {
    const response = await fetch(`/api/devices/${device.id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
    if (!response.ok) return notify('Device could not be deleted')
    await loadDashboard()
    notify('Device deleted')
  }
}
async function acknowledgeSingleAlert(alert) {
  const response = await fetch(`/api/alerts/${alert.id}/acknowledge`, { method: 'PATCH' })
  if (!response.ok) return notify('Alert could not be acknowledged')
  await loadDashboard()
  notify('Alert acknowledged')
}
function handleDashboardAction(event) {
  const deviceAction = event.target.closest('.row-action')
  if (deviceAction) {
    const row = deviceAction.closest('.device-row')
    const name = row?.querySelector('.device-name strong')?.textContent
    const device = devices.value.find((item) => item.name === name)
    if (device) manageDevice(device)
    return
  }
  const alertItem = event.target.closest('.alert-item')
  if (alertItem && !event.target.closest('button')) {
    const index = [...document.querySelectorAll('.alert-item')].indexOf(alertItem)
    if (alerts.value[index]) acknowledgeSingleAlert(alerts.value[index])
  }
}
let refreshTimer
onMounted(() => {
  document.addEventListener('click', handleDashboardAction)
  const demoAuth = isDemoMode()
  authenticated.value = demoAuth
  checkingAuth.value = false
  if (authenticated.value) {
    loadDashboard()
    refreshTimer = window.setInterval(loadDashboard, 30000)
  }
})
onUnmounted(() => {
  window.clearInterval(refreshTimer)
  document.removeEventListener('click', handleDashboardAction)
})
</script>

<template>
  <div v-if="checkingAuth" class="loading-screen">Loading Signaldesk...</div>
  <div v-else-if="!authenticated" class="login-screen"><form class="login-card" @submit.prevent="login"><div class="brand-mark"><Activity :size="20" :stroke-width="2.4" /></div><div class="eyebrow">ICT infrastructure</div><h1>Welcome back</h1><p>Sign in to monitor your campus systems.</p><label>Email<input v-model="loginForm.email" type="email" required autocomplete="email" /></label><label>Password<input v-model="loginForm.password" type="password" required autocomplete="current-password" /></label><p v-if="loginError" class="api-error">{{ loginError }}</p><button class="primary-btn" type="submit">Sign in</button></form></div>
  <div v-else class="app-shell">
    <aside class="sidebar" :class="{ 'sidebar-open': mobileMenuOpen }">
      <div class="brand-row"><div class="brand-mark"><Activity :size="20" :stroke-width="2.4" /></div><div><strong>signal<span>desk</span></strong><small>ICT infrastructure</small></div><button type="button" class="icon-btn mobile-close" aria-label="Close menu" @click.stop="mobileMenuOpen = false"><X :size="18" /></button></div>
      <div class="workspace-switcher"><div class="campus-avatar">FP</div><div><small>Workspace</small><strong>Federal Polytechnic</strong></div><ChevronDown :size="16" /></div>
      <nav class="nav-list" aria-label="Main navigation"><p class="nav-label">Monitor</p><button v-for="item in navItems" :key="item.label" class="nav-item" :class="{ active: activeView === item.label }" @click="selectView(item.label)"><component :is="item.icon" :size="18" /><span>{{ item.label }}</span><em v-if="item.count" :class="{ 'count-alert': item.alert }">{{ item.count === 'devices' ? summary.total_devices : String(summary.open_alerts).padStart(2, '0') }}</em></button><p class="nav-label second-label">Manage</p><button class="nav-item" :class="{ active: activeView === 'Device groups' }" @click="selectView('Device groups')"><SlidersHorizontal :size="18" /><span>Device groups</span></button><button class="nav-item" :class="{ active: activeView === 'Team access' }" @click="selectView('Team access')"><Users :size="18" /><span>Team access</span></button></nav>
      <div class="sidebar-bottom"><div class="help-box"><CircleHelp :size="18" /><div><strong>Need a hand?</strong><small>Read the operator guide</small></div><ArrowUpRight :size="15" /></div><button class="nav-item"><Settings :size="18" /><span>Settings</span></button><button class="nav-item" @click="logout"><LogOut :size="18" /><span>Sign out</span></button><div class="profile"><div class="profile-avatar">DO</div><div><strong>Daniel O.</strong><small>Administrator</small></div><MoreHorizontal :size="17" /></div></div>
    </aside>
    <main class="main-content">
      <header class="topbar"><button class="icon-btn menu-toggle" aria-label="Open menu" @click="mobileMenuOpen = true"><Menu :size="21" /></button><div class="breadcrumb"><span>Workspace</span><b>/</b><strong>{{ activeView }}</strong></div><div class="top-actions"><button class="icon-btn" aria-label="Help"><CircleHelp :size="19" /></button><button class="icon-btn notification" aria-label="Notifications"><Bell :size="19" /><i></i></button><div class="mini-avatar">DO</div></div></header>
      <div class="content-wrap">
        <section class="page-heading"><div><div class="eyebrow"><span class="pulse-dot"></span> Live environment <span class="dot-separator">·</span> Last synced just now</div><h1>Good morning, Daniel.</h1><p>Here’s the health of your infrastructure across campus.</p><p v-if="apiError" class="api-error">{{ apiError }}</p></div><button class="primary-btn" @click="addDevice"><Plus :size="17" /> Register device</button></section>
        <section v-if="activeView !== 'Overview'" class="view-panel"><div class="view-panel-heading"><div><div class="eyebrow"><span class="pulse-dot"></span> Workspace view</div><h2>{{ activeView }}</h2><p>{{ activeView === 'Devices' ? 'Manage registered infrastructure and review current health.' : activeView === 'Alerts' ? 'Review and resolve active infrastructure issues.' : activeView === 'Reports' ? 'Operational reporting from the current monitoring snapshot.' : activeView === 'Device groups' ? 'Organize devices by campus area, service, or ownership.' : 'Control workspace members and their access levels.' }}</p></div><button v-if="activeView === 'Devices'" class="primary-btn" @click="addDevice"><Plus :size="17" /> Register device</button></div><div v-if="activeView === 'Devices'" class="view-list"><div v-for="device in devices" :key="device.id || device.name" class="view-list-row"><div><strong>{{ device.name }}</strong><small>{{ device.type }} · {{ device.ip }}</small></div><span class="status-pill" :class="device.status"><i></i>{{ device.statusLabel }}</span><strong>{{ device.latency }}</strong></div></div><div v-else-if="activeView === 'Alerts'" class="view-list"><div v-for="alert in alerts" :key="alert.id" class="view-list-row"><div><strong>{{ alert.message }}</strong><small>{{ alert.device }} · {{ alert.created_at }}</small></div><span class="status-pill warning"><i></i>{{ alert.severity }}</span></div><div v-if="!alerts.length" class="empty-state">No open alerts.</div></div><div v-else-if="activeView === 'Reports' && report" class="report-content"><div class="report-toolbar"><select v-model="reportRange" @change="loadReport" aria-label="Report period"><option value="24h">Last 24 hours</option><option value="7d">Last 7 days</option><option value="30d">Last 30 days</option></select><button class="secondary-btn" @click="exportReport">Export CSV</button></div><div class="report-grid"><div class="report-stat"><span>Availability</span><strong>{{ report.summary.availability }}%</strong></div><div class="report-stat"><span>Average latency</span><strong>{{ report.summary.average_latency_ms }} ms</strong></div><div class="report-stat"><span>Open alerts</span><strong>{{ report.summary.open_alerts }}</strong></div><div class="report-stat"><span>Total devices</span><strong>{{ report.summary.total_devices }}</strong></div></div><div class="report-breakdown"><div><h3>Device availability</h3><p>Online {{ report.status_breakdown.online }} · Degraded {{ report.status_breakdown.warning }} · Offline {{ report.status_breakdown.offline }}</p></div><div><h3>Alert severity</h3><p>High {{ report.alert_breakdown.high }} · Medium {{ report.alert_breakdown.medium }} · Low {{ report.alert_breakdown.low }}</p></div></div></div><div v-else class="empty-state">{{ activeView }} is ready for configuration.</div></section>
        <section v-else class="stats-grid"><article class="stat-card"><div class="stat-top"><span class="stat-icon teal-bg"><ShieldCheck :size="18" /></span><span class="trend up">+0.4%</span></div><div class="stat-value">{{ summary.network_uptime }}<span>%</span></div><div class="stat-label">Network uptime <span>vs last 30 days</span></div><div class="sparkline teal-line"><svg viewBox="0 0 220 42" preserveAspectRatio="none"><path d="M0 32 C12 30, 15 25, 28 29 S44 31, 54 20 S67 24, 80 19 S95 20, 105 14 S117 18, 128 12 S142 16, 153 9 S167 13, 178 7 S192 8, 220 3" /></svg></div></article><article class="stat-card"><div class="stat-top"><span class="stat-icon blue-bg"><Server :size="18" /></span><span class="trend neutral">of {{ summary.total_devices }} devices</span></div><div class="stat-value">{{ summary.operational_devices }} <span class="fraction">/ {{ summary.total_devices }}</span></div><div class="stat-label">Systems operational <span>{{ summary.total_devices - summary.operational_devices }} need attention</span></div><div class="progress"><span :style="{ width: `${summary.total_devices ? summary.operational_devices / summary.total_devices * 100 : 0}%` }"></span></div></article><article class="stat-card"><div class="stat-top"><span class="stat-icon amber-bg"><AlertTriangle :size="18" /></span><span class="trend down">requiring attention</span></div><div class="stat-value">{{ String(summary.open_alerts).padStart(2, '0') }}</div><div class="stat-label">Open alerts <span>requiring attention</span></div><div class="alert-bars"><i></i><i></i><i></i><i class="muted"></i><i class="muted"></i></div></article><article class="stat-card"><div class="stat-top"><span class="stat-icon violet-bg"><Gauge :size="18" /></span><span class="trend up">live average</span></div><div class="stat-value">{{ summary.average_latency_ms }} <span class="unit">ms</span></div><div class="stat-label">Average response <span>across all devices</span></div><div class="response-line"><span style="width: 34%"></span></div></article></section>
        <section class="section-heading"><div><h2>Infrastructure status</h2><p>Real-time view of registered devices and services</p></div><div class="table-tools"><div class="search-box"><Search :size="16" /><input v-model="search" type="search" placeholder="Search devices..." /></div><select v-model="filter" aria-label="Filter devices"><option>All systems</option><option>Operational</option><option>Degraded</option><option>Unreachable</option></select><button class="icon-btn filter-btn" aria-label="More filters"><ListFilter :size="17" /></button></div></section>
        <section class="devices-panel"><div class="table-head"><span>Device</span><span>Status</span><span>Uptime</span><span>Response</span><span>Last check</span><span></span></div><div v-for="device in visibleDevices" :key="device.name" class="device-row"><div class="device-name"><span class="device-icon" :class="`${device.tone}-icon`"><component :is="device.icon" :size="18" /></span><div><strong>{{ device.name }}</strong><small>{{ device.type }} <b>·</b> {{ device.ip }}</small></div></div><div><span class="status-pill" :class="device.status"><i></i>{{ device.statusLabel }}</span></div><strong class="metric-text">{{ device.uptime }}</strong><span class="metric-text response">{{ device.latency }}</span><span class="last-check">{{ device.updated }}</span><button class="row-action" aria-label="Open device"><ArrowUpRight :size="16" /></button></div><div v-if="!visibleDevices.length" class="empty-state">No devices match this filter.</div><button class="view-all" @click="activeView = 'Devices'">View all {{ summary.total_devices }} devices <ArrowUpRight :size="15" /></button></section>
        <section class="lower-grid"><article class="panel alert-panel"><div class="panel-heading"><div><h2>Needs your attention</h2><p>Alerts that are still open or unresolved</p></div><button class="text-btn" @click="activeView = 'Alerts'">View all <ArrowUpRight :size="14" /></button></div><div v-for="alert in alerts" :key="alert.id" class="alert-item"><span class="alert-severity" :class="alert.severity"><AlertTriangle :size="17" /></span><div><strong>{{ alert.message }}</strong><small>{{ alert.device }}</small></div><span class="alert-time">{{ alert.created_at }}</span></div><div v-if="!alerts.length" class="empty-state">No open alerts.</div><button class="ack-btn" @click="acknowledgeAlert"><CheckCircle2 :size="16" /> Acknowledge selected alerts</button></article><article class="panel activity-panel"><div class="panel-heading"><div><h2>Recent activity</h2><p>Latest changes in your workspace</p></div><button class="icon-btn" aria-label="Activity options"><ChevronDown :size="17" /></button></div><div class="timeline"><div><span class="timeline-dot green"></span><p><strong>Polling cycle completed</strong><small>{{ summary.total_devices }} devices checked · 2 min ago</small></p></div><div><span class="timeline-dot blue"></span><p><strong>Dashboard connected</strong><small>Live monitoring data loaded just now</small></p></div><div><span class="timeline-dot amber"></span><p><strong>{{ summary.open_alerts }} open alerts</strong><small>Review unresolved infrastructure issues</small></p></div></div></article></section>
      </div>
    </main>
    <Transition name="toast"><div v-if="toast" class="toast"><CheckCircle2 :size="17" />{{ toast }}</div></Transition>
    <div v-if="showDeviceForm" class="modal-backdrop" @click.self="showDeviceForm = false"><form class="device-modal" @submit.prevent="submitDevice"><div class="modal-heading"><div><div class="eyebrow">New infrastructure</div><h2>Register device</h2></div><button type="button" class="icon-btn" aria-label="Close registration form" @click="showDeviceForm = false"><X :size="18" /></button></div><label>Device name<input v-model="deviceForm.name" required placeholder="e.g. Science Lab AP-04" /></label><label>Device type<input v-model="deviceForm.type" required placeholder="e.g. Wireless access point" /></label><label>IP address<input v-model="deviceForm.ip_address" required placeholder="10.22.4.18" /></label><label>Latency (ms)<input v-model.number="deviceForm.latency_ms" type="number" min="0" placeholder="Optional" /></label><div class="modal-actions"><button type="button" class="secondary-btn" @click="showDeviceForm = false">Cancel</button><button type="submit" class="primary-btn" :disabled="savingDevice">{{ savingDevice ? 'Registering...' : 'Register device' }}</button></div></form></div>
  </div>
</template>
