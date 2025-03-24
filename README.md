# NLP Tools for TYPO3

A comprehensive TYPO3 extension for Natural Language Processing, compatible with TYPO3 v12 and v13.

## Installation

```bash
composer require cywolf/nlp-tools
```

Activate the extension in the TYPO3 extension manager.

## Available Features

### 1. Stop Words Management

Filter stop words in different languages (FR, EN, DE, ES).

```php
use Cywolf\NlpTools\Service\StopWordsFactory;

class YourClass {
    protected StopWordsFactory $stopWordsFactory;

    public function __construct(StopWordsFactory $stopWordsFactory) 
    {
        $this->stopWordsFactory = $stopWordsFactory;
    }

    public function stopWordsExample(): void 
    {
        // Get stop words for a language
        $frenchStopWords = $this->stopWordsFactory->getStopWords('fr');

        // Check if a word is a stop word
        if ($frenchStopWords->isStopWord('le')) {
            // It's a stop word
        }

        // Get the complete list of stop words
        $allStopWords = $frenchStopWords->getStopWords();
    }
}
```

### 2. Language Detection

Automatic language detection service based on n-grams.

```php
use Cywolf\NlpTools\Service\LanguageDetectionService;

class YourClass {
    protected LanguageDetectionService $languageDetector;

    public function __construct(LanguageDetectionService $languageDetector) 
    {
        $this->languageDetector = $languageDetector;
    }

    public function detectionExample(): string 
    {
        $text = "This is an example of English text";
        return $this->languageDetector->detectLanguage($text); // Returns 'en'
    }
}
```

### 3. Text Analysis

Complete text analysis service including tokenization, stemming, and removal of stop words.

```php
use Cywolf\NlpTools\Service\TextAnalysisService;

class YourClass {
    protected TextAnalysisService $textAnalyzer;

    public function __construct(TextAnalysisService $textAnalyzer) 
    {
        $this->textAnalyzer = $textAnalyzer;
    }

    public function analysisExample(): array 
    {
        $text = "Here is an example text to analyze";

        // Tokenization
        $tokens = $this->textAnalyzer->tokenize($text);

        // Stemming
        $stemmed = $this->textAnalyzer->stem($text, 'en');

        // Remove stop words
        $withoutStopWords = $this->textAnalyzer->removeStopWords($text, 'en');

        return [
            'tokens' => $tokens,
            'stemmed' => $stemmed,
            'cleaned' => $withoutStopWords
        ];
    }
}
```

### 4. Text Vectorization

Service for converting text into vector representations for machine learning.

```php
use Cywolf\NlpTools\Service\TextVectorizerService;

class YourClass {
    protected TextVectorizerService $vectorizer;

    public function __construct(TextVectorizerService $vectorizer) 
    {
        $this->vectorizer = $vectorizer;
    }

    public function vectorizationExample(): array 
    {
        $texts = [
            "This is the first document to analyze",
            "A second document with different content",
            "And finally a third example"
        ];

        // Create TF-IDF vectors
        $tfIdfData = $this->vectorizer->createTfIdfVectors($texts, 'en');
        
        // Create document-term matrix
        $dtmData = $this->vectorizer->createDocumentTermMatrix($texts, 'en');
        
        // Calculate similarity between two vectors
        $similarity = $this->vectorizer->cosineSimilarity(
            $tfIdfData['vectors'][0],
            $tfIdfData['vectors'][1]
        );
        
        // Calculate similarity matrix
        $similarityMatrix = $this->vectorizer->calculateSimilarityMatrix($tfIdfData['vectors']);
        
        return [
            'tfidf' => $tfIdfData,
            'dtm' => $dtmData,
            'similarity' => $similarity,
            'matrix' => $similarityMatrix
        ];
    }
}
```

### 5. Text Clustering

Service for automatically grouping similar texts together.

```php
use Cywolf\NlpTools\Service\TextClusteringService;

class YourClass {
    protected TextClusteringService $clustering;

    public function __construct(TextClusteringService $clustering) 
    {
        $this->clustering = $clustering;
    }

    public function clusteringExample(): array 
    {
        $texts = [
            "The cat sleeps on the couch", 
            "My dog plays in the garden",
            "I like cats and domestic felines",
            "The dog is man's best friend",
            "Pets bring joy"
        ];

        // K-means clustering (k=2 groups)
        $kMeansClusters = $this->clustering->kMeansClustering($texts, 2, 'en');
        
        // Hierarchical clustering
        $hierarchicalClusters = $this->clustering->hierarchicalClustering(
            $texts, 
            0.6, // Distance threshold
            'en'
        );
        
        // Similarity-based clustering
        $similarityClusters = $this->clustering->similarityBasedClustering(
            $texts,
            0.7, // Similarity threshold
            'en'
        );
        
        return [
            'kmeans' => $kMeansClusters,
            'hierarchical' => $hierarchicalClusters,
            'similarity' => $similarityClusters
        ];
    }
}
```

