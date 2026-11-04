<?php

namespace App\Http\Controllers;

use App\Cms\FormDefinition;
use App\Jobs\SendContactMailJob;
use App\Mail\ContactMessageMail;
use App\Models\ContactSubmission;
use App\Models\FormOption;
use App\Models\SiteSetting;
use App\Services\WhatsApp\ContactWhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteFormController extends Controller
{
    public function contact(Request $request, ContactWhatsApp $whatsApp): RedirectResponse|JsonResponse
    {
        $json = $request->expectsJson();

        if (filled($request->input('empresa_site'))) {
            return $this->contactResult('enviado', $json);
        }

        try {
            $data = $request->validate(FormDefinition::validationRules('contact'), FormDefinition::validationMessages('contact'));
        } catch (ValidationException $exception) {
            if ($json) {
                throw $exception;
            }

            return $this->contactRedirect('invalido')
                ->withErrors($exception->errors())
                ->withInput();
        }

        if (is_string($data['nome'] ?? null)) {
            $data['nome'] = ContactSubmission::formatName($data['nome']);
        }

        $submission = ContactSubmission::query()->create(
            FormDefinition::submissionAttributes('contact', $data)
        );

        $this->queueMail($this->contactRecipient((string) ($data['destino'] ?? '')), new ContactMessageMail(
            $submission->name,
            $submission->email,
            $submission->destination,
            $this->mailFields($data),
        ));
        $this->notifyWhatsApp($whatsApp, $submission);

        return $this->contactResult('enviado', $json);
    }

    /**
     * Envio fora da requisição: falha de SMTP ou da fila nunca derruba o formulário.
     */
    private function queueMail(?string $recipient, ContactMessageMail $mail): void
    {
        if ($recipient === null) {
            report(new \RuntimeException('Formulário sem destinatário de e-mail configurado.'));

            return;
        }

        try {
            SendContactMailJob::dispatch($recipient, $mail);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyWhatsApp(ContactWhatsApp $whatsApp, ContactSubmission $submission, string $page = ''): void
    {
        try {
            $whatsApp->notify([
                'nome' => $submission->name,
                'email' => $submission->email,
                'telefone' => $submission->phone,
                'destino' => $submission->destination,
                'mensagem' => $submission->message,
                'pagina' => $page,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function contactResult(string $status, bool $json, int $code = 200): RedirectResponse|JsonResponse
    {
        if ($json) {
            return response()->json(['status' => $status], $code);
        }

        return $this->contactRedirect($status);
    }

    private function contactRedirect(string $status): RedirectResponse
    {
        return redirect()->to(url('/contato').'?status='.rawurlencode($status).'#orcamento');
    }

    private function contactRecipient(string $destination): ?string
    {
        if ($destination !== '') {
            $optionEmail = FormOption::query()
                ->where('form', 'contact')
                ->where('field', 'destino')
                ->where('value', $destination)
                ->value('email');

            if (is_string($optionEmail) && filter_var($optionEmail, FILTER_VALIDATE_EMAIL)) {
                return $optionEmail;
            }
        }

        return $this->siteContactEmail() ?? (
            is_string(config('mail.from.address')) && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
                ? config('mail.from.address')
                : null
        );
    }

    private function siteContactEmail(): ?string
    {
        $email = SiteSetting::query()->value('email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? $email
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function mailFields(array $data): array
    {
        $fields = [];

        foreach (FormDefinition::fields('contact') as $field) {
            $fieldName = $field['name'] ?? '';

            if ($fieldName === '' || $fieldName === 'lgpd' || ($field['type'] ?? '') === 'file') {
                continue;
            }

            $value = $data[$fieldName] ?? '';
            $fields[(string) ($field['label'] ?? $fieldName)] = is_scalar($value) ? (string) $value : '';
        }

        return $fields;
    }
}
