import { Controller, Get } from '@nestjs/common';
import { sql } from 'drizzle-orm';
import { RuntimeConfig } from '../config/runtime-config';

@Controller('health')
export class HealthController {
  constructor(private readonly rc: RuntimeConfig) {}
  @Get()
  async health() {
    if (!this.rc.isInstalled()) return { status: 'ok', installed: false, version: '0.1.0' };
    let db = 'ok';
    try { await this.rc.db().execute(sql`select 1`); } catch { db = 'error'; }
    return { status: db === 'ok' ? 'ok' : 'degraded', installed: true, db, version: '0.1.0', site: this.rc.config().site.name };
  }
}
