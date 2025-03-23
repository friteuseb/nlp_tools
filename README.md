# NLP Tools pour TYPO3

Une extension TYPO3 complète pour le traitement du langage naturel, compatible avec TYPO3 v12 et v13.

## Installation

```bash
composer require cywolf/nlp-tools
```

Activer l'extension dans le gestionnaire d'extensions de TYPO3.

## Fonctionnalités disponibles

### 1. Gestion des mots vides (Stop Words)

Permet de filtrer les mots vides dans différentes langues (FR, EN, DE, ES).

```php
use Cywolf\NlpTools\Service\StopWordsFactory;

class VotreClasse {
    protected StopWordsFactory $stopWordsFactory;

    public function __construct(StopWordsFactory $stopWordsFactory) 
    {
        $this->stopWordsFactory = $stopWordsFactory;
    }

    public function exempleStopWords(): void 
    {
        // Récupérer les stopwords pour une langue
        $frenchStopWords = $this->stopWordsFactory->getStopWords('fr');

        // Vérifier si un mot est un stopword
        if ($frenchStopWords->isStopWord('le')) {
            // C'est un stopword
        }

        // Obtenir la liste complète des stopwords
        $allStopWords = $frenchStopWords->getStopWords();
    }
}
```

### 2. Détection de langue

Service automatique de détection de langue basé sur les n-grammes.

```php
use Cywolf\NlpTools\Service\LanguageDetectionService;

class VotreClasse {
    protected LanguageDetectionService $languageDetector;

    public function __construct(LanguageDetectionService $languageDetector) 
    {
        $this->languageDetector = $languageDetector;
    }

    public function exempleDetection(): string 
    {
        $texte = "Voici un exemple de texte en français";
        return $this->languageDetector->detectLanguage($texte); // Retourne 'fr'
    }
}
```

### 3. Analyse de texte

Service complet d'analyse de texte incluant tokenization, stemming et extraction de n-grammes.

```php
use Cywolf\NlpTools\Service\TextAnalysisService;

class VotreClasse {
    protected TextAnalysisService $textAnalyzer;

    public function __construct(TextAnalysisService $textAnalyzer) 
    {
        $this->textAnalyzer = $textAnalyzer;
    }

    public function exempleAnalyse(): array 
    {
        $texte = "Voici un exemple de texte à analyser";

        // Tokenization
        $tokens = $this->textAnalyzer->tokenize($texte);

        // Stemming (avec Snowball)
        $stemmed = $this->textAnalyzer->stem($texte, 'fr');

        // Extraction de n-grammes
        $trigrams = $this->textAnalyzer->extractNGrams($texte, 3);

        // Suppression des stopwords
        $sansStopWords = $this->textAnalyzer->removeStopWords($texte, 'fr');

        return [
            'tokens' => $tokens,
            'stemmed' => $stemmed,
            'trigrams' => $trigrams,
            'cleaned' => $sansStopWords
        ];
    }
}
```

### 4. Vectorisation de texte

Service permettant de convertir du texte en représentations vectorielles pour le machine learning.

```php
use Cywolf\NlpTools\Service\TextVectorizerService;

class VotreClasse {
    protected TextVectorizerService $vectorizer;

    public function __construct(TextVectorizerService $vectorizer) 
    {
        $this->vectorizer = $vectorizer;
    }

    public function exempleVectorisation(): array 
    {
        $textes = [
            "Voici le premier document à analyser",
            "Un second document avec du contenu différent",
            "Et finalement un troisième exemple"
        ];

        // Créer des vecteurs TF-IDF
        $tfIdfData = $this->vectorizer->createTfIdfVectors($textes, 'fr');
        
        // Créer une matrice document-terme
        $dtmData = $this->vectorizer->createDocumentTermMatrix($textes, 'fr');
        
        // Calculer la similarité entre deux vecteurs
        $similarite = $this->vectorizer->cosineSimilarity(
            $tfIdfData['vectors'][0],
            $tfIdfData['vectors'][1]
        );
        
        // Calculer une matrice de similarité
        $similarityMatrix = $this->vectorizer->calculateSimilarityMatrix($tfIdfData['vectors']);
        
        return [
            'tfidf' => $tfIdfData,
            'dtm' => $dtmData,
            'similarite' => $similarite,
            'matrix' => $similarityMatrix
        ];
    }
}
```

### 5. Clustering de texte

Service pour regrouper automatiquement des textes similaires.

