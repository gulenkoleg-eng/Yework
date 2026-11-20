import { describe, it, expect } from 'vitest';
import { PERMISSIONS, SYSTEM_ROLES, CRITICAL_PERMISSIONS, permissionsOfRole } from './index';

describe('каталог прав', () => {
  it('коди унікальні й мають формат module.resource:action', () => {
    const codes = PERMISSIONS.map((p) => p.code);
    expect(new Set(codes).size).toBe(codes.length);
    for (const c of codes) expect(c).toMatch(/^[a-z_]+\.[a-z_]+:[a-z_]+$/);
  });
  it('жодна роль, крім owner, не містить критичних прав', () => {
    for (const r of SYSTEM_ROLES.filter((r) => r.slug !== 'owner')) {
      for (const c of permissionsOfRole(r.slug)) expect(CRITICAL_PERMISSIONS).not.toContain(c);
    }
  });
  it('усі права ролей існують у каталозі', () => {
    const all = new Set(PERMISSIONS.map((p) => p.code));
    for (const r of SYSTEM_ROLES) for (const c of permissionsOfRole(r.slug)) expect(all.has(c)).toBe(true);
  });
  it('критичні права з ТЗ позначені як критичні', () => {
    for (const c of ['data.export_global:run', 'data.backup:manage_settings', 'access.role:manage_settings', 'billing.monetization:manage_settings'])
      expect(CRITICAL_PERMISSIONS).toContain(c);
  });
});
