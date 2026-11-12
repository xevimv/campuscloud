'use client'
// app/verificar/page.tsx
// Página pública donde cualquiera puede pegar un UUID y verificar

import { useState } from 'react'
import { Shield, Search } from 'lucide-react'
import { useRouter } from 'next/navigation'

export default function VerificarBusquedaPage() {
  const [uuid, setUuid] = useState('')
  const [error, setError] = useState('')
  const router = useRouter()

  const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

  function buscar(e: React.FormEvent) {
    e.preventDefault()
    const limpio = uuid.trim()

    if (!UUID_REGEX.test(limpio)) {
      setError('El código debe tener el formato: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
      return
    }

    router.push(`/verificar/${limpio}`)
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center p-4">
      <div className="w-full max-w-md">

        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-sm border border-slate-100 mb-4">
            <Shield size={30} className="text-slate-600" />
          </div>
          <h1 className="text-2xl font-bold text-slate-800">Verificar Certificado</h1>
          <p className="text-slate-500 text-sm mt-2 max-w-sm mx-auto">
            Ingresa el código único que aparece en el certificado para verificar su autenticidad.
          </p>
        </div>

        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <form onSubmit={buscar} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                Código de verificación (UUID)
              </label>
              <input
                type="text"
                value={uuid}
                onChange={e => { setUuid(e.target.value); setError('') }}
                placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                className="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono placeholder-slate-300 focus:outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100 transition"
                spellCheck={false}
                autoComplete="off"
              />
              {error && <p className="text-xs text-red-500 mt-2">{error}</p>}
            </div>

            <button type="submit"
              className="w-full flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-white font-medium py-3 rounded-xl text-sm transition-colors">
              <Search size={16} /> Verificar certificado
            </button>
          </form>

          <div className="mt-5 pt-5 border-t border-slate-50">
            <p className="text-xs text-slate-400 text-center leading-relaxed">
              El código único aparece impreso en el certificado o fue enviado por correo electrónico al titular.
              También puedes escanear el código QR del certificado para verificar directamente.
            </p>
          </div>
        </div>

        <p className="text-center text-xs text-slate-400 mt-6">
          Operado por{' '}
          <a href="https://campusvirtual.edu.co" className="hover:underline">Nomaddi SAS</a>
        </p>
      </div>
    </div>
  )
}
