<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'role'],
  role: ['student', 'mentor', 'admin'],
})

type CourseItem = {
  id: number
  title: string
  slug: string
  level: string
  category: string | null
  price_amount: number
  currency: string
  price_label: string
}

type TransactionItem = {
  id: number
  reference: string
  status: 'pending' | 'paid'
  payment_method: 'bank_transfer' | 'ewallet' | 'qris'
  course: {
    id: number
    title: string
    slug: string
  } | null
  voucher: {
    id: number
    name: string
    code: string
    promo_price: number
  } | null
  original_price: number
  discount_amount: number
  final_price: number
  original_price_label: string
  discount_amount_label: string
  final_price_label: string
  created_at: string | null
  paid_at: string | null
}

type CheckoutResponse = {
  message: string
  transaction: TransactionItem
}

type PayResponse = {
  message: string
  transaction: TransactionItem
}

const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const apiBase = runtimeConfig.public.apiBase
const auth = useAuth()

await auth.ensureSession()

if (process.client && !auth.isAuthenticated.value) {
  await navigateTo('/login')
}

const headers = computed(() => auth.authHeaders())

const {
  data: coursesData,
  pending: coursesPending,
  error: coursesError,
  refresh: refreshCourses,
} = await useFetch<CourseItem[]>('/api/courses', {
  baseURL: apiBase,
  server: false,
  default: () => [],
})

const {
  data: transactionsData,
  pending: transactionsPending,
  error: transactionsError,
  refresh: refreshTransactions,
} = await useFetch<TransactionItem[]>('/api/student/transactions', {
  baseURL: apiBase,
  server: false,
  headers,
  default: () => [],
})

const checkoutForm = reactive({
  course_id: 0,
  voucher_code: '',
  payment_method: 'qris' as 'bank_transfer' | 'ewallet' | 'qris',
})

const checkoutMessage = ref('')
const checkoutError = ref('')
const isCheckoutSubmitting = ref(false)
const latestDraftTransaction = ref<TransactionItem | null>(null)
const payingTransactionId = ref<number | null>(null)

const selectedCourse = computed(() =>
  coursesData.value.find((course) => course.id === checkoutForm.course_id) || null
)

const statusLabel = (status: TransactionItem['status']) => (status === 'paid' ? 'Lunas' : 'Menunggu Bayar')
const paymentMethodLabel = (method: TransactionItem['payment_method']) => {
  if (method === 'bank_transfer') {
    return 'Bank Transfer'
  }

  if (method === 'ewallet') {
    return 'E-Wallet'
  }

  return 'QRIS'
}

watch(
  coursesData,
  (courses) => {
    const courseIdFromQuery = Number(route.query.course || 0)

    if (!courseIdFromQuery) {
      return
    }

    const found = courses.find((course) => course.id === courseIdFromQuery)

    if (found) {
      checkoutForm.course_id = found.id
    }
  },
  { immediate: true }
)

const extractError = (error: unknown, fallback: string) => {
  const data = (error as { data?: { message?: string; errors?: Record<string, string[]> } })?.data

  if (data?.errors) {
    const firstKey = Object.keys(data.errors)[0]
    const firstMessage = firstKey ? data.errors[firstKey]?.[0] : ''

    if (firstMessage) {
      return firstMessage
    }
  }

  return data?.message || fallback
}

const checkout = async () => {
  if (!checkoutForm.course_id) {
    checkoutError.value = 'Pilih kelas terlebih dahulu.'
    return
  }

  isCheckoutSubmitting.value = true
  checkoutError.value = ''
  checkoutMessage.value = ''

  try {
    const response = await $fetch<CheckoutResponse>('/api/student/transactions/checkout', {
      method: 'POST',
      baseURL: apiBase,
      headers: headers.value,
      body: {
        course_id: checkoutForm.course_id,
        voucher_code: checkoutForm.voucher_code.trim() || null,
        payment_method: checkoutForm.payment_method,
      },
    })

    latestDraftTransaction.value = response.transaction
    checkoutMessage.value = response.message
    await refreshTransactions()
  } catch (error: unknown) {
    checkoutError.value = extractError(error, 'Gagal membuat transaksi.')
  } finally {
    isCheckoutSubmitting.value = false
  }
}

const payNow = async (transaction: TransactionItem) => {
  payingTransactionId.value = transaction.id
  checkoutError.value = ''
  checkoutMessage.value = ''

  try {
    const response = await $fetch<PayResponse>(`/api/student/transactions/${transaction.id}/pay`, {
      method: 'POST',
      baseURL: apiBase,
      headers: headers.value,
    })

    latestDraftTransaction.value = response.transaction
    checkoutMessage.value = response.message
    await refreshTransactions()
  } catch (error: unknown) {
    checkoutError.value = extractError(error, 'Gagal memproses pembayaran.')
  } finally {
    payingTransactionId.value = null
  }
}

const formatDateTime = (value: string | null) => {
  if (!value) {
    return '-'
  }

  return new Date(value).toLocaleString('id-ID')
}
</script>

