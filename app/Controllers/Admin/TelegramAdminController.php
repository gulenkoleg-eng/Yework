<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Telegram;
use App\Core\View;
use RuntimeException;

/** Розділ «Telegram»: кожен працівник прив'язує свій Telegram, адміністратор налаштовує бота. */
final class TelegramAdminController
{
    private function staff(): array
    {
        $u = App::auth()->user();
        if (!$u || !$u['is_staff']) throw new HttpException(403, 'Адмін-панель доступна лише персоналу.');
        return $u;
    }

    public function index(Request $req): Response
    {
        $me = $this->staff();
        $canManage = App::auth()->can('settings.system:manage_settings');
        $cfg = Telegram::config();
        $linkUrl = null;
        $pend = App::session()->get('tg_link');
        if (is_array($pend) && ($pend['until'] ?? 0) > time() && (int) ($pend['uid'] ?? 0) === (int) $me['id']) $linkUrl = (string) $pend['url'];
        $staff = [];
        $info = null;
        if ($canManage) {
            $staff = App::db()->all('SELECT t.*, u.display_name FROM {p}telegram_accounts t JOIN {p}users u ON u.id = t.user_id ORDER BY t.linked_at');
            if (Telegram::ready() && $cfg['webhookAt']) $info = Telegram::webhookInfo();
        }
        return new Response(View::render('admin/telegram', [
            'title' => 'Telegram', 'nav' => 'telegram', 'me' => $me, 'cfg' => $cfg, 'ready' => Telegram::ready(),
            'account' => Telegram::account((int) $me['id']), 'linkUrl' => $linkUrl, 'canManage' => $canManage,
            'canModerate' => App::auth()->can(Telegram::MODERATE), 'staff' => $staff, 'info' => $info,
            'currentUrl' => Telegram::currentSiteUrl($req),
        ], 'admin'));
    }

    /** Прив'язати / відв'язати власний Telegram. */
    public function me(Request $req): Response
    {
        $me = $this->staff();
        $back = Response::redirect(url('/admin/telegram'));
        if ($req->str('do', 10) === 'unlink') {
            Telegram::unlink((int) $me['id'], (int) $me['id']);
            App::session()->set('tg_link', null);
            flash('ok', 'Telegram відв’язано.');
            return $back;
        }
        if (!Telegram::ready()) { flash('bad', 'Бота ще не налаштовано. Попросіть головного адміністратора додати токен у цьому розділі.'); return $back; }
        App::session()->set('tg_link', ['uid' => (int) $me['id'], 'url' => Telegram::createLinkUrl((int) $me['id']), 'until' => time() + 900]);
        return $back;
    }