```php
use Cywolf\NlpTools\Service\TextClusteringService;

class VotreClasse {
    protected TextClusteringService $clustering;

    public function __construct(TextClusteringService $clustering) 
    {
        $this->clustering = $clustering;
    }

    public function exempleClustering(): array 
    {
        $textes = [
            "Le chat dort sur le canapé", 
            "Mon chien joue dans le jardin",
            "J'aime les chats et les félins domestiques",
            "Le chien est le meilleur ami de l'homme",
            "Les animaux domestiques apportent de la joie"
        ];

        // Clustering K-means (k=2 groupes)
        $kMeansClusters = $this->clustering->kMeansClustering($textes, 2, 'fr');
        
        // Clustering hiérarchique
        $hierarchicalClusters = $this->clustering->hierarchicalClustering(
            $textes, 
            0.6, // Seuil de distance
            'fr'
        );
        
        // Clustering par similarité
        $similarityClusters = $this->clustering->similarityBasedClustering(
            $textes,
            0.7, // Seuil de similarité
            'fr'
        );
        
        return [
            'kmeans' => $kMeansClusters,
            'hierarchical' => $hierarchicalClusters,
            'similarity' => $similarityClusters
        ];
    }
}
```

### 6. Modélisation de sujets (Topic Modeling)

Service pour extraire des thèmes et sujets à partir de collections de textes.

```php
use Cywolf\NlpTools\Service\TopicModelingService;

class VotreClasse {
    protected TopicModelingService $topicModeling;

    public function __construct(TopicModelingService $topicModeling) 
    {
        $this->topicModeling = $topicModeling;
    }

    public function exempleTopics(): array 
    {
        $textes = [
            "La nouvelle politique économique favorise les entreprises locales",
            "Le gouvernement annonce un plan de relance économique",
            "Les chercheurs ont découvert un nouveau traitement médical",
            "Une étude scientifique révèle l'impact du climat sur la santé",
            "La bourse a connu une forte hausse suite aux annonces économiques"
        ];

        // Extraire des sujets (topics)
        $topics = $this->topicModeling->extractTopics(
            $textes,
            2, // Nombre de sujets à extraire
            5  // Nombre de termes par sujet
        );
        
        // Extraire les termes représentatifs d'un groupe de textes
        $termes = $this->topicModeling->extractTopicTerms(
            $textes,
            10 // Nombre de termes à extraire
        );
        
        // Extraire des expressions clés d'un texte
        $phrasesCles = $this->topicModeling->extractKeyPhrases(
            $textes[0],
            3 // Nombre d'expressions à extraire
        );
        
        return [
            'topics' => $topics,
            'termes' => $termes,
            'phrases_cles' => $phrasesCles
        ];
    }
}
```

## Exemple d'utilisation dans une extension TYPO3

### Configuration Services.yaml

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  VotreVendor\VotreExtension\:
    resource: '../Classes/*'

  VotreVendor\VotreExtension\Service\TextProcessingService:
    public: true
```

### Classe de service

```php
namespace VotreVendor\VotreExtension\Service;

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
        // Détection de la langue
        $language = $this->languageDetector->detectLanguage($text);

        // Analyse complète
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
        // Clustering et analyse thématique
        $clusters = $this->clustering->kMeansClustering($texts, 3);
        $topics = $this->topicModeling->extractTopics($texts, 3);
        
        return [
            'clusters' => $clusters,
            'topics' => $topics
        ];
    }
}
```

## Utilisation avec cache

Pour améliorer les performances, vous pouvez injecter un cache TYPO3 dans les services:

```php
use TYPO3\CMS\Core\Cache\CacheManager;
use Cywolf\NlpTools\Service\TextAnalysisService;

class VotreController
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
    
    public function votreAction(): void
    {
        // Récupérer le cache
        $cache = $this->cacheManager->getCache('nlp_tools');
        
        // Le passer à un service pour des calculs plus rapides
        $this->textAnalyzer->setCache($cache);
        
        // Utiliser le service normalement
        $tokens = $this->textAnalyzer->tokenize($text);
    }
}
```

## Compatibilité TYPO3

Cette extension est compatible avec:
- TYPO3 v12.4+
- TYPO3 v13.0+

## Notes importantes

- La détection de langue utilise la configuration de langue TYPO3 si disponible
- Le stemming utilise une implémentation interne simplifiée, avec fallback sur la bibliothèque Snowball
- Les services sont injectables via l'injection de dépendances de TYPO3
- Les algorithmes de clustering sont optimisés pour des performances acceptables même sur de grandes collections de textes
- Utilisez le cache pour améliorer les performances sur des opérations répétitives