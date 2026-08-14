<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\TelegramConfigurationService;
use RuntimeException;
use Throwable;

class TelegramSettingsController extends BaseController
{
    private TelegramConfigurationService $telegramConfig;

    public function __construct()
    {
        $this->telegramConfig = new TelegramConfigurationService();
    }

    public function index(): string
    {
        try {
            $settings = $this->telegramConfig->settings();
        } catch (Throwable $e) {
            return view('admin/telegram_settings/index', ['title' => 'Telegram Configuration', 'settings' => null, 'loadError' => $this->safeMessage($e)]);
        }

        return view('admin/telegram_settings/index', ['title' => 'Telegram Configuration', 'settings' => $settings, 'loadError' => null]);
    }

    public function update()
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $this->telegramConfig->save($input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('admin/telegram-settings'))->with('success', 'Telegram configuration saved.');
    }

    public function test()
    {
        try {
            $bot = $this->telegramConfig->testBot($this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/telegram-settings'))->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('admin/telegram-settings'))->with('success', 'Telegram bot verified: ' . $bot['username'] . '.');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'bot_username' => $this->request->getPost('bot_username'),
            'bot_token' => $this->request->getPost('bot_token'),
            'clear_token' => $this->request->getPost('clear_token') === '1' ? '1' : '0',
            'retry_attempts' => $this->request->getPost('retry_attempts'),
            'enabled' => $this->request->getPost('enabled') === '1' ? '1' : '0',
            'notify_initial_assignment' => $this->request->getPost('notify_initial_assignment') === '1' ? '1' : '0',
            'notify_routing' => $this->request->getPost('notify_routing') === '1' ? '1' : '0',
            'notify_reassignment' => $this->request->getPost('notify_reassignment') === '1' ? '1' : '0',
        ];
    }

    /** @param array<string, mixed> $input */
    private function valid(array $input): bool
    {
        return $this->validateData($input, [
            'bot_username' => 'required|max_length[100]',
            'bot_token' => 'permit_empty|max_length[512]',
            'clear_token' => 'required|in_list[0,1]',
            'retry_attempts' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
            'enabled' => 'required|in_list[0,1]',
            'notify_initial_assignment' => 'required|in_list[0,1]',
            'notify_routing' => 'required|in_list[0,1]',
            'notify_reassignment' => 'required|in_list[0,1]',
        ]);
    }

    private function actorId(): string { return (string) session()->get('auth_user_id'); }
    /** @return array{ip:string,browser:string} */
    private function requestMeta(): array { return ['ip' => (string) $this->request->getIPAddress(), 'browser' => mb_substr((string) $this->request->getUserAgent(), 0, 1000)]; }
    private function safeMessage(Throwable $e): string { return $e instanceof RuntimeException ? $e->getMessage() : 'Telegram configuration could not be processed. Please review the settings and try again.'; }
}
