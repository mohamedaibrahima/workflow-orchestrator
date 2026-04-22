<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { ref as dbRef, onValue } from 'firebase/database'
import DashboardLayout from '../../layouts/DashboardLayout.vue'
import api from '../../api/client'
import { realtimeDb } from '../../firebase'

type ApprovalStep = {
  id?: number
  name?: string
  step_key?: string
  status?: string
  sequence_order?: number
  execution_type?: string
  approval_mode?: string
  parallel_group?: string | null
  role?: {
    id?: number
    name?: string
  }
  assignments?: Array<{
    id?: number
    user_id?: number
    status?: string
  }>
}

type ApprovalItem = {
  id?: number
  request_id?: number
  status?: string
  requester_name?: string
  requesterName?: string
  workflow_type?: {
    id?: number
    name?: string
  }
  workflowType?: {
    id?: number
    name?: string
  }
  workflow_name?: string
  workflowName?: string
  current_step?: ApprovalStep
  currentStep?: ApprovalStep
  steps?: ApprovalStep[]
  payload?: any
  created_at?: string
  createdAt?: string
}

const approvals = ref<ApprovalItem[]>([])
const loading = ref(false)
const actingKey = ref('')
const error = ref('')
const success = ref('')
let stopRealtime: null | (() => void) = null

const normalizeApprovals = (payload: any): ApprovalItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.approvals)) return payload.approvals
  if (Array.isArray(payload?.pending)) return payload.pending
  return []
}

const loadApprovals = async () => {
  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const response = await api.get('/approvals')
    approvals.value = normalizeApprovals(response.data)
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load approvals.'
  } finally {
    loading.value = false
  }
}

const getRequestId = (item: ApprovalItem) => item.id ?? item.request_id

const getWorkflowName = (item: ApprovalItem) =>
  item.workflow_name ??
  item.workflowName ??
  item.workflowType?.name ??
  item.workflow_type?.name ??
  'Workflow Request'

const getRequesterName = (item: ApprovalItem) =>
  item.requester_name ?? item.requesterName ?? 'Unknown requester'

const getStatus = (item: ApprovalItem) => item.status ?? 'pending'

const getSteps = (item: ApprovalItem): ApprovalStep[] => {
  if (Array.isArray(item.steps)) return item.steps
  return []
}

const getCurrentStep = (item: ApprovalItem): ApprovalStep | undefined => {
  return item.current_step ?? item.currentStep ?? getSteps(item)[0]
}

const getStepName = (step?: ApprovalStep) => step?.name ?? step?.step_key ?? 'Approval Step'
const getStepId = (step?: ApprovalStep) => step?.id

const mergeApprovalItem = (incoming: ApprovalItem) => {
  const requestId = getRequestId(incoming)
  if (!requestId) return

  const incomingStep = getCurrentStep(incoming)
  const hasPendingAssignment =
    incomingStep?.assignments?.some((assignment) => assignment.status === 'pending') ?? false

  const isVisible =
    ['pending', 'in_progress'].includes(getStatus(incoming)) &&
    !!incomingStep &&
    hasPendingAssignment

  const existingIndex = approvals.value.findIndex((item) => getRequestId(item) === requestId)

  if (!isVisible) {
    if (existingIndex !== -1) {
      approvals.value.splice(existingIndex, 1)
    }
    return
  }

  if (existingIndex === -1) {
    approvals.value = [incoming, ...approvals.value]
    return
  }

  approvals.value[existingIndex] = {
    ...approvals.value[existingIndex],
    ...incoming,
  }
}

const subscribeRealtime = () => {
  if (stopRealtime) {
    stopRealtime()
  }

  const approvalsRef = dbRef(realtimeDb, 'approvals')

  stopRealtime = onValue(approvalsRef, (snapshot) => {
    const payload = snapshot.val()

    if (!payload || typeof payload !== 'object') {
      return
    }

    Object.values(payload).forEach((userBucket) => {
      if (!userBucket || typeof userBucket !== 'object') {
        return
      }

      Object.values(userBucket as Record<string, unknown>).forEach((item) => {
        if (item && typeof item === 'object') {
          mergeApprovalItem(item as ApprovalItem)
        }
      })
    })
  })
}

const runAction = async (item: ApprovalItem, action: 'approve' | 'reject') => {
  const requestId = getRequestId(item)
  const step = getCurrentStep(item)
  const stepId = getStepId(step)

  if (!requestId || !stepId) {
    error.value = 'Missing request or step identifier in approvals payload.'
    return
  }

  actingKey.value = `${requestId}-${stepId}-${action}`
  error.value = ''
  success.value = ''

  try {
    await api.post(`/requests/${requestId}/steps/${stepId}/action`, {
      action,
    })

    success.value = `Step ${action}d successfully.`
    await loadApprovals()
  } catch (err: any) {
    error.value = err?.response?.data?.message || `Failed to ${action} step.`
  } finally {
    actingKey.value = ''
  }
}

