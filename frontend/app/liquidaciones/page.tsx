'use client'
// app/liquidaciones/page.tsx
import useSWR from 'swr'
import { api, endpoints } from '@/lib/api'
import { estadoBadge, formatCOP, formatFecha, Empty, Skeleton } from '@/components/ui'
import { Download, CheckCircle, FileText } from 'lucide-react'

const fetcher = (url: string) => api.get(url)

export default function LiquidacionesPage() {
  const { data, isLoading, mutate } = useSWR(endpoints.liquidaciones(), fetcher)
  const items = (data as any)?.data ?? []

  async function marcarPagada(id: string) {
    if (!confirm('¿Confirmas que esta liquidación fue pagada?')) return
    await api.post(endpoints.liquidacionPagada(id))
    mutate()
  }

  async function marcarFacturada(id: string) {
    await api.post(endpoints.liquidacionFacturada(id))
    mutate()
  }

  function descargarPdf(id: string) {
    const token = localStorage.getItem('nomaddi_token')
    const base  = process.env.NEXT_PUBLIC_API_URL ?? ''
    window.open(`${base}${endpoints.liquidacionPdf(id)}?token=${token}`, '_blank')
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-800">Liquidaciones</h1>
        <p className="text-slate-500 text-sm mt-0.5">Fees mensuales a IETDHs aliadas</p>
      </div>

      <div className="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-slate-100 bg-slate-50">
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">IETDH</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Período</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Técnicos</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Bachillerato</th>
              <th className="text-right px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Total</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Estado</th>
              <th className="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              [...Array(5)].map((_, i) => (
                <tr key={i} className="border-b border-slate-50">
                  {[...Array(7)].map((_, j) => <td key={j} className="px-5 py-4"><Skeleton className="h-4 w-20" /></td>)}
                </tr>
              ))
            ) : items.length === 0 ? (
              <tr><td colSpan={7}><Empty mensaje="Sin liquidaciones" /></td></tr>
            ) : (
              items.map((l: any) => (
                <tr key={l.id} className="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                  <td className="px-5 py-4">
                    <p className="font-medium text-slate-800">{l.ietdh?.nombre}</p>
                    <p className="text-xs text-slate-400">NIT: {l.ietdh?.nit}</p>
                  </td>
                  <td className="px-5 py-4 text-slate-600 font-medium">{l.periodo}</td>
                  <td className="px-5 py-4 text-slate-600">{l.total_tecnicos}</td>
                  <td className="px-5 py-4 text-slate-600">{l.total_bachillerato}</td>
                  <td className="px-5 py-4 text-right font-bold text-slate-800">{formatCOP(l.valor_total)}</td>
                  <td className="px-5 py-4">{estadoBadge(l.estado)}</td>
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-2">
                      <button onClick={() => descargarPdf(l.id)} title="Descargar PDF"
                        className="text-slate-400 hover:text-blue-600 transition-colors">
                        <Download size={15} />
                      </button>
                      {l.estado === 'ENVIADA' && (
                        <button onClick={() => marcarFacturada(l.id)} title="Marcar como facturada"
                          className="text-slate-400 hover:text-amber-600 transition-colors">
                          <FileText size={15} />
                        </button>
                      )}
                      {(l.estado === 'FACTURADA' || l.estado === 'ENVIADA') && (
                        <button onClick={() => marcarPagada(l.id)} title="Marcar como pagada"
                          className="text-slate-400 hover:text-emerald-600 transition-colors">
                          <CheckCircle size={15} />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
