'use client'
// app/auditoria/page.tsx

import { useState } from 'react'
import useSWR from 'swr'
import { api } from '@/lib/api'
import { MetricCard, Empty, Skeleton } from '@/components/ui'
import {
  CheckCircle, XCircle, AlertTriangle,
  Play, RefreshCw, Settings, ChevronDown, ChevronUp,
  Minus
} from 'lucide-react'

const fetcher = (url: string) => api.get(url)

interface Semaforo {
  check: string
  ok: boolean | null
  critico: boolean
}

interface CursoAuditoria {
  id: string
  moodle_course_id: number
  nombre: string
  tipo_programa: string
  orden_en_carrera: number
  modulos_esperados: number
  examenes_esperados: number
  ultima_auditoria: {
    resultado_global: 'APROBADO' | 'ADVERTENCIA' | 'REPROBADO'
    semaforos: Semaforo[]
    auditado_en: string
    detalle: Record<string, any>
  } | null
}

// ── Icono de semáforo ─────────────────────────────────────────────
function IconoCheck({ ok, critico }: { ok: boolean | null; critico: boolean }) {
  if (ok === null) return <Minus size={14} className="text-slate-300" />
  if (ok) return <CheckCircle size={14} className="text-emerald-500" />
  if (critico) return <XCircle size={14} className="text-red-500" />
  return <AlertTriangle size={14} className="text-amber-500" />
}

