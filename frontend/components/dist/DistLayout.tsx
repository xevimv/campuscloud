'use client'
// components/dist/DistLayout.tsx
// Layout exclusivo para DIST_ADMIN — más limpio que el panel de Nomaddi

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
import {
  LayoutDashboard, Users, Award,
  UserCheck, LogOut, ChevronRight,
  GraduationCap, Menu, X,
} from 'lucide-react'
import clsx from 'clsx'
import { api } from '@/lib/api'

const nav = [
  { href: '/dist/dashboard',   label: 'Inicio',         icon: LayoutDashboard },
  { href: '/dist/estudiantes', label: 'Mis Estudiantes', icon: Users },
  { href: '/dist/vendedores',  label: 'Mis Vendedores',  icon: UserCheck },
  { href: '/dist/certificados',label: 'Certificados',    icon: Award },
]

interface Me {
  nombre: string
  email: string
  distribuidor?: { nombre: string }
}

export default function DistLayout({ children }: { children: React.ReactNode }) {
  const path   = usePathname()
  const router = useRouter()
  const [me, setMe]           = useState<Me | null>(null)
  const [sidebarOpen, setSidebarOpen] = useState(false)

  useEffect(() => {
    api.get<Me>('/auth/me').then(setMe).catch(() => {
      router.push('/login')
    })
  }, [])

  function logout() {
    localStorage.removeItem('nomaddi_token')
    router.push('/login')
  }

  const sidebarContent = (
    <div className="flex flex-col h-full">
      {/* Logo / Nombre distribuidor */}
      <div className="px-5 py-5 border-b border-slate-100">
        <div className="flex items-center gap-2 mb-1">
          <GraduationCap size={20} className="text-emerald-600" />
          <span className="font-bold text-slate-800 text-sm">
            {me?.distribuidor?.nombre ?? 'Mi Portal'}
          </span>
        </div>
        <p className="text-xs text-slate-400 truncate">{me?.email}</p>
      </div>

      {/* Navegación */}
      <nav className="flex-1 px-3 py-4 space-y-0.5">
        {nav.map(({ href, label, icon: Icon }) => (
          <Link key={href} href={href}
            onClick={() => setSidebarOpen(false)}
            className={clsx(
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors',
              path.startsWith(href)
                ? 'bg-emerald-50 text-emerald-700 font-medium'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800'
            )}>
            <Icon size={16} />
            {label}
            {path.startsWith(href) && <ChevronRight size={13} className="ml-auto text-emerald-500" />}
          </Link>
        ))}
      </nav>

      {/* Cerrar sesión */}
      <div className="px-3 py-4 border-t border-slate-100">
        <button onClick={logout}
          className="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-slate-700 hover:bg-slate-50 w-full transition-colors">
          <LogOut size={15} />
          Cerrar sesión
        </button>
      </div>
    </div>
  )

  return (
    <div className="min-h-screen bg-slate-50 flex">

      {/* Sidebar desktop */}
      <aside className="hidden md:flex w-56 min-h-screen bg-white border-r border-slate-100 flex-col flex-shrink-0">
        {sidebarContent}
      </aside>

      {/* Sidebar móvil */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-50 md:hidden">
          <div className="absolute inset-0 bg-black/30" onClick={() => setSidebarOpen(false)} />
          <aside className="absolute left-0 top-0 h-full w-64 bg-white shadow-xl">
            <button onClick={() => setSidebarOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
              <X size={18} />
            </button>
            {sidebarContent}
          </aside>
        </div>
      )}

      {/* Contenido principal */}
      <main className="flex-1 flex flex-col min-w-0">
        {/* Topbar móvil */}
        <div className="md:hidden flex items-center gap-3 px-4 py-3 bg-white border-b border-slate-100">
          <button onClick={() => setSidebarOpen(true)} className="text-slate-500">
            <Menu size={20} />
          </button>
          <span className="font-semibold text-slate-700 text-sm">
            {me?.distribuidor?.nombre ?? 'Mi Portal'}
          </span>
        </div>

        <div className="flex-1 overflow-auto">
          {children}
        </div>
      </main>
    </div>
  )
}
