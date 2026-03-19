<?php

declare(strict_types=1);

namespace Wortfreunde\WortfreundeConnector\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts Markdown content to HTML suitable for TYPO3 tt_content bodytext.
 *
 * Uses league/commonmark with GitHub-flavored Markdown support
 * (tables, autolinks, strikethrough, task lists).
 */
class MarkdownConverterService
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TaskListExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Convert Markdown string to HTML
     */
    public function convert(string $markdown): string
    {
        if (empty(trim($markdown))) {
            return '';
        }

        $html = (string)$this->converter->convert($markdown);

        // Replace <input> checkboxes with Unicode symbols for TYPO3 compatibility
        // TYPO3's htmlSanitizer strips <input> tags from bodytext
        $html = str_replace(
            ['<input checked="" disabled="" type="checkbox">', '<input disabled="" type="checkbox">'],
            ['&#9745; ', '&#9744; '],
            $html,
        );

        return $html;
    }

    /**
     * Extract the first H1 heading from Markdown as a title.
     * Returns null if no H1 is found.
     */
    public function extractTitle(string $markdown): ?string
    {
        // Match first line starting with "# "
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Remove the first H1 heading from Markdown content.
     * Useful when the title is stored separately in the header field.
     */
    public function removeTitle(string $markdown): string
    {
        return preg_replace('/^#\s+.+\n*/m', '', $markdown, 1);
    }

    /**
     * Extract frontmatter (YAML-style key: value) from the beginning of the Markdown.
     * Returns an associative array of metadata and the remaining Markdown body.
     *
     * Supports format:
     * ---
     * title: My Post Title
     * date: 2025-03-08
     * author: John Doe
     * tags: tech, typo3
     * ---
     * Actual markdown content...
     */
    public function extractFrontmatter(string $markdown): array
    {
        $metadata = [];
        $body = $markdown;

        // Match YAML frontmatter block
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $markdown, $matches)) {
            $frontmatterBlock = $matches[1];
            $body = $matches[2];

            foreach (explode("\n", $frontmatterBlock) as $line) {
                $line = trim($line);
                if (empty($line) || !str_contains($line, ':')) {
                    continue;
                }
                $colonPos = strpos($line, ':');
                $key = trim(substr($line, 0, $colonPos));
                $value = trim(substr($line, $colonPos + 1));

                // Remove surrounding quotes
                $value = trim($value, '"\'');

                $metadata[$key] = $value;
            }
        }

        return [
            'metadata' => $metadata,
            'body' => $body,
        ];
    }
}
