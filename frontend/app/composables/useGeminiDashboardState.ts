import { ref, computed, watch, onMounted } from 'vue'

export const useGeminiDashboardState = () => {
            const runtimeConfig = useRuntimeConfig()
            const apiBase = runtimeConfig.public.apiBase

            // Global State Management
            const role = ref('admin') // Default testing role
            const isSidebarOpen = ref(false)
            const currentMenu = ref('dashboard')
            
            // Modal & Toast State
            const isModalOpen = ref(false)
            const modalType = ref('')
            const toast = ref({ show: false, message: '', type: 'success' })

            // Menus
            const adminMenus = [
                { id: 'dashboard', label: 'Ringkasan Analytics', icon: 'ph-duotone ph-squares-four' },
                { id: 'users', label: 'Data Pengguna', icon: 'ph-duotone ph-users', badge: '12' },
                { id: 'transactions', label: 'Verifikasi Transaksi', icon: 'ph-duotone ph-receipt' },
                { id: 'content', label: 'Moderasi Instruktur', icon: 'ph-duotone ph-shield-check', badge: '2' },
                { id: 'settings', label: 'Pengaturan Global', icon: 'ph-duotone ph-gear' },
            ]
            const instructorMenus = [
                { id: 'dashboard', label: 'Dashboard Mentor', icon: 'ph-duotone ph-squares-four' },
                { id: 'courses', label: 'Kelola Kelas Saya', icon: 'ph-duotone ph-video-camera' },
                { id: 'discussions', label: 'Forum Q&A Kelas', icon: 'ph-duotone ph-chats-circle', badge: '5' },
                { id: 'earnings', label: 'Riwayat Pendapatan', icon: 'ph-duotone ph-wallet' },
                { id: 'profile', label: 'Pengaturan Profil', icon: 'ph-duotone ph-user' },
            ]
            const studentMenus = [
                { id: 'dashboard', label: 'Ruang Belajar', icon: 'ph-duotone ph-book-open' },
                { id: 'catalog', label: 'Katalog Kelas', icon: 'ph-duotone ph-compass' },
                { id: 'certificates', label: 'Sertifikat Kelulusan', icon: 'ph-duotone ph-seal-check' },
                { id: 'transactions', label: 'Riwayat Pembelian', icon: 'ph-duotone ph-receipt' },
                { id: 'settings', label: 'Pengaturan Akun', icon: 'ph-duotone ph-gear' },
            ]

            // Computed
            const activeMenus = computed(() => {
                if(role.value === 'admin') return adminMenus;
                if(role.value === 'instructor') return instructorMenus;
                return studentMenus;
            });

            const currentMenuLabel = computed(() => {
                const menu = activeMenus.value.find(m => m.id === currentMenu.value);
                return menu ? menu.label : 'Dashboard';
            });

            const currentUser = computed(() => {
                if(role.value === 'admin') return { name: 'Admin Pusat EduTech', roleText: 'Super Admin', avatar: 'https://i.pravatar.cc/150?img=11' }
                if(role.value === 'instructor') return { name: 'Budi Santoso', roleText: 'Instruktur Expert', avatar: 'https://i.pravatar.cc/150?img=8' }
                return { name: 'Andi Kusuma', roleText: 'Siswa Premium', avatar: 'https://i.pravatar.cc/150?img=12' }
            });

            // Watcher
            watch(role, () => {
                currentMenu.value = 'dashboard';
            });

            // Modal Methods
            const openModal = (type) => {
                modalType.value = type;
                isModalOpen.value = true;
            }

            const closeModal = () => {
                isModalOpen.value = false;
                setTimeout(() => { modalType.value = ''; }, 300);
            }

            const showToast = (message, type = 'success') => {
                toast.value = { show: true, message, type };
                setTimeout(() => {
                    toast.value.show = false;
                }, 3000);
            }

            const submitModal = (actionName) => {
                closeModal();
                showToast(`${actionName} berhasil diproses!`, 'success');
            }

            // Dummy Data State
            const adminStats = ref([
                { label: 'Total Pemasukan', value: 'Rp 84.5 Jt', icon: 'ph-fill ph-money', colorClass: 'bg-emerald-50 text-emerald-600', trend: 12 },
                { label: 'Siswa Terdaftar', value: '2,450', icon: 'ph-fill ph-users', colorClass: 'bg-blue-50 text-blue-600', trend: 5 },
                { label: 'Instruktur Aktif', value: '48', icon: 'ph-fill ph-chalkboard-teacher', colorClass: 'bg-purple-50 text-purple-600', trend: -2 },
                { label: 'Kelas Tersedia', value: '150', icon: 'ph-fill ph-play-circle', colorClass: 'bg-orange-50 text-orange-600', trend: 8 },
            ]);
            const recentRegistrations = ref([
                { name: 'Diana Fitri', course: 'UI/UX Design System Pro', time: '10 Min lalu', avatar: 'https://i.pravatar.cc/150?img=1' },
                { name: 'Reza Aditya', course: 'Bootcamp Fullstack Reguler', time: '1 Jam lalu', avatar: 'https://i.pravatar.cc/150?img=13' },
                { name: 'Siti Aminah', course: 'Fundamental Golang API', time: '2 Jam lalu', avatar: 'https://i.pravatar.cc/150?img=5' },
                { name: 'Kevin Pratama', course: 'Mastering Nuxt 4 & Vue', time: '4 Jam lalu', avatar: 'https://i.pravatar.cc/150?img=11' },
            ]);
            const instructorCourses = ref([
                { title: 'Mastering PostgreSQL 17 & Redis 7 Caching', img: 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=400&q=80', status: 'Published', students: '800', revenue: 'Rp 6.4 Jt' },
                { title: 'Golang Microservices & gRPC Architecture', img: 'https://images.unsplash.com/photo-1623479322729-28b25c16b011?w=400&q=80', status: 'Published', students: '420', revenue: 'Rp 4.2 Jt' },
            ]);

            const loadDashboardPayload = async () => {
                try {
                    const payload = await $fetch<{
                        adminStats?: Array<{
                            label: string
                            value: string
                            icon: string
                            colorClass: string
                            trend: number
                        }>
                        recentRegistrations?: Array<{
                            name: string
                            course: string
                            time: string
                            avatar: string
                        }>
                        instructorCourses?: Array<{
                            title: string
                            img: string
                            status: string
                            students: string
                            revenue: string
                        }>
                    }>('/api/gemini/dashboard', {
                        baseURL: apiBase,
                    })

                    if (Array.isArray(payload.adminStats) && payload.adminStats.length > 0) {
                        adminStats.value = payload.adminStats
                    }

                    if (Array.isArray(payload.recentRegistrations) && payload.recentRegistrations.length > 0) {
                        recentRegistrations.value = payload.recentRegistrations
                    }

                    if (Array.isArray(payload.instructorCourses) && payload.instructorCourses.length > 0) {
                        instructorCourses.value = payload.instructorCourses
                    }
                } catch (error) {
                    console.warn('Gemini dashboard API fallback ke data lokal.', error)
                }
            }

            onMounted(async () => {
                await loadDashboardPayload()
            })

            return {
                role, currentMenu, isSidebarOpen,
                activeMenus, currentMenuLabel, currentUser,
                adminStats, recentRegistrations, instructorCourses,
                isModalOpen, modalType, toast, openModal, closeModal, showToast, submitModal
            }
}
