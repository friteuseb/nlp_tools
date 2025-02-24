<?php
namespace Cywolf\NlpTools\StopWords;

abstract class AbstractStopWords implements StopWordsInterface
{
    protected array $stopWords = [];

    public function getStopWords(): array
    {
        return $this->stopWords;
    }

    public function isStopWord(string $word): bool
    {
        return in_array(strtolower($word), $this->stopWords);
    }
}