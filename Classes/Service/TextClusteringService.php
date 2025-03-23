<?php
namespace Cywolf\NlpTools\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Service for clustering similar texts together
 */
class TextClusteringService implements SingletonInterface
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
     * Cluster texts using K-means algorithm
     *
     * @param array $texts Array of text strings
     * @param int $k Number of clusters
     * @param string|null $language Language code (auto-detected if null)
     * @param int $maxIterations Maximum number of iterations
     * @return array Associative array with 'clusters' and 'centroids' keys
     */
    public function kMeansClustering(
        array $texts, 
        int $k, 
        ?string $language = null, 
        int $maxIterations = 100
    ): array {
        if (empty($texts) || $k <= 0 || $k > count($texts)) {
            return ['clusters' => [], 'centroids' => []];
        }

        // Cache key for clustering results
        $cacheKey = 'kmeans_' . md5(implode('', $texts)) . '_' . $k . '_' . $language;
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Convert texts to TF-IDF vectors
        $vectorData = $this->vectorizer->createTfIdfVectors($texts, $language);
        $vectors = $vectorData['vectors'];
        $vocabulary = $vectorData['vocabulary'];

        // Initialize centroids by randomly selecting k data points
        $textIds = array_keys($vectors);
        shuffle($textIds);
        $centroidIds = array_slice($textIds, 0, $k);
        $centroids = [];
        
        foreach ($centroidIds as $index => $id) {
            $centroids[$index] = $vectors[$id];
        }

        // Main K-means loop
        $clusters = [];
        $prevClusters = [];
        $iterations = 0;
        
        while ($iterations < $maxIterations) {
            // Assign each vector to the nearest centroid
            $clusters = array_fill(0, $k, []);
            
            foreach ($vectors as $textId => $vector) {
                $bestCluster = 0;
                $bestSimilarity = -1;
                
                for ($i = 0; $i < $k; $i++) {
                    $similarity = $this->vectorizer->cosineSimilarity($vector, $centroids[$i]);
                    
                    if ($similarity > $bestSimilarity) {
                        $bestSimilarity = $similarity;
                        $bestCluster = $i;
                    }
                }
                
                $clusters[$bestCluster][] = $textId;
            }
            
            // Check for convergence
            if ($this->compareClusters($clusters, $prevClusters)) {
                break;
            }
            
            $prevClusters = $clusters;
            
            // Update centroids
            for ($i = 0; $i < $k; $i++) {
                if (empty($clusters[$i])) {
                    continue; // Skip empty clusters
                }
                
                // Calculate new centroid as the mean of all vectors in the cluster
                $newCentroid = array_fill_keys($vocabulary, 0);
                $clusterSize = count($clusters[$i]);
                
                foreach ($clusters[$i] as $textId) {
                    foreach ($vocabulary as $term) {
                        $newCentroid[$term] += $vectors[$textId][$term] / $clusterSize;
                    }
                }
                
                $centroids[$i] = $newCentroid;
            }
            
            $iterations++;
        }

        // Calculate cluster coherence (average similarity within clusters)
        $coherence = [];
        for ($i = 0; $i < $k; $i++) {
            if (count($clusters[$i]) <= 1) {
                $coherence[$i] = 1.0; // Perfect coherence for clusters with 0 or 1 member
                continue;
            }
            
            $totalSimilarity = 0;
            $pairCount = 0;
            
            for ($j = 0; $j < count($clusters[$i]); $j++) {
                for ($l = $j + 1; $l < count($clusters[$i]); $l++) {
                    $textId1 = $clusters[$i][$j];
                    $textId2 = $clusters[$i][$l];
                    $totalSimilarity += $this->vectorizer->cosineSimilarity($vectors[$textId1], $vectors[$textId2]);
                    $pairCount++;
                }
            }
            
            $coherence[$i] = $pairCount > 0 ? $totalSimilarity / $pairCount : 0;
        }

        // Build result with original text data
        $result = [
            'clusters' => [],
            'centroids' => $centroids,
            'coherence' => $coherence,
            'iterations' => $iterations
        ];
        
        for ($i = 0; $i < $k; $i++) {
            $result['clusters'][$i] = [
                'members' => $clusters[$i],
                'texts' => array_intersect_key($texts, array_flip($clusters[$i])),
                'coherence' => $coherence[$i] ?? 0
            ];
        }
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cacheKey, $result);
        }
        
        return $result;
    }

    /**
     * Cluster texts using hierarchical clustering
     *
     * @param array $texts Array of text strings
     * @param float $distanceThreshold Threshold for forming clusters (0.0-1.0)
     * @param string|null $language Language code (auto-detected if null)
     * @return array Hierarchical cluster structure
     */
    public function hierarchicalClustering(
        array $texts, 
        float $distanceThreshold = 0.5, 
        ?string $language = null
    ): array {
        if (empty($texts)) {
            return [];
        }

        // Cache key for clustering results
        $cacheKey = 'hierarchical_' . md5(implode('', $texts)) . '_' . $distanceThreshold . '_' . $language;
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Convert texts to TF-IDF vectors
        $vectorData = $this->vectorizer->createTfIdfVectors($texts, $language);
        $vectors = $vectorData['vectors'];
        
        // Compute similarity matrix
        $similarityMatrix = $this->vectorizer->calculateSimilarityMatrix($vectors);
        
        // Convert similarity to distance (1 - similarity)
        $distanceMatrix = [];
        foreach ($similarityMatrix as $i => $row) {
            $distanceMatrix[$i] = [];
            foreach ($row as $j => $sim) {
                $distanceMatrix[$i][$j] = 1 - $sim;
            }
        }
        
        // Initialize each text as its own cluster
        $clusters = [];
        foreach (array_keys($vectors) as $textId) {
            $clusters[] = [
                'id' => uniqid('cluster_'),
                'members' => [$textId],
                'children' => [],
                'distance' => 0,
                'height' => 0
            ];
        }
        
        // Agglomerative clustering (bottom-up approach)
        while (count($clusters) > 1) {
            // Find the two closest clusters
            $minDistance = PHP_FLOAT_MAX;
            $cluster1 = null;
            $cluster2 = null;
            
            for ($i = 0; $i < count($clusters); $i++) {
                for ($j = $i + 1; $j < count($clusters); $j++) {
                    // Calculate average linkage (mean distance between all pairs)
                    $totalDistance = 0;
                    $pairCount = 0;
                    
                    foreach ($clusters[$i]['members'] as $member1) {
                        foreach ($clusters[$j]['members'] as $member2) {
                            $totalDistance += $distanceMatrix[$member1][$member2];
                            $pairCount++;
                        }
                    }
                    
                    $avgDistance = $pairCount > 0 ? $totalDistance / $pairCount : PHP_FLOAT_MAX;
                    
                    if ($avgDistance < $minDistance) {
                        $minDistance = $avgDistance;
                        $cluster1 = $i;
                        $cluster2 = $j;
                    }
                }
            }
            
            // If the distance exceeds threshold, we're done
            if ($minDistance > $distanceThreshold) {
                break;
            }
            
            // Merge the two clusters
            $newCluster = [
                'id' => uniqid('cluster_'),
                'members' => array_merge($clusters[$cluster1]['members'], $clusters[$cluster2]['members']),
                'children' => [$clusters[$cluster1], $clusters[$cluster2]],
                'distance' => $minDistance,
                'height' => max($clusters[$cluster1]['height'], $clusters[$cluster2]['height']) + 1
            ];
            
            // Remove the original clusters (in reverse order to avoid index issues)
            if ($cluster1 > $cluster2) {
                array_splice($clusters, $cluster1, 1);
                array_splice($clusters, $cluster2, 1);
            } else {
                array_splice($clusters, $cluster2, 1);
                array_splice($clusters, $cluster1, 1);
            }
            
            // Add the new merged cluster
            $clusters[] = $newCluster;
        }
        
        // Add text content to the final clusters for convenience
        $result = [];
        foreach ($clusters as $cluster) {
            $clusterWithTexts = $cluster;
            $clusterWithTexts['texts'] = array_intersect_key($texts, array_flip($cluster['members']));
            $result[] = $clusterWithTexts;
        }
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cacheKey, $result);
        }
        
        return $result;
    }

    /**
     * Cluster texts by similarity using a simple threshold-based approach
     *
     * @param array $texts Array of text strings
     * @param float $similarityThreshold Threshold for considering texts similar (0.0-1.0)
     * @param string|null $language Language code (auto-detected if null)
     * @return array Array of clusters
     */
    public function similarityBasedClustering(
        array $texts, 
        float $similarityThreshold = 0.7, 
        ?string $language = null
    ): array {
        if (empty($texts)) {
            return [];
        }

        // Convert texts to TF-IDF vectors
        $vectorData = $this->vectorizer->createTfIdfVectors($texts, $language);
        $vectors = $vectorData['vectors'];
        
        // Initialize clusters
        $clusters = [];
        $assigned = [];
        
        // Process each text
        foreach ($vectors as $textId => $vector) {
            // Skip if already assigned
            if (isset($assigned[$textId])) {
                continue;
            }
            
            // Create a new cluster
            $newCluster = ['members' => [$textId]];
            
            // Find similar texts for this cluster
            foreach ($vectors as $otherTextId => $otherVector) {
                if ($textId === $otherTextId || isset($assigned[$otherTextId])) {
                    continue;
                }
                
                $similarity = $this->vectorizer->cosineSimilarity($vector, $otherVector);
                
                if ($similarity >= $similarityThreshold) {
                    $newCluster['members'][] = $otherTextId;
                    $assigned[$otherTextId] = true;
                }
            }
            
            $assigned[$textId] = true;
            $clusters[] = $newCluster;
        }
        
        // Add text content to the clusters
        foreach ($clusters as &$cluster) {
            $cluster['texts'] = array_intersect_key($texts, array_flip($cluster['members']));
        }
        
        return $clusters;
    }

    /**
     * Compare two cluster assignments to check for convergence
     *
     * @param array $clusters1 First cluster assignment
     * @param array $clusters2 Second cluster assignment
     * @return bool True if clusters are the same
     */
    private function compareClusters(array $clusters1, array $clusters2): bool
    {
        if (empty($clusters2)) {
            return false;
        }
        
        if (count($clusters1) !== count($clusters2)) {
            return false;
        }
        
        // Sort clusters to ensure consistent ordering
        foreach ($clusters1 as &$cluster) {
            sort($cluster);
        }
        
        foreach ($clusters2 as &$cluster) {
            sort($cluster);
        }
        
        // Compare each cluster
        for ($i = 0; $i < count($clusters1); $i++) {
            if (count($clusters1[$i]) !== count($clusters2[$i])) {
                return false;
            }
            
            for ($j = 0; $j < count($clusters1[$i]); $j++) {
                if ($clusters1[$i][$j] !== $clusters2[$i][$j]) {
                    return false;
                }
            }
        }
        
        return true;
    }
}