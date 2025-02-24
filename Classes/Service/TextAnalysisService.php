<?php
namespace Cywolf\NlpTools\Service;

use TYPO3\CMS\Core\SingletonInterface;
use Wamania\Stemmer\French;
use Wamania\Stemmer\English;
use Wamania\Stemmer\German;
use Wamania\Stemmer\Spanish;
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

    public function removeStopWords(string $text, ?string $language = null): string
    {
        // Détecter la langue si non spécifiée
        $language = $language ?? $this->languageDetector->detectLanguage($text);
        
        // Récupérer les stop words pour la langue
        $stopWords = $this->stopWordsFactory->getStopWords($language);
        $stopWordsList = $stopWords->getStopWords();
        
        // Tokenize le texte
        $words = $this->tokenize($text);
        
        // Filtrer les stop words
        $filteredWords = array_filter($words, function($word) use ($stopWordsList) {
            return !in_array(mb_strtolower($word), $stopWordsList, true);
        });
        
        // Reconstruire le texte
        return implode(' ', $filteredWords);
    }
    
    private function getStemmer(string $language): ?object
    {
        if (isset($this->stemmers[$language])) {
            return $this->stemmers[$language];
        }

        // Implémenter un stemming simple pour éviter les problèmes de dépendance
        // Cette classe de stemming fera l'affaire en attendant de résoudre les problèmes avec la bibliothèque
        return new class($language) {
            private string $lang;
            
            public function __construct(string $lang) {
                $this->lang = $lang;
            }
            
            public function stem(string $word): string {
                // Implémentation basique qui fonctionne pour beaucoup de langues européennes
                // Enlève les terminaisons les plus communes
                $word = mb_strtolower($word);
                
                // Supprimer les s du pluriel (marche pour EN, FR, ES)
                if (mb_strlen($word) > 3 && mb_substr($word, -1) === 's') {
                    $word = mb_substr($word, 0, -1);
                }
                
                // Supprimer quelques terminaisons fréquentes selon la langue
                if ($this->lang === 'fr') {
                    $suffixes = ['ement', 'euse', 'eux', 'ant', 'ent', 'er', 'ez', 'é', 'ée'];
                } elseif ($this->lang === 'en') {
                    $suffixes = ['ing', 'ed', 'ly', 'ment', 'ers', 'or', 'ies', 'es', 'y'];
                } elseif ($this->lang === 'de') {
                    $suffixes = ['ung', 'lich', 'heit', 'keit', 'end', 'en', 'er'];
                } elseif ($this->lang === 'es') {
                    $suffixes = ['mente', 'ción', 'dor', 'ando', 'iendo', 'ado', 'ido', 'ar', 'er', 'ir'];
                } else {
                    $suffixes = ['ing', 'ed', 'ly']; // Défaut basé sur l'anglais
                }
                
                foreach ($suffixes as $suffix) {
                    if (mb_strlen($word) > (mb_strlen($suffix) + 2) && mb_substr($word, -mb_strlen($suffix)) === $suffix) {
                        return mb_substr($word, 0, -mb_strlen($suffix));
                    }
                }
                
                return $word;
            }
        };

        return $this->stemmers[$language];
    }

    public function stem(string $text, ?string $language = null): array
    {
        $language = $language ?? $this->languageDetector->detectLanguage($text);
        $words = $this->tokenize($text);
        
        // Vérifier si on a un stemmer pour cette langue
        $stemmer = $this->getStemmer($language);
        if (!$stemmer) {
            return $words; // Retourner les mots tokenisés si pas de stemmer
        }

        $stemmedWords = array_map(function($word) use ($stemmer, $language) {
            // Utiliser le cache si disponible
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
        
        return $stemmedWords; // Retourner le tableau de mots stemmisés
    }

    public function tokenize(string $text): array
    {
        // Pre-processing du texte
        $text = $this->cleanText($text);
        
        // Tokenization plus avancée avec support Unicode
        $tokens = preg_split('/[\s,\.!?\(\)\[\]{}"\']+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        return array_filter($tokens, function($token) {
            return mb_strlen($token) > 1; // Ignorer les tokens trop courts
        });
    }

    private function cleanText(string $text): string
    {
        // Normalisation UTF-8
        $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
        
        // Conversion en minuscules
        $text = mb_strtolower($text);
        
        // Suppression des accents
        $text = strtr(
            utf8_decode($text),
            utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'),
            'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'
        );
        
        return $text;
    }
}