<template>
  <section class="page-head">
    <div class="container">
      <p class="eyebrow">Pembayaran Kelas</p>
      <h1 class="page-title">Transaksi Belajar</h1>
      <p class="page-copy">
        Pilih kelas, masukkan voucher jika ada, lalu lakukan pembayaran. Semua riwayat tercatat otomatis.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="panel-grid">
        <article class="panel-card">
          <h2>Buat Transaksi Baru</h2>
          <p class="stack-meta">Langkah 1: pilih kelas dan metode pembayaran.</p>

          <div class="form-grid">
            <label class="form-field form-field-full">
              <span>Kelas</span>
              <select v-model.number="checkoutForm.course_id">
                <option :value="0">Pilih kelas</option>
                <option v-for="course in coursesData" :key="course.id" :value="course.id">
                  {{ course.title }} - {{ course.price_label }}
                </option>
              </select>
            </label>

            <label class="form-field form-field-full">
              <span>Kode Voucher (opsional)</span>
              <input
                v-model="checkoutForm.voucher_code"
                type="text"
                placeholder="Contoh: HEMAT50K"
              />
            </label>

            <label class="form-field form-field-full">
              <span>Metode Bayar</span>
              <select v-model="checkoutForm.payment_method">
                <option value="qris">QRIS</option>
                <option value="ewallet">E-Wallet</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </label>
          </div>

          <div v-if="selectedCourse" class="payment-preview section-spacer-sm">
            <p class="stack-title">{{ selectedCourse.title }}</p>
            <p class="stack-meta">{{ selectedCourse.level }} - {{ selectedCourse.category || 'General' }}</p>
            <p class="stack-percent">{{ selectedCourse.price_label }}</p>
          </div>

          <p v-if="coursesPending" class="status-meta">Memuat daftar kelas...</p>
          <p v-if="coursesError" class="status-meta status-error">
            Gagal memuat daftar kelas.
            <button type="button" class="btn btn-secondary btn-small" @click="refreshCourses()">Coba lagi</button>
          </p>

          <p v-if="checkoutError" class="status-meta status-error">{{ checkoutError }}</p>
          <p v-if="checkoutMessage" class="status-meta">{{ checkoutMessage }}</p>

          <div class="form-actions">
            <button type="button" class="btn btn-primary" :disabled="isCheckoutSubmitting" @click="checkout()">
              {{ isCheckoutSubmitting ? 'Memproses...' : 'Buat Tagihan' }}
            </button>
          </div>
        </article>

        <article class="panel-card">
          <h2>Konfirmasi Pembayaran</h2>
          <p class="stack-meta">Langkah 2: lakukan pembayaran pada transaksi pending.</p>

          <div v-if="latestDraftTransaction" class="stack-list">
            <div class="stack-row">
              <p class="stack-title">{{ latestDraftTransaction.course?.title }}</p>
              <p class="stack-meta">Ref: {{ latestDraftTransaction.reference }}</p>
              <p class="stack-meta">Metode: {{ paymentMethodLabel(latestDraftTransaction.payment_method) }}</p>
              <p class="stack-meta">Harga awal: {{ latestDraftTransaction.original_price_label }}</p>
              <p class="stack-meta">Diskon: {{ latestDraftTransaction.discount_amount_label }}</p>
              <p class="stack-percent">Total bayar: {{ latestDraftTransaction.final_price_label }}</p>

              <button
                v-if="latestDraftTransaction.status === 'pending'"
                type="button"
                class="btn btn-primary"
                :disabled="payingTransactionId === latestDraftTransaction.id"
                @click="payNow(latestDraftTransaction)"
              >
                {{ payingTransactionId === latestDraftTransaction.id ? 'Membayar...' : 'Bayar Sekarang' }}
              </button>
              <span v-else class="tag tag-success">Sudah lunas</span>
            </div>
          </div>
          <p v-else class="empty-state">
            Belum ada transaksi terbaru. Buat tagihan dulu dari panel kiri.
          </p>
        </article>
      </div>

      <article class="panel-card section-spacer">
        <div class="split-row">
          <h2>Riwayat Transaksi</h2>
          <button type="button" class="btn btn-secondary btn-small" @click="refreshTransactions()">Refresh</button>
        </div>

        <p v-if="transactionsPending" class="status-meta">Memuat transaksi...</p>
        <p v-else-if="transactionsError" class="status-meta status-error">Gagal memuat riwayat transaksi.</p>
        <p v-else-if="!transactionsData.length" class="empty-state">Belum ada transaksi pembayaran.</p>

        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Ref</th>
                <th>Kelas</th>
                <th>Voucher</th>
                <th>Metode</th>
                <th>Total</th>
                <th>Status</th>
                <th>Dibayar</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in transactionsData" :key="transaction.id">
                <td>{{ transaction.reference }}</td>
                <td>{{ transaction.course?.title || '-' }}</td>
                <td>{{ transaction.voucher?.code || '-' }}</td>
                <td>{{ paymentMethodLabel(transaction.payment_method) }}</td>
                <td>{{ transaction.final_price_label }}</td>
                <td>
                  <span class="tag" :class="transaction.status === 'paid' ? 'tag-success' : 'tag-warning'">
                    {{ statusLabel(transaction.status) }}
                  </span>
                </td>
                <td>{{ formatDateTime(transaction.paid_at) }}</td>
                <td>
                  <button
                    v-if="transaction.status === 'pending'"
                    type="button"
                    class="btn btn-primary btn-small"
                    :disabled="payingTransactionId === transaction.id"
                    @click="payNow(transaction)"
                  >
                    Bayar
                  </button>
                  <span v-else>-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </div>
  </section>
</template>
