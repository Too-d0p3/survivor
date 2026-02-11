<?php

declare(strict_types=1);

namespace App\Cli\User;

use App\Shared\SampleData\SampleData;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'app:sample-data:create',
    description: 'Create sample data in configured order',
)]
final class CreateSampleDataCommand extends Command
{
    /** @var iterable<SampleData> */
    private readonly iterable $sampleDataCreators;

    /** @var array{order: array<int, class-string>} */
    private readonly array $sampleDataConfig;

    private readonly Connection $connection;

    /**
     * @param iterable<SampleData> $sampleDataCreators
     * @param array{order: array<int, class-string>} $sampleDataConfig
     */
    public function __construct(
        #[AutowireIterator('app.shared.sample_data')]
        iterable $sampleDataCreators,
        #[Autowire('%app_sample_data%')]
        array $sampleDataConfig,
        Connection $connection,
    ) {
        $this->sampleDataCreators = $sampleDataCreators;
        $this->sampleDataConfig = $sampleDataConfig;
        $this->connection = $connection;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'reset-db',
                null,
                InputOption::VALUE_NONE,
                'Drops and recreates the database before creating sample data',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        if ((bool) $input->getOption('reset-db')) {
            $this->resetDatabasePostgres($symfonyStyle);
            $this->runMigrations($output);
        }

        $order = $this->sampleDataConfig['order'];

        $map = [];
        foreach ($this->sampleDataCreators as $sampleDataCreator) {
            $map[$sampleDataCreator::class] = $sampleDataCreator;
        }

        foreach ($order as $class) {
            if (!isset($map[$class])) {
                throw new RuntimeException(sprintf(
                    'SampleData class `%s` is not registered or does not implement SampleData interface.',
                    $class,
                ));
            }

            $output->writeln(sprintf('▶ %s', $class));
            $map[$class]->create();
        }

        $output->writeln('<info>✔ Sample data created</info>');
        return Command::SUCCESS;
    }

    private function resetDatabasePostgres(SymfonyStyle $symfonyStyle): void
    {
        if (($_ENV['APP_ENV'] ?? 'dev') === 'prod') {
            throw new RuntimeException('Database reset is not allowed in production.');
        }

        $params = $this->connection->getParams();
        $databaseName = $params['dbname'] ?? null;

        if (!is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException('Database name not found in connection params.');
        }

        if (
            !$symfonyStyle->confirm(
                sprintf('⚠ This will DROP and recreate database "%s". Continue?', $databaseName),
                false,
            )
        ) {
            $symfonyStyle->warning('Database reset aborted.');
            return;
        }

        /** @var array{dbname?: string, default_dbname?: string, driver?: 'ibm_db2'|'mysqli'|'oci8'|'pdo_mysql'|'pdo_oci'|'pdo_pgsql'|'pdo_sqlite'|'pdo_sqlsrv'|'pgsql'|'sqlite3'|'sqlsrv', host?: string, password?: string, port?: int, user?: string} $adminParams */
        $adminParams = $params;
        $defaultDatabaseName = $params['default_dbname'] ?? 'postgres';
        $adminParams['dbname'] = is_string($defaultDatabaseName) ? $defaultDatabaseName : 'postgres';

        $adminConnection = DriverManager::getConnection($adminParams);

        $adminConnection->executeStatement(
            "SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = :db
AND pid <> pg_backend_pid()",
            ['db' => $databaseName],
        );

        $platform = $adminConnection->getDatabasePlatform();
        $quotedDatabaseName = $platform->quoteSingleIdentifier($databaseName);

        try {
            $adminConnection->executeStatement("DROP DATABASE IF EXISTS {$quotedDatabaseName}");
            $adminConnection->executeStatement("CREATE DATABASE {$quotedDatabaseName}");
        } catch (Exception $exception) {
            throw new RuntimeException(sprintf('Failed to reset database: %s', $exception->getMessage()), 0, $exception);
        } finally {
            $adminConnection->close();
        }

        $symfonyStyle->success(sprintf('Database "%s" recreated.', $databaseName));
    }

    private function runMigrations(OutputInterface $output): void
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new RuntimeException('Console application not available.');
        }

        $command = $application->find('doctrine:migrations:migrate');

        $input = new ArrayInput([
            '--no-interaction' => true,
        ]);

        $command->run($input, $output);
    }
}
