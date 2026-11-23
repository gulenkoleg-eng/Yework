<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Приклади оголошень (0.5.14): три майстри в «Послугах» (кондиціонери, відеонагляд, ремонт квартир) і одне резюме.
 * Адміністратор додає їх однією кнопкою, щоб сайт не виглядав порожнім, і так само однією кнопкою видаляє.
 *
 * Усі дані вигадані. Фото робіт і логотипи намальовані спеціально для YEWORK і лежать у database/seed/demo.
 * Кожен приклад має окремого службового користувача без телефону, email і з випадковим паролем: увійти під ним не можна.
 * Що саме створено, записано в налаштуванні demo.content — за цим списком приклади й видаляються, нічого чужого не зачіпаючи.
 * Замовити послугу, побачити номер чи запросити кандидата з прикладу не можна: сайт чесно каже, що це приклад.
 */
final class Demo
{
    private const KEY = 'demo.content';
    private const DIR = '/database/seed/demo/';

    /** Що створено: ['users' => [...], 'masters' => [...], 'resumes' => [...]]. */
    public static function content(): array
    {
        try {
            $c = (array) App::setting(self::KEY, []);
        } catch (\Throwable) {
            return ['users' => [], 'masters' => [], 'resumes' => []];
        }
        $ids = static fn ($v) => array_values(array_map('intval', (array) $v));
        return ['users' => $ids($c['users'] ?? []), 'masters' => $ids($c['masters'] ?? []), 'resumes' => $ids($c['resumes'] ?? [])];
    }

    public static function has(): bool
    {
        $c = self::content();
        return (bool) ($c['users'] || $c['masters'] || $c['resumes']);
    }

    public static function isDemoMaster(int $id): bool { return in_array($id, self::content()['masters'], true); }
    public static function isDemoResume(int $id): bool { return in_array($id, self::content()['resumes'], true); }

