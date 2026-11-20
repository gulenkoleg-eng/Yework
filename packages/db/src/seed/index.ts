import { eq } from 'drizzle-orm';
import type { NodePgDatabase } from 'drizzle-orm/node-postgres';
import { PERMISSIONS, SYSTEM_ROLES, permissionsOfRole } from '@yework/permissions';
import * as s from '../schema';
import { SYSTEM_THEMES } from './themes';

type Db = NodePgDatabase<typeof s>;

/** Синхронізує каталог прав і системні ролі. Ідемпотентно: безпечно запускати після кожного оновлення. */
export async function syncPermissions(db: Db) {
  for (const p of PERMISSIONS) {
    await db.insert(s.permissions).values({ code: p.code, module: p.module, resource: p.resource, action: p.action, isCritical: p.critical, description: p.description })
      .onConflictDoUpdate({ target: s.permissions.code, set: { isCritical: p.critical, description: p.description } });
  }
  for (const r of SYSTEM_ROLES) {
    const [role] = await db.insert(s.roles).values({ slug: r.slug, name: r.name, description: r.description, isSystem: true })
      .onConflictDoUpdate({ target: s.roles.slug, set: { name: r.name, description: r.description, isSystem: true } }).returning();
    for (const code of permissionsOfRole(r.slug)) {
      await db.insert(s.rolePermissions).values({ roleId: role.id, permissionCode: code }).onConflictDoNothing();
    }
  }
}

/** 6 стандартних тем + публікація обраної як активної. */
export async function seedThemes(db: Db, activeSlug: string, actorId?: string) {
  let activeVersionId: string | undefined;
  for (const t of SYSTEM_THEMES) {
    const tokens = { base: t.base, light: t.light, dark: t.dark };
    const [theme] = await db.insert(s.themes).values({ slug: t.id, name: t.name, description: t.desc, isSystem: true, defaultMode: t.defaultMode, createdBy: actorId })
      .onConflictDoNothing().returning();
    const row = theme ?? (await db.select().from(s.themes).where(eq(s.themes.slug, t.id)))[0];
    const [ver] = await db.insert(s.themeVersions).values({ themeId: row.id, version: 1, schemaVersion: 1, tokens, comment: 'Стандартна тема', createdBy: actorId })
      .onConflictDoNothing().returning();
    const v = ver ?? (await db.select().from(s.themeVersions).where(eq(s.themeVersions.themeId, row.id)))[0];
    if (t.id === activeSlug) activeVersionId = v.id;
  }
  if (!activeVersionId) throw new Error(`Невідома тема: ${activeSlug}`);
  await db.update(s.themePublications).set({ isActive: false }).where(eq(s.themePublications.isActive, true));
  await db.insert(s.themePublications).values({ themeVersionId: activeVersionId, publishedBy: actorId, isActive: true });
}

/** Стартова географія: Київська обл. → Обухівський р-н → 3 громади запуску. */
export async function seedReference(db: Db) {
  const existing = await db.select().from(s.regions).limit(1);
  if (existing.length) return;
  const [oblast] = await db.insert(s.regions).values({ kind: 'oblast', name: 'Київська область' }).returning();
  const [raion] = await db.insert(s.regions).values({ kind: 'raion', name: 'Обухівський район', parentId: oblast.id }).returning();
  const launch: [string, string, string, string][] = [
    ['Миронівська громада', 'Миронівка', '49.6631', '30.9851'],
    ['Богуславська громада', 'Богуслав', '49.5464', '30.8742'],
    ['Кагарлицька громада', 'Кагарлик', '49.8576', '30.8276'],
  ];
  for (const [community, city, lat, lon] of launch) {
    const [c] = await db.insert(s.communities).values({ regionId: raion.id, name: community, isLaunch: true }).returning();
    await db.insert(s.settlements).values({ communityId: c.id, name: city, kind: 'city', lat, lon });
  }
}
export { SYSTEM_THEMES };

export const JOB_CATEGORIES: [string, string, string][] = [
  ['trade', 'Торгівля та продажі', 'shop'],
  ['agro', 'Сільське господарство', 'leaf'],
  ['construction', 'Будівництво та ремонт', 'hammer'],
  ['transport', 'Водії та логістика', 'truck'],
  ['production', 'Виробництво', 'factory'],
  ['food', 'Кафе, ресторани, кухня', 'cup'],
  ['education', 'Освіта', 'book'],
  ['medicine', 'Медицина та фармація', 'heart'],
  ['office', 'Офіс і адміністрування', 'folder'],
  ['accounting', 'Бухгалтерія та фінанси', 'calc'],
  ['it', 'IT та комп’ютери', 'code'],
  ['beauty', 'Краса та здоров’я', 'spark'],
  ['security', 'Охорона', 'shield'],
  ['cleaning', 'Прибирання та побут', 'home'],
  ['public', 'Державна служба', 'bank'],
  ['other', 'Інше', 'dots'],
];

/** Категорії вакансій. Ідемпотентно. */
export async function seedJobCategories(db: Db) {
  let i = 0;
  for (const [slug, name, icon] of JOB_CATEGORIES) {
    await db.insert(s.categories).values({ slug, name, icon, sort: (i += 10) }).onConflictDoNothing();
  }
}
