'use client'
// app/certificados/page.tsx
import useSWR from 'swr'
import { api, endpoints } from '@/lib/api'
import { estadoBadge, formatFecha, Badge, Empty, Skeleton } from '@/components/ui'
import { Download, XCircle } from 'lucide-react'

const fetcher = (url: string) => api.get(url)

export default function CertificadosPage() {
  const { data, isLoading, mutate } = useSWR(endpoints.certificados(), fetcher)
  const items = (data as any)?.data ?? []

  async function anular(id: string) {
    const motivo = prompt('Motivo de anulación:')
    if (!motivo) return
    await api.post(`/certificados/${id}/anular`, { motivo })
    mutate()
  }

  function descargarPdf(id: string) {
    const token = localStorage.getItem('nomaddi_token')
    const base  = process.env.NEXT_PUBLIC_API_URL ?? ''
    window.open(`${base}${endpoints.certificadoPdf(id)}?token=${token}`, '_blank')
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-800">Certificados</h1>
        <p className="text-slate-500 text-sm mt-0.5">Registro de certificados emitidos</p>
      </div>

      <div className="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-slate-100 bg-slate-50">
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Estudiante</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Programa</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">IETDH</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Acta / Folio</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Fecha</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Estado</th>
              <th className="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              [...Array(6)].map((_, i) => (
                <tr key={i} className="border-b border-slate-50">
                  {[...Array(7)].map((_, j) => <td key={j} className="px-5 py-4"><Skeleton className="h-4 w-24" /></td>)}
                </tr>
              ))
            ) : items.length === 0 ? (
              <tr><td colSpan={7}><Empty mensaje="Sin certificados emitidos" /></td></tr>
            ) : (
              items.map((c: any) => (
                <tr key={c.id} className="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                  <td className="px-5 py-4">
                    <p className="font-medium text-slate-800">
                      {c.matricula?.estudiante?.nombres} {c.matricula?.estudiante?.apellidos}
                    </p>
                    <p className="text-xs text-slate-400 font-mono">{c.matricula?.estudiante?.numero_documento}</p>
                  </td>
                  <td className="px-5 py-4 text-slate-600 text-xs">{c.matricula?.curso?.nombre}</td>
                  <td className="px-5 py-4 text-slate-600 text-xs">{c.ietdh?.nombre}</td>
                  <td className="px-5 py-4">
                    <span className="font-mono font-bold text-slate-700">
                      {c.acta?.codigo_acta}-{c.folio}
                    </span>
                  </td>
                  <td className="px-5 py-4 text-slate-500 text-xs">{formatFecha(c.fecha_emision)}</td>
                  <td className="px-5 py-4">
                    {c.anulado
                      ? <Badge variant="red">Anulado</Badge>
                      : <Badge variant="green">Válido</Badge>
                    }
                  </td>
                  <td className="px-5 py-4">
                    <div className="flex gap-2">
                      <button onClick={() => descargarPdf(c.id)} title="Descargar PDF"
                        className="text-slate-400 hover:text-blue-600 transition-colors">
                        <Download size={15} />
                      </button>
                      {!c.anulado && (
                        <button onClick={() => anular(c.id)} title="Anular certificado"
                          className="text-slate-400 hover:text-red-600 transition-colors">
                          <XCircle size={15} />
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