    /** Майстри-приклади. Ціни — орієнтовні ціни в Київській області на кінець 2026 року. */
    public const MASTERS = [
        [
            'key' => 'ac', 'login' => 'demo-kondytsionery', 'name' => 'Прохолода: монтаж кондиціонерів', 'cat' => 'conditioners', 'logo' => 'logo-ac',
            'home' => 'Миронівка', 'travel' => ['Богуслав', 'Кагарлик'], 'legal' => 'ФОП', 'pay' => 'cash,card,invoice', 'exp' => 9, 'days' => 18,
            'areas' => 'Миронівка й навколишні села, до 40 км',
            'about' => "Встановлюємо та обслуговуємо кондиціонери в квартирах, приватних будинках, магазинах і офісах. Працюємо з 2017 року, за цей час змонтували понад 600 кондиціонерів.\n\n"
                . "Як працюємо:\n• приїжджаємо на замір і підказуємо, яку потужність брати під вашу кімнату;\n• монтаж за 3–4 години, без пилу: штробимо з будівельним пилососом і прибираємо за собою;\n"
                . "• використовуємо мідну трасу з утепленням, вакуумуємо систему перед запуском;\n• гарантія на монтаж 2 роки, на кондиціонер від виробника 3–5 років.\n\n"
                . "Продаємо кондиціонери за цінами нижчими, ніж у магазинах, бо беремо напряму в дистриб’ютора. Чистка й заправка теж до нас.",
            'prices' => [
                ['service', 'Стандартний монтаж кондиціонера 7–9 тис. BTU, траса до 3 м', 'range', 7000, 8000, 'job', 'Кронштейни, утеплена мідна траса 3 м, дренаж і кабель уже в ціні'],
                ['service', 'Монтаж кондиціонера 12 тис. BTU, траса до 3 м', 'range', 8000, 9000, 'job', null],
                ['service', 'Монтаж кондиціонера 18–24 тис. BTU, траса до 3 м', 'range', 9500, 12000, 'job', null],
                ['service', 'Кожен наступний метр траси', 'fixed', 900, null, 'm', 'Мідь, утеплювач, кабель і дренаж'],
                ['service', 'Штроблення стіни під трасу', 'from', 350, null, 'm', 'З будівельним пилососом'],
                ['service', 'Демонтаж кондиціонера зі збереженням фреону', 'range', 1500, 2500, 'job', null],
                ['service', 'Чистка й сервісне обслуговування', 'range', 1200, 1800, 'pcs', 'Внутрішній і зовнішній блок, антибактеріальна обробка'],
                ['service', 'Дозаправка фреоном R32 / R410', 'from', 1500, null, 'job', null],
                ['service', 'Монтаж з автовишки (вище 2 поверху)', 'deal', null, null, 'job', 'Залежить від висоти й під’їзду'],
                ['goods', 'Кондиціонер інверторний 9 тис. BTU (до 25 м²)', 'from', 16500, null, 'pcs', null],
                ['goods', 'Кондиціонер інверторний 12 тис. BTU (до 35 м²)', 'from', 19800, null, 'pcs', null],
                ['goods', 'Кондиціонер інверторний 18 тис. BTU (до 50 м²)', 'from', 29500, null, 'pcs', null],
                ['goods', 'Кронштейни для зовнішнього блоку', 'fixed', 650, null, 'pcs', null],
                ['goods', 'Дренажна помпа', 'fixed', 1800, null, 'pcs', 'Коли нема куди вивести дренаж самопливом'],
            ],
            'works' => [
                ['Квартира в Миронівці: кондиціонер у спальню', 'Кондиціонер 9 тис. BTU над вікном у спальні, зовнішній блок на фасаді панельного будинку під вікном. Траса 3 м, вклалися в стандартний монтаж, робота зайняла 3 години.', 'Миронівка', 'Липень 2026', ['ac-1a', 'ac-1b']],
                ['Приватний будинок у Богуславі', 'Кондиціонер 12 тис. BTU у вітальню. Зовнішній блок на землі на опорі, траса 6 м у білому коробі по фасаду, щоб не псувати вигляд будинку.', 'Богуслав', 'Червень 2026', ['ac-2a', 'ac-2b']],
                ['Офіс у Кагарлику: 2 кондиціонери й обслуговування', 'Два кондиціонери в офісне приміщення на 6 робочих місць. Восени повернулися на сервіс: зняли й промили фільтри та теплообмінник.', 'Кагарлик', 'Травень 2026', ['ac-3a', 'ac-3b']],
            ],
        ],
        [
            'key' => 'cctv', 'login' => 'demo-videonahliad', 'name' => 'Пильне око: відеонагляд і сигналізація', 'cat' => 'cctv', 'logo' => 'logo-cam',
            'home' => 'Богуслав', 'travel' => ['Миронівка', 'Кагарлик'], 'legal' => 'ТОВ', 'pay' => 'cash,card,invoice', 'exp' => 7, 'days' => 9,
            'areas' => 'Богуслав, Миронівка, Кагарлик і села громад',
            'about' => "Встановлюємо відеонагляд для приватних будинків, магазинів, складів і ферм. Підбираємо камери під завдання: щоб було видно обличчя біля воріт і номер авто, а не просто «щось рухається».\n\n"
                . "Що отримуєте:\n• перегляд з телефону з будь-якого місця, налаштуємо застосунок і покажемо, як ним користуватися;\n• запис на 2–4 тижні назад;\n"
                . "• акуратний монтаж: кабель у гофрі або кабель-каналі, без звисаючих дротів;\n• гарантія 2 роки на обладнання й роботу.\n\n"
                . "Працюємо з ПДВ, даємо рахунок і акти для підприємств. Безкоштовний виїзд на огляд у Богуславі.",
            'prices' => [
                ['service', 'Встановлення камери (вулична або внутрішня)', 'range', 900, 1300, 'pcs', 'Монтаж, підключення й налаштування кута огляду'],
                ['service', 'Комплект із 4 камер під ключ для приватного будинку (роботи)', 'range', 6000, 8000, 'job', 'До 60 м кабелю'],
                ['service', 'Прокладання кабелю в гофрі або кабель-каналі', 'from', 35, null, 'm', null],
                ['service', 'Налаштування реєстратора й перегляду з телефону', 'fixed', 800, null, 'job', null],
                ['service', 'Встановлення Wi‑Fi камери', 'fixed', 600, null, 'pcs', null],
                ['service', 'Монтаж бездротової сигналізації (стартовий комплект)', 'from', 2500, null, 'job', null],
                ['service', 'Діагностика й обслуговування системи', 'fixed', 500, null, 'visit', null],
                ['goods', 'IP-камера 4 Мп вулична з ІЧ-підсвіткою', 'from', 2400, null, 'pcs', 'Бачить уночі до 30 м'],
                ['goods', 'Wi‑Fi камера поворотна 3 Мп', 'from', 1500, null, 'pcs', null],
                ['goods', 'Відеореєстратор на 8 камер', 'from', 3900, null, 'pcs', null],
                ['goods', 'Жорсткий диск 2 ТБ для запису', 'from', 3200, null, 'pcs', null],
                ['goods', 'PoE-комутатор на 4 камери', 'fixed', 1700, null, 'pcs', null],
                ['goods', 'Комплект: 4 камери + реєстратор + диск 1 ТБ', 'from', 14900, null, 'pcs', 'Обладнання без робіт'],
            ],
            'works' => [
                ['Приватний будинок у Миронівці: 4 камери', 'Чотири вуличні камери 4 Мп: двір, ворота, гараж і вулиця. Реєстратор у будинку, перегляд з телефону для всієї родини, запис на 3 тижні.', 'Миронівка', 'Вересень 2026', ['cam-1a', 'cam-1b']],
                ['Магазин у Богуславі: 6 камер і реєстратор', 'Купольні камери в торговій залі, на касі й на складі, дві вуличні біля входу. Реєстратор і комутатор у підсобці, власник бачить усе з телефону.', 'Богуслав', 'Серпень 2026', ['cam-2a', 'cam-2b']],
                ['Склад у Кагарлику: нічна охорона', 'Дві камери з ІЧ-підсвіткою над воротами складу і прожектор з датчиком руху. Уночі приходять сповіщення на телефон охоронця.', 'Кагарлик', 'Жовтень 2026', ['cam-3a', 'cam-3b']],
            ],
        ],
        [
            'key' => 'repair', 'login' => 'demo-remont', 'name' => 'Затишна оселя: ремонт квартир під ключ', 'cat' => 'repair', 'logo' => 'logo-rep',
            'home' => 'Миронівка', 'travel' => ['Богуслав', 'Кагарлик'], 'legal' => 'ФОП', 'pay' => 'cash,card', 'exp' => 12, 'days' => 30,
            'areas' => 'Миронівка, Богуслав, Кагарлик',
            'about' => "Бригада з 4 майстрів: робимо ремонт квартир і будинків від стяжки до останнього плінтуса. Можна замовити все під ключ або окремі роботи: плитку, гіпсокартон, шпаклювання, підлогу.\n\n"
                . "Як працюємо:\n• безкоштовно приїжджаємо на замір і складаємо кошторис з цінами на кожну роботу;\n• укладаємо договір із термінами, ціна після підписання не змінюється;\n"
                . "• допомагаємо вибрати й привезти матеріали, чеки віддаємо вам;\n• щотижня надсилаємо фото, як іде робота, якщо ви не можете приїхати.\n\n"
                . "Гарантія на роботи 1 рік. Ціни в прайсі за роботу, без матеріалів.",
            'prices' => [
                ['service', 'Косметичний ремонт під ключ', 'from', 2500, null, 'm2', 'За м² площі підлоги'],
                ['service', 'Капітальний ремонт квартири під ключ', 'from', 5500, null, 'm2', 'За м² площі підлоги, разом з електрикою й сантехнікою'],
                ['service', 'Шпаклювання стін під фарбування', 'range', 220, 300, 'm2', null],
                ['service', 'Фарбування стін і стелі', 'range', 120, 180, 'm2', 'У 2 шари'],
                ['service', 'Поклейка шпалер', 'range', 140, 220, 'm2', null],
                ['service', 'Укладання плитки', 'range', 650, 900, 'm2', 'Великий формат дорожче'],
                ['service', 'Укладання ламінату', 'range', 200, 280, 'm2', 'З підкладкою'],
                ['service', 'Стяжка підлоги', 'from', 350, null, 'm2', null],
                ['service', 'Стеля з гіпсокартону в один рівень', 'from', 450, null, 'm2', null],
                ['service', 'Монтаж точкового світильника', 'fixed', 150, null, 'pcs', null],
                ['service', 'Демонтаж старого покриття й вивезення сміття', 'deal', null, null, 'job', null],
                ['goods', 'Ламінат 32 клас', 'from', 420, null, 'm2', 'Привеземо під замовлення'],
                ['goods', 'Плитка для ванної кімнати', 'from', 480, null, 'm2', null],
            ],
            'works' => [
                ['Двокімнатна квартира в Миронівці під ключ', 'Ремонт за 7 тижнів: вирівняли стіни, нова електрика, ламінат, фарбування, акцентна стіна з рейками у вітальні, кухня з плиткою на кухонному фартуху.', 'Миронівка', 'Вересень 2026', ['rep-1a', 'rep-1b']],
                ['Ванна кімната в Богуславі', 'Повністю нова ванна: демонтаж старої плитки, гідроізоляція, плитка на стіни й підлогу, душова кабіна, підвісний унітаз і тумба з дзеркалом.', 'Богуслав', 'Липень 2026', ['rep-2a', 'rep-2b']],
                ['Будинок у Кагарлику: стелі й спальня', 'Дворівнева стеля з гіпсокартону з підсвіткою і точковими світильниками, шпаклювання й фарбування, у спальні шпалери й ламінат.', 'Кагарлик', 'Червень 2026', ['rep-3a', 'rep-3b']],
            ],
        ],
    ];