// ── Badge resultado global ────────────────────────────────────────
function ResultadoBadge({ resultado }: { resultado: string }) {
  const estilos: Record<string, string> = {
    APROBADO:    'bg-emerald-100 text-emerald-700 border-emerald-200',
    ADVERTENCIA: 'bg-amber-100 text-amber-700 border-amber-200',
    REPROBADO:   'bg-red-100 text-red-700 border-red-200',
  }
  return (
    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border ${estilos[resultado] ?? 'bg-slate-100 text-slate-500'}`}>
      {resultado === 'APROBADO'    && <CheckCircle size={11} />}
      {resultado === 'ADVERTENCIA' && <AlertTriangle size={11} />}
      {resultado === 'REPROBADO'   && <XCircle size={11} />}
      {resultado}
    </span>
  )
}

export default function AuditoriaPage() {
  const { data, isLoading, mutate } = useSWR('/auditoria', fetcher)
  const [expandido, setExpandido]   = useState<string | null>(null)
  const [auditando, setAuditando]   = useState<string | null>(null)
  const [auditandoTodo, setAuditandoTodo] = useState(false)
  const [filtro, setFiltro]         = useState<'TODOS' | 'APROBADO' | 'ADVERTENCIA' | 'REPROBADO' | 'SIN_AUDITAR'>('TODOS')
  const [editando, setEditando]     = useState<string | null>(null)

  const d = data as any
  const cursos: CursoAuditoria[] = d?.cursos ?? []

  const cursosFiltrados = cursos.filter(c => {
    if (filtro === 'TODOS') return true
    if (filtro === 'SIN_AUDITAR') return !c.ultima_auditoria
    return c.ultima_auditoria?.resultado_global === filtro
  })

  async function auditarCurso(cursoId: string) {
    setAuditando(cursoId)
    try {
      await api.post(`/auditoria/curso/${cursoId}`)
      mutate()
    } catch (e: any) {
      alert('Error al auditar: ' + e.message)
    } finally {
      setAuditando(null)
    }
  }

  async function auditarTodos() {
    if (!confirm(`¿Auditar los ${d?.total_cursos} cursos activos? Esto puede tardar varios minutos.`)) return
    setAuditandoTodo(true)
    try {
      const res = await api.post('/auditoria/todos') as any
      alert(res.mensaje)
      mutate()
    } catch (e: any) {
      alert('Error: ' + e.message)
    } finally {
      setAuditandoTodo(false)
    }
  }

  async function actualizarCriterios(cursoId: string, modulos: number, examenes: number) {
    await api.put(`/auditoria/curso/${cursoId}/criterios`, {
      modulos_esperados: modulos,
      examenes_esperados: examenes,
    })
    setEditando(null)
    mutate()
  }

  return (
    <div className="p-6 space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Auditoría de Cursos</h1>
          <p className="text-slate-500 text-sm mt-0.5">
            Verificación automática de calidad contra Moodle
          </p>
        </div>
        <button onClick={auditarTodos} disabled={auditandoTodo}
          className="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 disabled:opacity-60 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          {auditandoTodo
            ? <><RefreshCw size={14} className="animate-spin" /> Auditando...</>
            : <><Play size={14} /> Auditar todos los cursos</>
          }
        </button>
      </div>

      {/* Métricas */}
      {isLoading ? (
        <div className="grid grid-cols-4 gap-4">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="bg-white rounded-xl border p-5"><Skeleton className="h-8 w-12 mb-2" /><Skeleton className="h-4 w-20" /></div>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <MetricCard label="Total cursos"   value={d?.total_cursos ?? 0}   color="slate" />
          <MetricCard label="Aprobados"      value={d?.aprobados ?? 0}      color="green" />
          <MetricCard label="Con advertencia" value={d?.advertencias ?? 0}  color="amber" />
          <MetricCard label="Reprobados"     value={d?.reprobados ?? 0}     color="red" />
        </div>
      )}

      {/* Filtros */}
      <div className="flex gap-2 flex-wrap">
        {(['TODOS', 'APROBADO', 'ADVERTENCIA', 'REPROBADO', 'SIN_AUDITAR'] as const).map(f => (
          <button key={f} onClick={() => setFiltro(f)}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
              filtro === f
                ? 'bg-slate-800 text-white'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}>
            {f.replace('_', ' ')}
          </button>
        ))}
      </div>

      {/* Lista de cursos */}
      <div className="space-y-2">
        {isLoading ? (
          [...Array(5)].map((_, i) => (
            <div key={i} className="bg-white rounded-xl border border-slate-100 p-4">
              <Skeleton className="h-5 w-56 mb-2" />
              <Skeleton className="h-4 w-36" />
            </div>
          ))
        ) : cursosFiltrados.length === 0 ? (
          <Empty mensaje="Sin cursos en este filtro" />
        ) : (
          cursosFiltrados.map(curso => (
            <div key={curso.id}
              className={`bg-white rounded-xl border transition-all ${
                curso.ultima_auditoria?.resultado_global === 'REPROBADO'   ? 'border-red-100' :
                curso.ultima_auditoria?.resultado_global === 'ADVERTENCIA' ? 'border-amber-100' :
                curso.ultima_auditoria?.resultado_global === 'APROBADO'    ? 'border-emerald-100' :
                'border-slate-100'
              }`}>

              {/* Fila principal */}
              <div className="flex items-center gap-4 p-4">

                {/* Info del curso */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <p className="font-medium text-slate-800">{curso.nombre}</p>
                    <span className="text-xs text-slate-400 font-mono">ID {curso.moodle_course_id}</span>
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                      curso.tipo_programa === 'TECNICOS'
                        ? 'bg-blue-100 text-blue-600'
                        : 'bg-orange-100 text-orange-600'
                    }`}>
                      {curso.tipo_programa === 'TECNICOS' ? 'Técnico' : 'Bachillerato'}
                    </span>
                  </div>
                  <p className="text-xs text-slate-400 mt-0.5">
                    Orden {curso.orden_en_carrera} · Esperados: {curso.modulos_esperados} módulos, {curso.examenes_esperados} exámenes
                  </p>
                </div>

                {/* Semáforos rápidos */}
                {curso.ultima_auditoria && (
                  <div className="flex items-center gap-1.5 flex-shrink-0">
                    {curso.ultima_auditoria.semaforos.map((s, i) => (
                      <div key={i} title={s.check}>
                        <IconoCheck ok={s.ok} critico={s.critico} />
                      </div>
                    ))}
                  </div>
                )}

                {/* Resultado global */}
                <div className="flex-shrink-0">
                  {curso.ultima_auditoria
                    ? <ResultadoBadge resultado={curso.ultima_auditoria.resultado_global} />
                    : <span className="text-xs text-slate-400">Sin auditar</span>
                  }
                </div>

                {/* Acciones */}
                <div className="flex items-center gap-2 flex-shrink-0">
                  <button onClick={() => auditarCurso(curso.id)} disabled={auditando === curso.id}
                    title="Auditar este curso"
                    className="text-slate-400 hover:text-slate-700 transition-colors disabled:opacity-50">
                    {auditando === curso.id
                      ? <RefreshCw size={15} className="animate-spin" />
                      : <RefreshCw size={15} />
                    }
                  </button>
                  <button onClick={() => setEditando(editando === curso.id ? null : curso.id)}
                    title="Configurar criterios"
                    className="text-slate-400 hover:text-slate-700 transition-colors">
                    <Settings size={15} />
                  </button>
                  {curso.ultima_auditoria && (
                    <button onClick={() => setExpandido(expandido === curso.id ? null : curso.id)}
                      className="text-slate-400 hover:text-slate-700 transition-colors">
                      {expandido === curso.id ? <ChevronUp size={15}/> : <ChevronDown size={15}/>}
                    </button>
                  )}
                </div>
              </div>

              {/* Panel de configuración de criterios */}
              {editando === curso.id && (
                <EditarCriterios
                  curso={curso}
                  onGuardar={(m, e) => actualizarCriterios(curso.id, m, e)}
                  onCancelar={() => setEditando(null)}
                />
              )}

              {/* Detalle expandible */}
              {expandido === curso.id && curso.ultima_auditoria && (
                <DetalleAuditoria auditoria={curso.ultima_auditoria} />
              )}

            </div>
          ))
        )}
      </div>
    </div>
  )
}

