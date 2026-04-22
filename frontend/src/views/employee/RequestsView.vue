<script setup lang="ts">
import { onMounted, reactive, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '../../layouts/DashboardLayout.vue'
import api from '../../api/client'

type WorkflowStep = {
  id?: number
  name?: string
  role?: {
    id?: number
    name?: string
  }
  approval_mode?: string
  execution_type?: string
  sequence_order?: number
  parallel_group?: string | number | null
}

type WorkflowTypeItem = {
  id: number
  name?: string
  code?: string
  description?: string
  steps?: WorkflowStep[]
}

type RequestItem = {
  id: number
  status?: string
  workflow_type_id?: number
  workflow_type_name?: string
  workflow_name?: string
  created_at?: string
  current_step_name?: string
  payload?: any
  workflow_type?: {
    id?: number
    name?: string
    code?: string
    description?: string
  }
}

const router = useRouter()
const requests = ref<RequestItem[]>([])
const workflowTypes = ref<WorkflowTypeItem[]>([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const success = ref('')

const form = reactive({
  workflow_type_id: '',
  title: 'Purchase Laptop',
  amount: '25000',
  reason: 'For new employee onboarding',
  notes: '',
})

const normalizeRequests = (payload: any): RequestItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.requests)) return payload.requests
  return []
}

const normalizeWorkflowTypes = (payload: any): WorkflowTypeItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.workflows)) return payload.workflows
  return []
}

const loadRequests = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/requests')
    requests.value = normalizeRequests(response.data)
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load requests.'
  } finally {
    loading.value = false
  }
}

const loadWorkflowTypes = async () => {
  try {
    const response = await api.get('/requests/workflow-types')
    workflowTypes.value = normalizeWorkflowTypes(response.data)
  } catch (_err) {
    workflowTypes.value = []
  }
}

