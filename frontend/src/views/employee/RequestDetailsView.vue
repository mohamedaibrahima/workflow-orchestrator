<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DashboardLayout from '../../layouts/DashboardLayout.vue'
import api from '../../api/client'

type RoleItem = {
  id?: number
  name?: string
}

type UserItem = {
  id?: number
  name?: string
  email?: string
}

type AssignmentItem = {
  id?: number
  status?: string
  acted_at?: string | null
  user?: UserItem | null
  role?: RoleItem | null
}

type ActionItem = {
  id?: number
  action?: string
  comment?: string | null
  created_at?: string
  acted_at?: string
  user?: UserItem | null
}

type StepItem = {
  id: number
  name?: string
  status?: string
  sequence_order?: number
  execution_type?: string
  approval_mode?: string
  parallel_group?: string | number | null
  role?: RoleItem | null
  actor?: UserItem | null
  assignments?: AssignmentItem[]
  actions?: ActionItem[]
}

type AuditEventItem = {
  id?: number
  event_type?: string
  occurred_at?: string
  created_at?: string
  payload?: any
  user?: UserItem | null
}

type RequestDetails = {
  id: number
  status?: string
  created_at?: string
  payload?: any
  workflow_type?: {
    id?: number
    name?: string
    code?: string
    description?: string
  } | null
  steps?: StepItem[]
  actions?: ActionItem[]
  audit_events?: AuditEventItem[]
}

const route = useRoute()
const router = useRouter()

const requestItem = ref<RequestDetails | null>(null)
const loading = ref(false)
const error = ref('')

const fetchRequestDetails = async (id: string | string[] | undefined) => {
  if (!id) return

  loading.value = true
  error.value = ''

  try {
    const response = await api.get(`/requests/${id}`)
    requestItem.value = response.data?.data || response.data
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load request details.'
    requestItem.value = null
  } finally {
    loading.value = false
  }
}

const formatJson = (value: any) => {
  if (!value) return 'No data available.'

  try {
    return JSON.stringify(value, null, 2)
  } catch (_error) {
    return String(value)
  }
}

const statusClass = (status?: string) => {
  switch (status) {
    case 'approved':
      return { color: '#166534', background: '#dcfce7' }
    case 'rejected':
      return { color: '#991b1b', background: '#fee2e2' }
    case 'in_progress':
      return { color: '#1d4ed8', background: '#dbeafe' }
    default:
      return { color: '#92400e', background: '#fef3c7' }
  }
}

const actionTimestamp = (action?: ActionItem) => {
  return action?.acted_at || action?.created_at || '—'
}

const auditTimestamp = (event?: AuditEventItem) => {
  return event?.occurred_at || event?.created_at || '—'
}

onMounted(() => {
  fetchRequestDetails(route.params.id)
})

watch(
  () => route.params.id,
  (id) => {
    fetchRequestDetails(id)
  },
)
</script>

