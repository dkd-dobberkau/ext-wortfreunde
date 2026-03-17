<?php

declare(strict_types=1);

namespace Wortfreunde\WortfreundeConnector\Tests\Unit\Service;

use Wortfreunde\WortfreundeConnector\Service\MarkdownConverterService;
use PHPUnit\Framework\TestCase;

class MarkdownConverterServiceTest extends TestCase
{
    private MarkdownConverterService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new MarkdownConverterService();
    }

    /**
     * @test
     */
    public function convertTransformsBasicMarkdownToHtml(): void
    {
        $markdown = "# Hello World\n\nThis is a **bold** paragraph.";
        $result = $this->subject->convert($markdown);

        self::assertStringContainsString('<h1>Hello World</h1>', $result);
        self::assertStringContainsString('<strong>bold</strong>', $result);
    }

    /**
     * @test
     */
    public function convertHandlesEmptyInput(): void
    {
        self::assertSame('', $this->subject->convert(''));
        self::assertSame('', $this->subject->convert('   '));
    }

    /**
     * @test
     */
    public function convertHandlesTableMarkdown(): void
    {
        $markdown = "| Col1 | Col2 |\n|------|------|\n| A    | B    |";
        $result = $this->subject->convert($markdown);

        self::assertStringContainsString('<table>', $result);
        self::assertStringContainsString('<td>A</td>', $result);
    }

    /**
     * @test
     */
    public function extractTitleFindsH1Heading(): void
    {
        $markdown = "# My Blog Post\n\nSome content here.";
        self::assertSame('My Blog Post', $this->subject->extractTitle($markdown));
    }

    /**
     * @test
     */
    public function extractTitleReturnsNullWithoutH1(): void
    {
        $markdown = "## Not a H1\n\nSome content.";
        self::assertNull($this->subject->extractTitle($markdown));
    }

    /**
     * @test
     */
    public function removeTitleRemovesFirstH1(): void
    {
        $markdown = "# Title\n\nContent after title.\n\n# Second H1";
        $result = $this->subject->removeTitle($markdown);

        self::assertStringNotContainsString('# Title', $result);
        self::assertStringContainsString('Content after title.', $result);
        self::assertStringContainsString('# Second H1', $result);
    }

    /**
     * @test
     */
    public function extractFrontmatterParsesYamlBlock(): void
    {
        $markdown = "---\ntitle: My Post\nauthor: Jane Doe\ndate: 2025-03-08\ntags: tech, typo3\n---\n\n# Content\n\nHello world.";

        $result = $this->subject->extractFrontmatter($markdown);

        self::assertSame('My Post', $result['metadata']['title']);
        self::assertSame('Jane Doe', $result['metadata']['author']);
        self::assertSame('2025-03-08', $result['metadata']['date']);
        self::assertSame('tech, typo3', $result['metadata']['tags']);
        self::assertStringContainsString('# Content', $result['body']);
        self::assertStringNotContainsString('---', $result['body']);
    }

    /**
     * @test
     */
    public function extractFrontmatterHandlesNoFrontmatter(): void
    {
        $markdown = "# Just a title\n\nNo frontmatter here.";

        $result = $this->subject->extractFrontmatter($markdown);

        self::assertEmpty($result['metadata']);
        self::assertSame($markdown, $result['body']);
    }

    /**
     * @test
     */
    public function extractFrontmatterHandlesQuotedValues(): void
    {
        $markdown = "---\ntitle: \"Quoted Title\"\nauthor: 'Single Quoted'\n---\nBody";

        $result = $this->subject->extractFrontmatter($markdown);

        self::assertSame('Quoted Title', $result['metadata']['title']);
        self::assertSame('Single Quoted', $result['metadata']['author']);
    }

    /**
     * @test
     */
    public function convertStripsUnsafeHtmlInput(): void
    {
        $markdown = "Hello <script>alert('xss')</script> world.";
        $result = $this->subject->convert($markdown);

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('Hello', $result);
        self::assertStringContainsString('world.', $result);
    }

    /**
     * @test
     */
    public function convertHandlesStrikethrough(): void
    {
        $markdown = "This is ~~deleted~~ text.";
        $result = $this->subject->convert($markdown);

        self::assertStringContainsString('<del>deleted</del>', $result);
    }
}
