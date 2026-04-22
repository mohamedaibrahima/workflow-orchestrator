<script setup lang="ts">
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
  email: '',
  password: '',
})

const getDefaultRoute = () => {
  if (auth.isAdmin) return '/admin/roles'
  if (auth.canApprove) return '/approvals'
  return '/requests'
}

const submit = async () => {
  try {
    await auth.login(form)
    router.push(getDefaultRoute())
  } catch (_error) {}
}
</script>

<template>
  <div
    style="
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      background:
        radial-gradient(circle at top left, rgba(96, 165, 250, 0.18), transparent 30%),
        radial-gradient(circle at bottom right, rgba(37, 99, 235, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
    "
  >
    <section
      style="
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 64px;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.96) 0%, rgba(17, 24, 39, 0.98) 100%);
        color: white;
      "
    >
      <div style="max-width: 540px">
        <div
          style="
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: #bfdbfe;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 24px;
          "
        >
          <span
            style="
              width: 10px;
              height: 10px;
              border-radius: 999px;
              background: linear-gradient(135deg, #60a5fa, #2563eb);
              box-shadow: 0 0 0 6px rgba(96, 165, 250, 0.12);
            "
          ></span>
          Workflow Orchestrator
        </div>

        <h1
          style="
            margin: 0 0 16px;
            font-size: 3.25rem;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -0.04em;
          "
        >
          Manage approvals with clarity and control.
        </h1>

        <p
          style="
            margin: 0 0 28px;
            color: #cbd5e1;
            font-size: 1.04rem;
            line-height: 1.8;
            max-width: 48ch;
          "
        >
          Sign in to access roles, workflows, request operations, approvals, and failed-job recovery
          tools from one clean dashboard.
        </p>

        <div style="display: grid; gap: 14px; max-width: 480px">
          <div
            style="
              padding: 18px 20px;
              border-radius: 18px;
              background: rgba(255, 255, 255, 0.06);
              border: 1px solid rgba(255, 255, 255, 0.08);
            "
          >
            <strong style="display: block; margin-bottom: 8px; font-size: 1rem">
              Role-driven workflows
            </strong>
            <span style="color: #cbd5e1; line-height: 1.7">
              Configure sequential and parallel approval flows with dynamic role assignment.
            </span>
          </div>

          <div
            style="
              padding: 18px 20px;
              border-radius: 18px;
              background: rgba(255, 255, 255, 0.06);
              border: 1px solid rgba(255, 255, 255, 0.08);
            "
          >
            <strong style="display: block; margin-bottom: 8px; font-size: 1rem">
              Operational visibility
            </strong>
            <span style="color: #cbd5e1; line-height: 1.7">
              Track requests, monitor failed jobs, and recover background processing safely.
            </span>
          </div>
        </div>
      </div>
    </section>

    <section style="display: flex; align-items: center; justify-content: center; padding: 40px">
      <div
        style="
          width: 100%;
          max-width: 470px;
          background: rgba(255, 255, 255, 0.86);
          border: 1px solid rgba(226, 232, 240, 0.9);
          border-radius: 28px;
          box-shadow: 0 24px 60px rgba(15, 23, 42, 0.1);
          backdrop-filter: blur(18px);
          padding: 34px;
        "
      >
        <div style="margin-bottom: 28px">
          <div
            style="
              display: inline-flex;
              align-items: center;
              gap: 8px;
              padding: 6px 12px;
              border-radius: 999px;
              background: #eff6ff;
              color: #2563eb;
              font-size: 0.8rem;
              font-weight: 700;
              letter-spacing: 0.05em;
              text-transform: uppercase;
              margin-bottom: 16px;
            "
          >
            Secure Access
          </div>

          <h2
            style="
              margin: 0 0 8px;
              font-size: 2rem;
              font-weight: 800;
              letter-spacing: -0.03em;
              color: #0f172a;
            "
          >
            Welcome back
          </h2>

          <p style="margin: 0; color: #64748b; line-height: 1.7">
            Enter your credentials to continue to the dashboard.
          </p>
        </div>

        <form @submit.prevent="submit" style="display: grid; gap: 18px">
          <div>
            <label
              for="email"
              style="
                display: block;
                margin-bottom: 8px;
                font-size: 0.92rem;
                font-weight: 700;
                color: #334155;
              "
            >
              Email
            </label>
            <input id="email" v-model="form.email" type="email" placeholder="admin@example.com" />
          </div>

          <div>
            <label
              for="password"
              style="
                display: block;
                margin-bottom: 8px;
                font-size: 0.92rem;
                font-weight: 700;
                color: #334155;
              "
            >
              Password
            </label>
            <input id="password" v-model="form.password" type="password" placeholder="••••••••" />
          </div>

          <div v-if="auth.error" class="alert alert-error">
            {{ auth.error }}
          </div>

          <button
            type="submit"
            class="btn btn-primary"
            style="width: 100%; min-height: 50px"
            :disabled="auth.loading"
          >
            {{ auth.loading ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>
      </div>
    </section>
  </div>
</template>
