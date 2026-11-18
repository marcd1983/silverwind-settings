<?php

namespace Antlion\SilverwindSettings\Tasks;

use Antlion\SilverwindSettings\Tokens\TokenFile;
use Antlion\SilverwindSettings\Tokens\TokenImporter;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\SiteConfig\SiteConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Applies a design token file (e.g. a client's Figma variables exported as
 * JSON) to Settings > Theme. See TokenImporter for the formats and mapping.
 *
 *   vendor/bin/sake tasks:import-design-tokens                 # design/tokens.json
 *   vendor/bin/sake tasks:import-design-tokens --file=x.json --dry-run
 */
class ImportDesignTokensTask extends BuildTask
{
    protected static string $commandName = 'import-design-tokens';

    protected string $title = 'Import design tokens';

    protected static string $description = 'Applies design tokens (Figma variables exported as JSON) to Settings > Theme: colours, fonts, and sizes snapped to the nearest preset.';

    /** Relative to the project root */
    private static string $default_file = 'design/tokens.json';

    public function getOptions(): array
    {
        return [
            new InputOption('file', null, InputOption::VALUE_REQUIRED, 'Token file (relative to the project root, or absolute)'),
            new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without saving'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $file = (string) ($input->getOption('file') ?: static::config()->get('default_file'));
        $path = str_starts_with($file, '/') || preg_match('/^[a-z]:[\\\\\/]/i', $file)
            ? $file
            : Director::baseFolder() . '/' . $file;
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $importer = TokenImporter::create(TokenFile::fromFile($path));
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $config = SiteConfig::current_site_config();
        $plan = $dryRun ? $importer->plan($config) : $importer->apply($config);

        $output->writeln(($dryRun ? 'Dry run: nothing saved. ' : '') . "Tokens from {$file}");
        $output->writeln('');

        if ($plan['changes']) {
            $output->writeln($dryRun ? 'Would change:' : 'Changed:');
            foreach ($plan['changes'] as $c) {
                $from = $c['from'] === '' ? '(default)' : $c['from'];
                $to = $c['to'] === '' ? '(default)' : $c['to'];
                $output->writeln(sprintf('  %-20s %s -> %s   [%s]%s', $c['field'], $from, $to, $c['token'], $c['note'] ? "  note: {$c['note']}" : ''));
            }
        } else {
            $output->writeln('No changes: Settings > Theme already matches the tokens.');
        }

        if ($plan['unchanged']) {
            $output->writeln('');
            $output->writeln('Already matching: ' . implode(', ', $plan['unchanged']));
        }

        if ($plan['skipped']) {
            $output->writeln('');
            $output->writeln('Skipped (fix in the token file or config):');
            foreach ($plan['skipped'] as $s) {
                $output->writeln(sprintf('  %-20s [%s] %s', $s['field'], $s['token'], $s['reason']));
            }
        }

        if ($plan['unmatched']) {
            $output->writeln('');
            $output->writeln('Not used (no matching setting): ' . implode(', ', array_slice($plan['unmatched'], 0, 30))
                . (count($plan['unmatched']) > 30 ? sprintf(' … and %d more', count($plan['unmatched']) - 30) : ''));
        }

        return $plan['skipped'] && !$plan['changes'] && !$plan['unchanged'] ? Command::FAILURE : Command::SUCCESS;
    }
}
