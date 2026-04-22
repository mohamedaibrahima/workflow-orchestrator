<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DashboardLayout from '../../layouts/DashboardLayout.vue'
import api from '../../api/client'

type RoleItem = {
  id: number
  name: string
}

type UserItem = {
  id: number
  name: string
  email: string
  is_active: boolean
  roles: RoleItem[]
}

const users = ref<UserItem[]>([])
const roles = ref<RoleItem[]>([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const success = ref('')
const editingUserId = ref<number | null>(null)

const form = reactive({
  name: '',
  email: '',
  password: '',
  is_active: true,
  roles: [] as number[],
})

const normalizeUsers = (payload: any): UserItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.users)) return payload.users
  return []
}

const normalizeRoles = (payload: any): RoleItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.roles)) return payload.roles
  return []
}

const resetForm = () => {
  form.name = ''
  form.email = ''
  form.password = ''
  form.is_active = true
  form.roles = []
  editingUserId.value = null
}

const loadUsers = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/admin/users')
    users.value = normalizeUsers(response.data)
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load users.'
  } finally {
    loading.value = false
  }
}

const loadRoles = async () => {
  try {
    const response = await api.get('/admin/roles')
    roles.value = normalizeRoles(response.data)
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load roles.'
  }
}

const submitUser = async () => {
  success.value = ''
  error.value = ''

  if (!form.name.trim()) {
    error.value = 'Name is required.'
    return
  }

  if (!form.email.trim()) {
    error.value = 'Email is required.'
    return
  }

  if (!editingUserId.value && !form.password.trim()) {
    error.value = 'Password is required for new users.'
    return
  }

  if (form.roles.length === 0) {
    error.value = 'Please select at least one role.'
    return
  }

  submitting.value = true

  try {
    const payload: Record<string, any> = {
      name: form.name,
      email: form.email,
      is_active: form.is_active,
      roles: form.roles,
    }

    if (form.password.trim()) {
      payload.password = form.password
    }

    if (editingUserId.value) {
      await api.patch(`/admin/users/${editingUserId.value}`, payload)
      success.value = 'User updated successfully.'
    } else {
      await api.post('/admin/users', payload)
      success.value = 'User created successfully.'
    }

    resetForm()
    await loadUsers()
  } catch (err: any) {
    if (err?.response?.data?.errors) {
      const firstError = Object.values(err.response.data.errors)[0]
      error.value = Array.isArray(firstError) ? String(firstError[0]) : 'Validation error.'
    } else {
      error.value = err?.response?.data?.message || 'Failed to save user.'
    }
  } finally {
    submitting.value = false
  }
}

const editUser = (user: UserItem) => {
  success.value = ''
  error.value = ''
  editingUserId.value = user.id
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.is_active = user.is_active
  form.roles = user.roles.map((role) => role.id)
}

const toggleActive = async (user: UserItem) => {
  success.value = ''
  error.value = ''

  try {
    await api.patch(`/admin/users/${user.id}/toggle-active`)
    success.value = user.is_active
      ? 'User deactivated successfully.'
      : 'User activated successfully.'
    await loadUsers()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to toggle user status.'
  }
}

onMounted(async () => {
  await Promise.all([loadUsers(), loadRoles()])
})
</script>

