'use client'
// app/inventario/page.tsx

import { useState } from 'react'
import useSWR from 'swr'
import Link from 'next/link'
import { api } from '@/lib/api'
import { Empty, Skeleton } from '@/components/ui'
import { RefreshCw, ChevronRight, Search } from 'lucide-react'

const fetcher = (url: string) => api.get(url)

interface CursoResumen {
  curso_id: string
  moodle_course_id: number
  nombre: string
  nombre_corto: string
  tipo_programa: string
  orden_en_carrera: number
  imagen: string | null
  descripcion_corta: string
  secciones: number
  total_actividades: number
  videos: number
  audios: number
  pdfs: number
  paginas: number
  examenes: number
  h5p: number
  enlaces: number
  tareas: number
  certificado: boolean
  error?: string
}

function Num({ n, warn = false }: { n: number; warn?: boolean }) {
  if (n === 0) return <span className="text-slate-300">—</span>
  return (
    <span className={`font-semibold ${warn && n === 0 ? 'text-red-500' : 'text-slate-700'}`}>
      {n}
    </span>
  )
}

export default function InventarioPage() {
  const { data, isLoading, mutate } = useSWR('/inventario', fetcher)
  const [busqueda, setBusqueda] = useState('')
  const [filtro, setFiltro] = useState<'TODOS' | 'TECNICOS' | 'BACHILLERATO'>('TODOS')

  const cursos: CursoResumen[] = (data as any)?.cursos ?? []

  const cursosFiltrados = cursos
    .filter(c => filtro === 'TODOS' || c.tipo_programa === filtro)
    .filter(c => !busqueda || c.nombre.toLowerCase().includes(busqueda.toLowerCase()))

  return (
    <div className="p-6 space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Inventario de Cursos</h1>
          <p className="text-slate-500 text-sm mt-0.5">
            Tabla de contenido completa de todos los cursos en Moodle
          </p>
        </div>
        <button onClick={() => mutate()}
          className="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
          <RefreshCw size={14} /> Refrescar
        </button>
      </div>

      {/* Filtros y búsqueda */}
      <div className="flex items-center gap-3 flex-wrap">
        <div className="relative">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input type="text" placeholder="Buscar curso..." value={busqueda}
            onChange={e => setBusqueda(e.target.value)}
            className="pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 w-56" />
        </div>
        {(['TODOS', 'TECNICOS', 'BACHILLERATO'] as const).map(f => (
          <button key={f} onClick={() => setFiltro(f)}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
              filtro === f
                ? 'bg-slate-800 text-white'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}>
            {f === 'TODOS' ? 'Todos' : f === 'TECNICOS' ? 'Técnicos' : 'Bachillerato'}
          </button>
        ))}
        <span className="text-xs text-slate-400 ml-auto">{cursosFiltrados.length} cursos</span>
      </div>

      {/* Tabla */}
      <div className="bg-white rounded-xl border border-slate-100 overflow-x-auto">
        <table className="w-full text-sm min-w-[1000px]">
          <thead>
            <tr className="border-b border-slate-100 bg-slate-50">
              <th className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase sticky left-0 bg-slate-50 min-w-[220px]">Curso</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">Orden</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">Secciones</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">Total</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">🎬 Videos</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">🎵 Audios</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">📄 PDFs</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">📝 Páginas</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">📋 Exámenes</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">🎮 H5P</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">🔗 Enlaces</th>
              <th className="text-center px-3 py-3 text-xs font-semibold text-slate-500 uppercase">🏆 Cert.</th>
              <th className="px-3 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              [...Array(6)].map((_, i) => (
                <tr key={i} className="border-b border-slate-50">
                  {[...Array(13)].map((_, j) => (
                    <td key={j} className="px-3 py-4">
                      <Skeleton className="h-4 w-10 mx-auto" />
                    </td>
                  ))}
                </tr>
              ))
            ) : cursosFiltrados.length === 0 ? (
              <tr><td colSpan={13}><Empty mensaje="Sin cursos" /></td></tr>
            ) : (
              cursosFiltrados.map(curso => (
                <tr key={curso.curso_id}
                  className="border-b border-slate-50 hover:bg-slate-50 transition-colors">

                  {/* Nombre del curso */}
                  <td className="px-4 py-3 sticky left-0 bg-white hover:bg-slate-50">
                    <div className="flex items-center gap-3">
                      {curso.imagen ? (
                        <img src={curso.imagen} alt="" className="w-10 h-10 rounded-lg object-cover flex-shrink-0 border border-slate-100" />
                      ) : (
                        <div className="w-10 h-10 rounded-lg bg-slate-100 flex-shrink-0 flex items-center justify-center text-lg">
                          {curso.tipo_programa === 'TECNICOS' ? '⚙️' : '🎓'}
                        </div>
                      )}
                      <div className="min-w-0">
                        <p className="font-medium text-slate-800 text-xs leading-tight line-clamp-2">{curso.nombre}</p>
                        <p className="text-xs text-slate-400 font-mono mt-0.5">{curso.nombre_corto}</p>
                        {curso.error && <p className="text-xs text-red-500 mt-0.5">{curso.error}</p>}
                      </div>
                    </div>
                  </td>

                  <td className="px-3 py-3 text-center text-slate-500 text-xs">{curso.orden_en_carrera}</td>
                  <td className="px-3 py-3 text-center"><Num n={curso.secciones} /></td>
                  <td className="px-3 py-3 text-center">
                    <span className="font-bold text-slate-800">{curso.total_actividades}</span>
                  </td>
                  <td className="px-3 py-3 text-center"><Num n={curso.videos} /></td>
                  <td className="px-3 py-3 text-center"><Num n={curso.audios} /></td>
                  <td className="px-3 py-3 text-center"><Num n={curso.pdfs} /></td>
                  <td className="px-3 py-3 text-center"><Num n={curso.paginas} /></td>
                  <td className="px-3 py-3 text-center">
                    <span className={`font-semibold ${curso.examenes === 0 ? 'text-red-400' : 'text-slate-700'}`}>
                      {curso.examenes || '—'}
                    </span>
                  </td>
                  <td className="px-3 py-3 text-center"><Num n={curso.h5p} /></td>
                  <td className="px-3 py-3 text-center"><Num n={curso.enlaces} /></td>
                  <td className="px-3 py-3 text-center">
                    {curso.certificado
                      ? <span title="Tiene actividad de certificado">🏆</span>
                      : <span className="text-slate-200">—</span>
                    }
                  </td>
                  <td className="px-3 py-3">
                    <Link href={`/inventario/${curso.curso_id}`}
                      className="text-blue-600 hover:text-blue-700 transition-colors">
                      <ChevronRight size={14} />
                    </Link>
                  </td>
                </tr>
              ))
            )}
          </tbody>

          {/* Fila de totales */}
          {!isLoading && cursosFiltrados.length > 0 && (
            <tfoot>
              <tr className="bg-slate-50 border-t border-slate-200">
                <td className="px-4 py-3 text-xs font-bold text-slate-600 sticky left-0 bg-slate-50">
                  TOTALES ({cursosFiltrados.length} cursos)
                </td>
                <td></td>
                <td className="px-3 py-3 text-center font-bold text-slate-700 text-xs">
                  {cursosFiltrados.reduce((s, c) => s + (c.secciones || 0), 0)}
                </td>
                <td className="px-3 py-3 text-center font-bold text-slate-800 text-xs">
                  {cursosFiltrados.reduce((s, c) => s + (c.total_actividades || 0), 0)}
                </td>
                {(['videos','audios','pdfs','paginas','examenes','h5p','enlaces'] as const).map(campo => (
                  <td key={campo} className="px-3 py-3 text-center font-bold text-slate-700 text-xs">
                    {cursosFiltrados.reduce((s, c) => s + ((c as any)[campo] || 0), 0) || '—'}
                  </td>
                ))}
                <td className="px-3 py-3 text-center text-xs text-slate-500">
                  {cursosFiltrados.filter(c => c.certificado).length} con cert.
                </td>
                <td></td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

    </div>
  )
}
