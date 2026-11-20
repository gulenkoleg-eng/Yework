import { Injectable, NestMiddleware, ForbiddenException } from '@nestjs/common';
import type { Request, Response, NextFunction } from 'express';
/**
 * Захист від CSRF: кожен запит, що змінює дані, має містити заголовок X-YEWORK-CSRF.
 * Браузер не може додати власний заголовок у міжсайтовий запит без CORS, а CORS вимкнено.
 * Разом із cookie SameSite=Lax цього достатньо для першого етапу.
 */
@Injectable()
export class CsrfMiddleware implements NestMiddleware {
  use(req: Request, _res: Response, next: NextFunction) {
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(req.method) && req.get('x-yework-csrf') !== '1')
      throw new ForbiddenException({ message: 'Запит відхилено: немає заголовка захисту X-YEWORK-CSRF.' });
    next();
  }
}