    /** Резюме-приклад: вигаданий кандидат. Лише поля, які є в резюме сайту; нічого про військовий облік, сім’ю чи здоров’я. */
    public const RESUME = [
        'login' => 'demo-rezume', 'full_name' => 'Ткачук Максим Ігорович', 'birth_year' => 1993, 'home' => 'Миронівка', 'cat' => 'production', 'days' => 5,
        'title' => 'Оператор виробничої лінії', 'employment' => 'full', 'salary_from' => 25000, 'driving' => null,
        'experience' => [
            ['place' => 'Харчове підприємство, Миронівський район', 'position' => 'Оператор лінії фасування', 'from' => '2021', 'to' => 'тепер',
                'duties' => "Налаштування й запуск лінії фасування, контроль ваги й маркування, заміна плівки й етикеток.\nВедення змінного журналу, прибирання робочої зони за санітарними нормами. Працюю позмінно."],
            ['place' => 'Склад будматеріалів, м. Богуслав', 'position' => 'Комплектувальник', 'from' => '2017', 'to' => '2021',
                'duties' => 'Приймання й відвантаження товару, комплектування замовлень за накладними, облік залишків у програмі.'],
        ],
        'education' => [
            ['place' => 'Білоцерківський національний аграрний університет', 'specialty' => 'Агрономія (заочно)', 'year' => '2016'],
            ['place' => 'Професійний ліцей', 'specialty' => 'Слюсар-ремонтник', 'year' => '2011'],
        ],
        'skills' => "Впевнено працюю з комп’ютером: Excel, облікові програми\nНалаштування й дрібний ремонт обладнання лінії\nЧитання технологічних карт і креслень\nВідповідальність, акуратність, пунктуальність",
        'about' => "Шукаю стабільну роботу на виробництві в Миронівці або поруч. Готовий навчатися новому обладнанню, працювати позмінно й понаднормово, коли це потрібно.\n"
            . "Готовий почати протягом місяця.",
    ];

