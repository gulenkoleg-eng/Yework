import { z } from 'zod';
import { BadRequestException } from '@nestjs/common';
import { CONFIG_FILE_RE } from '../config/runtime-config';

const THEMES = ['modern-light', 'modern-dark', 'corporate-blue', 'emerald', 'premium', 'soft-ui'] as const;

export const dbSchema = z.object({
  host: z.string().trim().min(1, 'Вкажіть хост').max(255),
  port: z.coerce.number().int().min(1).max(65535).default(5432),
  database: z.string().trim().regex(/^[A-Za-z_][A-Za-z0-9_$-]{0,62}$/, 'Назва бази: латиниця, цифри, _ (до 63 символів)'),
  user: z.string().trim().min(1, 'Вкажіть користувача БД').max(63),
  password: z.string().min(1, 'Вкажіть пароль БД').max(200),
  ssl: z.boolean().default(false),
});

export const siteSchema = z.object({
  name: z.string().trim().min(2, 'Назва сайту закоротка').max(60),
  slogan: z.string().trim().max(80).default(''),
  url: z.string().trim().url('Вкажіть адресу сайту, наприклад https://yework.ua').refine((u) => /^https?:\/\//.test(u), 'Адреса має починатися з http:// або https://'),
  configFile: z.string().trim().toLowerCase()
    .transform((v) => (v.endsWith('.json') ? v : v + '.json'))
    .refine((v) => CONFIG_FILE_RE.test(v) && v !== 'install.lock.json', 'Ім’я файлу: латиниця, цифри, крапка, дефіс (наприклад yework.json)'),
  theme: z.enum(THEMES).default('modern-light'),
});

export const PASSWORD_MIN = 12;
export const adminSchema = z.object({
  login: z.string().trim().regex(/^[a-zA-Z0-9._-]{3,32}$/, 'Логін: 3–32 символи, латиниця, цифри, . _ -'),
  displayName: z.string().trim().min(2, 'Вкажіть ім’я').max(80),
  email: z.string().trim().email('Некоректний email').max(254).optional().or(z.literal('')),
  phone: z.string().trim().max(20).optional().or(z.literal('')),
  password: z.string().min(PASSWORD_MIN, `Пароль має містити щонайменше ${PASSWORD_MIN} символів`).max(200)
    .refine((p) => /[a-zа-яіїєґ]/.test(p) && /[A-ZА-ЯІЇЄҐ]/.test(p) && /\d/.test(p), 'Пароль має містити великі й малі літери та цифру'),
});

export const runSchema = z.object({ db: dbSchema, site: siteSchema, admin: adminSchema });
export type RunInput = z.infer<typeof runSchema>;
export type DbInput = z.infer<typeof dbSchema>;

export function parse<T extends z.ZodTypeAny>(schema: T, body: unknown): z.infer<T> {
  const r = schema.safeParse(body);
  if (!r.success) {
    const fields = r.error.issues.map((i) => ({ field: i.path.join('.'), message: i.message }));
    throw new BadRequestException({ message: fields[0]?.message ?? 'Некоректні дані', fields });
  }
  return r.data;
}
