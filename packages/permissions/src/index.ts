/**
 * Каталог прав YEWORK. Джерело істини для прав — цей файл;
 * під час встановлення та міграцій він синхронізується в таблицю access.permissions.
 * Формат коду: <модуль>.<ресурс>:<дія>
 */
export type Action = 'view' | 'create' | 'update' | 'delete' | 'moderate' | 'export' | 'manage_settings' | 'view_contacts' | 'assign' | 'publish' | 'run';

export interface PermissionDef {
  code: string;
  module: string;
  resource: string;
  action: Action;
  critical: boolean;
  description: string;
}

const p = (module: string, resource: string, action: Action, description: string, critical = false): PermissionDef => ({
  code: `${module}.${resource}:${action}`, module, resource, action, critical, description,
});

export const PERMISSIONS: PermissionDef[] = [
  // користувачі
  p('identity', 'user', 'view', 'Перегляд користувачів'),
  p('identity', 'user', 'update', 'Редагування та блокування користувачів'),
  p('identity', 'user', 'view_contacts', 'Перегляд телефонів і email користувачів'),
  // права доступу
  p('access', 'role', 'view', 'Перегляд ролей і прав'),
  p('access', 'role', 'manage_settings', 'Створення та зміна ролей, призначення ролей', true),
  p('access', 'critical', 'assign', 'Видача критичних прав', true),
  // контент трьох напрямків
  ...(['jobs', 'gigs', 'services'] as const).flatMap((m) => [
    p(m, 'listing', 'view', `Перегляд оголошень (${m})`),
    p(m, 'listing', 'update', `Редагування оголошень (${m})`),
    p(m, 'listing', 'delete', `Видалення оголошень (${m})`),
    p(m, 'listing', 'moderate', `Модерація оголошень (${m})`),
  ]),
  p('moderation', 'policy', 'manage_settings', 'Налаштування модерації'),
  p('messaging', 'support', 'view', 'Звернення в підтримку'),
  // дизайн
  p('theming', 'theme', 'view', 'Перегляд тем оформлення'),
  p('theming', 'theme', 'update', 'Створення та редагування тем (чернетки)'),
  p('theming', 'theme', 'publish', 'Публікація теми для всього сайту', true),
  // гроші, AI, статистика
  p('billing', 'plan', 'view', 'Перегляд тарифів і платежів'),
  p('billing', 'plan', 'update', 'Редагування тарифів'),
  p('billing', 'monetization', 'manage_settings', 'Увімкнення та вимкнення монетизації', true),
  p('ai', 'provider', 'manage_settings', 'Налаштування AI-провайдерів і ключів', true),
  p('ai', 'usage', 'view', 'Перегляд витрат на AI'),
  p('stats', 'dashboard', 'view', 'Статистика (агреговані дані)'),
  // дані та система
  p('data', 'export', 'run', 'Експорт у межах свого скоупу'),
  p('data', 'export_global', 'run', 'Глобальний експорт усіх даних', true),
  p('data', 'backup', 'manage_settings', 'Керування резервними копіями', true),
  p('data', 'restore', 'run', 'Відновлення з резервної копії', true),
  p('settings', 'system', 'manage_settings', 'Системні налаштування', true),
  p('audit', 'log', 'view', 'Перегляд журналу дій'),
];

export const CRITICAL_PERMISSIONS = PERMISSIONS.filter((x) => x.critical).map((x) => x.code);

export interface SystemRoleDef { slug: string; name: string; description: string; permissions: string[] | '*'; }

const codes = (pred: (x: PermissionDef) => boolean) => PERMISSIONS.filter((x) => !x.critical && pred(x)).map((x) => x.code);

/** Стартові ролі. Критичні права не входять у жодну роль — вони видаються окремо (access.critical_grants). */
export const SYSTEM_ROLES: SystemRoleDef[] = [
  { slug: 'owner', name: 'Головний адміністратор', description: 'Власник платформи. Єдиний, хто видає критичні права.', permissions: '*' },
  { slug: 'senior_admin', name: 'Старший адміністратор', description: 'Користувачі, контент, модерація, налаштування без критичних прав.', permissions: codes(() => true) },
  { slug: 'moderator_jobs', name: 'Модератор вакансій', description: 'Модерація вакансій.', permissions: codes((x) => x.module === 'jobs' && ['view', 'moderate'].includes(x.action)) },
  { slug: 'moderator_services', name: 'Модератор послуг', description: 'Модерація карток послуг і відгуків.', permissions: codes((x) => x.module === 'services' && ['view', 'moderate'].includes(x.action)) },
  { slug: 'moderator_regional', name: 'Регіональний модератор', description: 'Модерація всіх напрямків у призначеному регіоні (скоуп region).', permissions: codes((x) => ['jobs', 'gigs', 'services'].includes(x.module) && ['view', 'moderate'].includes(x.action)) },
  { slug: 'operator', name: 'Оператор підтримки', description: 'Звернення та перегляд користувачів без контактів.', permissions: ['identity.user:view', 'messaging.support:view'] },
  { slug: 'finance_admin', name: 'Фінансовий адміністратор', description: 'Тарифи й платежі. Перемикач монетизації — лише окремим критичним правом.', permissions: ['billing.plan:view', 'billing.plan:update'] },
  { slug: 'analyst', name: 'Аналітик', description: 'Лише агрегована статистика, без персональних даних.', permissions: ['stats.dashboard:view', 'ai.usage:view'] },
  { slug: 'designer', name: 'Дизайнер', description: 'Чернетки тем без права публікації.', permissions: ['theming.theme:view', 'theming.theme:update'] },
];

export function permissionsOfRole(slug: string): string[] {
  const r = SYSTEM_ROLES.find((x) => x.slug === slug);
  if (!r) return [];
  return r.permissions === '*' ? PERMISSIONS.map((x) => x.code) : r.permissions;
}
