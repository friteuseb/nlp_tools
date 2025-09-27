<?php
namespace Cywolf\NlpTools\Service;

use Cywolf\NlpTools\StopWords\StopWordsInterface;
use Cywolf\NlpTools\StopWords\FrenchStopWords;
use Cywolf\NlpTools\StopWords\EnglishStopWords;
use Cywolf\NlpTools\StopWords\GermanStopWords;
use Cywolf\NlpTools\StopWords\SpanishStopWords;
use Cywolf\NlpTools\StopWords\ItalianStopWords;
use Cywolf\NlpTools\StopWords\PortugueseStopWords;
use TYPO3\CMS\Core\SingletonInterface;

class StopWordsFactory implements SingletonInterface
{
    private array $instances = [];

    public function getStopWords(string $language): StopWordsInterface
    {
        if (isset($this->instances[$language])) {
            return $this->instances[$language];
        }

        $this->instances[$language] = match($language) {
            'fr' => new FrenchStopWords(),
            'en' => new EnglishStopWords(),
            'de' => new GermanStopWords(),
            'es' => new SpanishStopWords(),
            'it' => new ItalianStopWords(),
            'pt' => new PortugueseStopWords(),
            default => new EnglishStopWords(), // Fallback to English
        };

        return $this->instances[$language];
    }
}