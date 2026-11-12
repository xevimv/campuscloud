'use client'
// app/cursos/sincronizar/page.tsx

import { useState } from 'react'
import useSWR from 'swr'
import { api, endpoints } from '@/lib/api'
import { Badge, Empty, Skeleton, formatCOP } from '@/components/ui'
import {
  RefreshCw, Download, CheckCircle,
  AlertCircle, ChevronDown, ChevronUp
} from 'lucide-react'

const fetcher = (url: string) => api.get(url)

interface CursoMoodle {
  moodle_course_id: number
  nombre: string
  nombre_corto: string
  categoria: string | null
  visible: boolean
  ya_importado: boolean
  curso_id_panel: string | null
}

interface ConfigCurso {
  moodle_course_id: number
  nombre: string
  tipo_programa: 'TECNICOS' | 'BACHILLERATO'
  orden_en_carrera: number
  es_ultimo: boolean
  fee_nomaddi: number
  precio_minimo_publico: number
}

export default function SincronizarCursosPage() {
  const { data, isLoading, mutate } = useSWR(
    '/sincronizador/cursos', fetcher
  )

  const [seleccionados, setSeleccionados] = useState<Record<number, boolean>>({})
  const [configs, setConfigs]             = useState<Record<number, Partial<ConfigCurso>>>({})
  const [expandido, setExpandido]         = useState<number | null>(null)
  const [importando, setImportando]       = useState(false)
  const [resultado, setResultado]         = useState<any>(null)

  const cursos: CursoMoodle[] = (data as any)?.cursos ?? []
  const pendientes = cursos.filter(c => !c.ya_importado)
  const importados = cursos.filter(c => c.ya_importado)

  function toggleSeleccion(id: number) {
    setSeleccionados(prev => ({ ...prev, [id]: !prev[id] }))
    // Inicializar config con valores por defecto
    if (!configs[id]) {
      const curso = cursos.find(c => c.moodle_course_id === id)
      setConfigs(prev => ({
        ...prev,
        [id]: {
          moodle_course_id:      id,
          nombre:                curso?.nombre ?? '',
          tipo_programa:         'TECNICOS',
          orden_en_carrera:      1,
          es_ultimo:             false,
          fee_nomaddi:           30000,
          precio_minimo_publico: 80000,
        }
      }))
    }
  }

  function actualizarConfig(id: number, campo: string, valor: any) {
    setConfigs(prev => ({
      ...prev,
      [id]: { ...prev[id], [campo]: valor }
    }))
  }

  async function importarSeleccionados() {
    const cursosAImportar = Object.entries(seleccionados)
      .filter(([, sel]) => sel)
      .map(([id]) => configs[parseInt(id)])
      .filter(Boolean)

    if (cursosAImportar.length === 0) return

    setImportando(true)
    setResultado(null)

    try {
      const res = await api.post('/sincronizador/cursos/importar', {
        cursos: cursosAImportar
      })
      setResultado(res)
      setSeleccionados({})
      mutate()
    } catch (e: any) {
      setResultado({ error: e.message })
    } finally {
      setImportando(false)
    }
  }

  const totalSeleccionados = Object.values(seleccionados).filter(Boolean).length

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Sincronizar cursos desde Moodle</h1>
          <p className="text-slate-500 text-sm mt-0.5">
            Importa los cursos que ya existen en tu campus virtual al panel central.
          </p>
        </div>
        <button onClick={() => mutate()}
          className="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
          <RefreshCw size={14} /> Recargar
        </button>
      </div>

      {/* Resultado de la importación */}
      {resultado && (
        <div className={`rounded-xl border p-4 ${resultado.error ? 'bg-red-50 border-red-100' : 'bg-emerald-50 border-emerald-100'}`}>
          {resultado.error ? (
            <p className="text-red-700 text-sm">{resultado.error}</p>
          ) : (
            <div className="text-sm text-emerald-700 space-y-1">
              <p className="font-medium">{resultado.mensaje}</p>
              {resultado.omitidos > 0 && <p>{resultado.omitidos} omitidos (ya existían).</p>}
              {resultado.errores?.length > 0 && (
                <p className="text-red-600">{resultado.errores.length} con error.</p>
              )}
            </div>
          )}
        </div>
      )}

      {/* Resumen */}
      {!isLoading && (
        <div className="grid grid-cols-3 gap-4">
          <div className="bg-white rounded-xl border border-slate-100 p-4 text-center">
            <p className="text-2xl font-bold text-slate-800">{cursos.length}</p>
            <p className="text-sm text-slate-500 mt-1">Total en Moodle</p>
          </div>
          <div className="bg-emerald-50 rounded-xl border border-emerald-100 p-4 text-center">
            <p className="text-2xl font-bold text-emerald-700">{importados.length}</p>
            <p className="text-sm text-emerald-600 mt-1">Ya importados</p>
          </div>
          <div className="bg-amber-50 rounded-xl border border-amber-100 p-4 text-center">
            <p className="text-2xl font-bold text-amber-700">{pendientes.length}</p>
            <p className="text-sm text-amber-600 mt-1">Pendientes de importar</p>
          </div>
        </div>
      )}

      {/* Lista de cursos pendientes */}
      {pendientes.length > 0 && (
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold text-slate-700">Cursos pendientes de importar</h2>
            {totalSeleccionados > 0 && (
              <button onClick={importarSeleccionados} disabled={importando}
                className="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-60 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <Download size={14} />
                {importando ? 'Importando...' : `Importar ${totalSeleccionados} curso(s)`}
              </button>
            )}
          </div>

          {isLoading ? (
            [...Array(4)].map((_, i) => (
              <div key={i} className="bg-white rounded-xl border border-slate-100 p-4">
                <Skeleton className="h-5 w-48 mb-2" />
                <Skeleton className="h-4 w-32" />
              </div>
            ))
          ) : (
            pendientes.map(curso => (
              <div key={curso.moodle_course_id}
                className={`bg-white rounded-xl border transition-colors ${
                  seleccionados[curso.moodle_course_id]
                    ? 'border-emerald-200 ring-1 ring-emerald-200'
                    : 'border-slate-100'
                }`}>

                {/* Cabecera del curso */}
                <div className="flex items-center gap-4 p-4">
                  <input type="checkbox"
                    checked={!!seleccionados[curso.moodle_course_id]}
                    onChange={() => toggleSeleccion(curso.moodle_course_id)}
                    className="w-4 h-4 accent-emerald-600 cursor-pointer flex-shrink-0"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-slate-800 truncate">{curso.nombre}</p>
                    <div className="flex items-center gap-2 mt-1">
                      <span className="text-xs text-slate-400 font-mono">ID: {curso.moodle_course_id}</span>
                      {curso.categoria && (
                        <span className="text-xs text-slate-400">· {curso.categoria}</span>
                      )}
                      {!curso.visible && <Badge variant="amber">Oculto en Moodle</Badge>}
                    </div>
                  </div>

                  {seleccionados[curso.moodle_course_id] && (
                    <button onClick={() => setExpandido(
                      expandido === curso.moodle_course_id ? null : curso.moodle_course_id
                    )}
                      className="text-slate-400 hover:text-slate-600 transition-colors flex items-center gap-1 text-xs">
                      Configurar
                      {expandido === curso.moodle_course_id
                        ? <ChevronUp size={14} />
                        : <ChevronDown size={14} />
                      }
                    </button>
                  )}
                </div>

                {/* Panel de configuración expandible */}
                {seleccionados[curso.moodle_course_id] &&
                 expandido === curso.moodle_course_id && (
                  <div className="border-t border-slate-50 p-4 bg-slate-50 rounded-b-xl">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">

                      <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Tipo de programa</label>
                        <select
                          value={configs[curso.moodle_course_id]?.tipo_programa ?? 'TECNICOS'}
                          onChange={e => actualizarConfig(curso.moodle_course_id, 'tipo_programa', e.target.value)}
                          className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-slate-400">
                          <option value="TECNICOS">Técnico Laboral</option>
                          <option value="BACHILLERATO">Bachillerato</option>
                        </select>
                      </div>

                      <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Orden en la carrera</label>
                        <input type="number" min="1" max="99"
                          value={configs[curso.moodle_course_id]?.orden_en_carrera ?? 1}
                          onChange={e => actualizarConfig(curso.moodle_course_id, 'orden_en_carrera', parseInt(e.target.value))}
                          className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-slate-400"
                        />
                      </div>

                      <div className="flex items-end">
                        <label className="flex items-center gap-2 cursor-pointer pb-2">
                          <input type="checkbox"
                            checked={configs[curso.moodle_course_id]?.es_ultimo ?? false}
                            onChange={e => actualizarConfig(curso.moodle_course_id, 'es_ultimo', e.target.checked)}
                            className="w-4 h-4 accent-emerald-600"
                          />
                          <span className="text-sm text-slate-600">Es el último de la carrera</span>
                        </label>
                      </div>

                      <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Fee Nomaddi (Piso 1) $</label>
                        <input type="number" min="0"
                          value={configs[curso.moodle_course_id]?.fee_nomaddi ?? 30000}
                          onChange={e => actualizarConfig(curso.moodle_course_id, 'fee_nomaddi', parseFloat(e.target.value))}
                          className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-slate-400"
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Precio mínimo público (Piso 2) $</label>
                        <input type="number" min="0"
                          value={configs[curso.moodle_course_id]?.precio_minimo_publico ?? 80000}
                          onChange={e => actualizarConfig(curso.moodle_course_id, 'precio_minimo_publico', parseFloat(e.target.value))}
                          className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-slate-400"
                        />
                      </div>

                    </div>
                  </div>
                )}
              </div>
            ))
          )}
        </div>
      )}

      {/* Lista de cursos ya importados */}
      {importados.length > 0 && (
        <div className="space-y-2">
          <h2 className="font-semibold text-slate-700">Ya importados al panel</h2>
          <div className="bg-white rounded-xl border border-slate-100 overflow-hidden">
            {importados.map((curso, i) => (
              <div key={curso.moodle_course_id}
                className={`flex items-center justify-between px-5 py-3 ${
                  i < importados.length - 1 ? 'border-b border-slate-50' : ''
                }`}>
                <div>
                  <p className="text-sm font-medium text-slate-700">{curso.nombre}</p>
                  <p className="text-xs text-slate-400 font-mono">ID Moodle: {curso.moodle_course_id}</p>
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant="green">Importado</Badge>
                  <CheckCircle size={14} className="text-emerald-500" />
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {!isLoading && cursos.length === 0 && (
        <Empty mensaje="No se encontraron cursos en Moodle. Verifica la configuración del webservice." />
      )}
    </div>
  )
}
