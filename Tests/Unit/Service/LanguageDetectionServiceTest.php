<?php
namespace Cywolf\\NlpTools\\Tests\Unit\Service;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Cywolf\\NlpTools\\Service\LanguageDetectionService;

class LanguageDetectionServiceTest extends UnitTestCase
{
    protected LanguageDetectionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new LanguageDetectionService();
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