const submitRequest = async () => {
  success.value = ''
  error.value = ''

  if (!form.workflow_type_id) {
    error.value = 'Please select a workflow type.'
    return
  }

  if (!form.title.trim()) {
    error.value = 'Title is required.'
    return
  }

  if (!form.amount.trim()) {
    error.value = 'Amount is required.'
    return
  }

  const amountValue = Number(form.amount)

  if (Number.isNaN(amountValue)) {
    error.value = 'Amount must be a valid number.'
    return
  }

  submitting.value = true

  try {
    const response = await api.post('/requests', {
      workflow_type_id: Number(form.workflow_type_id),
      payload: {
        title: form.title.trim(),
        amount: amountValue,
        reason: form.reason.trim(),
        notes: form.notes.trim(),
      },
    })

    const createdRequestId = response.data?.data?.id

    success.value = 'Request created successfully.'
    form.workflow_type_id = ''
    form.title = 'Purchase Laptop'
    form.amount = '25000'
    form.reason = 'For new employee onboarding'
    form.notes = ''
    await loadRequests()

    if (createdRequestId) {
      router.push(`/requests/${createdRequestId}`)
    }
  } catch (err: any) {
    if (err?.response?.data?.errors) {
      const firstError = Object.values(err.response.data.errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : 'Validation error.'
    } else {
      error.value = err?.response?.data?.message || 'Failed to create request.'
    }
  } finally {
    submitting.value = false
  }
}

const requestTitle = (item: RequestItem) =>
  item.workflow_type?.name ||
  item.workflow_name ||
  item.workflow_type_name ||
  `Workflow #${item.workflow_type_id ?? '—'}`

const payloadPreview = (payload: any) => {
  if (!payload) return 'No payload data.'

  if (typeof payload === 'string') {
    return payload.length > 140 ? `${payload.slice(0, 140)}...` : payload
  }

  try {
    const text = JSON.stringify(payload)
    return text.length > 140 ? `${text.slice(0, 140)}...` : text
  } catch (_error) {
    return 'Payload preview unavailable.'
  }
}

const selectedWorkflow = computed(() => {
  return (
    workflowTypes.value.find((workflow) => workflow.id === Number(form.workflow_type_id)) || null
  )
})

const openDetails = (id: number) => {
  router.push(`/requests/${id}`)
}

const statusLabel = (status?: string) => {
  return status || 'pending'
}

onMounted(async () => {
  await Promise.all([loadRequests(), loadWorkflowTypes()])
})
</script>

<template>
  <DashboardLayout>
    <div class="page-stack">
      <section class="card card-cardy">
        <div class="page-eyebrow">Employee / Request Center</div>
        <h1 class="page-title">Requests</h1>
        <p class="page-description">
          Create new workflow requests and monitor their progress across the approval pipeline.
        </p>
      </section>

      <section class="grid-two">
        <div class="card card-cardy">
          <div class="card-header">
            <h3 class="section-title">Create New Request</h3>
            <p class="section-text">
              Select an active workflow type and fill in a simple request form. The app will package
              your input into the JSON payload expected by the backend.
            </p>
          </div>

          <form class="form-stack" @submit.prevent="submitRequest">
            <div>
              <label class="label" for="workflow-type">Workflow Type</label>
              <select id="workflow-type" v-model="form.workflow_type_id">
                <option value="">Select a workflow</option>
                <option v-for="workflow in workflowTypes" :key="workflow.id" :value="workflow.id">
                  {{ workflow.name || `Workflow #${workflow.id}` }}
                </option>
              </select>
            </div>

            <div v-if="selectedWorkflow" class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">
                {{ selectedWorkflow.name }}
              </strong>
              <span class="role-meta" style="display: block; margin-bottom: 8px">
                {{ selectedWorkflow.description || 'No description provided.' }}
              </span>

              <div
                v-if="selectedWorkflow.steps?.length"
                style="display: grid; gap: 10px; margin-top: 12px"
              >
                <div
                  v-for="step in selectedWorkflow.steps"
                  :key="`${step.id}-${step.name}`"
                  style="
                    padding: 12px;
                    border-radius: 12px;
                    background: white;
                    border: 1px solid #e2e8f0;
                  "
                >
                  <div class="role-name" style="margin-bottom: 4px">
                    {{ step.name || 'Workflow Step' }}
                  </div>
                  <div class="role-meta">
                    Role: {{ step.role?.name || '—' }} | Sequence:
                    {{ step.sequence_order ?? '—' }} | Approval: {{ step.approval_mode || '—' }} |
                    Execution:
                    {{ step.execution_type || '—' }}
                  </div>
                </div>
              </div>
            </div>

            <div>
              <label class="label" for="title">Request Title</label>
              <input
                id="title"
                v-model="form.title"
                type="text"
                placeholder="e.g. Purchase Laptop"
              />
            </div>

            <div>
              <label class="label" for="amount">Amount</label>
              <input
                id="amount"
                v-model="form.amount"
                type="number"
                min="0"
                step="0.01"
                placeholder="e.g. 25000"
              />
            </div>

            <div>
              <label class="label" for="reason">Reason</label>
              <textarea
                id="reason"
                v-model="form.reason"
                rows="4"
                style="resize: vertical"
                placeholder="Explain why this request is needed"
              ></textarea>
            </div>

            <div>
              <label class="label" for="notes">Notes</label>
              <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                style="resize: vertical"
                placeholder="Optional extra details"
              ></textarea>
            </div>

            <div class="button-row">
              <button type="submit" class="btn btn-primary" :disabled="submitting">
                {{ submitting ? 'Submitting...' : 'Create request' }}
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
            <h3 class="section-title">How it works</h3>
            <p class="section-text">
              After submission, the backend snapshots the workflow steps and assignees, then the
              request advances through sequential or parallel approvals based on the configured
              pipeline.
            </p>
          </div>

          <div class="form-stack">
            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Before you submit</strong>
              <span class="role-meta">
                Fill the fields naturally. The system converts them into the structured payload
                required by the API.
              </span>
            </div>

            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">After submission</strong>
              <span class="role-meta">
                Approvers will see pending steps in their inbox and the request status will update
                as the orchestration continues.
              </span>
            </div>
          </div>
        </div>
      </section>

      <section class="card card-cardy">
        <div class="toolbar">
          <div>
            <h3 class="section-title" style="margin-bottom: 4px">My Requests</h3>
            <p class="section-text">
              Review submitted requests and track their current workflow state.
            </p>
          </div>

          <div class="count-badge">
            {{ requests.length }}
          </div>
        </div>

        <div class="button-row" style="margin-bottom: 18px">
          <button class="btn btn-secondary" @click="loadRequests">Refresh list</button>
        </div>

        <p v-if="loading" class="section-text">Loading requests...</p>

        <div v-else-if="requests.length === 0" class="empty-state">
          No requests found yet. Create your first request to test the workflow engine.
        </div>

        <div v-else style="display: grid; gap: 16px">
          <div
            v-for="item in requests"
            :key="item.id"
            class="card"
            style="padding: 18px; border: 1px solid #e2e8f0; background: #ffffff"
          >
            <div
              style="
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 16px;
                flex-wrap: wrap;
              "
            >
              <div>
                <div class="role-name" style="margin-bottom: 6px">
                  {{ requestTitle(item) }}
                </div>
                <div class="role-meta" style="margin-bottom: 8px">
                  Request #{{ item.id }} • Status: {{ statusLabel(item.status) }}
                </div>
                <div class="section-text">
                  {{ payloadPreview(item.payload) }}
                </div>
              </div>

              <div class="button-row">
                <button class="btn btn-secondary" @click="openDetails(item.id)">
                  Open details
                </button>
              </div>
            </div>

            <div class="role-meta" style="margin-top: 14px">
              Created at: {{ item.created_at || '—' }}
            </div>
          </div>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
