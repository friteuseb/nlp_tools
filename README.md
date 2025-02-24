# NLP Tools pour TYPO3

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

class TextProcessingService 
{
    protected TextAnalysisService $textAnalyzer;
    protected LanguageDetectionService $languageDetector;

    public function __construct(
        TextAnalysisService $textAnalyzer,
        LanguageDetectionService $languageDetector
    ) {
        $this->textAnalyzer = $textAnalyzer;
        $this->languageDetector = $languageDetector;
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
            'without_stopwords' => $this->textAnalyzer->removeStopWords($text, $language)
        ];
    }
}
```


## Notes importantes

- La détection de langue utilise la configuration de langue TYPO3 si disponible
- Le stemming utilise la bibliothèque Snowball pour des résultats optimaux
- Les services sont injectables via l'injection de dépendances de TYPO3