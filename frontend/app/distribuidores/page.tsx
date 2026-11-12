'use client'
// app/distribuidores/page.tsx
import useSWR from 'swr'
import { api, endpoints } from '@/lib/api'
import { Badge, estadoBadge, Empty, Skeleton } from '@/components/ui'
import { Users, ChevronRight, PlusCircle } from 'lucide-react'
import Link from 'next/link'

const fetcher = (url: string) => api.get(url)

export default function DistribuidoresPage() {
  const { data, isLoading } = useSWR(endpoints.distribuidores(), fetcher)
  const distribuidores = (data as any) ?? []

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Distribuidores</h1>
          <p className="text-slate-500 text-sm mt-0.5">{distribuidores.length} registrados</p>
        </div>
        <button className="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PlusCircle size={15} /> Nuevo distribuidor
        </button>
      </div>

      <div className="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-slate-100 bg-slate-50">
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Nombre</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Dominio</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">IETDHs</th>
              <th className="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Estado</th>
              <th className="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              [...Array(5)].map((_, i) => (
                <tr key={i} className="border-b border-slate-50">
                  {[...Array(5)].map((_, j) => (
                    <td key={j} className="px-5 py-4"><Skeleton className="h-4 w-24" /></td>
                  ))}
                </tr>
              ))
            ) : distribuidores.length === 0 ? (
              <tr><td colSpan={5}><Empty mensaje="Sin distribuidores registrados" /></td></tr>
            ) : (
              distribuidores.map((d: any) => (
                <tr key={d.id} className="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <Users size={14} className="text-blue-600" />
                      </div>
                      <div>
                        <p className="font-medium text-slate-800">{d.nombre}</p>
                        <p className="text-xs text-slate-400">{d.email_contacto}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-5 py-4 text-slate-500 font-mono text-xs">{d.dominio}</td>
                  <td className="px-5 py-4">
                    <div className="flex flex-wrap gap-1">
                      {d.distribuidor_ietdhs?.length > 0
                        ? d.distribuidor_ietdhs.map((di: any, i: number) => (
                          <Badge key={i} variant="blue">{di.tipo_programa}</Badge>
                        ))
                        : <Badge variant="red">Sin IETDH</Badge>
                      }
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    {d.activo
                      ? <Badge variant="green">Activo</Badge>
                      : <Badge variant="slate">Inactivo</Badge>
                    }
                  </td>
                  <td className="px-5 py-4">
                    <Link href={`/distribuidores/${d.id}`}
                      className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 font-medium">
                      Ver <ChevronRight size={12} />
                    </Link>
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
