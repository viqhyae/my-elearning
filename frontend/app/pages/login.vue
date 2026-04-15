<script setup lang="ts">
import { DEMO_ACCOUNTS, type DemoAccount } from '../constants/demoAccounts'

definePageMeta({
  middleware: ['guest'],
})

const auth = useAuth()

await auth.ensureSession()

const quickAccounts = DEMO_ACCOUNTS

const email = ref(quickAccounts[0]?.email ?? '')
const password = ref(quickAccounts[0]?.password ?? '')
const isSubmitting = ref(false)
const loginError = ref('')

const fillAccount = (account: DemoAccount) => {
  email.value = account.email
  password.value = account.password
}

const submitLogin = async () => {
  loginError.value = ''
  isSubmitting.value = true

  try {
    const user = await auth.login(email.value, password.value)
    await navigateTo(auth.defaultPathByRole(user.role))
  } catch (error: unknown) {
    const message = (error as { data?: { message?: string } })?.data?.message
    loginError.value = message || 'Login gagal. Cek email dan password.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section class="page-head">
    <div class="container">
      <p class="eyebrow">Authentication</p>
      <h1 class="page-title">Login Dashboard LMS</h1>
      <p class="page-copy">
        Gunakan akun dummy untuk masuk sebagai admin, mentor, atau student.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container auth-grid">
      <article class="panel-card">
        <h2>Masuk</h2>
        <div class="form-grid">
          <label class="form-field form-field-full">
            <span>Email</span>
            <input v-model="email" type="email" placeholder="admin@elearning.local" />
          </label>
          <label class="form-field form-field-full">
            <span>Password</span>
            <input v-model="password" type="password" placeholder="password123" />
          </label>
        </div>
        <p v-if="loginError" class="status-meta status-error">{{ loginError }}</p>
        <div class="form-actions">
          <button type="button" class="btn btn-primary" :disabled="isSubmitting" @click="submitLogin()">
            {{ isSubmitting ? 'Memproses...' : 'Login' }}
          </button>
        </div>
      </article>

      <article class="panel-card">
        <h2>Akun Dummy</h2>
        <div class="stack-list">
          <div v-for="account in quickAccounts" :key="account.email" class="stack-row">
            <p class="stack-title">{{ account.label }}</p>
            <p class="stack-meta">{{ account.email }}</p>
            <p class="stack-meta">password: {{ account.password }}</p>
            <p class="stack-meta">dashboard: {{ account.defaultPath }}</p>
            <button type="button" class="btn btn-secondary btn-small" @click="fillAccount(account)">
              Gunakan akun ini
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