const payloadPreview = (payload: any) => {
  if (!payload) return 'No payload details.'
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

const formatDate = (value?: string) => {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString()
  } catch (_error) {
    return value
  }
}

onMounted(async () => {
  await loadApprovals()
  subscribeRealtime()
})

onUnmounted(() => {
  if (stopRealtime) {
    stopRealtime()
  }
})
</script>

<template>
  <DashboardLayout>
    <div class="page-stack">
      <section class="card card-cardy">
        <div class="page-eyebrow">Approver / Action Center</div>
        <h1 class="page-title">Pending Approvals</h1>
        <p class="page-description">
          Review the approval steps assigned to your role and take action on requests that are
          waiting for your decision.
        </p>
      </section>

      <section class="card card-cardy">
        <div class="toolbar">
          <div>
            <h3 class="section-title" style="margin-bottom: 4px">Approval Inbox</h3>
            <p class="section-text">
              Keep this page open to process request steps as they become available.
            </p>
          </div>

          <div class="count-badge">
            {{ approvals.length }}
          </div>
        </div>

        <div class="button-row" style="margin-bottom: 18px">
          <button class="btn btn-secondary" @click="loadApprovals">Refresh list</button>
        </div>

        <div v-if="success" class="alert alert-success" style="margin-bottom: 16px">
          {{ success }}
        </div>

        <div v-if="error" class="alert alert-error" style="margin-bottom: 16px">
          {{ error }}
        </div>

        <p v-if="loading" class="section-text">Loading approvals...</p>

        <div v-else-if="approvals.length === 0" class="empty-state">
          No pending approvals right now.
        </div>

        <div v-else style="display: grid; gap: 16px">
          <article
            v-for="item in approvals"
            :key="getRequestId(item)"
            class="card"
            style="padding: 22px"
          >
            <div
              style="
                display: flex;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
                margin-bottom: 16px;
              "
            >
              <div>
                <div class="page-eyebrow" style="margin-bottom: 12px">
                  Request #{{ getRequestId(item) }}
                </div>
                <h3 class="section-title" style="margin-bottom: 6px">
                  {{ getWorkflowName(item) }}
                </h3>
                <p class="section-text">
                  Current step: <strong>{{ getStepName(getCurrentStep(item)) }}</strong>
                </p>
              </div>

              <div style="text-align: right">
                <div class="role-meta">Requester</div>
                <div class="role-name" style="margin-bottom: 8px">
                  {{ getRequesterName(item) }}
                </div>
                <div class="role-meta">Status: {{ getStatus(item) }}</div>
              </div>
            </div>

            <div
              style="
                padding: 16px;
                border-radius: 16px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                margin-bottom: 16px;
              "
            >
              <div class="role-meta" style="margin-bottom: 8px">Payload preview</div>
              <div style="color: #0f172a; line-height: 1.7">
                {{ payloadPreview(item.payload) }}
              </div>
            </div>

            <div
              v-if="getCurrentStep(item)"
              style="
                padding: 16px;
                border-radius: 16px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                margin-bottom: 16px;
              "
            >
              <div class="role-meta" style="margin-bottom: 8px">Step details</div>
              <div class="role-meta" style="line-height: 1.8">
                Step ID: {{ getCurrentStep(item)?.id ?? '—' }} | Role:
                {{ getCurrentStep(item)?.role?.name ?? '—' }} | Sequence:
                {{ getCurrentStep(item)?.sequence_order ?? '—' }} | Approval:
                {{ getCurrentStep(item)?.approval_mode ?? '—' }} | Execution:
                {{ getCurrentStep(item)?.execution_type ?? '—' }}
              </div>
            </div>

            <div class="button-row">
              <button
                class="btn btn-primary"
                :disabled="
                  actingKey === `${getRequestId(item)}-${getStepId(getCurrentStep(item))}-approve`
                "
                @click="runAction(item, 'approve')"
              >
                {{
                  actingKey === `${getRequestId(item)}-${getStepId(getCurrentStep(item))}-approve`
                    ? 'Approving...'
                    : 'Approve'
                }}
              </button>

              <button
                class="btn btn-danger"
                :disabled="
                  actingKey === `${getRequestId(item)}-${getStepId(getCurrentStep(item))}-reject`
                "
                @click="runAction(item, 'reject')"
              >
                {{
                  actingKey === `${getRequestId(item)}-${getStepId(getCurrentStep(item))}-reject`
                    ? 'Rejecting...'
                    : 'Reject'
                }}
              </button>
            </div>

            <p class="helper-text" style="margin-top: 12px">
              Created at: {{ formatDate(item.created_at ?? item.createdAt) }}
            </p>
          </article>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
