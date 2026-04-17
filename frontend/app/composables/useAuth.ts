type AuthUser = {
  id: number
  name: string
  email: string
  role: 'admin' | 'mentor' | 'student'
  status: 'active' | 'inactive'
  avatar_url: string | null
}

type LoginResponse = {
  token: string
  user: AuthUser
}

type RegisterPayload = {
  name: string
  email: string
  password: string
  password_confirmation: string
  role?: 'student' | 'mentor' | 'career'
}

const TOKEN_KEY = 'elearning_token'
const USER_KEY = 'elearning_user'

export const useAuth = () => {
  const token = useState<string | null>('auth_token', () => null)
  const user = useState<AuthUser | null>('auth_user', () => null)
  const initialized = useState<boolean>('auth_initialized', () => false)
  const sessionValidated = useState<boolean>('auth_session_validated', () => false)

  const runtimeConfig = useRuntimeConfig()
  const apiBase = runtimeConfig.public.apiBase

  const isAuthenticated = computed(() => Boolean(token.value && user.value))

  const persistSession = () => {
    if (!process.client) {
      return
    }

    if (token.value) {
      localStorage.setItem(TOKEN_KEY, token.value)
    } else {
      localStorage.removeItem(TOKEN_KEY)
    }

    if (user.value) {
      localStorage.setItem(USER_KEY, JSON.stringify(user.value))
    } else {
      localStorage.removeItem(USER_KEY)
    }
  }

  const initFromStorage = () => {
    if (!process.client || initialized.value) {
      return
    }

    const storedToken = localStorage.getItem(TOKEN_KEY)
    const storedUser = localStorage.getItem(USER_KEY)

    token.value = storedToken

    if (storedUser) {
      try {
        user.value = JSON.parse(storedUser) as AuthUser
      } catch {
        user.value = null
      }
    }

    sessionValidated.value = false
    initialized.value = true
  }

  const clearSession = () => {
    token.value = null
    user.value = null
    sessionValidated.value = false
    persistSession()
  }

  const authHeaders = (): Record<string, string> => {
    const headers: Record<string, string> = {
      Accept: 'application/json',
    }

    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`
    }

    return headers
  }

  const login = async (email: string, password: string) => {
    const response = await $fetch<LoginResponse>('/api/login', {
      method: 'POST',
      baseURL: apiBase,
      headers: {
        Accept: 'application/json',
      },
      body: {
        email,
        password,
      },
    })

    token.value = response.token
    user.value = response.user
    sessionValidated.value = true
    persistSession()

    return response.user
  }

  const register = async (payload: RegisterPayload) => {
    const response = await $fetch<LoginResponse>('/api/register', {
      method: 'POST',
      baseURL: apiBase,
      headers: {
        Accept: 'application/json',
      },
      body: payload,
    })

    token.value = response.token
    user.value = response.user
    sessionValidated.value = true
    persistSession()

    return response.user
  }

  const fetchMe = async () => {
    if (!token.value) {
      return null
    }

    const response = await $fetch<AuthUser>('/api/me', {
      method: 'GET',
      baseURL: apiBase,
      headers: authHeaders(),
    })

    user.value = response
    sessionValidated.value = true
    persistSession()

    return response
  }

  const ensureSession = async () => {
    initFromStorage()

    if (!token.value) {
      return null
    }

    if (sessionValidated.value && user.value) {
      return user.value
    }

    try {
      return await fetchMe()
    } catch {
      clearSession()
      return null
    }
  }

  const logout = async () => {
    initFromStorage()

    try {
      if (token.value) {
        await $fetch('/api/logout', {
          method: 'POST',
          baseURL: apiBase,
          headers: authHeaders(),
        })
      }
    } catch {
      // Ignore logout network errors and clear local session anyway.
    } finally {
      clearSession()
    }
  }

  const defaultPathByRole = (role?: AuthUser['role']) => {
    if (role === 'admin') {
      return '/admin'
    }

    if (role === 'mentor') {
      return '/mentor'
    }

    return '/student'
  }

  return {
    token,
    user,
    isAuthenticated,
    initFromStorage,
    ensureSession,
    authHeaders,
    login,
    register,
    fetchMe,
    logout,
    clearSession,
    defaultPathByRole,
  }
}
