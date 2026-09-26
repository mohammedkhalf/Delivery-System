<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\CentralLogics\Helpers;
use Illuminate\Console\Command;

class AutoTranslateLanguage extends Command
{
    protected $signature = 'lang:auto-translate
                            {locale=ar : Target language code}
                            {--source=en : Source language code}
                            {--batch=30 : Strings per API request}
                            {--limit=0 : Max unique strings to translate (0 = all)}';

    protected $description = 'Auto-translate resources/lang/{locale}/messages.php from English';

    public function handle(): int
    {
        $locale = (string) $this->argument('locale');
        $source = (string) $this->option('source');
        $batchSize = max(1, (int) $this->option('batch'));
        $limit = max(0, (int) $this->option('limit'));

        $targetPath = base_path("resources/lang/{$locale}/messages.php");
        $sourcePath = base_path("resources/lang/{$source}/messages.php");

        if (! is_file($targetPath) || ! is_file($sourcePath)) {
            $this->error('Missing source or target language file.');

            return self::FAILURE;
        }

        /** @var array<string, string> $sourceMessages */
        $sourceMessages = include $sourcePath;
        /** @var array<string, string> $targetMessages */
        $targetMessages = include $targetPath;

        if (! is_array($sourceMessages) || ! is_array($targetMessages)) {
            $this->error('Language files must return arrays.');

            return self::FAILURE;
        }

        $cachePath = storage_path("app/lang-cache/{$source}-{$locale}.json");
        if (! is_dir(dirname($cachePath))) {
            mkdir(dirname($cachePath), 0775, true);
        }

        /** @var array<string, string> $cache */
        $cache = is_file($cachePath)
            ? (json_decode((string) file_get_contents($cachePath), true) ?: [])
            : [];

        $needsTranslation = [];
        foreach ($sourceMessages as $key => $english) {
            $english = (string) $english;
            if ($english === '' || ! preg_match('/[A-Za-z]/', $english)) {
                continue;
            }
            $current = (string) ($targetMessages[$key] ?? '');
            if ($locale === 'ar' && preg_match('/[\x{0600}-\x{06FF}]/u', $current)) {
                // Keep existing Arabic, and remember mapping.
                $cache[$english] = $current;
                continue;
            }
            if (! isset($cache[$english])) {
                $needsTranslation[$english] = true;
            }
        }

        $unique = array_keys($needsTranslation);
        if ($limit > 0) {
            $unique = array_slice($unique, 0, $limit);
        }

        $this->info(sprintf(
            'Translating %d unique strings to [%s] (batch=%d, cached=%d)...',
            count($unique),
            $locale,
            $batchSize,
            count($cache)
        ));

        $bar = $this->output->createProgressBar(max(1, count($unique)));
        $bar->start();

        if ($unique === []) {
            $bar->advance();
        }

        foreach (array_chunk($unique, $batchSize) as $chunkIndex => $chunk) {
            $translated = Helpers::auto_translator_batch($chunk, $source, $locale);
            foreach ($chunk as $i => $english) {
                $cache[$english] = $translated[$i] ?? $english;
            }
            file_put_contents($cachePath, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            if (($chunkIndex + 1) % 10 === 0) {
                $this->persistFromSource($sourceMessages, $targetMessages, $cache, $targetPath);
            }

            $bar->advance(count($chunk));
            usleep(100000);
        }

        $bar->finish();
        $this->newLine();

        $updated = $this->persistFromSource($sourceMessages, $targetMessages, $cache, $targetPath);
        file_put_contents(base_path("resources/lang/{$locale}/new-messages.php"), "<?php\n\nreturn [];\n");

        $this->info("Updated {$updated} keys in resources/lang/{$locale}/messages.php");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $sourceMessages
     * @param  array<string, string>  $targetMessages
     * @param  array<string, string>  $cache
     */
    private function persistFromSource(array $sourceMessages, array $targetMessages, array $cache, string $path): int
    {
        $updated = 0;
        $merged = $targetMessages;

        foreach ($sourceMessages as $key => $english) {
            $english = (string) $english;
            if (isset($cache[$english])) {
                if (($merged[$key] ?? null) !== $cache[$english]) {
                    $merged[$key] = $cache[$english];
                    $updated++;
                }
            } elseif (! array_key_exists($key, $merged)) {
                $merged[$key] = $english;
            }
        }

        file_put_contents($path, "<?php\n\nreturn " . var_export($merged, true) . ";\n");

        return $updated;
    }
}