<template>
  <DashboardLayout>
    <div class="page-stack">
      <section class="card card-cardy">
        <div class="button-row" style="margin-bottom: 16px">
          <button class="btn btn-secondary" @click="router.push('/requests')">
            Back to requests
          </button>
        </div>

        <div v-if="loading" class="section-text">Loading request details...</div>

        <div v-else-if="error" class="alert alert-error">
          {{ error }}
        </div>

        <div v-else-if="requestItem">
          <div class="page-eyebrow">Employee / Request Details</div>
          <h1 class="page-title" style="margin-bottom: 8px">
            {{ requestItem.workflow_type?.name || `Request #${requestItem.id}` }}
          </h1>
          <p class="page-description" style="margin-bottom: 18px">
            Track the full lifecycle of this request, including approvals, assignments, and audit
            history.
          </p>

          <div class="grid-two" style="margin-bottom: 24px">
            <div class="card" style="padding: 18px">
              <div class="role-meta">Request ID</div>
              <div class="role-name">#{{ requestItem.id }}</div>
            </div>

            <div class="card" style="padding: 18px">
              <div class="role-meta">Status</div>
              <div
                class="role-name"
                :style="{
                  display: 'inline-block',
                  padding: '6px 12px',
                  borderRadius: '999px',
                  ...statusClass(requestItem.status),
                }"
              >
                {{ requestItem.status || 'pending' }}
              </div>
            </div>
          </div>

          <section class="card" style="padding: 22px; margin-bottom: 20px">
            <h3 class="section-title" style="margin-bottom: 10px">Workflow info</h3>
            <p class="section-text" style="margin-bottom: 6px">
              <strong>Name:</strong> {{ requestItem.workflow_type?.name || '—' }}
            </p>
            <p class="section-text" style="margin-bottom: 6px">
              <strong>Code:</strong> {{ requestItem.workflow_type?.code || '—' }}
            </p>
            <p class="section-text">
              <strong>Description:</strong> {{ requestItem.workflow_type?.description || '—' }}
            </p>
          </section>

          <section class="card" style="padding: 22px; margin-bottom: 20px">
            <h3 class="section-title" style="margin-bottom: 10px">Payload</h3>
            <pre
              style="
                background: #0f172a;
                color: #e2e8f0;
                padding: 16px;
                border-radius: 16px;
                overflow: auto;
                line-height: 1.6;
              "
              >{{ formatJson(requestItem.payload) }}</pre
            >
          </section>

          <section class="card" style="padding: 22px; margin-bottom: 20px">
            <h3 class="section-title" style="margin-bottom: 16px">Workflow steps</h3>

            <div v-if="requestItem.steps?.length" style="display: grid; gap: 14px">
              <div
                v-for="step in requestItem.steps"
                :key="step.id"
                style="
                  padding: 16px;
                  border-radius: 16px;
                  border: 1px solid #e2e8f0;
                  background: #f8fafc;
                "
              >
                <div
                  style="
                    display: flex;
                    justify-content: space-between;
                    gap: 16px;
                    flex-wrap: wrap;
                    margin-bottom: 10px;
                  "
                >
                  <div>
                    <div class="role-name">{{ step.name || `Step #${step.id}` }}</div>
                    <div class="role-meta">
                      Role: {{ step.role?.name || '—' }} | Sequence:
                      {{ step.sequence_order ?? '—' }} | Approval: {{ step.approval_mode || '—' }} |
                      Execution: {{ step.execution_type || '—' }} | Group:
                      {{ step.parallel_group ?? '—' }}
                    </div>
                  </div>

                  <div
                    :style="{
                      display: 'inline-block',
                      padding: '6px 12px',
                      borderRadius: '999px',
                      height: 'fit-content',
                      ...statusClass(step.status),
                    }"
                  >
                    {{ step.status || 'pending' }}
                  </div>
                </div>

                <div v-if="step.assignments?.length" style="margin-top: 12px">
                  <div class="role-meta" style="margin-bottom: 8px">Assignments</div>
                  <div style="display: grid; gap: 8px">
                    <div
                      v-for="assignment in step.assignments"
                      :key="assignment.id"
                      style="
                        padding: 12px;
                        border-radius: 12px;
                        background: white;
                        border: 1px solid #e2e8f0;
                      "
                    >
                      <div class="role-name">
                        {{ assignment.user?.name || 'Unknown user' }}
                      </div>
                      <div class="role-meta">
                        {{ assignment.user?.email || '—' }} | Status:
                        {{ assignment.status || 'pending' }} | Acted at:
                        {{ assignment.acted_at || '—' }}
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="step.actions?.length" style="margin-top: 12px">
                  <div class="role-meta" style="margin-bottom: 8px">Step actions</div>
                  <div style="display: grid; gap: 8px">
                    <div
                      v-for="action in step.actions"
                      :key="action.id"
                      style="
                        padding: 12px;
                        border-radius: 12px;
                        background: white;
                        border: 1px solid #e2e8f0;
                      "
                    >
                      <div class="role-name">
                        {{ action.user?.name || 'Unknown user' }} — {{ action.action || 'action' }}
                      </div>
                      <div class="role-meta">
                        {{ actionTimestamp(action) }}
                      </div>
                      <div v-if="action.comment" class="section-text" style="margin-top: 6px">
                        {{ action.comment }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="empty-state">No steps available for this request.</div>
          </section>

          <section class="card" style="padding: 22px; margin-bottom: 20px">
            <h3 class="section-title" style="margin-bottom: 16px">Request actions</h3>

            <div v-if="requestItem.actions?.length" style="display: grid; gap: 10px">
              <div
                v-for="action in requestItem.actions"
                :key="action.id"
                style="
                  padding: 14px;
                  border-radius: 14px;
                  background: #f8fafc;
                  border: 1px solid #e2e8f0;
                "
              >
                <div class="role-name">
                  {{ action.user?.name || 'Unknown user' }} — {{ action.action || 'action' }}
                </div>
                <div class="role-meta">
                  {{ actionTimestamp(action) }}
                </div>
                <div v-if="action.comment" class="section-text" style="margin-top: 6px">
                  {{ action.comment }}
                </div>
              </div>
            </div>

            <div v-else class="empty-state">No request actions recorded yet.</div>
          </section>

          <section class="card" style="padding: 22px">
            <h3 class="section-title" style="margin-bottom: 16px">Audit trail</h3>

            <div v-if="requestItem.audit_events?.length" style="display: grid; gap: 10px">
              <div
                v-for="event in requestItem.audit_events"
                :key="event.id"
                style="
                  padding: 14px;
                  border-radius: 14px;
                  background: #f8fafc;
                  border: 1px solid #e2e8f0;
                "
              >
                <div class="role-name">
                  {{ event.event_type || 'event' }}
                </div>
                <div class="role-meta" style="margin-top: 4px">
                  {{ auditTimestamp(event) }}
                </div>
                <pre
                  v-if="event.payload"
                  style="
                    margin-top: 10px;
                    background: #ffffff;
                    padding: 12px;
                    border-radius: 12px;
                    overflow: auto;
                    line-height: 1.5;
                    border: 1px solid #e2e8f0;
                  "
                  >{{ formatJson(event.payload) }}</pre
                >
              </div>
            </div>

            <div v-else class="empty-state">No audit events recorded yet.</div>
          </section>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
