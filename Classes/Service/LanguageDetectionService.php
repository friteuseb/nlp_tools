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
        // Si la langue TYPO3 est disponible et qu'on est en frontend
        if (($GLOBALS['TSFE'] ?? null) && $GLOBALS['TSFE']->sys_language_uid > 0) {
            return $this->getTypo3Language();
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

        // Retourne la langue avec le meilleur score
        arsort($scores);
        return key($scores);
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

    private function getTypo3Language(): string
    {
        // Mapping des UIDs de langue TYPO3 vers les codes ISO
        $languageMap = [
            0 => 'en', // Default
            1 => 'fr',
            2 => 'de',
            3 => 'es',
            // Ajouter d'autres mappings selon votre configuration
        ];

        return $languageMap[$GLOBALS['TSFE']->sys_language_uid] ?? 'en';
    }
}