// ── Componente detalle de auditoría ──────────────────────────────
function DetalleAuditoria({ auditoria }: { auditoria: any }) {
  return (
    <div className="border-t border-slate-50 p-4 bg-slate-50 rounded-b-xl space-y-3">
      <p className="text-xs text-slate-400 mb-3">Última auditoría: {auditoria.auditado_en}</p>

      {auditoria.semaforos.map((s: Semaforo, i: number) => {
        const detalle = auditoria.detalle

        return (
          <div key={i} className={`flex items-start gap-3 p-3 rounded-lg border text-sm ${
            s.ok === null ? 'bg-slate-50 border-slate-100' :
            s.ok          ? 'bg-emerald-50 border-emerald-100' :
            s.critico     ? 'bg-red-50 border-red-100' :
                            'bg-amber-50 border-amber-100'
          }`}>
            <div className="mt-0.5"><IconoCheck ok={s.ok} critico={s.critico} /></div>
            <div className="flex-1">
              <p className={`font-medium ${
                s.ok === null ? 'text-slate-500' :
                s.ok          ? 'text-emerald-700' :
                s.critico     ? 'text-red-700' :
                                'text-amber-700'
              }`}>{s.check}</p>

              {/* Detalle específico por check */}
              {s.check === 'Título correcto' && detalle?.titulo && (
                <div className="text-xs mt-1 space-y-0.5">
                  <p className="text-slate-500">Esperado: <span className="font-mono">{detalle.titulo.esperado}</span></p>
                  <p className="text-slate-500">En Moodle: <span className="font-mono">{detalle.titulo.encontrado}</span></p>
                </div>
              )}
              {s.check === 'Módulos completos' && detalle?.modulos && (
                <p className="text-xs text-slate-500 mt-1">
                  {detalle.modulos.encontrados} de {detalle.modulos.esperados} secciones con contenido
                </p>
              )}
              {s.check === 'Exámenes completos' && detalle?.examenes && (
                <p className="text-xs text-slate-500 mt-1">
                  {detalle.examenes.encontrados} de {detalle.examenes.esperados} cuestionarios
                </p>
              )}
              {s.check === 'Sin enlaces rotos' && detalle?.enlaces && (
                <div className="text-xs mt-1">
                  <p className="text-slate-500">{detalle.enlaces.mensaje}</p>
                  {detalle.enlaces.rotos?.map((r: any, j: number) => (
                    <p key={j} className="text-red-600 font-mono mt-0.5">
                      ✕ {r.nombre}: {r.url} {r.codigo ? `(HTTP ${r.codigo})` : ''}
                    </p>
                  ))}
                </div>
              )}
              {s.check === 'Calificación config.' && detalle?.calificacion && (
                <div className="text-xs mt-1 space-y-0.5">
                  {detalle.calificacion.detalle_items?.map((item: any, j: number) => (
                    <p key={j} className="text-slate-500">
                      · {item.nombre} — Max: {item.max} · Aprobación: {item.pass}
                    </p>
                  ))}
                </div>
              )}
              {s.check === 'Bloque de progreso' && detalle?.bloque_progreso && !s.ok && (
                <p className="text-xs text-amber-600 mt-1">
                  Bloques encontrados: {detalle.bloque_progreso.bloques_encontrados?.join(', ') || 'ninguno'}
                </p>
              )}
            </div>
          </div>
        )
      })}
    </div>
  )
}

// ── Editor de criterios ───────────────────────────────────────────
function EditarCriterios({
  curso, onGuardar, onCancelar
}: {
  curso: CursoAuditoria
  onGuardar: (m: number, e: number) => void
  onCancelar: () => void
}) {
  const [modulos, setModulos]   = useState(curso.modulos_esperados)
  const [examenes, setExamenes] = useState(curso.examenes_esperados)

  return (
    <div className="border-t border-slate-100 p-4 bg-blue-50">
      <p className="text-xs font-semibold text-blue-700 mb-3">Criterios de auditoría para este curso</p>
      <div className="flex items-end gap-4">
        <div>
          <label className="block text-xs text-slate-600 mb-1">Módulos esperados</label>
          <input type="number" min="1" max="30" value={modulos}
            onChange={e => setModulos(parseInt(e.target.value))}
            className="w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-blue-400" />
        </div>
        <div>
          <label className="block text-xs text-slate-600 mb-1">Exámenes esperados</label>
          <input type="number" min="0" max="30" value={examenes}
            onChange={e => setExamenes(parseInt(e.target.value))}
            className="w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-blue-400" />
        </div>
        <button onClick={() => onGuardar(modulos, examenes)}
          className="bg-blue-600 text-white text-xs font-medium px-4 py-2 rounded-lg hover:bg-blue-500 transition-colors">
          Guardar
        </button>
        <button onClick={onCancelar}
          className="text-slate-500 text-xs hover:text-slate-700 transition-colors">
          Cancelar
        </button>
      </div>
    </div>
  )
}
