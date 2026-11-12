'use client'
// app/dist/dashboard/page.tsx

import useSWR from 'swr'
import { api } from '@/lib/api'
import DistLayout from '@/components/dist/DistLayout'
import { Skeleton, formatCOP } from '@/components/ui'
import {
  AreaChart, Area, XAxis, YAxis,
  Tooltip, ResponsiveContainer, CartesianGrid,
  BarChart, Bar,
} from 'recharts'
import {
  GraduationCap, Users, Award,
  TrendingUp, AlertCircle, BookOpen,
} from 'lucide-react'

const fetcher = (url: string) => api.get(url)

function StatCard({
  label, value, sub, icon: Icon, color = 'slate', alert = false
}: {
  label: string
  value: string | number
  sub?: string
  icon: any
  color?: 'green' | 'blue' | 'amber' | 'slate' | 'red'
  alert?: boolean
}) {
  const bg: Record<string, string> = {
    green: 'bg-emerald-50 border-emerald-100',
    blue:  'bg-blue-50 border-blue-100',
    amber: 'bg-amber-50 border-amber-100',
    slate: 'bg-slate-50 border-slate-100',
    red:   'bg-red-50 border-red-100',
  }
  const ic: Record<string, string> = {
    green: 'text-emerald-600',
    blue:  'text-blue-600',
    amber: 'text-amber-600',
    slate: 'text-slate-500',
    red:   'text-red-600',
  }
  const val: Record<string, string> = {
    green: 'text-emerald-700',
    blue:  'text-blue-700',
    amber: 'text-amber-700',
    slate: 'text-slate-700',
    red:   'text-red-700',
  }
  return (
    <div className={`rounded-xl border p-4 ${bg[color]}`}>
      <div className="flex items-start justify-between mb-3">
        <p className="text-xs font-medium text-slate-500">{label}</p>
        <Icon size={16} className={ic[color]} />
      </div>
      <p className={`text-2xl font-bold ${val[color]}`}>{value}</p>
      {sub && <p className="text-xs text-slate-400 mt-1">{sub}</p>}
      {alert && (
        <div className="flex items-center gap-1 mt-2">
          <AlertCircle size={11} className="text-amber-500" />
          <span className="text-xs text-amber-600">Requiere atención</span>
        </div>
      )}
    </div>
  )
}

export default function DistDashboardPage() {
  const { data, isLoading } = useSWR('/dist/dashboard', fetcher, {
    refreshInterval: 120_000,
  })

  const d = data as any
  const r = d?.resumen

  return (
    <DistLayout>
      <div className="p-5 space-y-6">

        {/* Bienvenida */}
        <div>
          <h1 className="text-xl font-bold text-slate-800">Buen día 👋</h1>
          <p className="text-slate-500 text-sm mt-0.5">Aquí está el resumen de tu operación</p>
        </div>

        {/* Métricas principales */}
        {isLoading ? (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="bg-white rounded-xl border p-4">
                <Skeleton className="h-4 w-24 mb-3" />
                <Skeleton className="h-7 w-12" />
              </div>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
            <StatCard label="Matrículas este mes"   value={r?.matriculas_mes ?? 0}      icon={TrendingUp}    color="green" />
            <StatCard label="Esta semana"            value={r?.matriculas_semana ?? 0}   icon={TrendingUp}    color="blue" />
            <StatCard label="Estudiantes activos"   value={r?.estudiantes_activos ?? 0}  icon={Users}         color="slate" />
            <StatCard label="Certificados este mes" value={r?.certificados_mes ?? 0}     icon={Award}         color="amber" />
            <StatCard label="Total histórico"       value={r?.total_matriculas ?? 0}     icon={BookOpen}      color="slate" />
            <StatCard
              label="Pendientes SIET"
              value={r?.pendientes_siet ?? 0}
              icon={AlertCircle}
              color={r?.pendientes_siet > 0 ? 'amber' : 'slate'}
              alert={r?.pendientes_siet > 0}
              sub="Completaron curso 1, sin llenar formulario"
            />
          </div>
        )}

        {/* Gráficos */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">

          {/* Matrículas por día */}
          <div className="bg-white rounded-xl border border-slate-100 p-4">
            <h3 className="font-semibold text-slate-700 text-sm mb-4">
              Matrículas — últimas 4 semanas
            </h3>
            {d?.matriculas_por_dia?.length > 0 ? (
              <ResponsiveContainer width="100%" height={160}>
                <AreaChart data={d.matriculas_por_dia}>
                  <defs>
                    <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%"  stopColor="#10b981" stopOpacity={0.15} />
                      <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                  <XAxis dataKey="fecha" tick={{ fontSize: 10 }} tickFormatter={v => v.slice(5)} />
                  <YAxis tick={{ fontSize: 10 }} allowDecimals={false} />
                  <Tooltip formatter={(v: any) => [v, 'Matrículas']} />
                  <Area type="monotone" dataKey="total" stroke="#10b981" fill="url(#g1)" strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-40 flex items-center justify-center text-slate-400 text-sm">
                Sin matrículas en las últimas 4 semanas
              </div>
            )}
          </div>

          {/* Top cursos */}
          <div className="bg-white rounded-xl border border-slate-100 p-4">
            <h3 className="font-semibold text-slate-700 text-sm mb-4">
              Cursos más vendidos este mes
            </h3>
            {d?.top_cursos?.length > 0 ? (
              <ResponsiveContainer width="100%" height={160}>
                <BarChart data={d.top_cursos} layout="vertical">
                  <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" horizontal={false} />
                  <XAxis type="number" tick={{ fontSize: 10 }} allowDecimals={false} />
                  <YAxis type="category" dataKey="curso" tick={{ fontSize: 10 }} width={120}
                    tickFormatter={v => v.length > 18 ? v.slice(0, 18) + '…' : v} />
                  <Tooltip />
                  <Bar dataKey="total" fill="#3b82f6" radius={[0, 4, 4, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-40 flex items-center justify-center text-slate-400 text-sm">
                Sin ventas este mes
              </div>
            )}
          </div>
        </div>

        {/* Top vendedores */}
        {d?.top_vendedores?.length > 0 && (
          <div className="bg-white rounded-xl border border-slate-100 p-4">
            <h3 className="font-semibold text-slate-700 text-sm mb-4">
              Rendimiento de vendedores este mes
            </h3>
            <div className="space-y-2">
              {d.top_vendedores.map((v: any, i: number) => (
                <div key={i} className="flex items-center gap-3">
                  <div className="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-500">
                    {i + 1}
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium text-slate-700">{v.vendedor}</span>
                      <span className="text-sm font-bold text-slate-800">{v.total}</span>
                    </div>
                    <div className="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                      <div className="h-full bg-emerald-400 rounded-full"
                        style={{ width: `${(v.total / d.top_vendedores[0].total) * 100}%` }} />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

      </div>
    </DistLayout>
  )
}
