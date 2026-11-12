'use client'
// app/webhooks/page.tsx
import useSWR from 'swr'
import { api, endpoints } from '@/lib/api'
import { estadoBadge, Empty, Skeleton } from '@/components/ui'
import { RefreshCw } from 'lucide-react'

const fetcher = (url: string) => api.get(url)

export default function WebhooksPage() {
  const { data, isLoading, mutate } = useSWR(
    `${endpoints.logsWebhook()}?per_page=50`, fetcher,
    { refreshInterval: 30_000 }
  )
  const items = (data as any)?.data ?? []

  const estadoColor: Record<string, string> = {
    MATRICULADO: 'bg-emerald-50',
    RECHAZADO:   'bg-red-50',
    ERROR:       'bg-red-50',
    RECIBIDO:    'bg-slate-50',
    VALIDADO:    'bg-blue-50',
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Log de Webhooks</h1>
          <p className="text-slate-500 text-sm mt-0.5">Historial de notificaciones de pago de ePayco</p>
        </div>
        <button onClick={() => mutate()}
          className="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
          <RefreshCw size={14} /> Actualizar
        </button>
      </div>

      <div className="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-slate-100 bg-slate-50">
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Referencia ePayco</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Estudiante</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Monto</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Estado</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Motivo</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Fecha/Hora</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              [...Array(8)].map((_, i) => (
                <tr key={i} className="border-b border-slate-50">
                  {[...Array(6)].map((_, j) => <td key={j} className="px-5 py-4"><Skeleton className="h-4 w-24" /></td>)}
                </tr>
              ))
            ) : items.length === 0 ? (
              <tr><td colSpan={6}><Empty mensaje="Sin webhooks registrados" /></td></tr>
            ) : (
              items.map((w: any) => (
                <tr key={w.id}
                  className={`border-b border-slate-50 transition-colors ${estadoColor[w.estado] ?? ''}`}>
                  <td className="px-5 py-3">
                    <span className="font-mono text-xs text-slate-700">{w.epayco_ref}</span>
                  </td>
                  <td className="px-5 py-3 text-slate-600 text-xs">{w.email_estudiante ?? '—'}</td>
                  <td className="px-5 py-3 text-slate-700 font-medium text-xs">
                    {w.monto ? `$${Number(w.monto).toLocaleString('es-CO')}` : '—'}
                  </td>
                  <td className="px-5 py-3">{estadoBadge(w.estado)}</td>
                  <td className="px-5 py-3 text-xs text-red-600 max-w-[200px] truncate" title={w.motivo_rechazo}>
                    {w.motivo_rechazo ?? '—'}
                  </td>
                  <td className="px-5 py-3 text-xs text-slate-400 whitespace-nowrap">{w.created_at}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
