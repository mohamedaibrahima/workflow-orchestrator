<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DashboardLayout from '../../layouts/DashboardLayout.vue'
import api from '../../api/client'

type RoleItem = {
  id: number
  name: string
}

const roles = ref<RoleItem[]>([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const success = ref('')

const form = reactive({
  name: '',
})

const normalizeRoles = (payload: any): RoleItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.roles)) return payload.roles
  return []
}

const loadRoles = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/admin/roles')
    roles.value = normalizeRoles(response.data)
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load roles.'
  } finally {
    loading.value = false
  }
}

const createRole = async () => {
  success.value = ''
  error.value = ''

  if (!form.name.trim()) {
    error.value = 'Role name is required.'
    return
  }

  submitting.value = true

  try {
    await api.post('/admin/roles', {
      name: form.name,
    })

    form.name = ''
    success.value = 'Role created successfully.'
    await loadRoles()
  } catch (err: any) {
    if (err?.response?.data?.errors) {
      const firstError = Object.values(err.response.data.errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : 'Validation error.'
    } else {
      error.value = err?.response?.data?.message || 'Failed to create role.'
    }
  } finally {
    submitting.value = false
  }
}

const deleteRole = async (id: number) => {
  success.value = ''
  error.value = ''

  const confirmed = window.confirm('Are you sure you want to delete this role?')
  if (!confirmed) return

  try {
    await api.delete(`/admin/roles/${id}`)
    success.value = 'Role deleted successfully.'
    await loadRoles()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to delete role.'
  }
}

onMounted(() => {
  loadRoles()
})
</script>

<template>
  <DashboardLayout>
    <div class="page-stack">
      <section class="card card-cardy">
        <div class="page-eyebrow">Admin / Access Control</div>
        <h1 class="page-title">Role Management</h1>
        <p class="page-description">
          Create and manage approval roles that are used across workflow definitions and request
          routing.
        </p>
      </section>

      <section class="grid-two">
        <div class="card card-cardy">
          <div class="card-header">
            <h3 class="section-title">Create New Role</h3>
            <p class="section-text">
              Add a role such as manager, finance, procurement, or legal reviewer.
            </p>
          </div>

          <form class="form-stack" @submit.prevent="createRole">
            <div>
              <label class="label" for="role-name">Role Name</label>
              <input
                id="role-name"
                v-model="form.name"
                type="text"
                placeholder="e.g. Finance Manager"
              />
              <p class="helper-text">
                Keep naming consistent so the workflow builder stays clean and readable.
              </p>
            </div>

            <div class="button-row">
              <button type="submit" class="btn btn-primary" :disabled="submitting">
                {{ submitting ? 'Creating role...' : 'Create role' }}
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
              Roles define who can approve workflow steps. If a role is referenced by a workflow,
              the backend may block deletion to preserve integrity.
            </p>
          </div>

          <div class="form-stack">
            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Examples</strong>
              <span class="role-meta"
                >HR Manager, Procurement Officer, Finance Reviewer, Department Head</span
              >
            </div>

            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Why this matters</strong>
              <span class="role-meta"
                >Clear role naming makes approvals, assignments, and audit visibility much easier
                later.</span
              >
            </div>
          </div>
        </div>
      </section>

      <section class="card card-cardy">
        <div class="toolbar">
          <div>
            <h3 class="section-title" style="margin-bottom: 4px">Existing Roles</h3>
            <p class="section-text">
              Review the current role catalog and remove roles when allowed.
            </p>
          </div>

          <div class="count-badge">
            {{ roles.length }}
          </div>
        </div>

        <div class="button-row" style="margin-bottom: 18px">
          <button class="btn btn-secondary" @click="loadRoles">Refresh list</button>
        </div>

        <p v-if="loading" class="section-text">Loading roles...</p>

        <div v-else-if="roles.length === 0" class="empty-state">
          No roles found yet. Create your first role to start building workflows.
        </div>

        <div v-else class="table-wrap">
          <table class="table-clean">
            <thead>
              <tr>
                <th>ID</th>
                <th>Role</th>
                <th>Summary</th>
                <th style="width: 160px">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="role in roles" :key="role.id">
                <td>#{{ role.id }}</td>
                <td class="role-name">{{ role.name }}</td>
                <td class="role-meta">
                  Available for workflow step assignment and role-based approvals.
                </td>
                <td>
                  <button class="btn btn-danger" @click="deleteRole(role.id)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
