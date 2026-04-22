<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const roleNames = computed(() => auth.roleNames)
const isAdmin = computed(() => auth.isAdmin)
const canApprove = computed(() => auth.canApprove)

const handleLogout = async () => {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-badge">
          <span class="brand-badge-dot"></span>
          Workflow Suite
        </div>

        <h1 class="brand-title">Control Panel</h1>
        <p class="brand-subtitle">
          Manage approval pipelines, roles, requests, and worker issues from one place.
        </p>
      </div>

      <div>
        <div class="sidebar-section-label">Workspace</div>
        <nav class="sidebar-nav">
          <RouterLink v-if="isAdmin" class="sidebar-link" to="/admin/users">Admin Users</RouterLink>
          <RouterLink v-if="isAdmin" class="sidebar-link" to="/admin/roles">Admin Roles</RouterLink>
          <RouterLink v-if="isAdmin" class="sidebar-link" to="/admin/workflows">
            Workflows
          </RouterLink>
          <RouterLink v-if="isAdmin" class="sidebar-link" to="/admin/failed-jobs">
            Failed Jobs
          </RouterLink>
          <RouterLink class="sidebar-link" to="/requests">Requests</RouterLink>
          <RouterLink v-if="canApprove" class="sidebar-link" to="/approvals">Approvals</RouterLink>
        </nav>
      </div>

      <div class="sidebar-footer">
        <p class="sidebar-user-label">
          {{ auth.user?.name || 'Authenticated User' }}
        </p>
        <p class="sidebar-user-subtitle">
          {{ auth.user?.email || 'Signed in user' }}
        </p>
        <p class="sidebar-user-subtitle" v-if="roleNames.length">
          {{ roleNames.join(', ') }}
        </p>

        <button class="btn btn-secondary" style="width: 100%" @click="handleLogout">Logout</button>
      </div>
    </aside>

    <main class="content-area">
      <header class="topbar">
        <div>
          <h2 class="topbar-title">Workflow Dashboard</h2>
          <p class="topbar-subtitle">
            A clean control center for approvals, orchestration, and operations.
          </p>
        </div>

        <div class="topbar-user">
          <p class="topbar-user-name">{{ auth.user?.name || 'User' }}</p>
          <p class="topbar-user-role">
            {{ roleNames.length ? roleNames.join(', ') : 'JWT Authenticated Session' }}
          </p>
        </div>
      </header>

      <slot />
    </main>
  </div>
</template>
