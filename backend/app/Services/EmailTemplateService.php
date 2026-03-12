<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use InvalidArgumentException;

class EmailTemplateService
{
    /**
     * Find an active template by key.
     */
    public function getByKey(string $key): ?EmailTemplate
    {
        return EmailTemplate::findByKey($key);
    }

    /**
     * Resolve a dot-notation key from an array (e.g. 'user.name' from ['user' => ['name' => 'John']]).
     */
    private function resolveVariable(array $variables, string $key): string
    {
        $keys = explode('.', $key);
        $value = $variables;
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }
            $value = $value[$segment];
        }
        return (string) $value;
    }

    /**
     * Replace {{variable}} and {{user.name}} placeholders in a string.
     */
    private function replaceVariables(string $content, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function (array $matches) use ($variables) {
            $key = trim($matches[1]);
            return $this->resolveVariable($variables, $key);
        }, $content);
    }

    /**
     * Render a template with the given variables (looks up by key, active only).
     *
     * @throws InvalidArgumentException if template not found
     */
    public function render(string $key, array $variables = []): RenderedEmail
    {
        $template = $this->getByKey($key);
        if (!$template) {
            throw new InvalidArgumentException("Email template not found: {$key}");
        }

        return $this->renderTemplate($template, $variables);
    }

    /**
     * Render a template model with the given variables (for admin preview of any template).
     */
    public function renderTemplate(EmailTemplate $template, array $variables = []): RenderedEmail
    {
        return $this->renderContent(
            $template->subject,
            $template->body_html,
            $template->body_text,
            $variables
        );
    }

    /**
     * Render raw content (subject, body_html, body_text) with variables (for live preview).
     */
    public function renderContent(
        string $subject,
        string $bodyHtml,
        ?string $bodyText,
        array $variables = []
    ): RenderedEmail {
        $subjectRendered = $this->replaceVariables($subject, $variables);
        $htmlRendered = $this->replaceVariables($bodyHtml, $variables);
        $textRendered = $bodyText
            ? $this->replaceVariables($bodyText, $variables)
            : strip_tags($htmlRendered);

        return new RenderedEmail($subjectRendered, $htmlRendered, $textRendered);
    }

    /**
     * Get the list of variable names for a template (for admin UI).
     *
     * @return array<int, string>
     */
    public function getAvailableVariables(string $key): array
    {
        $template = $this->getByKey($key);
        if (!$template || !is_array($template->variables)) {
            return [];
        }
        return $template->variables;
    }

    /**
     * Get default subject/body for a template key (for reset functionality).
     *
     * @return array{subject: string, body_html: string, body_text: string}|null
     */
    public function getDefaultContent(string $key): ?array
    {
        return EmailTemplateSeeder::getDefaultForKey($key);
    }
}
