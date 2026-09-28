<?php

namespace App\Trading;

/**
 * Presentation helpers for AI agent reasoning.
 *
 * Every helper escapes its output for HTML. The trading dashboard uses
 * these to render decision rationale, confidence, thesis, catalysts,
 * risks, and feedback-loop notes without duplicating escaping logic.
 */
final class AgentReasonPresenter
{
    /**
     * Escape any scalar for HTML output.
     */
    public static function escape(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Short one-line summary: the reason plus a truncated rationale.
     */
    public static function summary(?string $reason, ?string $rationale, int $maxLength = 140): string
    {
        $text = trim((string) ($reason ?? ''));
        $extra = trim((string) ($rationale ?? ''));
        if ($extra !== '') {
            $text .= ($text === '' ? '' : ' — ') . $extra;
        }
        if ($maxLength > 1 && mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength - 1) . '…';
        }

        return self::escape($text);
    }

    /**
     * Full rationale paragraph.
     */
    public static function rationale(?string $rationale): string
    {
        $text = trim((string) ($rationale ?? ''));

        return self::escape($text === '' ? 'No rationale recorded.' : $text);
    }

    /**
     * CSS class for the confidence badge.
     */
    public static function confidenceBadge(?string $confidence): string
    {
        $value = self::confidenceFraction($confidence);
        if ($value === null) {
            return 'badge bg-secondary';
        }
        if ($value >= 0.75) {
            return 'badge bg-success';
        }
        if ($value >= 0.5) {
            return 'badge bg-warning text-dark';
        }

        return 'badge bg-danger';
    }

    /**
     * Human label for a confidence value, e.g. "82%".
     */
    public static function confidenceLabel(?string $confidence): string
    {
        if ($confidence === null || trim($confidence) === '' || !is_numeric($confidence)) {
            return 'n/a';
        }
        $value = (float) $confidence;
        $percent = $value > 1 ? $value : $value * 100;

        return (string) (int) round($percent) . '%';
    }

    /**
     * Render a string list as <li> items (escaped).
     *
     * @param list<mixed> $items
     */
    public static function listItems(array $items): string
    {
        if ($items === []) {
            return '<li class="empty-note">None recorded.</li>';
        }
        $html = '';
        foreach ($items as $item) {
            $text = is_scalar($item) ? (string) $item : (string) json_encode($item);
            $html .= '<li>' . self::escape($text) . '</li>';
        }

        return $html;
    }

    /**
     * Callout box for the feedback-loop rationale.
     */
    public static function feedbackCallout(?string $feedback): string
    {
        $text = trim((string) ($feedback ?? ''));
        if ($text === '') {
            return '';
        }

        return '<div class="feedback-callout"><span class="feedback-label">Feedback loop</span><p>'
            . self::escape($text) . '</p></div>';
    }

    /**
     * Normalize a confidence value to a 0-1 fraction.
     */
    private static function confidenceFraction(?string $confidence): ?float
    {
        if ($confidence === null || trim($confidence) === '' || !is_numeric($confidence)) {
            return null;
        }
        $value = (float) $confidence;

        return $value > 1 ? $value / 100 : $value;
    }
}
