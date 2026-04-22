import { defineStore } from 'pinia'
import api from '../api/client'

type RoleItem = {
  id?: number
  name: string
}

type User = {
  id: number
  name: string
  email: string
  roles?: RoleItem[] | string[]
}

type LoginPayload = {
  email: string
  password: string
}

const normalizeRoles = (user: User | null): string[] => {
  if (!user?.roles) return []

  return user.roles
    .map((role) => {
      if (typeof role === 'string') return role
      return role?.name
    })
    .filter((role): role is string => !!role)
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    token: localStorage.getItem('token') || '',
    loading: false,
    error: '',
    initialized: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    roleNames: (state) => normalizeRoles(state.user),
    isAdmin(): boolean {
      return this.roleNames.includes('admin')
    },
    canApprove(): boolean {
      return this.roleNames.length > 0
    },
  },

  actions: {
    async login(payload: LoginPayload) {
      this.loading = true
      this.error = ''

      try {
        const response = await api.post('/auth/login', payload)
        const token = response.data?.access_token || response.data?.token
        const user = response.data?.user || null

        if (!token) {
          throw new Error('Token was not returned from login endpoint.')
        }

        this.token = token
        localStorage.setItem('token', token)

        if (user) {
          this.user = user
        } else {
          await this.fetchMe()
        }

        this.initialized = true
      } catch (error: any) {
        this.error = error?.response?.data?.message || error?.message || 'Login failed.'
        this.logoutLocal()
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchMe() {
      try {
        const response = await api.get('/auth/me')
        this.user = response.data?.user || response.data
        this.initialized = true
      } catch (error) {
        this.logoutLocal()
        throw error
      }
    },

    async init() {
      if (!this.token) {
        this.initialized = true
        return
      }

      if (this.user) {
        this.initialized = true
        return
      }

      try {
        await this.fetchMe()
      } catch (_error) {
      } finally {
        this.initialized = true
      }
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } catch (_error) {
      } finally {
        this.logoutLocal()
      }
    },

    logoutLocal() {
      this.user = null
      this.token = ''
      this.error = ''
      this.initialized = true
      localStorage.removeItem('token')
    },
  },
})
