import crypto from 'node:crypto';
import { hash, verify, Algorithm } from '@node-rs/argon2';

/** Argon2id з параметрами OWASP (19 MiB, 2 ітерації). */
const ARGON = { algorithm: Algorithm.Argon2id, memoryCost: 19456, timeCost: 2, parallelism: 1 };
export const hashPassword = (pw: string) => hash(pw, ARGON);
export const verifyPassword = (h: string, pw: string) => verify(h, pw).catch(() => false);

export const randomToken = (bytes = 32) => crypto.randomBytes(bytes).toString('base64url');
export const sha256 = (v: string) => crypto.createHash('sha256').update(v).digest('hex');
export const hmac = (key: string, v: string) => crypto.createHmac('sha256', Buffer.from(key, 'base64')).update(v).digest('hex');

/** AES-256-GCM для телефонів, email і секретів 2FA. Формат: v1.<iv>.<tag>.<data> (base64url). */
export function encrypt(keyB64: string, plain: string): string {
  const iv = crypto.randomBytes(12);
  const c = crypto.createCipheriv('aes-256-gcm', Buffer.from(keyB64, 'base64'), iv);
  const data = Buffer.concat([c.update(plain, 'utf8'), c.final()]);
  return ['v1', iv.toString('base64url'), c.getAuthTag().toString('base64url'), data.toString('base64url')].join('.');
}
export function decrypt(keyB64: string, payload: string): string {
  const [v, iv, tag, data] = payload.split('.');
  if (v !== 'v1') throw new Error('Невідомий формат шифрування');
  const d = crypto.createDecipheriv('aes-256-gcm', Buffer.from(keyB64, 'base64'), Buffer.from(iv, 'base64url'));
  d.setAuthTag(Buffer.from(tag, 'base64url'));
  return Buffer.concat([d.update(Buffer.from(data, 'base64url')), d.final()]).toString('utf8');
}
export function safeEqual(a: string, b: string): boolean {
  const x = Buffer.from(a), y = Buffer.from(b);
  return x.length === y.length && crypto.timingSafeEqual(x, y);
}
/** Нормалізація українського номера до +380XXXXXXXXX. Повертає null, якщо номер некоректний. */
export function normalizeUaPhone(raw: string): string | null {
  const d = raw.replace(/\D/g, '');
  const n = d.length === 10 && d.startsWith('0') ? '38' + d : d.length === 9 ? '380' + d : d;
  return /^380\d{9}$/.test(n) ? '+' + n : null;
}
