// lib/api.ts
const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'https://panel.campusvirtual.edu.co/api'

function getToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem('nomaddi_token')
}

async function apiFetch<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const token = getToken()
  const res = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })

  if (res.status === 401) {
    localStorage.removeItem('nomaddi_token')
    window.location.href = '/login'
    throw new Error('No autorizado')
  }

  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.message ?? `Error ${res.status}`)
  }

  return res.json()
}

export const api = {
  get:    <T>(url: string) => apiFetch<T>(url),
  post:   <T>(url: string, body?: unknown) =>
    apiFetch<T>(url, { method: 'POST', body: JSON.stringify(body) }),
  put:    <T>(url: string, body?: unknown) =>
    apiFetch<T>(url, { method: 'PUT', body: JSON.stringify(body) }),
  delete: <T>(url: string) => apiFetch<T>(url, { method: 'DELETE' }),
}

// ── Endpoints tipados ─────────────────────────────────────────────

export const endpoints = {
  // Auth
  login:          () => '/auth/login',
  me:             () => '/auth/me',

  // Dashboard
  dashboard:      () => '/dashboard',

  // Distribuidores
  distribuidores: () => '/distribuidores',
  distribuidor:   (id: string) => `/distribuidores/${id}`,
  vendedores:     (id: string) => `/distribuidores/${id}/vendedores`,
  reporteVendedores: (id: string) => `/distribuidores/${id}/vendedores/reporte`,

  // Liquidaciones
  liquidaciones:      () => '/liquidaciones',
  liquidacionesPendientes: () => '/liquidaciones/pendientes',
  liquidacion:        (id: string) => `/liquidaciones/${id}`,
  liquidacionFacturada: (id: string) => `/liquidaciones/${id}/facturada`,
  liquidacionPagada:  (id: string) => `/liquidaciones/${id}/pagada`,
  liquidacionPdf:     (id: string) => `/liquidaciones/${id}/pdf`,

  // Certificados
  certificados:   () => '/certificados',
  certificado:    (id: string) => `/certificados/${id}`,
  certificadoPdf: (id: string) => `/certificados/${id}/pdf`,
  verificar:      (uuid: string) => `/verificar/${uuid}`,

  // Webhooks
  logsWebhook:    () => '/logs/webhook',

  // SIET
  exportarSiet:   (ietdhId: string) => `/siet/exportar/${ietdhId}`,

  // IETDHs
  ietdhs:         () => '/ietdhs',
  ietdh:          (id: string) => `/ietdhs/${id}`,
}
