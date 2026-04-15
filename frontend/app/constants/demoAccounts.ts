export type DemoAccount = {
  label: 'Admin' | 'Mentor' | 'Student'
  role: 'admin' | 'mentor' | 'student'
  email: string
  password: string
  defaultPath: '/admin' | '/mentor' | '/student'
}

export const DEMO_PASSWORD = 'password123'

export const DEMO_ACCOUNTS: DemoAccount[] = [
  {
    label: 'Admin',
    role: 'admin',
    email: 'admin@elearning.local',
    password: DEMO_PASSWORD,
    defaultPath: '/admin',
  },
  {
    label: 'Mentor',
    role: 'mentor',
    email: 'mentor@elearning.local',
    password: DEMO_PASSWORD,
    defaultPath: '/mentor',
  },
  {
    label: 'Student',
    role: 'student',
    email: 'student@elearning.local',
    password: DEMO_PASSWORD,
    defaultPath: '/student',
  },
]
