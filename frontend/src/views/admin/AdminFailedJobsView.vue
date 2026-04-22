<script setup lang="ts">
import { onMounted, ref } from 'vue'
import DashboardLayout from '../../layouts/DashboardLayout.vue'
import api from '../../api/client'

type FailedJobItem = {
  id?: number
  uuid: string
  queue?: string
  connection?: string
  exception?: string
  failed_at?: string
  payload?: string
}

const jobs = ref<FailedJobItem[]>([])
const loading = ref(false)
const retryingUuid = ref('')
const error = ref('')
const success = ref('')

const normalizeJobs = (payload: any): FailedJobItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.jobs)) return payload.jobs
  if (Array.isArray(payload?.failed_jobs)) return payload.failed_jobs
  return []
}

const loadFailedJobs = async () => {
  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const response = await api.get('/admin/failed-jobs')
    jobs.value = normalizeJobs(response.data)
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load failed jobs.'
  } finally {
    loading.value = false
  }
}

const retryJob = async (uuid: string) => {
  retryingUuid.value = uuid
  error.value = ''
  success.value = ''

  try {
    await api.post(`/admin/failed-jobs/${uuid}/retry`)
    success.value = 'Job retried successfully.'
    await loadFailedJobs()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to retry job.'
  } finally {
    retryingUuid.value = ''
  }
}

const shortText = (value?: string, max = 160) => {
  if (!value) return '—'
  return value.length > max ? `${value.slice(0, max)}...` : value
}

onMounted(() => {
  loadFailedJobs()
})
</script>

<template>
  <DashboardLayout>
    <div class="page-stack">
      <section class="card card-cardy">
        <div class="page-eyebrow">Admin / Operations</div>
        <h1 class="page-title">Failed Jobs</h1>
        <p class="page-description">
          Inspect background job failures and retry dead-lettered work safely from the admin
          console.
        </p>
      </section>

      <section class="card card-cardy">
        <div class="toolbar">
          <div>
            <h3 class="section-title" style="margin-bottom: 4px">Job Recovery Console</h3>
            <p class="section-text">
              Use this view to inspect worker issues and retry jobs when appropriate.
            </p>
          </div>

          <div class="count-badge">
            {{ jobs.length }}
          </div>
        </div>

        <div class="button-row" style="margin-bottom: 18px">
          <button class="btn btn-secondary" @click="loadFailedJobs">Refresh list</button>
        </div>

        <div v-if="success" class="alert alert-success" style="margin-bottom: 16px">
          {{ success }}
        </div>

        <div v-if="error" class="alert alert-error" style="margin-bottom: 16px">
          {{ error }}
        </div>

        <p v-if="loading" class="section-text">Loading failed jobs...</p>

        <div v-else-if="jobs.length === 0" class="empty-state">
          No failed jobs were found. Your workers appear healthy right now.
        </div>

        <div v-else class="table-wrap">
          <table class="table-clean">
            <thead>
              <tr>
                <th>UUID</th>
                <th>Queue</th>
                <th>Failed At</th>
                <th>Exception</th>
                <th style="width: 180px">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="job in jobs" :key="job.uuid">
                <td>
                  <div class="role-name">{{ job.uuid }}</div>
                  <div class="role-meta">{{ job.connection || 'default connection' }}</div>
                </td>
                <td class="role-meta">{{ job.queue || 'default' }}</td>
                <td class="role-meta">{{ job.failed_at || '—' }}</td>
                <td class="role-meta">
                  {{ shortText(job.exception) }}
                </td>
                <td>
                  <button
                    class="btn btn-primary"
                    @click="retryJob(job.uuid)"
                    :disabled="retryingUuid === job.uuid"
                  >
                    {{ retryingUuid === job.uuid ? 'Retrying...' : 'Retry job' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
