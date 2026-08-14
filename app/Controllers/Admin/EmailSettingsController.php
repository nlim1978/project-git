<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\EmailConfigurationService;
use RuntimeException;
use Throwable;

class EmailSettingsController extends BaseController
{
    private EmailConfigurationService $emailConfig;

    public function __construct()
    {
        $this->emailConfig = new EmailConfigurationService();
    }

    public function index(): string
    {
        try {
            $settings = $this->emailConfig->settings();
        } catch (Throwable $e) {
            return view('admin/email_settings/index', ['title' => 'Email Configuration', 'settings' => null, 'loadError' => $this->safeMessage($e)]);
        }
        return view('admin/email_settings/index', ['title' => 'Email Configuration', 'settings' => $settings, 'loadError' => null]);
    }

    public function update()
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->emailConfig->save($input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/email-settings'))->with('success', 'Email configuration saved.');
    }

    public function test()
    {
        try {
            $this->emailConfig->sendTest($this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/email-settings'))->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/email-settings'))->with('success', 'SMTP test email sent to the configured sender address.');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'smtp_server' => $this->request->getPost('smtp_server'), 'smtp_port' => $this->request->getPost('smtp_port'),
            'encryption_type' => $this->request->getPost('encryption_type'), 'retry_attempts' => $this->request->getPost('retry_attempts'),
            'smtp_username' => $this->request->getPost('smtp_username'), 'smtp_password' => $this->request->getPost('smtp_password'),
            'clear_password' => $this->request->getPost('clear_password') === '1' ? '1' : '0',
            'sender_email' => $this->request->getPost('sender_email'), 'sender_name' => $this->request->getPost('sender_name'),
            'subject_template' => $this->request->getPost('subject_template'), 'body_template' => $this->request->getPost('body_template'),
            'enabled' => $this->request->getPost('enabled') === '1' ? '1' : '0',
        ];
    }

    /** @param array<string, mixed> $input */
    private function valid(array $input): bool
    {
        return $this->validateData($input, [
            'smtp_server' => 'required|max_length[255]',
            'smtp_port' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[65535]',
            'encryption_type' => 'required|in_list[None,SSL/TLS,STARTTLS]',
            'retry_attempts' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
            'smtp_username' => 'permit_empty|max_length[255]', 'smtp_password' => 'permit_empty|max_length[512]',
            'clear_password' => 'required|in_list[0,1]',
            'sender_email' => 'required|valid_email|max_length[254]', 'sender_name' => 'required|max_length[255]',
            'subject_template' => 'required|max_length[1000]', 'body_template' => 'required|max_length[10000]',
            'enabled' => 'required|in_list[0,1]',
        ]);
    }

    private function actorId(): string { return (string) session()->get('auth_user_id'); }
    /** @return array{ip:string,browser:string} */
    private function requestMeta(): array { return ['ip' => (string) $this->request->getIPAddress(), 'browser' => mb_substr((string) $this->request->getUserAgent(), 0, 1000)]; }
    private function safeMessage(Throwable $e): string { return $e instanceof RuntimeException ? $e->getMessage() : 'Email configuration could not be processed. Please review the settings and try again.'; }
}