    /** Налаштування бота (головний і старший адміністратор). */
    public function manage(Request $req): Response
    {
        $me = App::auth()->user();
        $uid = (int) $me['id'];
        $back = Response::redirect(url('/admin/telegram'));
        $do = $req->str('do', 20);
        $cfg = Telegram::config();
        try {
            switch ($do) {
                case 'token':
                    $token = preg_replace('/\s+/', '', $req->str('token', 200)) ?? '';
                    if ($token === '') { flash('bad', 'Вставте токен, який дав @BotFather.'); return $back; }
                    if (!Telegram::validToken($token)) { flash('bad', 'Це не схоже на токен бота. Він виглядає так: 1234567890:AAH…, скопіюйте його з @BotFather повністю.'); return $back; }
                    $bot = Telegram::api('getMe', [], $token);
                    if (!empty($cfg['tokenEnc']) && $cfg['webhookAt']) {
                        // Старий бот більше не надсилатиме запити на сайт.
                        try { Telegram::api('deleteWebhook', []); } catch (RuntimeException) {}
                    }
                    $cfg['tokenEnc'] = Crypto::encrypt($token);
                    $cfg['botUsername'] = (string) ($bot['username'] ?? '');
                    $cfg['botName'] = (string) ($bot['first_name'] ?? '');
                    $cfg['secret'] = Crypto::token(24);
                    $cfg['webhookAt'] = null;
                    Telegram::saveConfig($cfg, $uid);
                    Audit::log('telegram.settings', 'settings', 'telegram', ['bot' => '@' . $cfg['botUsername']]);
                    try {
                        Telegram::connectWebhook(Telegram::currentSiteUrl($req));
                        Audit::log('telegram.webhook', 'settings', 'telegram', ['url' => Telegram::config()['siteUrl']]);
                        flash('ok', 'Бота @' . $cfg['botUsername'] . ' підключено до сайту. Тепер додайте його у вашу групу.');
                    } catch (RuntimeException $e) {
                        flash('bad', 'Токен збережено (бот @' . $cfg['botUsername'] . '), але підключити до сайту не вдалося: ' . $e->getMessage());
                    }
                    return $back;

                case 'settings':
                    $chat = trim($req->str('chat_id', 30));
                    if ($chat !== '' && !Telegram::validChatId($chat)) { flash('bad', 'ID групи — це число, зазвичай з мінусом на початку, наприклад -1001234567890.'); return $back; }
                    if ($chat !== $cfg['chatId']) { $cfg['chatTitle'] = (string) ($cfg['seenChats'][$chat] ?? ''); $cfg['topicModeration'] = $cfg['topicUpdates'] = null; }
                    $cfg['chatId'] = $chat;
                    $cfg['moderation'] = (bool) $req->input('moderation');
                    $cfg['updates'] = (bool) $req->input('updates');
                    Telegram::saveConfig($cfg, $uid);
                    Audit::log('telegram.settings', 'settings', 'telegram', ['chat' => $chat, 'moderation' => $cfg['moderation'], 'updates' => $cfg['updates']]);
                    flash('ok', 'Налаштування збережено.');
                    return $back;

                case 'webhook':
                    if (!Telegram::ready()) { flash('bad', 'Спершу збережіть токен бота.'); return $back; }
                    Telegram::connectWebhook(Telegram::currentSiteUrl($req));
                    Audit::log('telegram.webhook', 'settings', 'telegram', ['url' => Telegram::config()['siteUrl']]);
                    flash('ok', 'Бота підключено до адреси ' . Telegram::config()['siteUrl'] . '.');
                    return $back;

                case 'webhook_off':
                    Telegram::disconnectWebhook();
                    Audit::log('telegram.webhook_off', 'settings', 'telegram');
                    flash('ok', 'Бота відключено від сайту. Кнопки в Telegram не діятимуть, доки не підключите знову.');
                    return $back;

                case 'test':
                    if (!Telegram::groupReady()) { flash('bad', 'Вкажіть ID групи і збережіть налаштування.'); return $back; }
                    $hello = '👋 Перевірка зв’язку з сайту <b>' . e((string) App::setting('site.name', 'YEWORK')) . '</b>. Бот працює. Надіслав: ' . e($me['display_name']) . '.';
                    Telegram::sendToGroup('moderation', $hello . "\nСюди надходитиме модерація.");
                    if ((int) $cfg['topicUpdates'] !== (int) $cfg['topicModeration']) Telegram::sendToGroup('updates', $hello . "\nСюди надходитимуть новини про версії.");
                    flash('ok', 'Тестове повідомлення надіслано в групу.');
                    return $back;

                case 'version':
                    if (!Telegram::groupReady()) { flash('bad', 'Вкажіть ID групи і збережіть налаштування.'); return $back; }
                    Telegram::announceVersion(true);
                    flash('ok', 'Опис версії ' . YW_VERSION . ' надіслано в групу.');
                    return $back;

                case 'clear':
                    [$done, $failed] = Telegram::clearGroupMessages();
                    Audit::log('telegram.cleared', 'settings', 'telegram', ['deleted' => $done, 'failed' => $failed]);
                    if (!$done && !$failed) flash('ok', 'Немає повідомлень бота, які сайт може видалити.');
                    else flash('ok', 'Видалено повідомлень бота: ' . $done . '.' . ($failed ? ' Не вдалося видалити: ' . $failed . ' (Telegram не дає боту видаляти повідомлення, старші за 48 годин, їх можна прибрати вручну).' : ''));
                    return $back;

                case 'topic_reset':
                    $cfg['topicModeration'] = $cfg['topicUpdates'] = null;
                    Telegram::saveConfig($cfg, $uid);
                    Audit::log('telegram.settings', 'settings', 'telegram', ['topics' => 'general']);
                    flash('ok', 'Бот писатиме в загальний чат групи.');
                    return $back;

                case 'unlink':
                    $target = (int) $req->input('user_id', 0);
                    Telegram::unlink($target, $uid);
                    flash('ok', 'Telegram працівника відв’язано.');
                    return $back;

                case 'remove':
                    if (!empty($cfg['tokenEnc'])) { try { Telegram::api('deleteWebhook', []); } catch (RuntimeException) {} }
                    Telegram::saveConfig(['seenChats' => $cfg['seenChats'], 'chatId' => $cfg['chatId'], 'chatTitle' => $cfg['chatTitle']], $uid);
                    Audit::log('telegram.settings', 'settings', 'telegram', ['removed' => true]);
                    flash('ok', 'Токен видалено, бота відключено від сайту.');
                    return $back;
            }
        } catch (RuntimeException $e) {
            flash('bad', $e->getMessage());
            return $back;
        }
        throw new HttpException(400, 'Невідома дія.');
    }
}
