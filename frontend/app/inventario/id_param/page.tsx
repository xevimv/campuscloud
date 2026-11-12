'use client'
// app/inventario/[id]/page.tsx

import { useState } from 'react'
import useSWR from 'swr'
import { useParams } from 'next/navigation'
import { api } from '@/lib/api'
import { Empty, Skeleton } from '@/components/ui'
import { RefreshCw, ChevronLeft, ExternalLink } from 'lucide-react'
import Link from 'next/link'

const fetcher = (url: string) => api.get(url)

interface Modulo {
  id: number
  nombre: string
  tipo: string
  tipo_label: string
  icono: string
  modname_moodle: string
  visible: boolean
  seccion: number
  seccion_nombre: string
  url_moodle: string | null
  archivos: { nombre: string; tipo_mime: string; tamaño: string }[]
}

interface Seccion {
  numero: number
  nombre: string
  visible: boolean
  total_items: number
  modulos: Modulo[]
}

const COLORES_TIPO: Record<string, string> = {
  VIDEO:        'bg-red-100 text-red-700 border-red-200',
  AUDIO:        'bg-purple-100 text-purple-700 border-purple-200',
  PDF:          'bg-orange-100 text-orange-700 border-orange-200',
  PAGINA:       'bg-blue-100 text-blue-700 border-blue-200',
  ETIQUETA:     'bg-slate-100 text-slate-600 border-slate-200',
  LIBRO:        'bg-indigo-100 text-indigo-700 border-indigo-200',
  EXAMEN:       'bg-emerald-100 text-emerald-700 border-emerald-200',
  TAREA:        'bg-yellow-100 text-yellow-700 border-yellow-200',
  FORO:         'bg-cyan-100 text-cyan-700 border-cyan-200',
  ENLACE:       'bg-teal-100 text-teal-700 border-teal-200',
  H5P:          'bg-pink-100 text-pink-700 border-pink-200',
  CERTIFICADO:  'bg-amber-100 text-amber-700 border-amber-200',
  OTRO:         'bg-slate-100 text-slate-500 border-slate-200',
}

