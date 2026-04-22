import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import AdminUsersView from '../views/admin/AdminUsersView.vue'
import AdminRolesView from '../views/admin/AdminRolesView.vue'
import AdminWorkflowsView from '../views/admin/AdminWorkflowsView.vue'
import AdminFailedJobsView from '../views/admin/AdminFailedJobsView.vue'
import RequestsView from '../views/employee/RequestsView.vue'
import RequestDetailsView from '../views/employee/RequestDetailsView.vue'
import ApprovalsView from '../views/approver/ApprovalsView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/login',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: AdminUsersView,
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/admin/roles',
      name: 'admin-roles',
      component: AdminRolesView,
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/admin/workflows',
      name: 'admin-workflows',
      component: AdminWorkflowsView,
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/admin/failed-jobs',
      name: 'admin-failed-jobs',
      component: AdminFailedJobsView,
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/requests',
      name: 'requests',
      component: RequestsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/requests/:id',
      name: 'request-details',
      component: RequestDetailsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/approvals',
      name: 'approvals',
      component: ApprovalsView,
      meta: { requiresAuth: true },
    },
  ],
})

const getDefaultRoute = (auth: ReturnType<typeof useAuthStore>) => {
  if (auth.isAdmin) return '/admin/users'
  if (auth.canApprove) return '/approvals'
  return '/requests'
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.init()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return '/login'
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return getDefaultRoute(auth)
  }

  const allowedRoles = (to.meta.roles as string[] | undefined) || []

  if (allowedRoles.length > 0) {
    const hasAllowedRole = auth.roleNames.some((role) => allowedRoles.includes(role))

    if (!hasAllowedRole) {
      return getDefaultRoute(auth)
    }
  }

  return true
})

export default router
