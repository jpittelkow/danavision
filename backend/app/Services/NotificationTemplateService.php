<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use Database\Seeders\NotificationTemplateSeeder;
use InvalidArgumentException;

class NotificationTemplateService
{
    /**
     * Find an active template by type and channel group.
     */
    public function getByTypeAndChannel(string $type, string $channelGroup): ?NotificationTemplate
    {
        return NotificationTemplate::findByTypeAndChannel($type, $channelGroup);
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
     * Render a template with the given variables (looks up by type and channel, active only).
     *
     * @return array{title: string, body: string}
     * @throws InvalidArgumentException if template not found
     */
    public function render(string $type, string $channelGroup, array $variables = []): array
    {
        $template = $this->getByTypeAndChannel($type, $channelGroup);
        if (!$template) {
            throw new InvalidArgumentException("Notification template not found: {$type} / {$channelGroup}");
        }

        return $this->renderTemplate($template, $variables);
    }

    /**
     * Render a template model with the given variables (for admin preview of any template).
     *
     * @return array{title: string, body: string}
     */
    public function renderTemplate(NotificationTemplate $template, array $variables = []): array
    {
        return $this->renderContent($template->title, $template->body, $variables);
    }

    /**
     * Render raw content (title, body) with variables (for live preview).
     *
     * @return array{title: string, body: string}
     */
    public function renderContent(string $title, string $body, array $variables = []): array
    {
        return [
            'title' => $this->replaceVariables($title, $variables),
            'body' => $this->replaceVariables($body, $variables),
        ];
    }

    /**
     * Get the list of variable names for a template (for admin UI).
     *
     * @return array<int, string>
     */
    public function getAvailableVariables(string $type, string $channelGroup): array
    {
        $template = NotificationTemplate::where('type', $type)
            ->where('channel_group', $channelGroup)
            ->first();

        if (!$template || !is_array($template->variables)) {
            return [];
        }
        return $template->variables;
    }

    /**
     * Get default title/body for a type and channel group (for reset functionality).
     *
     * @return array{title: string, body: string}|null
     */
    public function getDefaultContent(string $type, string $channelGroup): ?array
    {
        return NotificationTemplateSeeder::getDefaultFor($type, $channelGroup);
    }
}
