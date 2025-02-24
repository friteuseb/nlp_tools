<?php
namespace Cywolf\NlpTools\StopWords;

interface StopWordsInterface
{
    public function getStopWords(): array;
    public function isStopWord(string $word): bool;
}