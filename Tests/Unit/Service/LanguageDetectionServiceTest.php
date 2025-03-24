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
}