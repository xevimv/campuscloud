'use client'
// components/verificar/VerificarCliente.tsx

import { useEffect, useState } from 'react'
import {
  CheckCircle, XCircle, AlertTriangle,
  Loader2, GraduationCap, Calendar,
  Building2, FileText, Hash, Shield,
} from 'lucide-react'

interface ResultadoValido {
  valido: true
  estudiante: string
  programa: string
  tipo_programa: 'TECNICOS' | 'BACHILLERATO'
  institucion: string
  fecha_emision: string
  acta: string
  folio: number
  referencia: string
}

interface ResultadoInvalido {
  valido: false
  anulado?: boolean
  mensaje: string
  motivo?: string
}

type Resultado = ResultadoValido | ResultadoInvalido

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'https://panel.campusvirtual.edu.co/api'

export default function VerificarCliente({ uuid }: { uuid: string }) {
  const [resultado, setResultado] = useState<Resultado | null>(null)
  const [cargando, setCargando] = useState(true)

  useEffect(() => {
    async function verificar() {
      try {
        const res = await fetch(`${API_BASE}/verificar/${uuid}`, {
          headers: { Accept: 'application/json' },
        })
        const data = await res.json()
        setResultado(data)
      } catch {
        setResultado({ valido: false, mensaje: 'No se pudo conectar con el servidor de verificación.' })
      } finally {
        setCargando(false)
      }
    }
    verificar()
  }, [uuid])

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center p-4">
      <div className="w-full max-w-lg">

        {/* Encabezado */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-white rounded-2xl shadow-sm border border-slate-100 mb-4">
            <Shield size={26} className="text-slate-600" />
          </div>
          <h1 className="text-xl font-bold text-slate-800">Verificación de Certificado</h1>
          <p className="text-slate-500 text-sm mt-1">Sistema de autenticidad de certificados educativos</p>
        </div>

        {/* Tarjeta de resultado */}
        {cargando ? (
          <EstadoCargando />
        ) : resultado?.valido ? (
          <EstadoValido resultado={resultado as ResultadoValido} uuid={uuid} />
        ) : (resultado as ResultadoInvalido)?.anulado ? (
          <EstadoAnulado resultado={resultado as ResultadoInvalido} />
        ) : (
          <EstadoInvalido resultado={resultado as ResultadoInvalido} />
        )}

        {/* UUID consultado */}
        <p className="text-center text-xs text-slate-400 mt-6 font-mono break-all">
          Código: {uuid}
        </p>

        {/* Pie */}
        <div className="text-center mt-4">
          <p className="text-xs text-slate-400">
            Sistema operado por{' '}
            <a href="https://campusvirtual.edu.co" className="text-slate-500 hover:underline">
              Nomaddi SAS
            </a>
          </p>
        </div>

      </div>
    </div>
  )
}

// ── Estado: cargando ─────────────────────────────────────────────
function EstadoCargando() {
  return (
    <div className="bg-white rounded-2xl border border-slate-100 shadow-sm p-8 text-center">
      <Loader2 size={36} className="text-slate-400 animate-spin mx-auto mb-4" />
      <p className="text-slate-600 font-medium">Verificando certificado...</p>
      <p className="text-slate-400 text-sm mt-1">Consultando el registro oficial</p>
    </div>
  )
}

// ── Estado: válido ────────────────────────────────────────────────
function EstadoValido({ resultado, uuid }: { resultado: ResultadoValido; uuid: string }) {
  const tipoLabel = resultado.tipo_programa === 'TECNICOS'
    ? 'Técnico Laboral'
    : 'Bachillerato'

  return (
    <div className="bg-white rounded-2xl border border-emerald-100 shadow-sm overflow-hidden">

      {/* Banner verde */}
      <div className="bg-emerald-500 px-6 py-5 text-white">
        <div className="flex items-center gap-3">
          <CheckCircle size={28} className="flex-shrink-0" />
          <div>
            <p className="font-bold text-lg leading-tight">Certificado Válido</p>
            <p className="text-emerald-100 text-sm">Este certificado es auténtico y fue emitido por la institución indicada.</p>
          </div>
        </div>
      </div>

      {/* Datos del certificado */}
      <div className="p-6 space-y-4">

        <DatoFila
          icon={<GraduationCap size={16} className="text-emerald-600" />}
          label="Estudiante"
          valor={resultado.estudiante}
          destacado
        />

        <DatoFila
          icon={<FileText size={16} className="text-slate-500" />}
          label="Programa"
          valor={resultado.programa}
        />

        <DatoFila
          icon={<Building2 size={16} className="text-slate-500" />}
          label="Institución emisora"
          valor={resultado.institucion}
          sub={tipoLabel}
        />

        <DatoFila
          icon={<Calendar size={16} className="text-slate-500" />}
          label="Fecha de emisión"
          valor={resultado.fecha_emision}
        />

        <div className="grid grid-cols-2 gap-3 pt-2">
          <div className="bg-slate-50 rounded-xl p-4 text-center">
            <p className="text-xs text-slate-400 mb-1">N° de Acta</p>
            <p className="text-xl font-bold text-slate-700 font-mono">{resultado.acta}</p>
          </div>
          <div className="bg-slate-50 rounded-xl p-4 text-center">
            <p className="text-xs text-slate-400 mb-1">N° de Folio</p>
            <p className="text-xl font-bold text-slate-700 font-mono">{resultado.folio}</p>
          </div>
        </div>

      </div>

      {/* Pie de la tarjeta */}
      <div className="border-t border-slate-50 px-6 py-4 bg-slate-50">
        <p className="text-xs text-slate-400 text-center">
          Verificado el {new Date().toLocaleDateString('es-CO', {
            day: '2-digit', month: 'long', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
          })}
        </p>
      </div>

    </div>
  )
}

