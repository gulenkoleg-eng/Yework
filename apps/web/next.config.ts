import type { NextConfig } from 'next';
const API_URL = process.env.API_URL ?? 'http://localhost:4000';
const config: NextConfig = {
  output: 'standalone',
  poweredByHeader: false,
  // Усі /api/* проксуються на бекенд: один домен, cookie першої сторони, без CORS.
  async rewrites() { return [{ source: '/api/:path*', destination: `${API_URL}/api/:path*` }]; },
  async headers() {
    return [{ source: '/:path*', headers: [
      { key: 'X-Frame-Options', value: 'DENY' },
      { key: 'X-Content-Type-Options', value: 'nosniff' },
      { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
    ] }];
  },
};
export default config;