<template>
  <DashboardLayout>
    <div class="page-stack">
      <section class="card card-cardy">
        <div class="page-eyebrow">Admin / User Management</div>
        <h1 class="page-title">Users</h1>
        <p class="page-description">
          Create users, assign roles, and control whether accounts are active inside the workflow
          platform.
        </p>
      </section>

      <section class="grid-two">
        <div class="card card-cardy">
          <div class="card-header">
            <h3 class="section-title">
              {{ editingUserId ? 'Edit User' : 'Create New User' }}
            </h3>
            <p class="section-text">
              Add employees, managers, finance approvers, procurement approvers, or platform admins.
            </p>
          </div>

          <form class="form-stack" @submit.prevent="submitUser">
            <div>
              <label class="label" for="user-name">Name</label>
              <input
                id="user-name"
                v-model="form.name"
                type="text"
                placeholder="e.g. Employee One"
              />
            </div>

            <div>
              <label class="label" for="user-email">Email</label>
              <input
                id="user-email"
                v-model="form.email"
                type="email"
                placeholder="e.g. employee1@example.com"
              />
            </div>

            <div>
              <label class="label" for="user-password">
                {{ editingUserId ? 'Password (optional)' : 'Password' }}
              </label>
              <input
                id="user-password"
                v-model="form.password"
                type="password"
                :placeholder="
                  editingUserId ? 'Leave blank to keep current password' : 'Minimum 8 characters'
                "
              />
            </div>

            <div>
              <label class="label" for="user-roles">Roles</label>
              <select id="user-roles" v-model="form.roles" multiple>
                <option v-for="role in roles" :key="role.id" :value="role.id">
                  {{ role.name }}
                </option>
              </select>
              <p class="helper-text">Hold Ctrl or Cmd to select multiple roles.</p>
            </div>

            <label
              style="
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
                color: #0f172a;
              "
            >
              <input v-model="form.is_active" type="checkbox" />
              Active account
            </label>

            <div class="button-row">
              <button type="submit" class="btn btn-primary" :disabled="submitting">
                {{
                  submitting
                    ? editingUserId
                      ? 'Updating user...'
                      : 'Creating user...'
                    : editingUserId
                      ? 'Update user'
                      : 'Create user'
                }}
              </button>

              <button
                v-if="editingUserId"
                type="button"
                class="btn btn-secondary"
                @click="resetForm"
              >
                Cancel edit
              </button>
            </div>
          </form>

          <div v-if="success" class="alert alert-success" style="margin-top: 16px">
            {{ success }}
          </div>

          <div v-if="error" class="alert alert-error" style="margin-top: 16px">
            {{ error }}
          </div>
        </div>

        <div class="card card-cardy">
          <div class="card-header">
            <h3 class="section-title">Quick Notes</h3>
            <p class="section-text">
              Admins can create users, assign workflow roles, and activate or deactivate accounts
              without touching seeders.
            </p>
          </div>

          <div class="form-stack">
            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Recommended test users</strong>
              <span class="role-meta">
                Employees create requests, managers approve department steps, finance handles budget
                approval, and procurement handles purchasing steps.
              </span>
            </div>

            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Role assignment</strong>
              <span class="role-meta">
                A user may carry one or more roles depending on how you want your workflow to route
                approvals.
              </span>
            </div>
          </div>
        </div>
      </section>

      <section class="card card-cardy">
        <div class="toolbar">
          <div>
            <h3 class="section-title" style="margin-bottom: 4px">Existing Users</h3>
            <p class="section-text">
              Review user accounts, their assigned roles, and whether they can currently sign in.
            </p>
          </div>

          <div class="count-badge">
            {{ users.length }}
          </div>
        </div>

        <div class="button-row" style="margin-bottom: 18px">
          <button class="btn btn-secondary" @click="loadUsers">Refresh list</button>
        </div>

        <p v-if="loading" class="section-text">Loading users...</p>

        <div v-else-if="users.length === 0" class="empty-state">
          No users found yet. Create your first user to start testing the workflow system.
        </div>

        <div v-else class="table-wrap">
          <table class="table-clean">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th style="width: 220px">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>#{{ user.id }}</td>
                <td class="role-name">{{ user.name }}</td>
                <td class="role-meta">{{ user.email }}</td>
                <td class="role-meta">
                  {{ user.roles?.map((role) => role.name).join(', ') || 'No roles assigned' }}
                </td>
                <td>
                  <span
                    :style="{
                      color: user.is_active ? '#166534' : '#991b1b',
                      fontWeight: '600',
                    }"
                  >
                    {{ user.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>
                  <div class="button-row">
                    <button class="btn btn-secondary" @click="editUser(user)">Edit</button>
                    <button
                      class="btn"
                      :class="user.is_active ? 'btn-danger' : 'btn-primary'"
                      @click="toggleActive(user)"
                    >
                      {{ user.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