    /** Створює приклади, яких ще немає. Повертає, скільки створено. */
    public static function add(int $actorId): int
    {
        $db = App::db();
        $c = self::content();
        $have = [];
        foreach ($c['users'] as $uid) { $l = $db->value('SELECT login FROM {p}users WHERE id = ? AND deleted_at IS NULL', [$uid]); if ($l) $have[] = (string) $l; }
        $made = 0;
        $files = []; // для відкату, якщо щось зламається посередині
        try {
            foreach (self::MASTERS as $d) {
                if (in_array($d['login'], $have, true)) continue;
                [$uid, $mid] = self::addMaster($d, $actorId, $files);
                $c['users'][] = $uid; $c['masters'][] = $mid; $made++;
                App::setSetting(self::KEY, $c, $actorId);
            }
            if (!in_array(self::RESUME['login'], $have, true) && self::tableExists('resumes')) {
                [$uid, $rid] = self::addResume(self::RESUME);
                $c['users'][] = $uid; $c['resumes'][] = $rid; $made++;
                App::setSetting(self::KEY, $c, $actorId);
            }
        } catch (\Throwable $e) {
            foreach ($files as [$kind, $f]) Media::delete($kind, $f);
            throw $e;
        }
        if ($made) Audit::log('demo.added', 'settings', self::KEY, ['created' => $made]);
        return $made;
    }

