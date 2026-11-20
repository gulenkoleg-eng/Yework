/** Виклик API з браузера. Заголовок X-YEWORK-CSRF обов'язковий для всіх запитів, що змінюють дані. */
export async function api<T = any>(path: string, opts: { method?: string; body?: unknown; setupKey?: string } = {}): Promise<T> {
  const headers: Record<string, string> = { 'x-yework-csrf': '1' };
  if (opts.body !== undefined) headers['content-type'] = 'application/json';
  if (opts.setupKey) headers['x-setup-key'] = opts.setupKey;
  let res: Response;
  try {
    res = await fetch('/api' + path, { method: opts.method ?? (opts.body !== undefined ? 'POST' : 'GET'), headers, body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined, credentials: 'same-origin' });
  } catch {
    throw new ApiError('Сервер не відповідає. Перевірте, що API запущено.', 0);
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(data?.message ?? `Помилка ${res.status}`, res.status, data);
  return data as T;
}
export class ApiError extends Error { constructor(message: string, public status: number, public data?: any) { super(message); } }