### 6. Topic Modeling

Service for extracting themes and topics from text collections.

```php
use Cywolf\NlpTools\Service\TopicModelingService;

class YourClass {
    protected TopicModelingService $topicModeling;

    public function __construct(TopicModelingService $topicModeling) 
    {
        $this->topicModeling = $topicModeling;
    }

    public function topicsExample(): array 
    {
        $texts = [
            "The new economic policy favors local businesses",
            "The government announces an economic recovery plan",
            "Researchers have discovered a new medical treatment",
            "A scientific study reveals the impact of climate on health",
            "The stock market saw a strong rise following economic announcements"
        ];

        // Extract topics
        $topics = $this->topicModeling->extractTopics(
            $texts,
            2, // Number of topics to extract
            5  // Number of terms per topic
        );
        
        // Extract representative terms from a group of texts
        $terms = $this->topicModeling->extractTopicTerms(
            $texts,
            10 // Number of terms to extract
        );
        
        // Extract key phrases from a text
        $keyPhrases = $this->topicModeling->extractKeyPhrases(
            $texts[0],
            3 // Number of phrases to extract
        );
        
        return [
            'topics' => $topics,
            'terms' => $terms,
            'key_phrases' => $keyPhrases
        ];
    }
}
```

## Example of use in a TYPO3 extension

### Services.yaml configuration

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  YourVendor\YourExtension\:
    resource: '../Classes/*'

  YourVendor\YourExtension\Service\TextProcessingService:
    public: true
```

### Service class

```php
namespace YourVendor\YourExtension\Service;

use Cywolf\NlpTools\Service\TextAnalysisService;
use Cywolf\NlpTools\Service\LanguageDetectionService;
use Cywolf\NlpTools\Service\TextClusteringService;
use Cywolf\NlpTools\Service\TopicModelingService;

class TextProcessingService 
{
    protected TextAnalysisService $textAnalyzer;
    protected LanguageDetectionService $languageDetector;
    protected TextClusteringService $clustering;
    protected TopicModelingService $topicModeling;

    public function __construct(
        TextAnalysisService $textAnalyzer,
        LanguageDetectionService $languageDetector,
        TextClusteringService $clustering,
        TopicModelingService $topicModeling
    ) {
        $this->textAnalyzer = $textAnalyzer;
        $this->languageDetector = $languageDetector;
        $this->clustering = $clustering;
        $this->topicModeling = $topicModeling;
    }

    public function processText(string $text): array 
    {
        // Language detection
        $language = $this->languageDetector->detectLanguage($text);

        // Complete analysis
        return [
            'language' => $language,
            'tokens' => $this->textAnalyzer->tokenize($text),
            'stemmed' => $this->textAnalyzer->stem($text, $language),
            'without_stopwords' => $this->textAnalyzer->removeStopWords($text, $language),
            'key_phrases' => $this->topicModeling->extractKeyPhrases($text, 3, $language)
        ];
    }
    
    public function analyzeMultipleTexts(array $texts): array
    {
        // Clustering and topic analysis
        $clusters = $this->clustering->kMeansClustering($texts, 3);
        $topics = $this->topicModeling->extractTopics($texts, 3);
        
        return [
            'clusters' => $clusters,
            'topics' => $topics
        ];
    }
}
```

## Using with cache

To improve performance, you can inject a TYPO3 cache into the services:

```php
use TYPO3\CMS\Core\Cache\CacheManager;
use Cywolf\NlpTools\Service\TextAnalysisService;

class YourController
{
    protected TextAnalysisService $textAnalyzer;
    protected CacheManager $cacheManager;
    
    public function __construct(
        TextAnalysisService $textAnalyzer,
        CacheManager $cacheManager
    ) {
        $this->textAnalyzer = $textAnalyzer;
        $this->cacheManager = $cacheManager;
    }
    
    public function yourAction(): void
    {
        // Get the cache
        $cache = $this->cacheManager->getCache('nlp_tools');
        
        // Pass it to a service for faster calculations
        $this->textAnalyzer->setCache($cache);
        
        // Use the service normally
        $tokens = $this->textAnalyzer->tokenize($text);
    }
}
```

## TYPO3 Compatibility

This extension is compatible with:
- TYPO3 v12.4+
- TYPO3 v13.0+

## Important Notes

- Language detection uses TYPO3 language configuration if available
- Stemming uses a simplified internal implementation, with fallback to the Snowball library
- Services can be injected via TYPO3's dependency injection
- Clustering algorithms are optimized for acceptable performance even on large text collections
- Use caching to improve performance on repetitive operations