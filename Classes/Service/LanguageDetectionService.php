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
        // Input validation
        if (empty(trim($text))) {
            return 'en'; // Default fallback
        }

        // Priority 1: Use TYPO3 language if available and reliable
        $typo3Language = $this->getTypo3LanguageContext();
        if ($typo3Language !== null) {
            return $typo3Language;
        }

        // Priority 2: Automatic detection by content analysis
        if (mb_strlen(trim($text)) < 50) {
            // Text too short, use site default language
            return $this->getDefaultSiteLanguage();
        }

        // Text cleaning
        $text = $this->cleanText($text);

        // Trigram extraction
        $textTrigrams = $this->extractTrigrams($text);

        // Comparison with language profiles
        $scores = [];
        foreach ($this->languageProfiles as $lang => $profile) {
            $scores[$lang] = $this->calculateLanguageScore($textTrigrams, $profile);
        }

        // Confidence check of result
        arsort($scores);
        $topLanguages = array_slice($scores, 0, 2, true);

        if (count($topLanguages) >= 2) {
            $firstScore = reset($topLanguages);
            $secondScore = next($topLanguages);

            // If difference is too small, use TYPO3 context language
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
        $languages = ['fr', 'en', 'de', 'es', 'it', 'pt'];
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
        // TYPO3 14: Use Context API to get language
        try {
            $context = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class);
            $languageAspect = $context->getAspect('language');
            $languageId = $languageAspect->getId();

            // Get site and configured language
            if ($languageId > 0) {
                $siteFinder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class);
                $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

                if ($request && $request->getAttribute('site')) {
                    $site = $request->getAttribute('site');
                    $siteLanguage = $site->getLanguageById($languageId);

                    if ($siteLanguage) {
                        $locale = $siteLanguage->getLocale();
                        // Extract language code from locale (e.g., de_DE -> de)
                        return strtolower(substr($locale->getLanguageCode(), 0, 2));
                    }
                }
            }

            // Fallback: static mapping for compatibility
            return $this->getStaticLanguageMapping($languageId);

        } catch (\Exception $e) {
            // In case of error, try fallback methods
            return $this->getFallbackLanguage();
        }
    }

    private function getStaticLanguageMapping(int $languageId): ?string
    {
        // Mapping TYPO3 language UIDs to ISO codes
        $languageMap = [
            0 => 'en', // Default
            1 => 'fr',
            2 => 'de',
            3 => 'es',
            4 => 'it',
            5 => 'pt',
            6 => 'nl', // Dutch
            7 => 'da', // Danish
            8 => 'sv', // Swedish
            9 => 'no', // Norwegian
            10 => 'fi', // Finnish
            // Add other mappings according to your configuration
        ];

        return $languageMap[$languageId] ?? null;
    }

    private function getFallbackLanguage(): ?string
    {
        // TYPO3 14: Use Context API for language fallback
        try {
            $context = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class);
            $languageAspect = $context->getAspect('language');
            $languageId = $languageAspect->getId();
            return $this->getStaticLanguageMapping($languageId);
        } catch (\Exception $e) {
            return null;
        }
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
