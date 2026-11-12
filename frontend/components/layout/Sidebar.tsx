// components/layout/Sidebar.tsx
'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import {
  LayoutDashboard, Users, Building2, Award,
  FileText, Webhook, LogOut, ChevronRight,
  GraduationCap, AlertTriangle,
} from 'lucide-react'
import clsx from 'clsx'

const nav = [
  { href: '/dashboard',       label: 'Dashboard',       icon: LayoutDashboard },
  { href: '/distribuidores',  label: 'Distribuidores',  icon: Users },
  { href: '/liquidaciones',   label: 'Liquidaciones',   icon: FileText },
  { href: '/certificados',    label: 'Certificados',    icon: Award },
  { href: '/webhooks',        label: 'Webhooks',        icon: Webhook },
]

export default function Sidebar() {
  const path = usePathname()

  function logout() {
    localStorage.removeItem('nomaddi_token')
    window.location.href = '/login'
  }

  return (
    <aside className="w-60 min-h-screen bg-slate-900 flex flex-col">
      {/* Logo */}
      <div className="px-6 py-5 border-b border-slate-700">
        <div className="flex items-center gap-2">
          <GraduationCap className="text-emerald-400" size={22} />
          <span className="text-white font-bold text-base">Nomaddi</span>
        </div>
        <p className="text-slate-400 text-xs mt-0.5">Panel Central</p>
      </div>

      {/* Nav */}
      <nav className="flex-1 px-3 py-4 space-y-0.5">
        {nav.map(({ href, label, icon: Icon }) => (
          <Link key={href} href={href}
            className={clsx(
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors',
              path.startsWith(href)
                ? 'bg-emerald-600 text-white font-medium'
                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
            )}>
            <Icon size={16} />
            {label}
            {path.startsWith(href) && <ChevronRight size={14} className="ml-auto" />}
          </Link>
        ))}
      </nav>

      {/* Logout */}
      <div className="px-3 py-4 border-t border-slate-700">
        <button onClick={logout}
          className="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-slate-800 w-full transition-colors">
          <LogOut size={16} />
          Cerrar sesión
        </button>
      </div>
    </aside>
  )
}
