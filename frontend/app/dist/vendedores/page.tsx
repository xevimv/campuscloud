'use client'
// app/dist/vendedores/page.tsx

import useSWR from 'swr'
import { api } from '@/lib/api'
import DistLayout from '@/components/dist/DistLayout'
import { Badge, Skeleton, Empty } from '@/components/ui'
import { Copy, Check } from 'lucide-react'
import { useState } from 'react'

const fetcher = (url: string) => api.get(url)

export default function DistVendedoresPage() {
  const { data, isLoading } = useSWR('/dist/vendedores', fetcher)
  const vendedores = (data as any) ?? []
  const [copiado, setCopiado] = useState<string | null>(null)

  function copiar(texto: string, id: string) {
    navigator.clipboard.writeText(texto)
    setCopiado(id)
    setTimeout(() => setCopiado(null), 2000)
  }

  const totalMes = vendedores.reduce((s: number, v: any) => s + (v.matriculas_mes || 0), 0)

  return (
    <DistLayout>
      <div className="p-5 space-y-5">

        <div>
          <h1 className="text-xl font-bold text-slate-800">Mis Vendedores</h1>
          <p className="text-slate-500 text-sm mt-0.5">
            {vendedores.length} vendedores · {totalMes} matrículas este mes
          </p>
        </div>

        {isLoading ? (
          <div className="space-y-3">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="bg-white rounded-xl border border-slate-100 p-4">
                <Skeleton className="h-5 w-40 mb-2" />
                <Skeleton className="h-4 w-56" />
              </div>
            ))}
          </div>
        ) : vendedores.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-100 p-8 text-center">
            <p className="text-slate-400 text-sm">Aún no tienes vendedores registrados.</p>
            <p className="text-slate-400 text-xs mt-1">Contacta a Nomaddi para agregar vendedores a tu cuenta.</p>
          </div>
        ) : (
          <div className="space-y-3">
            {vendedores.map((v: any) => (
              <div key={v.id} className={`bg-white rounded-xl border overflow-hidden ${!v.activo ? 'opacity-60' : 'border-slate-100'}`}>
                <div className="p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        <p className="font-semibold text-slate-800">{v.nombre}</p>
                        {v.activo
                          ? <Badge variant="green">Activo</Badge>
                          : <Badge variant="slate">Inactivo</Badge>
                        }
                      </div>
                      <p className="text-xs text-slate-400">{v.email}</p>
                    </div>

                    {/* Métricas del mes */}
                    <div className="text-right flex-shrink-0">
                      <p className="text-2xl font-bold text-slate-800">{v.matriculas_mes}</p>
                      <p className="text-xs text-slate-400">matrículas este mes</p>
                      {v.comision_mes > 0 && (
                        <p className="text-sm font-medium text-emerald-600 mt-1">
                          + ${Number(v.comision_mes).toLocaleString('es-CO')} comisión
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Código referido + enlace */}
                  <div className="mt-3 p-3 bg-slate-50 rounded-lg border border-slate-100">
                    <div className="flex items-center justify-between gap-2">
                      <div>
                        <p className="text-xs text-slate-400 mb-0.5">Código de referido</p>
                        <p className="font-mono font-bold text-slate-700 text-sm">{v.codigo_referido}</p>
                      </div>
                      <button
                        onClick={() => copiar(v.enlace_ejemplo, v.id)}
                        className="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 transition-colors bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                        {copiado === v.id ? <><Check size={12} /> Copiado</> : <><Copy size={12} /> Copiar enlace</>}
                      </button>
                    </div>
                    <p className="text-xs text-slate-400 font-mono mt-2 truncate">{v.enlace_ejemplo}</p>
                  </div>

                  {/* Comisión configurada */}
                  <div className="flex items-center gap-4 mt-3 text-xs text-slate-500">
                    <span>
                      Comisión: <strong className="text-slate-700">
                        {v.comision_tipo === 'PORCENTAJE'
                          ? `${v.comision_valor}%`
                          : `$${Number(v.comision_valor).toLocaleString('es-CO')} fijo`
                        }
                      </strong>
                    </span>
                    <span>
                      Histórico: <strong className="text-slate-700">{v.total_historico} matrículas</strong>
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </DistLayout>
  )
}
