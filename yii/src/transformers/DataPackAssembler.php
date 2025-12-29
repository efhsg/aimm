<?php

declare(strict_types=1);

namespace app\transformers;

use app\dto\CollectionLog;
use app\dto\MacroData;
use app\queries\DataPackRepository;
use DateTimeImmutable;
use DirectoryIterator;
use RuntimeException;
use Throwable;

final class DataPackAssembler implements DataPackAssemblerInterface
{
    private const MEMORY_THRESHOLD_PERCENT = 80;
    private const TMP_SUFFIX = '.tmp';

    public function __construct(
        private DataPackRepository $repository,
    ) {
    }

    public function assemble(
        string $industryId,
        string $datapackId,
        MacroData $macro,
        CollectionLog $collectionLog,
        DateTimeImmutable $collectedAt,
    ): string {
        $dir = $this->repository->getIntermediateDir($industryId, $datapackId);
        $outputPath = $this->repository->getDataPackPath($industryId, $datapackId);
        $tmpPath = $outputPath . self::TMP_SUFFIX;

        $handle = @fopen($tmpPath, 'w');
        if ($handle === false) {
            throw new RuntimeException("Cannot open datapack file for writing: {$outputPath}");
        }

        try {
            $this->write($handle, $tmpPath, '{');
            $this->write($handle, $tmpPath, '"industry_id":' . $this->encode($industryId) . ',');
            $this->write($handle, $tmpPath, '"datapack_id":' . $this->encode($datapackId) . ',');
            $this->write(
                $handle,
                $tmpPath,
                '"collected_at":' . $this->encode($collectedAt->format(DateTimeImmutable::ATOM)) . ','
            );
            $this->write($handle, $tmpPath, '"macro":' . $this->encode($macro->toArray()) . ',');

            $this->write($handle, $tmpPath, '"companies":{');
            $first = true;
            foreach ($this->iterateIntermediateFiles($dir) as $ticker => $companyJson) {
                $this->checkMemoryThreshold();

                if (!$first) {
                    $this->write($handle, $tmpPath, ',');
                }
                $this->write($handle, $tmpPath, $this->encode($ticker) . ':' . $companyJson);
                $first = false;

                unset($companyJson);
            }
            $this->write($handle, $tmpPath, '},');

            $this->write(
                $handle,
                $tmpPath,
                '"collection_log":' . $this->encode($collectionLog->toArray())
            );
            $this->write($handle, $tmpPath, '}');

            fclose($handle);
            $handle = null;

            if (!rename($tmpPath, $outputPath)) {
                throw new RuntimeException("Failed to finalize datapack: {$outputPath}");
            }

            $this->repository->saveCollectionLog($industryId, $datapackId, $collectionLog);

            return $outputPath;
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($tmpPath);
            throw $e;
        }
    }

    /**
     * @return \Generator<string, string> ticker => raw JSON
     */
    private function iterateIntermediateFiles(string $dir): \Generator
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new DirectoryIterator($dir) as $file) {
            if ($file->isDot() || $file->getExtension() !== 'json') {
                continue;
            }

            $ticker = $file->getBasename('.json');
            $content = @file_get_contents($file->getPathname());

            if ($content === false) {
                throw new RuntimeException("Failed to read intermediate file: {$file->getPathname()}");
            }

            yield $ticker => $content;
        }
    }

    private function checkMemoryThreshold(): void
    {
        $limit = $this->getMemoryLimitBytes();
        $usage = memory_get_usage(true);
        $percent = ($usage / $limit) * 100;

        if ($percent > self::MEMORY_THRESHOLD_PERCENT) {
            gc_collect_cycles();

            $usage = memory_get_usage(true);
            $percent = ($usage / $limit) * 100;

            if ($percent > self::MEMORY_THRESHOLD_PERCENT) {
                throw new RuntimeException(sprintf(
                    'Memory threshold exceeded: %.1f%% of %s',
                    $percent,
                    $this->formatBytes($limit)
                ));
            }
        }
    }

    private function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 1) . 'G';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . 'M';
        }

        return round($bytes / 1024, 1) . 'K';
    }

    private function write($handle, string $path, string $chunk): void
    {
        if (fwrite($handle, $chunk) === false) {
            throw new RuntimeException("Failed to write datapack JSON: {$path}");
        }
    }

    private function encode(mixed $value): string
    {
        $json = json_encode($value);
        if ($json === false) {
            throw new RuntimeException('Failed to encode datapack JSON: ' . json_last_error_msg());
        }

        return $json;
    }
}
