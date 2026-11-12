'use client'
// app/dashboard/page.tsx
import useSWR from 'swr'
import { api, endpoints } from '@/lib/api'
import { MetricCard, Alert, estadoBadge, formatCOP, CardSkeleton, Empty } from '@/components/ui'
import { AreaChart, Area, BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'
import { RefreshCw } from 'lucide-react'

const fetcher = (url: string) => api.get(url)

export default function DashboardPage() {
  const { data, isLoading, mutate } = useSWR(endpoints.dashboard(), fetcher, {
    refreshInterval: 60_000, // refresca cada minuto
  })

  if (isLoading) return (
    <div className="p-6 space-y-6">
      <div className="grid grid-cols-4 gap-4">
        {[...Array(4)].map((_, i) => <CardSkeleton key={i} />)}
      </div>
    </div>
  )

  const d = data as any

  return (
    <div className="p-6 space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Dashboard</h1>
          <p className="text-slate-500 text-sm mt-0.5">Vista en tiempo real del sistema</p>
        </div>
        <button onClick={() => mutate()}
          className="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
          <RefreshCw size={14} /> Actualizar
        </button>
      </div>

      {/* Alertas */}
      {d?.alertas?.length > 0 && (
        <div className="space-y-2">
          {d.alertas.map((a: any, i: number) => (
            <Alert key={i} tipo={a.tipo} mensaje={a.mensaje} url={a.url} />
          ))}
        </div>
      )}

      {/* Métricas de hoy */}
      <div>
        <h2 className="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-3">Hoy</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <MetricCard label="Matrículas hoy"   value={d?.resumen_hoy?.matriculas ?? 0}   color="blue" />
          <MetricCard label="Certificados hoy"  value={d?.resumen_hoy?.certificados ?? 0}  color="green" />
          <MetricCard label="Webhooks OK"       value={d?.resumen_hoy?.webhooks_ok ?? 0}   color="green" />
          <MetricCard label="Webhooks con error" value={d?.resumen_hoy?.webhooks_err ?? 0}
            color={d?.resumen_hoy?.webhooks_err > 0 ? 'red' : 'slate'} />
        </div>
      </div>

      {/* Métricas del mes */}
      <div>
        <h2 className="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-3">Este mes</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <MetricCard label="Matrículas del mes"    value={d?.resumen_mes?.matriculas ?? 0}             color="blue" />
          <MetricCard label="Certificados del mes"  value={d?.resumen_mes?.certificados ?? 0}            color="green" />
          <MetricCard label="Distribuidores activos" value={d?.resumen_mes?.distribuidores_activos ?? 0} color="slate" />
          <MetricCard label="Liquidaciones por cobrar"
            value={formatCOP(d?.resumen_mes?.liquidaciones_pendientes_valor ?? 0)} color="amber" />
        </div>
      </div>

      {/* Gráficos */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {/* Matrículas por día */}
        <div className="bg-white rounded-xl border border-slate-100 p-5">
          <h3 className="font-semibold text-slate-700 mb-4">Matrículas — últimos 30 días</h3>
          {d?.matriculas_por_dia?.length > 0 ? (
            <ResponsiveContainer width="100%" height={180}>
              <AreaChart data={d.matriculas_por_dia}>
                <defs>
                  <linearGradient id="grad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.15}/>
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                <XAxis dataKey="fecha" tick={{ fontSize: 10 }} tickFormatter={v => v.slice(5)} />
                <YAxis tick={{ fontSize: 10 }} allowDecimals={false} />
                <Tooltip formatter={(v: any) => [v, 'Matrículas']} labelFormatter={l => `Fecha: ${l}`} />
                <Area type="monotone" dataKey="total" stroke="#10b981" fill="url(#grad)" strokeWidth={2} />
              </AreaChart>
            </ResponsiveContainer>
          ) : <Empty mensaje="Sin datos de los últimos 30 días" />}
        </div>

        {/* Top distribuidores */}
        <div className="bg-white rounded-xl border border-slate-100 p-5">
          <h3 className="font-semibold text-slate-700 mb-4">Top distribuidores del mes</h3>
          {d?.top_distribuidores?.length > 0 ? (
            <ResponsiveContainer width="100%" height={180}>
              <BarChart data={d.top_distribuidores} layout="vertical">
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" horizontal={false} />
                <XAxis type="number" tick={{ fontSize: 10 }} allowDecimals={false} />
                <YAxis type="category" dataKey="distribuidor" tick={{ fontSize: 10 }} width={110} />
                <Tooltip formatter={(v: any) => [v, 'Matrículas']} />
                <Bar dataKey="total" fill="#3b82f6" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : <Empty mensaje="Sin matrículas este mes" />}
        </div>
      </div>

      {/* Liquidaciones pendientes + Webhooks recientes */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {/* Liquidaciones */}
        <div className="bg-white rounded-xl border border-slate-100 p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-semibold text-slate-700">Liquidaciones pendientes</h3>
            <a href="/liquidaciones" className="text-xs text-blue-600 hover:underline">Ver todas →</a>
          </div>
          {d?.liquidaciones_pendientes?.length > 0 ? (
            <div className="space-y-2">
              {d.liquidaciones_pendientes.slice(0, 6).map((l: any) => (
                <div key={l.id} className="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
                  <div>
                    <p className="text-sm font-medium text-slate-700">{l.ietdh}</p>
                    <p className="text-xs text-slate-400">Período {l.periodo}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-bold text-amber-600">{formatCOP(l.valor_total)}</p>
                    {estadoBadge(l.estado)}
                  </div>
                </div>
              ))}
            </div>
          ) : <Empty mensaje="Sin liquidaciones pendientes 🎉" />}
        </div>

        {/* Webhooks recientes */}
        <div className="bg-white rounded-xl border border-slate-100 p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-semibold text-slate-700">Webhooks recientes</h3>
            <a href="/webhooks" className="text-xs text-blue-600 hover:underline">Ver log completo →</a>
          </div>
          {d?.webhooks_recientes?.length > 0 ? (
            <div className="space-y-2">
              {d.webhooks_recientes.map((w: any, i: number) => (
                <div key={i} className="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
                  <div>
                    <p className="text-xs font-mono text-slate-600">{w.ref?.slice(0, 18)}…</p>
                    {w.motivo && <p className="text-xs text-red-500 mt-0.5">{w.motivo}</p>}
                  </div>
                  <div className="text-right">
                    <div>{estadoBadge(w.estado)}</div>
                    <p className="text-xs text-slate-400 mt-0.5">{w.hora}</p>
                  </div>
                </div>
              ))}
            </div>
          ) : <Empty mensaje="Sin actividad reciente" />}
        </div>
      </div>

    </div>
  )
}
