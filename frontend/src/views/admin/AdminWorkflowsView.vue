<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import DashboardLayout from '../../layouts/DashboardLayout.vue'
import api from '../../api/client'

type RoleItem = {
  id: number
  name: string
}

type WorkflowStep = {
  step_key: string
  name: string
  role_id: number | ''
  sequence_order: number
  approval_mode: 'any' | 'all'
  execution_type: 'sequential' | 'parallel'
  parallel_group: string | null
}

type WorkflowItem = {
  id: number
  name?: string
  code?: string
  title?: string
  description?: string
  steps?: any[]
  definition?: {
    steps?: any[]
  }
}

const workflows = ref<WorkflowItem[]>([])
const roles = ref<RoleItem[]>([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const success = ref('')

const createEmptyStep = (order: number): WorkflowStep => ({
  step_key: '',
  name: '',
  role_id: '',
  sequence_order: order,
  approval_mode: 'any',
  execution_type: 'sequential',
  parallel_group: null,
})

const form = reactive({
  name: '',
  code: '',
  description: '',
  steps: [createEmptyStep(1)],
})

const normalizeWorkflows = (payload: any): WorkflowItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.workflows)) return payload.workflows
  return []
}

const normalizeRoles = (payload: any): RoleItem[] => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.roles)) return payload.roles
  return []
}

const loadWorkflows = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/admin/workflows')
    workflows.value = normalizeWorkflows(response.data)
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load workflows.'
  } finally {
    loading.value = false
  }
}

const loadRoles = async () => {
  try {
    const response = await api.get('/admin/roles')
    roles.value = normalizeRoles(response.data)
  } catch (_error) {
    roles.value = []
  }
}

const addStep = () => {
  form.steps.push(createEmptyStep(form.steps.length + 1))
}

const removeStep = (index: number) => {
  if (form.steps.length === 1) return
  form.steps.splice(index, 1)
  form.steps = form.steps.map((step, i) => ({
    ...step,
    sequence_order: i + 1,
  }))
}

const onExecutionTypeChange = (step: WorkflowStep) => {
  if (step.execution_type === 'sequential') {
    step.parallel_group = null
  }
}

watch(
  () => form.steps,
  (steps) => {
    steps.forEach((step) => {
      if (step.execution_type === 'sequential') {
        step.parallel_group = null
      }
    })
  },
  { deep: true },
)

