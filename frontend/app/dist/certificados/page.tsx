'use client'
// app/dist/certificados/page.tsx

import useSWR from 'swr'
import { api } from '@/lib/api'
import DistLayout from '@/components/dist/DistLayout'
import { Skeleton, Empty } from '@/components/ui'
import { ExternalLink, Copy, Check } from 'lucide-react'
import { useState } from 'react'

const fetcher = (url: string) => api.get(url)

export default function DistCertificadosPage() {
  const { data, isLoading } = useSWR('/dist/certificados', fetcher)
  const items = (data as any)?.data ?? []
  const total = (data as any)?.total ?? 0
  const [copiado, setCopiado] = useState<string | null>(null)

  function copiarUrl(url: string, id: string) {
    navigator.clipboard.writeText(url)
    setCopiado(id)
    setTimeout(() => setCopiado(null), 2000)
  }

  return (
    <DistLayout>
      <div className="p-5 space-y-5">

        <div>
          <h1 className="text-xl font-bold text-slate-800">Certificados</h1>
          <p className="text-slate-500 text-sm mt-0.5">
            {total} certificados emitidos a tus estudiantes
          </p>
        </div>

        <div className="bg-white rounded-xl border border-slate-100 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50">
                <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Estudiante</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Curso</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">IETDH</th>
                <th className="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Acta / Folio</th>
                <th className="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Fecha</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                [...Array(5)].map((_, i) => (
                  <tr key={i} className="border-b border-slate-50">
                    {[...Array(6)].map((_, j) => (
                      <td key={j} className="px-4 py-3"><Skeleton className="h-4 w-20" /></td>
                    ))}
                  </tr>
                ))
              ) : items.length === 0 ? (
                <tr><td colSpan={6}><Empty mensaje="Aún no hay certificados emitidos" /></td></tr>
              ) : (
                items.map((c: any) => (
                  <tr key={c.id} className="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-800 text-sm">{c.estudiante}</p>
                      <p className="text-xs text-slate-400 font-mono">{c.documento}</p>
                    </td>
                    <td className="px-4 py-3 text-sm text-slate-600">{c.curso}</td>
                    <td className="px-4 py-3 text-xs text-slate-500">{c.ietdh}</td>
                    <td className="px-4 py-3 text-center">
                      <span className="font-mono font-bold text-slate-700 text-sm">{c.referencia}</span>
                    </td>
                    <td className="px-4 py-3 text-center text-xs text-slate-400">{c.fecha}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2 justify-end">
                        <button
                          onClick={() => copiarUrl(c.url_verificar, c.id)}
                          title="Copiar enlace de verificación"
                          className="text-slate-300 hover:text-blue-500 transition-colors">
                          {copiado === c.id
                            ? <Check size={14} className="text-emerald-500" />
                            : <Copy size={14} />
                          }
                        </button>
                        <a href={c.url_verificar} target="_blank" rel="noopener noreferrer"
                          title="Verificar certificado"
                          className="text-slate-300 hover:text-blue-500 transition-colors">
                          <ExternalLink size={14} />
                        </a>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

      </div>
    </DistLayout>
  )
}