export default function InventarioDetallePage() {
  const { id } = useParams()
  const { data, isLoading, mutate } = useSWR(`/inventario/${id}`, fetcher)
  const [seccionExpandida, setSeccionExpandida] = useState<number | null>(null)

  const d = data as any

  async function refrescar() {
    await api.post(`/inventario/${id}/refrescar`)
    mutate()
  }

  if (isLoading) return (
    <div className="p-6 space-y-4">
      <Skeleton className="h-8 w-64" />
      <Skeleton className="h-4 w-48" />
      <div className="grid grid-cols-4 gap-4 mt-6">
        {[...Array(8)].map((_, i) => <Skeleton key={i} className="h-20 rounded-xl" />)}
      </div>
    </div>
  )

  if (!d) return <div className="p-6"><Empty mensaje="Curso no encontrado" /></div>

  const { metadatos, totales, resumen_tipos, secciones } = d

  return (
    <div className="p-6 space-y-6">

      {/* Header */}
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-start gap-4">
          {metadatos.imagen && (
            <img src={metadatos.imagen} alt=""
              className="w-20 h-20 rounded-xl object-cover border border-slate-100 flex-shrink-0" />
          )}
          <div>
            <Link href="/inventario" className="flex items-center gap-1 text-xs text-slate-400 hover:text-slate-600 mb-2 transition-colors">
              <ChevronLeft size={12} /> Volver al inventario
            </Link>
            <h1 className="text-xl font-bold text-slate-800 leading-tight">{metadatos.titulo}</h1>
            <p className="text-sm text-slate-500 font-mono mt-0.5">{metadatos.titulo_corto}</p>
            {metadatos.categoria && (
              <span className="inline-block mt-2 text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">
                {metadatos.categoria}
              </span>
            )}
          </div>
        </div>
        <button onClick={refrescar}
          className="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors flex-shrink-0">
          <RefreshCw size={14} /> Refrescar
        </button>
      </div>

      {/* Descripción */}
      {metadatos.descripcion && (
        <div className="bg-slate-50 rounded-xl border border-slate-100 p-4">
          <p className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Descripción del curso</p>
          <p className="text-sm text-slate-600 leading-relaxed">{metadatos.descripcion}</p>
        </div>
      )}

      {/* Totales por tipo — chips */}
      <div>
        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">
          {totales.actividades_total} actividades en {totales.secciones_con_contenido} secciones
        </p>
        <div className="flex flex-wrap gap-2">
          {(resumen_tipos as any[]).map((rt: any) => (
            <div key={rt.tipo}
              className={`flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm font-medium ${COLORES_TIPO[rt.tipo] ?? COLORES_TIPO.OTRO}`}>
              <span>{rt.icono}</span>
              <span>{rt.label}</span>
              <span className="font-bold">{rt.cantidad}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Grid de métricas rápidas */}
      <div className="grid grid-cols-3 md:grid-cols-6 gap-3">
        {[
          { label: 'Videos',   n: totales.videos,   icono: '🎬', color: 'bg-red-50 border-red-100' },
          { label: 'Audios',   n: totales.audios,   icono: '🎵', color: 'bg-purple-50 border-purple-100' },
          { label: 'PDFs',     n: totales.pdfs,     icono: '📄', color: 'bg-orange-50 border-orange-100' },
          { label: 'Páginas',  n: totales.paginas,  icono: '📝', color: 'bg-blue-50 border-blue-100' },
          { label: 'Exámenes', n: totales.examenes, icono: '📋', color: 'bg-emerald-50 border-emerald-100' },
          { label: 'H5P',      n: totales.h5p,      icono: '🎮', color: 'bg-pink-50 border-pink-100' },
        ].map(m => (
          <div key={m.label} className={`rounded-xl border p-3 text-center ${m.color}`}>
            <div className="text-2xl mb-1">{m.icono}</div>
            <div className="text-xl font-bold text-slate-800">{m.n}</div>
            <div className="text-xs text-slate-500">{m.label}</div>
          </div>
        ))}
      </div>

      {/* Secciones expandibles */}
      <div className="space-y-2">
        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">
          Contenido por sección
        </p>

        {(secciones as Seccion[]).map(seccion => (
          <div key={seccion.numero}
            className={`bg-white rounded-xl border overflow-hidden transition-all ${
              seccion.visible ? 'border-slate-100' : 'border-slate-100 opacity-60'
            }`}>

            {/* Cabecera de sección */}
            <button
              onClick={() => setSeccionExpandida(
                seccionExpandida === seccion.numero ? null : seccion.numero
              )}
              className="w-full flex items-center gap-4 px-5 py-3.5 text-left hover:bg-slate-50 transition-colors">

              <div className="w-7 h-7 rounded-lg bg-slate-800 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                {seccion.numero}
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-medium text-slate-800 text-sm truncate">{seccion.nombre}</p>
                {!seccion.visible && (
                  <span className="text-xs text-amber-500">Oculta para estudiantes</span>
                )}
              </div>

              {/* Mini resumen de tipos en la sección */}
              <div className="flex items-center gap-2 flex-shrink-0">
                {seccion.modulos.reduce((acc: Record<string, number>, m) => {
                  acc[m.icono] = (acc[m.icono] || 0) + 1
                  return acc
                }, {})}
                {Object.entries(
                  seccion.modulos.reduce((acc: Record<string, number>, m) => {
                    acc[m.icono] = (acc[m.icono] || 0) + 1
                    return acc
                  }, {})
                ).map(([icono, count]) => (
                  <span key={icono} className="text-xs text-slate-500 flex items-center gap-0.5">
                    {icono} {count > 1 ? count : ''}
                  </span>
                ))}
                <span className="text-xs text-slate-400 ml-2">
                  {seccion.total_items} item{seccion.total_items !== 1 ? 's' : ''}
                </span>
                <span className={`text-slate-400 transition-transform ${seccionExpandida === seccion.numero ? 'rotate-90' : ''}`}>
                  ›
                </span>
              </div>
            </button>

            {/* Detalle de módulos */}
            {seccionExpandida === seccion.numero && (
              <div className="border-t border-slate-50">
                {seccion.modulos.length === 0 ? (
                  <p className="px-5 py-4 text-sm text-slate-400">Sección vacía</p>
                ) : (
                  seccion.modulos.map((modulo, i) => (
                    <div key={modulo.id ?? i}
                      className={`flex items-start gap-4 px-5 py-3 ${
                        i < seccion.modulos.length - 1 ? 'border-b border-slate-50' : ''
                      } ${!modulo.visible ? 'opacity-50' : ''}`}>

                      {/* Ícono del tipo */}
                      <div className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-md border text-xs font-medium flex-shrink-0 ${
                        COLORES_TIPO[modulo.tipo] ?? COLORES_TIPO.OTRO
                      }`}>
                        {modulo.icono} {modulo.tipo_label}
                      </div>

                      {/* Nombre y detalles */}
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-slate-700 truncate">{modulo.nombre}</p>
                        {modulo.archivos.length > 0 && (
                          <div className="flex flex-wrap gap-2 mt-1">
                            {modulo.archivos.map((a, j) => (
                              <span key={j} className="text-xs text-slate-400">
                                {a.nombre} {a.tamaño ? `· ${a.tamaño}` : ''}
                              </span>
                            ))}
                          </div>
                        )}
                        {!modulo.visible && (
                          <span className="text-xs text-amber-500">Oculto</span>
                        )}
                      </div>

                      {/* Enlace a Moodle */}
                      {modulo.url_moodle && (
                        <a href={modulo.url_moodle} target="_blank" rel="noopener noreferrer"
                          className="text-slate-300 hover:text-blue-500 transition-colors flex-shrink-0">
                          <ExternalLink size={13} />
                        </a>
                      )}
                    </div>
                  ))
                )}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Pie: inventariado en */}
      <p className="text-xs text-slate-400 text-center">
        Inventariado el {d.inventariado_en} · Los datos se cachean 15 minutos
      </p>

    </div>
  )
}