// ── Estado: anulado ───────────────────────────────────────────────
function EstadoAnulado({ resultado }: { resultado: ResultadoInvalido }) {
  return (
    <div className="bg-white rounded-2xl border border-amber-100 shadow-sm overflow-hidden">
      <div className="bg-amber-500 px-6 py-5 text-white">
        <div className="flex items-center gap-3">
          <AlertTriangle size={28} className="flex-shrink-0" />
          <div>
            <p className="font-bold text-lg leading-tight">Certificado Anulado</p>
            <p className="text-amber-100 text-sm">Este certificado existe en el sistema pero ha sido anulado.</p>
          </div>
        </div>
      </div>
      <div className="p-6">
        {resultado.motivo && (
          <div className="bg-amber-50 border border-amber-100 rounded-xl p-4">
            <p className="text-sm text-amber-700 font-medium mb-1">Motivo de anulación:</p>
            <p className="text-sm text-amber-800">{resultado.motivo}</p>
          </div>
        )}
        <p className="text-sm text-slate-500 mt-4">
          Si tiene preguntas sobre este certificado, comuníquese directamente con la institución emisora.
        </p>
      </div>
    </div>
  )
}

// ── Estado: inválido ──────────────────────────────────────────────
function EstadoInvalido({ resultado }: { resultado: ResultadoInvalido }) {
  return (
    <div className="bg-white rounded-2xl border border-red-100 shadow-sm overflow-hidden">
      <div className="bg-red-500 px-6 py-5 text-white">
        <div className="flex items-center gap-3">
          <XCircle size={28} className="flex-shrink-0" />
          <div>
            <p className="font-bold text-lg leading-tight">Certificado No Encontrado</p>
            <p className="text-red-100 text-sm">El código ingresado no corresponde a ningún certificado registrado.</p>
          </div>
        </div>
      </div>
      <div className="p-6 space-y-3">
        <p className="text-sm text-slate-600">{resultado.mensaje}</p>
        <div className="bg-slate-50 rounded-xl p-4 text-sm text-slate-500 space-y-1">
          <p className="font-medium text-slate-600 mb-2">Posibles razones:</p>
          <p>• El código fue digitado incorrectamente.</p>
          <p>• El certificado no fue emitido por este sistema.</p>
          <p>• El código QR está dañado o fue alterado.</p>
        </div>
        <p className="text-xs text-slate-400 text-center pt-2">
          Si cree que esto es un error, comuníquese con la institución educativa.
        </p>
      </div>
    </div>
  )
}

// ── Componente auxiliar: fila de dato ────────────────────────────
function DatoFila({
  icon, label, valor, sub, destacado = false
}: {
  icon: React.ReactNode
  label: string
  valor: string
  sub?: string
  destacado?: boolean
}) {
  return (
    <div className="flex items-start gap-3">
      <div className="mt-0.5 flex-shrink-0">{icon}</div>
      <div className="min-w-0">
        <p className="text-xs text-slate-400 mb-0.5">{label}</p>
        <p className={`text-sm leading-snug break-words ${destacado ? 'font-bold text-slate-800 text-base' : 'font-medium text-slate-700'}`}>
          {valor}
        </p>
        {sub && <p className="text-xs text-slate-400 mt-0.5">{sub}</p>}
      </div>
    </div>
  )
}
