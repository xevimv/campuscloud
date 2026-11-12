'use client'
// app/dist/estudiantes/page.tsx

import { useState } from 'react'
import useSWR from 'swr'
import { api } from '@/lib/api'
import DistLayout from '@/components/dist/DistLayout'
import { estadoBadge, Skeleton, Empty } from '@/components/ui'
import { Search, CheckCircle, AlertTriangle } from 'lucide-react'

const fetcher = (url: string) => api.get(url)

export default function DistEstudiantesPage() {
  const [busqueda, setBusqueda] = useState('')
  const [estado, setEstado]     = useState('')
  const [siet, setSiet]         = useState('')
  const [page, setPage]         = useState(1)

  const params = new URLSearchParams()
  if (busqueda) params.set('busqueda', busqueda)
  if (estado)   params.set('estado', estado)
  if (siet)     params.set('siet', siet)
  params.set('page', String(page))

  const { data, isLoading } = useSWR(
    `/dist/estudiantes?${params.toString()}`, fetcher
  )

  const items   = (data as any)?.data ?? []
  const meta    = (data as any)?.meta ?? {}
  const total   = (data as any)?.total ?? 0

  return (
    <DistLayout>
      <div className="p-5 space-y-5">

        <div>
          <h1 className="text-xl font-bold text-slate-800">Mis Estudiantes</h1>
          <p className="text-slate-500 text-sm mt-0.5">{total} estudiantes en total</p>
        </div>

        {/* Filtros */}
        <div className="flex flex-wrap gap-2">
          <div className="relative">
            <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input type="text" placeholder="Buscar por nombre, doc, email..."
              value={busqueda}
              onChange={e => { setBusqueda(e.target.value); setPage(1) }}
              className="pl-8 pr-4 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 w-56 bg-white" />
          </div>

          <select value={estado} onChange={e => { setEstado(e.target.value); setPage(1) }}
            className="text-xs border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-slate-400 text-slate-600">
            <option value="">Todos los estados</option>
            <option value="ACTIVA">Activos</option>
            <option value="COMPLETADA">Completados</option>
            <option value="CANCELADA">Cancelados</option>
          </select>

          <select value={siet} onChange={e => { setSiet(e.target.value); setPage(1) }}
            className="text-xs border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-slate-400 text-slate-600">
            <option value="">Todos (SIET)</option>
            <option value="completo">SIET completado</option>
            <option value="pendiente">SIET pendiente</option>
          </select>
        </div>

        {/* Tabla */}
        <div className="bg-white rounded-xl border border-slate-100 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50">
                <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Estudiante</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Curso</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Vendedor</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Matrícula</th>
                <th className="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Estado</th>
                <th className="text-center px-4 py-3 text-xs font-semibold text-slate-500 uppercase">SIET</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                [...Array(8)].map((_, i) => (
                  <tr key={i} className="border-b border-slate-50">
                    {[...Array(6)].map((_, j) => (
                      <td key={j} className="px-4 py-3"><Skeleton className="h-4 w-24" /></td>
                    ))}
                  </tr>
                ))
              ) : items.length === 0 ? (
                <tr><td colSpan={6}><Empty mensaje="Sin estudiantes con estos filtros" /></td></tr>
              ) : (
                items.map((m: any) => (
                  <tr key={m.matricula_id} className="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-800 text-sm">
                        {m.estudiante.nombres} {m.estudiante.apellidos}
                      </p>
                      <p className="text-xs text-slate-400 font-mono">{m.estudiante.numero_documento}</p>
                      <p className="text-xs text-slate-400">{m.estudiante.email}</p>
                    </td>
                    <td className="px-4 py-3">
                      <p className="text-sm text-slate-700">{m.curso}</p>
                      <span className={`text-xs px-1.5 py-0.5 rounded font-medium ${
                        m.tipo_programa === 'TECNICOS'
                          ? 'bg-blue-50 text-blue-600'
                          : 'bg-orange-50 text-orange-600'
                      }`}>
                        {m.tipo_programa === 'TECNICOS' ? 'Técnico' : 'Bachillerato'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm text-slate-500">{m.vendedor}</td>
                    <td className="px-4 py-3 text-xs text-slate-400">{m.fecha_matricula}</td>
                    <td className="px-4 py-3 text-center">{estadoBadge(m.estado)}</td>
                    <td className="px-4 py-3 text-center">
                      {m.estudiante.siet_completo ? (
                        <CheckCircle size={15} className="text-emerald-500 mx-auto" title="SIET completado" />
                      ) : (
                        <AlertTriangle size={15} className="text-amber-400 mx-auto" title="SIET pendiente" />
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>

          {/* Paginación */}
          {meta?.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t border-slate-50">
              <p className="text-xs text-slate-400">
                Página {meta.current_page} de {meta.last_page}
              </p>
              <div className="flex gap-2">
                <button disabled={page === 1} onClick={() => setPage(p => p - 1)}
                  className="text-xs px-3 py-1.5 border border-slate-200 rounded-lg disabled:opacity-40 hover:bg-slate-50 transition-colors">
                  ← Anterior
                </button>
                <button disabled={page === meta.last_page} onClick={() => setPage(p => p + 1)}
                  className="text-xs px-3 py-1.5 border border-slate-200 rounded-lg disabled:opacity-40 hover:bg-slate-50 transition-colors">
                  Siguiente →
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </DistLayout>
  )
}
