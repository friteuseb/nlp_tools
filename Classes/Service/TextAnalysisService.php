<?php
namespace Cywolf\NlpTools\Service;

use TYPO3\CMS\Core\SingletonInterface;
use Wamania\Snowball\French;
use Wamania\Snowball\English;
use Wamania\Snowball\German;
use Wamania\Snowball\Spanish;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

class TextAnalysisService implements SingletonInterface
{
    private LanguageDetectionService $languageDetector;
    private StopWordsFactory $stopWordsFactory;
    private array $stemmers = [];
    private ?FrontendInterface $cache;

    public function __construct(
        LanguageDetectionService $languageDetector,
        StopWordsFactory $stopWordsFactory,
        ?FrontendInterface $cache = null
    ) {
        $this->languageDetector = $languageDetector;
        $this->stopWordsFactory = $stopWordsFactory;
        $this->cache = $cache;
    }

    /**
     * Sets a cache instance
     * 
     * @param FrontendInterface $cache
     * @return void
     */
    public function setCache(FrontendInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Removes stop words from a text
     *
     * @param string $text Text to process
     * @param string|null $language Language code (auto-detected if null)
     * @return string Text without stop words
     */
    public function removeStopWords(string $text, ?string $language = null): string
    {
        // Detect language if not specified
        $language = $language ?? $this->languageDetector->detectLanguage($text);
        
        // Get stop words for the language
        $stopWords = $this->stopWordsFactory->getStopWords($language);
        $stopWordsList = $stopWords->getStopWords();
        
        // Tokenize the text
        $words = $this->tokenize($text);
        
        // Filter stop words
        $filteredWords = array_filter($words, function($word) use ($stopWordsList) {
            return !in_array(mb_strtolower($word), $stopWordsList, true);
        });
        
        // Rebuild the text
        return implode(' ', $filteredWords);
    }
    
    /**
     * Extract n-grams from a text
     *
     * @param string $text Input text
     * @param int $n Size of n-grams (default: 3)
     * @return array Array of n-grams with their frequencies
     */
    public function extractNGrams(string $text, int $n = 3): array
    {
        $ngrams = [];
        $text = mb_strtolower($text);
        
        // Add padding for beginning and end
        $padding = str_repeat('_', $n - 1);
        $text = $padding . $text . $padding;
        
        // Extract n-grams
        for ($i = 0; $i <= mb_strlen($text) - $n; $i++) {
            $ngram = mb_substr($text, $i, $n);
            $ngrams[$ngram] = ($ngrams[$ngram] ?? 0) + 1;
        }
        
        return $ngrams;
    }
    
    private function getStemmer(string $language): ?object
    {
        if (isset($this->stemmers[$language])) {
            return $this->stemmers[$language];
        }

        $this->stemmers[$language] = match($language) {
            'fr' => new French(),
            'en' => new English(),
            'de' => new German(),
            'es' => new Spanish(),
            default => null
        };

        return $this->stemmers[$language];
    }

    /**
     * Stem a text (reduce words to their root form)
     *
     * @param string $text Text to stem
     * @param string|null $language Language code (auto-detected if null)
     * @return string Stemmed text
     */
    public function stem(string $text, ?string $language = null): string
    {
        $language = $language ?? $this->languageDetector->detectLanguage($text);
        $words = $this->tokenize($text);
        
        // Check if we have a stemmer for this language
        $stemmer = $this->getStemmer($language);
        if (!$stemmer) {
            return $text; // Return original text if no stemmer
        }

        $stemmedWords = array_map(function($word) use ($stemmer, $language) {
            // Use cache if available
            if ($this->cache) {
                $cacheIdentifier = 'stem_' . $language . '_' . md5($word);
                $stemmedWord = $this->cache->get($cacheIdentifier);
                if ($stemmedWord === false) {
                    $stemmedWord = $stemmer->stem($word);
                    $this->cache->set($cacheIdentifier, $stemmedWord);
                }
                return $stemmedWord;
            }
            
            return $stemmer->stem($word);
        }, $words);
        
        return implode(' ', $stemmedWords);
    }

    /**
     * Tokenize a text into words
     *
     * @param string $text Text to tokenize
     * @return array Array of tokens/words
     */
    public function tokenize(string $text): array
    {
        // Pre-processing of text
        $text = $this->cleanText($text);
        
        // Advanced tokenization with Unicode support
        $tokens = preg_split('/[\s,\.!?\(\)\[\]{}"\']+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Check if preg_split failed and returned false
        if ($tokens === false) {
            return []; // Return empty array instead of false
        }
        
        return array_filter($tokens, function($token) {
            return mb_strlen($token) > 1; // Ignore tokens that are too short
        });
    }

    private function cleanText(string $text): string
    {
        // UTF-8 normalization
        $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
        
        // Convert to lowercase
        $text = mb_strtolower($text);
        
        // Remove accents
        $text = strtr(
            utf8_decode($text),
            utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'),
            'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'
        );
        
        return $text;
    }
}