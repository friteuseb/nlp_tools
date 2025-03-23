<?php
namespace Cywolf\NlpTools\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Service to convert text to vector representations for machine learning
 */
class TextVectorizerService implements SingletonInterface
{
    private TextAnalysisService $textAnalyzer;
    private StopWordsFactory $stopWordsFactory;
    private ?FrontendInterface $cache;

    public function __construct(
        TextAnalysisService $textAnalyzer,
        StopWordsFactory $stopWordsFactory,
        ?FrontendInterface $cache = null
    ) {
        $this->textAnalyzer = $textAnalyzer;
        $this->stopWordsFactory = $stopWordsFactory;
        $this->cache = $cache;
    }

    /**
     * Convert a collection of texts to a TF-IDF matrix
     *
     * @param array $texts Array of text strings
     * @param string|null $language Language code (auto-detected if null)
     * @return array Associative array with 'vectors', 'vocabulary', and 'idf' keys
     */
    public function createTfIdfVectors(array $texts, ?string $language = null): array
    {
        // Detect language if not provided
        if ($language === null && !empty($texts)) {
            $language = $this->textAnalyzer->detectLanguage($texts[0] ?? '');
        }

        // Cache key for the tf-idf computation
        $cacheKey = 'tfidf_' . md5(implode('', $texts)) . '_' . $language;
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // 1. Tokenize and preprocess all texts
        $processedTexts = [];
        foreach ($texts as $index => $text) {
            // Remove stop words and apply stemming
            $cleanText = $this->textAnalyzer->removeStopWords($text, $language);
            $stemmed = $this->textAnalyzer->stem($cleanText, $language);
            $processedTexts[$index] = $stemmed;
        }

        // 2. Build vocabulary (unique terms across all documents)
        $vocabulary = [];
        foreach ($processedTexts as $docTerms) {
            foreach ($docTerms as $term) {
                $vocabulary[$term] = true;
            }
        }
        $vocabulary = array_keys($vocabulary);
        
        // 3. Calculate document frequencies
        $docFreq = array_fill_keys($vocabulary, 0);
        foreach ($processedTexts as $docTerms) {
            $uniqueTerms = array_unique($docTerms);
            foreach ($uniqueTerms as $term) {
                if (isset($docFreq[$term])) {
                    $docFreq[$term]++;
                }
            }
        }
        
        // 4. Calculate IDF (Inverse Document Frequency)
        $numDocs = count($texts);
        $idf = [];
        foreach ($docFreq as $term => $freq) {
            $idf[$term] = log(($numDocs + 1) / ($freq + 1)) + 1; // Smoothed IDF
        }
        
        // 5. Calculate TF-IDF vectors
        $vectors = [];
        foreach ($processedTexts as $docId => $docTerms) {
            $termFreq = array_count_values($docTerms);
            $vector = array_fill_keys($vocabulary, 0);
            
            foreach ($termFreq as $term => $freq) {
                if (isset($vector[$term]) && isset($idf[$term])) {
                    // TF * IDF
                    $vector[$term] = $freq * $idf[$term];
                }
            }
            
            $vectors[$docId] = $vector;
        }
        
        // 6. Normalize vectors (L2 normalization)
        foreach ($vectors as $docId => $vector) {
            $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vector)));
            if ($magnitude > 0) {
                $vectors[$docId] = array_map(function($x) use ($magnitude) { 
                    return $x / $magnitude; 
                }, $vector);
            }
        }
        
        $result = [
            'vectors' => $vectors,
            'vocabulary' => $vocabulary,
            'idf' => $idf
        ];
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cacheKey, $result);
        }
        
        return $result;
    }

    /**
     * Create a document-term matrix (counts of terms in documents)
     * 
     * @param array $texts Array of text strings
     * @param string|null $language Language code (auto-detected if null)
     * @return array Associative array with 'matrix' and 'vocabulary' keys
     */
    public function createDocumentTermMatrix(array $texts, ?string $language = null): array
    {
        // Detect language if not provided
        if ($language === null && !empty($texts)) {
            $language = $this->textAnalyzer->detectLanguage($texts[0] ?? '');
        }

        // Cache key
        $cacheKey = 'dtm_' . md5(implode('', $texts)) . '_' . $language;
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Process texts
        $processedTexts = [];
        foreach ($texts as $index => $text) {
            $cleanText = $this->textAnalyzer->removeStopWords($text, $language);
            $tokens = $this->textAnalyzer->tokenize($cleanText);
            $processedTexts[$index] = $tokens;
        }

        // Build vocabulary
        $vocabulary = [];
        foreach ($processedTexts as $docTerms) {
            foreach ($docTerms as $term) {
                $vocabulary[$term] = true;
            }
        }
        $vocabulary = array_keys($vocabulary);
        
        // Create document-term matrix
        $matrix = [];
        foreach ($processedTexts as $docId => $docTerms) {
            $termCounts = array_count_values($docTerms);
            $row = array_fill_keys($vocabulary, 0);
            
            foreach ($termCounts as $term => $count) {
                if (isset($row[$term])) {
                    $row[$term] = $count;
                }
            }
            
            $matrix[$docId] = $row;
        }
        
        $result = [
            'matrix' => $matrix,
            'vocabulary' => $vocabulary
        ];
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cacheKey, $result);
        }
        
        return $result;
    }

    /**
     * Calculate cosine similarity between two vectors
     * 
     * @param array $vector1 First vector
     * @param array $vector2 Second vector
     * @return float Similarity score between 0 and 1
     */
    public function cosineSimilarity(array $vector1, array $vector2): float
    {
        // Make sure vectors have the same dimensions
        $allKeys = array_unique(array_merge(array_keys($vector1), array_keys($vector2)));
        $v1 = array_fill_keys($allKeys, 0);
        $v2 = array_fill_keys($allKeys, 0);
        
        foreach ($vector1 as $key => $value) {
            $v1[$key] = $value;
        }
        
        foreach ($vector2 as $key => $value) {
            $v2[$key] = $value;
        }
        
        // Calculate dot product
        $dotProduct = 0;
        foreach ($allKeys as $key) {
            $dotProduct += $v1[$key] * $v2[$key];
        }
        
        // Calculate magnitudes
        $mag1 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $v1)));
        $mag2 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $v2)));
        
        // Avoid division by zero
        if ($mag1 * $mag2 == 0) {
            return 0;
        }
        
        return $dotProduct / ($mag1 * $mag2);
    }

    /**
     * Calculate similarity matrix for a set of vectors
     * 
     * @param array $vectors Array of vectors
     * @return array 2D array with similarity scores
     */
    public function calculateSimilarityMatrix(array $vectors): array
    {
        $numVectors = count($vectors);
        $similarityMatrix = [];
        
        for ($i = 0; $i < $numVectors; $i++) {
            $similarityMatrix[$i] = [];
            for ($j = 0; $j < $numVectors; $j++) {
                if ($i == $j) {
                    $similarityMatrix[$i][$j] = 1.0; // Self-similarity is 1
                } elseif (isset($similarityMatrix[$j][$i])) {
                    $similarityMatrix[$i][$j] = $similarityMatrix[$j][$i]; // Symmetric
                } else {
                    $similarityMatrix[$i][$j] = $this->cosineSimilarity($vectors[$i], $vectors[$j]);
                }
            }
        }
        
        return $similarityMatrix;
    }
}