const createWorkflow = async () => {
  success.value = ''
  error.value = ''

  if (!form.name.trim()) {
    error.value = 'Workflow name is required.'
    return
  }

  const invalidStep = form.steps.find(
    (step) =>
      !step.step_key.trim() ||
      !step.name.trim() ||
      step.role_id === '' ||
      !step.sequence_order ||
      !step.execution_type ||
      !step.approval_mode,
  )

  if (invalidStep) {
    error.value = 'Please complete all workflow steps.'
    return
  }

  const payloadSteps = form.steps.map((step) => ({
    step_key: step.step_key.trim(),
    name: step.name.trim(),
    role_id: Number(step.role_id),
    sequence_order: Number(step.sequence_order),
    approval_mode: step.approval_mode,
    execution_type: step.execution_type,
    parallel_group: step.execution_type === 'parallel' ? step.parallel_group?.trim() || null : null,
  }))

  submitting.value = true

  try {
    await api.post('/admin/workflows', {
      name: form.name,
      code: form.code.trim() || null,
      description: form.description,
      steps: payloadSteps,
    })

    success.value = 'Workflow created successfully.'
    form.name = ''
    form.code = ''
    form.description = ''
    form.steps = [createEmptyStep(1)]

    await loadWorkflows()
  } catch (err: any) {
    if (err?.response?.data?.errors) {
      const firstError = Object.values(err.response.data.errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : 'Validation error.'
    } else {
      error.value = err?.response?.data?.message || 'Failed to create workflow.'
    }
  } finally {
    submitting.value = false
  }
}

const getWorkflowName = (workflow: WorkflowItem) => {
  return workflow.name || workflow.title || 'Unnamed Workflow'
}

const extractSteps = (workflow: WorkflowItem): any[] => {
  if (Array.isArray(workflow.steps)) return workflow.steps
  if (Array.isArray(workflow.definition?.steps)) return workflow.definition.steps
  return []
}

const roleNameById = (roleId?: number) => {
  if (!roleId) return '—'
  return roles.value.find((role) => role.id === roleId)?.name || `Role #${roleId}`
}

onMounted(async () => {
  await Promise.all([loadWorkflows(), loadRoles()])
})
</script>

<template>
  <DashboardLayout>
    <div class="page-stack">
      <section class="card card-cardy">
        <div class="page-eyebrow">Admin / Workflow Builder</div>
        <h1 class="page-title">Workflows</h1>
        <p class="page-description">
          Create workflow definitions that control how requests move through sequential and parallel
          approval steps.
        </p>
      </section>

      <section class="grid-two">
        <div class="card card-cardy">
          <div class="card-header">
            <h3 class="section-title">Create Workflow</h3>
            <p class="section-text">
              Define a workflow type and add steps using form controls instead of manual JSON.
            </p>
          </div>

          <form class="form-stack" @submit.prevent="createWorkflow">
            <div>
              <label class="label" for="workflow-name">Workflow Name</label>
              <input
                id="workflow-name"
                v-model="form.name"
                type="text"
                placeholder="e.g. Purchase Request"
              />
            </div>

            <div>
              <label class="label" for="workflow-code">Workflow Code (optional)</label>
              <input
                id="workflow-code"
                v-model="form.code"
                type="text"
                placeholder="e.g. purchase-request"
              />
              <p class="helper-text">Leave empty to auto-generate the code.</p>
            </div>

            <div>
              <label class="label" for="workflow-description">Description</label>
              <textarea
                id="workflow-description"
                v-model="form.description"
                rows="4"
                style="resize: vertical"
                placeholder="Short description for this workflow type"
              ></textarea>
            </div>

            <div class="card" style="padding: 16px; background: #f8fafc">
              <div
                v-for="(step, index) in form.steps"
                :key="index"
                class="form-stack"
                style="
                  padding: 16px;
                  border: 1px solid #e2e8f0;
                  border-radius: 14px;
                  margin-bottom: 12px;
                "
              >
                <div class="toolbar" style="margin-bottom: 8px">
                  <strong>Step {{ index + 1 }}</strong>
                  <button
                    v-if="form.steps.length > 1"
                    type="button"
                    class="btn btn-secondary"
                    @click="removeStep(index)"
                  >
                    Remove
                  </button>
                </div>

                <div>
                  <label class="label">Step Key</label>
                  <input v-model="step.step_key" type="text" placeholder="manager_approval" />
                </div>

                <div>
                  <label class="label">Step Name</label>
                  <input v-model="step.name" type="text" placeholder="Manager Approval" />
                </div>

                <div>
                  <label class="label">Role</label>
                  <select v-model="step.role_id">
                    <option value="">Select role</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">
                      {{ role.name }}
                    </option>
                  </select>
                </div>

                <div>
                  <label class="label">Sequence Order</label>
                  <input v-model="step.sequence_order" type="number" min="1" />
                </div>

                <div>
                  <label class="label">Execution Type</label>
                  <select v-model="step.execution_type" @change="onExecutionTypeChange(step)">
                    <option value="sequential">Sequential</option>
                    <option value="parallel">Parallel</option>
                  </select>
                </div>

                <div v-if="step.execution_type === 'parallel'">
                  <label class="label">Parallel Group</label>
                  <input v-model="step.parallel_group" type="text" placeholder="finance_stage" />
                </div>

                <div>
                  <label class="label">Approval Mode</label>
                  <select v-model="step.approval_mode">
                    <option value="any">Any</option>
                    <option value="all">All</option>
                  </select>
                </div>
              </div>

              <button type="button" class="btn btn-secondary" @click="addStep">Add Step</button>
            </div>

            <div class="button-row">
              <button type="submit" class="btn btn-primary" :disabled="submitting">
                {{ submitting ? 'Creating...' : 'Create workflow' }}
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
            <h3 class="section-title">Workflow Notes</h3>
            <p class="section-text">
              A workflow represents the approval template used when employees create new requests.
            </p>
          </div>

          <div class="form-stack">
            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Available roles</strong>
              <span class="role-meta" v-if="roles.length">
                {{ roles.map((role) => `#${role.id} ${role.name}`).join(' , ') }}
              </span>
              <span class="role-meta" v-else>
                No roles loaded yet. Create roles first before defining workflow steps.
              </span>
            </div>

            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Sequential steps</strong>
              <span class="role-meta">
                Use sequential execution when each step must wait for the previous one to finish.
              </span>
            </div>

            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Parallel groups</strong>
              <span class="role-meta">
                Use the same parallel_group for steps that run together in the same stage.
              </span>
            </div>

            <div class="card" style="padding: 16px; background: #f8fafc">
              <strong style="display: block; margin-bottom: 8px">Approval modes</strong>
              <span class="role-meta">
                <strong>any</strong> means first approver wins, while <strong>all</strong> requires
                all assigned approvers.
              </span>
            </div>
          </div>
        </div>
      </section>

      <section class="card card-cardy">
        <div class="toolbar">
          <div>
            <h3 class="section-title" style="margin-bottom: 4px">Existing Workflows</h3>
            <p class="section-text">
              Review current workflow definitions and inspect their configured steps.
            </p>
          </div>

          <div class="count-badge">
            {{ workflows.length }}
          </div>
        </div>

        <div class="button-row" style="margin-bottom: 18px">
          <button class="btn btn-secondary" @click="loadWorkflows">Refresh list</button>
        </div>

        <p v-if="loading" class="section-text">Loading workflows...</p>

        <div v-else-if="workflows.length === 0" class="empty-state">
          No workflows found yet. Create your first workflow to start routing requests.
        </div>

        <div v-else style="display: grid; gap: 16px">
          <article
            v-for="workflow in workflows"
            :key="workflow.id"
            class="card"
            style="padding: 22px"
          >
            <div
              style="
                display: flex;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
                margin-bottom: 14px;
              "
            >
              <div>
                <div class="page-eyebrow" style="margin-bottom: 12px">
                  Workflow #{{ workflow.id }}
                </div>
                <h3 class="section-title" style="margin-bottom: 6px">
                  {{ getWorkflowName(workflow) }}
                </h3>
                <p class="section-text" style="margin-bottom: 4px">
                  {{ workflow.description || 'No description provided.' }}
                </p>
                <p class="role-meta">Code: {{ workflow.code || 'Auto-generated / not shown' }}</p>
              </div>

              <div style="text-align: right">
                <div class="role-meta">Steps count</div>
                <div class="role-name">
                  {{ extractSteps(workflow).length }}
                </div>
              </div>
            </div>

            <div
              v-if="extractSteps(workflow).length > 0"
              style="display: grid; gap: 12px; margin-top: 12px"
            >
              <div
                v-for="step in extractSteps(workflow)"
                :key="step.id || step.step_key || step.name"
                style="
                  padding: 16px;
                  border-radius: 16px;
                  background: #f8fafc;
                  border: 1px solid #e2e8f0;
                "
              >
                <div class="role-name" style="margin-bottom: 6px">
                  {{ step.name || 'Workflow Step' }}
                </div>
                <div class="role-meta" style="margin-bottom: 4px">
                  Key: {{ step.step_key || '—' }} | Role:
                  {{ step.role?.name || roleNameById(step.role_id) }} | Sequence:
                  {{ step.sequence_order ?? '—' }}
                </div>
                <div class="role-meta">
                  Approval: {{ step.approval_mode || '—' }} | Execution:
                  {{ step.execution_type || '—' }} | Parallel Group:
                  {{ step.parallel_group ?? '—' }}
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