    /** Видаляє все, що створено кнопкою «Додати приклади», разом із фото. Повертає, скільки прикладів видалено. */
    public static function remove(int $actorId): int
    {
        $db = App::db();
        $c = self::content();
        $n = 0;
        foreach ($c['masters'] as $mid) {
            $m = $db->one('SELECT id, photo FROM {p}masters WHERE id = ?', [$mid]);
            if (!$m) continue;
            foreach ($db->all('SELECT f.file FROM {p}master_work_photos f JOIN {p}master_works w ON w.id = f.work_id WHERE w.master_id = ?', [$mid]) as $p) {
                Media::delete('work', $p['file']); Media::delete('workthumb', $p['file']);
            }
            Media::delete('master', $m['photo']);
            $db->tx(function (Db $db) use ($mid) {
                $db->run('DELETE FROM {p}master_reviews WHERE master_id = ?', [$mid]);
                $db->run('DELETE FROM {p}service_orders WHERE master_id = ?', [$mid]);
                $db->run('DELETE FROM {p}masters WHERE id = ?', [$mid]); // прайс, об'єкти, фото й місця виїзду видаляються разом із ним
            });
            $n++;
        }
        foreach ($c['resumes'] as $rid) {
            if ($db->run('DELETE FROM {p}resumes WHERE id = ?', [$rid])->rowCount()) $n++;
        }
        foreach ($c['users'] as $uid) {
            try {
                $db->run('DELETE FROM {p}users WHERE id = ?', [$uid]); // профіль і сесії видаляються разом із ним
            } catch (\PDOException) {
                // на користувача ще щось посилається — ховаємо його, як видаленого
                $db->update('users', ['status' => 'blocked', 'deleted_at' => Db::now()], 'id = ?', [$uid]);
            }
        }
        $db->run('DELETE FROM {p}settings WHERE `key` = ?', [self::KEY]);
        Audit::log('demo.removed', 'settings', self::KEY, ['removed' => $n]);
        return $n;
    }

    private static function tableExists(string $t): bool
    {
        try { App::db()->value('SELECT 1 FROM {p}' . $t . ' LIMIT 1'); return true; } catch (\PDOException) { return false; }
    }

    private static function newUser(string $login, string $name): int
    {
        $db = App::db();
        if ($db->value('SELECT 1 FROM {p}users WHERE login = ?', [$login])) $login = substr($login, 0, 20) . '-' . bin2hex(random_bytes(3));
        return $db->insert('users', ['login' => $login, 'display_name' => mb_substr($name, 0, 80), 'password_hash' => Crypto::hashPassword(bin2hex(random_bytes(24)))]);
    }

    private static function settlement(string $name): ?int
    {
        $id = App::db()->value('SELECT id FROM {p}settlements WHERE name = ? ORDER BY id LIMIT 1', [$name]);
        return $id ? (int) $id : null;
    }

    private static function category(string $slug, array $fallbackSlugs = ['other']): ?int
    {
        $db = App::db();
        foreach ([$slug, ...$fallbackSlugs] as $s) {
            $id = $db->value('SELECT id FROM {p}service_categories WHERE slug = ? AND is_active = 1', [$s]);
            if ($id) return (int) $id;
        }
        $id = $db->value('SELECT id FROM {p}service_categories WHERE is_active = 1 ORDER BY sort, id LIMIT 1');
        return $id ? (int) $id : null;
    }

