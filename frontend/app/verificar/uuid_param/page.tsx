// app/verificar/[uuid]/page.tsx
// Página pública — sin autenticación — accesible desde cualquier dominio

import { Metadata } from 'next'
import VerificarCliente from '@/components/verificar/VerificarCliente'

interface Props {
  params: { uuid: string }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  return {
    title: `Verificar certificado · ${params.uuid.slice(0, 8)}...`,
    description: 'Verificación de autenticidad de certificado educativo',
    robots: 'noindex', // No indexar URLs de verificación
  }
}

export default function VerificarPage({ params }: Props) {
  return <VerificarCliente uuid={params.uuid} />
}
