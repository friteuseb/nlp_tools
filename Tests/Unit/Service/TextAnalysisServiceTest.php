<?php
namespace Cywolf\NlpTools\Tests\Unit\Service;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Cywolf\NlpTools\Service\TextAnalysisService;
use Cywolf\NlpTools\Service\LanguageDetectionService;
use Cywolf\NlpTools\Service\StopWordsFactory;

class TextAnalysisServiceTest extends UnitTestCase
{
    protected TextAnalysisService $subject;
    protected LanguageDetectionService $languageDetector;
    protected StopWordsFactory $stopWordsFactory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->languageDetector = $this->createMock(LanguageDetectionService::class);
        $this->stopWordsFactory = $this->createMock(StopWordsFactory::class);

        // Initialize the service
        $this->subject = new TextAnalysisService(
            $this->languageDetector,
            $this->stopWordsFactory
        );
    }

    /**
     * @test
     */
    public function tokenizeReturnsArray(): void
    {
        $result = $this->subject->tokenize('Sample text');
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function tokenizeReturnsEmptyArrayForEmptyText(): void
    {
        $result = $this->subject->tokenize('');
        $this->assertEquals([], $result);
    }

    /**
     * @test
     */
    public function tokenizeFiltersShortTokens(): void
    {
        $result = $this->subject->tokenize('a an the');
        $this->assertEquals([], $result);
    }

    /**
     * @test
     */
    public function tokenizeHandlesNumbersAndWords(): void
    {
        $result = $this->subject->tokenize('Hello world 123 test');
        $this->assertContains('Hello', $result);
        $this->assertContains('world', $result);
        $this->assertContains('123', $result);
        $this->assertContains('test', $result);
    }

    /**
     * @test
     */
    public function removeStopWordsReturnsString(): void
    {
        // Mock the dependencies
        $this->languageDetector->expects($this->once())
            ->method('detectLanguage')
            ->willReturn('en');

        $mockStopWords = $this->createMock(\Cywolf\NlpTools\StopWords\StopWordsInterface::class);
        $mockStopWords->expects($this->once())
            ->method('getStopWords')
            ->willReturn(['the', 'a', 'an']);

        $this->stopWordsFactory->expects($this->once())
            ->method('getStopWords')
            ->with('en')
            ->willReturn($mockStopWords);

        $result = $this->subject->removeStopWords('The quick brown fox');
        $this->assertIsString($result);
    }

    /**
     * @test
     */
    public function removeStopWordsReturnsEmptyStringForEmptyText(): void
    {
        $result = $this->subject->removeStopWords('');
        $this->assertEquals('', $result);
    }

    /**
     * @test
     */
    public function stemReturnsArray(): void
    {
        $this->languageDetector->expects($this->once())
            ->method('detectLanguage')
            ->willReturn('en');

        $result = $this->subject->stem('running runs runner');
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function stemReturnsEmptyArrayForEmptyText(): void
    {
        $result = $this->subject->stem('');
        $this->assertEquals([], $result);
    }

    /**
     * @test
     */
    public function extractNGramsReturnsArray(): void
    {
        $result = $this->subject->extractNGrams('hello');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('hel', $result);
        $this->assertArrayHasKey('ell', $result);
        $this->assertArrayHasKey('llo', $result);
    }
}