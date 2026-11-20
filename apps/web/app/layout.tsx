import type { Metadata, Viewport } from 'next';
import { Inter } from 'next/font/google';
import './globals.css';

// next/font завантажує шрифт під час збірки й віддає його з нашого сервера (без запитів до Google у браузері).
const inter = Inter({ subsets: ['latin', 'cyrillic'], variable: '--font-inter', display: 'swap' });

export const metadata: Metadata = { title: 'YEWORK — Є робота!', description: 'Робота, підробіток і майстри поруч з вами' };
export const viewport: Viewport = { width: 'device-width', initialScale: 1, themeColor: '#3D5AFE' };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="uk" className={inter.variable}>
      <body>{children}</body>
    </html>
  );
}
