<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\CompanyData;
use app\dto\GateResult;
use app\dto\IndustryDataPack;
use app\factories\CompanyDataFactory;
use app\factories\IndustryDataPackFactory;
use DateTimeImmutable;
use DirectoryIterator;
use InvalidArgumentException;
use RuntimeException;
use Yii;

/**
 * Repository for persisting and loading IndustryDataPack files.
 *
 * Directory structure:
 * {basePath}/{industryId}/{datapackId}/
 *   ├── datapack.json          # Final assembled datapack
 *   ├── validation.json        # Gate validation results
 *   ├── collection.log         # Human-readable log
 *   └── intermediate/          # Per-company intermediate files
 *       ├── SHEL.json
 *       └── AAPL.json
 */
final class DataPackRepository
{
    private const DATAPACK_FILENAME = 'datapack.json';
    private const VALIDATION_FILENAME = 'validation.json';
    private const INTERMEDIATE_DIR = 'intermediate';

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? Yii::getAlias('@runtime/datapacks');
    }

    /**
     * Get the full path to the datapack JSON file.
     */
    public function getDataPackPath(string $industryId, string $datapackId): string
    {
        return "{$this->getDataPackDir($industryId, $datapackId)}/" . self::DATAPACK_FILENAME;
    }

    /**
     * Get the path to the intermediate files directory.
     */
    public function getIntermediateDir(string $industryId, string $datapackId): string
    {
        return "{$this->getDataPackDir($industryId, $datapackId)}/" . self::INTERMEDIATE_DIR;
    }

    /**
     * Save datapack to disk.
     *
     * Uses atomic write (write to temp file, then rename) to prevent corruption.
     *
     * @throws RuntimeException If directory creation or file write fails
     */
    public function save(IndustryDataPack $dataPack): string
    {
        $dir = $this->getDataPackDir($dataPack->industryId, $dataPack->datapackId);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create datapack directory: {$dir}");
        }

        $path = $this->getDataPackPath($dataPack->industryId, $dataPack->datapackId);
        $json = json_encode($dataPack->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode datapack JSON: ' . json_last_error_msg());
        }

        $this->atomicWrite($path, $json);

        return $path;
    }

    /**
     * Save intermediate company data (for incremental backups).
     *
     * Used during collection to persist each company's data immediately,
     * allowing recovery if the process is interrupted.
     *
     * @throws RuntimeException If directory creation or file write fails
     */
    public function saveCompanyIntermediate(
        string $industryId,
        string $datapackId,
        CompanyData $company
    ): void {
        $dir = $this->getIntermediateDir($industryId, $datapackId);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create intermediate dir: {$dir}");
        }

        $ticker = $this->safePathSegment($company->ticker);
        $path = "{$dir}/{$ticker}.json";
        $json = json_encode($company->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode company intermediate JSON: ' . json_last_error_msg());
        }

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write company intermediate JSON: {$path}");
        }
    }

    /**
     * Load intermediate company data from disk.
     *
     * Used during final datapack assembly to avoid keeping all companies in memory.
     */
    public function loadCompanyIntermediate(
        string $industryId,
        string $datapackId,
        string $ticker
    ): ?CompanyData {
        $ticker = $this->safePathSegment($ticker);
        $path = "{$this->getIntermediateDir($industryId, $datapackId)}/{$ticker}.json";

        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if ($data === null) {
            return null;
        }

        return CompanyDataFactory::fromArray($data);
    }

    /**
     * List all intermediate company tickers for a datapack.
     *
     * @return list<string>
     */
    public function listIntermediateTickers(string $industryId, string $datapackId): array
    {
        $dir = $this->getIntermediateDir($industryId, $datapackId);

        if (!is_dir($dir)) {
            return [];
        }

        $tickers = [];
        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDot() || !$item->isFile() || $item->getExtension() !== 'json') {
                continue;
            }
            $tickers[] = $item->getBasename('.json');
        }

        sort($tickers);

        return $tickers;
    }

    /**
     * Save validation result alongside the datapack.
     *
     * @throws RuntimeException If directory creation or file write fails
     */
    public function saveValidation(
        string $industryId,
        string $datapackId,
        GateResult $result
    ): string {
        $dir = $this->getDataPackDir($industryId, $datapackId);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create datapack directory: {$dir}");
        }

        $path = "{$dir}/" . self::VALIDATION_FILENAME;

        $data = [
            'passed' => $result->passed,
            'errors' => array_map(static fn ($e) => $e->toArray(), $result->errors),
            'warnings' => array_map(static fn ($w) => $w->toArray(), $result->warnings),
            'validated_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode validation JSON: ' . json_last_error_msg());
        }

        $this->atomicWrite($path, $json);

        return $path;
    }

    /**
     * Load datapack from disk.
     */
    public function load(string $industryId, string $datapackId): ?IndustryDataPack
    {
        $path = $this->getDataPackPath($industryId, $datapackId);

        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if ($data === null) {
            return null;
        }

        return IndustryDataPackFactory::fromArray($data);
    }

    /**
     * List all datapacks for an industry.
     *
     * @return list<array{datapack_id: string, path: string, created_at: DateTimeImmutable}>
     */
    public function listByIndustry(string $industryId): array
    {
        $industryId = $this->safePathSegment($industryId);
        $dir = "{$this->basePath}/{$industryId}";

        if (!is_dir($dir)) {
            return [];
        }

        $datapacks = [];
        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }
            $datapacks[] = [
                'datapack_id' => $item->getFilename(),
                'path' => $item->getPathname(),
                'created_at' => (new DateTimeImmutable())->setTimestamp($item->getMTime()),
            ];
        }

        // Sort by newest first
        usort($datapacks, static fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $datapacks;
    }

    /**
     * Get the latest datapack for an industry.
     */
    public function getLatest(string $industryId): ?IndustryDataPack
    {
        $list = $this->listByIndustry($industryId);

        if ($list === []) {
            return null;
        }

        return $this->load($industryId, $list[0]['datapack_id']);
    }

    /**
     * Check if a datapack exists.
     */
    public function exists(string $industryId, string $datapackId): bool
    {
        return file_exists($this->getDataPackPath($industryId, $datapackId));
    }

    /**
     * Delete a datapack and all its associated files.
     *
     * @throws RuntimeException If deletion fails
     */
    public function delete(string $industryId, string $datapackId): void
    {
        $dir = $this->getDataPackDir($industryId, $datapackId);

        if (!is_dir($dir)) {
            return;
        }

        $this->deleteDirectory($dir);
    }

    /**
     * Get the base path for datapacks.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    private function getDataPackDir(string $industryId, string $datapackId): string
    {
        $industryId = $this->safePathSegment($industryId);
        $datapackId = $this->safePathSegment($datapackId);

        return "{$this->basePath}/{$industryId}/{$datapackId}";
    }

    /**
     * Validate path segment to prevent directory traversal attacks.
     *
     * @throws InvalidArgumentException If the value contains invalid characters
     */
    private function safePathSegment(string $value): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException("Invalid path segment: {$value}");
        }

        return $value;
    }

    /**
     * Write file atomically (write to temp, then rename).
     *
     * @throws RuntimeException If write or rename fails
     */
    private function atomicWrite(string $path, string $content): void
    {
        $tmpPath = "{$path}.tmp";

        if (file_put_contents($tmpPath, $content, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write file: {$tmpPath}");
        }

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new RuntimeException("Failed to move file into place: {$path}");
        }
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @throws RuntimeException If deletion fails
     */
    private function deleteDirectory(string $dir): void
    {
        foreach (new DirectoryIterator($dir) as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isDir()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                if (!unlink($item->getPathname())) {
                    throw new RuntimeException("Failed to delete file: {$item->getPathname()}");
                }
            }
        }

        if (!rmdir($dir)) {
            throw new RuntimeException("Failed to delete directory: {$dir}");
        }
    }
}
