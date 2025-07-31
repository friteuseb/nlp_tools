<?php
namespace Cywolf\NlpTools\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LanguageDetectionService implements SingletonInterface
{
    private array $languageProfiles = [];
    private StopWordsFactory $stopWordsFactory;

    public function __construct(StopWordsFactory $stopWordsFactory)
    {
        $this->stopWordsFactory = $stopWordsFactory;
        $this->initializeLanguageProfiles();
    }

    public function detectLanguage(string $text): string
    {
        // Priorité 1: Utiliser la langue TYPO3 si disponible et fiable
        $typo3Language = $this->getTypo3LanguageContext();
        if ($typo3Language !== null) {
            return $typo3Language;
        }

        // Priorité 2: Détection automatique par analyse du contenu
        if (mb_strlen(trim($text)) < 50) {
            // Texte trop court, utiliser la langue par défaut du site
            return $this->getDefaultSiteLanguage();
        }

        // Nettoyage du texte
        $text = $this->cleanText($text);
        
        // Extraction des trigrams
        $textTrigrams = $this->extractTrigrams($text);
        
        // Comparaison avec les profils de langue
        $scores = [];
        foreach ($this->languageProfiles as $lang => $profile) {
            $scores[$lang] = $this->calculateLanguageScore($textTrigrams, $profile);
        }

        // Vérification de la confiance du résultat
        arsort($scores);
        $topLanguages = array_slice($scores, 0, 2, true);
        
        if (count($topLanguages) >= 2) {
            $firstScore = reset($topLanguages);
            $secondScore = next($topLanguages);
            
            // Si la différence est trop faible, utiliser la langue du contexte TYPO3
            if (($firstScore - $secondScore) / $firstScore < 0.3) {
                $contextLanguage = $this->getTypo3LanguageContext();
                if ($contextLanguage && isset($scores[$contextLanguage])) {
                    return $contextLanguage;
                }
            }
        }

        return key($topLanguages) ?: 'en';
    }

    private function initializeLanguageProfiles(): void
    {
        $languages = ['fr', 'en', 'de', 'es'];
        foreach ($languages as $lang) {
            $stopWords = $this->stopWordsFactory->getStopWords($lang);
            $this->languageProfiles[$lang] = $this->createLanguageProfile($stopWords->getStopWords());
        }
    }

    private function createLanguageProfile(array $words): array
    {
        $profile = [];
        foreach ($words as $word) {
            $trigrams = $this->extractTrigrams($word);
            foreach ($trigrams as $trigram) {
                $profile[$trigram] = ($profile[$trigram] ?? 0) + 1;
            }
        }
        return $profile;
    }

    private function extractTrigrams(string $text): array
    {
        $trigrams = [];
        $text = '_' . strtolower($text) . '_';
        for ($i = 0; $i < strlen($text) - 2; $i++) {
            $trigram = substr($text, $i, 3);
            $trigrams[$trigram] = ($trigrams[$trigram] ?? 0) + 1;
        }
        return $trigrams;
    }

    private function calculateLanguageScore(array $textTrigrams, array $languageProfile): float
    {
        $score = 0;
        foreach ($textTrigrams as $trigram => $count) {
            if (isset($languageProfile[$trigram])) {
                $score += $count * $languageProfile[$trigram];
            }
        }
        return $score;
    }

    private function cleanText(string $text): string
    {
        return preg_replace('/[^a-zA-ZáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒß-]/', ' ', $text);
    }

    private function getTypo3LanguageContext(): ?string
    {
        // TYPO3 12/13: Utiliser le Context API pour obtenir la langue
        try {
            $context = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class);
            $languageAspect = $context->getAspect('language');
            $languageId = $languageAspect->getId();
            
            // Obtenir le site et la langue configurée
            if ($languageId > 0) {
                $siteFinder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class);
                $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
                
                if ($request && $request->getAttribute('site')) {
                    $site = $request->getAttribute('site');
                    $siteLanguage = $site->getLanguageById($languageId);
                    
                    if ($siteLanguage) {
                        $locale = $siteLanguage->getLocale();
                        // Extraire le code de langue du locale (ex: de_DE -> de)
                        return strtolower(substr($locale->getLanguageCode(), 0, 2));
                    }
                }
            }
            
            // Fallback: mapping statique pour compatibilité
            return $this->getStaticLanguageMapping($languageId);
            
        } catch (\Exception $e) {
            // En cas d'erreur, essayer les méthodes de fallback
            return $this->getFallbackLanguage();
        }
    }

    private function getStaticLanguageMapping(int $languageId): ?string
    {
        // Mapping des UIDs de langue TYPO3 vers les codes ISO
        $languageMap = [
            0 => 'en', // Default
            1 => 'fr',
            2 => 'de',
            3 => 'es',
            4 => 'it',
            5 => 'pt',
            // Ajouter d'autres mappings selon votre configuration
        ];

        return $languageMap[$languageId] ?? null;
    }

    private function getFallbackLanguage(): ?string
    {
        // TYPO3 < 12: Utiliser TSFE si disponible
        if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->sys_language_uid >= 0) {
            return $this->getStaticLanguageMapping($GLOBALS['TSFE']->sys_language_uid);
        }
        
        return null;
    }

    private function getDefaultSiteLanguage(): string
    {
        try {
            $context = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class);
            $languageAspect = $context->getAspect('language');
            $languageId = $languageAspect->getId();
            
            if ($languageId === 0) {
                // Langue par défaut du site
                $siteFinder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class);
                $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
                
                if ($request && $request->getAttribute('site')) {
                    $site = $request->getAttribute('site');
                    $defaultLanguage = $site->getDefaultLanguage();
                    $locale = $defaultLanguage->getLocale();
                    return strtolower(substr($locale->getLanguageCode(), 0, 2));
                }
            }
        } catch (\Exception $e) {
            // Fallback silencieux
        }
        
        return 'en'; // Fallback ultime
    }
}
