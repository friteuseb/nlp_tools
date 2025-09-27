<?php
namespace Cywolf\NlpTools\Tests\Unit\Service;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Cywolf\NlpTools\Service\LanguageDetectionService;
use Cywolf\NlpTools\Service\StopWordsFactory;

class LanguageDetectionServiceTest extends UnitTestCase
{
    protected LanguageDetectionService $subject;
    protected StopWordsFactory $stopWordsFactory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock of StopWordsFactory
        $this->stopWordsFactory = $this->createMock(StopWordsFactory::class);
        
        // Initialize the service with the mock dependency
        $this->subject = new LanguageDetectionService($this->stopWordsFactory);
    }

    /**
     * @test
     */
    public function detectLanguageReturnsString(): void
    {
        $result = $this->subject->detectLanguage('Sample text');
        $this->assertIsString($result);
    }

    /**
     * @test
     * @dataProvider languageDetectionDataProvider
     */
    public function detectLanguageDetectsCorrectLanguage(string $text, string $expected): void
    {
        $result = $this->subject->detectLanguage($text);
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function detectLanguageReturnsDefaultForEmptyText(): void
    {
        $result = $this->subject->detectLanguage('');
        $this->assertEquals('en', $result);
    }

    /**
     * @test
     */
    public function detectLanguageReturnsDefaultForWhitespaceOnlyText(): void
    {
        $result = $this->subject->detectLanguage('   ');
        $this->assertEquals('en', $result);
    }

    public function languageDetectionDataProvider(): array
    {
        return [
            ['Hello world', 'en'],
            ['Bonjour le monde', 'fr'],
            ['Hola mundo', 'es'],
            ['Hallo Welt', 'de'],
            ['The quick brown fox jumps over the lazy dog', 'en'],
            ['Le renard brun rapide saute par-dessus le chien paresseux', 'fr'],
            ['El zorro marrón rápido salta sobre el perro perezoso', 'es'],
            ['Der schnelle braune Fuchs springt über den faulen Hund', 'de'],
        ];
    }
}