<?php
namespace Cywolf\NlpTools\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Service for extracting topics from texts
 */
class TopicModelingService implements SingletonInterface
{
    private TextVectorizerService $vectorizer;
    private TextAnalysisService $textAnalyzer;
    private ?FrontendInterface $cache;

    public function __construct(
        TextVectorizerService $vectorizer,
        TextAnalysisService $textAnalyzer,
        ?FrontendInterface $cache = null
    ) {
        $this->vectorizer = $vectorizer;
        $this->textAnalyzer = $textAnalyzer;
        $this->cache = $cache;
    }

    /**
     * Extract the most representative terms from a cluster of texts
     *
     * @param array $texts Array of text strings
     * @param int $numTerms Number of representative terms to extract
     * @param string|null $language Language code (auto-detected if null)
     * @return array Array of terms and their scores
     */
    public function extractTopicTerms(array $texts, int $numTerms = 10, ?string $language = null): array
    {
        if (empty($texts)) {
            return [];
        }

        // Cache key
        $cacheKey = 'topic_terms_' . md5(implode('', $texts)) . '_' . $numTerms . '_' . $language;
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Create document-term matrix
        $dtmData = $this->vectorizer->createDocumentTermMatrix($texts, $language);
        $dtm = $dtmData['matrix'];
        $vocabulary = $dtmData['vocabulary'];
        
        // Calculate term frequency across all documents
        $termFreq = array_fill_keys($vocabulary, 0);
        foreach ($dtm as $doc) {
            foreach ($vocabulary as $term) {
                $termFreq[$term] += $doc[$term];
            }
        }
        
        // Calculate document frequency (in how many docs each term appears)
        $docFreq = array_fill_keys($vocabulary, 0);
        foreach ($dtm as $doc) {
            foreach ($vocabulary as $term) {
                if ($doc[$term] > 0) {
                    $docFreq[$term]++;
                }
            }
        }
        
        // Calculate TF-IDF score for each term
        $scores = [];
        $numDocs = count($texts);
        foreach ($vocabulary as $term) {
            if ($docFreq[$term] > 0 && $termFreq[$term] > 0) {
                // TF * IDF
                $tf = $termFreq[$term];
                $idf = log($numDocs / $docFreq[$term]) + 1;
                $scores[$term] = $tf * $idf;
            }
        }
        
        // Sort by score in descending order
        arsort($scores);
        
        // Take top N terms
        $topTerms = array_slice($scores, 0, $numTerms, true);
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cacheKey, $topTerms);
        }
        
        return $topTerms;
    }

    /**
     * Simple topic modeling using Non-negative Matrix Factorization (NMF) approximation
     *
     * @param array $texts Array of text strings
     * @param int $numTopics Number of topics to extract
     * @param int $numTermsPerTopic Number of terms to include per topic
     * @param string|null $language Language code (auto-detected if null)
     * @return array Array of topics with their top terms
     */
    public function extractTopics(
        array $texts, 
        int $numTopics = 5, 
        int $numTermsPerTopic = 10, 
        ?string $language = null
    ): array {
        if (empty($texts) || $numTopics <= 0 || $numTopics > count($texts)) {
            return [];
        }

        // Cache key
        $cacheKey = 'topics_' . md5(implode('', $texts)) . '_' . $numTopics . '_' . $language;
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Create TF-IDF vectors
        $tfIdfData = $this->vectorizer->createTfIdfVectors($texts, $language);
        $vectors = $tfIdfData['vectors'];
        $vocabulary = $tfIdfData['vocabulary'];
        
        // Using k-means as a simple topic modeling approximation
        $textClusteringService = new TextClusteringService($this->vectorizer, $this->textAnalyzer, $this->cache);
        $clusters = $textClusteringService->kMeansClustering($texts, $numTopics, $language);
        
        // Extract top terms for each cluster
        $topics = [];
        foreach ($clusters['clusters'] as $index => $cluster) {
            if (empty($cluster['texts'])) {
                continue;
            }
            
            $topTerms = $this->extractTopicTerms(
                $cluster['texts'], 
                $numTermsPerTopic, 
                $language
            );
            
            $topics[] = [
                'id' => "topic_$index",
                'terms' => $topTerms,
                'texts' => $cluster['texts'],
                'coherence' => $cluster['coherence']
            ];
        }
        
        // Sort topics by cluster size (number of texts)
        usort($topics, function($a, $b) {
            return count($b['texts']) - count($a['texts']);
        });
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cacheKey, $topics);
        }
        
        return $topics;
    }

    /**
     * Extract key phrases from a text using a graph-based approach
     * 
     * @param string $text Input text
     * @param int $numPhrases Number of key phrases to extract
     * @param string|null $language Language code (auto-detected if null)
     * @return array Array of key phrases and their scores
     */
    public function extractKeyPhrases(string $text, int $numPhrases = 5, ?string $language = null): array
    {
        if (empty($text)) {
            return [];
        }

        // Cache key
        $cacheKey = 'keyphrases_' . md5($text) . '_' . $numPhrases . '_' . $language;
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Detect language if not provided
        $language = $language ?? $this->textAnalyzer->detectLanguage($text);
        
        // Tokenize text
        $sentences = $this->splitIntoSentences($text);
        $phrases = [];
        
        // Extract candidate phrases from each sentence
        foreach ($sentences as $sentence) {
            // Clean and tokenize
            $tokens = $this->textAnalyzer->tokenize($sentence);
            
            // Skip short sentences
            if (count($tokens) < 3) {
                continue;
            }
            
            // Get stop words for filtering
            $stopWords = $this->textAnalyzer->getStopWordsFactory()->getStopWords($language)->getStopWords();
            
            // Extract noun phrases (simplified approach)
            $phrase = '';
            $currentPhrase = [];
            
            foreach ($tokens as $token) {
                $lowerToken = mb_strtolower($token);
                
                // Skip stop words at the beginning of phrases
                if (empty($currentPhrase) && in_array($lowerToken, $stopWords, true)) {
                    continue;
                }
                
                // Add token to current phrase
                $currentPhrase[] = $token;
                
                // End phrase if we hit a stop word or punctuation
                if (in_array($lowerToken, $stopWords, true) || preg_match('/[.,:;!?]/', $token)) {
                    // Remove the last token if it's a stop word or punctuation
                    array_pop($currentPhrase);
                    
                    // Store phrase if it's valid
                    if (count($currentPhrase) >= 2) {
                        $phrase = implode(' ', $currentPhrase);
                        if (!isset($phrases[$phrase])) {
                            $phrases[$phrase] = 0;
                        }
                        $phrases[$phrase]++;
                    }
                    
                    // Reset current phrase
                    $currentPhrase = [];
                }
            }
            
            // Handle any remaining phrase
            if (count($currentPhrase) >= 2) {
                $phrase = implode(' ', $currentPhrase);
                if (!isset($phrases[$phrase])) {
                    $phrases[$phrase] = 0;
                }
                $phrases[$phrase]++;
            }
        }
        
        // Score phrases using frequency and length
        $scoredPhrases = [];
        foreach ($phrases as $phrase => $frequency) {
            $length = mb_strlen($phrase);
            $wordCount = count(explode(' ', $phrase));
            
            // Score formula: frequency * log(length) * word count
            $scoredPhrases[$phrase] = $frequency * log($length + 1) * $wordCount;
        }
        
        // Sort by score
        arsort($scoredPhrases);
        
        // Get top phrases
        $topPhrases = array_slice($scoredPhrases, 0, $numPhrases, true);
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cacheKey, $topPhrases);
        }
        
        return $topPhrases;
    }

    /**
     * Split text into sentences
     *
     * @param string $text Input text
     * @return array Array of sentences
     */
    private function splitIntoSentences(string $text): array
    {
        // Simple sentence splitting
        $text = str_replace(["\n", "\r"], '. ', $text);
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Clean up sentences
        return array_map('trim', $sentences);
    }
}