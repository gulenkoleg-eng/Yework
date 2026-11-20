import { HttpException, HttpStatus } from '@nestjs/common';
/**
 * Простий обмежувач частоти в пам'яті процесу.
 * Етап 2: достатньо для інсталятора й входу на одному сервері. Далі буде замінено на Redis.
 */
export class RateLimiter {
  private hits = new Map<string, { n: number; until: number }>();
  constructor(private max: number, private windowMs: number) {}
  hit(key: string, message = 'Забагато спроб. Спробуйте пізніше.') {
    const now = Date.now();
    const e = this.hits.get(key);
    if (!e || e.until < now) { this.hits.set(key, { n: 1, until: now + this.windowMs }); return; }
    e.n++;
    if (e.n > this.max) throw new HttpException({ message, retryAfterSec: Math.ceil((e.until - now) / 1000) }, HttpStatus.TOO_MANY_REQUESTS);
  }
  reset(key: string) { this.hits.delete(key); }
}