    private static function addMaster(array $d, int $actorId, array &$files): array
    {
        $db = App::db();
        $cat = self::category($d['cat']);
        if (!$cat) throw new \RuntimeException('Немає жодної категорії послуг. Додайте категорію в адмінці → «Категорії послуг».');
        $home = self::settlement($d['home']);
        $travel = array_values(array_filter(array_map([self::class, 'settlement'], $d['travel'])));
        $publishedAt = date('Y-m-d H:i:s', time() - $d['days'] * 86400);

        $photo = Media::storePath('master', YW_ROOT . self::DIR . $d['logo'] . '.jpg', 'm', 'photo');
        $files[] = ['master', $photo];
        $uid = self::newUser($d['login'], $d['name']);
        $mid = $db->insert('masters', [
            'user_id' => $uid, 'name' => $d['name'], 'category_id' => $cat, 'settlement_id' => $home, 'areas' => $d['areas'],
            'travel_all' => $home && count($travel) === count($d['travel']) ? 0 : 1,
            'experience_years' => $d['exp'], 'about' => $d['about'], 'contact_phone' => null, 'photo' => $photo,
            'legal_form' => $d['legal'], 'payments' => $d['pay'], 'period_months' => 12, 'show_to' => Services::showTo(12, substr($publishedAt, 0, 10)),
            'status' => 'published', 'needs_review' => 0, 'submitted_at' => $publishedAt, 'moderated_by' => $actorId, 'moderated_at' => $publishedAt,
            'published_at' => $publishedAt, 'created_at' => $publishedAt, 'updated_at' => $publishedAt,
        ]);
        foreach ($travel as $sid) if ($sid !== $home) $db->run('INSERT IGNORE INTO {p}master_areas (master_id, settlement_id) VALUES (?, ?)', [$mid, $sid]);
        foreach ($d['prices'] as $i => [$kind, $title, $type, $from, $to, $unit, $note]) {
            $db->insert('master_prices', ['master_id' => $mid, 'kind' => $kind, 'title' => $title, 'price_type' => $type, 'price_from' => $from, 'price_to' => $to, 'unit' => $unit, 'note' => $note, 'sort' => $i * 10]);
        }
        foreach ($d['works'] as $i => [$title, $desc, $place, $when, $pics]) {
            $wid = $db->insert('master_works', ['master_id' => $mid, 'title' => $title, 'description' => $desc, 'place' => $place, 'done_on' => $when, 'sort' => $i * 10]);
            foreach ($pics as $k => $pic) {
                $file = Media::storePath('work', YW_ROOT . self::DIR . $pic . '.jpg', 'w' . $mid, 'photos');
                $files[] = ['work', $file];
                Media::storePath('workthumb', Media::dir('work') . '/' . $file, 'w' . $mid, 'photos', pathinfo($file, PATHINFO_FILENAME));
                $files[] = ['workthumb', $file];
                $db->insert('master_work_photos', ['work_id' => $wid, 'file' => $file, 'sort' => $k]);
            }
        }
        return [$uid, $mid];
    }

    private static function addResume(array $d): array
    {
        $db = App::db();
        $uid = self::newUser($d['login'], $d['full_name']);
        $publishedAt = date('Y-m-d H:i:s', time() - $d['days'] * 86400);
        Resumes::saveProfile($uid, ['full_name' => $d['full_name'], 'birth_year' => $d['birth_year'], 'settlement_id' => self::settlement($d['home']),
            'show_photo' => 0, 'show_contacts' => 0, 'photo_visibility' => 'nobody', 'phone_visibility' => 'nobody']);
        $cat = $db->value('SELECT id FROM {p}job_categories WHERE slug = ? AND is_active = 1', [$d['cat']]);
        $rid = $db->insert('resumes', [
            'user_id' => $uid, 'title' => $d['title'], 'category_id' => $cat ? (int) $cat : null, 'employment' => $d['employment'], 'salary_from' => $d['salary_from'],
            'experience' => json_encode($d['experience'], JSON_UNESCAPED_UNICODE), 'education' => json_encode($d['education'], JSON_UNESCAPED_UNICODE),
            'skills' => $d['skills'], 'driving' => $d['driving'], 'about' => $d['about'], 'no_experience' => 0,
            'status' => 'published', 'submitted_at' => $publishedAt, 'moderated_at' => $publishedAt, 'published_at' => $publishedAt, 'created_at' => $publishedAt, 'updated_at' => $publishedAt,
        ]);
        return [$uid, $rid];
    }
}
