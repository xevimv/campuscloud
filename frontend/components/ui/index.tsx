// components/ui/index.tsx
import clsx from 'clsx'
import { TrendingUp, TrendingDown, Minus } from 'lucide-react'

// ── MetricCard ────────────────────────────────────────────────────
interface MetricCardProps {
  label: string
  value: string | number
  sub?: string
  trend?: number | null   // % variación
  color?: 'blue' | 'green' | 'amber' | 'red' | 'slate'
  icon?: React.ReactNode
}

export function MetricCard({ label, value, sub, trend, color = 'blue', icon }: MetricCardProps) {
  const colors = {
    blue:  'bg-blue-50 border-blue-100',
    green: 'bg-emerald-50 border-emerald-100',
    amber: 'bg-amber-50 border-amber-100',
    red:   'bg-red-50 border-red-100',
    slate: 'bg-slate-50 border-slate-100',
  }
  const valueColors = {
    blue:  'text-blue-700',
    green: 'text-emerald-700',
    amber: 'text-amber-700',
    red:   'text-red-700',
    slate: 'text-slate-700',
  }

  return (
    <div className={clsx('rounded-xl border p-5', colors[color])}>
      <div className="flex justify-between items-start mb-3">
        <p className="text-sm text-slate-500 font-medium">{label}</p>
        {icon && <span className="text-slate-400">{icon}</span>}
      </div>
      <p className={clsx('text-3xl font-bold', valueColors[color])}>{value}</p>
      <div className="flex items-center gap-2 mt-2">
        {sub && <span className="text-xs text-slate-400">{sub}</span>}
        {trend !== undefined && trend !== null && (
          <span className={clsx(
            'flex items-center gap-0.5 text-xs font-medium',
            trend > 0 ? 'text-emerald-600' : trend < 0 ? 'text-red-500' : 'text-slate-400'
          )}>
            {trend > 0 ? <TrendingUp size={12}/> : trend < 0 ? <TrendingDown size={12}/> : <Minus size={12}/>}
            {Math.abs(trend)}%
          </span>
        )}
      </div>
    </div>
  )
}

// ── Badge ─────────────────────────────────────────────────────────
type BadgeVariant = 'green' | 'amber' | 'red' | 'blue' | 'slate'

export function Badge({ children, variant = 'slate' }: { children: React.ReactNode; variant?: BadgeVariant }) {
  const styles: Record<BadgeVariant, string> = {
    green: 'bg-emerald-100 text-emerald-700',
    amber: 'bg-amber-100 text-amber-700',
    red:   'bg-red-100 text-red-700',
    blue:  'bg-blue-100 text-blue-700',
    slate: 'bg-slate-100 text-slate-600',
  }
  return (
    <span className={clsx('inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', styles[variant])}>
      {children}
    </span>
  )
}

export function estadoBadge(estado: string) {
  const map: Record<string, BadgeVariant> = {
    ACTIVA: 'green', COMPLETADA: 'blue', CANCELADA: 'red',
    GENERADA: 'amber', ENVIADA: 'blue', FACTURADA: 'amber', PAGADA: 'green',
    MATRICULADO: 'green', RECHAZADO: 'red', ERROR: 'red',
    RECIBIDO: 'slate', VALIDADO: 'blue',
  }
  return <Badge variant={map[estado] ?? 'slate'}>{estado}</Badge>
}

// ── Alert ─────────────────────────────────────────────────────────
export function Alert({ tipo, mensaje, url }: { tipo: 'error'|'warning'|'info'; mensaje: string; url?: string }) {
  const styles = {
    error:   'bg-red-50 border-red-200 text-red-700',
    warning: 'bg-amber-50 border-amber-200 text-amber-700',
    info:    'bg-blue-50 border-blue-200 text-blue-700',
  }
  return (
    <div className={clsx('flex items-center gap-3 px-4 py-3 rounded-lg border text-sm', styles[tipo])}>
      <span className="flex-1">{mensaje}</span>
      {url && (
        <a href={url} className="font-medium underline underline-offset-2 text-xs whitespace-nowrap">
          Ver →
        </a>
      )}
    </div>
  )
}

// ── Skeleton loader ───────────────────────────────────────────────
export function Skeleton({ className }: { className?: string }) {
  return <div className={clsx('animate-pulse bg-slate-200 rounded', className)} />
}

export function CardSkeleton() {
  return (
    <div className="bg-slate-50 border border-slate-100 rounded-xl p-5">
      <Skeleton className="h-4 w-24 mb-3" />
      <Skeleton className="h-8 w-16 mb-2" />
      <Skeleton className="h-3 w-20" />
    </div>
  )
}

// ── Empty state ───────────────────────────────────────────────────
export function Empty({ mensaje }: { mensaje: string }) {
  return (
    <div className="text-center py-12 text-slate-400 text-sm">{mensaje}</div>
  )
}

// ── formatters ────────────────────────────────────────────────────
export function formatCOP(valor: number): string {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(valor)
}

export function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
}
